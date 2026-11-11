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

        // InstaShop اول است (خواستهٔ کاربر — Task 74)
        $this->assertLessThan(
            mb_strpos($response->getContent(), 'پکیج ویترین'),
            mb_strpos($response->getContent(), 'insta-shop.webp')
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

        // اولین اسلاید پیش‌فرض = InstaShop (خواستهٔ کاربر — Task 74)
        $this->assertSame('https://insta-shop.opashop.ir', $slides[0]['link']);
        $this->assertGreaterThan(1, count($slides));

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

    /* -------------------------------------------------------------- */
    /*  شخصی‌سازی کامل اسلایدها و اسلایدر (Task 73) */
    /* -------------------------------------------------------------- */

    public function test_slide_customization_persists_and_flows_to_homepage(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(SettingsHomePage::class)
            ->call('newSlide')
            ->set('slide.title', 'اسلاید شخصی‌سازی‌شده')
            ->set('slide.link', 'https://example.com/external')
            ->set('slide.link_text', 'برو بیرون')
            ->set('slide.content_align', 'center')
            ->set('slide.content_vpos', 'bottom')
            ->set('slide.button_style', 'soft')
            ->set('slide.overlay', 55)
            ->set('slide.new_tab', true)
            ->set('slide.sort_order', 1)
            ->call('saveSlide')
            ->assertHasNoErrors();

        $slide = HomeSlide::query()->where('title', 'اسلاید شخصی‌سازی‌شده')->firstOrFail();

        $this->assertSame('center', $slide->content_align);
        $this->assertSame('bottom', $slide->content_vpos);
        $this->assertSame('soft', $slide->button_style);
        $this->assertSame(55, $slide->overlay);
        $this->assertTrue($slide->new_tab);

        // صفحهٔ اصلی: جایگاه محتوا، استایل دکمهٔ ملایم، overlay عددی و تب جدید
        $response = $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee('اسلاید شخصی‌سازی‌شده');

        $html = $response->getContent();

        $this->assertStringContainsString('justify-end', $html);          // vpos=bottom
        $this->assertStringContainsString('text-center', $html);         // align=center
        $this->assertStringContainsString('mx-auto', $html);             // align=center → بلوک وسط
        $this->assertStringContainsString('rgba(9, 9, 11, 0.55)', $html); // overlay=55 inline
        $this->assertStringContainsString('bg-white/15', $html);         // button_style=soft
        $this->assertStringContainsString('target="_blank"', $html);     // new_tab=true

        // خروجی یکسان‌شدهٔ Showcase ساختار کامل دارد
        $shaped = Showcase::slides()[0];
        $this->assertSame('center', $shaped['content_align']);
        $this->assertSame('bottom', $shaped['content_vpos']);
        $this->assertSame('soft', $shaped['button_style']);
        $this->assertSame(55, $shaped['overlay']);
        $this->assertTrue($shaped['new_tab']);
    }

    public function test_slide_customization_validations(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(SettingsHomePage::class)
            ->call('newSlide')
            ->set('slide.title', 'اعتبارسنجی')
            ->set('slide.content_align', 'diagonal')
            ->set('slide.content_vpos', 'sideways')
            ->set('slide.button_style', 'neon')
            ->set('slide.overlay', 120)
            ->call('saveSlide')
            ->assertHasErrors(['slide.content_align', 'slide.content_vpos', 'slide.button_style', 'slide.overlay']);
    }

    public function test_slider_behavior_settings_are_customizable(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(SettingsHomePage::class)
            ->set('form.home_showcase_eyebrow', 'برچسب اسلایدر')
            ->set('form.home_showcase_heading', 'عنوان اختصاصی اسلایدر')
            ->set('form.home_showcase_autoplay', false)
            ->set('form.home_showcase_autoplay_speed', 9)
            ->set('form.home_showcase_dots', false)
            ->set('form.home_showcase_arrows', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee('عنوان اختصاصی اسلایدر')
            ->assertSee('برچسب اسلایدر')
            // پخش خودکار خاموش → پارامتر Alpine
            ->assertSee('showcaseSlider(')
            // دات‌ها خاموش → تب ناوبری رندر نمی‌شود
            ->assertDontSee('role="tablist"');

        $html = $this->get(route('shop.home'))->getContent();

        // autoplay=false → false به Alpine پاس می‌شود؛ سرعت ۹ ثانیه → ۹۰۰۰
        $this->assertStringContainsString('false, 9000', $html);
        // فلش‌های ناوبری رندر نشده‌اند
        $this->assertStringNotContainsString('aria-label="اسلاید قبلی"', $html);
    }

    public function test_slider_behavior_validates_autoplay_speed_range(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(SettingsHomePage::class)
            ->set('form.home_showcase_autoplay_speed', 2)
            ->call('save')
            ->assertHasErrors(['form.home_showcase_autoplay_speed']);

        Livewire::test(SettingsHomePage::class)
            ->set('form.home_showcase_autoplay_speed', 16)
            ->call('save')
            ->assertHasErrors(['form.home_showcase_autoplay_speed']);
    }

    public function test_default_slides_have_auto_customization_shape(): void
    {
        $slides = Showcase::slides();

        $this->assertNotEmpty($slides);

        foreach ($slides as $slide) {
            $this->assertSame('start', $slide['content_align']);
            $this->assertSame('center', $slide['content_vpos']);
            $this->assertSame('solid', $slide['button_style']);
            $this->assertNull($slide['overlay']);
            $this->assertFalse($slide['new_tab']);
        }
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
            ->assertSee('اسلایدهای خودکار از پکیج/محصول')
            ->assertSee('انتخاب پکیج‌ها و محصولات اسلایدهای خودکار');
    }

    public function test_guest_cannot_manage_slides(): void
    {
        // صفحهٔ پنل اصلاً باز نمی‌شود (گوارش توسط route/middleware)
        $this->get(route('admin.settings.home'))->assertRedirect();
    }

    /* -------------------------------------------------------------- */
    /*  Task 74 — اینستاگرام اول + انتخاب پکیج/محصول */
    /* -------------------------------------------------------------- */

    public function test_instashop_banner_is_first_by_default(): void
    {
        $this->makePackage(['name' => 'پکیج قدیمی‌تر']);
        $this->makePackage(['name' => 'پکیج تازه‌تر']);

        $slides = Showcase::slides();

        $this->assertSame('https://insta-shop.opashop.ir', $slides[0]['link']);
        $this->assertSame('پکیج تازه‌تر', $slides[1]['title']);
        $this->assertSame('پکیج قدیمی‌تر', $slides[2]['title']);

        $html = $this->get(route('shop.home'))->getContent();
        $this->assertLessThan(
            mb_strpos($html, 'پکیج قدیمی‌تر'),
            mb_strpos($html, 'insta-shop.webp')
        );
    }

    public function test_instashop_position_can_be_moved_last_via_panel(): void
    {
        $this->actingAs($this->admin);
        $this->makePackage(['name' => 'پکیج جایگاه آخر']);

        Livewire::test(SettingsHomePage::class)
            ->assertSet('form.home_showcase_instashop_position', 'first') // پیش‌فرض = اول
            ->set('form.home_showcase_instashop_position', 'last')
            ->call('save')
            ->assertHasNoErrors();

        $slides = Showcase::slides();
        $this->assertNotSame('https://insta-shop.opashop.ir', $slides[0]['link']);
        $this->assertSame('https://insta-shop.opashop.ir', end($slides)['link']);

        $html = $this->get(route('shop.home'))->getContent();
        $this->assertGreaterThan(
            mb_strpos($html, 'پکیج جایگاه آخر'),
            mb_strpos($html, 'insta-shop.webp')
        );
    }

    public function test_instashop_position_validates(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(SettingsHomePage::class)
            ->set('form.home_showcase_instashop_position', 'middle')
            ->call('save')
            ->assertHasErrors(['form.home_showcase_instashop_position']);
    }

    public function test_auto_slides_picker_saves_selection_and_order(): void
    {
        $this->actingAs($this->admin);

        $package = $this->makePackage(['name' => 'پکیج انتخابی اسلایدر']);
        $other = $this->makePackage(['name' => 'پکیج انتخاب‌نشده']);
        $product = $this->makeProduct(['name' => 'محصول انتخابی اسلایدر']);

        $component = Livewire::test(SettingsHomePage::class)
            // پکیج
            ->set('pickerType', 'package')
            ->set('pickerId', $package->id)
            ->call('addAutoSlide')
            ->assertHasNoErrors()
            // جلوگیری از تکرار
            ->set('pickerId', $package->id)
            ->call('addAutoSlide')
            ->assertHasErrors(['pickerId'])
            // تلاش مجدد — خطای قبلی نباید بماند
            ->set('pickerType', 'product')
            ->set('pickerId', $product->id)
            ->call('addAutoSlide')
            ->assertHasNoErrors();

        // ترتیب فرم: پکیج، محصول — محصول یک ردیف بالا می‌رود
        $component->call('moveAutoSlide', 1, 'up')->assertHasNoErrors();

        $component->call('save')->assertHasNoErrors();

        $this->assertSame(
            [['type' => 'product', 'id' => $product->id], ['type' => 'package', 'id' => $package->id]],
            Showcase::storedRefs()
        );

        // صفحهٔ اصلی: ترتیب در HTML (اینستا ← محصول ← پکیج)
        $html = $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee('محصول انتخابی اسلایدر')
            ->assertSee('پکیج انتخابی اسلایدر')
            ->getContent();

        $this->assertLessThan(
            mb_strpos($html, 'پکیج انتخابی اسلایدر'),
            mb_strpos($html, 'محصول انتخابی اسلایدر')
        );
        $this->assertLessThan(
            mb_strpos($html, 'محصول انتخابی اسلایدر'),
            mb_strpos($html, 'insta-shop.webp')
        );

        // اسلایدر دقیقاً فقط انتخاب‌شده‌ها را دارد — پکیجِ انتخاب‌نشده اسلاید نیست
        $this->assertSame(
            ['پیج اینستاگرامت را به یک فروشگاه آنلاین تبدیل کن', 'محصول انتخابی اسلایدر', 'پکیج انتخابی اسلایدر'],
            array_column(Showcase::slides(), 'title')
        );
    }

    public function test_auto_slide_can_be_removed_from_panel(): void
    {
        $this->actingAs($this->admin);
        $package = $this->makePackage(['name' => 'پکیج حذف‌شدنی']);

        Livewire::test(SettingsHomePage::class)
            ->set('pickerType', 'package')
            ->set('pickerId', $package->id)
            ->call('addAutoSlide')
            ->assertSee('پکیج حذف‌شدنی')
            ->call('removeAutoSlide', 0)
            // ردیف از لیست حذف شده (نامش فقط در گزینه‌های پیکر برمی‌گردد که طبیعی است)
            ->assertDontSee('auto-slide-row-package-'.$package->id, false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([], Showcase::storedRefs());
    }

    public function test_auto_slides_invalid_refs_fall_back_to_defaults(): void
    {
        $this->makePackage(['name' => 'پکیج فال‌بک اسلایدر']);

        // JSON نامعتبر و ردیف‌های ناآشنا بی‌صدا نادیده گرفته می‌شوند
        $this->setSetting('home_showcase_auto_slides', '{{{bad json');
        $this->assertSame([], Showcase::storedRefs());

        $this->setSetting('home_showcase_auto_slides', '[{"type":"category","id":5},{"type":"package","id":-3}]');
        $this->assertSame([], Showcase::storedRefs());

        // آیتمِ شکل-معتبر ولی ناموجود → فال‌بک نمی‌شود؛ فقط اینستاگرام می‌ماند
        $this->setSetting('home_showcase_auto_slides', '[{"type":"package","id":999999}]');
        $slides = Showcase::slides();
        $this->assertCount(1, $slides);
        $this->assertSame('https://insta-shop.opashop.ir', $slides[0]['link']);

        // فال‌بک: ۶ پکیج فعالِ آخر + اینستاگرام اول
        $this->setSetting('home_showcase_auto_slides', '');
        $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee('پکیج فال‌بک اسلایدر')
            ->assertSee('insta-shop.webp');
    }

    public function test_inactive_selected_package_is_skipped_in_auto_slides(): void
    {
        $active = $this->makePackage(['name' => 'پکیج فعالِ انتخاب']);
        $draft = $this->makePackage(['name' => 'پکیج پیش‌نویسِ انتخاب', 'status' => Package::STATUS_DRAFT]);

        $this->setSetting('home_showcase_auto_slides', Showcase::encodeRefs([
            ['type' => 'package', 'id' => $draft->id],
            ['type' => 'package', 'id' => $active->id],
        ]));

        $slides = Showcase::slides();

        $this->assertSame('https://insta-shop.opashop.ir', $slides[0]['link']); // اینستا اول
        $this->assertSame('پکیج فعالِ انتخاب', $slides[1]['title']);
        $this->assertCount(2, $slides); // پیش‌نویس نادیده گرفته شد
    }
}
