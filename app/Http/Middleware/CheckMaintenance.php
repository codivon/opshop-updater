<?php

namespace App\Http\Middleware;

use App\Support\Features;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * حالت تعمیر و نگهداری — وقتی در پنل (امنیت و امکانات) روشن باشد،
 * همهٔ صفحات عمومی پاسخ 503 با پیام قابل شخصی‌سازی می‌دهند.
 *
 * همیشه آزاد (حتی در حالت تعمیر):
 *   - پنل مدیریت + ورود/خروج مدیر (تا مدیر نتواند خودش را قفل کند)
 *   - کال‌بک درگاه پرداخت + مسیرهای پرداخت (تراکنش نیمه‌کاره نشود)
 *   - API v1 + دانلود عمومی get-update (سیستم‌های مشتریان نشکنند)
 *   - سرو فایل‌های uploads + refresh کپچا + health-check /up
 *   - کاربر واردشدهٔ وب (مدیر/اپراتور) — می‌تواند سایت را در حالت تعمیر ببیند
 */
class CheckMaintenance
{
    protected const EXEMPT = [
        'admin', 'admin/*',
        'login', 'logout', 'register', 'password/*',
        'api/*', 'payment/*', 'get-update/*',
        'uploads/*', 'captcha/*', 'livewire/*', 'up',
        'customer/login', 'customer/logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! Features::maintenance()) {
            return $next($request);
        }

        if ($request->is(self::EXEMPT)) {
            return $next($request);
        }

        // مدیر/اپراتور واردشده همیشه سایت را می‌بیند (ایمنی: قفل‌شدن ممکن نشود)
        if (auth('web')->check()) {
            return $next($request);
        }

        return response()->view('maintenance', [
            'message'  => Features::maintenanceMessage(),
            'siteName' => Features::siteName(),
        ], 503);
    }
}
