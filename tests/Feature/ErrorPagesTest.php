<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * صفحات خطای مدرن (Task 73) — ۴۰۳/۴۰۴/۵۰۰/۵۰۳ + ۴۰۱/۴۱۹/۴۲۹:
 *
 * - طراحی فارسی RTL مستقل (بدون وابستگی به لایوت/بیلد) با کد خطا، عنوان و
 *   پیام فارسی + دکمه‌های بازگشت
 * - ۴۰۳ پیام abort را نشان می‌دهد (مثل پیام «تنظیمات برنامه‌نویس»)
 * - ۴۰۴ مسیر ناموجود
 * - ۵۰۰: صفحهٔ خطا با abort(500) ساخته می‌شود (در محیط test debug خاموش است
 *   پس صفحهٔ اختصاصی رندر می‌شود نه صفحهٔ دیباگ)
 * - ۵۰۳: حالت تعمیر با artisan down (استور down فقط فایل است) — بعد از تست
 *   حتماً up می‌شود.
 */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // مسیرهای آزمون ۴۰۳/۵۰۰ — بیرون از گروه‌های پروژه
        Route::get('/forbidden-probe', fn () => abort(403, 'این مسیر مخصوص آزمون ۴۰۳ است.'));
        Route::get('/boom-500', fn () => abort(500));
    }

    public function test_404_renders_modern_persian_page(): void
    {
        $this->get('/this-page-does-not-exist-404')
            ->assertStatus(404)
            ->assertSee('صفحه پیدا نشد')
            ->assertSee('۴۰۴')
            ->assertSee('بازگشت به صفحهٔ اصلی')
            ->assertSee('آپدیت‌سنتر')
            ->assertSee('dir="rtl"', false);
    }

    public function test_403_renders_with_abort_message(): void
    {
        $this->get('/forbidden-probe')
            ->assertStatus(403)
            ->assertSee('۴۰۳')
            ->assertSee('دسترسی غیرمجاز')
            ->assertSee('این مسیر مخصوص آزمون ۴۰۳ است.');
    }

    public function test_500_renders_modern_page(): void
    {
        $this->get('/boom-500')
            ->assertStatus(500)
            ->assertSee('۵۰۰')
            ->assertSee('خطای غیرمنتظره');
    }

    public function test_419_renders_persian_page(): void
    {
        // صفحهٔ ۴۱۹ مستقیماً رندر می‌شود (رفتار middleware CSRF شبیه‌سازی می‌شود)
        $view = $this->view('errors.419');

        $view->assertSee('۴۱۹')
            ->assertSee('صفحه منقضی شد');
    }

    public function test_503_maintenance_down_renders_persian_page(): void
    {
        $this->artisan('down', ['--retry' => 5]);

        try {
            $this->get('/')
                ->assertStatus(503)
                ->assertSee('۵۰۳')
                ->assertSee('به‌زودی برمی‌گردیم');
        } finally {
            $this->artisan('up');
        }

        // بعد از up، سایت برمی‌گردد
        $this->get('/')
            ->assertOk();
    }

    public function test_401_and_429_render_persian_pages(): void
    {
        $this->view('errors.401')->assertSee('۴۰۱')->assertSee('ابتدا وارد شوید');
        $this->view('errors.429')->assertSee('۴۲۹')->assertSee('تعداد درخواست‌ها زیاد است');
    }

    /** استایل حیاتی صفحات خطا باید درون خود صفحه باشد (بدون وابستگی به بیلد) */
    public function test_error_page_is_self_contained(): void
    {
        $html = view('errors.404')->render();

        $this->assertStringContainsString('<style>', $html);
        $this->assertStringNotContainsString('@vite', $html);
        $this->assertStringContainsString('fonts/Vazirmatn', $html);
    }
}
