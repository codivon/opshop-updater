<?php

namespace Tests\Feature;

use App\Livewire\Shop\CustomerProfile;
use App\Models\Customer;
use App\Models\Gateway;
use App\Models\Package;
use App\Models\PackageLicense;
use App\Models\PackagePurchase;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Task 96 — پالایش صفحهٔ پروفایل مشتری:
 *
 * ۱) فیکس ارور صفحه‌بندی لایسنس‌ها: «The GET method is not supported for route
 *    livewire/update. Supported methods: POST.» — ریشه: کامپوننت تریت WithPagination
 *    نداشت؛ ویوهای صفحه‌بندی منتشرشدهٔ اپ (<a href>) در درخواست‌های update لایووایر
 *    آدرسشان به livewire/update حل می‌شد. با تریت، ویوهای لایووایر (wire:click) رندر می‌شوند.
 *
 * ۲) تب «اشتراک‌های من»: اشتراک‌های خریداری‌شده (فعال/منقضی/رزروشده) + سفارش‌های
 *    پرداخت‌شده در انتظار تأیید مدیر.
 *
 * ۳) پکیج‌های رایگانِ همراه اشتراک در تب پکیج‌ها با نشان «به واسطهٔ اشتراک».
 *
 * ۴) «مجموع خرید» شامل خریدهای اشتراک هم می‌شود (قبلاً فقط پکیج‌ها را می‌شمرد).
 */
class CustomerProfileSubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gateway::firstOrCreate(['key' => 'local'], ['name' => 'درگاه آزمایشی', 'is_active' => true]);
    }

    private function makeCustomer(): Customer
    {
        return Customer::create([
            'name' => 'مشتری اشتراک',
            'email' => 'subs-'.uniqid().'@example.com',
            'website_url' => 'https://subtest.example.com',
            'status' => Customer::STATUS_ACTIVE,
        ]);
    }

    private function makePlan(array $attrs = []): SubscriptionPlan
    {
        return SubscriptionPlan::create(array_merge([
            'name' => 'طرح اشتراکی',
            'slug' => 'profile-plan-'.uniqid(),
            'duration_months' => 3,
            'price' => 250000,
            'discount_price' => 0,
            'is_one_time' => false,
            'is_active' => true,
            'sort_order' => 0,
        ], $attrs));
    }

    private function makeSubscription(Customer $customer, SubscriptionPlan $plan, array $attrs = []): Subscription
    {
        return Subscription::create(array_merge([
            'customer_id' => $customer->id,
            'subscription_plan_id' => $plan->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
            'expires_at' => now()->addMonths(3),
            'status' => 'active',
            'price' => 250000,
            'discount' => 0,
            'final_amount' => 250000,
            'payment_status' => 'paid',
        ], $attrs));
    }

    private function makePaidOrder(Customer $customer, SubscriptionPlan $plan, array $attrs = []): SubscriptionOrder
    {
        return SubscriptionOrder::create(array_merge([
            'subscription_plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => 250000,
            'discount' => 0,
            'final_amount' => 250000,
            'gateway' => 'local',
            'status' => SubscriptionOrder::STATUS_PAID,
            'admin_status' => SubscriptionOrder::ADMIN_STATUS_PENDING,
            'paid_at' => now(),
        ], $attrs));
    }

    /* -------------------------------------------------------------- */
    /*  ۱) فیکس صفحه‌بندی — دیگر لینک GET به livewire/update رندر نمی‌شود */
    /* -------------------------------------------------------------- */

    public function test_license_pagination_uses_wire_click_and_page_2_works(): void
    {
        $customer = $this->makeCustomer();

        // ۱۱ لایسنس → صفحهٔ ۱ = ۱۰ رکورد، صفحهٔ ۲ = ۱ رکورد (قدیمی‌ترین)
        $oldestKey = null;

        for ($i = 1; $i <= 11; $i++) {
            $package = Package::create([
                'project_id' => Project::create(['name' => 'پ', 'slug' => 'p-'.uniqid()])->id,
                'slug' => 'pag-pkg-'.uniqid(),
                'name' => 'پکیج صفحه‌بندی '.$i,
                'short_description' => '-',
                'description' => '-',
                'is_free' => false,
                'default_price' => 100000,
                'status' => Package::STATUS_ACTIVE,
                'sort_order' => 0,
            ]);

            $key = 'LIC-PAG-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT);

            PackageLicense::create([
                'license_key' => $key,
                'package_id' => $package->id,
                'customer_id' => $customer->id,
                'status' => PackageLicense::STATUS_ACTIVE,
            ]);

            if ($i === 1) {
                $oldestKey = $key; // قدیمی‌ترین → صفحهٔ ۲
            }
        }

        $component = Livewire::actingAs($customer, 'customer')
            ->test(CustomerProfile::class)
            ->call('switchTab', 'licenses');

        $html = $component->html();

        // ریشهٔ باگ: لینک‌های <a href="…/livewire/update?licensesPage=2"> — دیگر نباید رندر شوند
        $this->assertStringNotContainsString('livewire/update?licensesPage', $html);
        // ویو لایووایر با wire:click رندر شده است
        $this->assertStringContainsString("gotoPage(2, 'licensesPage')", $html);

        // کلیک صفحهٔ ۲ (همان کاری که wire:click انجام می‌دهد) باید رکوردهای صفحهٔ ۲ را بدهد
        $component->call('gotoPage', 2, 'licensesPage')
            ->assertSee($oldestKey)
            ->assertDontSee('LIC-PAG-0011'); // رکورد صفحهٔ ۱ دیگر دیده نمی‌شود
    }

    /* -------------------------------------------------------------- */
    /*  ۲) تب اشتراک‌های من */
    /* -------------------------------------------------------------- */

    public function test_subscriptions_tab_lists_purchased_subscription(): void
    {
        $customer = $this->makeCustomer();
        $plan = $this->makePlan(['name' => 'طرح طلایی پروفایل']);
        $project = Project::create(['name' => 'سایت فروشگاه من', 'slug' => 'my-shop-'.uniqid()]);

        $this->makeSubscription($customer, $plan, ['project_id' => $project->id]);

        Livewire::actingAs($customer, 'customer')
            ->test(CustomerProfile::class)
            ->call('switchTab', 'subscriptions')
            ->assertSee('طرح طلایی پروفایل')
            ->assertSee('سایت فروشگاه من')
            ->assertSee('فعال')
            ->assertSee('روز باقی‌مانده')
            ->assertSee('250,000 تومان')
            ->assertSee('۳ ماه');
    }

    public function test_subscriptions_tab_shows_paid_order_awaiting_admin_approval(): void
    {
        $customer = $this->makeCustomer();
        $plan = $this->makePlan(['name' => 'طرح در انتظار']);

        // سفارش پرداخت‌شده ولی هنوز بدون اشتراک صادرشده
        $this->makePaidOrder($customer, $plan, [
            'status' => SubscriptionOrder::STATUS_PAID,
            'admin_status' => SubscriptionOrder::ADMIN_STATUS_PENDING,
        ]);

        Livewire::actingAs($customer, 'customer')
            ->test(CustomerProfile::class)
            ->call('switchTab', 'subscriptions')
            ->assertSee('طرح در انتظار')
            ->assertSee('در انتظار تأیید مدیر')
            ->assertSee('پس از تأیید مدیر، اشتراک فعال شده و همین‌جا نمایش داده می‌شود.');
    }

    public function test_subscriptions_tab_shows_expired_badge_for_past_subscription(): void
    {
        $customer = $this->makeCustomer();
        $plan = $this->makePlan(['name' => 'طرح قدیمی']);

        $this->makeSubscription($customer, $plan, [
            'expires_at' => now()->subDays(2),
            'end_date' => now()->subDays(2)->toDateString(),
        ]);

        Livewire::actingAs($customer, 'customer')
            ->test(CustomerProfile::class)
            ->call('switchTab', 'subscriptions')
            ->assertSee('طرح قدیمی')
            ->assertSee('منقضی‌شده');
    }

    /* -------------------------------------------------------------- */
    /*  ۳) پکیج‌های همراه اشتراک در تب پکیج‌ها */
    /* -------------------------------------------------------------- */

    public function test_packages_tab_shows_subscription_bundled_packages_with_note(): void
    {
        $customer = $this->makeCustomer();
        $plan = $this->makePlan(['name' => 'طرح همراه پکیج']);

        $limited = Package::create([
            'project_id' => Project::create(['name' => 'پ', 'slug' => 'p-'.uniqid()])->id,
            'slug' => 'bundle-limited-'.uniqid(),
            'name' => 'پکیج همراه محدود',
            'short_description' => '-',
            'description' => '-',
            'is_free' => false,
            'default_price' => 100000,
            'status' => Package::STATUS_ACTIVE,
            'sort_order' => 0,
        ]);

        $unlimited = Package::create([
            'project_id' => Project::create(['name' => 'پ', 'slug' => 'p-'.uniqid()])->id,
            'slug' => 'bundle-unlimited-'.uniqid(),
            'name' => 'پکیج همراه نامحدود',
            'short_description' => '-',
            'description' => '-',
            'is_free' => false,
            'default_price' => 100000,
            'status' => Package::STATUS_ACTIVE,
            'sort_order' => 0,
        ]);

        $plan->packages()->attach([$limited->id => ['free_months' => 3], $unlimited->id => ['free_months' => 0]]);

        $subscription = $this->makeSubscription($customer, $plan);

        // لایسنسِ صادرشده از طریق اشتراک (مثل approve واقعی)
        PackageLicense::create([
            'license_key' => 'LIC-BUNDLE-'.uniqid(),
            'package_id' => $limited->id,
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'status' => PackageLicense::STATUS_ACTIVE,
            'expires_at' => now()->addMonths(3),
        ]);

        Livewire::actingAs($customer, 'customer')
            ->test(CustomerProfile::class)
            ->call('switchTab', 'purchases')
            ->assertSee('همراه اشتراک «طرح همراه پکیج»')
            ->assertSee('به واسطهٔ اشتراک')
            ->assertSee('این پکیج به واسطهٔ اشتراک «طرح همراه پکیج» برای شما فعال شده است')
            ->assertSee('پکیج همراه محدود')
            ->assertSee('پکیج همراه نامحدود')
            ->assertSee('۳ ماه رایگان')
            ->assertSee('دسترسی نامحدود')
            ->assertSee('لایسنس فعال')
            // بدون خرید مستقیم پکیج، حالت خالی نباید نمایش داده شود
            ->assertDontSee('هنوز پکیجی نخریده‌اید');
    }

    public function test_expired_subscription_bundles_are_hidden_from_packages_tab(): void
    {
        $customer = $this->makeCustomer();
        $plan = $this->makePlan(['name' => 'طرح منقضی']);

        $package = Package::create([
            'project_id' => Project::create(['name' => 'پ', 'slug' => 'p-'.uniqid()])->id,
            'slug' => 'bundle-expired-'.uniqid(),
            'name' => 'پکیج همراه منقضی',
            'short_description' => '-',
            'description' => '-',
            'is_free' => false,
            'default_price' => 100000,
            'status' => Package::STATUS_ACTIVE,
            'sort_order' => 0,
        ]);

        $plan->packages()->attach($package->id, ['free_months' => 1]);

        $this->makeSubscription($customer, $plan, [
            'expires_at' => now()->subDay(),
            'end_date' => now()->subDay()->toDateString(),
        ]);

        Livewire::actingAs($customer, 'customer')
            ->test(CustomerProfile::class)
            ->call('switchTab', 'purchases')
            ->assertDontSee('همراه اشتراک «طرح منقضی»')
            ->assertDontSee('به واسطهٔ اشتراک')
            ->assertSee('هنوز پکیجی نخریده‌اید');
    }

    /* -------------------------------------------------------------- */
    /*  ۴) مجموع خرید شامل خریدهای اشتراک */
    /* -------------------------------------------------------------- */

    public function test_stats_spent_includes_subscription_orders(): void
    {
        $customer = $this->makeCustomer();
        $plan = $this->makePlan();

        // خرید اشتراک: ۲۵۰٬۰۰۰ تومان پرداخت‌شده
        $this->makePaidOrder($customer, $plan, [
            'status' => SubscriptionOrder::STATUS_PAID,
            'admin_status' => SubscriptionOrder::ADMIN_STATUS_APPROVED,
            'final_amount' => 250000,
        ]);

        // خرید مستقیم پکیج: ۵۰٬۰۰۰ تومان
        $package = Package::create([
            'project_id' => Project::create(['name' => 'پ', 'slug' => 'p-'.uniqid()])->id,
            'slug' => 'spent-pkg-'.uniqid(),
            'name' => 'پکیج خریده‌شده',
            'short_description' => '-',
            'description' => '-',
            'is_free' => false,
            'default_price' => 50000,
            'status' => Package::STATUS_ACTIVE,
            'sort_order' => 0,
        ]);

        PackagePurchase::create([
            'package_id' => $package->id,
            'customer_id' => $customer->id,
            'amount' => 50000,
            'status' => PackagePurchase::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $stats = Livewire::actingAs($customer, 'customer')
            ->test(CustomerProfile::class)
            ->instance()->stats;

        // قبلاً فقط ۵۰٬۰۰۰ شمرده می‌شد و خرید اشتراک نادیده گرفته می‌شد
        $this->assertSame(300000, $stats['spent']);
        $this->assertSame(1, $stats['purchases']);
    }

    /* -------------------------------------------------------------- */
    /*  ۵) بنر هشدار انقضای نزدیک */
    /* -------------------------------------------------------------- */

    public function test_expiring_subscription_shows_renewal_notice(): void
    {
        $customer = $this->makeCustomer();
        $plan = $this->makePlan(['name' => 'طرح رو به انقضا']);

        $this->makeSubscription($customer, $plan, [
            'expires_at' => now()->addDays(5),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        Livewire::actingAs($customer, 'customer')
            ->test(CustomerProfile::class)
            ->assertSee('اشتراک «طرح رو به انقضا» شما تا')
            ->assertSee('تمدید کنید');
    }

    public function test_far_future_subscription_shows_no_renewal_notice(): void
    {
        $customer = $this->makeCustomer();
        $plan = $this->makePlan();

        $this->makeSubscription($customer, $plan, [
            'expires_at' => now()->addDays(40),
            'end_date' => now()->addDays(40)->toDateString(),
        ]);

        $html = Livewire::actingAs($customer, 'customer')
            ->test(CustomerProfile::class)
            ->html();

        $this->assertStringNotContainsString('برای قطع نشدن خدمات', $html);
        $this->assertStringNotContainsString('اشتراک شما منقضی شده است', $html);
    }
}
