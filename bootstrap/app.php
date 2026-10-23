<?php

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
        // درگاه‌های پرداخت بانکی با POST خارج از Laravel (بدون توکن CSRF) به کال‌بک برمی‌گردند
        $middleware->validateCsrfTokens(except: ['payment/callback', 'api/*']);

        // هدرهای امنیتی روی همهٔ پاسخ‌ها (وب + API): ضدکلیک‌جک، nosniff و…
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
