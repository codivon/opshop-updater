<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Task 85 — گارد نقش مدیر: فقط کاربران با role=admin به صفحات /admin/* وارد می‌شوند؛
 * کاربر operator با صفحهٔ 403 مواجه می‌شود.
 *
 * تغییر مهم نسبت به نسخهٔ قبلی: این میدلور دیگر «fail-open» نیست —
 * قبل از آن، وقتی قابلیت نقش‌ها خاموش بود، هر کاربر احراز هویت‌شده‌ای
 * به کل پنل (بکاپ‌ها، لایسنس‌ها، لاگ‌ها، مالی) دسترسی داشت.
 *
 * تنها استثنا (ضد قفل‌شدگی): اگر در کل سیستم هیچ کاربر admin وجود نداشته باشد،
 * قدیمی‌ترین حساب (اولین کاربر = مالک سایت) به‌عنوان مدیر شناسایی می‌شود.
 */
class EnsureAdminRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'برای دسترسی به پنل مدیریت باید وارد شوید.');
        }

        // ایمنی ضد قفل‌شدگی: اگر هیچ admin واقعی وجود نداشته باشد،
        // اولین حسابِ ساخته‌شده (مالک) مدیر در نظر گرفته می‌شود — نه هر کاربر دلخواه.
        $isAdmin = $user->role === 'admin';

        if (! $isAdmin) {
            $firstUser = User::query()->orderBy('id')->first();

            $isAdmin = $firstUser !== null
                && $firstUser->id === $user->id
                && ! User::query()->where('role', 'admin')->exists();
        }

        if (! $isAdmin) {
            abort(403, 'دسترسی به پنل مدیریت فقط برای نقش «مدیر» مجاز است.');
        }

        return $next($request);
    }
}
