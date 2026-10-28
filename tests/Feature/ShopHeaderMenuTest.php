<?php

namespace Tests\Feature;

use App\Livewire\Settings\HomePage as SettingsHomePage;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * منوی هدر فروشگاه — Task 61:
 * حذف «ورود مدیر» از هدر + مدیریت ترتیب و روشن/خاموشی لینک‌های ناوبری
 * از پنل /admin/settings/home ← تب «منوی هدر» (گروه menu).
 */
class ShopHeaderMenuTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name'     => 'مدیر آزمون',
            'email'    => 'admin@menu.test',
            'password' => 'secret123',
            'role'     => 'admin',
            'status'   => 'active',
        ]);
    }

    /** ذخیرهٔ مستقیم یک تنظیم منو + پاک‌سازی کش آن (همان کاری که پنل می‌کند) */
    private function setSetting(string $key, string $value): void
    {
        Setting::set($key, $value, 'string', 'menu');
        Cache::forget('setting:'.$key);
    }

    /** بخش nav هدر را از HTML کامل جدا می‌کند تا با متن بدنهٔ صفحه اشتباه نشود */
    private function nav(string $html): string
    {
        return (string) Str::between($html, 'aria-label="ناوبری فروشگاه"', '</nav>');
    }

    /* -------------------------------------------------------------- */
    /*  هدر فروشگاه                                                    */
    /* -------------------------------------------------------------- */

    public function test_header_renders_default_menu_and_no_admin_login(): void
    {
        $html = $this->get(route('shop.home'))->assertOk()->getContent();
        $nav  = $this->nav($html);

        // ترتیب پیش‌فرض: خانه → پکیج‌ها → پروژه‌ها → قالب‌ها → طرح‌های اشتراک
        $this->assertLessThan(mb_strpos($nav, 'پکیج‌ها'), mb_strpos($nav, 'خانه'));
        $this->assertLessThan(mb_strpos($nav, 'پروژه‌ها'), mb_strpos($nav, 'پکیج‌ها'));
        $this->assertLessThan(mb_strpos($nav, 'قالب‌ها'), mb_strpos($nav, 'پروژه‌ها'));

        // «ورود مدیر» از هدر حذف شده است
        $this->assertStringNotContainsString('ورود مدیر', $html);
    }

    public function test_menu_item_can_be_disabled_and_enabled(): void
    {
        $this->assertStringContainsString(
            'طرح‌های اشتراک',
            $this->nav($this->get(route('shop.home'))->getContent())
        );

        // خاموشی → لینک از هدر می‌رود (بدنهٔ صفحه سرجایش است)
        $this->setSetting('menu_subscriptions_enabled', '0');

        $html = $this->get(route('shop.home'))->getContent();
        $this->assertStringNotContainsString('طرح‌های اشتراک', $this->nav($html));

        // روشن‌کردن مجدد
        $this->setSetting('menu_subscriptions_enabled', '1');

        $this->assertStringContainsString(
            'طرح‌های اشتراک',
            $this->nav($this->get(route('shop.home'))->getContent())
        );
    }

    public function test_menu_order_can_be_changed(): void
    {
        // پیش‌فرض: خانه قبل از طرح‌های اشتراک
        $nav = $this->nav($this->get(route('shop.home'))->getContent());
        $this->assertLessThan(mb_strpos($nav, 'طرح‌های اشتراک'), mb_strpos($nav, 'خانه'));

        // طرح‌های اشتراک به بالای منو (sort = ۵)
        $this->setSetting('menu_sort_subscriptions', '5');

        $nav = $this->nav($this->get(route('shop.home'))->getContent());
        $this->assertLessThan(mb_strpos($nav, 'خانه'), mb_strpos($nav, 'طرح‌های اشتراک'));
    }

    public function test_changelog_item_also_follows_feature_flag(): void
    {
        // سوییچ منو روشن، ولی پرچم ویژگی خاموش → لینک «تغییرات» نیست
        Setting::set('feat_changelog_enabled', '0', 'string', 'features');
        Cache::forget('setting:feat_changelog_enabled');

        $this->assertStringNotContainsString(
            'تغییرات',
            $this->nav($this->get(route('shop.home'))->getContent())
        );

        // با روشن‌شدن پرچم ویژگی، لینک برمی‌گردد
        Setting::set('feat_changelog_enabled', '1', 'string', 'features');
        Cache::forget('setting:feat_changelog_enabled');

        $this->assertStringContainsString(
            'تغییرات',
            $this->nav($this->get(route('shop.home'))->getContent())
        );
    }

    /* -------------------------------------------------------------- */
    /*  پنل — تب «منوی هدر»                                            */
    /* -------------------------------------------------------------- */

    public function test_panel_can_reorder_menu_items(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.settings.home'))
            ->assertOk()
            ->assertSee('منوی هدر');

        $panel = Livewire::test(SettingsHomePage::class)
            // مرزها: بالاترین ردیف بالا نمی‌رود
            ->assertSet('form.menu_sort_home', '10')
            ->call('moveMenuUp', 'home')
            ->assertSet('form.menu_sort_home', '10')
            // جابه‌جایی واقعی: خانه یک ردیف پایین (تعویض با پکیج‌ها)
            ->call('moveMenuDown', 'home')
            ->assertSet('form.menu_sort_home', '20')
            ->assertSet('form.menu_sort_packages', '10');

        // رگرسیون Task 60: رندر پنل بلافاصله ترتیب جدید را نشان می‌دهد
        // (برچسب‌های aria دکمه‌های فلش، یکتای هر ردیف‌اند)
        $rendered = $panel->html();
        $this->assertLessThan(
            mb_strpos($rendered, 'انتقال خانه به پایین'),
            mb_strpos($rendered, 'انتقال پکیج‌ها به پایین')
        );

        $panel->call('save')->assertHasNoErrors();

        $this->assertDatabaseHas('settings', ['key' => 'menu_sort_home',     'value' => '20', 'group' => 'menu']);
        $this->assertDatabaseHas('settings', ['key' => 'menu_sort_packages', 'value' => '10', 'group' => 'menu']);

        // هدر فروشگاه ترتیب جدید را می‌بیند: پکیج‌ها قبل از خانه
        $nav = $this->nav($this->get(route('shop.home'))->getContent());
        $this->assertLessThan(mb_strpos($nav, 'خانه'), mb_strpos($nav, 'پکیج‌ها'));
    }

    public function test_panel_menu_toggle_hides_header_link(): void
    {
        Livewire::test(SettingsHomePage::class)
            ->set('form.menu_templates_enabled', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('settings', ['key' => 'menu_templates_enabled', 'value' => '0', 'group' => 'menu']);

        $this->assertStringNotContainsString(
            'قالب‌ها',
            $this->nav($this->get(route('shop.home'))->getContent())
        );
    }
}
