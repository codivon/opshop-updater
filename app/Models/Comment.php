<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * دیدگاه چندریختی — هم برای محصولات (پروژه‌های قابل‌فروش) و هم برای پکیج‌ها.
 *
 * commentable_type از morph map استفاده می‌کند: 'product' | 'package'
 */
class Comment extends Model
{
    public const TYPE_PRODUCT = 'product';
    public const TYPE_PACKAGE = 'package';

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const STATUS_LABELS = [
        self::STATUS_PENDING  => 'در انتظار تأیید',
        self::STATUS_APPROVED => 'تأیید شده',
        self::STATUS_REJECTED => 'رد شده',
    ];

    protected $fillable = [
        'commentable_type', 'commentable_id', 'name', 'email', 'rating',
        'body', 'status', 'admin_note', 'approved_at', 'ip',
    ];

    protected $casts = [
        'rating'      => 'integer',
        'approved_at' => 'datetime',
    ];

    /* ---------------- Relationships ---------------- */

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /* ---------------- Helpers ---------------- */

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /** برچسب نوع دیدگاه برای نمایش در ادمین */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->commentable_type) {
            self::TYPE_PRODUCT => 'محصول (پروژه)',
            self::TYPE_PACKAGE => 'پکیج',
            default            => $this->commentable_type,
        };
    }

    public function approve(): void
    {
        $this->update([
            'status'      => self::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
    }

    public function reject(?string $note = null): void
    {
        $this->update([
            'status'     => self::STATUS_REJECTED,
            'admin_note' => $note ?? $this->admin_note,
        ]);
    }

    public static function pendingCount(): int
    {
        return static::query()->where('status', self::STATUS_PENDING)->count();
    }
}
