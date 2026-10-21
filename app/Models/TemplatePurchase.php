<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * خرید قالب.
 *  - فروشگاه (مهمان): customer_id = null
 *  - API (کد آپدیت مشتری — مانند جریان پکیج‌ها): customer_id ست می‌شود
 *    و دانلود فقط برای همان مشتری مجاز است.
 */
class TemplatePurchase extends Model
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

    /** نوع خرید — معمولی یا اختصاصی */
    public const TYPE_NORMAL    = 'normal';
    public const TYPE_EXCLUSIVE = 'exclusive';

    public const TYPE_LABELS = [
        self::TYPE_NORMAL    => 'معمولی',
        self::TYPE_EXCLUSIVE => 'اختصاصی',
    ];

    protected $fillable = [
        'template_id', 'version_id', 'customer_id', 'transaction_id', 'discount_code_id',
        'original_amount', 'amount', 'discount_amount', 'purchase_type', 'license_id',
        'gateway', 'payment_url', 'callback_url', 'status', 'paid_at', 'meta',
    ];

    protected $casts = [
        'amount'  => 'integer',
        'paid_at' => 'datetime',
        'meta'    => 'array',
    ];

    /* ---------------- Relationships ---------------- */

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class);
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(TemplateLicense::class);
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

        if ($this->template) {
            $this->template->incrementSales();

            // خرید اختصاصی → قالب دیگر قابل فروش نیست (واگذاری کامل)
            if ($this->purchase_type === self::TYPE_EXCLUSIVE && ! $this->template->is_sold_exclusively) {
                $this->template->update([
                    'is_sold_exclusively' => true,
                    'sold_exclusively_at' => now(),
                ]);
            }
        }

        // صدور لایسنس (گواهی خرید) — درون issueFor گارد تکرار وجود دارد
        TemplateLicense::issueFor($this);

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

    /**
     * ساخت توکن دانلود یک‌بارمصرف ۱۵ دقیقه‌ای برای نسخه خرید (پیش‌فرض: آخرین نسخه فعال).
     * customer برای خریدهای API روی توکن ثبت می‌شود.
     */
    public function createDownloadToken(?Customer $customer = null): ?TemplateDownloadToken
    {
        $version = $this->version_id
            ? TemplateVersion::find($this->version_id)
            : $this->template->latestVersion()->first();

        if (!$version || !$version->isActive() || !$version->hasFile()) {
            return null;
        }

        return TemplateDownloadToken::create([
            'token'               => 'tdt_' . bin2hex(random_bytes(20)),
            'template_id'         => $this->template_id,
            'template_version_id' => $version->id,
            'purchase_id'         => $this->id,
            'customer_id'         => $customer?->id ?? $this->customer_id,
            'expires_at'          => now()->addMinutes(15),
        ]);
    }
}
