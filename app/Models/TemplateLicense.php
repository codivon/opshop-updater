<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * لایسنس قالب — گواهی مالکیت/حق استفاده که پس از پرداخت موفق صادر می‌شود.
 *
 *  - خرید قالب یک‌باره است → expires_at = null یعنی «دائمی».
 *  - برای خریدهای API که با کد آپدیت انجام می‌شوند، customer_id ثبت می‌شود.
 *  - خرید اختصاصی هم همین لایسنس را می‌گیرد (purchase_type = exclusive).
 */
class TemplateLicense extends Model
{
    public const STATUS_ACTIVE  = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'license_key', 'template_id', 'customer_id', 'purchase_id', 'purchase_type',
        'status', 'starts_at', 'expires_at', 'notes',
    ];

    protected $casts = [
        'starts_at'  => 'datetime',
        'expires_at' => 'datetime',
    ];

    /* ---------------- Relationships ---------------- */

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(TemplatePurchase::class);
    }

    /* ---------------- Helpers ---------------- */

    public function isActive(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function isUnlimited(): bool
    {
        return $this->expires_at === null;
    }

    public function revoke(): void
    {
        $this->update(['status' => self::STATUS_REVOKED]);
    }

    public function restore(): void
    {
        $this->update(['status' => self::STATUS_ACTIVE]);
    }

    /** TMP-XXXX-XXXX-XXXX-XXXX */
    public static function generateKey(): string
    {
        $segments = [];

        for ($i = 0; $i < 4; $i++) {
            $segments[] = strtoupper(Str::random(4));
        }

        return 'TMP-' . implode('-', $segments);
    }

    /** صدور لایسنس برای یک خرید پرداخت‌شده (idempotent: خرید دارای لایسنس، دوباره لایسنس نمی‌گیرد) */
    public static function issueFor(TemplatePurchase $purchase): ?self
    {
        if ($purchase->license_id) {
            return self::find($purchase->license_id);
        }

        $existing = self::where('purchase_id', $purchase->id)->first();

        if ($existing) {
            return $existing;
        }

        $license = self::create([
            'license_key'   => self::generateKey(),
            'template_id'   => $purchase->template_id,
            'customer_id'   => $purchase->customer_id,
            'purchase_id'   => $purchase->id,
            'purchase_type' => $purchase->purchase_type,
            'status'        => self::STATUS_ACTIVE,
            'starts_at'     => $purchase->paid_at ?? now(),
            'expires_at'    => null, // خرید قالب دائمی است
            'notes'         => $purchase->purchase_type === TemplatePurchase::TYPE_EXCLUSIVE
                ? 'خرید اختصاصی — واگذاری کامل حقوق قالب'
                : null,
        ]);

        $purchase->update(['license_id' => $license->id]);

        return $license;
    }
}
