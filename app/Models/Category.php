<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    public const TYPE_PRODUCT = 'product';
    public const TYPE_PACKAGE = 'package';

    /** حداکثر عمق درخت دسته‌بندی (محافظ در برابر حلقه) */
    public const MAX_DEPTH = 5;

    protected $fillable = [
        'name', 'slug', 'type', 'parent_id', 'description', 'is_active', 'sort_order',
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

    /** دسته والد (برای دسته‌بندی درختی) */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** زیردسته‌ها */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
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

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /* ---------------- Hierarchy helpers ---------------- */

    /**
     * شناسه‌های همه زیردسته‌های یک دسته + خود آن دسته (BFS با محافظ حلقه).
     * برای فیلتر فروشگاه: انتخاب دسته والد → نمایش پکیج‌های خودش و همه فرزندانش.
     *
     * @return Collection<int, int>
     */
    public static function subtreeIds(int $rootId, ?Collection $pool = null): Collection
    {
        $pool ??= static::query()->get(['id', 'parent_id']);

        $ids = collect([$rootId]);
        $frontier = [$rootId];
        $guard = 0;

        while ($frontier !== [] && $guard++ < self::MAX_DEPTH * 10) {
            $next = [];

            foreach ($pool->whereIn('parent_id', $frontier) as $child) {
                if (! $ids->contains($child->id)) {
                    $ids->push($child->id);
                    $next[] = $child->id;
                }
            }

            $frontier = $next;
        }

        return $ids->values();
    }

    /** آیا دسته، خودش یا یکی از زیردسته‌های $rootId است؟ (جلوگیری از حلقه در انتخاب والد) */
    public static function inSubtree(int $rootId, int $candidateId): bool
    {
        if ($rootId === $candidateId) {
            return true;
        }

        return static::subtreeIds($rootId)->contains($candidateId);
    }

    /** برچسب مسیر: «والد › فرزند» (برای نمایش و API) */
    public function pathLabel(string $separator = ' › '): string
    {
        $names = [$this->name];
        $current = $this;
        $guard = 0;

        while ($current->parent_id !== null && $guard++ < self::MAX_DEPTH) {
            $current = $current->parent;

            if (! $current) {
                break;
            }

            array_unshift($names, $current->name);
        }

        return implode($separator, $names);
    }

    /**
     * گزینه‌های تختِ درختی برای سلکت‌های پنل مدیریت:
     * [id => 'نام'] | [id => '— زیردسته'] با بریدنِ زیردرختِ $excludeId.
     */
    public static function flatOptions(string $type, ?int $excludeId = null): array
    {
        $categories = static::query()
            ->ofType($type)
            ->ordered()
            ->get(['id', 'parent_id', 'name']);

        $ids = $categories->pluck('id');
        $options = [];

        $walk = null;
        $walk = function (int $parentId, int $depth) use (&$walk, &$options, $categories, $excludeId, $ids): void {
            // ریشه مجازی: دسته‌های بی‌والد + یتیم‌ها (والدشان در این نوع نیست)
            $children = $parentId === 0
                ? $categories->filter(fn ($c) => $c->parent_id === null || ! $ids->contains($c->parent_id))
                : $categories->where('parent_id', $parentId);

            foreach ($children as $cat) {
                if ($cat->id === $excludeId) {
                    continue; // خود گره و کل زیردرختش حذف می‌شود
                }

                $options[$cat->id] = str_repeat('— ', $depth) . $cat->name;
                $walk((int) $cat->id, $depth + 1);
            }
        };

        $walk(0, 0);

        return $options;
    }

    /**
     * درختِ فروشگاه: دسته‌های فعال یک نوع، مرتب‌شده سلسله‌مراتبی با:
     *  - shop_count       → تعداد تجمیعی (خود دسته + همه زیردسته‌ها)
     *  - hierarchy_label  → برچسب با مسیر والد برای چیپ‌های فیلتر
     */
    public static function shopTree(string $type): Collection
    {
        $categories = static::query()
            ->ofType($type)
            ->active()
            ->ordered()
            ->withCount([
                'packages' => fn ($q) => $q->where('status', Package::STATUS_ACTIVE),
                'products' => fn ($q) => $q->where('status', Product::STATUS_ACTIVE),
            ])
            ->get();

        $ids = $categories->pluck('id');

        // گروه‌بندی بر اساس والدِ معتبر؛ یتیم‌ها ریشه محسوب می‌شوند
        $byParent = $categories->groupBy(
            fn ($c) => ($c->parent_id !== null && $ids->contains($c->parent_id)) ? (int) $c->parent_id : 0
        );

        // تعداد تجمیعی (خود + زیردرخت)
        $sum = null;
        $sum = function (self $cat) use (&$sum, $byParent): int {
            $own = (int) ($cat->type === self::TYPE_PACKAGE ? $cat->packages_count : $cat->products_count);

            $childrenTotal = $byParent
                ->get($cat->id, collect())
                ->sum(fn (self $child) => $sum($child));

            $cat->setAttribute('shop_count', $own + $childrenTotal);

            return $own + $childrenTotal;
        };

        // مرتب‌سازی سلسله‌مراتبی + برچسب مسیر
        $ordered = collect();

        $walk = null;
        $walk = function (int $parentId, array $ancestors) use (&$walk, &$ordered, &$sum, $byParent): void {
            foreach ($byParent->get($parentId, collect()) as $cat) {
                $sum($cat);

                $label = $ancestors === []
                    ? $cat->name
                    : implode(' › ', [...$ancestors, $cat->name]);

                $cat->setAttribute('hierarchy_label', $label);
                $ordered->push($cat);

                $walk((int) $cat->id, [...$ancestors, $cat->name]);
            }
        };

        $walk(0, []);

        return $ordered;
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
