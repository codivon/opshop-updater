<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Package;
use App\Models\PackageLicense;
use App\Models\PackagePricingPlan;
use App\Models\PackagePurchase;
use App\Models\Subscription;
use App\Services\LicenseService;
use App\Services\PackageApiAuthService;
use App\Services\PaymentService;
use App\Services\SubscriptionService;
use App\Services\Sms\SmsManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ApiPackageController extends Controller
{
    public function __construct(
        private PackageApiAuthService $authService,
        private PaymentService $paymentService,
        private LicenseService $licenseService,
        private SubscriptionService $subscriptionService,
        private SmsManager $smsManager
    ) {}

    /* ===================================================================
     *  GET /api/v1/packages
     *  لیست پکیج‌های قابل دسترس برای این مشتری (پکیج‌های پروژه‌ای که مشتری اشتراک دارد)
     *  + پرچم is_purchased برای هر پکیج (لایسنس فعال دارد؟) تا پروژه خریدار
     *    بتواند به‌جای «خرید»، دکمه «دانلود» نمایش دهد.
     *  + بلوک subscription برای هر آیتم و subscription_summary در سطح پاسخ:
     *    پکیج‌هایی که با اشتراک فعالِ طرح‌محور مشتری «رایگان»‌اند.
     * =================================================================== */
    public function index(Request $request): JsonResponse
    {
        try {
            $customer = $this->authService->authenticate($request);

            // پکیج‌های مرتبط با پروژه‌های این مشتری
            $projectIds = $customer->subscriptions()
                ->where('status', 'active')
                ->where('payment_status', 'paid')
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->pluck('project_id')
                ->unique();

            $packages = Package::with(['latestVersion', 'activePricingPlans', 'images', 'categoryModel.parent'])
                ->whereIn('project_id', $projectIds)
                ->where('status', Package::STATUS_ACTIVE)
                ->when($request->filled('search'), function ($q) use ($request) {
                    $search = $request->search;
                    $q->where(fn ($sq) => $sq->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%"));
                })
                ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
                ->orderByDesc('created_at')
                ->paginate($request->input('per_page', 15));

            // لایسنس‌های فعالِ این مشتری برای پکیج‌های همین صفحه (یک کوئری)
            $licenses = $this->activeLicensesFor(
                $customer,
                $packages->getCollection()->pluck('id')->all()
            );

            // اشتراک فعالِ طرح‌محور مشتری + نقشه پکیج‌های پوشش‌داده‌شده (یک کوئری)
            [$coverage, $summarySubscription] = $this->subscriptionCoverage($customer);

            return response()->json([
                'data' => $packages->getCollection()->map(function (Package $package) use ($licenses, $coverage) {
                    $license = $licenses->get($package->id);
                    $covered = $coverage[$package->id] ?? null;

                    return $this->appendPurchaseInfo(
                        $package,
                        $license,
                        $covered ? $this->subscriptionInfoFor($covered['subscription'], $package, $license) : null
                    );
                })->values(),
                'meta' => [
                    'current_page' => $packages->currentPage(),
                    'last_page'    => $packages->lastPage(),
                    'total'        => $packages->total(),
                    'per_page'     => $packages->perPage(),
                ],
                // درگاه‌های فعال — برای نمایش کارت انتخاب درگاه در فروشگاه خریدار
                'gateways' => $this->presentGateways(),
                // خلاصه اشتراک فعالِ طرح‌محور (کنار meta)
                'subscription_summary' => $this->subscriptionSummary($summarySubscription),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], exception_status($e, 403));
        }
    }

    /* ===================================================================
     *  GET /api/v1/packages/{slug}
     *  جزئیات پکیج
     * =================================================================== */
    public function show(Request $request, string $slug): JsonResponse
    {
        try {
            $customer = $this->authService->authenticate($request);

            $package = Package::with(['activeVersions', 'activePricingPlans', 'project', 'images', 'categoryModel.parent'])
                ->where('slug', $slug)
                ->where('status', Package::STATUS_ACTIVE)
                ->first();

            if (!$package) {
                return response()->json(['error' => 'پکیج یافت نشد.'], 404);
            }

            // بررسی دسترسی مشتری به پروژه‌ی این پکیج
            $hasAccess = $customer->subscriptions()
                ->where('project_id', $package->project_id)
                ->where('status', 'active')
                ->where('payment_status', 'paid')
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->exists();

            if (!$hasAccess) {
                return response()->json(['error' => 'دسترسی غیرمجاز.'], 403);
            }

            // اگر لایسنس فعلی دارد، اطلاعاتش رو برمی‌گردانیم
            $license = $this->authService->getActiveLicense($customer, $slug);

            // این پکیج با اشتراک فعالِ طرح‌محور مشتری رایگان است؟
            $coveringSubscription = $this->subscriptionService->activeSubscriptionCovering($customer, $package);
            $subscriptionInfo = $coveringSubscription
                ? $this->subscriptionInfoFor($coveringSubscription, $package, $license)
                : null;

            return response()->json([
                'data' => $this->appendPurchaseInfo($package, $license, $subscriptionInfo),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], exception_status($e, 403));
        }
    }

    /* ===================================================================
     *  POST /api/v1/packages/{slug}/purchase
     *  ایجاد درخواست خرید و دریافت payment_url
     *  Body: { callback_url?, pricing_plan_id?, gateway? }
     *  Response: { payment_url, transaction_id, amount, gateway, purchase_id }
     *  - پکیج/طرح رایگان: { is_free, license_key, expires_at, download_token }
     *  - ⭐ پکیجِ پوشش‌داده‌شده با اشتراک فعالِ طرح‌محور: بدون درگاه و بدون نیاز به
     *    pricing_plan_id/callback_url → مستقیم لایسنس رایگان:
     *    { is_free: true, via_subscription: true, plan, license_key, expires_at,
     *      days_remaining, download_token, message } (idempotent برای لایسنس موجود)
     *  - gateway اختیاری است (پیش‌فرض zarinpal)؛ کلاینت می‌تواند درگاه فعال دیگری
     *    را انتخاب کند (مثلاً «local» برای تست جریان پرداخت).
     *  - payment_url برای درایورهای URL مستقیم است؛ برای درایورهای فرم‌محور
     *    یک مسیر امضادار روی پنل است که فرم را رندر و خودکار به درگاه POST می‌کند.
     * =================================================================== */
    public function purchase(Request $request, string $slug): JsonResponse
    {
        $request->validate([
            'callback_url'    => 'nullable|url',
            'pricing_plan_id' => 'nullable|exists:package_pricing_plans,id',
            'gateway'         => 'nullable|string|max:32',
        ]);

        try {
            $customer = $this->authService->authenticate($request);

            // درگاه اختیاری: کلاینت می‌تواند درگاه مشخصی بخواهد (مثلاً local برای تست)
            // باید در فهرست درگاه‌های پشتیبانی‌شده + فعال باشد.
            $gatewayKey = $request->input('gateway');
            if ($gatewayKey) {
                $gatewayActive = \App\Models\Gateway::query()
                    ->where('key', $gatewayKey)
                    ->whereIn('key', array_keys(config('general.supported_gateways')))
                    ->where('is_active', true)
                    ->exists();

                if (!$gatewayActive) {
                    return response()->json([
                        'error' => "درگاه «{$gatewayKey}» وجود ندارد یا فعال نیست. درگاه‌های فعال را از فهرست پکیج‌ها یا پنل بررسی کنید.",
                    ], 422);
                }
            }

            $package = Package::where('slug', $slug)
                ->where('status', Package::STATUS_ACTIVE)
                ->first();

            if (!$package) {
                return response()->json(['error' => 'پکیج یافت نشد.'], 404);
            }

            // ---------- ⭐ مسیر اشتراک فعال: پکیجِ همراهِ طرح → رایگان، بدون درگاه ----------
            $subscription = $this->subscriptionService->activeSubscriptionCovering($customer, $package);

            if ($subscription) {
                $plan    = $subscription->plan;
                $covered = $plan?->packages->firstWhere('id', $package->id);
                $freeMonths = (int) ($covered?->pivot->free_months ?? 1);

                $latestVersion = $package->latestVersion()->first();
                if (!$latestVersion) {
                    return response()->json(['error' => 'نسخه فعالی برای این پکیج وجود ندارد.'], 422);
                }

                // idempotent: لایسنس فعال موجود → همان لایسنس برمی‌گردد
                $license = $this->authService->getActiveLicense($customer, $slug);

                if (!$license) {
                    $license = $subscription->order
                        ? $this->subscriptionService->grantPackageAccess($customer, $package, $freeMonths, $subscription->order)
                        : $this->subscriptionService->grantFreeAccess(
                            $customer,
                            $package,
                            $freeMonths,
                            'دسترسی رایگان از طریق اشتراک «' . ($plan?->name ?? 'طرح اشتراک') . '» (API).'
                        );
                }

                return response()->json([
                    'is_free'          => true,
                    'via_subscription' => true,
                    'plan'             => $plan?->name,
                    'license_key'      => $license->license_key,
                    'expires_at'       => $license->expires_at?->toDateTimeString(),
                    'days_remaining'   => $license->days_remaining,
                    'download_token'   => $this->createDownloadToken($license, $latestVersion, $customer),
                    'message'          => 'این پکیج با اشتراک فعال شما رایگان است.',
                ]);
            }

            // ---------- مسیر عادی خرید ----------
            if (!$request->filled('pricing_plan_id')) {
                return response()->json(['error' => 'انتخاب طرح قیمت‌گذاری الزامی است.'], 422);
            }

            $plan = PackagePricingPlan::where('id', $request->pricing_plan_id)
                ->where('package_id', $package->id)
                ->where('is_active', true)
                ->first();

            if (!$plan) {
                return response()->json(['error' => 'طرح قیمت‌گذاری نامعتبر است.'], 422);
            }

            // جلوگیری از خرید مجدد طرح‌های یک‌بار مصرف
            if ($plan->is_one_time && $plan->hasCustomerUsed($customer->id)) {
                return response()->json([
                    'error' => 'شما قبلاً از این طرح استفاده کرده‌اید. این طرح فقط یک‌بار قابل خریداری است. لطفاً طرح دیگری انتخاب کنید.',
                ], 422);
            }

            $latestVersion = $package->latestVersion()->first();

            if (!$latestVersion) {
                return response()->json(['error' => 'نسخه فعالی برای این پکیج وجود ندارد.'], 422);
            }

            // اگر پکیج رایگان است، نیازی به پرداخت نیست - مستقیم لایسنس صادر می‌شود
            if ($package->is_free || $plan->final_price <= 0) {
                $purchase = PackagePurchase::create([
                    'package_id'      => $package->id,
                    'version_id'      => $latestVersion->id,
                    'pricing_plan_id' => $plan->id,
                    'customer_id'     => $customer->id,
                    'amount'          => 0,
                    'status'          => PackagePurchase::STATUS_PAID,
                    'paid_at'         => now(),
                ]);
                $license = $this->licenseService->issueLicense($purchase, $plan);
                return response()->json([
                    'is_free'      => true,
                    'license_key'  => $license->license_key,
                    'expires_at'   => $license->expires_at?->toDateTimeString(),
                    'download_token' => $this->createDownloadToken($license, $latestVersion, $customer),
                ]);
            }

            // مسیر پولی: بازگشت از درگاه به سایت مشتری الزامی است
            if (!$request->filled('callback_url')) {
                return response()->json(['error' => 'آدرس بازگشت (callback_url) برای پرداخت الزامی است.'], 422);
            }

            // ایجاد رکورد purchase
            $purchase = PackagePurchase::create([
                'package_id'      => $package->id,
                'version_id'      => $latestVersion->id,
                'pricing_plan_id' => $plan->id,
                'customer_id'     => $customer->id,
                'callback_url'    => $request->callback_url,
                'amount'          => $plan->final_price,
                'status'          => PackagePurchase::STATUS_PENDING,
            ]);

            // ایجاد پرداخت در درگاه (gateway اختیاری کلاینت یا پیش‌فرض)
            // اگر درگاه تراکنش نسازد، رکورد pending یتیم باقی نماند.
            try {
                $payment = $this->paymentService->createPayment($purchase, $gatewayKey);
            } catch (\Throwable $e) {
                $purchase->delete();
                throw $e;
            }

            return response()->json([
                'payment_url'    => $payment['payment_url'],
                'transaction_id' => $payment['transaction_id'],
                'amount'         => $payment['amount'],
                'gateway'        => $payment['gateway'],
                'purchase_id'    => $purchase->id,
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /* ===================================================================
     *  POST /api/v1/payments/{transaction_id}/verify
     *  تأیید پرداخت توسط پروژه خریدار
     *  Response: { paid, license_key, expires_at, download_token }
     * =================================================================== */
    public function verifyPayment(Request $request, string $transactionId): JsonResponse
    {
        try {
            $customer = $this->authService->authenticate($request);

            $purchase = PackagePurchase::where('transaction_id', $transactionId)
                ->where('customer_id', $customer->id)
                ->first();

            if (!$purchase) {
                return response()->json([
                    'paid'   => false,
                    'error'  => 'تراکنش یافت نشد.',
                ], 404);
            }

            // اگر قبلاً پرداخت و لایسنس صادر شده
            if ($purchase->isPaid() && $purchase->license) {
                $license = $purchase->license;
                $latestVersion = $purchase->package->latestVersion()->first();

                return response()->json([
                    'paid'         => true,
                    'license_key'  => $license->license_key,
                    'expires_at'   => $license->expires_at?->toDateTimeString(),
                    'days_remaining' => $license->days_remaining,
                    'download_token' => $latestVersion
                        ? $this->createDownloadToken($license, $latestVersion, $customer)
                        : null,
                    'signature'    => $latestVersion?->file_hash,
                    'version'      => $latestVersion?->version,
                ]);
            }

            // اگر هنوز pending است، تلاش به تأیید درگاه
            if ($purchase->status === PackagePurchase::STATUS_PENDING) {
                $result = $this->paymentService->verifyPayment($transactionId);

                if ($result['paid'] ?? false) {
                    $purchase->refresh();
                    $license = $purchase->license;
                    $latestVersion = $purchase->package->latestVersion()->first();

                    // 📱 پیامک: خرید پکیج (تأیید از مسیر API) + اطلاع مدیر
                    // (once → اگر قبلاً از مسیر کال‌بک ارسال شده، تکرار نمی‌شود)
                    try {
                        $vars = [
                            'customer_name' => $purchase->customer?->name ?? 'مشتری',
                            'package_name'  => $purchase->package?->name ?? 'پکیج',
                            'amount'        => number_format((int) $purchase->amount),
                            'license_key'   => (string) ($result['license_key'] ?? $license?->license_key ?? ''),
                        ];

                        $this->smsManager->send('purchase_paid', $purchase->customer?->phone, $vars, $purchase, once: true);
                        $this->smsManager->notifyAdmin('admin_purchase_paid', $vars, $purchase);
                    } catch (\Throwable) {
                        // پیامک هرگز نباید جریان خرید را بشکند
                    }

                    return response()->json([
                        'paid'         => true,
                        'license_key'  => $license?->license_key,
                        'expires_at'   => $license?->expires_at?->toDateTimeString(),
                        'days_remaining' => $license?->days_remaining,
                        'download_token' => ($license && $latestVersion)
                            ? $this->createDownloadToken($license, $latestVersion, $customer)
                            : null,
                        'signature'    => $latestVersion?->file_hash,
                        'version'      => $latestVersion?->version,
                    ]);
                }

                return response()->json([
                    'paid'   => false,
                    'error'  => $result['message'] ?? 'پرداخت هنوز تأیید نشده است.',
                ], 400);
            }

            return response()->json([
                'paid'   => false,
                'error'  => 'وضعیت تراکنش: ' . $purchase->status,
            ], 400);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], exception_status($e, 500));
        }
    }

    /* ===================================================================
     *  POST /api/v1/packages/{slug}/verify-license
     *  بررسی اعتبار لایسنس
     *  Body: { license_key }
     *  Response: { valid, expires_at, days_remaining, version, download_token, signature }
     * =================================================================== */
    public function verifyLicense(Request $request, string $slug): JsonResponse
    {
        $request->validate([
            'license_key' => 'required|string',
        ]);

        try {
            $customer = $this->authService->authenticate($request);

            $package = Package::where('slug', $slug)->first();
            if (!$package) {
                return response()->json(['valid' => false, 'message' => 'پکیج یافت نشد.'], 404);
            }

            $license = \App\Models\PackageLicense::where('license_key', $request->license_key)
                ->where('package_id', $package->id)
                ->where('customer_id', $customer->id)
                ->first();

            if (!$license) {
                return response()->json([
                    'valid'   => false,
                    'message' => 'لایسنس متعلق به این مشتری نیست.',
                ], 403);
            }

            $result = $this->licenseService->verify($license);

            if (!$result['valid']) {
                // اگه لایسنس منقضی شده و از طرح one-time بوده، پیام واضح بده
                $plan = $license->purchase?->pricingPlan;
                if ($plan && $plan->is_one_time) {
                    return response()->json([
                        'valid'        => false,
                        'message'      => 'لایسنس این طرح (یک‌بار مصرف) منقضی شده است. این طرح قابل تمدید نیست. لطفاً طرح دیگری خریداری کنید.',
                        'is_one_time'  => true,
                        'expires_at'   => $result['expires_at'] ?? null,
                    ]);
                }
                return response()->json($result);
            }

            $latestVersion = $package->latestVersion()->first();

            if (!$latestVersion) {
                return response()->json(['error' => 'نسخه فعالی برای این پکیج وجود ندارد.'], 422);
            }
            return response()->json([
                'valid'         => true,
                'expires_at'    => $result['expires_at'],
                'days_remaining'=> $result['days_remaining'],
                'is_unlimited'  => $result['is_unlimited'] ?? false,
                'version'       => $latestVersion?->version,
                'signature'     => $latestVersion?->file_hash,
                'download_token' => $latestVersion
                    ? $this->createDownloadToken($license, $latestVersion, $customer)
                    : null,
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], exception_status($e, 500));
        }
    }

    /* ===================================================================
     *  GET /api/v1/packages/{slug}/check-update
     *  Query: { current_version }
     *  Response: { has_update, latest_version, changelog, what_added, is_mandatory }
     * =================================================================== */
    public function checkUpdate(Request $request, string $slug): JsonResponse
    {
        $request->validate([
            'current_version' => 'required|string',
        ]);

        try {
            $customer = $this->authService->authenticate($request);

            $package = Package::where('slug', $slug)
                ->where('status', Package::STATUS_ACTIVE)
                ->first();

            if (!$package) {
                return response()->json(['error' => 'پکیج یافت نشد.'], 404);
            }
            $latestVersion = $package->latestVersion()->first();

            if (!$latestVersion) {
                return response()->json([
                    'has_update' => false,
                    'message'    => 'نسخه فعالی موجود نیست.',
                ]);
            }

            $hasUpdate = version_compare($latestVersion->version, $request->current_version, '>');

            return response()->json([
                'has_update'      => $hasUpdate,
                'latest_version'  => $latestVersion->version,
                'current_version' => $request->current_version,
                'is_mandatory'    => $hasUpdate && $latestVersion->is_mandatory,
                'changelog'       => $latestVersion->changelog,
                'what_added'      => $latestVersion->what_added,
                'what_changed'    => $latestVersion->what_changed,
                'what_fixed'      => $latestVersion->what_fixed,
                'min_php_version'    => $latestVersion->min_php_version,
                'min_laravel_version'=> $latestVersion->min_laravel_version,
                'dependencies'       => $latestVersion->dependencies,
                'file_size'          => $latestVersion->file_size,
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], exception_status($e, 403));
        }
    }

    /* ===================================================================
     *  Helper - درگاه‌های فعال برای نمایش به مشتری
     * =================================================================== */
    private function presentGateways(): array
    {
        return \App\Models\Gateway::query()
            ->active()
            ->orderBy('ordering')
            ->orderBy('name')
            ->get(['id', 'key', 'name', 'logo', 'ordering'])
            ->map(fn (\App\Models\Gateway $gateway) => [
                'key'   => $gateway->key,
                'title' => $gateway->name,
                'logo'  => gateway_logo_url($gateway->key, $gateway->logo),
                'is_test' => $gateway->key === 'local',
            ])
            ->values()
            ->all();
    }

    /* ===================================================================
     *  Helper - لایسنس‌های فعال مشتری برای مجموعه‌ای از پکیج‌ها (کلید = package_id)
     * =================================================================== */
    private function activeLicensesFor(Customer $customer, array $packageIds)
    {
        if (empty($packageIds)) {
            return collect();
        }

        return PackageLicense::where('customer_id', $customer->id)
            ->where('status', PackageLicense::STATUS_ACTIVE)
            ->whereIn('package_id', $packageIds)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get()
            ->keyBy('package_id');
    }

    /* ===================================================================
     *  Helper - اشتراک‌های فعالِ طرح‌محور مشتری + نقشه پکیج‌های پوشش‌داده‌شده
     *  (یک کوئری؛ کلید نقشه = package_id، اولین اشتراک فعال ملاک است)
     * =================================================================== */
    private function subscriptionCoverage(Customer $customer): array
    {
        $subs = $customer->subscriptions()
            ->whereNotNull('subscription_plan_id')
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with('plan.packages')
            ->get();

        $coverage = [];
        foreach ($subs as $sub) {
            foreach ($sub->plan?->packages ?? [] as $pkg) {
                if (!array_key_exists($pkg->id, $coverage)) {
                    $coverage[$pkg->id] = [
                        'subscription' => $sub,
                        'free_months'  => (int) ($pkg->pivot->free_months ?? 1),
                    ];
                }
            }
        }

        return [$coverage, $subs->first()];
    }

    /* ===================================================================
     *  Helper - بلوک اطلاعات اشتراک برای یک پکیجِ پوشش‌داده‌شده (فیلد subscription)
     * =================================================================== */
    private function subscriptionInfoFor(Subscription $subscription, Package $package, ?PackageLicense $license): array
    {
        $plan     = $subscription->plan;
        $covered  = $plan?->packages->firstWhere('id', $package->id);

        return [
            'is_free_with_subscription' => true,
            'plan_name'                 => $plan?->name,
            'plan_slug'                 => $plan?->slug,
            'subscription_expires_at'   => $subscription->expires_at?->toDateTimeString(),
            'days_remaining'            => $subscription->expires_at !== null
                ? (int) max(0, now()->diffInDays($subscription->expires_at))
                : null,
            'free_months'               => (int) ($covered?->pivot->free_months ?? 1),
            // لایسنس صادرشده برای این پکیج (اگر لایسنس فعال وجود دارد)
            'license_key'               => $license?->license_key,
        ];
    }

    /* ===================================================================
     *  Helper - خلاصه اشتراک فعالِ طرح‌محور (سطح پاسخ، کنار meta)
     * =================================================================== */
    private function subscriptionSummary(?Subscription $subscription): array
    {
        if (!$subscription) {
            return [
                'has_active_subscription' => false,
                'plan_name'               => null,
                'expires_at'              => null,
                'days_remaining'          => null,
            ];
        }

        return [
            'has_active_subscription' => true,
            'plan_name'               => $subscription->plan?->name,
            'expires_at'              => $subscription->expires_at?->toDateTimeString(),
            'days_remaining'          => $subscription->expires_at !== null
                ? (int) max(0, now()->diffInDays($subscription->expires_at))
                : null,
        ];
    }

    /* ===================================================================
     *  Helper - افزودن اطلاعات خرید/لایسنس به خروجی پکیج
     *  is_purchased => true یعنی پروژه خریدار باید دکمه «دانلود» نشان دهد
     *  subscription => بلوک اشتراک فعال (پکیج رایگانِ طرح) یا null
     * =================================================================== */
    private function appendPurchaseInfo(Package $package, ?PackageLicense $license, ?array $subscriptionInfo = null): array
    {
        return array_merge($package->toArray(), [
            'is_purchased' => $license !== null,
            'purchased_license' => $license ? [
                'license_key'    => $license->license_key,
                'expires_at'     => $license->expires_at?->toDateTimeString(),
                'days_remaining' => $license->days_remaining,
                'is_unlimited'   => $license->expires_at === null,
            ] : null,
            // نام قدیمی برای سازگاری با یکپارچه‌سازی‌های فعلی
            'installed_license' => $license ? [
                'license_key'    => $license->license_key,
                'expires_at'     => $license->expires_at?->toDateTimeString(),
                'days_remaining' => $license->days_remaining,
            ] : null,
            // پکیجِ رایگانِ طرحِ اشتراک فعال (null = پوشش داده نمی‌شود)
            'subscription' => $subscriptionInfo,
            // دسته‌بندی ساختاریافته با زنجیره والدین — برای انتخاب/ساخت دسته درختی در فروشگاه خریدار (مثلاً ووکامرس)
            'category_details' => $this->categoryDetailsFor($package),
        ]);
    }

    /**
     * دسته‌بندی پکیج با زنجیره کامل والدین:
     *  - parents     → از ریشه تا والدِ مستقیم (برای ساخت درخت دسته در فروشگاه)
     *  - path        → برچسب نمایشی «والد › فرزند»
     *  - path_slugs  → مسیر نامک‌ها از ریشه (مشابه term path وردپرس)
     */
    private function categoryDetailsFor(Package $package): ?array
    {
        $category = $package->categoryModel;

        if (!$category) {
            return null;
        }

        $chain = collect([$category]);
        $current = $category;

        for ($i = 0; $i < Category::MAX_DEPTH && $current->parent_id !== null; $i++) {
            $parent = $current->parent;

            if (!$parent) {
                break;
            }

            $chain->unshift($parent);
            $current = $parent;
        }

        $present = fn ($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug];

        return [
            'id'         => $category->id,
            'name'       => $category->name,
            'slug'       => $category->slug,
            'parents'    => $chain->slice(0, -1)->values()->map($present)->all(),
            'path'       => $chain->map(fn ($c) => $c->name)->implode(' › '),
            'path_slugs' => $chain->map(fn ($c) => $c->slug)->values()->all(),
        ];
    }

    /* ===================================================================
     *  GET /api/v1/categories?type=package|product
     *  درخت دسته‌بندی‌های فعال — برای اینکه پروژه خریدار (مثلاً افزونه وردپرس)
     *  بتواند هنگام نصب پکیج، دسته فروشگاه را به‌صورت درختی انتخاب/بسازد.
     * =================================================================== */
    public function categories(Request $request): JsonResponse
    {
        try {
            $this->authService->authenticate($request);

            $type = $request->input('type', Category::TYPE_PACKAGE);

            if (! in_array($type, [Category::TYPE_PACKAGE, Category::TYPE_PRODUCT], true)) {
                return response()->json(['error' => 'نوع دسته‌بندی نامعتبر است.'], 422);
            }

            $categories = Category::query()
                ->ofType($type)
                ->active()
                ->ordered()
                ->get(['id', 'parent_id', 'name', 'slug', 'description']);

            $ids = $categories->pluck('id');

            // گروه‌بندی بر اساس والدِ معتبر؛ یتیم‌ها ریشه محسوب می‌شوند
            $byParent = $categories->groupBy(
                fn ($c) => ($c->parent_id !== null && $ids->contains($c->parent_id)) ? (int) $c->parent_id : 0
            );

            $build = null;
            $build = function (int $parentId) use (&$build, $byParent): array {
                return $byParent->get($parentId, collect())
                    ->map(fn ($c) => [
                        'id'          => $c->id,
                        'name'        => $c->name,
                        'slug'        => $c->slug,
                        'description' => $c->description,
                        'children'    => $build((int) $c->id),
                    ])
                    ->values()
                    ->all();
            };

            return response()->json([
                'data' => $build(0),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], exception_status($e, 403));
        }
    }

    /* ===================================================================
     *  Helper - ساخت download token موقت (15 دقیقه)
     * =================================================================== */
    private function createDownloadToken(
        \App\Models\PackageLicense $license,
        \App\Models\PackageVersion $version,
        Customer $customer
    ): string {
        $token = \App\Models\PackageDownloadToken::create([
            'token'       => \App\Models\PackageDownloadToken::generate(),
            'license_id'  => $license->id,
            'version_id'  => $version->id,
            'customer_id' => $customer->id,
            'expires_at'  => now()->addMinutes(15),
        ]);

        return $token->token;
    }
}
