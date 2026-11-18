<?php

namespace App\Services;

use App\Models\PackageLicense;
use App\Models\PackagePurchase;
use App\Models\Subscription;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionPlan;
use App\Services\Sms\SmsManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Task 87 — سرویس «رزرو تمدید» (Renewal Reservation)
 *
 * قاعدهٔ کسب‌وکار:
 *  ۱) اگر مشتری برای یک پکیج/طرح، اعتبارِ فعال داشته باشد، خریدِ مجدد همان مورد
 *     تا ۵ روزِ پایانیِ اعتبار مسدود است (با پیام دقیق شامل طرح و روزهای مانده).
 *  ۲) در ۵ روزِ پایانی، خرید مجاز است اما به‌جای فعال‌سازیِ فوری «رزرو» می‌شود:
 *     پول پرداخت می‌شود ولی لایسنس/اشتراک جدید تا پایانِ دورهٔ فعلی صادر نمی‌شود.
 *  ۳) کالکسیون زمان‌بندی‌شدهٔ renewals:activate پس از پایان دورهٔ فعلی، رزرو را
 *     فعال و پیامک فعال‌سازی می‌فرستد.
 *
 * همهٔ پیام‌ها شامل: نام طرح فعلی + روزهای مانده + زمان قابل خرید مجدد است.
 */
class RenewalService
{
    /** پنجرهٔ مجاز خرید مجدد (روز مانده به پایان اعتبار) */
    public const WINDOW_DAYS = 5;

    public function __construct(
        private LicenseService $licenseService
    ) {}

    /* ===================================================================
     *  پکیج‌ها
     * =================================================================== */

    /**
     * آخرین لایسنس فعالِ مشتری برای یک پکیج (نامحدود یا با انقضای آینده).
     */
    public function findActiveLicense(int $customerId, int $packageId): ?PackageLicense
    {
        return PackageLicense::query()
            ->where('customer_id', $customerId)
            ->where('package_id', $packageId)
            ->where('status', PackageLicense::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END DESC')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * خرید رزروشدهٔ پرداخت‌شده و هنوز فعال‌نشده برای این پکیج.
     */
    public function findReservedPurchase(int $customerId, int $packageId): ?PackagePurchase
    {
        return PackagePurchase::query()
            ->where('customer_id', $customerId)
            ->where('package_id', $packageId)
            ->where('status', PackagePurchase::STATUS_PAID)
            ->whereNotNull('reserved_activate_at')
            ->whereNull('reserved_activated_at')
            ->latest('id')
            ->first();
    }

    /**
     * خرید رزروشدهٔ در انتظار پرداخت (pending) — مانع ثبت خرید رزرو تکراری.
     */
    public function findPendingReservedPurchase(int $customerId, int $packageId): ?PackagePurchase
    {
        return PackagePurchase::query()
            ->where('customer_id', $customerId)
            ->where('package_id', $packageId)
            ->where('status', PackagePurchase::STATUS_PENDING)
            ->whereNotNull('reserved_activate_at')
            ->latest('id')
            ->first();
    }

    /**
     * قضاوت خرید مجدد یک پکیج برای مشتری.
     *
     * @return array{allowed: bool, reserved: bool, message: string, days_remaining: ?int,
     *               active_license: ?PackageLicense, meta: ?array}
     */
    public function evaluatePackageRenewal(int $customerId, int $packageId): array
    {
        $deny = fn (string $message, string $reason = 'window_closed', ?Carbon $activateAt = null) => [
            'allowed' => false, 'reserved' => false, 'message' => $message, 'reason' => $reason,
            'days_remaining' => null, 'active_license' => null, 'meta' => null,
            'activate_at' => $activateAt?->format('Y-m-d H:i:s'),
        ];

        // ۱) خرید رزروشدهٔ فعال‌نشده → تا فعال‌سازی، خرید مجدد نداریم
        if ($reserved = $this->findReservedPurchase($customerId, $packageId)) {
            return $deny(
                'شما یک خرید رزروشده برای این پکیج دارید که پس از پایان دورهٔ فعلی شما ('
                .$this->jdate($reserved->reserved_activate_at).') به‌صورت خودکار فعال می‌شود؛ تا آن زمان امکان خرید مجدد وجود ندارد.', 'reserved_active', $reserved->reserved_activate_at);
        }

        // ۲) خرید رزروشدهٔ در انتظار پرداخت → ابتدا آن را تکمیل کنید
        if ($pending = $this->findPendingReservedPurchase($customerId, $packageId)) {
            return $deny(
                'شما یک خرید رزروشده برای این پکیج در انتظار پرداخت دارید؛ ابتدا پرداخت آن را تکمیل کنید یا آن را رها کنید تا پس از پایان دورهٔ فعلی فعال شود.', 'reserved_payment', $pending->reserved_activate_at);
        }

        // ۳) لایسنس فعال → پنجرهٔ ۵ روزه
        $license = $this->findActiveLicense($customerId, $packageId);

        if (! $license) {
            return [
                'allowed' => true, 'reserved' => false, 'message' => '',
                'days_remaining' => null, 'active_license' => null, 'meta' => null,
            ];
        }

        // لایسنس نامحدود → خرید مجدد بی‌معنا
        if ($license->expires_at === null) {
            return $deny('شما لایسنس نامحدود این پکیج را در اختیار دارید و نیازی به خرید مجدد نیست.', 'unlimited');
        }

        $daysRemaining = max(0, (int) floor(now()->diffInDays($license->expires_at, false)));

        // Task 89: برچسب منبع دسترسی — اگر لایسنس از طریق اشتراک صادر شده، نام طرح اشتراک گفته می‌شود
        $sourceLabel = $this->licenseSourceLabel($license);

        // هنوز در پنجرهٔ بستهٔ خرید
        if ($daysRemaining > self::WINDOW_DAYS) {
            return [
                'allowed' => false, 'reserved' => false,
                'message' => 'شما این پکیج را با '.$sourceLabel.' در اختیار دارید و اعتبار آن تا '
                    .fa_num($daysRemaining).' روز دیگر به پایان می‌رسد. از '.fa_num(self::WINDOW_DAYS).' روز مانده به پایان اعتبار می‌توانید طرح بعدی را خریداری کنید؛ '
                    .'خرید جدید پس از پایان دورهٔ فعلی به‌صورت خودکار فعال خواهد شد.',
                'reason' => 'window_closed',
                'days_remaining' => null, 'active_license' => $license, 'meta' => null, 'activate_at' => null,
            ];
        }

        // پنجرهٔ ۵ روزه → خرید مجاز اما رزرو
        return [
            'allowed' => true,
            'reserved' => true,
            'message' => 'اعتبار فعلی شما برای این پکیج تا '.fa_num($daysRemaining).' روز دیگر به پایان می‌رسد؛ '
                .'خرید شما رزرو شد و پس از پایان دورهٔ فعلی ('.$this->jdate($license->expires_at).') به‌طور خودکار فعال خواهد شد.',
            'days_remaining' => $daysRemaining,
            'active_license' => $license,
            'meta' => $this->packageReservationPayload($license),
        ];
    }

    /** payload ستون‌های رزرو برای PackagePurchase::create */
    public function packageReservationPayload(PackageLicense $activeLicense): array
    {
        return [
            'reserved_activate_at' => $activeLicense->expires_at->format('Y-m-d H:i:s'),
            'reserved_source_license_id' => $activeLicense->id,
        ];
    }

    /**
     * فعال‌سازی خودکار یک خرید رزروشده (کالکسیون renewals:activate).
     * لایسنس جدید از «زمان پایان دورهٔ فعلی» شروع می‌شود تا هیچ روزی از اعتبار خریدِ جدید تلف نشود.
     */
    public function activateReservedPurchase(PackagePurchase $purchase): ?PackageLicense
    {
        // idempotent
        if ($purchase->reserved_activated_at !== null || $purchase->license_id !== null) {
            return null;
        }

        if ($purchase->reserved_activate_at === null || now()->lessThan($purchase->reserved_activate_at)) {
            return null; // هنوز زمانش نرسیده
        }

        return DB::transaction(function () use ($purchase) {
            $purchase->refresh();

            if ($purchase->reserved_activated_at !== null || $purchase->license_id !== null) {
                return null;
            }

            // اگر مدیر/سرویس دیگری لایسنس فعال داده، رزرو را بدون صدورِ دوباره ببند
            // (لایسنس مبدا خودِ رزرو استثناست — در لحظهٔ فعال‌سازی هنوز ممکن است با وضعیت فعال باشد)
            if ($existing = $this->findActiveLicense($purchase->customer_id, $purchase->package_id)) {
                $isSource = (int) $existing->id === (int) $purchase->reserved_source_license_id;

                if (! $isSource) {
                    $purchase->update([
                        'reserved_activated_at' => now(),
                        'meta' => array_merge($purchase->meta ?? [], [
                            'activation_skipped' => 'لایسنس فعال هم‌زمان موجود بود (#'.$existing->id.')',
                        ]),
                    ]);

                    Log::info('Reserved purchase skipped: active license already exists', [
                        'purchase_id' => $purchase->id,
                        'license_id' => $existing->id,
                    ]);

                    return null;
                }
            }

            $plan = $purchase->pricingPlan;
            $months = (int) ($plan?->duration_months ?? 0);
            $activateAt = Carbon::parse($purchase->reserved_activate_at);

            $license = PackageLicense::create([
                'license_key' => PackageLicense::generateKey(),
                'package_id' => $purchase->package_id,
                'customer_id' => $purchase->customer_id,
                'purchase_id' => $purchase->id,
                'renewed_from' => $purchase->reserved_source_license_id,
                'status' => PackageLicense::STATUS_ACTIVE,
                'starts_at' => $activateAt,
                'expires_at' => $months > 0 ? (clone $activateAt)->addMonths($months) : null,
                'duration_months' => $months,
                'notes' => 'فعال‌سازی خودکار خرید رزروشده پس از پایان دورهٔ فعلی.',
            ]);

            // بستن لایسنس قبلی (اگر هنوز به‌روز نشده بود)
            if ($purchase->reserved_source_license_id) {
                PackageLicense::query()
                    ->where('id', $purchase->reserved_source_license_id)
                    ->where('status', PackageLicense::STATUS_ACTIVE)
                    ->update(['status' => PackageLicense::STATUS_EXPIRED]);
            }

            $purchase->update([
                'license_id' => $license->id,
                'reserved_activated_at' => now(),
            ]);

            // 📱 پیامک فعال‌سازی خودکار
            DB::afterCommit(function () use ($purchase, $license) {
                try {
                    app(SmsManager::class)->send('package_renewal_activated', $purchase->customer?->phone, [
                        'customer_name' => $purchase->customer?->name ?? 'مشتری',
                        'package_name' => $purchase->package?->name ?? 'پکیج',
                        'license_key' => $license->license_key,
                        'end_date' => $license->expires_at
                            ? (verta_date($license->expires_at, 'Y/m/d') ?? $license->expires_at->format('Y/m/d'))
                            : 'نامحدود',
                    ], $purchase, once: true);
                } catch (\Throwable $e) {
                    Log::warning('Renewal activation SMS failed: '.$e->getMessage());
                }
            });

            Log::info('Reserved purchase auto-activated', [
                'purchase_id' => $purchase->id,
                'license_id' => $license->id,
                'months' => $months,
            ]);

            return $license;
        });
    }

    /* ===================================================================
     *  اشتراک‌ها (طرح‌محور)
     * =================================================================== */

    /**
     * اشتراکِ فعالِ مشتری برای یک طرح مشخص (فعالِ جاری یا رزروشدهٔ در انتظار).
     */
    public function findActivePlanSubscription(int $customerId, int $planId): ?Subscription
    {
        return Subscription::query()
            ->where(function ($q) use ($customerId, $planId) {
                $q->where(function ($r) use ($customerId, $planId) {
                    // رزروشدهٔ در انتظار فعال‌سازی
                    $r->where('customer_id', $customerId)
                        ->where('subscription_plan_id', $planId)
                        ->where('status', Subscription::STATUS_RESERVED);
                })->orWhere(function ($s) use ($customerId, $planId) {
                    // فعال با اعتبار جاری
                    $s->where('customer_id', $customerId)
                        ->where('subscription_plan_id', $planId)
                        ->where('status', 'active')
                        ->where(function ($w) {
                            $w->whereNull('expires_at')
                                ->orWhere('expires_at', '>', now());
                        });
                });
            })
            ->orderByRaw('CASE WHEN status = ? THEN 1 ELSE 0 END DESC', [Subscription::STATUS_RESERVED])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * سفارشِ پرداخت‌شدهٔ در انتظار تأیید مدیر برای همین طرح.
     */
    public function findPendingPlanOrder(int $customerId, int $planId): ?SubscriptionOrder
    {
        return SubscriptionOrder::query()
            ->where('customer_id', $customerId)
            ->where('subscription_plan_id', $planId)
            ->where('status', SubscriptionOrder::STATUS_PAID)
            ->where('admin_status', SubscriptionOrder::ADMIN_STATUS_PENDING)
            ->latest('id')
            ->first();
    }

    /**
     * اشتراک رزروشدهٔ فعال‌نشده برای همین طرح.
     */
    public function findReservedSubscription(int $customerId, int $planId): ?Subscription
    {
        return Subscription::query()
            ->where('customer_id', $customerId)
            ->where('subscription_plan_id', $planId)
            ->where('status', Subscription::STATUS_RESERVED)
            ->latest('id')
            ->first();
    }

    /**
     * قضاوت خرید مجدد یک طرح اشتراک برای مشتری.
     *
     * @return array{allowed: bool, reserved: bool, message: string, days_remaining: ?int,
     *               active_subscription: ?Subscription}
     */
    public function evaluateSubscriptionRenewal(int $customerId, int $planId): array
    {
        $deny = fn (string $message, string $reason = 'window_closed', ?Carbon $activateAt = null) => [
            'allowed' => false, 'reserved' => false, 'message' => $message, 'reason' => $reason,
            'days_remaining' => null, 'active_subscription' => null,
            'activate_at' => $activateAt?->format('Y-m-d H:i:s'),
        ];

        // ۱) اشتراک رزروشده → تا فعال‌سازی، خرید مجدد نداریم
        if ($reserved = $this->findReservedSubscription($customerId, $planId)) {
            return $deny(
                'شما یک خرید رزروشده برای همین طرح دارید که پس از پایان اشتراک فعلی شما ('
                .$this->jdate($reserved->reserved_activate_at).') به‌صورت خودکار فعال می‌شود؛ تا آن زمان امکان خرید مجدد وجود ندارد.', 'reserved_active', $reserved->reserved_activate_at);
        }

        // ۲) سفارشِ در انتظار تأیید مدیر → تا تعیین تکلیف، درخواست جدید نداریم
        if ($pendingOrder = $this->findPendingPlanOrder($customerId, $planId)) {
            return $deny(
                'شما یک درخواست خرید برای همین طرح دارید که در انتظار تأیید مدیر است؛ پس از تعیین تکلیف آن می‌توانید درخواست جدید ثبت کنید.', 'pending_order');
        }

        // ۳) اشتراک فعال → پنجرهٔ ۵ روزه
        $subscription = $this->findActivePlanSubscription($customerId, $planId);

        if (! $subscription) {
            return [
                'allowed' => true, 'reserved' => false, 'message' => '',
                'days_remaining' => null, 'active_subscription' => null,
            ];
        }

        $expiresAt = $subscription->effective_expires_at;

        if ($expiresAt === null) {
            return $deny(
                'اشتراک «'.($subscription->plan?->name ?? 'طرح فعلی').'» شما نامحدود است و نیازی به خرید مجدد نیست. '
                .'اگر می‌خواهید اشتراک جدیدی جایگزین اشتراک فعلی شما شود، از پنل مشتری خود تیکت ارسال کنید تا توسط پشتیبانی انجام شود.', 'unlimited');
        }

        $daysRemaining = max(0, (int) floor(now()->diffInDays($expiresAt, false)));

        if ($daysRemaining > self::WINDOW_DAYS) {
            return [
                'allowed' => false, 'reserved' => false,
                'message' => 'شما طرح «'.($subscription->plan?->name ?? 'طرح فعلی').'» را در اشتراک فعلی خود دارید و '
                    .fa_num($daysRemaining).' روز از اعتبار آن باقی مانده است. از '.fa_num(self::WINDOW_DAYS).' روز مانده به پایان اشتراک می‌توانید طرح جدید را خریداری کنید؛ '
                    .'خرید جدید به‌صورت رزرو ذخیره شده و پس از پایان دورهٔ فعلی به‌طور خودکار فعال می‌شود. '
                    .'اگر می‌خواهید اشتراک جدید جایگزین اشتراک فعلی شما شود، از پنل مشتری خود تیکت ارسال کنید تا توسط پشتیبانی انجام شود.',
                'reason' => 'window_closed',
                'days_remaining' => null, 'active_subscription' => $subscription, 'activate_at' => null,
            ];
        }

        return [
            'allowed' => true,
            'reserved' => true,
            'message' => 'اعتبار اشتراک فعلی شما تا '.fa_num($daysRemaining).' روز دیگر به پایان می‌رسد؛ '
                .'خرید شما رزرو شد و پس از پایان اشتراک فعلی ('.$this->jdate($expiresAt).') به‌طور خودکار فعال خواهد شد.',
            'days_remaining' => $daysRemaining,
            'active_subscription' => $subscription,
        ];
    }

    /** payload رزرو برای متای سفارش اشتراک */
    public function subscriptionReservationPayload(Subscription $activeSubscription): array
    {
        return [
            'reserved' => true,
            'activate_at' => $activeSubscription->effective_expires_at?->format('Y-m-d H:i:s'),
            'source_subscription_id' => $activeSubscription->id,
            'reserved_at' => now()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * فعال‌سازی خودکار یک اشتراک رزروشده — صدور لایسنس پکیج‌های همراه + پیامک.
     */
    public function activateReservedSubscription(Subscription $subscription): ?Subscription
    {
        // idempotent
        if ($subscription->status !== Subscription::STATUS_RESERVED) {
            return null;
        }

        $order = $subscription->order;
        $activateAtRaw = $subscription->reserved_activate_at?->format('Y-m-d H:i:s')
            ?? (is_array($order?->meta) ? ($order->meta['renewal']['activate_at'] ?? null) : null);

        if (! $activateAtRaw) {
            return null;
        }

        $activateAt = Carbon::parse($activateAtRaw);

        if (now()->lessThan($activateAt)) {
            return null; // هنوز زمانش نرسیده
        }

        return DB::transaction(function () use ($subscription, $activateAt, $order) {
            $subscription->refresh();

            if ($subscription->status !== Subscription::STATUS_RESERVED) {
                return null;
            }

            $plan = $subscription->plan;
            $customer = $subscription->customer;

            // اگر اشتراک فعال دیگری (مثلاً تأیید دستی مدیر) وجود دارد → رزرو را ببند
            // (اشتراک مبدا استثناست — در لحظهٔ فعال‌سازی ممکن است هنوز فعال باشد)
            $sourceId = (int) (is_array($order?->meta) ? ($order->meta['renewal']['source_subscription_id'] ?? 0) : 0);

            $otherActive = Subscription::query()
                ->where('customer_id', $subscription->customer_id)
                ->where('subscription_plan_id', $subscription->subscription_plan_id)
                ->where('id', '!=', $subscription->id)
                ->when($sourceId > 0, fn ($q) => $q->where('id', '!=', $sourceId))
                ->where('status', 'active')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->exists();

            if ($otherActive) {
                $subscription->update([
                    'status' => 'expired',
                    'description' => 'اشتراک رزروشده — به دلیل وجود اشتراک فعال‌تر، بدون فعال‌سازی بسته شد.',
                ]);

                Log::info('Reserved subscription superseded by an active subscription', [
                    'subscription_id' => $subscription->id,
                ]);

                return null;
            }

            $months = (int) (
                (is_array($order?->meta) ? ($order->meta['chosen_duration']['duration_months'] ?? null) : null)
                ?? $plan?->duration_months
                ?? (is_array($order?->meta) ? ($order->meta['plan']['duration_months'] ?? null) : null)
                ?? 1
            );

            $expiresAt = $months > 0 ? (clone $activateAt)->addMonths($months) : null;

            $subscription->update([
                'start_date' => $activateAt->toDateString(),
                'end_date' => $expiresAt?->toDateString(),
                'expires_at' => $expiresAt,
                'status' => 'active',
                'reserved_activate_at' => null,
                'description' => 'اشتراک «'.($order?->plan_name ?? $plan?->name ?? 'طرح').'» — فعال‌شده به‌صورت خودکار پس از پایان دورهٔ قبلی.',
            ]);

            // صدور لایسنس پکیج‌های همراه (همان پیکربندیِ مؤثرِ سفارش)
            // Task 89: اشتراک پاس می‌شود تا لایسنس تا انضای همین اشتراک معتبر باشد
            $service = app(SubscriptionService::class);
            $packages = $service->resolveOrderPackages($subscription);

            foreach ($packages as $package) {
                try {
                    $service->grantPackageAccess($customer, $package, (int) ($package->pivot->free_months ?? 1), $order, $subscription);
                } catch (\Throwable $e) {
                    Log::warning('Failed granting reserved-subscription package access', [
                        'subscription_id' => $subscription->id,
                        'package_id' => $package->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // 📱 پیامک فعال‌سازی (همان قالب اشتراک فعال — لاگ یکتا روی این اشتراک)
            DB::afterCommit(function () use ($customer, $order, $subscription, $expiresAt) {
                try {
                    app(SmsManager::class)->send('subscription_activated', $customer->phone, [
                        'customer_name' => $customer->name ?? 'مشتری',
                        'plan_name' => $order?->plan_name ?? $subscription->plan?->name ?? 'طرح اشتراک',
                        'end_date' => verta_date($expiresAt, 'Y/m/d') ?? $expiresAt?->format('Y/m/d'),
                    ], $subscription, once: true);
                } catch (\Throwable $e) {
                    Log::warning('Reserved subscription activation SMS failed: '.$e->getMessage());
                }
            });

            Log::info('Reserved subscription auto-activated', [
                'subscription_id' => $subscription->id,
                'months' => $months,
                'packages' => $packages->count(),
            ]);

            return $subscription;
        });
    }

    /* ===================================================================
     *  Helpers
     * =================================================================== */

    /**
     * وضعیت نمایشی خرید مجدد یک پکیج برای کارت/فرم خرید (بنر صفحهٔ پکیج).
     *
     * @return array{state: string, days: ?int, plan: ?string, activate_at: ?Carbon, message: string}|null
     *                                                                                                     state: blocked | window | reserved — null = محدودیتی نیست
     */
    public function pageNoticeForPackage(int $customerId, int $packageId): ?array
    {
        if ($reserved = $this->findReservedPurchase($customerId, $packageId)) {
            return [
                'state' => 'reserved',
                'days' => null,
                'plan' => null,
                'activate_at' => $reserved->reserved_activate_at,
                'message' => 'یک خرید رزروشده برای این پکیج دارید که پس از پایان دورهٔ فعلی شما ('
                    .$this->jdate($reserved->reserved_activate_at).') به‌صورت خودکار فعال می‌شود.',
            ];
        }

        if ($this->findPendingReservedPurchase($customerId, $packageId)) {
            return [
                'state' => 'reserved',
                'days' => null,
                'plan' => null,
                'activate_at' => null,
                'message' => 'یک خرید رزروشده برای این پکیج در انتظار پرداخت دارید؛ پس از پرداخت، به‌صورت خودکار پس از پایان دورهٔ فعلی فعال می‌شود.',
            ];
        }

        $license = $this->findActiveLicense($customerId, $packageId);

        if (! $license) {
            return null;
        }

        if ($license->expires_at === null) {
            return [
                'state' => 'blocked',
                'days' => null,
                'plan' => null,
                'activate_at' => null,
                'message' => 'شما لایسنس نامحدود این پکیج را در اختیار دارید و نیازی به خرید مجدد نیست.',
            ];
        }

        $days = max(0, (int) floor(now()->diffInDays($license->expires_at, false)));
        $planLabel = $this->licenseSourceLabel($license);

        if ($days > self::WINDOW_DAYS) {
            return [
                'state' => 'blocked',
                'days' => $days,
                'plan' => $planLabel,
                'activate_at' => null,
                'message' => 'شما این پکیج را با '.$planLabel.' در اختیار دارید و اعتبار آن تا '
                    .fa_num($days).' روز دیگر به پایان می‌رسد. از '.fa_num(self::WINDOW_DAYS).' روز مانده به پایان اعتبار می‌توانید طرح بعدی را رزرو کنید.',
            ];
        }

        return [
            'state' => 'window',
            'days' => $days,
            'plan' => $planLabel,
            'activate_at' => $license->expires_at,
            'message' => 'اعتبار فعلی شما تا '.fa_num($days).' روز دیگر به پایان می‌رسد؛ همین حالا می‌توانید طرح بعدی را خریداری کنید. '
                .'خرید شما رزرو می‌شود و پس از پایان دورهٔ فعلی ('.$this->jdate($license->expires_at).') به‌طور خودکار فعال خواهد شد.',
        ];
    }

    /**
     * وضعیت نمایشی خرید مجدد یک طرح اشتراک (بنر کارت طرح).
     *
     * @return array{state: string, days: ?int, plan: ?string, activate_at: ?Carbon, message: string}|null
     */
    public function pageNoticeForPlan(int $customerId, int $planId): ?array
    {
        $planName = SubscriptionPlan::query()->find($planId)?->name;

        if ($reserved = $this->findReservedSubscription($customerId, $planId)) {
            return [
                'state' => 'reserved',
                'days' => null,
                'plan' => $planName,
                'activate_at' => $reserved->reserved_activate_at,
                'message' => 'یک خرید رزروشده برای همین طرح دارید که پس از پایان اشتراک فعلی شما ('
                    .$this->jdate($reserved->reserved_activate_at).') به‌صورت خودکار فعال می‌شود.',
            ];
        }

        if ($this->findPendingPlanOrder($customerId, $planId)) {
            return [
                'state' => 'blocked',
                'days' => null,
                'plan' => $planName,
                'activate_at' => null,
                'message' => 'شما یک درخواست خرید برای همین طرح دارید که در انتظار تأیید مدیر است؛ پس از تعیین تکلیف آن می‌توانید درخواست جدید ثبت کنید.',
            ];
        }

        $subscription = $this->findActivePlanSubscription($customerId, $planId);

        if (! $subscription) {
            return null;
        }

        $expiresAt = $subscription->effective_expires_at;

        if ($expiresAt === null) {
            return [
                'state' => 'blocked',
                'days' => null,
                'plan' => $planName,
                'activate_at' => null,
                'message' => 'اشتراک «'.($planName ?? 'طرح فعلی').'» شما نامحدود است و نیازی به خرید مجدد نیست.',
            ];
        }

        $days = max(0, (int) floor(now()->diffInDays($expiresAt, false)));

        if ($days > self::WINDOW_DAYS) {
            return [
                'state' => 'blocked',
                'days' => $days,
                'plan' => $planName,
                'activate_at' => null,
                'message' => 'شما طرح «'.($planName ?? 'طرح فعلی').'» را در اشتراک فعلی خود دارید و '
                    .fa_num($days).' روز از اعتبار آن باقی مانده است. از '.fa_num(self::WINDOW_DAYS).' روز مانده به پایان اشتراک می‌توانید طرح جدید را رزرو کنید.',
            ];
        }

        return [
            'state' => 'window',
            'days' => $days,
            'plan' => $planName,
            'activate_at' => $expiresAt,
            'message' => 'اعتبار اشتراک فعلی شما تا '.fa_num($days).' روز دیگر به پایان می‌رسد؛ همین حالا می‌توانید طرح جدید را خریداری کنید. '
                .'خرید شما رزرو می‌شود و پس از پایان اشتراک فعلی ('.$this->jdate($expiresAt).') به‌طور خودکار فعال خواهد شد.',
        ];
    }

    /** برچسب طرحِ لایسنس فعلی (برای پیام‌ها) */
    public function licensePlanLabel(PackageLicense $license): string
    {
        $planName = $license->purchase?->pricingPlan?->name;

        if ($planName) {
            return $planName;
        }

        return $license->duration_months > 0
            ? 'طرح '.fa_num($license->duration_months).' ماهه'
            : 'نامحدود';
    }

    /**
     * برچسب «منبعِ» اعتبارِ فعلی پکیج (Task 89):
     * لایسنس صادرشده از طریق اشتراک → «اشتراک «نام طرح»»؛
     * در غیر این صورت برچسب طرح خریدِ مستقل (طرح قیمت‌گذاری / مدت).
     */
    public function licenseSourceLabel(PackageLicense $license): string
    {
        $planName = $license->subscription?->plan?->name;

        if ($planName) {
            return 'اشتراک «'.$planName.'»';
        }

        return $this->licensePlanLabel($license);
    }

    /** تاریخ شمسی برای پیام‌ها */
    private function jdate(?Carbon $date): string
    {
        if ($date === null) {
            return '—';
        }

        return verta_date($date, 'Y/m/d') ?? $date->format('Y/m/d');
    }
}
