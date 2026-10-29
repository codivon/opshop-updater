<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * مدت اعتبار طرح اشتراک — هر طرح می‌تواند چند مدت داشته باشد
 * (مثلاً ۱ ماهه / ۳ ماهه / ۶ ماهه) و هر مدت قیمت و تخفیف مستقل خودش را دارد.
 *
 * طرح‌هایی که ردیفی ندارند از فیلدهای تک‌مدت خود طرح استفاده می‌کنند
 * (duration_months + price + discount_price) — سازگاری کامل با داده‌های قدیمی.
 */
class SubscriptionPlanDuration extends Model
{
    protected $fillable = [
        'subscription_plan_id',
        'duration_months',
        'price',
        'discount_price',
        'is_active',
        'sort_order',
        'features',
    ];

    protected $casts = [
        'duration_months' => 'integer',
        'price'           => 'integer',
        'discount_price'  => 'integer',
        'is_active'       => 'boolean',
        'sort_order'      => 'integer',
        'features'        => 'array',
    ];

    /** در سریالایز JSON/آرایه (از جمله API) همیشه لِیبل و مبلغ نهایی همراه است */
    protected $appends = ['duration_label', 'final_price', 'has_discount', 'discount_percent'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /** پکیج‌های همراه اختصاصی این مدت (نبود ردیف = ارث‌بری از پکیج‌های طرح) */
    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'subscription_plan_duration_package', 'subscription_plan_duration_id', 'package_id')
            ->withPivot('free_months')
            ->withTimestamps();
    }

    /* ---------------------------------------------------------------- */
    /*  Attributes                                                       */
    /* ---------------------------------------------------------------- */

    /** برچسب مدت اعتبار (۰ = نامحدود) */
    public function getDurationLabelAttribute(): string
    {
        return match (true) {
            (int) $this->duration_months === 0 => 'نامحدود',
            (int) $this->duration_months === 1 => '۱ ماه',
            default => fa_num($this->duration_months) . ' ماه',
        };
    }

    /** مبلغ نهایی قابل پرداخت برای این مدت (قیمت − تخفیف، حداقل صفر) */
    public function getFinalPriceAttribute(): int
    {
        return max(0, (int) $this->price - (int) ($this->discount_price ?? 0));
    }

    public function getIsFreeAttribute(): bool
    {
        return $this->final_price <= 0;
    }

    public function getHasDiscountAttribute(): bool
    {
        return (int) ($this->discount_price ?? 0) > 0 && (int) $this->price > (int) $this->discount_price;
    }

    /** درصد تخفیف گردشده (۰ تا ۹۹) — بدون تخفیف = ۰ */
    public function getDiscountPercentAttribute(): int
    {
        if (!$this->has_discount) {
            return 0;
        }

        return (int) min(99, round(((int) $this->price - $this->final_price) * 100 / (int) $this->price));
    }

    /* ---------------------------------------------------------------- */
    /*  پیکربندی مستقل هر مدت (قابلیت‌ها / پکیج‌ها) — Task 63              */
    /* ---------------------------------------------------------------- */

    /** آیا این مدت قابلیت‌های اختصاصی خودش را دارد؟ */
    public function hasOwnFeatures(): bool
    {
        return is_array($this->features) && $this->features !== [];
    }

    /** آیا این مدت پکیج‌های اختصاصی خودش را دارد؟ */
    public function hasOwnPackages(): bool
    {
        if ($this->relationLoaded('packages')) {
            return $this->packages->isNotEmpty();
        }

        return $this->packages()->exists();
    }

    /**
     * قابلیت‌های مؤثر این مدت — اختصاصی مدت یا فال‌بک به قابلیت‌های طرح
     * (فال‌بک اختیاری برای جلوگیری از کوئری اضافه وقتی features طرح در دسترس است)
     */
    public function resolvedFeatures(?array $planFeatures = null): array
    {
        if ($this->hasOwnFeatures()) {
            return array_values($this->features);
        }

        if ($planFeatures !== null) {
            return array_values($planFeatures);
        }

        return array_values($this->plan?->features ?? []);
    }

    /**
     * پکیج‌های مؤثر این مدت (همراه pivot free_months) —
     * اختصاصی مدت یا فال‌بک به پکیج‌های خود طرح (فال‌بک اختیاری برای جلوگیری از کوئری اضافه)
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Package>|null $fallback پکیج‌های طرح (در دسترس بودن)
     * @return \Illuminate\Support\Collection<int, \App\Models\Package>
     */
    public function resolvedPackages($fallback = null)
    {
        if ($this->hasOwnPackages()) {
            return $this->relationLoaded('packages') ? $this->packages : $this->packages()->get();
        }

        return $fallback ?? $this->plan?->packages ?? collect();
    }
}
