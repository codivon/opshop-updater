<?php

namespace Tests\Feature;

use App\Livewire\Shop\SubscriptionsIndex;
use App\Models\Project;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

/**
 * Task 94 — انتخاب پروژه هنگام خرید اشتراک (درخواست کاربر):
 *
 *  «وقتی اشتراک خریداری می‌کنیم، در صفحه اشتراک، بتونیم پروژه را هم انتخاب کنیم،
 *   و روی فیلد "کد آپدیت پروژه شما" قرارش بده و اگر یک پروژه بود، همون سلکت شده باشه.»
 *
 *  - سلکت «پروژهٔ شما» روی فیلد کد آپدیت رندر می‌شود (فقط وقتی پروژهٔ فعال هست)
 *  - تک‌پروژه → از قبل انتخاب است
 *  - خرید الزاماً پروژه می‌گیرد؛ snapshot سفارش + اشتراکِ صادرشده به همان پروژه گره می‌خورد
 *    (API پکیج/قالبِ فروشگاهِ خریدار بر همین اساس کار می‌کند)
 *  - فروشگاه بدون پروژهٔ فعال → فرم بدون سلکت و خرید بدون پروژه همچنان کار می‌کند
 */
class SubscriptionProjectSelectionTest extends TestCase
{
    use MakesShopData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // کپچای فرم پرداخت خاموش (پیش‌فرض روشن است)
        Setting::set('sec_captcha_checkout', '0', 'string', 'security');
        Cache::forget('setting:sec_captcha_checkout');
    }

    private function makePlan(array $attrs = []): SubscriptionPlan
    {
        $plan = SubscriptionPlan::create(array_merge([
            'name' => 'طرح پروژه‌دار',
            'slug' => 'project-plan-'.uniqid(),
            'duration_months' => 1,
            'price' => 0,
            'discount_price' => 0,
            'is_one_time' => false,
            'is_active' => true,
            'sort_order' => 0,
        ], $attrs));

        // صفحهٔ طرح‌ها فقط طرح‌های دارای پکیج فعال را نشان می‌دهد
        $package = $this->makePackage();
        $this->makePackageVersion($package);
        $plan->packages()->attach($package->id, ['free_months' => 1]);

        return $plan;
    }

    /* -------------------------------------------------------------- */
    /*  رندر فرم */
    /* -------------------------------------------------------------- */

    public function test_single_active_project_is_preselected(): void
    {
        $project = Project::create(['name' => 'پروژهٔ تک', 'slug' => 'single-'.uniqid(), 'status' => 'active']);

        Livewire::test(SubscriptionsIndex::class)
            ->assertSet('project', (string) $project->id);
    }

    public function test_multiple_projects_start_unselected_and_select_shows(): void
    {
        Project::create(['name' => 'پ۱', 'slug' => 'p1-'.uniqid(), 'status' => 'active']);
        Project::create(['name' => 'پ۲', 'slug' => 'p2-'.uniqid(), 'status' => 'active']);

        $plan = $this->makePlan();

        // سلکت پروژه داخل فرمِ باز‌شدهٔ کارت طرح است (setBuying فرم را باز می‌کند)
        Livewire::test(SubscriptionsIndex::class)
            ->call('setBuying', $plan->slug)
            ->assertSet('project', '')
            ->assertSee('پروژهٔ شما')
            ->assertSee('پ۱')
            ->assertSee('پ۲');
    }

    public function test_archived_project_never_appears_or_preselects(): void
    {
        Project::create(['name' => 'بایگانی', 'slug' => 'arch-'.uniqid(), 'status' => 'archived']);

        Livewire::test(SubscriptionsIndex::class)
            ->assertSet('project', '')
            ->assertDontSee('بایگانی');
    }

    public function test_form_lists_all_active_projects_including_fixture_project(): void
    {
        // makePlan → makePackage → پروژهٔ فعالِ خودکار؛ + دو پروژهٔ صریح
        $plan = $this->makePlan();
        $first = Project::create(['name' => 'پ۱', 'slug' => 'p1-'.uniqid(), 'status' => 'active']);
        $second = Project::create(['name' => 'پ۲', 'slug' => 'p2-'.uniqid(), 'status' => 'active']);

        $component = Livewire::test(SubscriptionsIndex::class)->call('setBuying', $plan->slug);

        $this->assertSame('', $component->get('project'));
        $this->assertSame(3, $component->get('projectOptions')->count());
        $this->assertTrue($component->get('projectOptions')->contains('id', $first->id));
        $this->assertTrue($component->get('projectOptions')->contains('id', $second->id));
    }

    /** پکیجِ خودکارِ فیکسچر هم پروژهٔ فعال می‌سازد — تک‌پروژه یعنی همان */
    public function test_fixture_package_project_is_preselected_when_it_is_the_only_one(): void
    {
        $plan = $this->makePlan();

        $component = Livewire::test(SubscriptionsIndex::class)->call('setBuying', $plan->slug);

        $this->assertSame(1, $component->get('projectOptions')->count());
        $this->assertSame((string) $component->get('projectOptions')->first()->id, $component->get('project'));
    }

    /* -------------------------------------------------------------- */
    /*  خرید */
    /* -------------------------------------------------------------- */

    public function test_purchase_without_selected_project_is_rejected_when_projects_exist(): void
    {
        Project::create(['name' => 'پ۱', 'slug' => 'p1-'.uniqid(), 'status' => 'active']);
        Project::create(['name' => 'پ۲', 'slug' => 'p2-'.uniqid(), 'status' => 'active']);

        $customer = $this->makeCustomer();
        $plan = $this->makePlan();

        Livewire::test(SubscriptionsIndex::class)
            ->set('updateCode', $customer->update_code)
            ->set('buyingSlug', $plan->slug)
            ->call('buy')
            ->assertDispatched('toast');

        $this->assertSame(0, SubscriptionOrder::where('customer_id', $customer->id)->count());
    }

    public function test_purchase_with_project_stores_snapshot_and_subscription_gets_project(): void
    {
        $customer = $this->makeCustomer();
        $project = Project::create(['name' => 'پروژهٔ خریدار', 'slug' => 'buyer-'.uniqid(), 'status' => 'active']);
        $plan = $this->makePlan();

        Livewire::test(SubscriptionsIndex::class)
            ->set('updateCode', $customer->update_code)
            ->set('buyingSlug', $plan->slug)
            ->set('project', (string) $project->id)
            ->call('buy')
            ->assertRedirect();

        $order = SubscriptionOrder::where('customer_id', $customer->id)->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertSame($project->id, (int) $order->meta['project']['id']);
        $this->assertSame('پروژهٔ خریدار', $order->meta['project']['name']);

        // تأیید مدیر → اشتراکِ صادرشده به همان پروژه گره می‌خورد
        app(SubscriptionService::class)->approve($order);

        $subscription = Subscription::where('customer_id', $customer->id)->latest('id')->first();
        $this->assertNotNull($subscription);
        $this->assertSame($project->id, $subscription->project_id);
        $this->assertSame('active', $subscription->status);
    }

    public function test_reserved_subscription_also_carries_project(): void
    {
        $customer = $this->makeCustomer();
        $project = Project::create(['name' => 'پروژهٔ رزرو', 'slug' => 'res-'.uniqid(), 'status' => 'active']);
        $plan = $this->makePlan();

        // اشتراکِ فعالِ جاری برای همین طرح → خرید طرح در پنجرهٔ ۵ روزه به‌صورت رزروشده ثبت می‌شود
        Subscription::create([
            'customer_id' => $customer->id,
            'project_id' => $project->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'payment_status' => 'paid',
            'start_date' => now()->subDays(20)->toDateString(),
            'expires_at' => now()->addDays(2)->toDateString(), // در پنجرهٔ ۵ روزه → رزرو
            'price' => 0,
            'final_amount' => 0,
        ]);

        $order = app(SubscriptionService::class)->purchase(
            $customer,
            $plan,
            null,
            null,
            null,
            null,
            $project->id,
        )['order'];

        $this->assertSame($project->id, (int) $order->meta['project']['id']);

        app(SubscriptionService::class)->approve($order->refresh());

        $reserved = Subscription::where('customer_id', $customer->id)->where('status', Subscription::STATUS_RESERVED)->first();
        $this->assertNotNull($reserved);
        $this->assertSame($project->id, $reserved->project_id);
    }

    public function test_purchase_always_carries_preselected_project_when_only_one_exists(): void
    {
        $customer = $this->makeCustomer();
        $plan = $this->makePlan(); // پکیج فیکسچر → تک‌پروژهٔ فعال

        Livewire::test(SubscriptionsIndex::class)
            ->set('updateCode', $customer->update_code)
            ->set('buyingSlug', $plan->slug)
            ->call('buy')
            ->assertRedirect();

        $order = SubscriptionOrder::where('customer_id', $customer->id)->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertSame(1, Project::where('status', 'active')->count());
        $this->assertSame((int) Project::query()->active()->first()->id, (int) $order->meta['project']['id']);
    }

    public function test_invalid_project_id_is_rejected_by_service(): void
    {
        $this->expectException(\RuntimeException::class);

        $customer = $this->makeCustomer();
        $plan = $this->makePlan();

        app(SubscriptionService::class)->purchase($customer, $plan, null, null, null, null, 999999);
    }
}
