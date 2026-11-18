<?php

namespace Tests\Feature;

use App\Livewire\Customers\Show;
use App\Models\Customer;
use App\Models\PackageLicense;
use App\Models\PackagePurchase;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Template;
use App\Models\TemplateLicense;
use App\Models\TemplatePurchase;
use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

/**
 * Task 88 — پروندهٔ کامل مشتری در پنل مدیریت (Customers\Show).
 *
 * پوشش:
 *  ۱) گارد: مهمان → ورود، کاربر غیرمدیر → 403
 *  ۲) نمایش همهٔ بخش‌ها: KPI، نمودار، جدول‌های تفکیک‌شده (پکیج/قالب/اشتراک/سفارش)
 *  ۳) مدیریت اشتراک: ویرایش تاریخ انضا (کم/زیاد)، ± ماه سریع، حذف، صدور از طرح با لایسنس پکیج‌ها
 *  ۴) مدیریت لایسنس: ویرایش انضا، ± ماه، ابطال/فعال‌سازی، حذف، صدور دستی
 *  ۵) لایسنس قالب: ابطال/حذف
 */
class CustomerShowTest extends TestCase
{
    use MakesShopData, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'مدیر تست',
            'email' => 'admin-show-'.uniqid().'@example.com',
            'password' => 'password123',
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    private function makePaidPurchase(array $licenseAttrs = [], array $planAttrs = []): array
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

        return [$customer, $package, $plan, $purchase, $license->fresh()];
    }

    private function makeTemplatePurchase(Customer $customer, array $attrs = []): TemplatePurchase
    {
        $template = Template::create([
            'name' => 'قالب تست '.uniqid(),
            'slug' => 'test-template-'.uniqid(),
            'short_description' => 'توضیح',
            'description' => '<p>توضیح</p>',
            'price' => 200000,
            'discount' => 0,
            'status' => Template::STATUS_ACTIVE,
            'sort_order' => 0,
        ]);

        $purchase = TemplatePurchase::create(array_merge([
            'template_id' => $template->id,
            'customer_id' => $customer->id,
            'amount' => 200000,
            'original_amount' => 200000,
            'purchase_type' => TemplatePurchase::TYPE_NORMAL,
            'status' => TemplatePurchase::STATUS_PAID,
            'paid_at' => now(),
        ], $attrs));

        TemplateLicense::issueFor($purchase);

        return $purchase->fresh();
    }

    /* ---------------- گاردها ---------------- */

    public function test_guest_is_redirected_to_login(): void
    {
        $customer = $this->makeCustomer();

        $this->get(route('admin.customers.show', $customer))
            ->assertRedirect(route('login'));
    }

    public function test_non_admin_user_gets_403(): void
    {
        $customer = $this->makeCustomer();
        $operator = User::create([
            'name' => 'اپراتور',
            'email' => 'op-'.uniqid().'@example.com',
            'password' => 'password123',
            'role' => 'operator',
            'status' => 'active',
        ]);

        $this->actingAs($operator)
            ->get(route('admin.customers.show', $customer))
            ->assertForbidden();
    }

    /* ---------------- نمایش ---------------- */

    public function test_admin_sees_full_customer_profile_with_breakdowns(): void
    {
        [$customer, $package, $plan, $purchase, $license] = $this->makePaidPurchase([
            'expires_at' => now()->addMonths(6),
        ]);
        $this->makeTemplatePurchase($customer);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.customers.show', $customer))
            ->assertOk();

        $html = $response->getContent();

        // هویت مشتری
        $this->assertStringContainsString($customer->name, $html);
        $this->assertStringContainsString($customer->update_code, $html);

        // KPI مجموع پرداخت = پکیج (۱۰۰ هزار) + قالب (۲۰۰ هزار)
        $this->assertStringContainsString('مجموع پرداخت', $html);

        // تفکیک‌ها
        $this->assertStringContainsString('خریدهای پکیج', $html);
        $this->assertStringContainsString('لایسنس‌های پکیج', $html);
        $this->assertStringContainsString('قالب‌ها', $html);
        $this->assertStringContainsString('اشتراک‌ها', $html);
        $this->assertStringContainsString('سفارش‌های طرح اشتراک', $html);

        // ردیف خرید پکیج
        $this->assertStringContainsString($plan->name, $html);
        // ردیف خرید قالب
        $this->assertStringContainsString('اختصاصی' === '' ? '' : 'قالب تست', $html);
        // کلید لایسنس
        $this->assertStringContainsString($license->license_key, $html);

        // نمودارها
        $this->assertStringContainsString('تفکیک مبلغ خریدها بر اساس نوع', $html);
        $this->assertStringContainsString('روند خرید ۱۲ ماه اخیر', $html);
    }

    public function test_stats_are_accounted_without_double_counting(): void
    {
        [$customer, , , , $license] = $this->makePaidPurchase([
            'expires_at' => now()->addMonths(6),
        ]);
        $this->makeTemplatePurchase($customer);

        $component = new Show;
        $component->customer = $customer;
        $stats = $component->stats;

        $this->assertSame(100000, $stats['package_revenue']);
        $this->assertSame(200000, $stats['template_revenue']);
        $this->assertSame(300000, $stats['total_revenue']);
        $this->assertSame(2, $stats['total_purchases']);
        $this->assertSame(1, $stats['licenses_active']);
        $this->assertSame(1, $stats['template_licenses_active']);
        $this->assertSame(0, $stats['subs_total']);
    }

    /* ---------------- مدیریت اشتراک ---------------- */

    private function makeSubscription(Customer $customer, array $attrs = []): Subscription
    {
        return Subscription::create(array_merge([
            'customer_id' => $customer->id,
            'price' => 500000,
            'discount' => 0,
            'payment_status' => 'paid',
            'status' => 'active',
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => now()->addMonths(4)->toDateString(),
            'expires_at' => now()->addMonths(4)->endOfDay(),
            'description' => 'اشتراک تستی',
        ], $attrs));
    }

    public function test_admin_can_edit_subscription_expiry(): void
    {
        [$customer] = $this->makePaidPurchase();
        $subscription = $this->makeSubscription($customer);

        $newEnd = now()->addMonths(10)->toDateString();

        // فراخوانی مستقیم متد کامپوننت (قطعی‌تر از شبیه‌سازی درخواست لیووایر)
        $component = new Show;
        $component->customer = $customer;
        $component->subscriptionId = $subscription->id;
        $component->subscriptionForm = [
            'subscription_plan_id' => $subscription->subscription_plan_id,
            'price' => '500000',
            'discount' => '0',
            'payment_status' => 'paid',
            'status' => 'active',
            'start_date' => $subscription->start_date->format('Y-m-d'),
            'end_date' => $newEnd,
            'description' => 'تمدید دستی',
        ];

        $component->saveSubscription();

        $subscription->refresh();

        $this->assertSame($newEnd, $subscription->end_date->format('Y-m-d'));
        $this->assertTrue($subscription->expires_at->endOfDay()->isFuture());
        $this->assertSame('تمدید دستی', $subscription->description);
    }

    public function test_admin_can_increase_and_decrease_subscription_expiry_quickly(): void
    {
        [$customer] = $this->makePaidPurchase();
        $subscription = $this->makeSubscription($customer, [
            'end_date' => now()->addMonths(4)->toDateString(),
            'expires_at' => now()->addMonths(4)->endOfDay(),
        ]);

        $component = new Show;
        $component->customer = $customer;

        // +۳ ماه
        $component->adjustSubscriptionExpiry($subscription->id, 3);

        $subscription->refresh();
        $this->assertTrue(
            $subscription->expires_at->between(
                now()->addMonths(6)->subDays(3),
                now()->addMonths(8)->addDays(3)
            ),
            'بعد از +۳ ماه باید حدود ۷ ماه مانده باشد (got '.$subscription->expires_at->format('Y-m-d').')'
        );

        // -۱ ماه
        $component->adjustSubscriptionExpiry($subscription->id, -1);

        $subscription->refresh();
        $this->assertTrue(
            $subscription->expires_at->between(
                now()->addMonths(5)->subDays(3),
                now()->addMonths(7)->addDays(3)
            ),
            'بعد از -۱ ماه باید حدود ۶ ماه مانده باشد (got '.$subscription->expires_at->format('Y-m-d').')'
        );
    }

    public function test_admin_cannot_shrink_subscription_into_the_past(): void
    {
        [$customer] = $this->makePaidPurchase();
        $subscription = $this->makeSubscription($customer, [
            'end_date' => now()->addDays(20)->toDateString(),
            'expires_at' => now()->addDays(20)->endOfDay(),
        ]);

        $component = new Show;
        $component->customer = $customer;

        $component->adjustSubscriptionExpiry($subscription->id, -3);

        $subscription->refresh();
        $this->assertTrue($subscription->expires_at->isFuture(), 'انضا نباید در گذشته برود');
    }

    public function test_reserved_subscription_is_not_editable(): void
    {
        [$customer] = $this->makePaidPurchase();
        $subscription = $this->makeSubscription($customer, [
            'status' => Subscription::STATUS_RESERVED,
            'reserved_activate_at' => now()->addDays(10),
            'start_date' => null,
            'end_date' => null,
            'expires_at' => null,
        ]);

        $component = new Show;
        $component->customer = $customer;
        $component->subscriptionId = $subscription->id;
        $component->subscriptionForm = [
            'price' => '1', 'discount' => '0', 'payment_status' => 'paid', 'status' => 'active',
            'start_date' => now()->toDateString(), 'end_date' => now()->addMonth()->toDateString(),
            'description' => '',
        ];

        $component->openSubscriptionEdit($subscription->id);

        // openSubscriptionEdit برای رزروشده → مودال باز نمی‌شود
        $this->assertFalse($component->showSubscriptionModal);

        $component->adjustSubscriptionExpiry($subscription->id, 3);
        $subscription->refresh();
        $this->assertSame(Subscription::STATUS_RESERVED, $subscription->status);
    }

    public function test_admin_can_delete_subscription(): void
    {
        [$customer] = $this->makePaidPurchase();
        $subscription = $this->makeSubscription($customer);

        $component = new Show;
        $component->customer = $customer;
        $component->deleteSubscriptionId = $subscription->id;

        $component->deleteSubscription();

        $this->assertDatabaseMissing('subscriptions', ['id' => $subscription->id]);
    }

    public function test_admin_can_grant_plan_subscription_with_package_licenses(): void
    {
        [$customer, $package] = $this->makePaidPurchase();

        $plan = SubscriptionPlan::create([
            'name' => 'طرح طلایی',
            'slug' => 'gold-'.uniqid(),
            'description' => '',
            'duration_months' => 6,
            'price' => 800000,
            'discount_price' => null,
            'is_one_time' => false,
            'is_active' => true,
            'sort_order' => 0,
            'features' => [],
        ]);
        $plan->packages()->attach($package->id, ['free_months' => 3]);

        $component = new Show;
        $component->customer = $customer;
        $component->subscriptionMode = 'plan';
        $component->grantPlanPackages = true;
        $component->subscriptionForm = [
            'subscription_plan_id' => $plan->id,
            'price' => '',
            'discount' => 0,
            'payment_status' => 'paid',
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'description' => '',
        ];

        $component->saveSubscription();

        // اشتراک طرح‌محور صادر شد
        $this->assertDatabaseHas('subscriptions', [
            'customer_id' => $customer->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
        ]);

        // لایسنس پکیج همراه صادر شد (پایه ۱ + تازه ۱ = حداقل ۲)
        $licenseCount = PackageLicense::query()
            ->where('customer_id', $customer->id)
            ->where('package_id', $package->id)
            ->count();
        $this->assertGreaterThanOrEqual(2, $licenseCount);
    }

    /* ---------------- مدیریت لایسنس ---------------- */

    public function test_admin_can_edit_license_expiry_and_status(): void
    {
        [$customer, , , , $license] = $this->makePaidPurchase([
            'expires_at' => now()->addMonths(6),
        ]);

        $newExpiry = now()->addMonths(12)->toDateString();

        $component = new Show;
        $component->customer = $customer;
        $component->licenseId = $license->id;
        $component->licenseForm = [
            'package_id' => $license->package_id,
            'duration_months' => '12',
            'status' => 'active',
            'expires_at' => $newExpiry,
            'notes' => 'تمدید ادمین',
        ];

        $component->saveLicense();

        $license->refresh();
        $this->assertSame($newExpiry, $license->expires_at->format('Y-m-d'));
        $this->assertSame('تمدید ادمین', $license->notes);
    }

    public function test_admin_can_set_license_unlimited_by_clearing_expiry(): void
    {
        [$customer, , , , $license] = $this->makePaidPurchase([
            'expires_at' => now()->addMonths(6),
        ]);

        $component = new Show;
        $component->customer = $customer;
        $component->licenseId = $license->id;
        $component->licenseForm = [
            'package_id' => $license->package_id,
            'status' => 'active',
            'expires_at' => '',
            'notes' => '',
        ];

        $component->saveLicense();

        $license->refresh();
        $this->assertNull($license->expires_at);
        $this->assertTrue($license->isUnlimited());
    }

    public function test_admin_can_adjust_license_expiry_by_months(): void
    {
        [$customer, , , , $license] = $this->makePaidPurchase([
            'expires_at' => now()->addMonths(2),
        ]);

        $component = new Show;
        $component->customer = $customer;

        $component->adjustLicenseExpiry($license->id, 6);

        $license->refresh();
        $this->assertTrue($license->expires_at->isFuture());
        $this->assertTrue($license->expires_at->between(now()->addMonths(7)->subDays(3), now()->addMonths(9)->addDays(3)));
    }

    public function test_admin_can_revoke_and_restore_license(): void
    {
        [$customer, , , , $license] = $this->makePaidPurchase();

        $component = new Show;
        $component->customer = $customer;

        $component->toggleLicense($license->id);
        $this->assertSame('revoked', $license->fresh()->status);

        $component->toggleLicense($license->id);
        $this->assertSame('active', $license->fresh()->status);
    }

    public function test_admin_can_delete_license(): void
    {
        [$customer, , , , $license] = $this->makePaidPurchase();

        $component = new Show;
        $component->customer = $customer;
        $component->deleteLicenseId = $license->id;

        $component->deleteLicense();

        $this->assertDatabaseMissing('package_licenses', ['id' => $license->id]);
    }

    public function test_admin_can_issue_manual_license(): void
    {
        [$customer, $package] = $this->makePaidPurchase();

        $component = new Show;
        $component->customer = $customer;
        $component->licenseForm = [
            'package_id' => $package->id,
            'duration_months' => '3',
            'status' => 'active',
            'expires_at' => '',
            'notes' => 'صدور دستی',
        ];

        $component->saveLicense();

        $this->assertDatabaseHas('package_licenses', [
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'duration_months' => 3,
            'status' => 'active',
        ]);
    }

    /* ---------------- لایسنس قالب ---------------- */

    public function test_admin_can_revoke_and_delete_template_license(): void
    {
        [$customer] = $this->makePaidPurchase();
        $purchase = $this->makeTemplatePurchase($customer);
        $license = TemplateLicense::query()->where('purchase_id', $purchase->id)->first();

        $component = new Show;
        $component->customer = $customer;

        $component->toggleTemplateLicense($license->id);
        $this->assertSame('revoked', $license->fresh()->status);

        $component->toggleTemplateLicense($license->id);
        $this->assertSame('active', $license->fresh()->status);

        $component->confirmDeleteTemplateLicense($license->id);
        $component->deleteTemplateLicense();

        $this->assertDatabaseMissing('template_licenses', ['id' => $license->id]);
    }

    /* ---------------- گارد مالکیت ---------------- */

    public function test_actions_are_scoped_to_the_route_customer(): void
    {
        [$customerA, , , , $licenseA] = $this->makePaidPurchase();
        [$customerB] = $this->makePaidPurchase();

        $component = new Show;
        $component->customer = $customerB;
        $component->deleteLicenseId = $licenseA->id;

        // حذف لایسنسِ مشتری دیگر از صفحهٔ مشتری دوم → 404
        $this->expectException(HttpException::class);
        $component->deleteLicense();
    }
}
