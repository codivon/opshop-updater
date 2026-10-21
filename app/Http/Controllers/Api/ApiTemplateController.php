<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Gateway;
use App\Models\Template;
use App\Models\TemplatePurchase;
use App\Services\PackageApiAuthService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * API قالب‌ها — دقیقاً با همان محدودیت‌ها و الگوی API پکیج‌ها:
 *
 *  - احراز هویت: توکن (Bearer / X-Project-Key / ?token=) = update_code مشتری
 *    + هدر X-Project-Url (بررسی دامنه با PackageApiAuthService — همان سرویس پکیج‌ها)
 *  - فقط قالب‌های «فعال» دیده می‌شوند
 *  - دانلود فقط برای خریدِ پرداخت‌شده (is_purchased) مجاز است
 *  - لینک دانلود: یک‌بارمصرف با انقضای ۱۵ دقیقه
 *  - خرید پولی: callback_url الزامی + درگاه فعال + verify از سمت پروژه خریدار
 */
class ApiTemplateController extends Controller
{
    public function __construct(
        private PackageApiAuthService $authService,
        private PaymentService $paymentService,
    ) {}

    /* ===================================================================
     *  GET /api/v1/templates
     *  لیست قالب‌های فعال + پرچم is_purchased برای هر قالب
     *  (پروژه خریدار به‌جای «خرید»، دکمه «دانلود» نشان می‌دهد)
     * =================================================================== */
    public function index(Request $request): JsonResponse
    {
        try {
            $customer = $this->authService->authenticate($request);

            $templates = Template::with(['latestVersion', 'category.parent'])
                // تعداد خریدهای پرداخت‌شدهٔ هر قالب (چند نفر استفاده می‌کنند/خریده‌اند) — درخواست کاربر برای API
                ->withCount(['purchases as purchases_count' => fn ($q) => $q->where('status', TemplatePurchase::STATUS_PAID)])
                ->where('status', Template::STATUS_ACTIVE)
                // قالب‌های واگذارشده (خرید اختصاصی) از فهرست API حذف می‌شوند — مثل فروشگاه
                ->where('is_sold_exclusively', false)
                ->when($request->filled('search'), function ($q) use ($request) {
                    $search = $request->search;
                    $q->where(fn ($sq) => $sq->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%"));
                })
                ->when($request->filled('category'), fn ($q) => $q->where('category_id', $request->category))
                ->orderByDesc('created_at')
                ->paginate($request->input('per_page', 15));

            // خریدهای پرداخت‌شده‌ی این مشتری برای قالب‌های همین صفحه (یک کوئری)
            $paidPurchaseIds = $this->paidPurchaseIdsFor(
                $customer,
                $templates->getCollection()->pluck('id')->all()
            );

            return response()->json([
                'data' => $templates->getCollection()
                    ->map(fn (Template $template) => $this->appendPurchaseInfo($template, $paidPurchaseIds))
                    ->values(),
                'meta' => [
                    'current_page' => $templates->currentPage(),
                    'last_page'    => $templates->lastPage(),
                    'total'        => $templates->total(),
                    'per_page'     => $templates->perPage(),
                ],
                // درگاه‌های فعال — برای نمایش کارت انتخاب درگاه در فروشگاه خریدار
                'gateways' => $this->presentGateways(),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], exception_status($e, 403));
        }
    }

    /* ===================================================================
     *  GET /api/v1/templates/{slug}
     *  جزئیات قالب
     * =================================================================== */
    public function show(Request $request, string $slug): JsonResponse
    {
        try {
            $customer = $this->authService->authenticate($request);

            $template = Template::with(['activeVersions', 'category.parent'])
                ->withCount(['purchases as purchases_count' => fn ($q) => $q->where('status', TemplatePurchase::STATUS_PAID)])
                ->where('slug', $slug)
                ->where('status', Template::STATUS_ACTIVE)
                ->first();

            if (!$template) {
                return response()->json(['error' => 'قالب یافت نشد.'], 404);
            }

            $paidPurchaseIds = $this->paidPurchaseIdsFor($customer, [$template->id]);

            return response()->json([
                'data' => $this->appendPurchaseInfo($template, $paidPurchaseIds),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], exception_status($e, 403));
        }
    }

    /* ===================================================================
     *  POST /api/v1/templates/{slug}/purchase
     *  ایجاد درخواست خرید و دریافت payment_url
     *  Body: { callback_url?, gateway? }
     *  Response: { payment_url, transaction_id, amount, gateway, purchase_id }
     *  - قالب رایگان: { is_free, download_token, version }
     *  - gateway اختیاری است (پیش‌فرض: اولین درگاه فعال)؛ باید فعال باشد.
     *  - payment_url برای درایورهای URL مستقیم است؛ برای درایورهای فرم‌محور
     *    یک مسیر امضادار روی پنل است که فرم را رندر و خودکار به درگاه POST می‌کند.
     * =================================================================== */
    public function purchase(Request $request, string $slug): JsonResponse
    {
        $request->validate([
            'callback_url'  => 'nullable|url',
            'gateway'       => 'nullable|string|max:32',
            'purchase_type' => 'nullable|in:normal,exclusive',
        ]);

        try {
            $customer = $this->authService->authenticate($request);

            // درگاه اختیاری: کلاینت می‌تواند درگاه مشخصی بخواهد (مثلاً local برای تست)
            // باید در فهرست درگاه‌های پشتیبانی‌شده + فعال باشد.
            $gatewayKey = $request->input('gateway');
            if ($gatewayKey) {
                $gatewayActive = Gateway::query()
                    ->where('key', $gatewayKey)
                    ->whereIn('key', array_keys(config('general.supported_gateways')))
                    ->where('is_active', true)
                    ->exists();

                if (!$gatewayActive) {
                    return response()->json([
                        'error' => "درگاه «{$gatewayKey}» وجود ندارد یا فعال نیست. درگاه‌های فعال را از فهرست قالب‌ها یا پنل بررسی کنید.",
                    ], 422);
                }
            }

            // نوع خرید: normal (پیش‌فرض) | exclusive
            $purchaseType = $request->input('purchase_type', Template::PURCHASE_NORMAL) === Template::PURCHASE_EXCLUSIVE
                ? Template::PURCHASE_EXCLUSIVE
                : Template::PURCHASE_NORMAL;

            $template = Template::where('slug', $slug)
                ->where('status', Template::STATUS_ACTIVE)
                ->first();

            if (!$template) {
                return response()->json(['error' => 'قالب یافت نشد.'], 404);
            }

            // خرید اختصاصی: گاردها
            if ($purchaseType === Template::PURCHASE_EXCLUSIVE) {
                if ($template->is_sold_exclusively) {
                    return response()->json([
                        'error' => 'این قالب قبلاً به‌صورت اختصاصی واگذار شده و دیگر قابل خرید نیست.',
                    ], 422);
                }

                if ((int) $template->exclusive_price <= 0) {
                    return response()->json([
                        'error' => 'خرید اختصاصی برای این قالب فعال نشده است.',
                    ], 422);
                }
            }

            $latestVersion = $template->latestVersion()->first();

            if (!$latestVersion) {
                return response()->json(['error' => 'نسخه فعالی برای این قالب وجود ندارد.'], 422);
            }

            // مبلغ بر اساس نوع خرید
            $amount = $purchaseType === Template::PURCHASE_EXCLUSIVE
                ? $template->exclusive_final_price
                : $template->final_price;

            // جلوگیری از خرید تکراری (فقط برای خرید معمولی — اختصاصی همیشه خرید جدید است)
            if ($purchaseType === Template::PURCHASE_NORMAL
                && $template->purchases()
                    ->where('customer_id', $customer->id)
                    ->where('status', TemplatePurchase::STATUS_PAID)
                    ->where(fn ($q) => $q->whereNull('version_id')->orWhere('version_id', $latestVersion->id))
                    ->exists()) {
                return response()->json([
                    'is_free'       => $template->is_effectively_free,
                    'is_purchased'  => true,
                    'download_token' => $this->createDownloadToken($customer, $template, $latestVersion),
                    'version'       => $latestVersion->version,
                    'message'       => 'شما قبلاً این قالب را خریداری کرده‌اید؛ مستقیم دانلود کنید.',
                ]);
            }

            // ---------- مسیر رایگان: نیازی به پرداخت نیست (فقط خرید معمولی) ----------
            if ($purchaseType === Template::PURCHASE_NORMAL && $template->is_effectively_free) {
                $purchase = TemplatePurchase::create([
                    'template_id'   => $template->id,
                    'version_id'    => $latestVersion->id,
                    'customer_id'   => $customer->id,
                    'purchase_type' => $purchaseType,
                    'amount'        => 0,
                    'status'        => TemplatePurchase::STATUS_PAID,
                    'paid_at'       => now(),
                ]);

                return response()->json([
                    'is_free'        => true,
                    'purchase_id'    => $purchase->id,
                    'version'        => $latestVersion->version,
                    'download_token' => $this->createDownloadToken($customer, $template, $latestVersion),
                ]);
            }

            // ---------- مسیر پولی: بازگشت از درگاه به سایت مشتری الزامی است ----------
            if (!$request->filled('callback_url')) {
                return response()->json(['error' => 'آدرس بازگشت (callback_url) برای پرداخت الزامی است.'], 422);
            }

            // ایجاد رکورد purchase
            $purchase = TemplatePurchase::create([
                'template_id'   => $template->id,
                'version_id'    => $latestVersion->id,
                'customer_id'   => $customer->id,
                'purchase_type' => $purchaseType,
                'callback_url'  => $request->callback_url,
                'amount'        => $amount,
                'status'        => TemplatePurchase::STATUS_PENDING,
            ]);

            // ایجاد پرداخت در درگاه (gateway اختیاری کلاینت یا پیش‌فرض)
            // اگر درگاه تراکنش نسازد، رکورد pending یتیم باقی نماند.
            try {
                $payment = $this->paymentService->createTemplatePayment($purchase, $gatewayKey);
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
     *  POST /api/v1/templates/payments/{transaction_id}/verify
     *  تأیید پرداخت توسط پروژه خریدار
     *  Response: { paid, purchase_id, version, download_token }
     * =================================================================== */
    public function verifyPayment(Request $request, string $transactionId): JsonResponse
    {
        try {
            $customer = $this->authService->authenticate($request);

            $purchase = TemplatePurchase::where('transaction_id', $transactionId)
                ->where('customer_id', $customer->id)
                ->first();

            if (!$purchase) {
                return response()->json([
                    'paid'   => false,
                    'error'  => 'تراکنش یافت نشد.',
                ], 404);
            }

            // اگر قبلاً پرداخت شده
            if ($purchase->isPaid()) {
                $latestVersion = $purchase->template->latestVersion()->first();

                return response()->json([
                    'paid'           => true,
                    'purchase_id'    => $purchase->id,
                    'amount'         => (int) $purchase->amount,
                    'paid_at'        => $purchase->paid_at?->toDateTimeString(),
                    'download_token' => $latestVersion
                        ? $this->createDownloadToken($customer, $purchase->template, $latestVersion)
                        : null,
                    'signature'      => $latestVersion?->file_hash,
                    'version'        => $latestVersion?->version,
                ]);
            }

            // اگر هنوز pending است، تلاش به تأیید درگاه
            if ($purchase->status === TemplatePurchase::STATUS_PENDING) {
                $result = $this->paymentService->verifyTemplatePayment($transactionId);

                if ($result['paid'] ?? false) {
                    $purchase->refresh();
                    $latestVersion = $purchase->template->latestVersion()->first();

                    return response()->json([
                        'paid'           => true,
                        'purchase_id'    => $purchase->id,
                        'amount'         => (int) $purchase->amount,
                        'paid_at'        => $purchase->paid_at?->toDateTimeString(),
                        'download_token' => $latestVersion
                            ? $this->createDownloadToken($customer, $purchase->template, $latestVersion)
                            : null,
                        'signature'      => $latestVersion?->file_hash,
                        'version'        => $latestVersion?->version,
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
     *  GET /api/v1/templates/{slug}/check-update
     *  Query: { current_version }
     *  Response: { has_update, latest_version, changelog, what_added, is_mandatory }
     *  (بدون نیاز به خرید — مشتریِ احرازشده می‌تواند وضعیت آپدیت را ببیند)
     * =================================================================== */
    public function checkUpdate(Request $request, string $slug): JsonResponse
    {
        $request->validate([
            'current_version' => 'required|string',
        ]);

        try {
            $this->authService->authenticate($request);

            $template = Template::where('slug', $slug)
                ->where('status', Template::STATUS_ACTIVE)
                ->first();

            if (!$template) {
                return response()->json(['error' => 'قالب یافت نشد.'], 404);
            }

            $latestVersion = $template->latestVersion()->first();

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
                'file_size'       => $latestVersion->file_size,
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
        return Gateway::query()
            ->active()
            ->orderBy('ordering')
            ->orderBy('name')
            ->get(['id', 'key', 'name', 'logo', 'ordering'])
            ->map(fn (Gateway $gateway) => [
                'key'   => $gateway->key,
                'title' => $gateway->name,
                'logo'  => gateway_logo_url($gateway->key, $gateway->logo),
                'is_test' => $gateway->key === 'local',
            ])
            ->values()
            ->all();
    }

    /* ===================================================================
     *  Helper - شناسه خریدهای پرداخت‌شده مشتری برای مجموعه‌ای از قالب‌ها
     *  خروجی: [template_id => purchase_id]
     * =================================================================== */
    private function paidPurchaseIdsFor(Customer $customer, array $templateIds)
    {
        if (empty($templateIds)) {
            return collect();
        }

        return TemplatePurchase::where('customer_id', $customer->id)
            ->where('status', TemplatePurchase::STATUS_PAID)
            ->whereIn('template_id', $templateIds)
            ->orderByDesc('id')
            ->get(['id', 'template_id', 'version_id'])
            ->keyBy('template_id');
    }

    /* ===================================================================
     *  Helper - افزودن اطلاعات خرید به خروجی قالب
     *  is_purchased => true یعنی پروژه خریدار باید دکمه «دانلود» نشان دهد
     * =================================================================== */
    private function appendPurchaseInfo(Template $template, $paidPurchaseIds): array
    {
        $purchase = $paidPurchaseIds[$template->id] ?? null;

        return array_merge($template->toArray(), [
            'is_purchased' => $purchase !== null,
            // تعداد خریدهای موفق (چند نفر این قالب را خریده‌اند/استفاده می‌کنند)
            'purchases_count' => (int) ($template->purchases_count ?? 0),
            'purchased'     => $purchase ? [
                'purchase_id' => $purchase->id,
                'version_id'  => $purchase->version_id,
                // نام قدیمی برای سازگاری با الگوی پکیج‌ها
                'installed_license' => null,
            ] : null,
        ]);
    }

    /* ===================================================================
     *  Helper - ساخت download token موقت (15 دقیقه، یک‌بارمصرف)
     * =================================================================== */
    private function createDownloadToken(Customer $customer, Template $template, \App\Models\TemplateVersion $version): string
    {
        $token = \App\Models\TemplateDownloadToken::create([
            'token'               => \Illuminate\Support\Str::random(64),
            'template_id'         => $template->id,
            'template_version_id' => $version->id,
            'customer_id'         => $customer->id,
            'expires_at'          => now()->addMinutes(15),
        ]);

        return $token->token;
    }
}
