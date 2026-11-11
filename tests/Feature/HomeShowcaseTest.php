<?php

namespace Tests\Feature;

use App\Livewire\Settings\HomePage as SettingsHomePage;
use App\Models\HomeSlide;
use App\Models\Package;
use App\Models\Setting;
use App\Models\User;
use App\Support\Showcase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

/**
 * اسلایدر معرفی صفحهٔ اصلی (Task 71):
 *
 *  - پیش‌فرض اول: بنر خودکار از پکیج‌های فعال (تصویر/عنوان/متن/لینک + نشان نام پروژه)
 *  - پیش‌فرض دوم: بنر ویژهٔ InstaShop (تصویر کامیت‌شده + متن/لینک از تنظیمات)
 *  - قاعده: اسلاید دستیِ فعال ← فقط دستی‌ها؛ وگرنه پیش‌فرض‌ها
 *  - گیت بخش: home_showcase_enabled + ترتیب home_sort_showcase
 *  - پنل: CRUD لحظه‌ای اسلایدها در تب «اسلایدر معرفی»
 */
class HomeShowcaseTest extends TestCase
{
    use MakesShopData;
    use RefreshDatabase;

    private User $admin;

    /** ذخیرهٔ مستقیم تنظیم گروه home + پاک‌سازی کش */
    private function setSetting(string $key, string $value): void
    {
        Setting::set($key, $value, 'string', 'home');
        Cache::forget('setting:'.$key);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'مدیر آزمون',
            'email' => 'admin@showcase.test',
            'password' => 'secret123',
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    /* -------------------------------------------------------------- */
    /*  پیش‌فرض‌ها */
    /* -------------------------------------------------------------- */

    public function test_instashop_banner_shows_by_default_even_without_packages(): void
    {
        $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee('ویترین محصولات')
            ->assertSee('images/showcase/insta-shop.webp')
            ->assertSee('پیج اینستاگرامت را به یک فروشگاه آنلاین تبدیل کن')
            ->assertSee('https://insta-shop.opashop.ir');
    }

    public function test_package_banner_is_auto_generated_with_project_badge_and_link(): void
    {
        $package = $this->makePackage([
            'name' => 'پکیج ویترین',
            'short_description' => 'توضیح اسلاید پکیج تستی',
        ]);
        $package->refresh();

        $response = $this->get(route('shop.home'));

        $response->assertOk()
            ->assertSee('پکیج ویترین')
            ->assertSee($package->project->name)          // نشان = نام پروژه
            ->assertSee('packages/'.$package->slug, false) // دکمهٔ مشاهده و خرید
            ->assertSee('مشاهده و خرید');

        // InstaShop هم بعد از بنر پکیج‌ها می‌آید
        $this->assertLessThan(
            mb_strpos($response->getContent(), 'insta-shop.webp'),
            mb_strpos($response->getContent(), 'پکیج ویترین')
        );
    }

    public function test_only_active_packages_become_banners(): void
    {
        $this->makePackage(['name' => 'پکیج فعال اسلایدر', 'status' => Package::STATUS_ACTIVE]);
        $this->makePackage(['name' => 'پکیج پیش‌نویس اسلایدر', 'status' => Package::STATUS_DRAFT]);

        $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee('پکیج فعال اسلایدر')
            ->assertDontSee('پکیج پیش‌نویس اسلایدر');
    }

    public function test_showcase_support_can_be_disabled_and_instashop_hidden(): void
    {
        $this->setSetting('home_showcase_packages_enabled', '0');
        $this->setSetting('home_showcase_instashop_enabled', '0');

        // هر دو پیش‌فرض خاموش → هیچ اسلایدی نیست → بخش رندر نمی‌شود
        $this->get(route('shop.home'))
            ->assertOk()
            ->assertDontSee('ویترین محصولات')
            ->assertDontSee('insta-shop.webp');
    }

    /* -------------------------------------------------------------- */
    /*  قاعدهٔ اولویت اسلاید دستی */
    /* -------------------------------------------------------------- */

    public function test_active_manual_slides_take_priority_over_defaults(): void
    {
        $this->makePackage(['name' => 'پکیج پنهان در حالت دستی']);

        HomeSlide::query()->create([
            'title' => 'اسلاید دستی ویژه',
            'text' => 'متن اسلاید دستی',
            'badge' => 'نشان آزمون',
            'link' => '/shop/packages',
            'link_text' => 'دیدن همه',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // اسلاید دستی → پیش‌فرض‌ها فقط «داخل بخش اسلایدر» حذف می‌شوند
        // (نام پکیج ممکن است به‌طور قانونی در بخش «پکیج‌های فروشگاه» باشد)
        $html = $this->get(route('shop.home'))->assertOk()->getContent();
        $start = mb_strpos($html, 'id="showcase"');
        $end = mb_strpos($html, '</section>', $start);
        $this->assertNotFalse($start, 'بخش showcase رندر نشده است');

        $showcase = mb_substr($html, $start, (int) $end - $start);

        $this->assertStringContainsString('اسلاید دستی ویژه', $showcase);
        $this->assertStringContainsString('نشان آزمون', $showcase);
        $this->assertStringContainsString('دیدن همه', $showcase);
        $this->assertStringNotContainsString('پکیج پنهان در حالت دستی', $showcase);
        $this->assertStringNotContainsString('insta-shop.webp', $showcase);
    }

    public function test_inactive_manual_slides_fall_back_to_defaults(): void
    {
        HomeSlide::query()->create([
            'title' => 'اسلاید غیرفعال',
            'is_active' => false,
        ]);

        $this->get(route('shop.home'))
            ->assertOk()
            ->assertDontSee('اسلاید غیرفعال')
            ->assertSee('insta-shop.webp');
    }

    /* -------------------------------------------------------------- */
    /*  گیت بخش و ترتیب */
    /* -------------------------------------------------------------- */

    public function test_showcase_section_can_be_disabled(): void
    {
        $this->setSetting('home_showcase_enabled', '0');

        $this->get(route('shop.home'))
            ->assertOk()
            ->assertDontSee('ویترین محصولات');
    }

    public function test_showcase_section_can_be_reordered_above_hero(): void
    {
        $this->setSetting('home_sort_showcase', '5'); // بالای هیرو (10)

        $html = $this->get(route('shop.home'))->assertOk()->getContent();

        $this->assertLessThan(
            mb_strpos($html, 'از اول تا بزرگ شدن استارتاپ'), // هیرو
            mb_strpos($html, 'ویترین محصولات')               // اسلایدر
        );
    }

    /* -------------------------------------------------------------- */
    /*  رندرکنندهٔ اسلایدها */
    /* -------------------------------------------------------------- */

    public function test_showcase_resolves_unified_slide_shape(): void
    {
        $package = $this->makePackage(['name' => 'پکیج شکل یکسان']);
        $slides = Showcase::slides();

        $this->assertNotEmpty($slides);

        foreach ($slides as $slide) {
            $this->assertArrayHasKey('badge', $slide);
            $this->assertArrayHasKey('title', $slide);
            $this->assertArrayHasKey('text', $slide);
            $this->assertArrayHasKey('image', $slide);
            $this->assertArrayHasKey('link', $slide);
            $this->assertArrayHasKey('link_text', $slide);
        }

        // آخرین اسلاید پیش‌فرض = InstaShop
        $this->assertSame('https://insta-shop.opashop.ir', end($slides)['link']);

        // اسلاید پکیج به صفحهٔ خودش لینک است
        $packageSlide = collect($slides)->first(fn ($s) => str_contains($s['link'], '/packages/'.$package->slug));
        $this->assertNotNull($packageSlide);
        $this->assertSame($package->project->name, $packageSlide['badge']);
    }

    /* -------------------------------------------------------------- */
    /*  پنل — CRUD لحظه‌ای اسلایدها */
    /* -------------------------------------------------------------- */

    public function test_panel_can_create_edit_toggle_move_and_delete_slides(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(SettingsHomePage::class)
            // افزودن
            ->call('newSlide')
            ->set('slide.badge', 'نشان پنل')
            ->set('slide.title', 'اسلاید از پنل')
            ->set('slide.text', 'متن از پنل')
            ->set('slide.link', '/shop/packages')
            ->set('slide.link_text', 'مشاهده')
            ->call('saveSlide')
            ->assertHasNoErrors()
            ->assertNotSet('showSlideForm', true);

        $slide = HomeSlide::query()->where('title', 'اسلاید از پنل')->first();
        $this->assertNotNull($slide);
        $this->assertSame('نشان پنل', $slide->badge);
        $this->assertTrue($slide->is_active);
        $this->assertSame(1, $slide->sort_order);

        // اسلاید دستی → پیش‌فرض‌ها از صفحهٔ اصلی حذف می‌شوند
        $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee('اسلاید از پنل')
            ->assertDontSee('insta-shop.webp');

        // ویرایش
        Livewire::test(SettingsHomePage::class)
            ->call('editSlide', $slide->id)
            ->assertSet('editingSlideId', $slide->id)
            ->set('slide.title', 'اسلاید ویرایش‌شده')
            ->call('saveSlide')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('home_slides', ['id' => $slide->id, 'title' => 'اسلاید ویرایش‌شده']);

        // غیرفعال → پیش‌فرض‌ها برمی‌گردند
        Livewire::test(SettingsHomePage::class)->call('toggleSlide', $slide->id);
        $this->assertFalse($slide->fresh()->is_active);

        $this->get(route('shop.home'))
            ->assertOk()
            ->assertDontSee('اسلاید ویرایش‌شده')
            ->assertSee('insta-shop.webp');

        // افزودن اسلاید دوم + جابه‌جایی
        $second = HomeSlide::query()->create([
            'title' => 'اسلاید دوم', 'is_active' => true, 'sort_order' => 2,
        ]);

        Livewire::test(SettingsHomePage::class)
            ->call('moveSlide', $second->id, 'up')
            ->assertHasNoErrors();

        $this->assertSame(1, $second->fresh()->sort_order);
        $this->assertSame(2, $slide->fresh()->sort_order);

        // حذف
        Livewire::test(SettingsHomePage::class)
            ->call('deleteSlide', $second->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('home_slides', ['id' => $second->id]);
    }

    public function test_panel_validates_slide_title(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(SettingsHomePage::class)
            ->call('newSlide')
            ->set('slide.title', '')
            ->call('saveSlide')
            ->assertHasErrors(['slide.title']);

        $this->assertSame(0, HomeSlide::query()->count());
    }

    public function test_panel_showcase_tab_renders_for_admin(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.settings.home'))
            ->assertOk()
            ->assertSee('اسلایدر معرفی')
            ->assertSee('افزودن اسلاید جدید')
            ->assertSee('بنر خودکار از پکیج‌های فعال');
    }

    public function test_guest_cannot_manage_slides(): void
    {
        // صفحهٔ پنل اصلاً باز نمی‌شود (گوارش توسط route/middleware)
        $this->get(route('admin.settings.home'))->assertRedirect();
    }
}
