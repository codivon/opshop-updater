<?php

namespace App\Support;

use RuntimeException;

/**
 * ویرایش امن فایل .env — خواندن و نوشتن کلیدهای ساده (APP_ENV، APP_DEBUG و…).
 *
 * مسیر فایل تزریق‌شدنی است تا در تست‌ها بتوان با یک فایل موقت کار کرد و
 * از دست‌کاری .env واقعی پروژه جلوگیری شود.
 */
final class EnvEditor
{
    public function __construct(private ?string $path = null) {}

    public function path(): string
    {
        return $this->path ?? base_path('.env');
    }

    /** فایل .env وجود دارد و قابل نوشتن است؟ */
    public function writable(): bool
    {
        $path = $this->path();

        return is_file($path) && is_writable($path);
    }

    /** مقدار فعلی یک کلید — مستقیم از فایل (بدون کش پیکربندی) */
    public function get(string $key): ?string
    {
        $path = $this->path();

        if (! is_file($path)) {
            return null;
        }

        $pattern = '/^'.preg_quote($key, '/').'=(.*)$/m';

        if (preg_match($pattern, (string) file_get_contents($path), $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * نوشتن/به‌روزرسانی یک کلید در .env — خط موجود جایگزین و کلید جدید در انتهای فایل اضافه می‌شود.
     *
     * @throws RuntimeException وقتی فایل موجود نیست یا قابل نوشتن نیست
     */
    public function write(string $key, string $value): void
    {
        $path = $this->path();

        if (! is_file($path)) {
            throw new RuntimeException('فایل .env یافت نشد.');
        }

        if (! is_writable($path)) {
            throw new RuntimeException('فایل .env قابل نوشتن نیست (دسترسی فایل را بررسی کنید).');
        }

        $content = (string) file_get_contents($path);
        $line = $key.'='.$value;
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

        if (preg_match($pattern, $content)) {
            $content = (string) preg_replace($pattern, $line, $content, 1);
        } else {
            $content = rtrim($content).PHP_EOL.$line.PHP_EOL;
        }

        file_put_contents($path, $content, LOCK_EX);
    }
}
