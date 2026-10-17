<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackagePricingPlan extends Model
{
    use HasFactory;

    protected $table = 'package_pricing_plans';

    protected $fillable = [
        'package_id', 'name', 'duration_months', 'price',
        'discount_price', 'is_one_time', 'description', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'duration_months' => 'integer',
        'price'           => 'integer',
        'discount_price'  => 'integer',
        'is_active'       => 'boolean',
        'is_one_time'     => 'boolean',
        'sort_order'      => 'integer',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function purchases()
    {
        return $this->hasMany(PackagePurchase::class, 'pricing_plan_id');
    }

    public function getFinalPriceAttribute(): int
    {
        // FIX (طرح رایگان با discount_price=0): مقدار صفر یعنی «بدون تخفیف» نه «رایگان»؛
        // ادمین برای بدون‌تخفیف null ذخیره می‌کند ولی اگر جایی صفر ذخیره شود، قبلاً
        // final_price=0 (رایگان) نمایش داده می‌شد. حالا فقط تخفیفِ معتبر (>۰ و <قیمت) اعمال می‌شود.
        if ($this->discount_price !== null && $this->discount_price > 0 && $this->discount_price < $this->price) {
            return (int) $this->discount_price;
        }

        return (int) $this->price;
    }

    public function getHasDiscountAttribute(): bool
    {
        return $this->discount_price !== null
            && $this->discount_price < $this->price;
    }

    public function getDiscountPercentAttribute(): int
    {
        if (!$this->has_discount || $this->price <= 0) {
            return 0;
        }
        return (int) round((1 - $this->discount_price / $this->price) * 100);
    }

    public function getDurationLabelAttribute(): string
    {
        if ($this->duration_months === 0) {
            return 'نامحدود';
        }
        if ($this->duration_months < 12) {
            return $this->duration_months . ' ماه';
        }
        $years = $this->duration_months / 12;
        return ($years == floor($years) ? (int) $years : $years) . ' سال';
    }

    /**
     * آیا این طرح یک‌بار مصرف است؟
     */
    public function isOneTime(): bool
    {
        return $this->is_one_time;
    }

    /**
     * بررسی اینکه آیا مشتری قبلاً این طرح one-time را خریده است یا خیر
     */
    public function hasCustomerUsed(int $customerId): bool
    {
        if (!$this->is_one_time) {
            return false;
        }

        return PackagePurchase::where('customer_id', $customerId)
            ->where('pricing_plan_id', $this->id)
            ->where('status', PackagePurchase::STATUS_PAID)
            ->exists();
    }
}
