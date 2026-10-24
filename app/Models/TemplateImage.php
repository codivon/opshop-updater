<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * تصویر گالری قالب — ساختار عیناً مانند PackageImage.
 */
class TemplateImage extends Model
{
    protected $fillable = [
        'template_id', 'path', 'original_name', 'size', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'size'       => 'integer',
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    /**
     * url در هر سریالایز (از جمله API) به‌صورت لینک کامل ارسال می‌شود —
     * تا پروژهٔ خریدار بتواند تصاویر گالری را مستقیم بارگذاری کند.
     */
    protected $appends = ['url'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function getUrlAttribute(): string
    {
        return template_asset_url($this->path);
    }
}
