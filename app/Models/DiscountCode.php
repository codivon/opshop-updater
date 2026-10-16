<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * کد تخفیف (کوپن) — قابل اعمال روی پکیج، محصول و طرح اشتراک.
 *
 *  - TYPE_PERCENT: «value» درصد (۱..۱۰۰)
 *  - TYPE_FIXED:   «value» مبلغ ثابت به تومان (کاپ روی مبلغ خرید)
 *
 *  محدودیت‌ها:
 *  - expires_at: تاریخ انقضا (null = بدون انقضا)
 *  - max_uses:   حداکثر تعداد استفاده کل (null = نامحدود) — با used_count کنترل می‌شود
 *  - applies_packages / applies_products / applies_subscriptions: محدوده اعتبار
 */
class DiscountCode extends Model
{
    public const TYPE_PERCENT = 'percent';
    public const TYPE_FIXED   = 'fixed';

    /** کانتکست‌های اعمال کد (برای راستی‌آزمایی محدوده) */
    public const CONTEXT_PACKAGE       = 'package';
    public const CONTEXT_PRODUCT       = 'product';
    public const CONTEXT_SUBSCRIPTION  = 'subscription';

    public const TYPE_LABELS = [
        self::TYPE_PERCENT => 'درصدی',
        self::TYPE_FIXED   => 'مبلغ ثابت',
    ];

    protected $fillable = [
        'code', 'type', 'value', 'max_uses', 'used_count', 'expires_at',
        'applies_packages', 'applies_products', 'applies_subscriptions',
        'is_active', 'notes',
    ];

    protected $casts = [
        'value'                  => 'integer',
        'max_uses'               => 'integer',
        'used_count'             => 'integer',
        'expires_at'             => 'datetime',
        'applies_packages'       => 'boolean',
        'applies_products'       => 'boolean',
        'applies_subscriptions'  => 'boolean',
        'is_active'              => 'boolean',
    ];

    /* ---------------------------------------------------------------- */
    /*  Relationships                                                    */
    /* ---------------------------------------------------------------- */

    public function packagePurchases(): HasMany
    {
        return $this->hasMany(PackagePurchase::class);
    }

    public function productPurchases(): HasMany
    {
        return $this->hasMany(ProductPurchase::class);
    }

    public function subscriptionOrders(): HasMany
    {
        return $this->hasMany(SubscriptionOrder::class);
    }

    /* ---------------------------------------------------------------- */
    /*  Scopes                                                           */
    /* ---------------------------------------------------------------- */

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /* ---------------------------------------------------------------- */
    /*  Helpers                                                          */
    /* ---------------------------------------------------------------- */

    /** آیا کد از نظر فعال‌بودن/انقضا/ظرفیت در وضعیت قابل استفاده است؟ */
    public function isUsable(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return $this->hasRemainingUses();
    }

    /** آیا ظرفیت استفاده باقی مانده است؟ */
    public function hasRemainingUses(): bool
    {
        return $this->max_uses === null || $this->used_count < $this->max_uses;
    }

    /** تعداد استفاده‌های باقی‌مانده (null = نامحدود) */
    public function remainingUses(): ?int
    {
        if ($this->max_uses === null) {
            return null;
        }

        return max(0, $this->max_uses - $this->used_count);
    }

    /** آیا کد برای این نوع خرید قابل استفاده است؟ */
    public function appliesTo(string $context): bool
    {
        return match ($context) {
            self::CONTEXT_PACKAGE      => $this->applies_packages,
            self::CONTEXT_PRODUCT      => $this->applies_products,
            self::CONTEXT_SUBSCRIPTION => $this->applies_subscriptions,
            default                    => false,
        };
    }

    /**
     * محاسبه مبلغ تخفیف برای یک مبلغ مشخص (بدون کسر بیشتر از خود مبلغ).
     */
    public function calculateDiscount(int $amount): int
    {
        if ($amount <= 0) {
            return 0;
        }

        $discount = $this->type === self::TYPE_PERCENT
            ? (int) floor($amount * min(100, max(0, $this->value)) / 100)
            : (int) $this->value;

        return max(0, min($discount, $amount));
    }

    /**
     * افزایش اتمیک شمارنده استفاده (با گارد ظرفیت تا از اضافه‌برداشت جلوگیری شود).
     * در markAsPaid مدل‌های خرید صدا زده می‌شود.
     */
    public function recordUsage(): void
    {
        static::query()
            ->whereKey($this->getKey())
            ->where(fn ($q) => $q->whereNull('max_uses')->orWhereRaw('used_count < max_uses'))
            ->increment('used_count');

        $this->refresh();
    }

    /** برچسب خوانا: «۱۵٪ تخفیف» یا «۵۰٫۰۰۰ تومان تخفیف» */
    public function valueLabel(): string
    {
        return $this->type === self::TYPE_PERCENT
            ? fa_num($this->value) . '٪ تخفیف'
            : money($this->value) . ' تخفیف';
    }

    /** وضعیت کد برای ادمین: فعال / منقضی / تکمیل ظرفیت / غیرفعال */
    public function statusLabel(): array
    {
        if (!$this->is_active) {
            return ['غیرفعال', 'neutral'];
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return ['منقضی شده', 'danger'];
        }

        if (!$this->hasRemainingUses()) {
            return ['تکمیل ظرفیت', 'warning'];
        }

        return ['فعال', 'success'];
    }

    /**
     * کد را نرمال می‌کند: حروف بزرگ + حذف فاصله‌ها.
     */
    public static function normalize(?string $code): string
    {
        return strtoupper(trim(en_num((string) $code)));
    }
}
