<?php

namespace App\Console\Commands;

use App\Models\PackagePurchase;
use App\Models\Subscription;
use App\Services\RenewalService;
use Illuminate\Console\Command;

/**
 * Task 87 — فعال‌سازی خودکار رزروهای تمدید
 *
 *  ۱) خریدهای پکیجِ «رزروشده» (پرداخت‌شده اما در انتظار پایان دورهٔ فعلی مشتری)
 *     → صدور لایسنس از زمان پایان دورهٔ فعلی + پیامک فعال‌سازی.
 *  ۲) اشتراک‌های «رزروشده» (تأییدشده اما در انتظار پایان اشتراک فعلی)
 *     → ست‌کردن تاریخ‌ها + صدور لایسنس پکیج‌های همراه + پیامک فعال‌سازی.
 *
 * اجرای دستی:  php artisan renewals:activate  (یا با --dry برای پیش‌نمایش)
 * زمان‌بندی:   هر دقیقه (routes/console.php) — فعال‌سازی دقیقاً بعد از انقضا
 */
class ActivateReservedRenewals extends Command
{
    protected $signature = 'renewals:activate {--dry : فقط نمایش، بدون تغییر}';

    protected $description = 'فعال‌سازی خودکار خریدها و اشتراک‌های رزروشده پس از پایان دورهٔ فعلی مشتری';

    public function handle(RenewalService $renewals): int
    {
        $dry = (bool) $this->option('dry');

        // ---------------- ۱) خریدهای پکیجِ رزروشده ----------------
        $purchases = PackagePurchase::query()
            ->where('status', PackagePurchase::STATUS_PAID)
            ->whereNull('license_id')
            ->whereNotNull('reserved_activate_at')
            ->whereNull('reserved_activated_at')
            ->with(['customer', 'package', 'pricingPlan'])
            ->get();

        $duePurchases = $purchases->filter(fn ($p) => $p->reserved_activate_at !== null && now()->greaterThanOrEqualTo($p->reserved_activate_at));

        $this->info('خریدهای رزروشدهٔ در انتظار: '.$purchases->count().' (سررسید: '.$duePurchases->count().')');

        foreach ($duePurchases as $purchase) {
            $this->line("  پکیج «{$purchase->package?->name}» برای {$purchase->customer?->name} → فعال‌سازی");

            if ($dry) {
                continue;
            }

            $license = $renewals->activateReservedPurchase($purchase);

            if ($license) {
                $this->info("    لایسنس صادر شد: {$license->license_key}");
            } else {
                $this->line('    (رد شد — لایسنس فعال موجود یا قبلاً فعال‌شده)');
            }
        }

        // ---------------- ۲) اشتراک‌های رزروشده ----------------
        $subscriptions = Subscription::query()
            ->where('status', Subscription::STATUS_RESERVED)
            ->with(['customer', 'plan', 'order'])
            ->get();

        $dueSubscriptions = $subscriptions->filter(function ($sub) {
            $activateAt = $sub->reserved_activate_at;

            if (! $activateAt && is_array($sub->order?->meta)) {
                $activateAt = $sub->order->meta['renewal']['activate_at'] ?? null;
            }

            return $activateAt !== null && now()->greaterThanOrEqualTo($activateAt);
        });

        $this->info('اشتراک‌های رزروشدهٔ در انتظار: '.$subscriptions->count().' (سررسید: '.$dueSubscriptions->count().')');

        foreach ($dueSubscriptions as $subscription) {
            $this->line("  طرح «{$subscription->plan?->name}» برای {$subscription->customer?->name} → فعال‌سازی");

            if ($dry) {
                continue;
            }

            $activated = $renewals->activateReservedSubscription($subscription);

            if ($activated) {
                $this->info('    اشتراک فعال شد تا: '.($activated->expires_at?->format('Y/m/d') ?? 'نامحدود'));
            } else {
                $this->line('    (رد شد — اشتراک فعال‌تر موجود یا قبلاً فعال‌شده)');
            }
        }

        $this->info('انجام شد.');

        return self::SUCCESS;
    }
}
