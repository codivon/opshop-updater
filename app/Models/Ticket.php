<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * تیکت پشتیبانی مشتری.
 *
 * گات: فقط مشتریِ دارای اشتراک فعال (پرداخت‌شده و منقضی‌نشده) می‌تواند تیکت ثبت کند —
 * بررسی در SubscriptionService::hasActiveSubscription و روی همهٔ مسیرها اعمال می‌شود.
 */
class Ticket extends Model
{
    public const STATUS_OPEN     = 'open';     // منتظر پاسخ مدیر
    public const STATUS_ANSWERED = 'answered'; // مدیر پاسخ داده — منتظر مشتری
    public const STATUS_CLOSED   = 'closed';   // بسته‌شده

    public const STATUS_LABELS = [
        self::STATUS_OPEN     => 'در انتظار پاسخ',
        self::STATUS_ANSWERED => 'پاسخ داده شد',
        self::STATUS_CLOSED   => 'بسته‌شده',
    ];

    protected $fillable = [
        'customer_id',
        'subscription_id',
        'subject',
        'status',
        'last_reply_at',
    ];

    protected $casts = [
        'last_reply_at' => 'datetime',
    ];

    /* ---------------------------------------------------------------- */
    /*  Relations                                                        */
    /* ---------------------------------------------------------------- */

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** اشتراکِ فعالِ لحظهٔ ثبت تیکت (فقط برای گزارش؛ حذف اشتراک تیکت را نمی‌شکند) */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class)->oldest('id');
    }

    public function firstReply(): ?TicketReply
    {
        return $this->replies()->first();
    }

    /* ---------------------------------------------------------------- */
    /*  Helpers                                                          */
    /* ---------------------------------------------------------------- */

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isAnswered(): bool
    {
        return $this->status === self::STATUS_ANSWERED;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /** تعداد تیکت‌های در انتظار پاسخ مدیر (برای نشان سایدبار) */
    public static function pendingCount(): int
    {
        return self::query()->where('status', self::STATUS_OPEN)->count();
    }
}
