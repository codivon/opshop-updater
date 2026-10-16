<?php

namespace App\Services;

use App\Models\Package;
use App\Models\PackageImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * سرویس آپلود تصاویر پکیج‌ها — مقاوم‌سازی‌شده برای هاست اشتراکی (نسخه ۲):
 *
 *  ۱) مسیر اصلی: public/uploads/… (سرو مستقیم توسط وب‌سرور)
 *  ۲) مسیر جایگزین (fallback): storage/app/public/uploads/…
 *     اگر public قابل نوشتن نباشد — از طریق روت /uploads/{path} سرو می‌شود.
 *
 *  در هر دو حالت مسیرِ ذخیره‌شده در دیتابیس یکسان است: uploads/…
 *
 *  🔧 تغییر نسخه ۲ (رفع ارور «Could not move the file …»):
 *  Livewire فایل را ابتدا در storage/app/private/livewire-tmp قرار می‌دهد.
 *  move() (rename داخلی) روی بعضی هاست‌ها بین این دو مسیر شکست می‌خورد
 *  (سطح‌های مختلف filesystem یا محدودیت rename). طبق درخواست، به‌جای move
 *  از copy() + unlink() استفاده می‌شود — tmp توسط Livewire هم خودکار پاک می‌شود.
 *  نکته مهم: مسیر fallback قبلاً به اشتباه «uploads» را دوبار تکرار می‌کرد
 *  (storage/app/public/uploads/uploads/…) — اصلاح شد.
 */
class ImageUploadService
{
    private const THUMBNAIL_DIR = 'uploads/packages/thumbnails';
    private const GALLERY_DIR   = 'uploads/packages/gallery';

    /** ریشه‌ی جایگزین داخل storage/app */
    private const FALLBACK_ROOT = 'public'; // → storage/app/public/uploads/…

    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const MAX_SIZE_KB   = 3072; // 3MB

    /* ===================================================================
     *  آپلود تصویر شاخص
     *  Returns: مسیر نسبی (uploads/packages/thumbnails/xxx.png)
     * =================================================================== */
    public function uploadThumbnail(UploadedFile $file, string $slug): string
    {
        $this->validateImage($file);

        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $filename  = $slug . '_thumb_' . time() . '_' . Str::random(6) . '.' . $extension;

        return $this->storeFile($file, self::THUMBNAIL_DIR, $filename);
    }

    /* ===================================================================
     *  آپلود تصویر شاخص محصول قابل‌فروش (پروژه)
     *  Returns: مسیر نسبی (uploads/products/thumbnails/xxx.jpg)
     * =================================================================== */
    public function uploadThumbnailProduct(UploadedFile $file, string $slug): string
    {
        $this->validateImage($file);

        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $filename  = 'product_' . \Illuminate\Support\Str::slug($slug) . '_thumb_' . time() . '_' . Str::random(6) . '.' . $extension;

        return $this->storeFile($file, 'uploads/products/thumbnails', $filename);
    }

    /* ===================================================================
     *  آپلود چندین تصویر گالری
     *  Returns: array of PackageImage
     * =================================================================== */
    public function uploadGalleryImages(array $files, Package $package): array
    {
        $uploaded = [];

        DB::transaction(function () use ($files, $package, &$uploaded) {
            $nextOrder = $package->images()->max('sort_order') ?: 0;

            foreach ($files as $file) {
                if (!$file instanceof UploadedFile || !$file->isValid()) {
                    continue;
                }

                $this->validateImage($file);

                $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
                $filename  = $package->slug . '_gallery_' . time() . '_' . Str::random(6) . '.' . $extension;

                // ذخیره اطلاعات قبل از انتقال
                $originalName = $file->getClientOriginalName();
                $fileSize     = $file->getSize();

                $path = $this->storeFile($file, self::GALLERY_DIR, $filename);

                $nextOrder++;

                $image = PackageImage::create([
                    'package_id'    => $package->id,
                    'path'          => $path,
                    'original_name' => $originalName,
                    'size'          => $fileSize,
                    'sort_order'    => $nextOrder,
                    'is_active'     => true,
                ]);

                $uploaded[] = $image;
            }
        });

        return $uploaded;
    }

    /* ===================================================================
     *  هسته ذخیره‌سازی: public → در صورت عدم امکان، storage fallback
     *  استراتژی: copy + unlink (به‌جای move/rename)
     * =================================================================== */
    private function storeFile(UploadedFile $file, string $relativeDir, string $filename): string
    {
        // مسیر tmp واقعی فایل (livewire-tmp یا php upload tmp)
        $source = $file->getRealPath();

        if (!$source || !is_file($source)) {
            throw new RuntimeException('فایل آپلودی روی سرور یافت نشد؛ لطفاً دوباره تلاش کنید.');
        }

        $publicDir = public_path($relativeDir);

        // ۱) تلاش برای نوشتن در public
        $stored = $this->copyTo($source, $publicDir, $filename);

        if ($stored === true) {
            return $relativeDir . '/' . $filename;
        }

        Log::warning('Upload to public dir failed, falling back to storage', [
            'dir'   => $relativeDir,
            'error' => is_string($stored) ? $stored : 'unknown',
        ]);

        // ۲) مسیر جایگزین: storage/app/public/uploads/…
        $fallbackDir = storage_path('app/' . self::FALLBACK_ROOT . '/' . $relativeDir);

        $stored = $this->copyTo($source, $fallbackDir, $filename);

        if ($stored !== true) {
            $reason = is_string($stored) ? $stored : 'دلیل نامشخص';
            throw new RuntimeException(
                'ذخیره تصویر ناموفق بود (' . $reason . '). ' .
                'لطفاً دسترسی (پرمیژن ۷۷۵) و مالکیت پوشه‌های «public/uploads» و «storage/app/public» را بررسی کنید.'
            );
        }

        return $relativeDir . '/' . $filename;
    }

    /**
     * کپی فایل tmp به مقصد با ساخت خودکار دایرکتوری.
     *
     * @return bool|string true در صورت موفقیت؛ در خطا، پیام علت (string)
     */
    private function copyTo(string $source, string $targetDir, string $filename): bool|string
    {
        // ۱) ساخت دایرکتوری (بازگشتی) — اگر شکست خورد علت را گزارش کن
        $dirOk = $this->ensureDirectory($targetDir);

        if (!$dirOk) {
            return 'ساخت/دسترسی پوشه «' . $targetDir . '» ممکن نیست (پرمیژن؟)';
        }

        $target = $targetDir . '/' . $filename;

        // ۲) کپی (به‌جای rename) — بین دیسک‌ها/لایه‌های filesystem هم کار می‌کند
        if (!@copy($source, $target)) {
            $err = error_get_last()['message'] ?? 'copy failed';

            // تلاش دوم با rename (اگر copy به هر دلیل نشد)
            if (!@rename($source, $target)) {
                return 'کپی فایل به «' . $target . '» ناموفق بود: ' . $err;
            }
        }

        // ۳) پاک‌سازی tmp (Livewire خودش هم پاک می‌کند — این برای اطمینان است)
        @chmod($target, 0644);

        // اگر copy بود (نه rename) فایل tmp را پاک می‌کنیم
        if (is_file($source) && $source !== $target) {
            @unlink($source);
        }

        return true;
    }

    /**
     * ساخت مطمئن دایرکتوری + اطمینان از قابل نوشتن بودن
     */
    private function ensureDirectory(string $path, int $mode = 0775): bool
    {
        if (!is_dir($path)) {
            // mkdir بازگشتی — سطح به سطح تا اگر والد‌ها مشکل داشتند هم بسازیم
            if (!@mkdir($path, $mode, true) && !is_dir($path)) {
                // والد را یک بار چک/اصلاح می‌کنیم و دوباره تلاش
                @chmod(dirname($path), $mode);

                if (!@mkdir($path, $mode, true) && !is_dir($path)) {
                    return false;
                }
            }
        }

        // اگر وجود دارد ولی قابل نوشتن نیست → تلاش برای اصلاح دسترسی
        if (is_dir($path) && !is_writable($path)) {
            @chmod($path, $mode);
            @chmod(dirname($path), $mode);
        }

        // تست واقعی نوشتن (is_writable روی بعضی NFS گمراه‌کننده است)
        if (is_dir($path) && is_writable($path)) {
            $probe = $path . '/.write_test_' . bin2hex(random_bytes(3));
            if (@file_put_contents($probe, 'ok') !== false) {
                @unlink($probe);

                return true;
            }
        }

        return false;
    }

    /* ===================================================================
     *  حذف تصاویر (هر دو مسیر پاک می‌شوند)
     * =================================================================== */
    public function deleteThumbnail(Package $package): void
    {
        if ($package->thumbnail) {
            $this->deleteFile($package->thumbnail);
            $package->update(['thumbnail' => null]);
        }
    }

    public function deleteGalleryImage(PackageImage $image): void
    {
        $this->deleteFile($image->path);
        $image->delete();
    }

    public function deleteAllGalleryImages(Package $package): int
    {
        $count = 0;
        foreach ($package->images as $image) {
            $this->deleteFile($image->path);
            $image->delete();
            $count++;
        }
        return $count;
    }

    /** حذف فایل از public و در صورت نبود، از storage fallback (عمومی — برای لوگو درگاه‌ها هم استفاده می‌شود) */
    public function deleteFile(string $relativePath): void
    {
        $publicPath   = public_path($relativePath);
        $fallbackPath = storage_path('app/' . self::FALLBACK_ROOT . '/' . $relativePath);

        foreach ([$publicPath, $fallbackPath] as $candidate) {
            if (is_file($candidate)) {
                @unlink($candidate);
            }
        }
    }

    /* ===================================================================
     *  آپلود لوگوی درگاه (عمومی — در Gateways استفاده می‌شود)
     *  Returns: مسیر نسبی (uploads/gateways/logos/xxx.svg) یا null
     * =================================================================== */
    public function uploadGatewayLogo(UploadedFile $file, string $key): ?string
    {
        if (!$file->isValid()) {
            throw new RuntimeException('فایل لوگو معتبر نیست.');
        }

        $mime = $file->getMimeType();

        $allowed = array_merge(self::ALLOWED_MIMES, [
            'image/svg+xml',
            'image/x-svg',
            'application/octet-stream', // بعضی هاست‌ها svg را اینطور می‌شناسند
        ]);

        if (!in_array($mime, $allowed, true)) {
            throw new RuntimeException('فرمت لوگو مجاز نیست؛ فقط JPG, PNG, WEBP, SVG.');
        }

        // برای SVG فقط پسوند .svg
        $ext       = strtolower($file->getClientOriginalExtension() ?: 'png');
        $ext       = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'], true) ? ($ext === 'jpeg' ? 'jpg' : $ext) : 'png';
        $filename  = $key . '-' . time() . '-' . Str::random(4) . '.' . $ext;

        $dir   = 'uploads/gateways/logos';
        $probe = $this->storeFile($file, $dir, $filename);

        return $probe;
    }

    /* ===================================================================
     *  آپدیت ترتیب تصاویر گالری
     * =================================================================== */
    public function updateOrder(array $orderedIds): void
    {
        DB::transaction(function () use ($orderedIds) {
            foreach ($orderedIds as $order => $id) {
                PackageImage::where('id', $id)->update(['sort_order' => $order + 1]);
            }
        });
    }

    /* ===================================================================
     *  Validation
     * =================================================================== */
    private function validateImage(UploadedFile $file): void
    {
        if (!in_array($file->getMimeType(), self::ALLOWED_MIMES)) {
            throw new RuntimeException(
                'فرمت فایل مجاز نیست. فقط JPG, PNG, WEBP, GIF مجاز است.'
            );
        }

        if ($file->getSize() > self::MAX_SIZE_KB * 1024) {
            throw new RuntimeException(
                'حجم فایل نباید بیشتر از ' . (self::MAX_SIZE_KB / 1024) . ' مگابایت باشد.'
            );
        }
    }
}
