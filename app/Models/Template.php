<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * قالب پروژه (Template) — بخش فروش قالب‌ها، مشابه پکیج‌ها/محصولات.
 * فایل‌های نسخه‌ها روی disk «local» در storage/app/private/templates/{slug}/ نگهداری می‌شوند.
 */
class Template extends Model
{
    public const STATUS_DRAFT    = 'draft';
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_ARCHIVED = 'archived';

    /** انواع خرید — معمولی و اختصاصی */
    public const PURCHASE_NORMAL    = 'normal';
    public const PURCHASE_EXCLUSIVE = 'exclusive';

    public const PURCHASE_LABELS = [
        self::PURCHASE_NORMAL    => 'خرید معمولی',
        self::PURCHASE_EXCLUSIVE => 'خرید اختصاصی',
    ];

    public const STATUS_LABELS = [
        self::STATUS_DRAFT    => 'پیش‌نویس',
        self::STATUS_ACTIVE   => 'فعال',
        self::STATUS_ARCHIVED => 'آرشیو',
    ];

    protected $fillable = [
        'category_id', 'name', 'slug', 'short_description', 'description',
        'thumbnail', 'price', 'discount', 'exclusive_price', 'exclusive_discount',
        'exclusive_note', 'exclusive_buyer_label', 'is_free', 'status', 'sort_order',
    ];

    protected $casts = [
        'price'                => 'integer',
        'discount'             => 'integer',
        'exclusive_price'      => 'integer',
        'exclusive_discount'   => 'integer',
        'is_free'              => 'boolean',
        'is_sold_exclusively'  => 'boolean',
        'sold_exclusively_at'  => 'datetime',
        'sort_order'           => 'integer',
        'sales_count'          => 'integer',
        'downloads_count'      => 'integer',
        'views_count'          => 'integer',
    ];

    /* ---------------- Relationships ---------------- */

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(TemplateVersion::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(TemplatePurchase::class);
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /** گالری تصاویر قالب */
    public function images(): HasMany
    {
        return $this->hasMany(TemplateImage::class)->orderBy('sort_order');
    }

    public function activeImages(): HasMany
    {
        return $this->images()->where('is_active', true);
    }

    /** لایسنس‌های صادرشده برای این قالب */
    public function licenses(): HasMany
    {
        return $this->hasMany(TemplateLicense::class);
    }

    /** خریدار اختصاصی (آخرین خرید اختصاصی موفق) */
    public function exclusivePurchase()
    {
        return $this->hasOne(TemplatePurchase::class)
            ->where('purchase_type', TemplatePurchase::TYPE_EXCLUSIVE)
            ->where('status', TemplatePurchase::STATUS_PAID)
            ->latest('id');
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
        return $this->hasOne(TemplateVersion::class)
            ->whereIn('status', [TemplateVersion::STATUS_ACTIVE])
            ->orderByDesc('id');
    }

    public function activeVersions()
    {
        return $this->versions()->where('status', TemplateVersion::STATUS_ACTIVE)->orderByDesc('id');
    }

    /** قیمت نهایی پس از تخفیف (قالب رایگان = 0) */
    public function getFinalPriceAttribute(): int
    {
        return $this->finalPriceFor(self::PURCHASE_NORMAL);
    }

    /** قیمت نهایی خرید اختصاصی پس از تخفیف */
    public function getExclusiveFinalPriceAttribute(): int
    {
        return $this->finalPriceFor(self::PURCHASE_EXCLUSIVE);
    }

    /** آیا خرید اختصاصی برای این قالب فعال است؟ */
    public function getHasExclusiveOfferAttribute(): bool
    {
        return ! $this->is_sold_exclusively && $this->exclusivePriceBase() > 0;
    }

    /** محاسبه یکسان برای هر دو نوع خرید */
    public function finalPriceFor(string $purchaseType): int
    {
        if ($this->is_free) {
            return 0;
        }

        if ($purchaseType === self::PURCHASE_EXCLUSIVE) {
            $price    = $this->exclusive_price;
            $discount = $this->exclusive_discount;
        } else {
            $price    = $this->price;
            $discount = $this->discount;
        }

        if ($price <= 0) {
            return 0;
        }

        if ($discount > 0 && $discount < 100) {
            return (int) max(0, round($price * (100 - $discount) / 100));
        }

        return $discount >= 100 ? 0 : (int) $price;
    }

    /** قیمت پایه هر نوع خرید (برای نمایش خط‌خورده) */
    public function basePriceFor(string $purchaseType): int
    {
        if ($this->is_free) {
            return 0;
        }

        return $purchaseType === self::PURCHASE_EXCLUSIVE ? (int) $this->exclusive_price : (int) $this->price;
    }

    /** آیا خرید اختصاصی مقدار پایه دارد؟ */
    public function exclusivePriceBase(): int
    {
        return (int) $this->exclusive_price;
    }

    /** آیا قالب به‌صورت اختصاصی فروخته شده؟ */
    public function getIsSoldOutAttribute(): bool
    {
        return $this->is_sold_exclusively;
    }

    /**
     * متن نمایشی خریدار اختصاصی برای صفحهٔ فروشگاه:
     * برچسب ذخیره‌شده (خودکار/ادمین) در اولویت، وگرنه نام مشتریِ آخرین خرید اختصاصی.
     */
    public function exclusiveBuyerDisplay(): ?string
    {
        if ($label = trim((string) $this->exclusive_buyer_label)) {
            return $label;
        }

        $customer = $this->exclusivePurchase?->customer;

        return $customer?->name ?: null;
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
            return template_asset_url($this->thumbnail);
        }

        return asset('images/package-default.png');
    }

    /**
     * وضعیت فروش اختصاصی: template پایه شمارش فروش «معمولی» را جدا نگه می‌دارد؛
     * برای نمایش در کارت‌ها، «فروش» شامل خرید اختصاصی هم می‌شود (مثل مارکت‌پلیس‌ها).
     */
    public function getHasPurchasesAttribute(): bool
    {
        return $this->sales_count > 0 || $this->is_sold_exclusively;
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

    public function incrementDownloads(): void
    {
        $this->increment('downloads_count');
    }

    public static function generateSlug(string $name, ?int $ignoreId = null): string
    {
        $base = \Illuminate\Support\Str::slug($name);

        if ($base === '') {
            $base = 'template-' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6));
        }

        $slug = $base;
        $i = 2;

        while (self::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
