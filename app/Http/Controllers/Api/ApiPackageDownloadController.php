<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Package;
use App\Models\PackageDownloadToken;
use App\Services\PackageApiAuthService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use RuntimeException;

class ApiPackageDownloadController extends Controller
{
    public function __construct(
        private PackageApiAuthService $authService,
        private SubscriptionService $subscriptionService,
    ) {}

    /* ===================================================================
     *  GET /api/v1/packages/download/{token}
     *  دانلود فایل ZIP با token موقت
     *
     *  دو حالت احراز هویت:
     *   1) با هدرهای احراز (Bearer/X-Project-Url) → رفتار قبلی (سازگار با قبل)
     *   2) بدون هدرها (مرورگر مشتری روی لینک مستقیم) → خودِ token معتبرِ
     *      یک‌بارمصرفِ ۱۵دقیقه‌ای به‌تنهایی اعتبار درخواست است.
     * =================================================================== */
    public function download(Request $request, string $dlToken)
    {
        $downloadToken = PackageDownloadToken::where('token', $dlToken)->first();

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

                if ($downloadToken->customer_id !== $customer->id) {
                    return response()->json(['error' => 'توکن دانلود به این مشتری تعلق ندارد.'], 403);
                }
            } else {
                // حالت لینک مستقیم: token خودش گواهی دسترسی است (یک‌بارمصرف + ۱۵ دقیقه)
                $customer = $downloadToken->customer;
            }

            if (!$customer || $customer->status !== 'active') {
                return response()->json(['error' => 'حساب مشتری غیرفعال است.'], 403);
            }

            if (!$downloadToken->isValid()) {
                return response()->json(['error' => 'توکن دانلود منقضی یا استفاده‌شده است.'], 403);
            }

            $version = $downloadToken->version;
            if (!$version || !Storage::disk('local')->exists($version->file_path)) {
                return response()->json(['error' => 'فایل پکیج یافت نشد.'], 404);
            }

            // 🚩 گیت اشتراک: حتی با توکن معتبر، دانلود فقط با اشتراکِ فعالِ پرداخت‌شده و
            // منقضی‌نشدهٔ پوشش‌دهندهٔ پروژهٔ این پکیج یا اشتراکِ فعالِ طرح‌محوری که
            // این پکیج را در پیکربندی مؤثرِ مدتِ خریداری‌شده‌اش رایگان می‌کند (Task 66).
            if (!$this->subscriptionService->hasActiveSubscriptionForProject($customer, (int) $version->package->project_id)
                && !$this->subscriptionService->activeSubscriptionCovering($customer, $version->package)) {
                return response()->json([
                    'error' => 'اشتراک فعالی وجود ندارد؛ دانلود پکیج فقط با اشتراک فعال امکان‌پذیر است.',
                ], 403);
            }

            // علامت‌گذاری توکن به‌عنوان استفاده‌شده
            $downloadToken->markAsUsed($request->ip());

            // افزایش تعداد دانلودها
            $version->incrementDownloads();
            $version->package->incrementDownloads();

            return $this->streamVersionFile($version);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], exception_status($e, 403));
        }
    }

    /* ===================================================================
     *  GET /api/v1/packages/{slug}/download
     *  دانلود مستقیم آخرین نسخه‌ی پکیجِ «خریداری‌شده» (لایسنس فعال لازم است).
     *  مناسب وقتی پروژه‌ی خریدار خودش فایل را proxy می‌کند.
     *  Query: { version? = نسخه خاص، پیش‌فرض آخرین نسخه }
     * =================================================================== */
    public function downloadBySlug(Request $request, string $slug)
    {
        try {
            $customer = $this->authService->authenticate($request);

            $resolved = $this->resolveLicensedVersion($request, $customer, $slug);
            if ($resolved instanceof JsonResponse) {
                return $resolved;
            }
            [$license, $version] = $resolved;

            if (!Storage::disk('local')->exists($version->file_path)) {
                return response()->json(['error' => 'فایل این نسخه یافت نشد.'], 404);
            }

            $version->incrementDownloads();
            $version->package->incrementDownloads();

            // ثبت سابقه دانلود با توکن یک‌بارمصرف (برای گزارش‌ها)
            PackageDownloadToken::create([
                'token'       => PackageDownloadToken::generate(),
                'license_id'  => $license->id,
                'version_id'  => $version->id,
                'customer_id' => $customer->id,
                'expires_at'  => now()->addMinutes(15),
                'used_at'     => now(),
                'ip_address'  => $request->ip(),
            ]);

            return $this->streamVersionFile($version);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], exception_status($e, 403));
        }
    }

    /* ===================================================================
     *  POST /api/v1/packages/{slug}/download-url
     *  ساخت لینک دانلود یک‌بارمصرف (۱۵ دقیقه) برای پکیج خریداری‌شده.
     *  پروژه خریدار می‌تواند این URL را مستقیم به مرورگر مشتری بدهد؛
     *  لینک بدون نیاز به هدرهای احراز هویت کار می‌کند.
     *  Response: { download_url, expires_in_seconds, version, file_size, file_hash }
     * =================================================================== */
    public function issueDownloadUrl(Request $request, string $slug): JsonResponse
    {
        try {
            $customer = $this->authService->authenticate($request);

            $resolved = $this->resolveLicensedVersion($request, $customer, $slug);
            if ($resolved instanceof JsonResponse) {
                return $resolved;
            }
            [$license, $version] = $resolved;

            if (!Storage::disk('local')->exists($version->file_path)) {
                return response()->json(['error' => 'فایل این نسخه یافت نشد.'], 404);
            }

            $token = PackageDownloadToken::create([
                'token'       => PackageDownloadToken::generate(),
                'license_id'  => $license->id,
                'version_id'  => $version->id,
                'customer_id' => $customer->id,
                'expires_at'  => now()->addMinutes(15),
            ]);

            return response()->json([
                'download_url'        => route('api.packages.download', $token->token),
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
     *  Helper - یافتن (لایسنس فعال، نسخه درخواستی/آخرین نسخه) برای مشتری
     *  خروجی: [PackageLicense, PackageVersion] یا JsonResponse خطا
     * =================================================================== */
    private function resolveLicensedVersion(Request $request, Customer $customer, string $slug): array|JsonResponse
    {
        $package = Package::where('slug', $slug)
            ->where('status', Package::STATUS_ACTIVE)
            ->first();

        if (!$package) {
            return response()->json(['error' => 'پکیج یافت نشد.'], 404);
        }

        // 🚩 گیت اشتراک: دانلود/ساخت لینک فقط با اشتراکِ فعالِ پرداخت‌شده و منقضی‌نشدهٔ
        // پوشش‌دهندهٔ پروژهٔ پکیج — یا اشتراکِ فعالِ طرح‌محوری که این پکیج را در
        // پیکربندی مؤثرِ مدتِ خریداری‌شده‌اش رایگان می‌کند (Task 66).
        if (!$this->subscriptionService->hasActiveSubscriptionForProject($customer, (int) $package->project_id)
            && !$this->subscriptionService->activeSubscriptionCovering($customer, $package)) {
            return response()->json([
                'error' => 'اشتراک فعالی وجود ندارد؛ دانلود پکیج فقط با اشتراک فعال امکان‌پذیر است.',
            ], 403);
        }

        $license = $this->authService->getActiveLicense($customer, $slug);

        if (!$license) {
            return response()->json([
                'error' => 'شما این پکیج را نخریده‌اید یا لایسنس شما فعال نیست.',
                'is_purchased' => false,
            ], 403);
        }

        $version = $request->filled('version')
            ? $package->versions()->where('version', $request->input('version'))->first()
            : $package->latestVersion()->first();

        if (!$version) {
            return response()->json(['error' => 'نسخه فعالی برای این پکیج وجود ندارد.'], 422);
        }

        return [$license, $version];
    }

    /* ===================================================================
     *  Helper - استریم فایل نسخه با هدرهای متادیتا
     * =================================================================== */
    private function streamVersionFile(\App\Models\PackageVersion $version): StreamedResponse
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
            'X-Package-Version'   => $version->version,
            'X-Package-Hash'      => $version->file_hash,
        ]);
    }
}
