<?php

namespace App\Console\Commands;

use App\Models\PackageLicense;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;

/**
 * Task 89 — همگام‌سازی لایسنس‌های پکیجِ صادرشده از طریق اشتراک‌ها
 *
 * قاعدهٔ کسب‌وکار: پکیجِ همراهِ اشتراک فقط تا انضای همان اشتراک معتبر است.
 *
 *  ۱) همهٔ اشتراک‌های فعال → انضای لایسنس‌های وابسته با انضای اشتراک همگام می‌شود
 *     (اصلاح دادهٔ خراب قدیمی مثل «لایسنس ۳۸ روز مانده برای اشتراکِ ۱۰ روز مانده»).
 *  ۲) لایسنس‌های وابسته به اشتراکِ منقضی/غیرفعال → وضعیت expired
 *     (اشتراک تمام شد → پکیج هم بسته می‌شود).
 *
 * اجرای دستی:  php artisan subscriptions:sync-licenses  (یا با --dry برای پیش‌نمایش)
 * زمان‌بندی:   روزانه (routes/console.php)
 */
class SyncSubscriptionLicenses extends Command
{
    protected $signature = 'subscriptions:sync-licenses {--dry : فقط نمایش، بدون تغییر}';

    protected $description = 'همگام‌سازی انضای لایسنس‌های صادرشده از طریق اشتراک‌ها و بستن لایسنس‌های اشتراک‌های منقضی';

    public function handle(SubscriptionService $service): int
    {
        $dry = (bool) $this->option('dry');

        // ---------------- ۱) همگام‌سازی اشتراک‌های فعال ----------------
        $active = Subscription::query()
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get();

        $this->info('اشتراک‌های فعال: '.$active->count());

        foreach ($active as $subscription) {
            $updated = $dry ? 0 : $service->syncSubscriptionLicenses($subscription);

            if ($updated > 0) {
                $this->line("  اشتراک #{$subscription->id} ({$subscription->plan?->name}) → {$updated} لایسنس همگام شد");
            }
        }

        // ---------------- ۲) بستن لایسنس‌های اشتراک‌های منقضی/غیرفعال ----------------
        $staleQuery = PackageLicense::query()
            ->whereNotNull('subscription_id')
            ->where('status', PackageLicense::STATUS_ACTIVE)
            ->whereHas('subscription', function ($q) {
                $q->where(function ($sub) {
                    $sub->where('status', '!=', 'active')
                        ->orWhere(function ($exp) {
                            $exp->whereNotNull('expires_at')->where('expires_at', '<=', now());
                        });
                });
            })
            ->with('subscription:id,plan_id,status,expires_at');

        $stale = $staleQuery->get();

        $this->info('لایسنس‌های وابسته به اشتراکِ منقضی: '.$stale->count());

        foreach ($stale as $license) {
            $this->line("  {$license->license_key} (اشتراک #{$license->subscription_id} — {$license->subscription?->status}) → منقضی");

            if (! $dry) {
                $license->update(['status' => PackageLicense::STATUS_EXPIRED]);
            }
        }

        if ($dry) {
            $this->info('(dry — هیچ تغییری ذخیره نشد)');
        }

        $this->info('انجام شد.');

        return self::SUCCESS;
    }
}
