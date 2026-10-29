<?php

namespace Tests\Feature;

use App\Livewire\Plans\Index as PlansIndex;
use App\Livewire\Shop\Plans as ShopPlans;
use App\Livewire\Shop\SubscriptionsIndex;
use App\Models\PackageLicense;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanDuration;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

/**
 * پیکربندی مستقل «قابلیت‌ها» و «پکیج‌های همراه» برای هر مدت اعتبار طرح — Task 63.
 *
 * مثال کاربر: مدت ۱ ماهه پکیج‌های ۱ ماهه دارد؛ در مدت ۳ ماهه باید بتوان
 * مدت اعتبار پکیج‌ها را ۳ ماهه (یا هر چیز دیگر) تعریف کرد.
 *  - مدت بدون پیکربندی اختصاصی → ارث‌بری از قابلیت‌ها/پکیج‌های خود طرح
 *  - خرید با مدت انتخابی → snapshot پیکربندی مؤثر همان مدت → تأیید مدیر از snapshot
 */
class PlanDurationConfigTest extends TestCase
{
    use RefreshDatabase, MakesShopData;

    private SubscriptionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SubscriptionService::class);
    }

    /** طرح دو‌مدتی رایگان (۱ ماهه و ۳ ماهه) با قابلیت سطح طرح */
    private function makeMultiDurationPlan(): SubscriptionPlan
    {
        $plan = SubscriptionPlan::create([
            'name'            => 'طرح چندمدتی',
            'slug'            => 'multi-dur-' . uniqid(),
            'duration_months' => 1,
            'price'           => 0,
            'is_active'       => true,
            'sort_order'      => 0,
            'features'        => ['پشتیبانی پایه'],
        ]);

        $plan->durations()->create(['duration_months' => 1, 'price' => 0, 'is_active' => true, 'sort_order' => 1]);
        $plan->durations()->create(['duration_months' => 3, 'price' => 0, 'is_active' => true, 'sort_order' => 2]);

        return $plan;
    }

    public function test_duration_own_packages_are_used_in_snapshot_and_approval(): void
    {
        $plan = $this->makeMultiDurationPlan();

        // پکیج سطح طرح (۱ ماه رایگان) + پکیج اختصاصی مدت ۳ ماهه (۳ ماه رایگان)
        $planPackage = $this->makePackage(['name' => 'پکیج سطح طرح']);
        $plan->packages()->attach($planPackage->id, ['free_months' => 1]);

        $vipPackage = $this->makePackage(['name' => 'پکیج ویژه ۳ ماهه']);
        $duration3  = $plan->durations()->where('duration_months', 3)->first();
        $duration3->packages()->attach($vipPackage->id, ['free_months' => 3]);

        $customer = $this->makeCustomer();

        // خرید با مدت ۳ ماهه → snapshot از پیکربندی همان مدت
        $result = $this->service->purchase($customer, $plan, null, null, null, 3);
        $order  = $result['order'];

        $this->assertTrue($result['is_free']);
        $this->assertSame(
            [$vipPackage->id],
            array_column($order->meta['chosen_duration']['packages'], 'id')
        );
        $this->assertSame(3, (int) $order->meta['chosen_duration']['packages'][0]['free_months']);

        // تأیید مدیر → فقط لایسنس پکیج همان مدت با ۳ ماه اعتبار
        $subscription = $this->service->approve($order);

        $this->assertSame(1, PackageLicense::where('customer_id', $customer->id)->count());

        $license = PackageLicense::where('customer_id', $customer->id)->sole();
        $this->assertSame($vipPackage->id, $license->package_id);
        $this->assertSame(3, (int) $license->duration_months);
        $this->assertNotNull($license->expires_at);
        $this->assertTrue($license->expires_at->gt(now()->addMonths(2)));
    }

    public function test_duration_without_own_config_inherits_plan_packages(): void
    {
        $plan = $this->makeMultiDurationPlan();

        $planPackage = $this->makePackage(['name' => 'پکیج ارثی']);
        $plan->packages()->attach($planPackage->id, ['free_months' => 2]);

        $customer = $this->makeCustomer();

        // مدت ۱ ماهه پیکربندی اختصاصی ندارد → پکیج‌های طرح با همان مدت‌هایشان
        $result = $this->service->purchase($customer, $plan, null, null, null, 1);
        $order  = $result['order'];

        $this->assertSame(
            [$planPackage->id],
            array_column($order->meta['chosen_duration']['packages'], 'id')
        );
        $this->assertSame(2, (int) $order->meta['chosen_duration']['packages'][0]['free_months']);

        $this->service->approve($order);

        $license = PackageLicense::where('customer_id', $customer->id)->sole();
        $this->assertSame($planPackage->id, $license->package_id);
        $this->assertSame(2, (int) $license->duration_months);
    }

    public function test_duration_own_features_override_snapshot(): void
    {
        $plan = $this->makeMultiDurationPlan();

        $duration3 = $plan->durations()->where('duration_months', 3)->first();
        $duration3->update(['features' => ['پشتیبانی VIP سه‌ماهه', 'آپدیت رایگان']]);

        $customer = $this->makeCustomer();

        // مدت ۳ ماهه → قابلیت‌های اختصاصی همان مدت
        $order3 = $this->service->purchase($customer, $plan, null, null, null, 3)['order'];
        $this->assertSame(['پشتیبانی VIP سه‌ماهه', 'آپدیت رایگان'], $order3->meta['plan']['features']);
        $this->assertSame(['پشتیبانی VIP سه‌ماهه', 'آپدیت رایگان'], $order3->meta['chosen_duration']['features']);

        // مدت ۱ ماهه (بدون پیکربندی) → قابلیت‌های خود طرح
        $order1 = $this->service->purchase($customer, $plan, null, null, null, 1)['order'];
        $this->assertSame(['پشتیبانی پایه'], $order1->meta['plan']['features']);
    }

    public function test_admin_panel_saves_and_reloads_per_duration_config(): void
    {
        $plan   = $this->makeMultiDurationPlan();
        $pkgA   = $this->makePackage(['name' => 'پکیج الف']);
        $pkgB   = $this->makePackage(['name' => 'پکیج ب']);
        $plan->packages()->attach($pkgA->id, ['free_months' => 1]);

        $duration1 = $plan->durations()->where('duration_months', 1)->first();
        $duration3 = $plan->durations()->where('duration_months', 3)->first();

        Livewire::test(PlansIndex::class)
            ->call('openEdit', $plan->id)
            ->set('formDurations.1.features', ['پشتیبانی ۳ ماهه VIP', 'آپدیت رایگان'])
            ->set('formDurations.1.packages', [['package_id' => (string) $pkgB->id, 'free_months' => '3']])
            ->call('save')
            ->assertHasNoErrors();

        // قابلیت‌ها روی ردیف مدت ذخیره شد (بازسازی کامل ردیف‌ها → واکشی دوباره بر اساس مدت)
        $duration3After = $plan->durations()->where('duration_months', 3)->first();
        $duration1After = $plan->durations()->where('duration_months', 1)->first();

        $this->assertSame(
            ['پشتیبانی ۳ ماهه VIP', 'آپدیت رایگان'],
            $duration3After->features
        );
        $this->assertNull($duration1After->features); // خالی = ارث‌بری

        // pivot پکیج‌های مدت ۳ ماهه
        $this->assertDatabaseHas('subscription_plan_duration_package', [
            'subscription_plan_duration_id' => $duration3After->id,
            'package_id'                    => $pkgB->id,
            'free_months'                   => 3,
        ]);
        $this->assertDatabaseMissing('subscription_plan_duration_package', [
            'subscription_plan_duration_id' => $duration1After->id,
        ]);

        // باز کردن مودال ویرایش → مقادیر ذخیره‌شده برمی‌گردند
        Livewire::test(PlansIndex::class)
            ->call('openEdit', $plan->id)
            ->assertSet('formDurations.1.features', ['پشتیبانی ۳ ماهه VIP', 'آپدیت رایگان'])
            ->assertSet('formDurations.1.packages.0.package_id', (string) $pkgB->id)
            ->assertSet('formDurations.1.packages.0.free_months', '3');
    }

    public function test_admin_panel_add_and_remove_duration_package_rows(): void
    {
        $plan = $this->makeMultiDurationPlan();
        $pkgA = $this->makePackage(['name' => 'پکیج الف']);

        Livewire::test(PlansIndex::class)
            ->call('openEdit', $plan->id)
            ->set('formDurations.0.packageToAdd', (string) $pkgA->id)
            ->call('addDurationPackage', 0)
            ->assertSet('formDurations.0.packages.0.package_id', (string) $pkgA->id)
            ->assertSet('formDurations.0.packages.0.free_months', '1')
            // افزودن تکراری همان پکیج نادیده گرفته می‌شود
            ->set('formDurations.0.packageToAdd', (string) $pkgA->id)
            ->call('addDurationPackage', 0)
            ->assertCount('formDurations.0.packages', 1)
            ->call('removeDurationPackage', 0, 0)
            ->assertCount('formDurations.0.packages', 0);
    }

    public function test_admin_panel_add_remove_and_save_duration_feature_rows(): void
    {
        $plan = $this->makeMultiDurationPlan();

        Livewire::test(PlansIndex::class)
            ->call('openEdit', $plan->id)
            // افزودن دو ردیف قابلیت به مدت ۳ ماهه (index 1) مثل قابلیت‌های طرح
            ->call('addDurationFeature', 1)
            ->call('addDurationFeature', 1)
            ->set('formDurations.1.features.0', 'پشتیبانی ۳ ماهه VIP')
            ->set('formDurations.1.features.1', '  آپدیت رایگان  ')
            // حذف یک ردیف + افزودن ردیف تازه → reindex درست
            ->call('removeDurationFeature', 1, 0)
            ->call('addDurationFeature', 1)
            ->set('formDurations.1.features.1', 'مشاوره اختصاصی')
            ->call('save')
            ->assertHasNoErrors();

        // ردیف اول حذف شده بود → فقط «آپدیت رایگان» (بدون فاصلهٔ اضافی) و «مشاوره اختصاصی» ذخیره می‌شود
        $duration3After = $plan->durations()->where('duration_months', 3)->first();
        $this->assertSame(['آپدیت رایگان', 'مشاوره اختصاصی'], $duration3After->features);

        // مدت ۱ ماهه دست‌نخورده → خالی = ارث‌بری
        $duration1After = $plan->durations()->where('duration_months', 1)->first();
        $this->assertNull($duration1After->features);

        // باز کردن مودال → ردیف‌های قابلیت ذخیره‌شده برمی‌گردند
        Livewire::test(PlansIndex::class)
            ->call('openEdit', $plan->id)
            ->assertSet('formDurations.1.features', ['آپدیت رایگان', 'مشاوره اختصاصی']);
    }

    public function test_shop_card_shows_duration_config_of_selected_duration(): void
    {
        $plan = $this->makeMultiDurationPlan();

        $planPackage = $this->makePackage(['name' => 'پکیج ارثی فروشگاه']);
        $plan->packages()->attach($planPackage->id, ['free_months' => 1]);

        $vipPackage = $this->makePackage(['name' => 'پکیج ویژه فروشگاه']);
        $duration3  = $plan->durations()->where('duration_months', 3)->first();
        $duration3->update(['features' => ['قابلیت ویژه سه‌ماهه']]);
        $duration3->packages()->attach($vipPackage->id, ['free_months' => 3]);

        // حالت اولیه (بدون انتخاب مدت) → قابلیت‌ها و پکیج‌های خود طرح
        Livewire::test(ShopPlans::class)
            ->assertSee('پکیج ارثی فروشگاه')
            ->assertSee('پشتیبانی پایه')
            ->assertDontSee('پکیج ویژه فروشگاه')
            // انتخاب مدت ۳ ماهه → پیکربندی همان مدت
            ->call('selectDuration', $plan->id, 3)
            ->assertSee('پکیج ویژه فروشگاه')
            ->assertSee('۳ ماه رایگان')
            ->assertSee('قابلیت ویژه سه‌ماهه')
            ->assertDontSee('پکیج ارثی فروشگاه');
    }

    /** صفحهٔ واقعی فروشگاه (/subscriptions) — کارت با مدت نمایشی (اولین گزینه به‌صورت پیش‌فرض) */
    public function test_shop_subscriptions_page_shows_per_duration_config(): void
    {
        $plan = $this->makeMultiDurationPlan();

        $planPackage = $this->makePackage(['name' => 'پکیج ارثی صفحه فروشگاه']);
        $plan->packages()->attach($planPackage->id, ['free_months' => 1]);

        $vipPackage = $this->makePackage(['name' => 'پکیج ویژه سه‌ماهه']);
        $duration3  = $plan->durations()->where('duration_months', 3)->first();
        $duration3->update(['features' => ['قابلیت اختصاصی سه‌ماهه']]);
        $duration3->packages()->attach($vipPackage->id, ['free_months' => 3]);

        // پیش‌فرض: مدت نمایشی = اولین گزینه (۱ ماهه، بدون پیکربندی) → قابلیت‌ها/پکیج‌های طرح
        Livewire::test(SubscriptionsIndex::class)
            ->assertSee('پکیج ارثی صفحه فروشگاه')
            ->assertSee('۱ ماه رایگان')
            ->assertSee('پشتیبانی پایه')
            ->assertDontSee('پکیج ویژه سه‌ماهه')
            // انتخاب مدت ۳ ماهه در فرم خرید → پیکربندی همان مدت روی کارت
            ->call('selectDuration', $plan->id, 3)
            ->assertSee('پکیج ویژه سه‌ماهه')
            ->assertSee('۳ ماه رایگان')
            ->assertSee('قابلیت اختصاصی سه‌ماهه')
            ->assertDontSee('پکیج ارثی صفحه فروشگاه')
            ->assertDontSee('پشتیبانی پایه');
    }
}
