<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * پاسخ تیکت — مشتری یا مدیر.
 */
class TicketReply extends Model
{
    public const AUTHOR_CUSTOMER = 'customer';
    public const AUTHOR_ADMIN    = 'admin';

    protected $fillable = [
        'ticket_id',
        'author_type',
        'body',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function isFromAdmin(): bool
    {
        return $this->author_type === self::AUTHOR_ADMIN;
    }

    /** نام نمایش‌دهندهٔ نویسنده (مدیر = مدیر پشتیبانی) */
    public function authorLabel(): string
    {
        return $this->isFromAdmin() ? 'پشتیبانی' : ($this->ticket->customer->name ?? 'مشتری');
    }
}
