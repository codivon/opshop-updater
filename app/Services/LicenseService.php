<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Package;
use App\Models\PackageLicense;
use App\Models\PackagePricingPlan;
use App\Models\PackagePurchase;
use App\Models\PackageVersion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use packages\shetabit\payment\src\Facade\Payment;
use Shetabit\Payment\Models\Transaction;

class LicenseService
{
    /**
     * صدور لایسنس پس از پرداخت موفق
     */
    public function issueLicense(PackagePurchase $purchase,PackagePricingPlan $plan): PackageLicense {

        return DB::transaction(function () use ($purchase, $plan) {
            $startsAt = now();
            $expiresAt = $plan->duration_months > 0
                ? Carbon::now()->addMonths($plan->duration_months)
                : null;

            $license = PackageLicense::create([
                'license_key'     => PackageLicense::generateKey(),
                'package_id'      => $purchase->package_id,
                'customer_id'     => $purchase->customer_id,
                'purchase_id'     => $purchase->id,
                'status'          => PackageLicense::STATUS_ACTIVE,
                'starts_at'       => $startsAt,
                'expires_at'      => $expiresAt,
                'duration_months' => $plan->duration_months,
            ]);

            $purchase->update(['license_id' => $license->id]);
            $purchase->package->incrementPurchases();

            return $license;
        });
    }

    /**
     * صدور یا تمدید لایسنس بر اساس وضعیت فعلی مشتری:
     * اگر مشتری برای همین پکیج لایسنس فعال/منقضی (غیر باطل‌شده) داشته باشد
     * ابتدا تمدید انجام می‌شود؛ در صورت خطا (طرح‌های یک‌بار مصرف) یا نبود لایسنس قبلی، لایسنس جدید صادر می‌شود.
     */
    public function issueOrRenew(PackagePurchase $purchase, PackagePricingPlan $plan): PackageLicense
    {
        $oldLicense = PackageLicense::query()
            ->where('package_id', $purchase->package_id)
            ->where('customer_id', $purchase->customer_id)
            ->whereIn('status', [PackageLicense::STATUS_ACTIVE, PackageLicense::STATUS_EXPIRED])
            ->latest('id')
            ->first();

        if ($oldLicense) {
            try {
                return $this->renewLicense($oldLicense, $purchase, $plan);
            } catch (\RuntimeException $e) {
                // طرح یک‌بار مصرف قابل تمدید نیست → لایسنس جدید صادر می‌کنیم
            }
        }

        return $this->issueLicense($purchase, $plan);
    }

    /**
     * تمدید لایسنس (برای لایسنس‌های منقضی یا در حال انقضا)
     * لایسنس جدید ساخته می‌شود و renewed_from به لایسنس قبلی اشاره می‌کند
     */
    public function renewLicense(PackageLicense $oldLicense,PackagePurchase $newPurchase,PackagePricingPlan $plan): PackageLicense {
        // جلوگیری از تمدید طرح‌های یک‌بار مصرف
        if ($plan->is_one_time) {
            throw new \RuntimeException(
                'این طرح یک‌بار مصرف است و قابل تمدید نیست. لطفاً طرح دیگری انتخاب کنید.'
            );
        }

        // اگر لایسنس قبلی از یک طرح one-time بوده، جلوگیری از تمدید
        $oldPlan = $oldLicense->purchase?->pricingPlan;
        if ($oldPlan && $oldPlan->is_one_time) {
            throw new \RuntimeException(
                'لایسنس فعلی شما از طرح یک‌بار مصرف است و قابل تمدید نیست. لطفاً طرح دیگری انتخاب کنید.'
            );
        }

        return DB::transaction(function () use ($oldLicense, $newPurchase, $plan) {
            // اگر لایسنس قبلی هنوز فعال است، تاریخ انقضا را اضافه می‌کنیم
            // در غیر این صورت، از الان شروع می‌شود
            $startsAt = $oldLicense->isActive() ? $oldLicense->starts_at : now();
            $baseDate = $oldLicense->isActive() && $oldLicense->expires_at
                ? $oldLicense->expires_at
                : now();

            $expiresAt = $plan->duration_months > 0
                ? (clone $baseDate)->addMonths($plan->duration_months)
                : null;

            $license = PackageLicense::create([
                'license_key'     => PackageLicense::generateKey(),
                'package_id'      => $oldLicense->package_id,
                'customer_id'     => $oldLicense->customer_id,
                'purchase_id'     => $newPurchase->id,
                'renewed_from'    => $oldLicense->id,
                'status'          => PackageLicense::STATUS_ACTIVE,
                'starts_at'       => $startsAt,
                'expires_at'      => $expiresAt,
                'duration_months' => $plan->duration_months,
            ]);

            // لایسنس قبلی را revoked می‌کنیم
            $oldLicense->update(['status' => PackageLicense::STATUS_REVOKED]);

            $newPurchase->update(['license_id' => $license->id]);

            return $license;
        });
    }

    /**
     * بررسی اعتبار لایسنس
     * Returns: ['valid' => bool, 'expires_at' => ?string, 'days_remaining' => ?int, 'version' => ?string]
     */
    public function verify(PackageLicense $license): array
    {
        if (!$license->isActive()) {
            return [
                'valid'         => false,
                'message'       => 'لایسنس غیرفعال یا منقضی است.',
                'expires_at'    => $license->expires_at?->toDateTimeString(),
                'days_remaining'=> $license->days_remaining,
            ];
        }

        $latestVersion = $license->package->latestVersion();

        return [
            'valid'         => true,
            'expires_at'    => $license->expires_at?->toDateTimeString(),
            'days_remaining'=> $license->days_remaining,
            'version'       => $latestVersion ? $latestVersion->first()->version : null,
            'is_unlimited'  => $license->isUnlimited(),
        ];
    }

    /**
     * مارک کردن لایسنس‌های منقضی‌شده به‌صورت خودکار
     */
    public function expireOldLicenses(): int
    {
        $count = PackageLicense::where('status', PackageLicense::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => PackageLicense::STATUS_EXPIRED]);

        if ($count > 0) {
            Log::info("Expired {$count} licenses");
        }

        return $count;
    }
}
