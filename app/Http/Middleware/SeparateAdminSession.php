<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * جداسازی کامل سشن ورود مدیر از سشن فروشگاه/مشتریان (درخواست کاربر — Task 93).
 *
 * مشکل: مدیر و مشتری هر دو روی یک کوکی سشن مشترک (`laravel-session`) بودند؛ هر
 * تخریبی که در یک سمت رخ می‌داد (خروج مدیر با session()->invalidate()،
 * cache:clear روی هاست‌هایی که سشن را روی استور کش می‌گذارند، یا هر خطای
 * نامشخص دیگر) سمت دیگر را هم بیرون می‌انداخت.
 *
 * راه‌حل: قبل از StartSession، بسته به «مبدأ درخواست» نام کوکی سشن عوض می‌شود:
 *   - صفحات پنل مدیریت (/admin/*) + ورود/خروج مدیر (/login, /logout) → کوکی مخصوص پنل
 *   - فروشگاه، پنل مشتری، پرداخت و بقیهٔ سایت → کوکی مخصوص فروشگاه
 *
 * درخواست‌های Livewire (`/livewire/*`) از هر دو سمت می‌آیند؛ مبدأ به ترتیب از
 * روی `snapshot.memo.path` (آدرس صفحه‌ای که کامپوننت روی آن رندر شده — دقیق و
 * قطعی) و در نبود آن از هدر Referer (همهٔ درخواست‌های same-origin آدرس کامل
 * می‌فرستند) تشخیص داده می‌شود.
 *
 * نتیجه: خروج مدیر، تغییر تنظیمات یا پاک‌سازی کش دیگر هیچ اثری روی لاگینِ
 * مشتری در همان مرورگر ندارد و برعکس. یک‌بارمصرف: پس از استقرار، همهٔ کاربران
 * (مدیر و مشتری) یک بار دوباره وارد می‌شوند چون کوکی‌های قدیمی کنار گذاشته می‌شوند.
 */
class SeparateAdminSession
{
    /** نام کوکی سشن سمت پنل مدیریت */
    public const ADMIN_COOKIE = 'opshop-admin-session';

    /** نام کوکی سشن سمت فروشگاه/پنل مشتری */
    public const SHOP_COOKIE = 'opshop-shop-session';

    public function handle(Request $request, Closure $next): Response
    {
        config(['session.cookie' => $this->cookieNameFor($request)]);

        return $next($request);
    }

    /** نام کوکی سشن مناسب این درخواست */
    private function cookieNameFor(Request $request): string
    {
        $path = trim($request->path(), '/');

        // صفحات خود پنل مدیریت + ورود/خروج مدیر (laravel/ui)
        if ($this->isAdminPath($path)) {
            return self::ADMIN_COOKIE;
        }

        // درخواست‌های Livewire از هر دو سمت می‌آیند — تشخیص از روی صفحهٔ مبدأ
        if ($this->isLivewireEndpoint($path)) {
            return $this->isAdminPath($this->livewireOriginPath($request) ?? '')
                ? self::ADMIN_COOKIE
                : self::SHOP_COOKIE;
        }

        // اندپوینت کپچا بین پنل و فروشگاه مشترک است؛ سؤال/پاسخ در سشن ذخیره می‌شود
        // پس باید همان سشنِ صفحهٔ مبدأ باشد — کامپوننت x-captcha پارامتر ctx را می‌فرستد
        if ($path === 'captcha/image' || $path === 'captcha/refresh') {
            $ctx = (string) $request->query('ctx', '');

            if ($ctx === 'admin') {
                return self::ADMIN_COOKIE;
            }

            if ($ctx === 'shop') {
                return self::SHOP_COOKIE;
            }

            // فال‌بک: Referer صفحهٔ مبدأ (تصاویر same-origin آدرس کامل می‌فرستند)
            return $this->isAdminPath($this->refererPath($request) ?? '')
                ? self::ADMIN_COOKIE
                : self::SHOP_COOKIE;
        }

        return self::SHOP_COOKIE;
    }

    /** آیا این مسیر مربوط به پنل مدیریت است؟ */
    private function isAdminPath(string $path): bool
    {
        $path = trim($path, '/');

        if ($path === 'login' || $path === 'logout') {
            return true;
        }

        // مسیرهای بازیابی گذرواژهٔ laravel/ui هم فقط مخصوص مدیر هستند (مشتری رمز ندارد)
        if ($path === 'password' || $path === 'password/reset' || str_starts_with($path, 'password/')) {
            return true;
        }

        return $path === 'admin' || str_starts_with($path, 'admin/');
    }

    /** آیا این درخواست به اندپوینت‌های مشترک Livewire می‌رسد؟ */
    private function isLivewireEndpoint(string $path): bool
    {
        return $path === 'livewire/update'
            || $path === 'livewire/upload-file'
            || $path === 'livewire/preview-file';
    }

    /**
     * مسیر صفحهٔ مبدأ درخواست Livewire:
     *  ۱) snapshot کامپوننت — memo.path آدرس صفحه‌ای است که کامپوننت روی آن رندر شده.
     *  ۲) هدر Referer — برای درخواست‌های same-origin مرورگر آدرس کامل می‌فرستد.
     */
    private function livewireOriginPath(Request $request): ?string
    {
        // ۱) اندپوینت update — بدنه JSON با snapshot های رمزگذاری‌شدهٔ دومرحله‌ای
        if ($request->isJson()) {
            $payload = json_decode((string) $request->getContent(), true);

            foreach ((array) ($payload['components'] ?? []) as $component) {
                $snapshot = $component['snapshot'] ?? null;

                if (is_string($snapshot)) {
                    $snapshot = json_decode($snapshot, true);
                }

                $pagePath = (string) ((is_array($snapshot) ? $snapshot['memo']['path'] : '') ?? '');

                if ($pagePath !== '') {
                    return $pagePath;
                }
            }
        }

        // ۲) آپلود/پیش‌نمایش فایل (multipart یا GET بدون snapshot) — Referer
        return $this->refererPath($request);
    }

    /** مسیر صفحهٔ مبدأ از هدر Referer (اگر موجود باشد) */
    private function refererPath(Request $request): ?string
    {
        $referer = (string) $request->header('referer', '');

        if ($referer === '') {
            return null;
        }

        $path = (string) parse_url($referer, PHP_URL_PATH);

        return $path !== '' ? $path : null;
    }
}
