<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * سرو فایل‌های uploads/…
 *
 *  ۱) اگر فایل در public/uploads وجود دارد → وب‌سرور خودش مستقیم سرو می‌کند و
 *     این کنترلر اصلاً فراخوانی نمی‌شود.
 *  ۲) اگر وجود ندارد (آپلود در مسیر جایگزین storage/app/public/uploads شده
 *     چون public قابل نوشتن نبود) → همین روت فایل را استریم می‌کند.
 *
 *  نتیجه: مسیر URL ثابت می‌ماند (/uploads/…)، فارغ از محل فیزیکی ذخیره.
 */
class UploadsServeController extends Controller
{
    public const FALLBACK_ROOT = 'public/uploads'; // داخل storage/app

    public function __invoke(string $path): BinaryFileResponse
    {
        $path = str_replace(['..', "\0"], '', $path); // ضد مسیرپیمایی
        $path = ltrim($path, '/');

        if ($path === '') {
            abort(404);
        }

        $fallback = storage_path('app/'.self::FALLBACK_ROOT.'/'.$path);

        if (! is_file($fallback)) {
            abort(404);
        }

        // فقط پسوندهای مجاز (ایمنی)
        $allowed = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'zip', 'svg'];
        $ext = strtolower(pathinfo($fallback, PATHINFO_EXTENSION));

        if (! in_array($ext, $allowed, true)) {
            abort(403, 'نوع فایل مجاز نیست.');
        }

        $mime = match ($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };

        $headers = [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
        ];

        // Task 85 — امنیت (LOW1): SVG/ZIP اجراشدنی/اجراشدنی نیستند به‌صورت inline؛
        // SVG حاوی script می‌تواند XSS در دامنهٔ سایت باشد — فقط دانلود.
        if (in_array($ext, ['svg', 'zip'], true)) {
            $headers['Content-Disposition'] = 'attachment; filename="'.basename($fallback).'"';
        }

        return response()
            ->file($fallback, $headers);
    }
}
