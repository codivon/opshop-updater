<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * سفارش اشتراک (Subscription Order)
 *
 * ثبت خرید یک طرح از سوی مشتری (فروشگاه یا API):
 *  - status: وضعیت پرداخت (pending/paid/failed)
 *  - admin_status: وضعیت تأیید مدیر (pending/approved/rejected)
 *  - پس از تأیید مدیر: subscription_id + starts_at/expires_at پر می‌شوند
 *    و لایسنس رایگان پکیج‌های همراه طرح صادر می‌شود.
 */
class SubscriptionOrder extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID    = 'paid';
    public const STATUS_FAILED  = 'failed';

    public const ADMIN_STATUS_PENDING  = 'pending';
    public const ADMIN_STATUS_APPROVED = 'approved';
    public const ADMIN_STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'subscription_plan_id',
        'customer_id',
        'discount_code_id',
        'amount',
        'discount',
        'final_amount',
        'gateway',
        'transaction_id',
        'payment_url',
        'callback_url',
        'status',
        'admin_status',
        'paid_at',
        'approved_at',
        'rejected_at',
        'rejected_reason',
        'subscription_id',
        'starts_at',
        'expires_at',
        'meta',
    ];

    protected $casts = [
        'amount'       => 'integer',
        'discount'     => 'integer',
        'final_amount' => 'integer',
        'paid_at'      => 'datetime',
        'approved_at'  => 'datetime',
        'rejected_at'  => 'datetime',
        'starts_at'    => 'datetime',
        'expires_at'   => 'datetime',
        'meta'         => 'array',
    ];

    /* ---------------------------------------------------------------- */
    /*  Relations                                                        */
    /* ---------------------------------------------------------------- */

    /** طرح خریداری‌شده (ممکن است حذف شده باشد → snapshot در meta) */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /* ---------------------------------------------------------------- */
    /*  Status helpers                                                   */
    /* ---------------------------------------------------------------- */

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function markAsPaid(?string $gateway = null): void
    {
        // گارد idempotency: کال‌بک تکراری درگاه نباید شمارنده کد تخفیف را دوباره مصرف کند
        if ($this->isPaid()) {
            return;
        }

        $this->update([
            'status'   => self::STATUS_PAID,
            'paid_at'  => now(),
            'gateway'  => $gateway ?? $this->gateway,
        ]);

        // شمارنده استفاده از کد تخفیف فقط یک‌بار (همزمان با تأیید پرداخت) مصرف می‌شود
        $this->discountCode?->recordUsage();
    }

    public function markAsFailed(?string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
        ]);

        if ($reason) {
            $this->forceFill(['meta->fail_reason' => $reason])->save();
        }
    }

    /** برچسب وضعیت پرداخت (فارسی) */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PAID    => 'پرداخت شده',
            self::STATUS_FAILED  => 'ناموفق',
            default              => 'در انتظار پرداخت',
        };
    }

    /** برچسب وضعیت تأیید مدیر (فارسی) */
    public function getAdminStatusLabelAttribute(): string
    {
        return match ($this->admin_status) {
            self::ADMIN_STATUS_APPROVED => 'تأیید شده',
            self::ADMIN_STATUS_REJECTED => 'رد شده',
            default                     => 'در انتظار تأیید مدیر',
        };
    }

    /** نام طرح (با fallback به snapshot ذخیره‌شده) */
    public function getPlanNameAttribute(): string
    {
        return $this->plan?->name
            ?? $this->meta['plan']['name']
            ?? 'طرح حذف‌شده';
    }
}
