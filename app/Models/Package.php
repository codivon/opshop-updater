<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Package extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id', 'slug', 'name', 'short_description', 'description',
        'author', 'category', 'thumbnail', 'is_free', 'default_price',
        'status', 'module_name', 'downloads_count', 'purchases_count', 'sort_order',
    ];

    protected $casts = [
        'is_free'         => 'boolean',
        'default_price'   => 'integer',
        'downloads_count' => 'integer',
        'purchases_count' => 'integer',
        'sort_order'      => 'integer',
    ];

    protected $appends = ['thumbnail_url'];

    public const STATUS_DRAFT    = 'draft';
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_ARCHIVED = 'archived';

    /* ---------------- Relationships ---------------- */

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * ⚠️ نام متد «categoryModel» است نه «category» — چون ستون متنی category
     * از قبل روی این مدل وجود دارد و attribute همیشه بر relation اولویت دارد.
     */
    public function categoryModel(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PackageVersion::class)->orderByDesc('release_date');
    }

    public function activeVersions(): HasMany
    {
        return $this->versions()->where('status', PackageVersion::STATUS_ACTIVE);
    }

    public function latestVersion()
    {
        return $this->hasOne(PackageVersion::class)->where('status', PackageVersion::STATUS_ACTIVE)->latest('id');
    }

    public function pricingPlans(): HasMany
    {
        return $this->hasMany(PackagePricingPlan::class)->orderBy('sort_order');
    }

    public function activePricingPlans(): HasMany
    {
        return $this->pricingPlans()->where('is_active', true);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(PackagePurchase::class);
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(PackageLicense::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(PackageImage::class)->orderBy('sort_order');
    }

    public function activeImages(): HasMany
    {
        return $this->images()->where('is_active', true);
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

    public function incrementPurchases(): void
    {
        $this->increment('purchases_count');
    }

    public function getFormattedPriceAttribute(): string
    {
        return $this->is_free ? 'رایگان' : number_format($this->default_price) . ' تومان';
    }

    /**
     * آدرس تصویر شاخص:
     * - اگر URL کامل باشد → همان برمی‌گردد
     * - اگر مسیر storage باشد → URL عمومی ساخته می‌شود
     * - اگر خالی باشد → placeholder برمی‌گردد
     */
    public function getThumbnailUrlAttribute(): string
    {
        // اگر thumbnail وجود ندارد
        if (!$this->thumbnail) {
            return asset('images/package-default.png');
        }

        // اگر URL کامل است (شروع با http)
        if (preg_match('#^https?://#', $this->thumbnail)) {
            return $this->thumbnail;
        }

        // اگر مسیر در پوشه public است (با asset)
        if (file_exists(public_path($this->thumbnail))) {
            return asset($this->thumbnail);
        }

        // تصویر پیش‌فرض
        return asset('images/package-default.png');
    }

    /**
     * آیا تصویر شاخص از نوع آپلود شده (نه URL) است؟
     */
    public function getIsThumbnailUploadedAttribute(): bool
    {
        return $this->thumbnail
            && !preg_match('#^https?://#', $this->thumbnail);
    }
}
