<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Gateway;
use App\Models\PackageLicense;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionPlan;
use App\Services\PackageApiAuthService;
use App\Services\SubscriptionService;
use App\Services\Sms\SmsManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * API طرح‌های اشتراک (v1)
 *
 * GET  /api/v1/subscription-plans                        → لیست طرح‌های فعال (features + پکیج‌های همراه)
 * POST /api/v1/subscription-plans/{slug}/purchase        → ثبت سفارش (callback_url + gateway?)
 * POST /api/v1/subscriptions/payments/{transactionId}/verify → تأیید پرداخت سفارش اشتراک
 * GET  /api/v1/my-subscriptions                          → اشتراک‌های فعال + سفارش‌های مشتری
 *                                                        ⭐ + free_packages: پکیج‌های رایگانِ طرح‌های فعال (+ لایسنس فعال هر کدام)
 *
 * احراز هویت: همان قرارداد پکیج‌ها (Authorization: Bearer {update_code} + X-Project-Url).
 */
class ApiSubscriptionController extends Controller
{
    public function __construct(
        private PackageApiAuthService $authService,
        private SubscriptionService $subscriptionService,
        private SmsManager $smsManager
    ) {}

    /* ===================================================================
     *  GET /api/v1/subscription-plans
     *  لیست طرح‌های اشتراک فعال برای نمایش در فروشگاه پروژه خریدار
     * =================================================================== */
    public function index(Request $request): JsonResponse
    {
        try {
            $this->authService->authenticate($request);

            $plans = SubscriptionPlan::query()
                ->active()
                ->with(['packages:id,name,slug,category,short_description'])
                ->orderBy('sort_order')
                ->orderByDesc('id')
                ->when($request->filled('search'), function ($q) use ($request) {
                    $search = $request->search;
                    $q->where(fn ($sq) => $sq->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%"));
                })
                ->paginate($request->input('per_page', 15));

            return response()->json([
                'data' => $plans->getCollection()->map(fn (SubscriptionPlan $plan) => $this->presentPlan($plan))->values(),
                'gateways' => $this->presentGateways(),
                'meta' => [
                    'current_page' => $plans->currentPage(),
                    'last_page'    => $plans->lastPage(),
                    'total'        => $plans->total(),
                    'per_page'     => $plans->perPage(),
                ],
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], exception_status($e, 403));
        }
    }

    /* ===================================================================
     *  POST /api/v1/subscription-plans/{slug}/purchase
     *  Body: { callback_url (required), gateway? }
     *  - طرح رایگان: { is_free, order_id, admin_status: 'pending' } — در انتظار تأیید مدیر
     *  - طرح پولی: { payment_url, transaction_id, amount, gateway, order_id }
     *    (payment_url فقط ریدایرکت شود — همان قرارداد خرید پکیج)
     * =================================================================== */
    public function purchase(Request $request, string $slug): JsonResponse
    {
        $request->validate([
            'callback_url' => 'required|url',
            'gateway'      => 'nullable|string|max:32',
        ]);

        try {
            $customer = $this->authService->authenticate($request);

            // درگاه اختیاری — باید فعال باشد (همان قرارداد خرید پکیج)
            $gatewayKey = $request->input('gateway');
            if ($gatewayKey) {
                $gatewayActive = Gateway::query()
                    ->where('key', $gatewayKey)
                    ->whereIn('key', array_keys(config('general.supported_gateways')))
                    ->where('is_active', true)
                    ->exists();

                if (!$gatewayActive) {
                    return response()->json([
                        'error' => "درگاه «{$gatewayKey}» وجود ندارد یا فعال نیست.",
                    ], 422);
                }
            }

            $plan = SubscriptionPlan::query()
                ->where('slug', $slug)
                ->where('is_active', true)
                ->first();

            if (!$plan) {
                return response()->json(['error' => 'طرح اشتراک یافت نشد.'], 404);
            }

            $result = $this->subscriptionService->purchase(
                $customer,
                $plan,
                $gatewayKey,
                $request->callback_url
            );

            /** @var SubscriptionOrder $order */
            $order = $result['order'];

            if (($result['is_free'] ?? false) || $order->isPaid()) {
                return response()->json([
                    'is_free'       => true,
                    'order_id'      => $order->id,
                    'plan'          => $order->plan_name,
                    'admin_status'  => $order->admin_status,   // pending → در انتظار تأیید مدیر
                    'status'        => $order->status,
                    'message'       => $result['message'] ?? 'درخواست اشتراک ثبت شد؛ پس از تأیید مدیر فعال می‌شود.',
                ]);
            }

            return response()->json([
                'payment_url'    => $result['payment_url'],
                'transaction_id' => $result['transaction_id'],
                'amount'         => $result['amount'],
                'gateway'        => $result['gateway'],
                'order_id'       => $order->id,
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], exception_status($e, 422));
        }
    }

    /* ===================================================================
     *  POST /api/v1/subscriptions/payments/{transactionId}/verify
     *  تأیید پرداخت سفارش اشتراک توسط پروژه خریدار.
     *  ⚠️ برای درایور آزمایشی local پارامتر ?transactionId={trx} را هم در query بفرستید.
     *  Response: { paid, order_id, admin_status, message }
     *  — لایسنس پکیج‌های همراه پس از تأیید مدیر صادر می‌شود (نه در این مرحله).
     * =================================================================== */
    public function verifyPayment(Request $request, string $transactionId): JsonResponse
    {
        try {
            $customer = $this->authService->authenticate($request);

            $order = SubscriptionOrder::query()
                ->where('transaction_id', $transactionId)
                ->where('customer_id', $customer->id)
                ->first();

            if (!$order) {
                return response()->json([
                    'paid'  => false,
                    'error' => 'سفارش اشتراک یافت نشد.',
                ], 404);
            }

            $result = $this->subscriptionService->verifyPayment($transactionId);
            $order->refresh();

            // 📱 پیامک: پرداخت اشتراک (تأیید از مسیر API) + اطلاع مدیر
            // (once → اگر قبلاً از مسیر کال‌بک ارسال شده، تکرار نمی‌شود)
            if (($result['paid'] ?? false) && $order->status === SubscriptionOrder::STATUS_PAID) {
                try {
                    $vars = [
                        'customer_name' => $order->customer?->name ?? 'مشتری',
                        'plan_name'     => $order->plan_name,
                        'amount'        => number_format((int) $order->final_amount),
                    ];

                    $this->smsManager->send('subscription_paid', $order->customer?->phone, $vars, $order, once: true);
                    $this->smsManager->notifyAdminSale('subscription', 'admin_subscription_paid', $vars, $order);
                } catch (\Throwable) {
                    // پیامک هرگز نباید جریان را بشکند
                }
            }

            return response()->json([
                'paid'           => $result['paid'] ?? false,
                'order_id'       => $order->id,
                'plan'           => $order->plan_name,
                'status'         => $order->status,
                'admin_status'   => $order->admin_status,
                'subscription_id' => $order->subscription_id,
                'expires_at'     => $order->expires_at?->toDateTimeString(),
                'message'        => $result['message'] ?? null,
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], exception_status($e, 403));
        }
    }

    /* ===================================================================
     *  GET /api/v1/my-subscriptions
     *  اشتراک‌های طرح‌محورِ این مشتری + سفارش‌های اخیر
     *  ⭐ + free_packages: پکیج‌هایی که با اشتراک فعالِ طرح‌محور رایگان‌اند
     *    (لایسنس فعال هر پکیج هم اگر صادر شده باشد برمی‌گردد)
     * =================================================================== */
    public function mySubscriptions(Request $request): JsonResponse
    {
        try {
            /** @var Customer $customer */
            $customer = $this->authService->authenticate($request);

            $subscriptions = $customer->subscriptions()
                ->whereNotNull('subscription_plan_id')
                ->with('plan:id,name,slug,duration_months')
                ->orderByDesc('id')
                ->limit(20)
                ->get()
                ->map(function ($sub) {
                    return [
                        'id'              => $sub->id,
                        'plan'            => $sub->plan?->name,
                        'plan_slug'       => $sub->plan?->slug,
                        'status'          => $sub->status,
                        'starts_at'       => $sub->start_date?->toDateString(),
                        'expires_at'      => $sub->expires_at?->toDateTimeString(),
                        'is_unlimited'    => $sub->expires_at === null,
                        'days_remaining'  => $sub->expires_at ? max(0, now()->diffInDays($sub->expires_at)) : null,
                    ];
                });

            $orders = $customer->subscriptionOrders()
                ->with('plan:id,name,slug')
                ->orderByDesc('id')
                ->limit(20)
                ->get()
                ->map(fn (SubscriptionOrder $order) => [
                    'id'            => $order->id,
                    'plan'          => $order->plan_name,
                    'amount'        => (int) $order->final_amount,
                    'status'        => $order->status,
                    'admin_status'  => $order->admin_status,
                    'rejected_reason' => $order->rejected_reason,
                    'created_at'    => $order->created_at?->toDateTimeString(),
                ]);

            return response()->json([
                'data' => [
                    'subscriptions'  => $subscriptions,
                    'orders'         => $orders,
                    'free_packages'  => $this->freePackagesFor($customer),
                ],
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], exception_status($e, 403));
        }
    }

    /* ---------------------------------------------------------------- */
    /*  Helpers                                                          */
    /* ---------------------------------------------------------------- */

    /**
     * پکیج‌های رایگانِ طرح‌های اشتراکِ فعال مشتری.
     * لایسنس‌های فعال مشتری یک‌بار کوئری می‌شوند و بر اساس package_id نگاشت می‌گردند.
     */
    private function freePackagesFor(Customer $customer): array
    {
        $activeSubs = $customer->subscriptions()
            ->whereNotNull('subscription_plan_id')
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with('plan.packages')
            ->get();

        if ($activeSubs->isEmpty()) {
            return [];
        }

        // لایسنس‌های فعال مشتری — یک کوئری (کلید = package_id)
        $licenses = PackageLicense::query()
            ->where('customer_id', $customer->id)
            ->where('status', PackageLicense::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get()
            ->keyBy('package_id');

        $freePackages = [];
        $seen = [];
        foreach ($activeSubs as $sub) {
            foreach ($sub->plan?->packages ?? [] as $pkg) {
                if (in_array($pkg->id, $seen)) {
                    continue; // هر پکیج یک‌بار (اولین اشتراک فعال ملاک است)
                }
                $seen[] = $pkg->id;

                $license    = $licenses->get($pkg->id);
                $freeMonths = (int) ($pkg->pivot->free_months ?? 1);

                $freePackages[] = [
                    'slug'              => $pkg->slug,
                    'name'              => $pkg->name,
                    'free_months'       => $freeMonths,
                    'free_label'        => SubscriptionPlan::freeMonthsLabel($freeMonths),
                    'subscription_plan' => $sub->plan?->name,
                    // لایسنس صادرشده (اگر مشتری لایسنس فعال این پکیج را دارد)
                    'license_key'       => $license?->license_key,
                    'expires_at'        => $license?->expires_at?->toDateTimeString(),
                ];
            }
        }

        return $freePackages;
    }

    /**
     * درگاه‌های فعال برای نمایش به مشتری (انتخاب درگاه در فروشگاه خریدار).
     * شامل لوگو و عنوان تا فروشگاه بتواند کارت‌های انتخاب زیبا بسازد.
     */
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

    private function presentPlan(SubscriptionPlan $plan): array
    {
        return [
            'id'              => $plan->id,
            'name'            => $plan->name,
            'slug'            => $plan->slug,
            'description'     => $plan->description,
            'duration_months' => (int) $plan->duration_months,
            'duration_label'  => $plan->duration_label,
            'price'           => (int) $plan->price,
            'discount_price'  => $plan->discount_price !== null ? (int) $plan->discount_price : null,
            'final_price'     => $plan->final_price,
            'is_free'         => $plan->is_free,
            'is_one_time'     => (bool) $plan->is_one_time,
            'features'        => $plan->features ?? [],
            'packages'        => $plan->packages->map(fn ($p) => [
                'slug'        => $p->slug,
                'name'        => $p->name,
                'free_months' => (int) $p->pivot->free_months,
                'free_label'  => SubscriptionPlan::freeMonthsLabel((int) $p->pivot->free_months),
            ])->values()->all(),
            'sort_order'      => (int) $plan->sort_order,
        ];
    }
}
