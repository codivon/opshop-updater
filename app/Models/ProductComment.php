<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductComment extends Model
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const STATUS_LABELS = [
        self::STATUS_PENDING  => 'در انتظار تأیید',
        self::STATUS_APPROVED => 'تأیید شده',
        self::STATUS_REJECTED => 'رد شده',
    ];

    protected $fillable = [
        'product_id', 'name', 'email', 'rating', 'body', 'status',
        'admin_note', 'approved_at', 'ip',
    ];

    protected $casts = [
        'rating'      => 'integer',
        'approved_at' => 'datetime',
    ];

    /* ---------------- Relationships ---------------- */

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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
