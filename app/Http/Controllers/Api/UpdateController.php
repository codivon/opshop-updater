<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Update as UpdateModel;
use App\Services\PackageApiAuthService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Task 85 — امنیت (H1/M2): بازنویسی کامل کنترلر آپدیت با احراز هویت مشترکِ fail-closed:
 *  - توکن مشتری + اشتراک فعالِ پرداخت‌شده
 *  - دامنهٔ پروژه از هدر X-Project-Url (همان قرارداد بقیهٔ API) — بدون هدر یا با دامنهٔ خالی، دسترسی 403 است
 *  - دانلود آپدیت فقط از بین پروژه‌های خودِ مشتری (قبلاً هر آپدیتی با شمردن شناسه قابل دانلود بود)
 */
class UpdateController extends Controller
{
    /**
     * 1. بررسی نسخه (Check for Updates)
     */
    public function check(Request $request)
    {
        $customer = $this->authorizeCustomer($request);
        $currentVersion = (string) $request->input('version'); // مثلا 1.0.0

        // آخرین آپدیت منتشرشده برای پروژه‌های همین مشتری
        $latestUpdate = UpdateModel::query()
            ->whereIn('project_id', $customer->subscriptions()->pluck('project_id'))
            ->where('status', 'active')
            ->orderByDesc('version')
            ->first();

        if (! $latestUpdate) {
            return response()->json(['update_available' => false]);
        }

        if (version_compare($latestUpdate->version, $currentVersion, '<=')) {
            return response()->json(['update_available' => false]);
        }

        // ساخت لینک دانلود امن (توکن مشتری داخل لینک + اعتبارسنجی کامل در download)
        $downloadUrl = route('api.update.download', [
            'token' => $customer->update_code,
            'updateId' => $latestUpdate->id,
        ]);

        return response()->json([
            'update_available' => true,
            'version' => $latestUpdate->version,
            'description' => $latestUpdate->description,
            'download_url' => $downloadUrl,
            'file_size' => $latestUpdate->file_size ?? null,
        ]);
    }

    /**
     * 2. دانلود امن فایل (Secure Download)
     */
    public function download(Request $request, $updateId)
    {
        $customer = $this->authorizeCustomer($request);

        // Task 85 (H1): آپدیت فقط باید از پروژه‌های خودِ مشتری باشد — نه هر آپدیتی با شناسهٔ دلخواه
        $update = UpdateModel::query()
            ->whereIn('project_id', $customer->subscriptions()->pluck('project_id'))
            ->where('status', 'active')
            ->find($updateId);

        if (! $update) {
            return response()->json(['error' => 'آپدیت موردنظر برای پروژه‌های شما یافت نشد'], 404);
        }

        // از دیسک local استفاده کن (مسیر storage/app/private)
        if (! Storage::disk('local')->exists($update->download_link)) {
            return response()->json(['error' => 'فایل یافت نشد'], 404);
        }

        return Storage::disk('local')->download($update->download_link, $update->file_name);
    }

    /**
     * احراز هویت مشترک: توکن مشتری + اشتراک فعال + دامنهٔ پروژه (هدر X-Project-Url).
     * منطق دقیقاً مشترک با بقیهٔ API (PackageApiAuthService) — fail-closed.
     */
    private function authorizeCustomer(Request $request): Customer
    {
        try {
            $customer = app(PackageApiAuthService::class)->authenticate($request);
        } catch (RuntimeException $e) {
            throw new HttpResponseException(
                response()->json(['error' => $e->getMessage()], $e->getCode() ?: 403)
            );
        }

        $hasActiveSubscription = $customer->subscriptions()
            ->where('status', 'active')
            ->where('payment_status', 'paid')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();

        if (! $hasActiveSubscription) {
            throw new HttpResponseException(
                response()->json(['error' => 'اشتراک فعالی وجود ندارد'], 403)
            );
        }

        return $customer;
    }
}
