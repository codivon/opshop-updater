<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * پشتیبانی از فایل‌های آپلود تکه‌ای (Chunked) در کامپوننت‌های Livewire.
 *
 * کلاینت (public/js/fast-upload.js) فایل حجیم را در تکه‌های ~۴MB به
 * ChunkUploadController می‌فرستد؛ فایل در storage/app/chunk-uploads/{uuid}.part
 * جمع می‌شود و مسیر نسبی‌اش در یک پراپرتی عمومی کامپوننت ذخیره می‌شود
 * (مثل versionChunkPath). در لحظهٔ «ذخیره»، این تریت فایل را اعتبارسنجی و
 * به مقصد نهایی منتقل می‌کند — دقیقاً با همان قراردادی که UploadedFile
 * آپلود معمولی Livewire دارد (file_path / file_hash / file_size).
 *
 * قرارداد نام‌گذاری پراپرتی‌ها:
 *   {prop}      = مسیر نسبی فایل موقت (chunk-uploads/{uuid}.part)
 *   {prop}Name  = نام اصلی فایل روی دستگاه کاربر
 *   {prop}Size  = حجم فایل به بایت
 */
trait InteractsWithChunkUploads
{
    /** آخرین پیام خطای اعتبارسنجی فایل تکه‌ای */
    public ?string $chunkError = null;

    /** الگوی معتبر مسیر فایل موقت تکه‌ای */
    protected function chunkPattern(): string
    {
        return '/^chunk-uploads\/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\.part$/';
    }

    /** آیا فایل تکه‌ای انتخاب شده است؟ */
    public function hasChunkFile(string $prop): bool
    {
        return filled($this->{$prop} ?? null) && preg_match($this->chunkPattern(), (string) $this->{$prop}) === 1;
    }

    /**
     * اعتبارسنجی داخلی؛ مسیر مطلق یا null به‌همراه پیام خطا در $this->chunkError.
     *
     * @return string|null
     */
    protected function chunkAbsolutePath(string $prop, array $allowedExt, int $maxMb): ?string
    {
        $this->chunkError = null;

        $rel = (string) ($this->{$prop} ?? '');

        if ($rel === '') {
            $this->chunkError = 'فایلی آپلود نشده است.';

            return null;
        }

        if (preg_match($this->chunkPattern(), $rel) !== 1) {
            $this->chunkError = 'فایل آپلودشده معتبر نیست — دوباره انتخابش کنید.';

            return null;
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($rel)) {
            $this->chunkError = 'فایل موقت آپلود یافت نشد — لطفاً دوباره فایل را انتخاب کنید.';

            return null;
        }

        $absolute = $disk->path($rel);
        $size     = (int) (@filesize($absolute) ?: 0);

        if ($size <= 0) {
            $this->chunkError = 'فایل آپلودشده خالی است — دوباره انتخابش کنید.';

            return null;
        }

        if ($size > $maxMb * 1048576) {
            $this->chunkError = "حجم فایل بیش از حد مجاز ({$maxMb} مگابایت) است.";

            return null;
        }

        if ($allowedExt !== []) {
            $original = (string) ($this->{$prop . 'Name'} ?? '');
            $ext      = strtolower(pathinfo($original, PATHINFO_EXTENSION));

            if (! in_array($ext, $allowedExt, true)) {
                $this->chunkError = 'فرمت فایل مجاز نیست (فقط ' . implode('، ', $allowedExt) . ').';

                return null;
            }
        }

        return $absolute;
    }

    /**
     * اعتبارسنجی فایل تکه‌ای و برگرداندن مسیر مطلق.
     * در خطا addError روی $errorKey می‌گذارد (مثل خطاهای آپلود معمولی نمایش داده می‌شود) و null برمی‌گرداند.
     */
    protected function takeChunkFile(string $prop, string $errorKey, array $allowedExt = [], int $maxMb = 512): ?string
    {
        $absolute = $this->chunkAbsolutePath($prop, $allowedExt, $maxMb);

        if ($absolute === null && $this->chunkError) {
            $this->addError($errorKey, $this->chunkError);
        }

        return $absolute;
    }

    /**
     * انتقال فایل تکه‌ای به مقصد نهایی روی دیسک local
     * و بازگشت همان قرارداد storeVersionFile: [file_path, file_hash, file_size]
     *
     * @return array{file_path: string, file_hash: string, file_size: int}
     *
     * @throws RuntimeException با پیام دقیق خطا
     */
    protected function storeChunkFile(string $prop, string $destDir, string $filename, array $allowedExt = [], int $maxMb = 2048): array
    {
        $absolute = $this->chunkAbsolutePath($prop, $allowedExt, $maxMb);

        if ($absolute === null) {
            throw new RuntimeException((string) $this->chunkError);
        }

        $disk    = Storage::disk('local');
        $destRel = trim($destDir, '/') . '/' . ltrim($filename, '/');
        $destAbs = $disk->path($destRel);

        @mkdir(dirname($destAbs), 0775, true);

        if (! @rename($absolute, $destAbs)) {
            if (! @copy($absolute, $destAbs)) {
                throw new RuntimeException('ذخیرهٔ فایل روی دیسک ناموفق بود (کوتا/دسترسی).');
            }
            @unlink($absolute);
        }

        clearstatcache(true, $destAbs);
        $size = (int) (@filesize($destAbs) ?: 0);

        // پاک‌سازی پراپرتی‌ها بعد از جابه‌جایی موفق
        $this->{$prop} = null;
        $this->{$prop . 'Name'} = null;
        $this->{$prop . 'Size'} = null;

        return [
            'file_path' => $destRel,
            'file_hash' => hash_file('sha256', $destAbs) ?: '',
            'file_size' => $size,
        ];
    }

    /** حذف فایل موقت تکه‌ای (در حالت انصراف/جایگزینی) */
    protected function clearChunkFile(string $prop): void
    {
        $rel = (string) ($this->{$prop} ?? '');

        if ($rel !== '' && preg_match($this->chunkPattern(), $rel) === 1) {
            Storage::disk('local')->delete($rel);
        }

        $this->{$prop} = null;
        $this->{$prop . 'Name'} = null;
        $this->{$prop . 'Size'} = null;
    }
}
