<?php

namespace App\Support;

use App\Models\HomeSlide;
use App\Models\Package;
use App\Models\Product;
use Illuminate\Support\Str;

/**
 * تأمین‌کنندهٔ اسلایدهای «اسلایدر معرفی محصول» صفحهٔ اصلی — Task 71 + Task 74.
 *
 * قاعدهٔ نمایش (ساده و قابل پیش‌بینی):
 *   - هر وقت اسلاید دستیِ فعالی در جدول home_slides باشد ← فقط همان‌ها
 *     (به ترتیب sort_order پنل) نمایش داده می‌شوند.
 *   - در غیر این صورت «پیش‌فرض‌ها» ساخته می‌شوند:
 *       ۱) اسلایدهای خودکار از پکیج/محصول — انتخابِ مدیر در پنل (به ترتیب
 *          دلخواه مدیر) و اگر چیزی انتخاب نشده باشد، ۶ پکیج فعالِ آخر
 *          — toggle: home_showcase_packages_enabled
 *       ۲) بنر ویژهٔ InstaShop (تصویر کامیت‌شده در ریپو + متن/لینک از پنل)
 *          — toggle: home_showcase_instashop_enabled
 *          جایگاهش قابل انتخاب است: اول (پیش‌فرض) یا آخرِ اسلایدهای خودکار.
 *
 * خروجی آرایهٔ یکسان‌شدهٔ [badge,title,text,image,link,link_text] است تا ویو
 * برای اسلاید دستی/خودکار هیچ تفاوتی نداشته باشد.
 */
class Showcase
{
    /** تصویر بنر ویژهٔ InstaShop (کامیت‌شده در ریپو — public/images) */
    public const INSTASHOP_IMAGE = 'images/showcase/insta-shop.webp';

    /** سقف بنرهای خودکار پکیج‌ها در حالت fallback (هیچ انتخابی نشده باشد) */
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
                contentAlign: $slide->content_align,
                contentVpos: $slide->content_vpos,
                buttonStyle: $slide->button_style,
                overlay: $slide->overlay,
                newTab: $slide->new_tab,
            ))
            ->all();

        if ($manual !== []) {
            return $manual;
        }

        // اسلایدهای خودکار از پکیج/محصول (انتخاب مدیر، وگرنه ۶ پکیج فعالِ آخر)
        $auto = HomePage::on('home_showcase_packages_enabled') ? static::autoSlides() : [];

        // بنر InstaShop — جایگاهش از پنل: اول (پیش‌فرض) یا آخر
        $insta = HomePage::on('home_showcase_instashop_enabled') ? [static::instaShopSlide()] : [];

        return HomePage::str('home_showcase_instashop_position') === 'last'
            ? array_merge($auto, $insta)
            : array_merge($insta, $auto);
    }

    /**
     * اسلایدهای خودکار از انتخاب مدیر (پکیج/محصول به ترتیب دلخواه)؛
     * اگر چیزی انتخاب نشده باشد = رفتار پیش‌فرض سابق (۶ پکیج فعالِ آخر).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function autoSlides(): array
    {
        $refs = static::storedRefs();

        if ($refs === []) {
            return static::packageSlides();
        }

        $packageIds = array_map(fn (array $r) => $r['id'], array_filter($refs, fn (array $r) => $r['type'] === 'package'));
        $productIds = array_map(fn (array $r) => $r['id'], array_filter($refs, fn (array $r) => $r['type'] === 'product'));

        // فقط مواردِ فعال — پکیج/محصولِ حذف‌شده یا غیرفعال نادیده گرفته می‌شود
        $packages = $packageIds === [] ? collect() : Package::query()
            ->with(['project:id,name', 'latestVersion:id,package_id,version'])
            ->where('status', Package::STATUS_ACTIVE)
            ->whereIn('id', $packageIds)
            ->get()
            ->keyBy('id');

        $products = $productIds === [] ? collect() : Product::query()
            ->with('category:id,name')
            ->where('status', Product::STATUS_ACTIVE)
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $slides = [];

        foreach ($refs as $ref) {
            $model = $ref['type'] === 'package' ? $packages->get($ref['id']) : $products->get($ref['id']);

            if ($model === null) {
                continue;
            }

            $slides[] = $ref['type'] === 'package'
                ? static::packageSlide($model)
                : static::productSlide($model);
        }

        return $slides;
    }

    /**
     * refs ذخیره‌شدهٔ پنل از تنظیم home_showcase_auto_slides.
     *
     * @return array<int, array{type: string, id: int}>
     */
    public static function storedRefs(): array
    {
        return static::parseRefs(HomePage::str('home_showcase_auto_slides'));
    }

    /**
     * پارسر JSON انتخاب‌های مدیر — هم برای تنظیم ذخیره‌شدهٔ صفحهٔ اصلی و هم
     * برای مقدارِ فرمِ پنل (قبل از «ذخیره تنظیمات») استفاده می‌شود؛
     * ساختار نامعتبر/ناآشنا بی‌صدا حذف می‌شود.
     *
     * @return array<int, array{type: string, id: int}>
     */
    public static function parseRefs(?string $raw): array
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return [];
        }

        $refs = [];

        foreach ($decoded as $ref) {
            if (! is_array($ref)) {
                continue;
            }

            $type = $ref['type'] ?? null;
            $id = (int) ($ref['id'] ?? 0);

            if (in_array($type, ['package', 'product'], true) && $id > 0) {
                $refs[] = ['type' => $type, 'id' => $id];
            }
        }

        return $refs;
    }

    /** انکد refs به JSON برای ذخیره در تنظیم home_showcase_auto_slides */
    public static function encodeRefs(array $refs): string
    {
        return $refs === [] ? '' : (string) json_encode(array_values($refs), JSON_UNESCAPED_UNICODE);
    }

    /**
     * حالت fallback — از هر پکیج فعال یک بنر (جدیدترین‌ها اول).
     */
    public static function packageSlides(): array
    {
        return Package::query()
            ->with(['project:id,name', 'latestVersion:id,package_id,version'])
            ->where('status', Package::STATUS_ACTIVE)
            ->orderByDesc('id')
            ->limit(static::AUTO_LIMIT)
            ->get()
            ->map(fn (Package $package) => static::packageSlide($package))
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

    /** بنر یک پکیج — نشان = نام پروژه، لینک = صفحهٔ فروش پکیج */
    private static function packageSlide(Package $package): array
    {
        return static::format(
            badge: (string) ($package->project?->name ?: 'پکیج آماده'),
            title: (string) $package->name,
            text: Str::limit(trim((string) $package->short_description), 160),
            image: $package->thumbnail_url,
            link: route('shop.package', ['slug' => $package->slug]),
            linkText: 'مشاهده و خرید',
        );
    }

    /** بنر یک محصول — نشان = نام دسته (یا «پروژه آماده»)، لینک = صفحهٔ فروش محصول */
    private static function productSlide(Product $product): array
    {
        return static::format(
            badge: (string) ($product->category?->name ?: 'پروژه آماده'),
            title: (string) $product->name,
            text: Str::limit(trim((string) $product->short_description), 160),
            image: $product->thumbnail_url,
            link: route('shop.product', ['slug' => $product->slug]),
            linkText: 'مشاهده و خرید',
        );
    }

    /**
     * آرایهٔ یکسان‌شدهٔ اسلاید برای ویو — دستی و پیش‌فرض هر دو ساختار دارند؛
     * اسلایدهای پیش‌فرض با موقعیت/استایل «خودکار» (رفتار همیشگی) هستند.
     */
    private static function format(
        string $badge,
        string $title,
        string $text,
        ?string $image,
        string $link,
        string $linkText,
        string $contentAlign = 'start',
        string $contentVpos = 'center',
        string $buttonStyle = 'solid',
        ?int $overlay = null,
        bool $newTab = false,
    ): array {
        return [
            'badge' => trim($badge),
            'title' => trim($title),
            'text' => trim($text),
            'image' => $image,
            'link' => trim($link),
            'link_text' => trim($linkText) ?: 'مشاهده',
            'content_align' => array_key_exists($contentAlign, HomeSlide::ALIGNS) ? $contentAlign : 'start',
            'content_vpos' => array_key_exists($contentVpos, HomeSlide::VPOSS) ? $contentVpos : 'center',
            'button_style' => array_key_exists($buttonStyle, HomeSlide::BUTTON_STYLES) ? $buttonStyle : 'solid',
            'overlay' => $overlay !== null ? max(0, min(HomeSlide::OVERLAY_MAX, $overlay)) : null,
            'new_tab' => $newTab,
        ];
    }
}
