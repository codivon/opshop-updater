<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    protected $casts = [
        'duration_months' => 'integer',
        'price'           => 'integer',
        'discount_price'  => 'integer',
        'is_active'       => 'boolean',
        'sort_order'      => 'integer',
    ];

    /** در سریالایز JSON/آرایه (از جمله API) همیشه لِیبل و مبلغ نهایی همراه است */
    protected $appends = ['duration_label', 'final_price', 'has_discount', 'discount_percent'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
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
}
