<?php

namespace Tests\Feature;

use App\Livewire\Shop\PackageCheckout;
use App\Models\DiscountCode;
use App\Models\PackagePurchase;
use App\Models\Setting;
use App\Models\User;
use App\Services\CaptchaService;
use App\Services\IranIpGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

/**
 * کپچای چنددرایوره + گارد IP پرداخت (Task 81):
 *
 *  کپچا:
 *   - درایور پیش‌فرض «ریاضی پیشرفته» (سازگار با رفتار قدیمی) + سؤال چندعملوندی
 *   - درایور «تصویری حرف‌عددی» با پاسخ ۵ نویسه‌ای و مقایسهٔ case-insensitive
 *   - فال‌بک امن درایور ابری بدون کلید → ریاضی خودمیزبان
 *   - راستی‌آزمایی توکن ابری (turnstile/hcaptcha) با API سایت‌وریفی
 *   - تلهٔ زمان روی کپچای خودمیزبان
 *
 *  گارد IP پرداخت:
 *   - IP خارج از ایران → بستن انتقال به درگاه + مودال هشدار (بدون تراکنش)
 *   - پرچم proxy/hosting (فیلترشکن/دیتاسنتر) حتی با IP ایران → بستن + مودال
 *   - IP ایران سالم → ریدایرکت به درگاه مثل قبل
 *   - مسیر رایگان (کد تخفیف کامل) از گارد مستثنی است
 *   - قطعی سرویس‌ها: حالت عادی اجازه، حالت سخت‌گیرانه بستن
 *   - IP خصوصی/لوکال (تست) همیشه مجاز
 */
class CaptchaDriversAndIpGuardTest extends TestCase
{
    use MakesShopData, RefreshDatabase;

    /** IP عمومی ساختگی برای تست گارد */
    protected const FAKE_IP = '93.110.20.35';

    protected function setUp(): void
    {
        parent::setUp();

        // تلهٔ زمان خاموش برای پاسخ‌دادن بی‌درنگ در تست‌ها
        Setting::set('sec_captcha_min_seconds', '0', 'string', 'security');
        Cache::forget('setting:sec_captcha_min_seconds');

        // کش نتیجهٔ گارد IP بین تست‌ها نشتی می‌کند → پاک‌سازی کامل
        Cache::flush();

        // IP عمومی ساختگی برای گارد روی جریان پرداخت —
        // فراخوان‌های Livewire::test از makeRequest می‌گذرند و این مقدار را می‌خوانند
        $this->withServerVariables(['REMOTE_ADDR' => self::FAKE_IP]);
    }

    /* -------------------------------------------------------------- */
    /*  درایورها */
    /* -------------------------------------------------------------- */

    public function test_default_driver_is_advanced_math(): void
    {
        CaptchaService::fresh('admin_login');

        $this->assertSame('math', CaptchaService::driver('admin_login'));
        $this->assertIsNumeric(session(CaptchaService::SESSION_ANSWER));
        $this->assertNotSame('', session(CaptchaService::SESSION_QUESTION));
    }

    public function test_math_driver_supports_multi_operand_expression(): void
    {
        Setting::set('sec_captcha_math_operands', '3', 'string', 'security');
        Cache::forget('setting:sec_captcha_math_operands');

        CaptchaService::fresh('checkout');

        // ۳ عملوند = دو عملگر در سؤال (جمع/ضرب/تفریق)
        $question = (string) session(CaptchaService::SESSION_QUESTION);
        $operators = preg_match_all('/[+\x{00d7}\x{2212}]/u', $question);

        $this->assertSame(2, $operators);

        // پاسخ درست پذیرفته می‌شود
        $answer = (string) session(CaptchaService::SESSION_ANSWER);
        $this->assertTrue(CaptchaService::verify(fa_num($answer), 'checkout'));
    }

    public function test_image_driver_generates_alphanumeric_question(): void
    {
        Setting::set('sec_captcha_driver_customer_login', 'image', 'string', 'security');
        Cache::forget('setting:sec_captcha_driver_customer_login');

        CaptchaService::fresh('customer_login');

        $answer = (string) session(CaptchaService::SESSION_ANSWER);

        $this->assertMatchesRegularExpression('/^[A-Z0-9]{5}$/', $answer);
        $this->assertSame($answer, (string) session(CaptchaService::SESSION_QUESTION));

        // پاسخ درست (حتی با حروف کوچک) پذیرفته و پاسخ غلط رد می‌شود
        $this->assertTrue(CaptchaService::verify(strtolower($answer), 'customer_login'));

        Setting::set('sec_captcha_driver_customer_login', 'image', 'string', 'security');
        Cache::forget('setting:sec_captcha_driver_customer_login');
        CaptchaService::fresh('customer_login');

        $this->assertFalse(CaptchaService::verify('ZZZZZ', 'customer_login'));
    }

    public function test_cloud_driver_without_keys_falls_back_to_math(): void
    {
        Setting::set('sec_captcha_driver_checkout', 'turnstile', 'string', 'security');
        Cache::forget('setting:sec_captcha_driver_checkout');

        $this->assertSame('math', CaptchaService::driver('checkout'));

        Setting::set('sec_captcha_turnstile_site', '0x4AAAAAAAsite', 'string', 'security');
        Setting::set('sec_captcha_turnstile_secret', '0x4AAAAAAAscret', 'string', 'security');
        Cache::forget('setting:sec_captcha_turnstile_site');
        Cache::forget('setting:sec_captcha_turnstile_secret');

        $this->assertSame('turnstile', CaptchaService::driver('checkout'));
    }

    public function test_turnstile_valid_token_is_accepted(): void
    {
        Setting::set('sec_captcha_driver_checkout', 'turnstile', 'string', 'security');
        Setting::set('sec_captcha_turnstile_site', '0x4AAAAAAAsite', 'string', 'security');
        Setting::set('sec_captcha_turnstile_secret', '0x4AAAAAAAscret', 'string', 'security');
        Cache::forget('setting:sec_captcha_driver_checkout');

        Http::fake([
            'challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => true]),
        ]);

        $this->assertTrue(CaptchaService::verify('XXXX-token', 'checkout'));
    }

    public function test_turnstile_invalid_token_is_rejected_fail_closed(): void
    {
        Setting::set('sec_captcha_driver_checkout', 'turnstile', 'string', 'security');
        Setting::set('sec_captcha_turnstile_site', '0x4AAAAAAAsite', 'string', 'security');
        Setting::set('sec_captcha_turnstile_secret', '0x4AAAAAAAscret', 'string', 'security');
        Cache::forget('setting:sec_captcha_driver_checkout');

        Http::fake([
            'challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => false]),
        ]);

        $this->assertFalse(CaptchaService::verify('bad-token', 'checkout'));
    }

    public function test_hcaptcha_token_verified_with_siteverify_api(): void
    {
        Setting::set('sec_captcha_driver_comments', 'hcaptcha', 'string', 'security');
        Setting::set('sec_captcha_hcaptcha_site', '10000000-ffff', 'string', 'security');
        Setting::set('sec_captcha_hcaptcha_secret', '0xsecret', 'string', 'security');
        Cache::forget('setting:sec_captcha_driver_comments');

        Http::fake([
            'api.hcaptcha.com/siteverify' => Http::response(['success' => true]),
        ]);

        $this->assertTrue(CaptchaService::verify('P1_eyJ', 'comments'));
    }

    /* -------------------------------------------------------------- */
    /*  گارد IP پرداخت — سرویس */
    /* -------------------------------------------------------------- */

    public function test_guard_blocks_foreign_ip(): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response(['status' => 'success', 'countryCode' => 'US', 'proxy' => false, 'hosting' => false]),
        ]);

        $verdict = IranIpGuard::check(self::FAKE_IP);

        $this->assertFalse($verdict['ok']);
        $this->assertSame('US', $verdict['country']);
        $this->assertStringContainsString('IP ایران', (string) $verdict['reason']);
    }

    public function test_guard_blocks_vpn_flag_even_for_iranian_ip(): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response(['status' => 'success', 'countryCode' => 'IR', 'proxy' => true, 'hosting' => false]),
        ]);

        $verdict = IranIpGuard::check(self::FAKE_IP);

        $this->assertFalse($verdict['ok']);
        $this->assertTrue($verdict['vpn']);
        $this->assertStringContainsString('فیلترشکن', (string) $verdict['reason']);
    }

    public function test_guard_allows_clean_iranian_ip(): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response(['status' => 'success', 'countryCode' => 'IR', 'proxy' => false, 'hosting' => false]),
        ]);

        $verdict = IranIpGuard::check(self::FAKE_IP);

        $this->assertTrue($verdict['ok']);
        $this->assertFalse($verdict['vpn']);
    }

    public function test_guard_falls_back_to_second_provider(): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response([], 500),
            'ipwho.is/*' => Http::response(['success' => true, 'country_code' => 'IR']),
        ]);

        $verdict = IranIpGuard::check(self::FAKE_IP);

        $this->assertTrue($verdict['ok']);
        $this->assertSame('IR', $verdict['country']);
    }

    public function test_guard_fail_open_by_default_and_closed_in_strict_mode(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        Setting::set('sec_payment_ip_guard_strict', '0', 'string', 'security');
        Cache::forget('setting:sec_payment_ip_guard_strict');

        $this->assertTrue(IranIpGuard::check(self::FAKE_IP)['ok']);

        Setting::set('sec_payment_ip_guard_strict', '1', 'string', 'security');
        Cache::forget('setting:sec_payment_ip_guard_strict');

        Cache::forget('ipguard:'.md5(self::FAKE_IP));

        $verdict = IranIpGuard::check(self::FAKE_IP);

        $this->assertFalse($verdict['ok']);
        $this->assertTrue($verdict['unknown']);
    }

    public function test_guard_allways_allows_private_ip(): void
    {
        Http::fake(['*' => Http::response(['status' => 'success', 'countryCode' => 'US'])]);

        $this->assertTrue(IranIpGuard::check('127.0.0.1')['ok']);
        $this->assertTrue(IranIpGuard::check('10.0.0.5')['ok']);
        $this->assertTrue(IranIpGuard::check('')['ok']);
    }

    /* -------------------------------------------------------------- */
    /*  گارد IP + کپچا روی جریان پرداخت (checkout) */
    /* -------------------------------------------------------------- */

    private function makePendingPurchase(): PackagePurchase
    {
        $package = $this->makePackage();
        $this->makePackageVersion($package);
        $plan = $this->makePlan($package, ['price' => 150000]);
        $customer = $this->makeCustomer();

        return PackagePurchase::create([
            'package_id' => $package->id,
            'version_id' => $package->latestVersion()->first()->id,
            'pricing_plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'callback_url' => 'https://example.com/pay/callback',
            'gateway' => 'local',
            'amount' => 150000,
            'original_amount' => 150000,
            'status' => PackagePurchase::STATUS_PENDING,
        ]);
    }

    public function test_pay_with_wrong_captcha_is_blocked(): void
    {
        // کپچای checkout فعال + گارد خاموش
        Setting::set('sec_payment_ip_guard', '0', 'string', 'security');
        Cache::forget('setting:sec_payment_ip_guard');

        $purchase = $this->makePendingPurchase();

        Livewire::test(PackageCheckout::class, ['purchase' => $purchase])
            ->call('pay')
            ->assertNoRedirect()
            ->assertHasErrors(['captcha']);

        // تراکنشی ساخته نشده
        $this->assertNull($purchase->fresh()->transaction_id);
    }

    public function test_pay_with_correct_captcha_passes(): void
    {
        // کپچای checkout فعال + گارد خاموش
        Setting::set('sec_payment_ip_guard', '0', 'string', 'security');
        Cache::forget('setting:sec_payment_ip_guard');

        $purchase = $this->makePendingPurchase();

        // mount سؤال تازه می‌سازد → پاسخ را بعد از mount از سشن می‌خوانیم
        $component = Livewire::test(PackageCheckout::class, ['purchase' => $purchase]);
        $answer = (string) session(CaptchaService::SESSION_ANSWER);

        $component
            ->set('captcha', fa_num($answer))
            ->call('pay')
            ->assertRedirect();

        $this->assertNotNull($purchase->fresh()->transaction_id);
    }

    /**
     * گارد جایگزین برای تست جریان Livewire —
     * در تست کامپوننت، request IP لوکال است (Livewire::test) و Http::fake به آن
     * نمی‌رسد؛ پس نتیجهٔ گارد را با swap کانتینر کنترل می‌کنیم.
     */
    private function swapGuard(bool $ok, ?string $reason = null, ?string $country = null, bool $vpn = false, bool $unknown = false): void
    {
        $this->swap(IranIpGuard::class, new class($ok, $reason, $country, $vpn, $unknown) extends IranIpGuard
        {
            public function __construct(
                protected bool $verdictOk,
                protected ?string $verdictReason,
                protected ?string $verdictCountry,
                protected bool $verdictVpn,
                protected bool $verdictUnknown,
            ) {}

            public function checkIp(?string $ip = null): array
            {
                return [
                    'ok' => $this->verdictOk,
                    'reason' => $this->verdictReason,
                    'country' => $this->verdictCountry,
                    'vpn' => $this->verdictVpn,
                    'unknown' => $this->verdictUnknown,
                ];
            }
        });
    }

    public function test_pay_with_foreign_ip_shows_vpn_modal_and_no_transaction(): void
    {
        Setting::set('sec_captcha_checkout', '0', 'string', 'security');
        Cache::forget('setting:sec_captcha_checkout');

        $this->swapGuard(
            false,
            'پرداخت‌ها فقط از IP ایران پذیرفته می‌شود. لطفاً فیلترشکن (VPN) خود را خاموش کنید، صفحه را دوباره بارگذاری کنید و دوباره تلاش کنید.',
            'DE',
        );

        $purchase = $this->makePendingPurchase();

        Livewire::test(PackageCheckout::class, ['purchase' => $purchase])
            ->call('pay')
            ->assertNoRedirect()
            ->assertSet('showVpnModal', true)
            ->assertSee('فیلترشکن');

        // تراکنش درگاه ساخته نشده و خرید همچنان pending است
        $fresh = $purchase->fresh();

        $this->assertNull($fresh->transaction_id);
        $this->assertNull($fresh->payment_url);
        $this->assertTrue($fresh->status === PackagePurchase::STATUS_PENDING);
    }

    public function test_pay_with_iranian_ip_redirects_to_gateway(): void
    {
        Setting::set('sec_captcha_checkout', '0', 'string', 'security');
        Cache::forget('setting:sec_captcha_checkout');

        // IP ایران سالم → عبور از گارد و ساخت تراکنش مثل قبل
        $this->swapGuard(true, null, 'IR');

        $purchase = $this->makePendingPurchase();

        Livewire::test(PackageCheckout::class, ['purchase' => $purchase])
            ->call('pay')
            ->assertRedirect();

        $fresh = $purchase->fresh();

        $this->assertNotNull($fresh->transaction_id);
        $this->assertStringContainsString('/payment/form/', (string) $fresh->payment_url);
    }

    public function test_full_discount_free_path_bypasses_ip_guard(): void
    {
        // حتی با حکمِ مسدودِ گارد، مسیر رایگان (کد تخفیف کامل) باز می‌ماند
        Setting::set('sec_captcha_checkout', '0', 'string', 'security');
        Cache::forget('setting:sec_captcha_checkout');

        $this->swapGuard(false, 'پرداخت فقط از IP ایران — فیلترشکن خاموش کنید.', 'DE');

        $coupon = $this->makeCoupon([
            'code' => 'FREEPASS',
            'type' => DiscountCode::TYPE_PERCENT,
            'value' => 100,
            'applies_products' => false,
        ]);

        $purchase = $this->makePendingPurchase();

        Livewire::test(PackageCheckout::class, ['purchase' => $purchase])
            ->set('codeInput', 'FREEPASS')
            ->call('applyDiscount')
            ->call('pay')
            ->assertSet('showVpnModal', false)
            ->assertRedirect();

        $this->assertTrue($purchase->fresh()->isPaid());
        $this->assertSame(0, (int) $purchase->fresh()->amount);
        $this->assertSame(1, $coupon->fresh()->used_count);
    }

    /* -------------------------------------------------------------- */
    /*  پنل تنظیمات امنیت */
    /* -------------------------------------------------------------- */

    public function test_security_settings_page_shows_captcha_drivers_and_ip_guard(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->get('/admin/settings/security')
            ->assertOk()
            ->assertSee('تصویری حرف‌عددی')
            ->assertSee('ریاضی پیشرفته')
            ->assertSee('hCaptcha')
            ->assertSee('Cloudflare Turnstile')
            ->assertSee('فرم‌های پرداخت فروشگاه')
            ->assertSee('sec_captcha_hcaptcha_site')
            ->assertSee('sec_captcha_turnstile_secret')
            ->assertSee('تعداد عملوند')
            ->assertSee('گارد IP پرداخت');
    }
}
