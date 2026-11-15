<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * فرمان کلیدسازی امضای لایسنس — رفع خطای
 * «نصب پکیج ناموفق بود: کلید امضای لایسنس (PACKAGE_LICENSE_SIGNING_KEY) تنظیم نشده است.»
 *
 * جفت‌کلید EC P-256 می‌سازد:
 *   • کلید خصوصی → در .env سرور بازار (PACKAGE_LICENSE_SIGNING_KEY) — ریشهٔ اعتماد امضا
 *   • کلید عمومی → برای جاسازی در کد کلاینت (فاز بعدی سخت‌سازی؛ جایی بکاپ بگیرید)
 *
 * مثال:
 *   php artisan license:generate-signing-keys --write-env
 *   php artisan license:generate-signing-keys --write-env --force   (بازنویسی کلید موجود)
 */
class GenerateSigningKeys extends Command
{
    protected $signature = 'license:generate-signing-keys
                            {--write-env : نوشتن مستقیم کلید خصوصی در فایل .env}
                            {--force : اگر کلید قبلاً تنظیم شده، بازنویسی شود}';

    protected $description = 'ساخت جفت‌کلید EC P-256 برای امضای پاکت لایسنس و نوشتن آن در .env';

    private const ENV_NAME = 'PACKAGE_LICENSE_SIGNING_KEY';

    public function handle(): int
    {
        if (! extension_loaded('openssl')) {
            $this->error('اکستنشن openssl روی PHP فعال نیست — کلید را دستی بسازید (راهنمای README، بخش «کلید امضای لایسنس»).');

            return self::FAILURE;
        }

        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name'       => 'prime256v1',
        ]);

        if ($key === false) {
            $this->error('ساخت کلید ناموفق بود ('.$this->opensslLastError().').');
            $this->line('راه دستی (روی همان سرور):');
            $this->line('  openssl ecparam -name prime256v1 -genkey -noout -out private.pem');
            $this->line('  openssl ec -in private.pem -pubout -out public.pem');

            return self::FAILURE;
        }

        $privatePem = '';

        if (! openssl_pkey_export($key, $privatePem)) {
            $this->error('خروجی گرفتن کلید خصوصی ناموفق بود ('.$this->opensslLastError().').');

            return self::FAILURE;
        }

        $details   = openssl_pkey_get_details($key);
        $publicPem = (string) ($details['key'] ?? '');

        // مقدار تک‌خطی .env: newline های واقعی → دنباله‌های literal \n
        // (سرویس امضا این فرمت را نرمال‌سازی می‌کند)
        $envLine = self::ENV_NAME.'="'.str_replace("\n", '\\n', $privatePem).'"';

        if ($this->option('write-env')) {
            $replaced = $this->writeEnv($envLine);

            if ($replaced !== true) {
                $this->error($replaced);

                return self::FAILURE;
            }
        }

        $this->info('جفت‌کلید امضای لایسنس ساخته شد (EC P-256).');
        $this->newLine();

        if ($this->option('write-env')) {
            $this->components->info('.env بروز شد — حالا حتماً کش پیکربندی را خالی کنید:');
            $this->line('  php artisan config:clear');
        } else {
            $this->components->info('این خط را در فایل .env سرور بازار (آپدیت‌سنتر) بگذارید:');
            $this->line($envLine);
            $this->newLine();
            $this->components->info('سپس کش پیکربندی را خالی کنید:');
            $this->line('  php artisan config:clear');
        }

        $this->newLine();
        $this->components->info('کلید عمومی (برای جاسازی در کد کلاینت — فاز بعدی؛ بکاپ امن نگه‌دارید):');
        $this->line($publicPem);

        $this->newLine();
        $this->warn('مهم: کلید خصوصی ریشهٔ اعتماد سیستم لایسنس است — بکاپ امن نگه‌دارید. گم‌شدن/تعویض آن یعنی همهٔ پاکت‌های امضاشدهٔ قبلی بی‌اعتبار می‌شوند.');

        return self::SUCCESS;
    }

    /**
     * نوشتن/جایگزینی امن خط کلید در .env — مقدار تک‌خطی با دنباله‌های \n
     *
     * @return true|string  true در موفقیت، متن خطا در شکست
     */
    private function writeEnv(string $envLine): bool|string
    {
        $path = base_path('.env');

        if (! is_file($path)) {
            return 'فایل .env پیدا نشد در: '.$path;
        }

        $content = (string) file_get_contents($path);

        $current = '';

        if (preg_match('/^'.self::ENV_NAME.'=(.*)$/m', $content, $m)) {
            $current = trim(trim((string) $m[1]), "\"'");
        }

        if ($current !== '' && ! $this->option('force')) {
            return self::ENV_NAME.' قبلاً تنظیم شده است؛ برای بازنویسی از --force استفاده کنید.';
        }

        if (preg_match('/^'.preg_quote(self::ENV_NAME, '/').'=.*$/m', $content)) {
            $content = (string) preg_replace('/^'.preg_quote(self::ENV_NAME, '/').'.*$/m', $envLine, $content);
        } else {
            $content = rtrim($content, "\n")."\n\n# --- پاکت لایسنس امضاشده (سخت‌سازی امنیتی فاز ۱) ---\n".$envLine."\n";
        }

        file_put_contents($path, $content, LOCK_EX);

        return true;
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
