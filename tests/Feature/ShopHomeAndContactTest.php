<?php

namespace Tests\Feature;

use App\Livewire\Messages\Index as MessagesIndex;
use App\Livewire\Settings\HomePage as SettingsHomePage;
use App\Livewire\Shop\ContactForm;
use App\Models\ContactMessage;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * صفحهٔ اصلی فروشگاه (معرفی + محصولات + تماس با ما) — Task 56:
 * ویجت‌های قابل ویرایش از پنل /admin/settings/home، فرم تماس با ضداسپم،
 * صندوق پیام‌های /admin/messages و کد اینماد فوتر.
 */
class ShopHomeAndContactTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name'     => 'مدیر آزمون',
            'email'    => 'admin@home.test',
            'password' => 'secret123',
            'role'     => 'admin',
            'status'   => 'active',
        ]);
    }

    /** ذخیرهٔ مستقیم یک تنظیم + پاک‌سازی کش آن (همان کاری که پنل می‌کند) */
    private function setSetting(string $key, string $value): void
    {
        Setting::set($key, $value, 'string', 'home');
        Cache::forget('setting:'.$key);
    }

    /* -------------------------------------------------------------- */
    /*  رندر صفحهٔ اصلی                                                 */
    /* -------------------------------------------------------------- */

    public function test_home_shows_intro_then_products_and_contact(): void
    {
        $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee('از اول تا بزرگ شدن استارتاپ کنار شما هستیم') // هیرو (معرفی)
            ->assertSee('معرفی')                                       // بخش معرفی
            ->assertSee('سال تجربه')                                   // آمار اعتماد
            ->assertSee('چرا به ما اعتماد می‌کنند؟')                    // چرا ما
            ->assertSee('نظرات مشتریان')                               // نظرات
            ->assertSee('پکیج‌های فروشگاه')                             // محصولات
            ->assertSee('تماس با ما')                                  // تماس
            ->assertSee('قالب اختصاصی')
            ->assertSee('استارتاپ اختصاصی')
            ->assertSee('info@weblak.ir');                             // راه ارتباطی پیش‌فرض
    }

    public function test_home_content_is_editable_from_settings(): void
    {
        $this->setSetting('home_hero_title', 'عنوان ویژهٔ آزمون پنل');

        $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee('عنوان ویژهٔ آزمون پنل');
    }

    public function test_widgets_can_be_disabled(): void
    {
        $this->setSetting('home_about_enabled', '0');
        $this->setSetting('home_contact_enabled', '0');

        $this->get(route('shop.home'))
            ->assertOk()
            ->assertDontSee('معرفی')
            ->assertDontSee('info@weblak.ir');
    }

    public function test_categories_section_can_be_disabled_and_enabled(): void
    {
        \App\Models\Category::create(['name' => 'دستهٔ پکیج', 'slug' => 'cat-pkg', 'type' => 'package', 'is_active' => true]);
        \App\Models\Category::create(['name' => 'دستهٔ پروژه', 'slug' => 'cat-prd', 'type' => 'product', 'is_active' => true]);

        // پیش‌فرض: بخش دسته‌بندی محصولات روی صفحهٔ اصلی دیده می‌شود
        $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee('دسته‌بندی محصولات');

        // خاموش‌کردن سوییچ مستقل دسته‌بندی (با سوییچ پکیج/پروژهٔ روشن) → کل بخش مخفی می‌شود
        $this->setSetting('home_categories_enabled', '0');
        $this->get(route('shop.home'))
            ->assertOk()
            ->assertDontSee('دسته‌بندی محصولات');

        // روشن‌کردن مجدد → بخش برمی‌گردد
        $this->setSetting('home_categories_enabled', '1');
        $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee('دسته‌بندی محصولات');

        // رگرسیون: با خاموشی پکیج‌ها و پروژه‌ها، بخش دسته‌بندیِ روشن رندر نمی‌شود (ظرف خالی نمی‌ماند)
        $this->setSetting('home_packages_enabled', '0');
        $this->setSetting('home_products_enabled', '0');
        $this->get(route('shop.home'))
            ->assertOk()
            ->assertDontSee('دسته‌بندی محصولات');

        // سوییچ «دسته‌بندی محصولات» در پنل صفحهٔ اصلی موجود است
        $this->actingAs($this->admin)
            ->get(route('admin.settings.home'))
            ->assertOk()
            ->assertSee('دسته‌بندی محصولات');
    }

    public function test_home_sections_can_be_reordered_from_settings(): void
    {
        // ترتیب پیش‌فرض: هیرو قبل از تماس با ما
        $default = $this->get(route('shop.home'))->assertOk()->getContent();
        $this->assertLessThan(
            mb_strpos($default, 'هر ایده‌ای در سر دارید'),
            mb_strpos($default, 'از اول تا بزرگ شدن استارتاپ')
        );

        // تماس با ما بالاتر از هیرو (کلیدهای home_sort_*)
        $this->setSetting('home_sort_contact', '10');
        $this->setSetting('home_sort_hero', '120');

        $html = $this->get(route('shop.home'))->assertOk()->getContent();
        $this->assertLessThan(
            mb_strpos($html, 'از اول تا بزرگ شدن استارتاپ'),
            mb_strpos($html, 'هر ایده‌ای در سر دارید')
        );
    }

    public function test_panel_can_reorder_sections(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.settings.home'))
            ->assertOk()
            ->assertSee('نمایش و ترتیب بخش‌ها');

        Livewire::test(SettingsHomePage::class)
            // مرزها: بالاترین ردیف بالا نمی‌رود و پایین‌ترین پایین نمی‌رود
            ->assertSet('form.home_sort_hero', '10')
            ->call('moveSectionUp', 'hero')
            ->assertSet('form.home_sort_hero', '10')
            ->call('moveSectionDown', 'contact')
            ->assertSet('form.home_sort_contact', '120')
            // جابه‌جایی واقعی: هیرو دو ردیف پایین (تعویض با اسلایدر معرفی و بعد آمار اعتماد — Task 71 بخش جدید بین این دو)
            ->call('moveSectionDown', 'hero')
            ->assertSet('form.home_sort_hero', '15')
            ->assertSet('form.home_sort_showcase', '10')
            ->call('moveSectionDown', 'hero')
            ->assertSet('form.home_sort_hero', '20')
            ->assertSet('form.home_sort_stats', '15')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('settings', ['key' => 'home_sort_hero',  'value' => '20', 'group' => 'home']);
        $this->assertDatabaseHas('settings', ['key' => 'home_sort_stats', 'value' => '15', 'group' => 'home']);

        // روی صفحهٔ اصلی: آمار اعتماد («سال تجربه») بالاتر از هیرو رندر می‌شود
        $html = $this->get(route('shop.home'))->assertOk()->getContent();
        $this->assertLessThan(
            mb_strpos($html, 'از اول تا بزرگ شدن استارتاپ'),
            mb_strpos($html, 'سال تجربه')
        );
    }

    public function test_panel_move_updates_rendered_row_order_immediately(): void
    {
        // رگرسیون باگ «ویجت‌ها جابه‌جا نمیشوند»: computed sectionRows در همان درخواستِ
        // فلش کش می‌شد و رندر بعدی ترتیب کهنه را نشان می‌داد — پس از move، خودِ HTML
        // رندرشدهٔ پنل باید بلافاصله ترتیب جدید را نشان دهد (قبل از هر ذخیره‌ای).
        $panel = Livewire::test(SettingsHomePage::class)
            ->assertSeeInOrder(['هیرو (معرفی بالا)', 'اسلایدر معرفی', 'آمار اعتماد'], false)
            ->call('moveSectionDown', 'hero');

        $html = $panel->html();
        $this->assertLessThan(
            mb_strpos($html, 'هیرو (معرفی بالا)'),
            mb_strpos($html, 'اسلایدر معرفی')
        );

        // و برگشت به جای اولش
        $panel->call('moveSectionUp', 'hero');

        $html = $panel->html();
        $this->assertLessThan(
            mb_strpos($html, 'هیرو (معرفی بالا)'),
            mb_strpos($html, 'اسلایدر معرفی')
        );
    }

    public function test_subscription_active_box_is_independent_widget(): void
    {
        // طرح اشتراک فعال تا بخش «طرح‌های اشتراک ویژه» هم رندر شود
        \App\Models\SubscriptionPlan::create([
            'name' => 'طرح آزمون', 'slug' => 'plan-test',
            'price' => 100000, 'duration_months' => 1, 'is_active' => true,
        ]);

        // پیش‌فرض: جعبهٔ «اشتراک فعال دارید؟» و طرح‌های اشتراک هر دو هست‌اند
        $this->get(route('shop.home'))->assertOk()
            ->assertSee('اشتراک فعال دارید؟')
            ->assertSee('طرح‌های اشتراک ویژه');

        // خاموشی «اشتراک فعال» → جعبهٔ بررسی می‌رود؛ طرح‌ها سرجایشان
        $this->setSetting('home_subscription_active_enabled', '0');
        $this->get(route('shop.home'))->assertOk()
            ->assertDontSee('اشتراک فعال دارید؟')
            ->assertSee('طرح‌های اشتراک ویژه');

        // خاموشی «اشتراک‌ها» → طرح‌ها می‌روند؛ جعبهٔ بررسی مستقل می‌ماند
        $this->setSetting('home_subscription_active_enabled', '1');
        $this->setSetting('home_subscription_enabled', '0');
        $this->get(route('shop.home'))->assertOk()
            ->assertSee('اشتراک فعال دارید؟')
            ->assertDontSee('طرح‌های اشتراک ویژه');

        $this->setSetting('home_subscription_enabled', '1');
    }

    public function test_subscription_active_box_can_be_reordered(): void
    {
        // طرح اشتراک فعال تا بخش «طرح‌های اشتراک ویژه» هم رندر شود
        \App\Models\SubscriptionPlan::create([
            'name' => 'طرح آزمون', 'slug' => 'plan-test',
            'price' => 100000, 'duration_months' => 1, 'is_active' => true,
        ]);

        // پیش‌فرض (۹۵): جعبهٔ «اشتراک فعال دارید؟» بالای طرح‌های اشتراک (۱۰۰)
        $html = $this->get(route('shop.home'))->getContent();
        $this->assertLessThan(
            mb_strpos($html, 'طرح‌های اشتراک ویژه'),
            mb_strpos($html, 'اشتراک فعال دارید؟')
        );

        // انتقال آن به پایینِ طرح‌ها از پنل (کلید home_sort_*)
        $this->setSetting('home_sort_subscription_active', '130');

        $html = $this->get(route('shop.home'))->getContent();
        $this->assertLessThan(
            mb_strpos($html, 'اشتراک فعال دارید؟'),
            mb_strpos($html, 'طرح‌های اشتراک ویژه')
        );
    }

    public function test_track_section_can_be_disabled(): void
    {
        $this->get(route('shop.home'))->assertOk()->assertSee('خرید قبلی دارید؟');

        $this->setSetting('home_track_enabled', '0');
        $this->get(route('shop.home'))->assertOk()->assertDontSee('خرید قبلی دارید؟');

        $this->setSetting('home_track_enabled', '1');
        $this->get(route('shop.home'))->assertOk()->assertSee('خرید قبلی دارید؟');
    }

    public function test_enamad_code_renders_in_footer_when_set(): void
    {
        $this->get(route('shop.home'))->assertOk()->assertDontSee('trustseal.enamad.ir');

        $this->setSetting('footer_enamad_code', '<a href="https://trustseal.enamad.ir/?id=777">اینماد</a>');

        $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee('trustseal.enamad.ir', false)
            ->assertSee('اینماد');
    }

    /* -------------------------------------------------------------- */
    /*  پنل «صفحهٔ اصلی فروشگاه»                                        */
    /* -------------------------------------------------------------- */

    public function test_home_settings_page_renders_for_admin(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.settings.home'))
            ->assertOk()
            ->assertSee('صفحهٔ اصلی فروشگاه')
            ->assertSee('کد اینماد');
    }

    public function test_guest_cannot_open_home_settings(): void
    {
        $this->get(route('admin.settings.home'))->assertRedirect(route('login'));
    }

    public function test_admin_can_save_home_settings(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(SettingsHomePage::class)
            ->assertSet('form.home_hero_title', 'از اول تا بزرگ شدن استارتاپ کنار شما هستیم')
            ->set('form.home_hero_title', 'تست ذخیرهٔ پنل')
            ->set('form.contact_email', 'not-an-email')
            ->call('save')
            ->assertHasErrors(['form.contact_email']);

        Livewire::test(SettingsHomePage::class)
            ->set('form.home_hero_title', 'تست ذخیرهٔ پنل')
            ->set('form.contact_email', 'boss@example.com')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertDatabaseHas('settings', [
            'key'   => 'home_hero_title',
            'value' => 'تست ذخیرهٔ پنل',
            'group' => 'home',
        ]);

        // مقدار ذخیره‌شده روی صفحهٔ اصلی اعمال می‌شود
        $this->get(route('shop.home'))->assertSee('تست ذخیرهٔ پنل');
    }

    /* -------------------------------------------------------------- */
    /*  لینک سایت برند (weblak.ir) — Task 62                            */
    /* -------------------------------------------------------------- */

    public function test_hero_third_button_and_about_portfolio_link_render_by_default(): void
    {
        $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee('بیشتر درباره من و نمونه‌کارها')    // دکمهٔ سوم هیرو
            ->assertSee('href="https://weblak.ir"', false)  // لینک پیش‌فرض برند
            ->assertSee('target="_blank"', false)           // لینک بیرونی در تب جدید
            ->assertSee('مشاهده نمونه‌کارها در وب‌لاک');     // لینک بخش معرفی
    }

    public function test_brand_links_are_editable_and_hideable(): void
    {
        // متن دکمهٔ سوم خالی → فقط همان دکمه مخفی می‌شود؛ دکمه‌های دیگر سرجایشان‌اند
        $this->setSetting('home_hero_cta3_text', '');

        $this->get(route('shop.home'))
            ->assertOk()
            ->assertDontSee('بیشتر درباره من و نمونه‌کارها')
            ->assertSee('مشاهده محصولات'); // دکمهٔ اول

        // متن و لینک سفارشی برای دکمهٔ سوم و لینک معرفی
        $this->setSetting('home_hero_cta3_text', 'نمونه‌کارهای من');
        $this->setSetting('home_about_more_link', 'https://weblak.ir/portfolio');

        $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee('نمونه‌کارهای من')
            ->assertSee('href="https://weblak.ir/portfolio"', false);
    }

    public function test_panel_edits_brand_links_and_persists(): void
    {
        Livewire::test(SettingsHomePage::class)
            ->assertSet('form.home_hero_cta3_link', 'https://weblak.ir')
            ->set('form.home_about_more_text', 'پورتفولیوی من')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertDatabaseHas('settings', [
            'key'   => 'home_about_more_text',
            'value' => 'پورتفولیوی من',
            'group' => 'home',
        ]);

        $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee('پورتفولیوی من');
    }

    /* -------------------------------------------------------------- */
    /*  فرم تماس با ما                                                  */
    /* -------------------------------------------------------------- */

    private function disableAntiSpam(): void
    {
        Setting::set('sec_captcha_contact', '0', 'string', 'security');
        Cache::forget('setting:sec_captcha_contact');
    }

    public function test_contact_form_submits_message(): void
    {
        $this->disableAntiSpam();

        Livewire::test(ContactForm::class)
            ->set('name', 'سارا محمدی')
            ->set('phone', '۰۹۱۲۳۴۵۶۷۸۹') // ارقام فارسی هم پذیرفته می‌شود
            ->set('email', 'sara@example.com')
            ->set('requestType', 'startup')
            ->set('message', 'یک ایده استارتاپ دارم؛ لطفاً برای مشاوره تماس بگیرید.')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $message = ContactMessage::query()->sole();

        $this->assertSame('سارا محمدی', $message->name);
        $this->assertSame('09123456789', $message->phone); // نرمال‌شده
        $this->assertSame('startup', $message->request_type);
        $this->assertTrue($message->isNew());
    }

    public function test_contact_form_validates_input(): void
    {
        $this->disableAntiSpam();

        Livewire::test(ContactForm::class)
            ->set('name', '')
            ->set('phone', '12345')           // موبایل نامعتبر
            ->set('requestType', 'hacking')   // نوع نامعتبر
            ->set('message', 'کوتاه')
            ->call('submit')
            ->assertHasErrors(['name', 'phone', 'requestType', 'message']);

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_contact_form_blocks_honeypot_bots(): void
    {
        $this->disableAntiSpam();

        Livewire::test(ContactForm::class)
            ->set('name', 'ربات')
            ->set('phone', '09120000000')
            ->set('message', 'اسپم اسپم اسپم اسپم')
            ->set('website', 'http://spam.example') // فیلد مخفی پُر شد = ربات
            ->call('submit')
            ->assertHasErrors(['captcha']);

        $this->assertDatabaseCount('contact_messages', 0);
    }

    /* -------------------------------------------------------------- */
    /*  صندوق پیام‌های تماس                                             */
    /* -------------------------------------------------------------- */

    public function test_messages_inbox_lists_and_marks_read(): void
    {
        $message = ContactMessage::create([
            'name'         => 'علی رضایی',
            'phone'        => '09351112233',
            'request_type' => 'project',
            'message'      => 'درخواست ساخت سایت شرکتی با پنل مدیریت.',
        ]);

        $this->assertSame(1, ContactMessage::newCount());

        $this->actingAs($this->admin)
            ->get(route('admin.messages.index'))
            ->assertOk()
            ->assertSee('علی رضایی')
            ->assertSee('پروژه اختصاصی');

        Livewire::test(MessagesIndex::class)
            ->call('view', $message->id)
            ->assertSet('viewingId', $message->id);

        $this->assertFalse($message->fresh()->isNew());

        Livewire::test(MessagesIndex::class)
            ->call('toggleRead', $message->id);

        $this->assertTrue($message->fresh()->isNew());
        $this->assertSame(1, ContactMessage::newCount());
    }

    public function test_messages_inbox_deletes(): void
    {
        $message = ContactMessage::create([
            'name'         => 'کاربر حذف',
            'phone'        => '09122223344',
            'request_type' => 'consult',
            'message'      => 'سؤالی درباره مشاوره دارم.',
        ]);

        Livewire::actingAs($this->admin)
            ->test(MessagesIndex::class)
            ->call('delete', $message->id);

        $this->assertDatabaseMissing('contact_messages', ['id' => $message->id]);
    }

    public function test_guest_cannot_open_messages_inbox(): void
    {
        $this->get(route('admin.messages.index'))->assertRedirect(route('login'));
    }
}
