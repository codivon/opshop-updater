<?php

namespace App\Http\Middleware;

use App\Support\Features;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * اجبار نقش مدیر (وقتی «نقش‌های مدیر» در پنل امنیت و امکانات روشن باشد):
 * فقط کاربران با role=admin به صفحات /admin/* وارد می‌شوند؛ کاربر operator
 * با صفحهٔ 403 مواجه می‌شود.
 *
 * ⚠️ پیش‌فرض خاموش است — قبل از روشن‌کردن، نقش کاربر خودتان را در صفحهٔ
 * «کاربران» روی admin بگذارید (وگرنه پنل را از دست می‌دهید).
 */
class EnsureAdminRole
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Features::roles()) {
            return $next($request);
        }

        // ایمنی: اگر هیچ کاربر admin در سیستم نبود، بلاک نکن
        // (وگرنه با یک پیکربندی اشتباه، کل پنل برای همیشه قفل می‌شد)
        if (! \App\Models\User::query()->where('role', 'admin')->exists()) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user || $user->role !== 'admin') {
            abort(403, 'دسترسی به پنل مدیریت فقط برای نقش «مدیر» مجاز است.');
        }

        return $next($request);
    }
}
