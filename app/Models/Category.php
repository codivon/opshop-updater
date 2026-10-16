<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    public const TYPE_PRODUCT = 'product';
    public const TYPE_PACKAGE = 'package';

    protected $fillable = [
        'name', 'slug', 'type', 'description', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /* ---------------- Relationships ---------------- */

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }

    /* ---------------- Scopes ---------------- */

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /* ---------------- Helpers ---------------- */

    /** تعداد استفاده‌شده (پروژه + پکیج) برای جلوگیری از حذف در حال استفاده */
    public function usageCount(): int
    {
        return match ($this->type) {
            self::TYPE_PRODUCT => $this->products()->count(),
            self::TYPE_PACKAGE => $this->packages()->count(),
            default            => 0,
        };
    }

    public static function generateSlug(string $name): string
    {
        $base = \Illuminate\Support\Str::slug($name);

        if ($base === '') {
            $base = 'cat-' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6));
        }

        $slug = $base;
        $i = 2;

        while (self::where('slug', $slug)->where('id', '!=', request()?->route('category')?->id ?? 0)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
