<?php

namespace Tests\Feature;

use App\Livewire\Shop\CustomerLogin;
use App\Models\Setting;
use App\Services\CaptchaImageRenderer;
use App\Services\CaptchaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * کپچای تصویری (Task 70) — سؤال امنیتی به‌جای متنِ HTML به‌صورت PNG با نویز
 * سرو می‌شود تا اسکریپت‌ها نتوانند با regex سؤال را استخراج و حل کنند.
 *
 * پوشش: اندپوینت تصویر (PNG/no-store/عدم مصرف سؤال)، شمارندهٔ نسخه
 * (cache-busting)، روت refresh، نبودِ متن سؤال در HTML صفحهٔ ورود،
 * سازگاری verify پس از دریافت تصویر و تازه‌سازی Livewire.
 */
class CaptchaImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // تلهٔ زمان را در تست‌ها خاموش می‌کنیم (سؤال و پاسخ بی‌درنگ قابل مصرف)
        Setting::set('sec_captcha_min_seconds', '0', 'string', 'security');
        Cache::forget('setting:sec_captcha_min_seconds');
    }

    /* -------------------------------------------------------------- */
    /*  اندپوینت تصویر */
    /* -------------------------------------------------------------- */

    public function test_captcha_image_endpoint_returns_real_png_with_no_store(): void
    {
        $response = $this->get(route('captcha.image'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        // فریم‌ورک هدر Cache-Control را نرمال‌سازی می‌کند — نبود در کش مهم است
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $png = (string) $response->getContent();

        // امضای واقعی PNG + قابل رمزگشایی با GD
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($png, 0, 8));
        $info = getimagesizefromstring($png);
        $this->assertIsArray($info);
        $this->assertSame('image/png', $info['mime']);
        $this->assertGreaterThan(1, $info[0]); // عرض تصویر
    }

    public function test_image_endpoint_creates_question_when_session_is_empty(): void
    {
        $this->assertNull(session(CaptchaService::SESSION_ANSWER));

        $this->get(route('captcha.image'))->assertOk();

        $this->assertIsNumeric(session(CaptchaService::SESSION_ANSWER));
        $this->assertNotSame('', session(CaptchaService::SESSION_QUESTION));
        $this->assertSame(1, CaptchaService::version()); // سؤال نو = نسخه ۱
    }

    public function test_image_fetch_does_not_regenerate_or_consume_the_question(): void
    {
        CaptchaService::fresh();
        $answer = session(CaptchaService::SESSION_ANSWER);
        $version = CaptchaService::version();

        // دو دریافت تصویر پشت‌سرهم — سؤال و نسخه نباید تغییر کنند
        $this->get(route('captcha.image'))->assertOk();
        $this->get(route('captcha.image'))->assertOk();

        $this->assertSame($answer, session(CaptchaService::SESSION_ANSWER));
        $this->assertSame($version, CaptchaService::version());
    }

    public function test_verify_still_works_after_fetching_the_image(): void
    {
        CaptchaService::fresh();
        $answer = (string) session(CaptchaService::SESSION_ANSWER);

        $this->get(route('captcha.image'))->assertOk();

        $this->assertTrue(CaptchaService::verify($answer));
    }

    /* -------------------------------------------------------------- */
    /*  شمارندهٔ نسخه (cache-busting) و تازه‌سازی */
    /* -------------------------------------------------------------- */

    public function test_version_increments_on_every_fresh_question(): void
    {
        CaptchaService::fresh();
        $v1 = CaptchaService::version();

        CaptchaService::fresh();

        $this->assertSame($v1 + 1, CaptchaService::version());
    }

    public function test_refresh_route_bumps_version(): void
    {
        CaptchaService::fresh();
        $version = CaptchaService::version();

        $this->get(route('captcha.refresh'))->assertRedirect();

        $this->assertSame($version + 1, CaptchaService::version());
    }

    public function test_livewire_refresh_captcha_bumps_version_and_keeps_component_alive(): void
    {
        $before = CaptchaService::version();

        Livewire::test(CustomerLogin::class)
            ->call('refreshCaptcha')
            ->assertOk();

        // mount هم سؤال تازه می‌سازد + یک تازه‌سازی صریح — حداقل یک نسخه جلو
        $this->assertGreaterThan($before, CaptchaService::version());
    }

    /* -------------------------------------------------------------- */
    /*  سؤال دیگر در HTML لو نمی‌رود */
    /* -------------------------------------------------------------- */

    public function test_login_page_shows_image_and_never_leaks_the_question_text(): void
    {
        CaptchaService::fresh();
        $question = (string) session(CaptchaService::SESSION_QUESTION);

        $response = $this->get('/login');

        $response->assertOk()
            ->assertSee('captcha/image');          // تصویر سرو می‌شود

        // سؤال متنی دیگر در HTML نیست — ربات نمی‌تواند با regex بخواندش
        $this->assertStringNotContainsString($question, $response->getContent());
        $this->assertStringNotContainsString('= ؟', $response->getContent());
    }

    public function test_customer_login_page_shows_image_captcha(): void
    {
        $this->get(route('shop.login'))
            ->assertOk()
            ->assertSee('captcha/image');
    }

    /* -------------------------------------------------------------- */
    /*  رندرکننده */
    /* -------------------------------------------------------------- */

    public function test_renderer_produces_distinct_pixels_for_same_question(): void
    {
        $a = CaptchaImageRenderer::render('۴ + ۳');
        $b = CaptchaImageRenderer::render('۴ + ۳');

        $this->assertStringStartsWith("\x89PNG", $a);
        $this->assertStringStartsWith("\x89PNG", $b);

        // نویز تصادفی → دو خروجی برای یک سؤال باید از نظر بایت متفاوت باشند
        $this->assertNotSame($a, $b);
    }
}
