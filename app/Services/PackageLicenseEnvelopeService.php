<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Package;
use App\Models\PackageLicense;
use App\Models\PackageVersion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * سرویس پاکت لایسنس امضاشده (Signed License Envelope) — سخت‌سازی امنیتی فاز ۱
 * ==================================================================
 *
 * مشکل ریشه‌ای قبلی: کلاینت (پروژهٔ فروشگاه) فقط یک پاسخ JSON ساده از
 * verify-license می‌گرفت و تاریخ محلی دیتابیس را ملاک قرار می‌داد؛ نتیجه:
 * با قطع سرور بازار (یا دستکاری دیتابیس فروشگاه) پکیج‌ها به‌کار ادامه می‌دادند.
 *
 * راه‌حل: سرور بازار برای هر واکنش verify-license یک «پاکت» می‌سازد و با
 * کلید خصوصی EC P-256 امضا می‌کند. کلاینت با کلید عمومیِ جاسازی‌شده در
 * کُد خود، امضا را راستی‌آزمایی می‌کند؛ بنابراین:
 *   • دستکاری پاسخ در مسیر شبکه (بدون کلید خصوصی) ناممکن است.
 *   • نقطهٔ حقیقت برای انقضا/باطل‌شدن/دامنه، پاسخ امضاشدهٔ سرور است — نه دیتابیس فروشگاه.
 *   • پاکت در سمت کلاینت ذخیره می‌شود و در هر boot دوباره اعتبارسنجی امضای آن انجام می‌شود.
 *
 * همین سرویس «مانیفست یکپارچگی فایل‌ها» (module.lock) را هم از روی ZIP نسخهٔ
 * پکیج می‌سازد و امضا می‌کند: SHA-256 تک‌تک فایل‌های پکیج (شامل PHP، Blade، CSS،
 * JS و config). حذف یا تغییر حتی یک فایل در هاست فروشگاه = شکست راستی‌آزمایی.
 *
 * کلید خصوصی: env PACKAGE_LICENSE_SIGNING_KEY (PEM کامل EC PRIVATE KEY)
 * کلید عمومی در کد کلاینت جاسازی می‌شود.
 */
class PackageLicenseEnvelopeService
{
    private SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /** نسخهٔ قالب پاکت — تغییر ساختار در آینده این عدد را بالا می‌برد */
    public const ENVELOPE_VERSION = 1;

    /** مهلت پیش‌فرض تمدید خطا (ساعت) — کلاینت تا این مدت در وضعیت «مهلت» می‌ماند */
    public const DEFAULT_GRACE_HOURS = 24;

    /** فاصلهٔ پیش‌فرض چک دوره‌ای (ساعت) */
    public const DEFAULT_CHECK_INTERVAL_HOURS = 24;

    /* ===================================================================
     *  امضا و راستی‌آزمایی
     * =================================================================== */

    /**
     * امضای پاکت با کلید خصوصی EC — خروجی: signature (base64)
     *
     * @param  array<string, mixed>  $payload
     */
    public function sign(array $payload): string
    {
        $privateKeyPem = $this->resolvePrivateKeyPem();

        if ($privateKeyPem === '') {
            throw new RuntimeException('کلید امضای لایسنس (PACKAGE_LICENSE_SIGNING_KEY) تنظیم نشده است.');
        }

        $key = openssl_pkey_get_private($privateKeyPem);

        if ($key === false) {
            throw new RuntimeException('کلید خصوصی امضای لایسنس نامعتبر است.');
        }

        $signature = '';

        if (! openssl_sign($this->canonicalJson($payload), $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('امضای پاکت لایسنس ناموفق بود.');
        }

        return base64_encode($signature);
    }

    /**
     * خواندن کلید خصوصی از config/env با نرمال‌سازی فرمت.
     *
     * سه شکل مقدار در .env پذیرفته می‌شود:
     *   1) PEM چندخطی داخل کوتیشن (dotenv چندخطی را ساپورت می‌کند)
     *   2) PEM تک‌خطی با دنباله‌های literal \\n (مطابق .env.example)
     *   3) همان مقدار به‌اشتباه در کوتیشن پیچیده‌شده
     * همه به PEM استاندارد با newline واقعی تبدیل می‌شوند تا openssl بپذیرد.
     */
    private function resolvePrivateKeyPem(): string
    {
        $pem = (string) config('services.license_envelope.private_key', (string) env('PACKAGE_LICENSE_SIGNING_KEY', ''));

        $pem = trim($pem);

        if ($pem === '') {
            return '';
        }

        // اگر به‌اشتباه کلاً داخل کوتیشن مانده باشد، جدا می‌کنیم
        // کد کاراکترها: 34 = دابل‌کوتیشن (")، 39 = آپاستروف (')
        if (strlen($pem) > 1) {
            $first = ord($pem[0]);
            $last  = ord($pem[strlen($pem) - 1]);

            if (($first === 34 && $last === 34) || ($first === 39 && $last === 39)) {
                $pem = substr($pem, 1, -1);
                $pem = trim($pem);
            }
        }

        // مقدار تک‌خطی .env: دنباله‌های literal \n / \r به newline واقعی تبدیل شوند
        if (! str_contains($pem, "\n")) {
            $pem = str_replace(['\\r\\n', '\\n', '\\r'], ["\r\n", "\n", "\n"], $pem);
        }

        return $pem;
    }

    /**
     * JSON کانونیکی — ksort بازگشتی + بدون escape — برای تطبیق بایت‌به‌بایت
     * بین سرور و کلاینت.
     *
     * @param  array<string, mixed>  $payload
     */
    public function canonicalJson(array $payload): string
    {
        $this->deepSort($payload);

        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function deepSort(array &$array): void
    {
        ksort($array);

        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->deepSort($value);
            }
        }
    }

    /* ===================================================================
     *  ساخت پاکت لایسنس
     * =================================================================== */

    /**
     * پاکت امضاشدهٔ لایسنس برای یک خریدار/پکیج.
     *
     * قرارداد مهم: روی هر دو مسیر «معتبر» و «نامعتبر» پاکت امضاشده با HTTP 200
     * برمی‌گردد تا کلاینت بتواند حکم قطعیِ امضاشده دریافت کند؛ خطاهای احراز
     * (توکن/دامنهٔ غلط) همچنان 4xx بدون پاکت هستند و کلاینت آن‌ها را «رد قطعی
     * سرور» می‌داند.
     *
     * @return array{envelope: array<string,mixed>, signature: string}
     */
    public function buildForLicense(PackageLicense $license, Customer $customer, Package $package, ?string $requestDomain): array
    {
        $licenseService = app(LicenseService::class);
        $verify         = $licenseService->verify($license);

        $valid = (bool) ($verify['valid'] ?? false);

        // پوشش اشتراک طرح‌محور — همان منطق listPackages
        $subscription = null;
        $coveringSub  = $this->subscriptionService->activeSubscriptionCovering($customer, $package);

        if ($coveringSub) {
            $subscription = [
                'covered'                 => true,
                'plan_name'               => $coveringSub->plan?->name,
                'plan_slug'               => $coveringSub->plan?->slug,
                'subscription_expires_at' => $coveringSub->expires_at?->toDateTimeString(),
            ];
        }

        // اگر لایسنسِ تکی نامعتبر است ولی اشتراکِ فعال این پکیج را می‌پوشاند،
        // کلاینت باید بداند پکیج هنوز مجاز است (منبع حقیقت = سرور).
        $effectiveValid = $valid || $coveringSub !== null;

        $payload = [
            'v'                    => self::ENVELOPE_VERSION,
            'slug'                 => $package->slug,
            'license_key'          => $license->license_key,
            'customer_id'          => $customer->id,
            'domain'               => $this->normalizeDomain($requestDomain ?? ''),
            'valid'                => $effectiveValid,
            'license_valid'        => $valid,
            'status'               => $license->status,
            'reason'               => $effectiveValid ? null : (string) ($verify['message'] ?? 'لایسنس معتبر نیست.'),
            'expires_at'           => $license->expires_at?->toDateTimeString(),
            'days_remaining'       => $verify['days_remaining'] ?? null,
            'is_unlimited'         => $license->isUnlimited(),
            'subscription'         => $subscription,
            'grace_hours'          => (int) max(1, (int) config('services.license_envelope.grace_hours', self::DEFAULT_GRACE_HOURS)),
            'check_interval_hours' => (int) max(1, (int) config('services.license_envelope.check_interval_hours', self::DEFAULT_CHECK_INTERVAL_HOURS)),
            'issued_at'            => now()->toIso8601String(),
        ];

        return [
            'envelope'  => $payload,
            'signature' => $this->sign($payload),
        ];
    }

    /**
     * پاکت امضاشدهٔ «رد قطعی» — برای وقتی اصلاً لایسنی وجود ندارد ولی احراز
     * انجام شده (مثلاً کلید غلط) تا کلاینت حکم امضاشده داشته باشد.
     *
     * @return array{envelope: array<string,mixed>, signature: string}
     */
    public function buildRejection(Customer $customer, Package $package, ?string $licenseKey, string $reason, ?string $requestDomain): array
    {
        $payload = [
            'v'                    => self::ENVELOPE_VERSION,
            'slug'                 => $package->slug,
            'license_key'          => (string) $licenseKey,
            'customer_id'          => $customer->id,
            'domain'               => $this->normalizeDomain($requestDomain ?? ''),
            'valid'                => false,
            'license_valid'        => false,
            'status'               => 'not_found',
            'reason'               => $reason,
            'expires_at'           => null,
            'days_remaining'       => null,
            'is_unlimited'         => false,
            'subscription'         => null,
            'grace_hours'          => (int) max(1, (int) config('services.license_envelope.grace_hours', self::DEFAULT_GRACE_HOURS)),
            'check_interval_hours' => (int) max(1, (int) config('services.license_envelope.check_interval_hours', self::DEFAULT_CHECK_INTERVAL_HOURS)),
            'issued_at'            => now()->toIso8601String(),
        ];

        return [
            'envelope'  => $payload,
            'signature' => $this->sign($payload),
        ];
    }

    /**
     * ضربان قلب لایسنس — آخرین دامنه/IP/زمانی که کلاینت چک کرده است.
     * برای شناسایی ناهنجاری (استفادهٔ هم‌زمان از یک لایسنس روی چند دامنه) در پنل بازار.
     */
    public function heartbeat(PackageLicense $license, ?string $domain, ?string $ip): void
    {
        try {
            $license->forceFill([
                'last_check_at'     => now(),
                'last_check_ip'     => (string) $ip,
                'last_check_domain' => $this->normalizeDomain((string) $domain),
            ])->save();
        } catch (\Throwable $e) {
            // ضربان قلب هرگز نباید مسیر verify را بشکند
            Log::warning('License heartbeat failed', ['license' => $license->id, 'error' => $e->getMessage()]);
        }
    }

    private function normalizeDomain(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        $host = parse_url($url, PHP_URL_HOST) ?: preg_replace('#^https?://#', '', $url);
        $host = preg_replace('#^www\.#', '', (string) $host);

        return trim((string) $host, '/');
    }

    /* ===================================================================
     *  مانیفست یکپارچگی فایل‌ها (module.lock)
     * =================================================================== */

    /**
     * ساخت مانیفست امضاشده از ZIP نسخهٔ پکیج — SHA-256 همهٔ فایل‌ها.
     *
     * نکته: پوشهٔ .git داخل ZIP اگر وجود داشته باشد (نباید وجود داشته باشد)
     * نادیده گرفته می‌شود. نتیجه per-version کش می‌شود تا هر verify ZIP باز نشود.
     *
     * @return array{manifest: array<string,mixed>, signature: string}
     */
    public function manifestForVersion(Package $package, PackageVersion $version): array
    {
        $cacheKey = 'license_envelope.manifest.'.$package->slug.'.'.$version->version;

        $cached = Cache::get($cacheKey);

        if (is_array($cached) && isset($cached['manifest'], $cached['signature'])) {
            return $cached;
        }

        $files = $this->hashZipFiles($version);

        $manifest = [
            'v'            => self::ENVELOPE_VERSION,
            'slug'         => $package->slug,
            'version'      => $version->version,
            'file_hash'    => $version->file_hash, // SHA-256 کل ZIP — تطبیق با دانلود
            'generated_at' => now()->toIso8601String(),
            'files'        => $files,
        ];

        $result = [
            'manifest'  => $manifest,
            'signature' => $this->sign($manifest),
        ];

        // مانیفست ثابت نسخه است — کش طولانی بی‌خطر است
        Cache::put($cacheKey, $result, now()->addDays(30));

        return $result;
    }

    /**
     * باز کردن ZIP نسخه و هش‌کردن همهٔ فایل‌ها.
     *
     * @return array<string, string>  path => sha256 (مسیر نسبی، جداکنندهٔ /)
     */
    private function hashZipFiles(PackageVersion $version): array
    {
        $zipPath = $version->getAbsoluteFilePath();

        if (! is_file($zipPath)) {
            throw new RuntimeException('فایل ZIP نسخهٔ پکیج یافت نشد.');
        }

        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('باز کردن ZIP نسخهٔ پکیج ناموفق بود.');
        }

        $files = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            // ورودی دایرکتوری را رد کن
            $name = (string) $zip->getNameIndex($i);

            if ($name === '' || str_ends_with($name, '/')) {
                continue;
            }

            $stream = $zip->getStream($name);

            if ($stream === false) {
                continue;
            }

            $hash = hash_init('sha256');
            hash_update_stream($hash, $stream);
            fclose($stream);

            // حذف نام پوشهٔ ریشهٔ داخل ZIP (مثل CustomerClub/...) — کلاینت مسیر نسبی می‌خواهد
            $relative = $this->stripRootFolder($name);

            if ($relative === null || $relative === '' || str_starts_with($relative, '.git/')) {
                continue;
            }

            $files[$relative] = hash_final($hash);
        }

        $zip->close();

        ksort($files);

        return $files;
    }

    /**
     * «CustomerClub/app/Services/X.php» → «app/Services/X.php»
     */
    private function stripRootFolder(string $name): ?string
    {
        $parts = explode('/', $name);

        if (count($parts) < 2) {
            return null; // فایل ریشهٔ ZIP — پکیج ما همیشه داخل یک پوشه است
        }

        array_shift($parts);

        return implode('/', $parts);
    }
}
