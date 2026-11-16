<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * سرویس مدیریت «کلید امضای لایسنس» (EC P-256) از داخل پنل — بدون نیاز به شِل/SSH.
 *
 * پیش‌زمینه: مدیرانِ روی هاست اشتراکی به artisan دسترسی ندارند و خطای
 *   «کلید امضای لایسنس (PACKAGE_LICENSE_SIGNING_KEY) تنظیم نشده است.»
 * فقط با ویرایش دستی .env قابل رفع بود. این سرویس همان کاری را که فرمان
 * license:generate-signing-keys انجام می‌دهد، داخل خودِ برنامه می‌کند:
 *   • ساخت جفت‌کلید EC P-256 با openssl (در همان پروسهٔ PHP)
 *   • نوشتن تک‌خطی کلید خصوصی در .env (فرمت \n — همان قرارداد فرمان)
 *   • پاک‌سازی کش پیکربندی (معادل artisan config:clear)
 *
 * نکتهٔ مهم: «منبع حقیقت» وضعیت، خودِ فایل .env است نه config() — چون در
 * همان درخواستی که .env نوشته می‌شود، کانفیگِ لودشده در بوت هنوز کهنه است
 * (حتی بعد از config:clear). برای همین status() مستقیم فایل را می‌خواند.
 *
 * امنیت: کلید خصوصی هرگز به خروجی/پراپرتی‌های Livewire نمی‌رود؛ فقط
 * وضعیت، اثرانگشت SHA-256 و کلید عمومی نمایش داده می‌شود.
 */
class LicenseSigningKeyService
{
    public const ENV_NAME = 'PACKAGE_LICENSE_SIGNING_KEY';

    /**
     * وضعیت جاری کلید.
     * state: missing (تنظیم نشده) | invalid (تنظیم اما نامعتبر) | ok (فعال)
     *
     * @return array{state:string, fingerprint:?string, public_key:?string, shop_env_value:?string, env_exists:bool, env_writable:bool, config_cached:bool, grace_hours:int, check_interval_hours:int}
     */
    public function status(): array
    {
        $pem        = $this->resolvePrivatePem();
        $state      = 'missing';
        $fingerprint = null;
        $publicKey  = null;

        if ($pem !== '') {
            $key = openssl_pkey_get_private($pem);

            if ($key !== false) {
                $state     = 'ok';
                $details   = openssl_pkey_get_details($key);
                $publicKey = (string) ($details['key'] ?? '');
                $fingerprint = $publicKey !== '' ? $this->fingerprint($publicKey) : null;
            } else {
                $state = 'invalid';
            }
        }

        return [
            'state'                => $state,
            'fingerprint'          => $fingerprint,
            'public_key'           => $publicKey,
            'shop_env_value'       => $publicKey !== '' ? self::shopEnvValue($publicKey) : null,
            'env_exists'           => is_file(base_path('.env')),
            'env_writable'         => is_file(base_path('.env')) ? is_writable(base_path('.env')) : is_writable(base_path()),
            'config_cached'        => is_file(base_path('bootstrap/cache/config.php')),
            'grace_hours'          => (int) config('services.license_envelope.grace_hours', 24),
            'check_interval_hours' => (int) config('services.license_envelope.check_interval_hours', 24),
        ];
    }

    /**
     * ساخت جفت‌کلید و ثبت در .env — با $force برای تعویض کلید موجود.
     *
     * @return array{ok:bool, message:string, public_key:?string, fingerprint:?string, shop_env_value:?string, config_cache_cleared:bool}
     */
    public function generate(bool $force = false): array
    {
        $fail = fn (string $message): array => [
            'ok' => false, 'message' => $message, 'public_key' => null, 'fingerprint' => null, 'shop_env_value' => null, 'config_cache_cleared' => false,
        ];

        if (! extension_loaded('openssl')) {
            return $fail('اکستنشن openssl روی PHP فعال نیست — از فرمان artisan license:generate-signing-keys استفاده کنید.');
        }

        $path = base_path('.env');

        if (! is_file($path)) {
            return $fail('فایل .env پیدا نشد در: '.$path);
        }

        if (! is_writable($path)) {
            return $fail('فایل .env قابل‌نوشتن نیست — مجوز (permission) فایل را از File Manager هاست بررسی کنید.');
        }

        if ($this->currentEnvRaw() !== '' && ! $force) {
            return $fail('کلید قبلاً تنظیم شده است؛ برای تعویض از دکمهٔ «تعویض کلید» استفاده کنید.');
        }

        // ساخت جفت‌کلید — دقیقاً مطابق فرمان license:generate-signing-keys
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name'       => 'prime256v1',
        ]);

        if ($key === false) {
            return $fail('ساخت کلید ناموفق بود ('.$this->opensslLastError().').');
        }

        $privatePem = '';

        if (! openssl_pkey_export($key, $privatePem)) {
            return $fail('خروجی گرفتن کلید خصوصی ناموفق بود ('.$this->opensslLastError().').');
        }

        $details   = openssl_pkey_get_details($key);
        $publicPem = (string) ($details['key'] ?? '');

        // نوشتن در .env — مقدار تک‌خطی با دنباله‌های literal \n (سرویس امضا نرمال‌سازی می‌کند)
        $envLine = self::ENV_NAME.'="'.str_replace("\n", '\\n', $privatePem).'"';
        $content = (string) file_get_contents($path);

        if (preg_match('/^'.preg_quote(self::ENV_NAME, '/').'=.*$/m', $content)) {
            // جایگزینی خط موجود — با callback تا محتوای envLine هرگز به‌عنوان
            // الگوی جایگزینی preg تفسیر نشود (بک‌اسلش‌های \n محفوظ می‌مانند)
            $content = (string) preg_replace_callback(
                '/^'.preg_quote(self::ENV_NAME, '/').'.*$/m',
                fn (): string => $envLine,
                $content
            );
        } else {
            $content = rtrim($content, "\n")."\n\n# --- پاکت لایسنس امضاشده (سخت‌سازی امنیتی فاز ۱) ---\n".$envLine."\n";
        }

        if (@file_put_contents($path, $content, LOCK_EX) === false) {
            return $fail('نوشتن .env ناموفق بود — مجوز فایل یا فضای هاست را بررسی کنید.');
        }

        // معادل artisan config:clear — بدون شِل
        $cacheCleared = $this->clearConfigCache();

        // پاک‌سازی امضاهای مانیفستِ کش‌شده با کلید قدیمی — فروشگاه‌ها باید
        // امضای تازه با کلید جدید بگیرند نه امضای کهنهٔ کش (driver دیتابیس)
        $manifestCacheFlushed = $this->flushManifestSignatureCache();

        Log::warning('License signing key '.($force ? 'ROTATED' : 'generated').' from admin panel', [
            'fingerprint'              => $this->fingerprint($publicPem),
            'config_cache_cleared'     => $cacheCleared,
            'manifest_cache_flushed'   => $manifestCacheFlushed,
        ]);

        return [
            'ok'                   => true,
            'message'              => $force
                ? 'کلید با موفقیت تعویض شد — قبل از فعال‌شدنِ فروشگاه‌ها، کلید عمومی جدید را در .env هر فروشگاه (PACKAGE_LICENSE_PUBLIC_KEY) ثبت کنید.'
                : 'کلید با موفقیت ساخته و در .env ثبت شد.',
            'public_key'           => $publicPem,
            'fingerprint'          => $this->fingerprint($publicPem),
            'shop_env_value'       => self::shopEnvValue($publicPem),
            'config_cache_cleared' => $cacheCleared,
        ];
    }

    /**
     * پاک‌سازی کش پیکربندی از داخل برنامه (معادل artisan config:clear) —
     * برای وقتی مدیر .env را دستی ویرایش کرده و شِل ندارد.
     */
    public function clearConfigCache(): bool
    {
        try {
            Artisan::call('config:clear');

            return true;
        } catch (\Throwable $e) {
            Log::warning('License signing key: config:clear failed from panel', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * بهترین‌تلاش برای حذف مانیفست‌های امضاشدهٔ کش‌شده با کلید قبلی.
     *
     * درایور پیش‌فرض کش دیتابیس است (پشتیبانی از tags ندارد) — مستقیم جدول
     * cache خالی می‌شود. برای درایورهای دیگر، هرگز نباید چرخش کلید را بشکند؛
     * فقط لاگ می‌شود (کلیدِ کشِ مانیفست شامل شناسهٔ کلید امضاست —
     * PackageLicenseEnvelopeService::signingKeyId — پس مدخل‌های کهنه به‌هرحال
     * دیگر سرو نمی‌شوند؛ این پاک‌سازی صرفاً بهداشتی است).
     */
    private function flushManifestSignatureCache(): bool
    {
        try {
            if ((string) config('cache.default') === 'database') {
                DB::table((string) config('cache.database.table', 'cache'))
                    ->where('key', 'like', 'license_envelope.manifest.%')
                    ->delete();

                return true;
            }

            return false;
        } catch (\Throwable $e) {
            Log::warning('License signing key: manifest signature cache flush failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * خطِ آمادهٔ .env برای فروشگاه (shop-pro-laravel-13) — کلید عمومیِ متناظر با
     * کلید خصوصی این سرور، تک‌خطی با دنبالهٔ \n. مدیرِ فروشگاه این خط را در
     * .env فروشگاه می‌گذارد و bootstrap/cache/config.php را حذف می‌کند؛ از آن
     * پس امضاهای این سرور در فروشگاه تأیید می‌شوند. بدون این کار، تعویض کلید
     * همهٔ پکیج‌های نصب‌شده را در فروشگاه می‌بندد (fail-closed).
     */
    public static function shopEnvValue(string $publicPem): string
    {
        $singleLine = str_replace("\n", '\\n', trim($publicPem));

        return 'PACKAGE_LICENSE_PUBLIC_KEY="'.$singleLine.'"';
    }

    /* ------------------------------------------------------------------ */
    /*  Private helpers                                                    */
    /* ------------------------------------------------------------------ */

    /** خواندن مقدار خام خط کلید از .env — مستقل از کش پیکربندی */
    private function currentEnvRaw(): string
    {
        $path = base_path('.env');

        if (! is_file($path)) {
            return '';
        }

        preg_match('/^'.preg_quote(self::ENV_NAME, '/').'=(.*)$/m', (string) file_get_contents($path), $m);

        return trim(trim((string) ($m[1] ?? '')), "\"'");
    }

    /**
     * نرمال‌سازی مقدار کلید — همان قرارداد سه‌فرمتی سرویس پاکت
     * (PackageLicenseEnvelopeService::resolvePrivateKeyPem):
     *   ۱) PEM چندخطی داخل کوتیشن  ۲) تک‌خطی با دنباله‌های \n  ۳) کوتیشن اضافه
     */
    private function resolvePrivatePem(): string
    {
        $pem = $this->currentEnvRaw();

        if ($pem === '') {
            $pem = trim((string) config('services.license_envelope.private_key', ''));
        }

        $pem = trim($pem);

        if ($pem === '') {
            return '';
        }

        // اگر به‌اشتباه کلاً داخل کوتیشن مانده باشد، جدا می‌کنیم (۳۴="، ۳۹=')
        if (strlen($pem) > 1) {
            $first = ord($pem[0]);
            $last  = ord($pem[strlen($pem) - 1]);

            if (($first === 34 && $last === 34) || ($first === 39 && $last === 39)) {
                $pem = trim(substr($pem, 1, -1));
            }
        }

        // دنباله‌های literal \n → newline واقعی (PEM چندخطی استاندارد)
        return trim(str_replace('\\n', "\n", $pem));
    }

    /** اثرانگشت SHA-256 کلید عمومی (اولین ۱۶ رقم hex چهارچارگاهی) — نمایش بدون افشای کلید خصوصی */
    private function fingerprint(string $publicPem): string
    {
        if (preg_match('/-----BEGIN [A-Z ]+-----\s*(.*?)\s*-----END [A-Z ]+-----/s', $publicPem, $m)) {
            $der = base64_decode((string) preg_replace('/\s+/', '', $m[1]), true);

            if ($der !== false && $der !== '') {
                return implode(':', str_split(substr(hash('sha256', $der), 0, 16), 4));
            }
        }

        return '';
    }

    private function opensslLastError(): string
    {
        $messages = [];

        while (($m = openssl_error_string()) !== false) {
            $messages[] = $m;
        }

        return implode(' | ', array_slice($messages, 0, 3)) ?: 'خطای ناشناختهٔ openssl';
    }
}
