<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * آپلود تکه‌ای (Chunked) فایل‌های حجیم — ZIP نسخه‌ها، فایل آپدیت، پشتیبان.
 *
 * چرا؟ روی اینترنت ایران یک POST بزرگ معمولاً قطع می‌شود و آپلود از صفر
 * تکرار می‌شود. اینجا فایل در تکه‌های ~۴ مگابایتی به storage/app/chunk-uploads
 * اضافه می‌شود؛ هر تکه idempotent است (تکرارش بی‌خطر) و کلاینت می‌تواند با
 * GET status اندازهٔ فعلی را بگیرد و از همان‌جا ادامه دهد.
 *
 * ذخیرهٔ نهایی (انتقال به مقصد + هش) در لحظهٔ «ذخیره» توسط خود کامپوننت
 * Livewire با تریت InteractsWithChunkUploads انجام می‌شود.
 */
class ChunkUploadController extends Controller
{
    /** پوشهٔ موقت تکه‌ها */
    protected function dir(): string
    {
        $dir = storage_path('app/chunk-uploads');

        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    /** مسیر امن فایل موقت — شناسه باید UUID معتبر باشد */
    protected function pathOf(string $id): string
    {
        if (! preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $id)) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'شناسهٔ آپلود نامعتبر است.');
        }

        return $this->dir() . '/' . $id . '.part';
    }

    /** دریافت یک تکه و افزودن به انتهای فایل موقت */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'upload_id' => ['required', 'string'],
            'offset'    => ['required', 'integer', 'min:0'],
            'max_mb'    => ['nullable', 'integer', 'min:1', 'max:2048'],
            'chunk'     => ['required', 'file', 'max:8192'], // هر تکه حداکثر ۸MB
        ]);

        $path      = $this->pathOf((string) $data['upload_id']);
        $maxBytes  = (int) ($data['max_mb'] ?? 512) * 1048576;
        $offset    = (int) $data['offset'];

        clearstatcache(true, $path);
        $current = is_file($path) ? (int) filesize($path) : 0;

        if ($offset > $current) {
            // تکه‌های میانی گم شده‌اند → کلاینت باید از این اندازه ادامه دهد
            return response()->json(['status' => 'gap', 'size' => $current], Response::HTTP_CONFLICT);
        }

        if ($offset < $current) {
            // تکهٔ تکراری (ریترای/شبکهٔ بی‌ثبات) — بی‌خطر است
            return response()->json(['status' => 'duplicate', 'size' => $current]);
        }

        if ($current >= $maxBytes) {
            abort(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, 'حجم فایل بیش از حد مجاز است.');
        }

        $content = (string) $data['chunk']->getContent();

        if ($content === '' || file_put_contents($path, $content, FILE_APPEND | LOCK_EX) === false) {
            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'نوشتن تکه روی دیسک ناموفق بود.');
        }

        clearstatcache(true, $path);

        return response()->json(['status' => 'ok', 'size' => (int) filesize($path)]);
    }

    /** اندازهٔ فعلی فایل موقت (برای resume) */
    public function status(Request $request): JsonResponse
    {
        $id   = (string) $request->query('id', '');
        $path = $this->pathOf($id);

        clearstatcache(true, $path);

        return response()->json([
            'exists' => is_file($path),
            'size'   => is_file($path) ? (int) filesize($path) : 0,
        ]);
    }
}
