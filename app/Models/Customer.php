<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * مشتری فروشگاه.
 *
 * ورود مشتری فقط با «کد آپدیت» (بدون رمز) انجام می‌شود؛
 * بنابراین مدل اینجا Authenticatable است (گارد customer در config/auth.php)
 * اما getAuthPassword معنا ندارد — احراز هویت با update_code انجام می‌شود.
 */
class Customer extends Model implements AuthenticatableContract
{
    use HasFactory;
    use AuthenticatableTrait;

    public const STATUS_ACTIVE   = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'website_url',
        'update_code',
        'status',
    ];

    protected $hidden = [
        'remember_token',
    ];

    // رابطه یک‌به‌چند با مدل Update
    public function updates(): HasMany
    {
        return $this->hasMany(Update::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** سفارش‌های طرح اشتراک این مشتری */
    public function subscriptionOrders(): HasMany
    {
        return $this->hasMany(SubscriptionOrder::class);
    }

    /** خریدهای پکیج این مشتری */
    public function purchases(): HasMany
    {
        return $this->hasMany(PackagePurchase::class);
    }

    /** لایسنس‌های پکیج این مشتری */
    public function licenses(): HasMany
    {
        return $this->hasMany(PackageLicense::class);
    }

    /** آیا این مشتری اشتراک فعالِ طرح‌محور دارد؟ */
    public function activePlanSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->whereNotNull('subscription_plan_id')
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->with('plan.packages')
            ->orderByDesc('id')
            ->first();
    }

    // تولید کد آپدیت در صورت نیاز
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($customer) {
            if (empty($customer->update_code)) {
                $customer->update_code = self::generateUpdateCode();
            }
        });
    }

    public static function generateUpdateCode(): string
    {
        do {
            $code = strtoupper(bin2hex(random_bytes(6))); // تولید کد 12 کاراکتری
        } while (self::where('update_code', $code)->exists());

        return $code;
    }
}
