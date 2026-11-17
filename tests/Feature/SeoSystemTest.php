<?php

namespace Tests\Feature;

use App\Livewire\Settings\SeoSettings;
use App\Models\Setting;
use App\Models\Template;
use App\Models\User;
use App\Support\HomePage;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

/**
 * سیستم سئوی مرکزی (Task 82):
 * - متاهای سرورساید همهٔ صفحات فروشگاه (title/description/canonical/robots/OG)
 * - متای پویای صفحات جزئیات + فیلدهای meta_title/meta_description هر رکورد
 * - robots.txt پویا (خزنده‌های AI) + sitemap.xml + llms.txt
 * - پنل سئو (/admin/settings/seo)
 * - مجوزهای فوتر (تصویر/کد) و سازگاری با کد اینماد قدیمی
 */
class SeoSystemTest extends TestCase
{
    use MakesShopData;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'مدیر سئو',
            'email' => 'seo-admin@test.local',
            'password' => 'secret123',
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    /** ذخیرهٔ مستقیم یک تنظیم سئو + پاک‌سازی کش آن */
    private function setSeo(string $key, string $value): void
    {
        Setting::set($key, $value, 'string', 'seo');
        Cache::forget('setting:'.$key);
    }

    /* -------------------------------------------------------------- */
    /*  متاهای پایه */
    /* -------------------------------------------------------------- */

    public function test_shop_pages_render_meta_stack(): void
    {
        $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee('<meta name="description"', false)
            ->assertSee('<meta name="robots" content="index, follow', false)
            ->assertSee('<link rel="canonical"', false)
            ->assertSee('og:title', false)
            ->assertSee('twitter:card', false)
            ->assertSee('application/ld+json', false)
            ->assertSee('favicon.svg', false);
    }

    public function test_title_template_applies_to_list_pages(): void
    {
        $this->setSeo('seo_title_template', '%page% | %site%');
        $this->setSeo('site_name', 'فروشگاه آزمون');

        $this->get(route('shop.packages'))
            ->assertOk()
            ->assertSee('<title>پکیج‌ها | فروشگاه آزمون</title>', false);
    }

    public function test_per_page_title_override_is_used(): void
    {
        $this->setSeo('seo_page_products_title', 'خرید پروژه آماده با قیمت مناسب');

        $this->get(route('shop.products'))
            ->assertOk()
            ->assertSee('<title>خرید پروژه آماده با قیمت مناسب', false);
    }

    public function test_per_page_description_override_is_used(): void
    {
        $this->setSeo('seo_page_templates_desc', 'توضیح سئوی اختصاصی قالب‌های آزمون');

        $this->get(route('shop.templates'))
            ->assertOk()
            ->assertSee('<meta name="description" content="توضیح سئوی اختصاصی قالب‌های آزمون"', false);
    }

    public function test_canonical_host_rewrite(): void
    {
        $this->setSeo('seo_canonical_host', 'https://canonical.example');

        $this->get(route('shop.packages'))
            ->assertOk()
            ->assertSee('<link rel="canonical" href="https://canonical.example/shop/packages"', false);
    }

    public function test_owner_verification_metas_render(): void
    {
        $this->setSeo('seo_verif_google', 'gsc-token-123');

        $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee('<meta name="google-site-verification" content="gsc-token-123"', false);
    }

    /* -------------------------------------------------------------- */
    /*  متای پویا + فیلدهای اختصاصی رکورد */
    /* -------------------------------------------------------------- */

    public function test_package_detail_uses_name_and_software_schema(): void
    {
        $package = $this->makePackage([
            'name' => 'پکیج سئودار آزمون',
            'slug' => 'seo-pkg',
            'default_price' => 500000,
        ]);
        $this->makePackageVersion($package, ['version' => '2.1.0']);

        $this->get(route('shop.package', 'seo-pkg'))
            ->assertOk()
            ->assertSee('<title>پکیج سئودار آزمون', false)
            ->assertSee('"@type":"SoftwareApplication"', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSee('"@type":"Offer"', false)
            ->assertSee('"priceCurrency":"IRR"', false)
            ->assertSee('"softwareVersion":"2.1.0"', false);
    }

    public function test_package_meta_fields_take_precedence(): void
    {
        $this->makePackage([
            'name' => 'پکیج معمولی',
            'slug' => 'meta-pkg',
            'meta_title' => 'عنوان سفارشی سئو',
            'meta_description' => 'توضیح سفارشی سئو برای پکیج',
        ]);

        $this->get(route('shop.package', 'meta-pkg'))
            ->assertOk()
            ->assertSee('<title>عنوان سفارشی سئو', false)
            ->assertSee('<meta name="description" content="توضیح سفارشی سئو برای پکیج"', false);
    }

    public function test_product_meta_fields_take_precedence(): void
    {
        $this->makeProduct([
            'slug' => 'meta-prod',
            'meta_title' => 'عنوان محصول سئو',
            'meta_description' => 'توضیح محصول سئو',
        ]);

        $this->get(route('shop.product', 'meta-prod'))
            ->assertOk()
            ->assertSee('<title>عنوان محصول سئو', false)
            ->assertSee('<meta name="description" content="توضیح محصول سئو"', false);
    }

    public function test_template_meta_fields_take_precedence(): void
    {
        Template::create([
            'name' => 'قالب آزمون سئو',
            'slug' => 'meta-tpl',
            'price' => 0,
            'is_free' => true,
            'status' => Template::STATUS_ACTIVE,
            'meta_title' => 'عنوان قالب سئو',
            'meta_description' => 'توضیح قالب سئو',
        ]);

        $this->get(route('shop.template', 'meta-tpl'))
            ->assertOk()
            ->assertSee('<title>عنوان قالب سئو', false)
            ->assertSee('<meta name="description" content="توضیح قالب سئو"', false);
    }

    public function test_non_indexable_pages_are_noindex(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow"', false);

        $this->get(route('shop.login'))
            ->assertOk()
            ->assertSee('noindex', false);
    }

    /* -------------------------------------------------------------- */
    /*  robots.txt / sitemap.xml / llms.txt */
    /* -------------------------------------------------------------- */

    public function test_robots_txt_blocks_private_areas_and_lists_sitemap(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /admin', false)
            ->assertSee('Disallow: /payment', false)
            ->assertSee('Sitemap: ', false)
            ->assertSee('User-agent: GPTBot', false);
    }

    public function test_ai_bot_can_be_blocked_from_panel(): void
    {
        $this->setSeo('seo_ai_gptbot', '0');

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee("User-agent: GPTBot\nDisallow: /", false);
    }

    public function test_ai_bot_allowed_by_default(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee("User-agent: GPTBot\nDisallow:", false);
    }

    public function test_sitemap_contains_active_records(): void
    {
        $package = $this->makePackage(['slug' => 'map-pkg']);
        $this->makeProduct(['slug' => 'map-prod']);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('<loc>', false)
            ->assertSee('/shop/packages</loc>', false)
            ->assertSee('/packages/map-pkg</loc>', false)
            ->assertSee('/products/map-prod</loc>', false)
            ->assertSee('urlset', false);
    }

    public function test_sitemap_can_be_disabled(): void
    {
        $this->setSeo('seo_sitemap_enabled', '0');

        $this->get('/sitemap.xml')->assertNotFound();
    }

    public function test_llms_txt_contains_links(): void
    {
        $this->makePackage(['name' => 'پکیج ایل‌ال‌ام', 'slug' => 'llm-pkg']);

        $this->get('/llms.txt')
            ->assertOk()
            ->assertSee('## صفحات اصلی', false)
            ->assertSee('/shop/packages', false);

        $this->get('/llms-full.txt')
            ->assertOk()
            ->assertSee('/packages/llm-pkg', false)
            ->assertSee('پکیج ایل‌ال‌ام', false);
    }

    /* -------------------------------------------------------------- */
    /*  پنل سئو */
    /* -------------------------------------------------------------- */

    public function test_seo_settings_page_renders_for_admin(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.settings.seo'))
            ->assertOk()
            ->assertSee('قالب عنوان صفحات')
            ->assertSee('خزنده‌های هوش مصنوعی')
            ->assertSee('Google Search Console')
            ->assertSee('سایت‌مپ و llms.txt');
    }

    public function test_seo_settings_can_be_saved_from_panel(): void
    {
        Livewire::actingAs($this->admin)
            ->test(SeoSettings::class)
            ->set('form.seo_title_template', '%page% - %site%')
            ->set('form.seo_page_home_title', 'خانهٔ فروشگاه سئو')
            ->set('form.seo_ai_gptbot', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('settings', [
            'key' => 'seo_title_template',
            'value' => '%page% - %site%',
            'group' => 'seo',
        ]);
        $this->assertDatabaseHas('settings', [
            'key' => 'seo_ai_gptbot',
            'value' => '0',
        ]);

        // اثر فوری روی فرانت
        $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee('<title>خانهٔ فروشگاه سئو', false);

        $this->get('/robots.txt')->assertSee("User-agent: GPTBot\nDisallow: /", false);
    }

    public function test_seo_settings_requires_admin(): void
    {
        $this->get(route('admin.settings.seo'))->assertRedirect(route('login'));
    }

    /* -------------------------------------------------------------- */
    /*  مجوزهای فوتر */
    /* -------------------------------------------------------------- */

    public function test_footer_badges_render_image_and_code(): void
    {
        Setting::set('footer_badges', json_encode([
            ['type' => 'image', 'title' => 'نماد اعتماد', 'image' => 'uploads/footer-badges/test.png', 'link' => 'https://example.com/license', 'enabled' => true],
            ['type' => 'code', 'title' => 'اینماد', 'image' => '', 'code' => '<a href="https://trustseal.enamad.ir/?id=999">اینماد آزمون</a>', 'link' => '', 'enabled' => true],
            ['type' => 'image', 'title' => 'مخفی', 'image' => 'uploads/footer-badges/hidden.png', 'link' => '', 'enabled' => false],
        ], JSON_UNESCAPED_SLASHES), 'string', 'home');
        Cache::forget('setting:footer_badges');
        Cache::forget('settings');

        $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee('uploads/footer-badges/test.png', false)
            ->assertSee('https://example.com/license', false)
            ->assertSee('اینماد آزمون', false)
            ->assertDontSee('uploads/footer-badges/hidden.png', false);
    }

    public function test_legacy_enamad_code_still_renders_without_badges(): void
    {
        Setting::set('footer_enamad_code', '<a href="https://trustseal.enamad.ir/?id=777">اینماد قدیمی</a>', 'string', 'home');
        Cache::forget('setting:footer_enamad_code');
        Cache::forget('settings');

        $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee('اینماد قدیمی', false);
    }

    public function test_badge_image_url_supports_absolute_and_relative(): void
    {
        $this->assertSame('https://cdn.example.com/b.png', HomePage::badgeImageUrl('https://cdn.example.com/b.png'));
        $this->assertStringEndsWith('/uploads/footer-badges/x.png', HomePage::badgeImageUrl('uploads/footer-badges/x.png'));
    }

    /* -------------------------------------------------------------- */
    /*  پنل صفحهٔ اصلی فروشگاه — تب فوتر */
    /* -------------------------------------------------------------- */

    public function test_home_settings_footer_tab_manages_badges(): void
    {
        Setting::set('footer_enamad_code', '<a href="https://trustseal.enamad.ir/?id=777">اینماد قدیمی</a>', 'string', 'home');
        Cache::forget('setting:footer_enamad_code');
        Cache::forget('settings');

        $component = Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Settings\HomePage::class);

        // انتقال خودکار کد اینماد قدیمی به فهرست
        $this->assertCount(1, $component->instance()->badges);
        $this->assertSame('code', $component->instance()->badges[0]['type']);

        $component->call('addFooterBadge')
            ->set('badges.1.type', 'image')
            ->set('badges.1.title', 'مجوز آزمون')
            ->set('badges.1.image', 'uploads/footer-badges/new.png')
            ->call('moveFooterBadge', 1, 'up')
            ->call('save')
            ->assertHasNoErrors();

        $stored = json_decode((string) Setting::get('footer_badges', '[]'), true);

        $this->assertCount(2, $stored);
        $this->assertSame('image', $stored[0]['type']); // بعد از move up اول شد
        $this->assertSame('uploads/footer-badges/new.png', $stored[0]['image']);
        $this->assertSame('code', $stored[1]['type']); // اینماد قدیمی
    }

    public function test_footer_badge_removal_from_panel(): void
    {
        Setting::set('footer_badges', '[]', 'string', 'home');
        Cache::forget('setting:footer_badges');

        $component = Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Settings\HomePage::class)
            ->call('addFooterBadge')
            ->call('addFooterBadge')
            ->set('badges.1.title', 'مجوز باقی‌مانده')
            ->set('badges.1.image', 'uploads/footer-badges/kept.png')
            ->call('removeFooterBadge', 0)
            ->call('save');

        $stored = json_decode((string) Setting::get('footer_badges', '[]'), true);

        $this->assertCount(1, $stored);
        $this->assertSame('uploads/footer-badges/kept.png', $stored[0]['image']);
    }
}
