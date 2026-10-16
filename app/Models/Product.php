<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Product extends Model
{
    public const STATUS_DRAFT    = 'draft';
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_ARCHIVED = 'archived';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT    => 'پیش‌نویس',
        self::STATUS_ACTIVE   => 'فعال',
        self::STATUS_ARCHIVED => 'آرشیو',
    ];

    protected $fillable = [
        'category_id', 'name', 'slug', 'short_description', 'description',
        'thumbnail', 'price', 'discount', 'is_free', 'status', 'sort_order',
    ];

    protected $casts = [
        'price'       => 'integer',
        'discount'    => 'integer',
        'is_free'     => 'boolean',
        'sort_order'  => 'integer',
        'sales_count' => 'integer',
        'views_count' => 'integer',
    ];

    /* ---------------- Relationships ---------------- */

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ProductVersion::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(ProductPurchase::class);
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /* ---------------- Scopes ---------------- */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->latest('id');
    }

    /* ---------------- Accessors / Helpers ---------------- */

    /** آخرین نسخه‌ی فعال */
    public function latestVersion()
    {
        return $this->hasOne(ProductVersion::class)
            ->whereIn('status', [ProductVersion::STATUS_ACTIVE])
            ->orderByDesc('id');
    }

    public function activeVersions()
    {
        return $this->versions()->where('status', ProductVersion::STATUS_ACTIVE)->orderByDesc('id');
    }

    /** قیمت نهایی پس از تخفیف (پروژه‌ی رایگان = 0) */
    public function getFinalPriceAttribute(): int
    {
        if ($this->is_free) {
            return 0;
        }

        if ($this->price <= 0) {
            return 0;
        }

        if ($this->discount > 0 && $this->discount < 100) {
            return (int) max(0, round($this->price * (100 - $this->discount) / 100));
        }

        return $this->discount >= 100 ? 0 : (int) $this->price;
    }

    /** آیا مسیر دانلود رایگان دارد؟ */
    public function getIsEffectivelyFreeAttribute(): bool
    {
        return $this->is_free || $this->final_price <= 0;
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if ($this->thumbnail) {
            // مسیر public یا fallback storage (سرو با روت uploads.serve)
            return asset($this->thumbnail);
        }

        return asset('images/package-default.png');
    }

    /** میانگین امتیاز دیدگاه‌های تأییدشده */
    public function getRatingAttribute(): ?float
    {
        $avg = $this->comments()
            ->where('status', Comment::STATUS_APPROVED)
            ->whereNotNull('rating')
            ->avg('rating');

        return $avg ? round((float) $avg, 1) : null;
    }

    public function approvedCommentsCount(): int
    {
        return $this->comments()->where('status', Comment::STATUS_APPROVED)->count();
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function incrementViews(): void
    {
        $this->increment('views_count');
    }

    public function incrementSales(): void
    {
        $this->increment('sales_count');
    }

    public static function generateSlug(string $name, ?int $ignoreId = null): string
    {
        $base = \Illuminate\Support\Str::slug($name);

        if ($base === '') {
            $base = 'product-' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6));
        }

        $slug = $base;
        $i = 2;

        while (self::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
