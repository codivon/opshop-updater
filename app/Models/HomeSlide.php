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
    /** محل افقی محتوای اسلاید (RTL: start = راست) */
    public const ALIGNS = ['start' => 'راست', 'center' => 'وسط', 'end' => 'چپ'];

    /** محل عمودی محتوای اسلاید */
    public const VPOSS = ['bottom' => 'پایین', 'center' => 'وسط', 'top' => 'بالا'];

    /** استایل دکمهٔ اسلاید */
    public const BUTTON_STYLES = ['solid' => 'پررنگ', 'soft' => 'ملایم', 'link' => 'متنی'];

    /** سقف شدت تیرگی لایهٔ خوانایی (٪) */
    public const OVERLAY_MAX = 90;

    protected $fillable = [
        'title', 'text', 'badge', 'image', 'link', 'link_text',
        'content_align', 'content_vpos', 'button_style', 'overlay', 'new_tab',
        'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'new_tab' => 'boolean',
    ];

    /** اسلایدهای فعال به ترتیب پنل */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }
}
