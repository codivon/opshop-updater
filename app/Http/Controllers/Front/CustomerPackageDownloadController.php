<?php

namespace App\Http\Controllers\Front;

use App\Models\PackageLicense;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * دانلود پکیج برای مشتری واردشده‌ی فروشگاه (پروفایل مشتری).
 *
 * مسیر: GET /customer/download/{license}
 * فقط لایسنس فعالِ متعلق به مشتریِ لاگین‌شده مجاز است؛
 * فایل آخرین نسخه‌ی فعال پکیج استریم می‌شود.
 */
class CustomerPackageDownloadController
{
    public function download(PackageLicense $license): BinaryFileResponse
    {
        /** @var \App\Models\Customer|null $customer */
        $customer = auth('customer')->user();
        abort_if(!$customer, 403);
        abort_unless($license->customer_id === $customer->id, 403);
        abort_unless($license->status === PackageLicense::STATUS_ACTIVE, 403, 'لایسنس این پکیج فعال نیست.');

        $version = $license->package?->latestVersion()->first();
        abort_if(!$version, 404, 'نسخه‌ی فعالی برای این پکیج وجود ندارد.');
        abort_if(!$version->file_path || !Storage::disk('local')->exists($version->file_path), 404, 'فایل این نسخه یافت نشد.');

        $version->incrementDownloads();
        $version->package->incrementDownloads();

        $filename = $license->package->slug . '-v' . str_replace([' ', '/'], '-', (string) $version->version) . '.zip';

        return Storage::disk('local')->download($version->file_path, $filename);
    }
}
