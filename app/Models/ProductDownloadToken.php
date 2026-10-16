<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductDownloadToken extends Model
{
    protected $fillable = [
        'token', 'product_id', 'product_version_id', 'purchase_id',
        'expires_at', 'used_at', 'ip',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
    ];

    /* ---------------- Relationships ---------------- */

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ProductVersion::class, 'product_version_id');
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(ProductPurchase::class);
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
