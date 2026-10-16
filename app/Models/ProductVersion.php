<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductVersion extends Model
{
    public const STATUS_DRAFT    = 'draft';
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_ARCHIVED = 'archived';

    public const TYPE_MAJOR = 'major';
    public const TYPE_MINOR = 'minor';
    public const TYPE_PATCH = 'patch';

    public const TYPE_LABELS = [
        self::TYPE_MAJOR => 'بزرگ (Major)',
        self::TYPE_MINOR => 'متوسط (Minor)',
        self::TYPE_PATCH => 'اصلاحی (Patch)',
    ];

    protected $fillable = [
        'product_id', 'version', 'type', 'changelog', 'what_added',
        'what_changed', 'what_fixed', 'file_path', 'file_hash', 'file_size',
        'is_mandatory', 'status', 'downloads_count', 'release_date',
    ];

    protected $casts = [
        'is_mandatory'    => 'boolean',
        'downloads_count' => 'integer',
        'release_date'    => 'datetime',
        'file_size'       => 'integer',
    ];

    /* ---------------- Relationships ---------------- */

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /* ---------------- Helpers ---------------- */

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function incrementDownloads(): void
    {
        $this->increment('downloads_count');
    }

    public function getAbsoluteFilePath(): string
    {
        return Storage::disk('local')->path($this->file_path);
    }

    public function hasFile(): bool
    {
        return $this->file_path && Storage::disk('local')->exists($this->file_path);
    }

    public function getFileSizeHumanAttribute(): string
    {
        if (!$this->file_size) {
            return '—';
        }

        $bytes  = (int) $this->file_size;
        $units  = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen((string) $bytes) - 1) / 3);

        return sprintf('%.2f %s', $bytes / pow(1024, $factor), $units[$factor] ?? 'B');
    }
}
