<?php

namespace App\Http\Middleware;

use App\Support\Features;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * هدرهای امنیتی پایه روی همهٔ پاسخ‌ها (وب + API):
 * ضدکلیک‌جک (X-Frame-Options)، ضد MIME-sniffing، سیاست ارجاع و
 * قطع دسترسی سخت‌افزاری مرورگر (دوربین/میکروفون/موقعیت).
 *
 * هر دو کلید از پنل «امنیت و امکانات» قابل خاموش/روشن‌کردن‌اند:
 *   - sec_headers_enabled: کلید اصلی (پیش‌فرض روشن)
 *   - sec_headers_hsts: فقط هدر HSTS (پیش‌فرض روشن)
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! Features::headers()) {
            return $response;
        }

        // صفحهٔ سایت فقط در iframe همان دامنه می‌تواند باز شود (ضدکلیک‌جک)
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // مرورگر نوع فایل را حدس نزند (سرو HTML به‌جای عکس/فایل)
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // URL کامل صفحهٔ قبلی به سایت‌های بیرونی درز نکند
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // دسترسی سخت‌افزاری مرورگر برای این سایت خاموش شود
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // HTTPS اجباری در مرورگر پس از اولین بازدید HTTPS (روی HTTP نادیده گرفته می‌شود)
        if (Features::hsts()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=15552000');
        }

        return $response;
    }
}
