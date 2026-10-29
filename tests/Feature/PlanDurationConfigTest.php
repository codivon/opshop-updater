<?php

namespace Tests\Feature;

use App\Livewire\Plans\Form as PlansForm;
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
 *
 * Task 64: فرم پنل به کامپوننت مجزای Plans\Form منتقل شده (رفع کندی مودال) —
 * تست‌های پنل از طریق رویداد plan-form-open (همان مسیر واقعی دکمهٔ جدول) باز می‌شوند.
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

        Livewire::test(PlansForm::class)
            ->dispatch('plan-form-open', id: $plan->id)
            ->assertSet('showModal', true)
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
        Livewire::test(PlansForm::class)
            ->call('openEdit', $plan->id)
            ->assertSet('formDurations.1.features', ['پشتیبانی ۳ ماهه VIP', 'آپدیت رایگان'])
            ->assertSet('formDurations.1.packages.0.package_id', (string) $pkgB->id)
            ->assertSet('formDurations.1.packages.0.free_months', '3');
    }

    public function test_admin_panel_add_and_remove_duration_package_rows(): void
    {
        $plan = $this->makeMultiDurationPlan();
        $pkgA = $this->makePackage(['name' => 'پکیج الف']);

        Livewire::test(PlansForm::class)
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

        Livewire::test(PlansForm::class)
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
        Livewire::test(PlansForm::class)
            ->call('openEdit', $plan->id)
            ->assertSet('formDurations.1.features', ['آپدیت رایگان', 'مشاوره اختصاصی']);
    }

    /** مودال «جزئیات مدت» فروشگاه (Task 64) — دادهٔ هر مدت با ارث‌بری درست حل می‌شود */
    public function test_shop_duration_details_modal_data_resolves_per_duration(): void
    {
        $plan = $this->makeMultiDurationPlan();

        $planPackage = $this->makePackage(['name' => 'پکیج ارثی مودال']);
        $plan->packages()->attach($planPackage->id, ['free_months' => 1]);

        $vipPackage = $this->makePackage(['name' => 'پکیج ویژه مودال']);
        $duration3  = $plan->durations()->where('duration_months', 3)->first();
        $duration3->update(['features' => ['قابلیت ویژه مودال']]);
        $duration3->packages()->attach($vipPackage->id, ['free_months' => 3]);

        $details = Livewire::test(SubscriptionsIndex::class)
            ->instance()
            ->durationDetailsFor($plan);

        $this->assertCount(2, $details);

        // مدت ۱ ماهه — بدون پیکربندی → ارث‌بری از طرح + نشان غیرسفارشی
        $this->assertFalse($details[0]['custom']);
        $this->assertSame(['پشتیبانی پایه'], $details[0]['features']);
        $this->assertSame('پکیج ارثی مودال', $details[0]['packages'][0]['name']);
        $this->assertSame('۱ ماه رایگان', $details[0]['packages'][0]['free']);

        // مدت ۳ ماهه — پیکربندی اختصاصی → قابلیت‌ها/پکیج‌های خودش
        $this->assertTrue($details[1]['custom']);
        $this->assertSame(['قابلیت ویژه مودال'], $details[1]['features']);
        $this->assertSame('پکیج ویژه مودال', $details[1]['packages'][0]['name']);
        $this->assertSame('۳ ماه رایگان', $details[1]['packages'][0]['free']);

        // طرح تک‌مدتی (بدون ردیف مدت) → جزئیاتی ندارد (مودال نمایش داده نمی‌شود)
        $single = SubscriptionPlan::create([
            'name' => 'تک‌مدتی', 'slug' => 'single-' . uniqid(),
            'duration_months' => 2, 'price' => 10000, 'is_active' => true,
        ]);
        $this->assertSame([], Livewire::test(SubscriptionsIndex::class)->instance()->durationDetailsFor($single));
    }

    /** جزئیات مودال در HTML صفحه فروشگاه به‌صورت base64 تزریق می‌شود (بدون متن لاتین/فارسی خام در DOM) */
    public function test_shop_page_embeds_duration_details_data_island(): void
    {
        $plan = $this->makeMultiDurationPlan();

        $content = Livewire::test(SubscriptionsIndex::class)->html();

        // آیلند دادهٔ مودال باید موجود باشد
        $this->assertMatchesRegularExpression('/durs: JSON\.parse\(atob\(/', $content);
        // متن پیکربندی نباید خام در DOM باشد (مودال سمت مرورگر از آیلند می‌خواند)
        $this->assertStringNotContainsString('پشتیبانی پایه\u0022', $content);
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

    /**
     * Task 65 — کارت فروشگاه: به‌جای لیست‌های بلند، نوار فشردهٔ «دارای قابلیت‌ها و پکیج‌های رایگان»؛
     * نام قابلیت‌ها/پکیج‌ها دیگر در بدنهٔ کارت نیست (داخل آیلند base64 مودال است).
     */
    public function test_shop_subscriptions_page_shows_compact_details_banner(): void
    {
        $plan = $this->makeMultiDurationPlan();

        $planPackage = $this->makePackage(['name' => 'پکیج ارثی صفحه فروشگاه']);
        $plan->packages()->attach($planPackage->id, ['free_months' => 1]);

        $vipPackage = $this->makePackage(['name' => 'پکیج ویژه سه‌ماهه']);
        $duration3  = $plan->durations()->where('duration_months', 3)->first();
        $duration3->update(['features' => ['قابلیت اختصاصی سه‌ماهه']]);
        $duration3->packages()->attach($vipPackage->id, ['free_months' => 3]);

        $content = Livewire::test(SubscriptionsIndex::class)
            ->assertSee('این طرح قابلیت‌ها و پکیج‌های رایگان دارد')
            ->assertSee('برای مشاهده کلیک کنید')
            // نام‌ها دیگر در DOM نیستند — فقط شمارنده‌ها
            ->assertDontSee('پکیج ارثی صفحه فروشگاه')
            ->assertDontSee('پکیج ویژه سه‌ماهه')
            ->assertDontSee('پشتیبانی پایه')
            ->html();

        // آیلند مودال برای طرح چندمدتی و تک‌مدتی (Task 65) تزریق می‌شود
        $this->assertMatchesRegularExpression('/durs: JSON\.parse\(atob\(/', $content);
    }

    /** Task 65 — طرح تک‌مدتی (بدون ردیف مدت) هم مودال جزئیات دارد: cardDetailsFor یک ردیف می‌سازد */
    public function test_shop_single_duration_plan_gets_details_entry(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'تک‌مدتی ویژه', 'slug' => 'single-' . uniqid(),
            'duration_months' => 2, 'price' => 100000, 'discount_price' => 20000,
            'is_active' => true, 'features' => ['پشتیبانی دوماهه'],
        ]);
        $pkg = $this->makePackage(['name' => 'پکیج تک‌مدتی']);
        $plan->packages()->attach($pkg->id, ['free_months' => 2]);

        $component = Livewire::test(SubscriptionsIndex::class);

        $details = $component->instance()->cardDetailsFor($plan);

        $this->assertCount(1, $details);
        $this->assertSame(['پشتیبانی دوماهه'], $details[0]['features']);
        $this->assertSame('پکیج تک‌مدتی', $details[0]['packages'][0]['name']);
        $this->assertSame('۲ ماه رایگان', $details[0]['packages'][0]['free']);
        $this->assertFalse($details[0]['custom']);
        $this->assertSame(20, $details[0]['percent']);

        // نوار فشرده + مودال برای تک‌مدتی هم در DOM رندر می‌شود
        $component->assertSee('این طرح قابلیت‌ها و پکیج‌های رایگان دارد');
    }

    /** Task 65 — کپی طرح از جدول پنل: همهٔ مدت‌ها + قابلیت‌ها + پکیج‌های هر مدت */
    public function test_admin_panel_copies_plan_with_durations_and_packages(): void
    {
        $plan = $this->makeMultiDurationPlan();
        $plan->update(['description' => 'توضیحات طرح اصلی', 'price' => 490000, 'discount_price' => 50000]);

        $planPackage = $this->makePackage(['name' => 'پکیج سطح طرح']);
        $plan->packages()->attach($planPackage->id, ['free_months' => 1]);

        $vipPackage = $this->makePackage(['name' => 'پکیج ویژه']);
        $duration3  = $plan->durations()->where('duration_months', 3)->first();
        $duration3->update(['features' => ['قابلیت ویژه سه‌ماهه'], 'price' => 1200000]);
        $duration3->packages()->attach($vipPackage->id, ['free_months' => 3]);

        Livewire::test(PlansIndex::class)
            ->call('copy', $plan->id)
            ->assertHasNoErrors();

        $this->assertSame(2, SubscriptionPlan::count());
        $copy = SubscriptionPlan::query()->whereKeyNot($plan->id)->sole();

        // نام/شناسه/وضعیت — کپی غیرفعال ساخته می‌شود تا بی‌اجازه منتشر نشود
        $this->assertSame($plan->name . ' (کپی)', $copy->name);
        $this->assertNotSame($plan->slug, $copy->slug);
        $this->assertFalse($copy->is_active);
        $this->assertSame('توضیحات طرح اصلی', $copy->description);
        $this->assertSame(490000, $copy->price);
        $this->assertSame(50000, $copy->discount_price);
        $this->assertSame(['پشتیبانی پایه'], $copy->features);

        // پکیج سطح طرح با همان مدت رایگان
        $this->assertDatabaseHas('subscription_plan_package', [
            'subscription_plan_id' => $copy->id,
            'package_id'           => $planPackage->id,
            'free_months'          => 1,
        ]);

        // مدت‌ها با قیمت/قابلیت‌هایشان
        $this->assertSame(2, $copy->durations()->count());
        $copyDuration3 = $copy->durations()->where('duration_months', 3)->first();
        $this->assertSame(1200000, (int) $copyDuration3->price);
        $this->assertSame(['قابلیت ویژه سه‌ماهه'], $copyDuration3->features);

        // پکیج اختصاصی مدت ۳ ماهه کپی‌شده
        $this->assertDatabaseHas('subscription_plan_duration_package', [
            'subscription_plan_duration_id' => $copyDuration3->id,
            'package_id'                    => $vipPackage->id,
            'free_months'                   => 3,
        ]);

        // مدت ۱ ماهه بدون پیکربندی اختصاصی
        $copyDuration1 = $copy->durations()->where('duration_months', 1)->first();
        $this->assertNull($copyDuration1->features);
        $this->assertDatabaseMissing('subscription_plan_duration_package', [
            'subscription_plan_duration_id' => $copyDuration1->id,
        ]);

        // کپی دوم → شناسهٔ یکتا (تداخل slug حل می‌شود)
        Livewire::test(PlansIndex::class)->call('copy', $plan->id);
        $this->assertSame(3, SubscriptionPlan::count());
        // هر دو کپی با شناسه‌های متمایز (سوییچ تصادفی خودکار در تداخل)
        $this->assertSame(2, SubscriptionPlan::where('slug', 'like', $plan->slug . '-copy%')->count());
        $this->assertSame(3, SubscriptionPlan::query()->distinct('slug')->count('slug'));
    }
}
