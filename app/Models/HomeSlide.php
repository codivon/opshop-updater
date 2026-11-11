<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * اسلاید «اسلایدر معرفی» صفحهٔ اصلی — محتوای دستی از پنل.
 *
 * هر اسلاید = تصویر/رنگ + نشان + عنوان + متن + دکمهٔ اختیاری.
 * خواندن برای نمایش از App\Support\Showcase::slides() است (fallback خودکار
 * به بنرهای پکیج‌ها + بنر ویژهٔ InstaShop وقتی هیچ رکورد فعالی نیست).
 */
class HomeSlide extends Model
{
    protected $fillable = [
        'title', 'text', 'badge', 'image', 'link', 'link_text', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** اسلایدهای فعال به ترتیب پنل */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }
}
