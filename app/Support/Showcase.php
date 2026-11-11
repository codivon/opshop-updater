<?php

namespace App\Support;

use App\Models\HomeSlide;
use App\Models\Package;
use Illuminate\Support\Str;

/**
 * تأمین‌کنندهٔ اسلایدهای «اسلایدر معرفی محصول» صفحهٔ اصلی — Task 71.
 *
 * قاعدهٔ نمایش (ساده و قابل پیش‌بینی):
 *   - هر وقت اسلاید دستیِ فعالی در جدول home_slides باشد ← فقط همان‌ها
 *     (به ترتیب sort_order پنل) نمایش داده می‌شوند.
 *   - در غیر این صورت «پیش‌فرض‌ها» ساخته می‌شوند:
 *       ۱) بنر خودکار از پکیج‌های فعال (تصویر/عنوان/متن/لینک هر پکیج)
 *          — toggle: home_showcase_packages_enabled
 *       ۲) بنر ویژهٔ InstaShop (تصویر کامیت‌شده در ریپو + متن/لینک از پنل)
 *          — toggle: home_showcase_instashop_enabled
 *
 * خروجی آرایهٔ یکسان‌شدهٔ [badge,title,text,image,link,link_text] است تا ویو
 * برای اسلاید دستی/خودکار هیچ تفاوتی نداشته باشد.
 */
class Showcase
{
    /** تصویر بنر ویژهٔ InstaShop (کامیت‌شده در ریپو — public/images) */
    public const INSTASHOP_IMAGE = 'images/showcase/insta-shop.webp';

    /** سقف بنرهای خودکار پکیج‌ها در پیش‌فرض */
    public const AUTO_LIMIT = 6;

    /**
     * اسلایدهای نمایش صفحهٔ اصلی — دستی‌ها اولویت دارند، وگرنه پیش‌فرض‌ها.
     */
    public static function slides(): array
    {
        $manual = HomeSlide::active()
            ->get()
            ->map(fn (HomeSlide $slide) => static::format(
                badge: (string) $slide->badge,
                title: (string) $slide->title,
                text: (string) $slide->text,
                image: $slide->image !== null ? template_asset_url($slide->image) : null,
                link: (string) $slide->link,
                linkText: (string) $slide->link_text,
            ))
            ->all();

        if ($manual !== []) {
            return $manual;
        }

        return array_merge(
            HomePage::on('home_showcase_packages_enabled') ? static::packageSlides() : [],
            HomePage::on('home_showcase_instashop_enabled') ? [static::instaShopSlide()] : [],
        );
    }

    /**
     * پیش‌فرض اول — از هر پکیج فعال یک بنر (جدیدترین‌ها اول).
     */
    public static function packageSlides(): array
    {
        return Package::query()
            ->with(['project:id,name', 'latestVersion:id,package_id,version'])
            ->where('status', Package::STATUS_ACTIVE)
            ->orderByDesc('id')
            ->limit(static::AUTO_LIMIT)
            ->get()
            ->map(fn (Package $package) => static::format(
                badge: (string) ($package->project?->name ?: 'پکیج آماده'),
                title: (string) $package->name,
                text: Str::limit(trim((string) $package->short_description), 160),
                image: $package->thumbnail_url,
                link: route('shop.package', ['slug' => $package->slug]),
                linkText: 'مشاهده و خرید',
            ))
            ->all();
    }

    /**
     * پیش‌فرض دوم — بنر ویژهٔ InstaShop (بیزینس آمادهٔ فروش اینستاگرامی).
     */
    public static function instaShopSlide(): array
    {
        return static::format(
            badge: HomePage::str('home_showcase_instashop_badge') ?: 'بیزینس آماده',
            title: HomePage::str('home_showcase_instashop_title') ?: 'InstaShop',
            text: HomePage::str('home_showcase_instashop_text'),
            image: asset(static::INSTASHOP_IMAGE),
            link: HomePage::str('home_showcase_instashop_link') ?: 'https://insta-shop.opashop.ir',
            linkText: HomePage::str('home_showcase_instashop_link_text') ?: 'مشاهدهٔ دمو',
        );
    }

    private static function format(string $badge, string $title, string $text, ?string $image, string $link, string $linkText): array
    {
        return [
            'badge' => trim($badge),
            'title' => trim($title),
            'text' => trim($text),
            'image' => $image,
            'link' => trim($link),
            'link_text' => trim($linkText) ?: 'مشاهده',
        ];
    }
}
