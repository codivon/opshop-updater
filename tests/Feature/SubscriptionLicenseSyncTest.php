<?php

namespace Tests\Feature;

use App\Models\PackageLicense;
use App\Models\SubscriptionPlan;
use App\Services\RenewalService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

/**
 * Task 89 — لایسنس پکیجِ همراهِ اشتراک دقیقاً با اشتراک شروع/پایان می‌یابد
 *
 * قواعد:
 *  ۱) انضای لایسنس اشتراکی از انضای «اشتراک» محاسبه می‌شود — هرگز از انضای لایسنس قبلی + مدت
 *     (رفع باگ واقعی: اشتراک ۱ ماهه / ۱۳ روز مانده، ولی پیام گارد «۳۸ روز مانده»)
 *  ۲) cap: لایسنس اشتراکی هرگز از انضای اشتراک بیرون نمی‌زند — اشتراک تمام شد → پکیج بسته
 *  ۳) لایسنسِ خریدِ مستقلِ پولیِ با اعتبارِ بیشتر از اشتراک دست‌نخورده می‌ماند
 *  ۴) تغییر/حذف اشتراک توسط مدیر → لایسنس‌ها همگام/باطل می‌شوند
 *  ۵) کالکسیون subscriptions:sync-licenses دادهٔ خراب قدیمی را اصلاح می‌کند
 */
class SubscriptionLicenseSyncTest extends TestCase
{
    use MakesShopData, RefreshDatabase;

    private SubscriptionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SubscriptionService::class);
    }

    private function makePlanWithPackage(array $planAttrs = [], int $freeMonths = 1): array
    {
        $package = $this->makePackage();
        $this->makePackageVersion($package);

        $plan = SubscriptionPlan::create([
            'name' => 'طرح تستی اشتراک',
            'slug' => 'sub-'.uniqid(),
            'duration_months' => 1,
            'price' => 0,
            'is_active' => true,
            'sort_order' => 0,
            'features' => [],
            ...$planAttrs,
        ]);
        $plan->durations()->create(['duration_months' => 1, 'price' => 0, 'is_active' => true, 'sort_order' => 1]);
        $plan->packages()->attach($package->id, ['free_months' => $freeMonths]);

        return [$plan, $package];
    }

    private function makeDirectLicense(int $customerId, int $packageId, array $attrs = []): PackageLicense
    {
        return PackageLicense::create([
            'license_key' => PackageLicense::generateKey(),
            'package_id' => $packageId,
            'customer_id' => $customerId,
            'status' => PackageLicense::STATUS_ACTIVE,
            'starts_at' => now()->subMonths(2),
            'duration_months' => 1,
            ...$attrs,
        ]);
    }

    /* ---------------- سناریوی باگ واقعی ---------------- */

    public function test_subscription_grant_does_not_extend_from_previous_license_expiry(): void
    {
        [$plan, $package] = $this->makePlanWithPackage();
        $customer = $this->makeCustomer();

        // لایسنس قبلی فعال (مثل طرح آغازین رایگان مشتری واقعی) تا ~۱۵ روز دیگر —
        // باگ واقعی: انضای جدید = انضای قبلی + ۱ ماه (≈ ۴۵ روز) با اینکه اشتراک فقط ۱ ماه بود
        $old = $this->makeDirectLicense($customer->id, $package->id, [
            'expires_at' => now()->addDays(15),
        ]);

        // خرید و تأیید اشتراک ۱ ماهه با همین پکیج
        $order = $this->service->purchase($customer, $plan, null, null, null, 1)['order'];
        $subscription = $this->service->approve($order);

        $this->assertNotNull($subscription->expires_at);

        $granted = PackageLicense::query()
            ->where('customer_id', $customer->id)
            ->where('package_id', $package->id)
            ->where('id', '!=', $old->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($granted);
        $this->assertSame($subscription->id, $granted->subscription_id);
        $this->assertSame(PackageLicense::STATUS_REVOKED, $old->fresh()->status);

        // 🔑 انضای جدید تا انضای اشتراک — نه انضای قبلی + ۱ ماه (باگ «۳۸ روز»)
        $this->assertTrue(
            $granted->expires_at->lessThanOrEqualTo($subscription->expires_at->addMinute()),
            'لایسنس اشتراکی نباید از انضای اشتراک بیشتر باشد'
        );
        $this->assertTrue(
            $granted->expires_at->lessThan(now()->addDays(35)),
            'لایسنس اشتراکی نباید از انضای لایسنس قبلی ادامه یابد'
        );

        // لایسنس قبلی باطل شده پس گارد از لایسنس اشتراکی می‌خواند → روزها = روزهای اشتراک (نه ۴۵ روز)
        $renewals = app(RenewalService::class);
        $renewal = $renewals->evaluatePackageRenewal($customer->id, $package->id);
        $this->assertFalse($renewal['allowed']);
        $this->assertSame('window_closed', $renewal['reason']);

        $activeLicense = $renewals->findActiveLicense($customer->id, $package->id);
        $daysRemaining = $activeLicense->days_remaining;
        $this->assertLessThanOrEqual(31, $daysRemaining);
        $this->assertStringContainsString(fa_num($daysRemaining), $renewal['message']);
    }

    /* ---------------- cap و خرید مستقل ---------------- */

    public function test_subscription_does_not_touch_paid_license_with_longer_validity(): void
    {
        [$plan, $package] = $this->makePlanWithPackage();
        $customer = $this->makeCustomer();

        $paid = $this->makeDirectLicense($customer->id, $package->id, [
            'expires_at' => now()->addMonths(6),
        ]);

        $order = $this->service->purchase($customer, $plan, null, null, null, 1)['order'];
        $subscription = $this->service->approve($order);

        // لایسنس پولی با اعتبار بیشتر از اشتراک → دست‌نخورده و تنها لایسنس فعال
        $this->assertSame(PackageLicense::STATUS_ACTIVE, $paid->fresh()->status);
        $this->assertNull($paid->fresh()->subscription_id);
        $this->assertSame(
            1,
            PackageLicense::query()->where('customer_id', $customer->id)->where('package_id', $package->id)->where('status', 'active')->count()
        );
    }

    public function test_subscription_grant_never_outlives_subscription_even_for_unlimited_free_months(): void
    {
        [$plan, $package] = $this->makePlanWithPackage(freeMonths: 0); // «نامحدود» در پیکربندی طرح
        $customer = $this->makeCustomer();

        $order = $this->service->purchase($customer, $plan, null, null, null, 1)['order'];
        $subscription = $this->service->approve($order);

        $granted = PackageLicense::query()
            ->where('customer_id', $customer->id)
            ->where('package_id', $package->id)
            ->where('subscription_id', $subscription->id)
            ->first();

        $this->assertNotNull($granted);
        // free_months=0 در مسیر اشتراکی یعنی «تا انضای اشتراک» — نه لایسنس نامحدودِ جدا از اشتراک
        $this->assertNotNull($granted->expires_at);
        $this->assertTrue($granted->expires_at->lessThanOrEqualTo($subscription->expires_at->addMinute()));
    }

    /* ---------------- همگام‌سازی با تغییر اشتراک ---------------- */

    public function test_admin_expiry_adjustment_syncs_subscription_licenses(): void
    {
        [$plan, $package] = $this->makePlanWithPackage();
        $customer = $this->makeCustomer();

        $order = $this->service->purchase($customer, $plan, null, null, null, 1)['order'];
        $subscription = $this->service->approve($order);

        $license = PackageLicense::query()->where('subscription_id', $subscription->id)->first();
        $this->assertNotNull($license);

        // +۳ ماه توسط مدیر → لایسنس همان cap خودش می‌ماند (شروع + ۱ ماهِ پیکربندی)
        // و همچنان ≤ انضای جدیدِ اشتراک
        $subscription->update([
            'end_date' => now()->addMonths(4)->toDateString(),
            'expires_at' => now()->addMonths(4),
        ]);
        $this->service->syncSubscriptionLicenses($subscription->fresh());

        $this->assertTrue(
            $license->fresh()->expires_at->lessThanOrEqualTo($subscription->refresh()->expires_at->addMinute())
        );

        // -۲ ماه → لایسنس هم کوتاه می‌شود (cap = انضای اشتراک)
        $subscription->update([
            'end_date' => now()->addWeeks(2)->toDateString(),
            'expires_at' => now()->addWeeks(2),
        ]);
        $this->service->syncSubscriptionLicenses($subscription->fresh());

        $this->assertTrue($license->fresh()->expires_at->lessThanOrEqualTo(now()->addWeeks(2)->addMinute()));
    }

    public function test_deleting_subscription_revokes_its_licenses(): void
    {
        [$plan, $package] = $this->makePlanWithPackage();
        $customer = $this->makeCustomer();

        $order = $this->service->purchase($customer, $plan, null, null, null, 1)['order'];
        $subscription = $this->service->approve($order);

        $license = PackageLicense::query()->where('subscription_id', $subscription->id)->first();
        $this->assertNotNull($license);

        $subscription->delete();

        $this->assertSame(PackageLicense::STATUS_REVOKED, $license->fresh()->status);
        $this->assertFalse($license->fresh()->isActive());
    }

    /* ---------------- کالکسیون sync ---------------- */

    public function test_sync_command_repairs_stale_license_and_closes_expired_subscription_licenses(): void
    {
        [$plan, $package] = $this->makePlanWithPackage();
        $customer = $this->makeCustomer();

        $order = $this->service->purchase($customer, $plan, null, null, null, 1)['order'];
        $subscription = $this->service->approve($order);

        $license = PackageLicense::query()->where('subscription_id', $subscription->id)->first();

        // دادهٔ خراب قدیمی (باگ «۳۸ روز»): انضای لایسنس از انضای اشتراک جلو زده
        $license->update(['expires_at' => now()->addDays(38)]);
        $this->assertTrue($license->expires_at->greaterThan($subscription->expires_at));

        $this->artisan('subscriptions:sync-licenses')->assertSuccessful();

        // اصلاح شد: انضا = انضای اشتراک
        $this->assertTrue(
            $license->fresh()->expires_at->lessThanOrEqualTo($subscription->refresh()->expires_at->addMinute())
        );

        // اشتراک منقضی می‌شود → لایسنش هم باید بسته شود
        $subscription->update([
            'status' => 'expired',
            'expires_at' => now()->subDay(),
            'end_date' => now()->subDay()->toDateString(),
        ]);

        $this->artisan('subscriptions:sync-licenses')->assertSuccessful();

        $this->assertSame(PackageLicense::STATUS_EXPIRED, $license->fresh()->status);
        $this->assertFalse($license->fresh()->isActive());
    }
}
