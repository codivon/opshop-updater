<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPurchase extends Model
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_PAID     = 'paid';
    public const STATUS_FAILED   = 'failed';
    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_LABELS = [
        self::STATUS_PENDING  => 'در انتظار پرداخت',
        self::STATUS_PAID     => 'پرداخت شده',
        self::STATUS_FAILED   => 'ناموفق',
        self::STATUS_REFUNDED => 'بازپرداخت',
    ];

    protected $fillable = [
        'product_id', 'version_id', 'transaction_id', 'discount_code_id',
        'original_amount', 'amount', 'discount_amount', 'gateway',
        'payment_url', 'callback_url', 'status', 'paid_at', 'meta',
    ];

    protected $casts = [
        'amount'  => 'integer',
        'paid_at' => 'datetime',
        'meta'    => 'array',
    ];

    /* ---------------- Relationships ---------------- */

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ProductVersion::class);
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

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function markAsPaid(?string $gateway = null): void
    {
        // گارد idempotency: کال‌بک تکراری درگاه نباید فروش/شمارنده کد تخفیف را دوباره مصرف کند
        if ($this->isPaid()) {
            return;
        }

        $this->update([
            'status'  => self::STATUS_PAID,
            'paid_at' => now(),
            'gateway' => $gateway ?? $this->gateway,
        ]);

        if ($this->product) {
            $this->product->incrementSales();
        }

        // شمارنده استفاده از کد تخفیف فقط یک‌بار (همزمان با تأیید پرداخت) مصرف می‌شود
        $this->discountCode?->recordUsage();
    }

    public function markAsFailed(?string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'meta'   => array_merge($this->meta ?? [], ['fail_reason' => $reason]),
        ]);
    }

    /** ساخت توکن دانلود یک‌بارمصرف ۱۵ دقیقه‌ای برای آخرین نسخه فعال */
    public function createDownloadToken(): ?ProductDownloadToken
    {
        $version = $this->version_id
            ? ProductVersion::find($this->version_id)
            : $this->product->latestVersion()->first();

        if (!$version || !$version->isActive() || !$version->hasFile()) {
            return null;
        }

        return ProductDownloadToken::create([
            'token'             => 'pdt_' . bin2hex(random_bytes(20)),
            'product_id'        => $this->product_id,
            'product_version_id' => $version->id,
            'purchase_id'       => $this->id,
            'expires_at'        => now()->addMinutes(15),
        ]);
    }
}
