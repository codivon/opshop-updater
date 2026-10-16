<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\DiscountCode;
use App\Models\Package;
use App\Models\PackageLicense;
use App\Models\Subscription;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionPlan;
use App\Services\Sms\SmsManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * منطق تجاری طرح‌های اشتراک:
 *  - خرید طرح (رایگان/پولی از طریق درگاه)
 *  - تأیید پرداخت
 *  - تأیید/رد مدیر → فعال‌سازی اشتراک + صدور لایسنس رایگان پکیج‌های همراه طرح
 */
class SubscriptionService
{
    public function __construct(
        private PaymentService $paymentService
    ) {}

    /** پیامک پس از فعال‌سازی/تمدید (در صورت فعال بودن سیستم) */
    private function sms(): SmsManager
    {
        return app(SmsManager::class);
    }

    /* ===================================================================
     *  خرید طرح
     * =================================================================== */

    /**
     * ثبت سفارش اشتراک.
     *
     * @param  string|null $callbackUrl آدرس بازگشت فروشگاه مشتری (API) — در مسیر وب خالی است.
     * @param  DiscountCode|null $coupon کد تخفیف اعمال‌شده (از قبل اعتبارسنجی‌شده توسط DiscountService)
     * @return array{order: SubscriptionOrder, payment_url?: string, transaction_id?: string, is_free?: bool, message?: string}
     *
     * @throws RuntimeException (طرح غیرفعال / یک‌بارمصرفِ استفاده‌شده / خطای درگاه)
     */
    public function purchase(Customer $customer, SubscriptionPlan $plan, ?string $gatewayKey = null, ?string $callbackUrl = null, ?DiscountCode $coupon = null): array
    {
        if (!$plan->is_active) {
            throw new RuntimeException('این طرح اشتراک فعال نیست.');
        }

        if ($customer->status !== 'active') {
            throw new RuntimeException('حساب این مشتری غیرفعال است.');
        }

        // گارد طرح یک‌بارمصرف: مشتری فقط یک‌بار می‌تواند از طرح استفاده کند
        if ($plan->is_one_time && $plan->hasCustomerUsed($customer->id)) {
            throw new RuntimeException('شما قبلاً از این طرح استفاده کرده‌اید؛ این طرح فقط یک‌بار قابل خریداری است.');
        }

        $amount    = (int) $plan->price;
        $discount  = (int) ($plan->discount_price ?? 0); // تخفیف خودِ طرح (مبلغ)
        $baseFinal = $plan->final_price;

        // ---- کد تخفیف (کوپن) ----
        // تخفیف کد روی مبلغِ پس از تخفیف طرح محاسبه می‌شود؛ مبلغ نهایی هرگز منفی نمی‌شود.
        $couponDiscount = 0;
        if ($coupon && $baseFinal > 0) {
            $couponDiscount = $coupon->calculateDiscount($baseFinal);
        }

        $final = max(0, $baseFinal - $couponDiscount);
        $totalDiscount = $discount + $couponDiscount;

        // snapshot طرح در meta (برای زمانی که طرح بعداً حذف/ویرایش شود)
        $snapshot = [
            'plan' => [
                'name'            => $plan->name,
                'slug'            => $plan->slug,
                'duration_months' => (int) $plan->duration_months,
                'features'        => $plan->features ?? [],
                'price'           => $amount,
                'final_price'     => $final,
                'is_one_time'     => (bool) $plan->is_one_time,
                'packages'        => $plan->packages()
                    ->get(['packages.id', 'packages.name', 'packages.slug'])
                    ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug, 'free_months' => (int) $p->pivot->free_months])
                    ->values()
                    ->all(),
            ],
        ];

        // snapshot کد تخفیف (برای گزارش بعدی حتی پس از حذف کد)
        if ($coupon && $couponDiscount > 0) {
            $snapshot['coupon'] = [
                'code'     => $coupon->code,
                'type'     => $coupon->type,
                'value'    => (int) $coupon->value,
                'discount' => $couponDiscount,
            ];
        }

        $order = SubscriptionOrder::create([
            'subscription_plan_id' => $plan->id,
            'customer_id'          => $customer->id,
            'discount_code_id'     => ($coupon && $couponDiscount > 0) ? $coupon->id : null,
            'amount'               => $amount,
            'discount'             => $totalDiscount,
            'final_amount'         => $final,
            'callback_url'         => $callbackUrl,
            'status'               => SubscriptionOrder::STATUS_PENDING,
            'admin_status'         => SubscriptionOrder::ADMIN_STATUS_PENDING,
            'meta'                 => $snapshot,
        ]);

        // ---------- مسیر رایگان: بدون درگاه، مستقیم در انتظار تأیید مدیر ----------
        // (طرح رایگان یا کد تخفیفِ ۱۰۰٪)
        if ($final <= 0) {
            $order->update([
                'amount'       => 0,
                // اگر کوپن پوشش کامل داده، تخفیفِ ثبت‌شده باید حفظ شود (برای گزارش/نمایش)
                'discount'     => ($coupon && $couponDiscount > 0) ? $totalDiscount : 0,
                'final_amount' => 0,
                'status'       => SubscriptionOrder::STATUS_PAID,
                'paid_at'      => now(),
                'gateway'      => null,
            ]);

            // مصرف شمارنده کد تخفیف (مسیر رایگان از markAsPaid عبور نمی‌کند)
            $order->discountCode?->recordUsage();

            // 📱 پیامک: تأیید ثبت درخواست به مشتری + اطلاع‌رسانی فوری به مدیر
            try {
                $vars = [
                    'customer_name' => $customer->name,
                    'plan_name'     => $plan->name,
                    'amount'        => 'رایگان',
                ];

                $this->sms()->send('subscription_request_ack', $customer->phone, $vars, $order, once: true);
                $this->sms()->notifyAdmin('admin_subscription_request', $vars, $order);
            } catch (\Throwable) {
                // پیامک هرگز نباید جریان اصلی را بشکند
            }

            return [
                'order'    => $order,
                'is_free'  => true,
                'message'  => 'درخواست اشتراک رایگان ثبت شد و در انتظار تأیید مدیر است.',
            ];
        }

        // ---------- مسیر پرداخت ----------
        try {
            $payment = $this->paymentService->createSubscriptionPayment($order, $gatewayKey);
        } catch (\Throwable $e) {
            // حذف رکورد یتیم pending (همان الگوی خرید پکیج)
            $order->delete();

            throw new RuntimeException($e->getMessage(), 0, $e);
        }

        return [
            'order'          => $order,
            'payment_url'    => $payment['payment_url'],
            'transaction_id' => $payment['transaction_id'],
            'amount'         => $final,
            'gateway'        => $payment['gateway'],
        ];
    }

    /**
     * تأیید پرداخت سفارش اشتراک (بازگشت از درگاه).
     * توجه: صدور لایسنس/فعال‌سازی در متد approve() و پس از تأیید مدیر انجام می‌شود.
     *
     * @return array{paid: bool, order?: SubscriptionOrder, message?: string}
     */
    public function verifyPayment(string $transactionId): array
    {
        $order = SubscriptionOrder::where('transaction_id', $transactionId)->first();

        if (!$order) {
            return ['paid' => false, 'message' => 'سفارش اشتراک یافت نشد.'];
        }

        if ($order->isPaid()) {
            return ['paid' => true, 'order' => $order, 'message' => 'این تراکنش قبلاً تأیید شده است.'];
        }

        return $this->paymentService->verifySubscriptionPayment($transactionId);
    }

    /* ===================================================================
     *  تأیید / رد مدیر
     * =================================================================== */

    /**
     * تأیید درخواست اشتراک:
     *  ۱) رکورد Subscription فعال ساخته می‌شود (اشتراکِ طرح‌محور)
     *  ۲) برای هر پکیجِ همراه طرح، لایسنس رایگان (به مدت free_months) صادر/تمدید می‌شود
     *  ۳) سفارش approved + بازه اعتبار ثبت می‌گردد
     */
    public function approve(SubscriptionOrder $order): Subscription
    {
        if ($order->admin_status === SubscriptionOrder::ADMIN_STATUS_APPROVED) {
            return $order->subscription; // idempotent
        }

        if (!$order->isPaid()) {
            throw new RuntimeException('این سفارش هنوز پرداخت نشده است؛ ابتدا باید پرداخت تأیید شود.');
        }

        if ($order->admin_status === SubscriptionOrder::ADMIN_STATUS_REJECTED) {
            throw new RuntimeException('این درخواست قبلاً رد شده است.');
        }

        $plan     = $order->plan;
        $customer = $order->customer;

        return DB::transaction(function () use ($order, $plan, $customer) {
            $months    = (int) ($plan?->duration_months ?? $order->meta['plan']['duration_months'] ?? 1);
            $startsAt  = now();
            $expiresAt = $months > 0 ? (clone $startsAt)->addMonths($months) : null;

            // ۱) اشتراک فعال
            $subscription = Subscription::create([
                'customer_id'          => $customer->id,
                'project_id'           => null, // اشتراکِ طرح‌محور، پروژه خاصی ندارد
                'subscription_plan_id' => $plan?->id,
                'subscription_order_id' => $order->id,
                'start_date'           => $startsAt->toDateString(),
                'end_date'             => $expiresAt?->toDateString(),
                'expires_at'           => $expiresAt,
                'status'               => 'active',
                'price'                => $order->amount,
                'discount'             => $order->discount,
                'final_amount'         => $order->final_amount,
                'payment_status'       => 'paid',
                'description'          => 'اشتراک «' . $order->plan_name . '» — فعال‌شده پس از تأیید مدیر.',
            ]);

            // ۲) لایسنس رایگان پکیج‌های همراه طرح
            $granted = [];
            $packages = $plan
                ? $plan->packages()->get(['packages.id', 'packages.name', 'packages.slug'])
                : collect();
            // اگر طرح حذف شده باشد، از snapshot سفارش استفاده می‌کنیم
            if ($packages->isEmpty() && !empty($order->meta['plan']['packages'])) {
                $packages = Package::whereIn('id', array_column($order->meta['plan']['packages'], 'id'))
                    ->get()
                    ->map(function ($p) use ($order) {
                        $snap = collect($order->meta['plan']['packages'])->firstWhere('id', $p->id);
                        $p->pivot = (object) ['free_months' => (int) ($snap['free_months'] ?? 1)];

                        return $p;
                    });
            }

            foreach ($packages as $package) {
                $freeMonths = (int) ($package->pivot->free_months ?? 1);
                $license    = $this->grantPackageAccess($customer, $package, $freeMonths, $order);

                if ($license) {
                    $granted[] = [
                        'package' => $package->name,
                        'months'  => $freeMonths,
                        'license' => $license->license_key,
                    ];
                }
            }

            // ۳) نهایی‌سازی سفارش
            $order->update([
                'admin_status'    => SubscriptionOrder::ADMIN_STATUS_APPROVED,
                'approved_at'     => now(),
                'subscription_id' => $subscription->id,
                'starts_at'       => $startsAt,
                'expires_at'      => $expiresAt,
            ]);

            Log::info('Subscription order approved', [
                'order_id'       => $order->id,
                'customer_id'    => $customer->id,
                'subscription'   => $subscription->id,
                'granted_packages' => count($granted),
            ]);

            // 📱 پیامک: اشتراک فعال شد (پس از commit دیتابیس)
            DB::afterCommit(function () use ($customer, $order, $subscription, $expiresAt) {
                try {
                    $this->sms()->send('subscription_activated', $customer->phone, [
                        'customer_name' => $customer->name ?? 'مشتری',
                        'plan_name'     => $order->plan_name,
                        'end_date'      => verta_date($expiresAt, 'Y/m/d') ?? $expiresAt?->format('Y/m/d'),
                    ], $subscription, once: true);
                } catch (\Throwable $e) {
                    Log::warning('Activation SMS failed: ' . $e->getMessage());
                }
            });

            return $subscription;
        });
    }

    /** رد درخواست اشتراک توسط مدیر */
    public function reject(SubscriptionOrder $order, ?string $reason = null): void
    {
        if ($order->admin_status === SubscriptionOrder::ADMIN_STATUS_APPROVED) {
            throw new RuntimeException('این درخواست قبلاً تأیید و اشتراک فعال شده است؛ نمی‌توان آن را رد کرد.');
        }

        $reason = $reason ?: 'درخواست شما توسط مدیر رد شد.';

        $order->update([
            'admin_status'    => SubscriptionOrder::ADMIN_STATUS_REJECTED,
            'rejected_at'     => now(),
            'rejected_reason' => $reason,
        ]);

        // 📱 پیامک: اطلاع رد درخواست به مشتری
        try {
            $this->sms()->send('subscription_rejected', $order->customer?->phone, [
                'customer_name' => $order->customer?->name ?? 'مشتری',
                'plan_name'     => $order->plan_name,
                'reason'        => $reason,
            ], $order, once: true);
        } catch (\Throwable) {
            // پیامک هرگز نباید جریان اصلی را بشکند
        }
    }

    /* ===================================================================
     *  صدور دسترسی رایگان به پکیج
     * =================================================================== */

    /**
     * اشتراک فعالِ طرح‌محوری که این پکیج را رایگان می‌کند (یا null).
     *
     * منطق: اشتراک‌های طرح‌محورِ (subscription_plan_id پر) فعالِ مشتری — بدون انقضا
     * یا با انقضای آینده — که پکیج موردنظر در فهرست پکیج‌های همراهِ طرحشان باشد.
     * (مشخصه pivot «free_months» مدت دسترسی رایگان است؛ ۰ = نامحدود)
     */
    public function activeSubscriptionCovering(Customer $customer, Package $package): ?Subscription
    {
        return $customer->subscriptions()
            ->whereNotNull('subscription_plan_id')
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->whereHas('plan.packages', fn ($q) => $q->where('packages.id', $package->id))
            ->with('plan.packages')
            ->first();
    }

    /**
     * صدور یا تمدید لایسنس رایگان یک پکیج برای مشتری (با سفارش اشتراک).
     *
     * @param  int $freeMonths مدت دسترسی رایگان (۰ = نامحدود)
     */
    public function grantPackageAccess(Customer $customer, Package $package, int $freeMonths, SubscriptionOrder $order): PackageLicense
    {
        return $this->grantFreeAccess(
            $customer,
            $package,
            $freeMonths,
            'دسترسی رایگان از طریق اشتراک «' . $order->plan_name . '».'
        );
    }

    /**
     * هسته صدور/تمدید لایسنس رایگان — بدون نیاز به سفارش اشتراک
     * (برای مسیر API: وقتی اشتراک فعال هست ولی سفارشی به آن متصل نیست).
     *  - اگر لایسنس فعال/منقضی (غیر باطل‌شده) دارد → تمدید: از انقضای فعلی + free_months
     *  - در غیر این صورت → لایسنس جدید از الان + free_months
     *
     * @param  int         $freeMonths مدت دسترسی رایگان (۰ = نامحدود)
     * @param  string|null $note       یادداشت لایسنس (منبع دسترسی رایگان)
     */
    public function grantFreeAccess(Customer $customer, Package $package, int $freeMonths, ?string $note = null): PackageLicense
    {
        $license = DB::transaction(function () use ($customer, $package, $freeMonths, $note) {
            $existing = PackageLicense::query()
                ->where('package_id', $package->id)
                ->where('customer_id', $customer->id)
                ->whereIn('status', [PackageLicense::STATUS_ACTIVE, PackageLicense::STATUS_EXPIRED])
                ->latest('id')
                ->first();

            $startsAt = now();
            $baseDate = ($existing && $existing->isActive() && $existing->expires_at)
                ? Carbon::parse($existing->expires_at)
                : now();
            $expiresAt = $freeMonths > 0 ? (clone $baseDate)->addMonths($freeMonths) : null;

            $license = PackageLicense::create([
                'license_key'     => PackageLicense::generateKey(),
                'package_id'      => $package->id,
                'customer_id'     => $customer->id,
                'renewed_from'    => $existing?->id,
                'status'          => PackageLicense::STATUS_ACTIVE,
                'starts_at'       => $startsAt,
                'expires_at'      => $expiresAt,
                'duration_months' => $freeMonths,
                'notes'           => $note ?: 'دسترسی رایگان از طریق اشتراک.',
            ]);

            if ($existing) {
                $existing->update(['status' => PackageLicense::STATUS_REVOKED]);
            }

            // 📱 پیامک: تمدید دسترسی رایگان (وقتی لایسنس قبلی وجود داشت)
            if ($existing) {
                DB::afterCommit(function () use ($customer, $package, $license, $note, $expiresAt) {
                    try {
                        // نام طرح از یادداشت لایسنس استخراج می‌شود (نمونه: دسترسی رایگان از طریق اشتراک «X»)
                        $planName = 'اشتراک';
                        if ($note && preg_match('/«(.+?)»/u', $note, $m)) {
                            $planName = $m[1];
                        }

                        $this->sms()->send('subscription_renewed', $customer->phone, [
                            'customer_name' => $customer->name ?? 'مشتری',
                            'package_name'  => $package->name,
                            'plan_name'     => $planName,
                            'end_date'      => verta_date($expiresAt, 'Y/m/d') ?? $expiresAt?->format('Y/m/d'),
                        ], $license);
                    } catch (\Throwable $e) {
                        Log::warning('Renewal SMS failed: ' . $e->getMessage());
                    }
                });
            }

            return $license;
        });

        return $license;
    }

    /* ===================================================================
     *  وضعیت‌ها
     * =================================================================== */

    /** تعداد درخواست‌های در انتظار تأیید مدیر (برای داشبورد/سایدبار) */
    public static function pendingApprovalsCount(): int
    {
        return SubscriptionOrder::query()
            ->where('admin_status', SubscriptionOrder::ADMIN_STATUS_PENDING)
            ->where('status', SubscriptionOrder::STATUS_PAID)
            ->count();
    }

    /** اشتراک‌های فعالِ طرح‌محور یک مشتری */
    public function activePlanSubscriptions(Customer $customer)
    {
        return $customer->subscriptions()
            ->whereNotNull('subscription_plan_id')
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with('plan')
            ->get();
    }
}
