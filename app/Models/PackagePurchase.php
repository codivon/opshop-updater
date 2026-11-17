<?php

namespace App\Models;

use App\Models\Concerns\HasPurchaseAccessToken;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackagePurchase extends Model
{
    use HasFactory;
    use HasPurchaseAccessToken;

    protected $fillable = [
        'package_id', 'version_id', 'pricing_plan_id', 'customer_id',
        'license_id', 'transaction_id', 'discount_code_id',
        'original_amount', 'amount', 'discount_amount',
        'callback_url', 'gateway', 'payment_url', 'status', 'paid_at', 'meta',
        'access_token',
        'reserved_activate_at', 'reserved_source_license_id', 'reserved_activated_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'paid_at' => 'datetime',
        'meta' => 'array',
        'reserved_activate_at' => 'datetime',
        'reserved_activated_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    /* ---------------- Relationships ---------------- */

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(PackageVersion::class);
    }

    public function pricingPlan(): BelongsTo
    {
        return $this->belongsTo(PackagePricingPlan::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(PackageLicense::class);
    }

    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class);
    }

    /* ---------------- Helpers ---------------- */

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function markAsPaid(?string $gateway = null): void
    {
        // گارد idempotency: کال‌بک تکراری درگاه نباید شمارنده کد تخفیف/لایسنس را دوباره مصرف کند
        if ($this->isPaid()) {
            return;
        }

        $this->update([
            'status' => self::STATUS_PAID,
            'paid_at' => now(),
            'gateway' => $gateway ?? $this->gateway,
        ]);

        // شمارنده استفاده از کد تخفیف فقط یک‌بار (همزمان با تأیید پرداخت) مصرف می‌شود
        $this->discountCode?->recordUsage();
    }

    public function markAsFailed(?string $reason = null): void
    {
        // Task 85 — امنیت: خریدِ پرداخت‌شده هرگز نباید با کال‌بک هم‌زمان به «ناموفق» برگردد
        if ($this->isPaid()) {
            return;
        }

        $this->update([
            'status' => self::STATUS_FAILED,
            'meta' => array_merge($this->meta ?? [], ['fail_reason' => $reason]),
        ]);
    }

    /* ---------------- Task 87 — رزرو تمدید ---------------- */

    /** آیا این خرید «رزروشده» است؟ (پرداخت شده اما لایسنسش پس از پایان دورهٔ فعلی صادر می‌شود) */
    public function isReserved(): bool
    {
        return $this->reserved_activate_at !== null;
    }

    /** آیا رزرو هنوز فعال نشده؟ */
    public function isReservedPending(): bool
    {
        return $this->isReserved() && $this->reserved_activated_at === null;
    }
}
