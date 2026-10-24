<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
