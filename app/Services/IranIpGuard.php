<?php

namespace App\Services;

use App\Support\Features;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * گارد IP پرداخت — «پرداخت‌ها باید از IP ایران انجام شود».
 *
 * اگر مشتری با فیلترشکن/VPN یا از IP خارج از ایران دکمهٔ پرداخت را بزند،
 * انتقال به درگاه انجام نمی‌شود و پیام واضح (مودال زیبا در صفحهٔ تسویه) می‌بیند.
 *
 * تشخیص با دو سرویس رایگان و بدون کلید:
 *   ۱) ip-api.com  — کشور + پرچم‌های proxy/hosting (فیلترشکن/دیتاسنتر)
 *   ۲) ipwho.is    — فال‌بک https (فقط کشور)
 *
 *  - IPهای خصوصی/لوکال (تست، localhost) همیشه مجازند
 *  - نتیجهٔ هر IP ۱۰ دقیقه کش می‌شود (سقف نرخ سرویس‌ها)
 *  - اگر هیچ سرویسی در دسترس نبود:
 *      حالت عادی → اجازه (fail-open؛ کسب‌وکار نباید بابت قطعیِ سرویس بیرونی بند شود)
 *      حالت سخت‌گیرانه (پنل) → بستن (fail-closed)
 */
class IranIpGuard
{
    /** طول کش نتیجهٔ هر IP (ثانیه) */
    public const CACHE_TTL = 600;

    /** کشورهای مجاز — فعلاً فقط ایران */
    protected const ALLOWED_COUNTRIES = ['IR'];

    /**
     * API استاتیک — برای فراخوانی ساده در کد تولیدی.
     * از کانتینر می‌گیرد تا در تست با swap جایگزین‌پذیر باشد.
     */
    public static function check(?string $ip = null): array
    {
        return app(static::class)->checkIp($ip);
    }

    /**
     * بررسی IP؛ خروجی قراردادی برای UI:
     *
     *   ok      → ادامهٔ پرداخت مجاز است
     *   reason  → پیام فارسی نمایشی (وقتی مسدود)
     *   detail  → کشور/پرچم‌ها برای لاگ و تست
     */
    public function checkIp(?string $ip = null): array
    {
        $ip = trim((string) ($ip ?? (string) request()->ip()));

        // بدون IP معتبر یا IP لوکال/خصوصی → گاردی برای قضاوت نیست؛ اجازه
        if ($ip === '' || ! static::isPublic($ip)) {
            return ['ok' => true, 'reason' => null, 'country' => null, 'vpn' => null, 'unknown' => false];
        }

        $key = 'ipguard:'.md5($ip);
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached;
        }

        $verdict = static::resolve($ip);

        Cache::put($key, $verdict, static::CACHE_TTL);

        return $verdict;
    }

    /** منطق اصلی تشخیص (بدون کش) — جدا برای تست */
    protected static function resolve(string $ip): array
    {
        $data = static::lookup($ip);

        // هیچ سرویسی در دسترس نبود
        if ($data === null) {
            if (Features::paymentIpGuardStrict()) {
                return [
                    'ok' => false,
                    'reason' => 'در این لحظه امکان راستی‌آزمایی IP شما نیست. برای ادامهٔ پرداخت، فیلترشکن (VPN) خود را خاموش کنید و دوباره تلاش کنید.',
                    'country' => null,
                    'vpn' => null,
                    'unknown' => true,
                ];
            }

            return ['ok' => true, 'reason' => null, 'country' => null, 'vpn' => null, 'unknown' => true];
        }

        $country = $data['country'] ?? null;
        $vpn = (bool) ($data['vpn'] ?? false);

        // IP خارج از ایران
        if ($country !== null && ! in_array($country, static::ALLOWED_COUNTRIES, true)) {
            return [
                'ok' => false,
                'reason' => 'پرداخت‌ها فقط از IP ایران پذیرفته می‌شود. لطفاً فیلترشکن (VPN) خود را خاموش کنید، صفحه را دوباره بارگذاری کنید و دوباره تلاش کنید.',
                'country' => $country,
                'vpn' => $vpn,
                'unknown' => false,
            ];
        }

        // IP ایران اما پرچم فیلترشکن/دیتاسنتر
        if ($vpn) {
            return [
                'ok' => false,
                'reason' => 'IP شما به‌عنوان فیلترشکن (VPN) یا سرور شناسایی شد. لطفاً فیلترشکن خود را خاموش کنید، صفحه را دوباره بارگذاری کنید و دوباره تلاش کنید.',
                'country' => $country,
                'vpn' => true,
                'unknown' => false,
            ];
        }

        return ['ok' => true, 'reason' => null, 'country' => $country, 'vpn' => false, 'unknown' => false];
    }

    /**
     * پرس‌وجو از سرویس‌های تشخیص؛ خروجی [country، vpn] یا null (قطعی کامل).
     *
     * @return array{country: ?string, vpn: bool}|null
     */
    protected static function lookup(string $ip): ?array
    {
        // ۱) ip-api.com — رایگان، بدون کلید، همراه پرچم‌های proxy/hosting
        try {
            $response = Http::connectTimeout(3)->timeout(6)
                ->get('http://ip-api.com/json/'.rawurlencode($ip), [
                    'fields' => 'status,message,countryCode,proxy,hosting,query',
                ]);

            if ($response->successful() && ($response->json('status') ?? '') === 'success') {
                return [
                    'country' => $response->json('countryCode'),
                    'vpn' => (bool) $response->json('proxy') || (bool) $response->json('hosting'),
                ];
            }
        } catch (\Throwable $e) {
            Log::debug('IranIpGuard: ip-api lookup failed', ['ip' => $ip, 'error' => $e->getMessage()]);
        }

        // ۲) ipwho.is — فال‌بک https؛ فقط کشور
        try {
            $response = Http::connectTimeout(3)->timeout(6)
                ->get('https://ipwho.is/'.rawurlencode($ip));

            if ($response->successful() && (bool) ($response->json('success') ?? false)) {
                return [
                    'country' => $response->json('country_code'),
                    'vpn' => false, // این سرویس پرچم VPN ندارد — فقط قضاوت کشور
                ];
            }
        } catch (\Throwable $e) {
            Log::debug('IranIpGuard: ipwho.is lookup failed', ['ip' => $ip, 'error' => $e->getMessage()]);
        }

        return null;
    }

    /** فقط IPهای عمومی قضاوت می‌شوند؛ خصوصی/رزروشده (تست/localhost) مجاز */
    protected static function isPublic(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
