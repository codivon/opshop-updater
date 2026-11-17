<?php

namespace Tests\Feature;

use App\Models\PackageLicense;
use App\Models\PackagePurchase;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\LicenseService;
use App\Services\PaymentService;
use App\Services\RenewalService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

/**
 * Task 87 — رزرو تمدید (Renewal Reservation)
 *
 * قواعد:
 *  ۱) خرید مجدد همان پکیج/طرح در حالِ اعتبار → خطا با پیام دقیق (طرح + روزهای مانده)
 *  ۲) در ۵ روزِ پایانی → خرید مجاز اما «رزرو»: پرداخت انجام می‌شود ولی لایسنس/اشتراک
 *     پس از پایان دورهٔ فعلی صادر می‌شود
 *  ۳) کالکسیون renewals:activate → فعال‌سازی خودکار + پیامک
 */
class RenewalReservationTest extends TestCase
{
    use MakesShopData, RefreshDatabase;

    private RenewalService $renewals;

    private SubscriptionService $subscriptionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renewals = app(RenewalService::class);
        $this->subscriptionService = app(SubscriptionService::class);
    }

    /* ---------------- پکیج‌ها ---------------- */

    private function makePaidLicense(array $licenseAttrs = [], array $planAttrs = []): array
    {
        $package = $this->makePackage();
        $this->makePackageVersion($package);
        $plan = $this->makePlan($package, $planAttrs);
        $customer = $this->makeCustomer();

        $purchase = PackagePurchase::create([
            'package_id' => $package->id,
            'version_id' => $package->latestVersion()->first()->id,
            'pricing_plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => (int) $plan->final_price,
            'original_amount' => (int) $plan->final_price,
            'status' => PackagePurchase::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $license = app(LicenseService::class)->issueLicense($purchase, $plan);
        $license->update($licenseAttrs);

        return [$package, $plan, $customer, $purchase, $license->fresh()];
    }

    public function test_repurchase_blocked_while_license_active(): void
    {
        [, , , , $license] = $this->makePaidLicense([
            'expires_at' => now()->addDays(50),
        ]);

        $result = $this->renewals->evaluatePackageRenewal($license->customer_id, $license->package_id);

        $this->assertFalse($result['allowed']);
        $this->assertFalse($result['reserved']);
        $this->assertStringContainsString('خریداری کرده‌اید', $result['message']);
        $this->assertStringContainsString('روز دیگر به پایان می‌رسد', $result['message']);
        $this->assertStringContainsString('۵ روز مانده', $result['message']);
    }

    public function test_repurchase_blocked_for_unlimited_license(): void
    {
        [, , , , $license] = $this->makePaidLicense([
            'expires_at' => null,
            'duration_months' => 0,
        ], ['duration_months' => 0]);

        $result = $this->renewals->evaluatePackageRenewal($license->customer_id, $license->package_id);

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('نامحدود', $result['message']);
    }

    public function test_repurchase_allowed_in_window_and_purchase_is_reserved(): void
    {
        [$package, $plan, $customer, , $license] = $this->makePaidLicense([
            'expires_at' => now()->addDays(3),
        ]);

        $result = $this->renewals->evaluatePackageRenewal($customer->id, $package->id);

        $this->assertTrue($result['allowed']);
        $this->assertTrue($result['reserved']);
        $this->assertTrue($result['days_remaining'] >= 0 && $result['days_remaining'] <= 5);
        $this->assertSame($license->id, $result['meta']['reserved_source_license_id']);

        // خرید رزروشده ثبت می‌شود → تلاش مجدد مسدود است
        PackagePurchase::create(array_merge([
            'package_id' => $package->id,
            'version_id' => $package->latestVersion()->first()->id,
            'pricing_plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => (int) $plan->final_price,
            'status' => PackagePurchase::STATUS_PAID,
            'paid_at' => now(),
        ], $result['meta']));

        $again = $this->renewals->evaluatePackageRenewal($customer->id, $package->id);

        $this->assertFalse($again['allowed']);
        $this->assertStringContainsString('رزروشده', $again['message']);
    }

    public function test_reserved_purchase_verification_defers_license(): void
    {
        [$package, $plan, $customer, , $license] = $this->makePaidLicense([
            'expires_at' => now()->addDays(4),
        ]);

        $reserved = PackagePurchase::create([
            'package_id' => $package->id,
            'version_id' => $package->latestVersion()->first()->id,
            'pricing_plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'gateway' => 'local',
            'transaction_id' => 'TRX-RESERVED-TEST',
            'amount' => (int) $plan->final_price,
            'original_amount' => (int) $plan->final_price,
            'status' => PackagePurchase::STATUS_PENDING,
            ...$this->renewals->packageReservationPayload($license),
        ]);

        // درایور local هنگام verify مقدار transactionId را از Request می‌خواند
        request()->merge(['transactionId' => 'TRX-RESERVED-TEST']);

        $result = app(PaymentService::class)->verifyPayment('TRX-RESERVED-TEST');

        $this->assertTrue($result['paid']);
        $this->assertTrue($result['reserved']);
        $this->assertStringContainsString('خودکار', $result['message']);

        $reserved->refresh();

        $this->assertTrue($reserved->isPaid());
        $this->assertTrue($reserved->isReserved());
        $this->assertNull($reserved->license_id);
        $this->assertSame(0, PackageLicense::query()->where('purchase_id', $reserved->id)->count());
    }

    public function test_reserved_purchase_auto_activates_with_full_duration(): void
    {
        [$package, $plan, $customer, , $license] = $this->makePaidLicense([
            'expires_at' => now()->addDays(2),
        ], ['duration_months' => 3]);

        $reserved = PackagePurchase::create([
            'package_id' => $package->id,
            'version_id' => $package->latestVersion()->first()->id,
            'pricing_plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => (int) $plan->final_price,
            'status' => PackagePurchase::STATUS_PAID,
            'paid_at' => now(),
            ...$this->renewals->packageReservationPayload($license),
        ]);

        // قبل از سررسید → فعال نمی‌شود
        $this->assertNull($this->renewals->activateReservedPurchase($reserved));

        // سررسید (پایان دورهٔ فعلی) → فعال می‌شود
        $reserved->update(['reserved_activate_at' => now()->subDay()]);

        $newLicense = $this->renewals->activateReservedPurchase($reserved);

        $this->assertNotNull($newLicense);
        $this->assertTrue($newLicense->expires_at->gt(now()));
        $this->assertSame(3, (int) $newLicense->duration_months);
        $this->assertSame($license->id, (int) $newLicense->renewed_from);

        // لایسنس قبلی بسته شده و خرید به لایسنس جدید وصل است
        $this->assertSame(PackageLicense::STATUS_EXPIRED, $license->fresh()->status);
        $this->assertSame($newLicense->id, $reserved->fresh()->license_id);
        $this->assertNotNull($reserved->fresh()->reserved_activated_at);

        // اجرای مجدد → idempotent
        $this->assertNull($this->renewals->activateReservedPurchase($reserved->fresh()));
    }

    /* ---------------- اشتراک‌ها ---------------- */

    private function makeMultiDurationPlan(): SubscriptionPlan
    {
        $plan = SubscriptionPlan::create([
            'name' => 'طرح چندمدتی',
            'slug' => 'multi-dur-'.uniqid(),
            'duration_months' => 1,
            'price' => 0,
            'is_active' => true,
            'sort_order' => 0,
            'features' => ['پشتیبانی پایه'],
        ]);

        $plan->durations()->create(['duration_months' => 1, 'price' => 0, 'is_active' => true, 'sort_order' => 1]);
        $plan->durations()->create(['duration_months' => 3, 'price' => 0, 'is_active' => true, 'sort_order' => 2]);

        return $plan;
    }

    public function test_same_plan_repurchase_blocked_while_subscription_active(): void
    {
        $plan = $this->makeMultiDurationPlan();
        $customer = $this->makeCustomer();

        Subscription::create([
            'customer_id' => $customer->id,
            'subscription_plan_id' => $plan->id,
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->addDays(50)->toDateString(),
            'expires_at' => now()->addDays(50),
            'status' => 'active',
            'price' => 0,
            'discount' => 0,
            'payment_status' => 'paid',
        ]);

        try {
            $this->subscriptionService->purchase($customer, $plan, null, null, null, 3);
            $this->fail('باید RuntimeException پرتاب می‌شد');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('روز از اعتبار آن باقی مانده است', $e->getMessage());
            $this->assertStringContainsString($plan->name, $e->getMessage());
        }
    }

    public function test_same_plan_repurchase_in_window_creates_reserved_order(): void
    {
        $plan = $this->makeMultiDurationPlan();
        $planPackage = $this->makePackage(['name' => 'پکیج همراه']);
        $plan->packages()->attach($planPackage->id, ['free_months' => 1]);

        $customer = $this->makeCustomer();

        $active = Subscription::create([
            'customer_id' => $customer->id,
            'subscription_plan_id' => $plan->id,
            'start_date' => now()->subDays(28)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'expires_at' => now()->addDays(3),
            'status' => 'active',
            'price' => 0,
            'discount' => 0,
            'payment_status' => 'paid',
        ]);

        // خرید در پنجرهٔ ۵ روزه → سفارش رزرو
        $result = $this->subscriptionService->purchase($customer, $plan, null, null, null, 3);

        $this->assertTrue($result['reserved']);
        $this->assertStringContainsString('رزرو', $result['message']);

        $order = $result['order'];
        $this->assertTrue($order->meta['renewal']['reserved']);
        $this->assertSame($active->id, $order->meta['renewal']['source_subscription_id']);

        // تأیید مدیر → اشتراک رزروشده (بدون لایسنس پکیج‌ها تا پایان دورهٔ فعلی)
        $subscription = $this->subscriptionService->approve($order);

        $this->assertSame(Subscription::STATUS_RESERVED, $subscription->status);
        $this->assertNull($subscription->expires_at);
        $this->assertNotNull($subscription->reserved_activate_at);
        $this->assertSame(0, PackageLicense::query()->where('customer_id', $customer->id)->count());

        // خرید مجدد دیگر مسدود است (رزروشدهٔ موجود)
        try {
            $this->subscriptionService->purchase($customer, $plan, null, null, null, 1);
            $this->fail('باید RuntimeException پرتاب می‌شد');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('رزروشده', $e->getMessage());
        }
    }

    public function test_reserved_subscription_auto_activates_and_grants_licenses(): void
    {
        $plan = $this->makeMultiDurationPlan();
        $planPackage = $this->makePackage(['name' => 'پکیج همراه']);
        $plan->packages()->attach($planPackage->id, ['free_months' => 2]);

        $customer = $this->makeCustomer();

        Subscription::create([
            'customer_id' => $customer->id,
            'subscription_plan_id' => $plan->id,
            'start_date' => now()->subDays(28)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'expires_at' => now()->addDays(3),
            'status' => 'active',
            'price' => 0,
            'discount' => 0,
            'payment_status' => 'paid',
        ]);

        $order = $this->subscriptionService->purchase($customer, $plan, null, null, null, 3)['order'];
        $subscription = $this->subscriptionService->approve($order);

        // قبل از سررسید → فعال نمی‌شود
        $this->assertNull($this->renewals->activateReservedSubscription($subscription->fresh()));

        // سررسید → فعال‌سازی خودکار + صدور لایسنس پکیج‌های همراه
        // (اشتراک فعلی هم پایان می‌یابد — دقیقاً مثل زمان‌بندی واقعی بعد از انقضا)
        $subscription->update(['reserved_activate_at' => now()->subDay()]);
        Subscription::query()->where('id', $order->meta['renewal']['source_subscription_id'])
            ->update([
                'status' => 'expired',
                'end_date' => now()->subDay()->toDateString(),
                'expires_at' => now()->subDay(),
            ]);

        $activated = $this->renewals->activateReservedSubscription($subscription->fresh());

        $this->assertNotNull($activated);
        $this->assertSame('active', $activated->status);
        $this->assertTrue($activated->expires_at->gt(now()));
        // بازهٔ اعتبار = ۳ ماه کامل از زمان پایان دورهٔ قبلی
        $this->assertSame(3, (int) $activated->start_date->diffInMonths($activated->end_date));

        $license = PackageLicense::query()->where('customer_id', $customer->id)->first();
        $this->assertNotNull($license);
        $this->assertSame($planPackage->id, $license->package_id);
        $this->assertSame(2, (int) $license->duration_months);

        // اجرای مجدد → idempotent
        $this->assertNull($this->renewals->activateReservedSubscription($activated->fresh()));
    }
}
