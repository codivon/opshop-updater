<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\ProductDownloadToken;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * دانلود محصول قابل‌فروش با توکن یک‌بارمصرف ۱۵ دقیقه‌ای (مهمان — بدون احراز هویت).
 * همان الگوی PackageDownloadController اما برای محصولات.
 */
class ProductDownloadController extends Controller
{
    public function download(string $token): BinaryFileResponse
    {
        $tokenModel = ProductDownloadToken::query()
            ->with(['version', 'product'])
            ->where('token', $token)
            ->first();

        if (!$tokenModel) {
            abort(404, 'لینک دانلود یافت نشد.');
        }

        if (!$tokenModel->isValid()) {
            $reason = $tokenModel->used_at
                ? 'این لینک دانلود قبلاً استفاده شده است. لطفاً از صفحه نتیجه خرید، لینک جدید بگیرید.'
                : 'این لینک دانلود منقضی شده است (۱۵ دقیقه). لطفاً از صفحه نتیجه خرید، لینک جدید بگیرید.';

            abort(403, $reason);
        }

        $version = $tokenModel->version;

        if (!$version || !$version->hasFile()) {
            abort(404, 'فایل نسخه محصول یافت نشد. لطفاً با پشتیبانی تماس بگیرید.');
        }

        // مصرف توکن (یک‌بارمصرف)
        $tokenModel->consume(request()->ip());
        $version->incrementDownloads();

        Log::info('Product download started', [
            'product_id' => $tokenModel->product_id,
            'version_id' => $version->id,
            'purchase_id' => $tokenModel->purchase_id,
        ]);

        $absolutePath = $version->getAbsoluteFilePath();
        $filename     = $tokenModel->product?->slug . '-' . $version->version . '.zip';

        return response()->download($absolutePath, $filename);
    }
}
