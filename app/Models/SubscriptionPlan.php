<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * طرح اشتراک (Subscription Plan)
 *
 * طرحی که مشتریان از فروشگاه/API خریداری می‌کنند؛ پولی یا رایگان.
 * پس از پرداخت (یا ثبت رایگان)، مدیر درخواست را تأیید می‌کند →
 * اشتراک فعال + لایسنس رایگان پکیج‌های همراه طرح صادر می‌شود.
 */
class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'duration_months',
        'price',
        'discount_price',
        'is_one_time',
        'is_active',
        'sort_order',
        'features',
    ];

    protected $casts = [
        'duration_months' => 'integer',
        'price'           => 'integer',
        'discount_price'  => 'integer',
        'is_one_time'     => 'boolean',
        'is_active'       => 'boolean',
        'sort_order'      => 'integer',
        'features'        => 'array',
    ];

    /* ---------------------------------------------------------------- */
    /*  Relations                                                        */
    /* ---------------------------------------------------------------- */

    /** پکیج‌های همراه طرح (خرید طرح = دسترسی رایگان موقت به این پکیج‌ها) */
    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'subscription_plan_package')
            ->withPivot('free_months')
            ->withTimestamps();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(SubscriptionOrder::class, 'subscription_plan_id');
    }

    /** مدت‌های اعتبار طرح (چندگزینه‌ای) — مرتب بر اساس ترتیب نمایش و مدت */
    public function durations(): HasMany
    {
        return $this->hasMany(SubscriptionPlanDuration::class, 'subscription_plan_id')
            ->orderBy('sort_order')
            ->orderBy('duration_months');
    }

    /** فقط مدت‌های فعال (گزینه‌های قابل انتخاب در فروشگاه/API) */
    public function activeDurations(): HasMany
    {
        return $this->durations()->where('is_active', true);
    }

    /* ---------------------------------------------------------------- */
    /*  Attributes                                                       */
    /* ---------------------------------------------------------------- */

    /** مبلغ نهایی قابل پرداخت (قیمت − تخفیف، حداقل صفر) */
    public function getFinalPriceAttribute(): int
    {
        return max(0, (int) $this->price - (int) ($this->discount_price ?? 0));
    }

    public function getIsFreeAttribute(): bool
    {
        return $this->final_price <= 0;
    }

    /** برچسب مدت اعتبار طرح */
    public function getDurationLabelAttribute(): string
    {
        return match (true) {
            (int) $this->duration_months === 0 => 'نامحدود',
            (int) $this->duration_months === 1 => '۱ ماه',
            default => fa_num($this->duration_months) . ' ماه',
        };
    }

    /* ---------------------------------------------------------------- */
    /*  مدت‌های اعتبار (چندگزینه‌ای)                                      */
    /* ---------------------------------------------------------------- */

    /** آیا طرح حداقل یک مدت اعتبار فعال دارد؟ */
    public function hasDurations(): bool
    {
        if ($this->relationLoaded('durations')) {
            return $this->durations->contains(fn (SubscriptionPlanDuration $d) => $d->is_active);
        }

        return $this->durations()->where('is_active', true)->exists();
    }

    /** گزینه‌های مدت فعال (مرتب) */
    public function durationOptions()
    {
        if ($this->relationLoaded('durations')) {
            return $this->durations->filter(fn (SubscriptionPlanDuration $d) => $d->is_active)->values();
        }

        return $this->activeDurations()->get();
    }

    /** گزینهٔ مدت فعال با مدت مشخص — یا null (فال‌بک به تک‌مدت طرح) */
    public function durationOption(int $months): ?SubscriptionPlanDuration
    {
        return $this->durationOptions()->first(fn (SubscriptionPlanDuration $d) => (int) $d->duration_months === $months);
    }

    /** برچسب مدت دسترسی رایگان یک پکیج همراه */
    public static function freeMonthsLabel(int $months): string
    {
        return match (true) {
            $months === 0 => 'نامحدود',
            $months === 1 => '۱ ماه رایگان',
            default => fa_num($months) . ' ماه رایگان',
        };
    }

    /**
     * پکیج‌های همراه مؤثر برای یک مدت اعتبار (همراه pivot free_months) —
     * اگر مدت پیکربندی اختصاصی داشته باشد از همان، وگرنه از پکیج‌های خود طرح — Task 63
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Package>
     */
    public function packagesForDuration(?SubscriptionPlanDuration $duration)
    {
        if ($duration && $duration->hasOwnPackages()) {
            return $duration->packages()->get();
        }

        return $this->packages()->get(['packages.id', 'packages.name', 'packages.slug']);
    }

    /* ---------------------------------------------------------------- */
    /*  Helpers                                                          */
    /* ---------------------------------------------------------------- */

    /**
     * آیا این مشتری قبلاً از این طرح استفاده کرده است؟
     * (سفارش‌های pending/approved استفاده‌شده حساب می‌شوند؛ ردشده قابل تکرار است)
     */
    public function hasCustomerUsed(int $customerId): bool
    {
        return SubscriptionOrder::query()
            ->where('subscription_plan_id', $this->id)
            ->where('customer_id', $customerId)
            ->where('admin_status', '!=', SubscriptionOrder::ADMIN_STATUS_REJECTED)
            ->exists();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function generateSlug(string $name): string
    {
        $base = \Illuminate\Support\Str::slug($name) ?: 'plan';
        $slug = $base;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base . '-' . strtolower(\Illuminate\Support\Str::random(4));
        }

        return $slug;
    }
}
