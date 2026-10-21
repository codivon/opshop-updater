<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateDownloadToken extends Model
{
    protected $fillable = [
        'token', 'template_id', 'template_version_id', 'purchase_id', 'customer_id',
        'expires_at', 'used_at', 'ip',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
    ];

    /* ---------------- Relationships ---------------- */

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class, 'template_version_id');
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(TemplatePurchase::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /* ---------------- Helpers ---------------- */

    public function isValid(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }

    public function consume(?string $ip = null): void
    {
        $this->update([
            'used_at' => now(),
            'ip'      => $ip,
        ]);
    }
}
