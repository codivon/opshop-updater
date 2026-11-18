<?php

namespace Tests\Feature;

use App\Livewire\Shop\CustomerLogin;
use App\Models\Customer;
use App\Models\Setting;
use App\Services\CaptchaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

/**
 * ثبت‌نام مشتری از صفحهٔ ورود (تب «ثبت‌نام جدید»):
 *  - نمایش تب‌ها + فیلدهای فرم ثبت‌نام
 *  - ثبت‌نام موفق → مشتریِ فعال + کد آپدیت خودکار + ورود خودکار + نرمال‌سازی ورودی‌ها
 *  - اعتبارسنجی (نام/ایمیل/آدرس سایت) + ایمیل و دامنهٔ تکراری
 *  - لایه‌های ضدربات (هانی‌پات / کپچا / قفل تلاش IP)
 */
class CustomerRegisterTest extends TestCase
{
    use MakesShopData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // پیش‌فرض این فایل: کپچا و هانی‌پات خاموش (سناریوهای اختصاصی خودشان روشن می‌کنند)
        foreach (['sec_captcha_customer_login' => '0', 'sec_honeypot_enabled' => '0'] as $key => $value) {
            Setting::set($key, $value, 'string', 'security');
            Cache::forget('setting:'.$key);
        }

        // RateLimiter روی استور «file» است (بین تست‌ها می‌ماند) — کلیدهای این صفحه پاک شود
        RateLimiter::clear('cust-reg:127.0.0.1');
        RateLimiter::clear('cust-login:127.0.0.1');
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('cust-reg:127.0.0.1');
        RateLimiter::clear('cust-login:127.0.0.1');

        parent::tearDown();
    }

    /* ---------------- رندر و تب‌ها ---------------- */

    public function test_login_page_renders_tabs_and_register_fields_after_switch(): void
    {
        Livewire::test(CustomerLogin::class)
            ->assertOk()
            ->assertSee('ورود مشتری')
            ->assertSee('ثبت‌نام جدید')
            ->assertSee('کد آپدیت شما')
            ->assertDontSee('نام و نام خانوادگی')
            ->call('switchTab', 'register')
            ->assertSee('نام و نام خانوادگی')
            ->assertSee('ایمیل')
            ->assertSee('شماره تلفن')
            ->assertSee('آدرس سایت')
            ->assertSee('وضعیت حساب')
            ->assertDontSee('کد آپدیت شما');
    }

    public function test_switch_tab_with_invalid_value_falls_back_to_login(): void
    {
        Livewire::test(CustomerLogin::class)
            ->call('switchTab', 'hacked')
            ->assertSet('tab', CustomerLogin::TAB_LOGIN);
    }

    /* ---------------- ثبت‌نام موفق ---------------- */

    public function test_register_creates_active_customer_and_logs_in(): void
    {
        Livewire::test(CustomerLogin::class)
            ->call('switchTab', 'register')
            ->set('reg.name', 'علی محمدی')
            ->set('reg.email', 'ALI@Example.com')
            ->set('reg.phone', '۰۹۱۲۳۴۵۶۷۸۹')
            ->set('reg.website_url', 'myshop.ir')
            ->call('register')
            ->assertRedirect(route('customer.profile'));

        $customer = Customer::query()->where('email', 'ali@example.com')->firstOrFail();

        $this->assertSame('علی محمدی', $customer->name);
        $this->assertSame('09123456789', $customer->phone);
        $this->assertSame('https://myshop.ir', $customer->website_url);
        $this->assertSame(Customer::STATUS_ACTIVE, $customer->status);
        $this->assertMatchesRegularExpression('/^[A-F0-9]{12}$/', $customer->update_code);

        // ورود خودکار پس از ثبت‌نام
        $this->assertTrue(auth('customer')->check());
        $this->assertSame($customer->id, auth('customer')->id());
        $this->assertSame($customer->update_code, session('shop_update_code'));
    }

    public function test_register_keeps_explicit_scheme_and_trims_trailing_slash(): void
    {
        Livewire::test(CustomerLogin::class)
            ->call('switchTab', 'register')
            ->set('reg.name', 'تابستان دیجیتال')
            ->set('reg.email', 't1@example.com')
            ->set('reg.website_url', 'http://tavarestan.ir/')
            ->call('register');

        $this->assertSame(
            'http://tavarestan.ir',
            Customer::query()->where('email', 't1@example.com')->value('website_url')
        );
    }

    public function test_register_always_redirects_to_profile_even_with_redirect_param(): void
    {
        // تازه‌وارد باید کد آپدیت و راهنمای اتصال فروشگاه را در پروفایل ببیند؛
        // پس حتی با پارامتر redirect داخلی هم مقصد پروفایل است.
        Livewire::withQueryParams(['redirect' => route('shop.subscriptions')])
            ->test(CustomerLogin::class)
            ->call('switchTab', 'register')
            ->set('reg.name', 'مسیردار تست')
            ->set('reg.email', 'redirected@example.com')
            ->set('reg.website_url', 'redirected.ir')
            ->call('register')
            ->assertRedirect(route('customer.profile'));
    }

    public function test_register_ignores_foreign_redirect_host(): void
    {
        Livewire::withQueryParams(['redirect' => 'https://evil.example.com/phishing'])
            ->test(CustomerLogin::class)
            ->call('switchTab', 'register')
            ->set('reg.name', 'ضد اپن ریدایرکت')
            ->set('reg.email', 'open-redirect@example.com')
            ->set('reg.website_url', 'open-redirect.ir')
            ->call('register')
            ->assertRedirect(route('customer.profile'));
    }

    /* ---------------- اعتبارسنجی ---------------- */

    public function test_register_validates_required_fields(): void
    {
        Livewire::test(CustomerLogin::class)
            ->call('switchTab', 'register')
            ->set('reg.email', 'not-an-email')
            ->set('reg.website_url', 'not a url at all!!!')
            ->call('register')
            ->assertHasErrors(['reg.name' => 'required', 'reg.email' => 'email', 'reg.website_url' => 'url']);

        $this->assertSame(0, Customer::count());
    }

    public function test_register_rejects_duplicate_email(): void
    {
        $this->makeCustomer(['email' => 'dup@example.com']);

        Livewire::test(CustomerLogin::class)
            ->call('switchTab', 'register')
            ->set('reg.name', 'کاربر دوم')
            ->set('reg.email', 'dup@example.com')
            ->set('reg.website_url', 'other-site.ir')
            ->call('register')
            ->assertHasErrors(['reg.email']);

        $this->assertSame(1, Customer::count());
        $this->assertFalse(auth('customer')->check());
    }

    /* ---------------- یکتایی دامنهٔ سایت ---------------- */

    public function test_register_rejects_domain_registered_for_another_customer(): void
    {
        // ثبت‌شده با www — دامنهٔ نرمال‌شده یکی است
        $this->makeCustomer(['website_url' => 'https://www.taken.ir']);

        Livewire::test(CustomerLogin::class)
            ->call('switchTab', 'register')
            ->set('reg.name', 'دامنهٔ تکراری')
            ->set('reg.email', 'dup-domain@example.com')
            ->set('reg.website_url', 'https://taken.ir')
            ->call('register')
            ->assertHasErrors(['reg.website_url']);

        $this->assertSame(1, Customer::count());
    }

    public function test_register_allows_distinct_domain(): void
    {
        $this->makeCustomer(['website_url' => 'https://shop.ir']);

        Livewire::test(CustomerLogin::class)
            ->call('switchTab', 'register')
            ->set('reg.name', 'دامنهٔ دیگر')
            ->set('reg.email', 'other-domain@example.com')
            ->set('reg.website_url', 'https://panel.other.ir')
            ->call('register')
            ->assertHasNoErrors();

        $this->assertSame(2, Customer::count());
    }

    /* ---------------- ضدربات ---------------- */

    public function test_register_honeypot_blocks_bot(): void
    {
        Setting::set('sec_honeypot_enabled', '1', 'string', 'security');
        Cache::forget('setting:sec_honeypot_enabled');

        Livewire::test(CustomerLogin::class)
            ->call('switchTab', 'register')
            ->set('reg.name', 'ربات تست')
            ->set('reg.email', 'bot@example.com')
            ->set('reg.website_url', 'bot.ir')
            ->set('website', 'http://spam.bot')
            ->call('register')
            ->assertHasErrors(['reg.email']);

        $this->assertSame(0, Customer::count());
        $this->assertFalse(auth('customer')->check());
    }

    public function test_register_wrong_captcha_is_rejected_and_question_renewed(): void
    {
        Setting::set('sec_captcha_customer_login', '1', 'string', 'security');
        Cache::forget('setting:sec_captcha_customer_login');

        // تلهٔ زمان را صفر می‌کنیم تا سناریو فقط «پاسخ غلط» را بسنجد
        Setting::set('sec_captcha_min_seconds', '0', 'string', 'security');
        Cache::forget('setting:sec_captcha_min_seconds');

        CaptchaService::fresh('customer_login');

        Livewire::test(CustomerLogin::class)
            ->call('switchTab', 'register')
            ->set('reg.name', 'کپچای غلط')
            ->set('reg.email', 'captcha-wrong@example.com')
            ->set('reg.website_url', 'captcha-wrong.ir')
            ->set('captcha', '999999')
            ->call('register')
            ->assertHasErrors(['captcha']);

        $this->assertSame(0, Customer::count());
    }

    public function test_register_correct_captcha_passes(): void
    {
        Setting::set('sec_captcha_customer_login', '1', 'string', 'security');
        Cache::forget('setting:sec_captcha_customer_login');

        Setting::set('sec_captcha_min_seconds', '0', 'string', 'security');
        Cache::forget('setting:sec_captcha_min_seconds');

        // پاسخ را بعد از ساخت کامپوننت می‌خوانیم چون mount() سؤال تازه می‌سازد
        $component = Livewire::test(CustomerLogin::class)
            ->call('switchTab', 'register')
            ->set('reg.name', 'کپچای درست')
            ->set('reg.email', 'captcha-ok@example.com')
            ->set('reg.website_url', 'captcha-ok.ir');

        $answer = (string) session(CaptchaService::SESSION_ANSWER);

        $component
            ->set('captcha', $answer)
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('customer.profile'));

        $this->assertSame(1, Customer::count());
        $this->assertTrue(auth('customer')->check());
    }

    public function test_register_rate_limit_locks_repeated_attempts(): void
    {
        // شش تلاشِ قبلی از همین IP (سقف ثبت‌نام) — کلید/سقف باید با کامپوننت یکی باشد
        for ($i = 0; $i < 6; $i++) {
            RateLimiter::hit('cust-reg:127.0.0.1', 30 * 60);
        }

        Livewire::test(CustomerLogin::class)
            ->call('switchTab', 'register')
            ->set('reg.name', 'قفل‌شده')
            ->set('reg.email', 'locked@example.com')
            ->set('reg.website_url', 'locked.ir')
            ->call('register')
            ->assertHasErrors(['reg.email']);

        $this->assertSame(0, Customer::count());
    }

    public function test_register_clears_limiter_after_success(): void
    {
        Livewire::test(CustomerLogin::class)
            ->call('switchTab', 'register')
            ->set('reg.name', 'پاک‌کنندهٔ قفل')
            ->set('reg.email', 'limiter-clear@example.com')
            ->set('reg.website_url', 'limiter-clear.ir')
            ->call('register');

        $this->assertFalse(RateLimiter::tooManyAttempts('cust-reg:127.0.0.1', 6));
    }
}
