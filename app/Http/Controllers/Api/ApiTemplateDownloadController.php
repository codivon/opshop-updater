<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Template;
use App\Models\TemplateDownloadToken;
use App\Services\PackageApiAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use RuntimeException;

/**
 * دانلود قالب‌ها از API — همان الگو و محدودیت‌های دانلود پکیج‌ها:
 *
 *  - لینک یک‌بارمصرف: توکن ۱۵دقیقه‌ای، حتی بدون هدرهای احراز کار می‌کند
 *    (خودِ توکن معتبر گواهی دسترسی است) اما اگر هدرهای احراز ارسال شود،
 *    تعلق توکن به همان مشتری هم کنترل می‌شود.
 *  - دانلود مستقیم با slug: احراز هویت کامل + خریدِ پرداخت‌شده‌ی همان مشتری لازم است.
 *  - issueDownloadUrl: ساخت لینک یک‌بارمصرف برای قالبِ خریداری‌شده.
 */
class ApiTemplateDownloadController extends Controller
{
    public function __construct(private PackageApiAuthService $authService) {}

    /* ===================================================================
     *  GET /api/v1/templates/download/{token}
     *  دانلود فایل ZIP با token موقت
     *
     *  دو حالت احراز هویت (مانند پکیج‌ها):
     *   1) با هدرهای احراز (Bearer/X-Project-Url) → تعلق توکن به مشتری کنترل می‌شود
     *   2) بدون هدرها (مرورگر مشتری روی لینک مستقیم) → خودِ token معتبرِ
     *      یک‌بارمصرفِ ۱۵دقیقه‌ای به‌تنهایی اعتبار درخواست است.
     * =================================================================== */
    public function download(Request $request, string $dlToken)
    {
        $downloadToken = TemplateDownloadToken::where('token', $dlToken)->first();

        if (!$downloadToken) {
            return response()->json(['error' => 'توکن دانلود نامعتبر است.'], 404);
        }

        try {
            // آیا هدرهای احراز هویت ارسال شده است؟
            $hasAuthHeaders = $request->bearerToken()
                || $request->header('X-Project-Key')
                || $request->input('token')
                || $request->header('X-Project-Url');

            if ($hasAuthHeaders) {
                $customer = $this->authService->authenticate($request);

                if ($downloadToken->customer_id && $downloadToken->customer_id !== $customer->id) {
                    return response()->json(['error' => 'توکن دانلود به این مشتری تعلق ندارد.'], 403);
                }
            } else {
                // حالت لینک مستقیم: token خودش گواهی دسترسی است (یک‌بارمصرف + ۱۵ دقیقه)
                $customer = $downloadToken->customer;
            }

            if ($customer && $customer->status !== 'active') {
                return response()->json(['error' => 'حساب مشتری غیرفعال است.'], 403);
            }

            if (!$downloadToken->isValid()) {
                return response()->json(['error' => 'توکن دانلود منقضی یا استفاده‌شده است.'], 403);
            }

            $version = $downloadToken->version;
            if (!$version || !Storage::disk('local')->exists($version->file_path)) {
                return response()->json(['error' => 'فایل قالب یافت نشد.'], 404);
            }

            // علامت‌گذاری توکن به‌عنوان استفاده‌شده
            $downloadToken->consume($request->ip());

            // افزایش تعداد دانلودها
            $version->incrementDownloads();
            $version->template?->incrementDownloads();

            return $this->streamVersionFile($version);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], exception_status($e, 403));
        }
    }

    /* ===================================================================
     *  GET /api/v1/templates/{slug}/download
     *  دانلود مستقیم آخرین نسخه‌ی قالبِ «خریداری‌شده» (خرید پرداخت‌شده لازم است).
     *  مناسب وقتی پروژه‌ی خریدار خودش فایل را proxy می‌کند.
     *  Query: { version? = نسخه خاص، پیش‌فرض آخرین نسخه }
     * =================================================================== */
    public function downloadBySlug(Request $request, string $slug)
    {
        try {
            $customer = $this->authService->authenticate($request);

            $resolved = $this->resolvePurchasedVersion($request, $customer, $slug);
            if ($resolved instanceof JsonResponse) {
                return $resolved;
            }
            [$purchase, $version] = $resolved;

            if (!Storage::disk('local')->exists($version->file_path)) {
                return response()->json(['error' => 'فایل این نسخه یافت نشد.'], 404);
            }

            $version->incrementDownloads();
            $version->template?->incrementDownloads();

            // ثبت سابقه دانلود با توکن یک‌بارمصرف (برای گزارش‌ها)
            TemplateDownloadToken::create([
                'token'               => Str::random(64),
                'template_id'         => $version->template_id,
                'template_version_id' => $version->id,
                'purchase_id'         => $purchase->id,
                'customer_id'         => $customer->id,
                'expires_at'          => now()->addMinutes(15),
                'used_at'             => now(),
                'ip'                  => $request->ip(),
            ]);

            return $this->streamVersionFile($version);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], exception_status($e, 403));
        }
    }

    /* ===================================================================
     *  POST /api/v1/templates/{slug}/download-url
     *  ساخت لینک دانلود یک‌بارمصرف (۱۵ دقیقه) برای قالب خریداری‌شده.
     *  پروژه خریدار می‌تواند این URL را مستقیم به مرورگر مشتری بدهد؛
     *  لینک بدون نیاز به هدرهای احراز هویت کار می‌کند.
     *  Response: { download_url, expires_in_seconds, version, file_size, file_hash }
     * =================================================================== */
    public function issueDownloadUrl(Request $request, string $slug): JsonResponse
    {
        try {
            $customer = $this->authService->authenticate($request);

            $resolved = $this->resolvePurchasedVersion($request, $customer, $slug);
            if ($resolved instanceof JsonResponse) {
                return $resolved;
            }
            [$purchase, $version] = $resolved;

            if (!Storage::disk('local')->exists($version->file_path)) {
                return response()->json(['error' => 'فایل این نسخه یافت نشد.'], 404);
            }

            $token = TemplateDownloadToken::create([
                'token'               => Str::random(64),
                'template_id'         => $version->template_id,
                'template_version_id' => $version->id,
                'purchase_id'         => $purchase->id,
                'customer_id'         => $customer->id,
                'expires_at'          => now()->addMinutes(15),
            ]);

            return response()->json([
                'download_url'        => route('api.templates.download', $token->token),
                'expires_in_seconds'  => 900,
                'is_one_time'         => true,
                'slug'                => $slug,
                'version'             => $version->version,
                'file_size'           => (int) $version->file_size,
                'file_hash'           => $version->file_hash,
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], exception_status($e, 403));
        }
    }

    /* ===================================================================
     *  Helper - یافتن (خرید پرداخت‌شده، نسخه درخواستی/آخرین نسخه) برای مشتری
     *  خروجی: [TemplatePurchase, TemplateVersion] یا JsonResponse خطا
     * =================================================================== */
    private function resolvePurchasedVersion(Request $request, Customer $customer, string $slug): array|JsonResponse
    {
        $template = Template::where('slug', $slug)
            ->where('status', Template::STATUS_ACTIVE)
            ->first();

        if (!$template) {
            return response()->json(['error' => 'قالب یافت نشد.'], 404);
        }

        $purchase = TemplatePurchase::where('customer_id', $customer->id)
            ->where('template_id', $template->id)
            ->where('status', TemplatePurchase::STATUS_PAID)
            ->orderByDesc('id')
            ->first();

        if (!$purchase) {
            return response()->json([
                'error' => 'شما این قالب را نخریده‌اید یا خرید شما پرداخت‌شده نیست.',
                'is_purchased' => false,
            ], 403);
        }

        $version = $request->filled('version')
            ? $template->versions()->where('version', $request->input('version'))->first()
            : $template->latestVersion()->first();

        if (!$version) {
            return response()->json(['error' => 'نسخه فعالی برای این قالب وجود ندارد.'], 422);
        }

        return [$purchase, $version];
    }

    /* ===================================================================
     *  Helper - استریم فایل نسخه با هدرهای متادیتا
     * =================================================================== */
    private function streamVersionFile(\App\Models\TemplateVersion $version): StreamedResponse
    {
        $fileName = basename($version->file_path);
        $mimeType = Storage::disk('local')->mimeType($version->file_path);
        $size = Storage::disk('local')->size($version->file_path);

        return new StreamedResponse(function () use ($version) {
            $stream = Storage::disk('local')->readStream($version->file_path);
            while (!feof($stream)) {
                echo fread($stream, 1024 * 1024); // 1MB chunks
            }
            fclose($stream);
        }, 200, [
            'Content-Type'        => $mimeType,
            'Content-Length'      => $size,
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'X-Template-Version'  => $version->version,
            'X-Template-Hash'     => $version->file_hash,
        ]);
    }
}
