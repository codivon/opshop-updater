<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'project_id',
        'subscription_plan_id',
        'subscription_order_id',
        'start_date',
        'end_date',
        'expires_at',
        'status',
        'price',
        'discount',
        'final_amount',
        'payment_status',
        'description',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'expires_at' => 'datetime',
        'price' => 'decimal:2',
        'discount' => 'decimal:2',
        'final_amount' => 'decimal:2',
    ];

    // روابط
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** طرح اشتراکی که این رکورد از آن صادر شده (null برای اشتراک‌های پروژه‌ای دستی) */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /** سفارشی که منجر به این اشتراک شد */
    public function order(): BelongsTo
    {
        return $this->belongsTo(SubscriptionOrder::class, 'subscription_order_id');
    }

    /**
     * پکیج‌های همراهِ مؤثر این اشتراک — با همان اولویتی که SubscriptionService::approve()
     * لایسنس‌ها را صادر کرده است (Task 66 — رفع ناسازگاری بررسی API با پیکربندی هر مدت):
     *
     *  ۱) snapshotِ پیکربندی مؤثرِ «مدتِ انتخابیِ مشتری» در لحظهٔ خرید
     *     (order.meta.chosen_duration.packages — منبع واقعی صدور لایسنس‌ها)
     *  ۲) پکیج‌های فعلی سطحِ طرح (قرارداد قدیمیِ پیش از پیکربندی هر مدت)
     *  ۳) snapshotِ سطحِ طرح در سفارش (وقتی طرح حذف شده است)
     *
     * هر آیتم دارای pivot «free_months» است (مدت رایگان؛ ۰ = نامحدود).
     */
    public function effectivePackages(): Collection
    {
        $orderMeta = $this->order?->meta;

        // ۱) snapshotِ مدتِ انتخابی (منبع واقعی صدور لایسنس در approve)
        $durationSnap = is_array($orderMeta) ? ($orderMeta['chosen_duration']['packages'] ?? []) : [];

        if (is_array($durationSnap) && $durationSnap !== []) {
            return $this->hydrateSnapshotPackages($durationSnap);
        }

        // ۲) پکیج‌های فعلی سطح طرح (رابطهٔ BelongsToMany با withPivot('free_months'))
        if ($this->plan) {
            return $this->plan->packages;
        }

        // ۳) طرح حذف‌شده → snapshotِ سطحِ طرحِ سفارش
        $planSnap = is_array($orderMeta) ? ($orderMeta['plan']['packages'] ?? []) : [];

        if (is_array($planSnap) && $planSnap !== []) {
            return $this->hydrateSnapshotPackages($planSnap);
        }

        return collect();
    }

    /** ساخت کالکشن پکیج از snapshot سفارش (id/name/slug + free_months) با pivot هیدرات‌شده */
    private function hydrateSnapshotPackages(array $snap): Collection
    {
        return Package::query()
            ->whereIn('id', array_column($snap, 'id'))
            ->get()
            ->map(function (Package $p) use ($snap) {
                $row = collect($snap)->firstWhere('id', $p->id);
                $p->pivot = (object) ['free_months' => (int) ($row['free_months'] ?? 1)];

                return $p;
            })
            ->values();
    }

    // محاسبه خودکار مبلغ نهایی قبل از ذخیره
    public static function boot()
    {
        parent::boot();

        static::saving(function ($subscription) {
            $subscription->final_amount = $subscription->price - $subscription->discount;
            if ($subscription->final_amount < 0) {
                $subscription->final_amount = 0;
            }
        });
    }

    // متد کمکی برای بررسی انقضا
    public function isExpired(): bool
    {
        if (!$this->expires_at) return false;
        return Carbon::now()->greaterThan($this->expires_at);
    }

    /** تاریخ انقضای مؤثر — expires_at یا در نبودش end_date (هم‌سو با صفحهٔ اشتراک‌ها) */
    public function getEffectiveExpiresAtAttribute()
    {
        return $this->expires_at ?? $this->end_date;
    }

    // فرمت کردن قیمت به تومان
    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price) . ' تومان';
    }

    public function getFormattedFinalAmountAttribute(): string
    {
        return number_format($this->final_amount) . ' تومان';
    }
}
