<?php

use App\Http\Middleware\CheckMaintenance;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SeparateAdminSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Task 93 — جداسازی کامل سشن مدیر از سشن فروشگاه/مشتری (کوکی‌های جدا):
        // باید قبل از StartSession اجرا شود تا نام کوکی از ابتدای درخواست درست باشد.
        $middleware->prepend(SeparateAdminSession::class);

        // Task 85 — امنیت (M9): اعتماد به پروکسی معکوس (سی‌پنل/انجین‌کس/کلاودفلر)
        // تا IP واقعی کاربر در rate-limiter و گاردهای IP درست باشد.
        $middleware->trustProxies(at: '*');

        // درگاه‌های پرداخت بانکی با POST خارج از Laravel (بدون توکن CSRF) به کال‌بک برمی‌گردند؛
        // Task 92: کال‌بک ممکن است شناسهٔ ارجاع را در سگمنت مسیر داشته باشد (payment/callback/{ref})
        $middleware->validateCsrfTokens(except: ['payment/callback', 'payment/callback/*', 'api/*']);

        // هدرهای امنیتی روی همهٔ پاسخ‌ها (وب + API): ضدکلیک‌جک، nosniff و…
        // (کلید اصلی + HSTS از پنل «امنیت و امکانات»)
        $middleware->append(SecurityHeaders::class);

        // حالت تعمیر و نگهداری — پنل/پرداخت/API همیشه باز می‌مانند
        $middleware->append(CheckMaintenance::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
