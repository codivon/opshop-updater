<?php

namespace App\Support;

/**
 * مرجع یکتای منوی هدر فروشگاه — Task 61.
 *
 * آیتم‌های ناوبری هدر (خانه، پکیج‌ها، پروژه‌ها، قالب‌ها، تغییرات، طرح‌های اشتراک)
 * از پنل /admin/settings/home ← تب «منوی هدر» قابل روشن/خاموش و جابه‌جایی هستند.
 * - ذخیره در گروه menu از جدول settings (کلیدهای menu_*_enabled و menu_sort_*)؛
 *   خواندن از helper سراسری setting() (کش ۶۰ ثانیه‌ای) و پاک‌سازی کش در ذخیرهٔ پنل.
 * - خاموشی سوییچ فقط لینک هدر را مخفی می‌کند؛ دسترسی مستقیم با آدرس سرجایش است.
 * - آیتم «تغییرات» علاوه بر سوییچ خودش به پرچم ویژگی Features::changelog() هم وابسته است.
 */
class ShopMenu
{
    /** تعریف آیتم‌های منو (کلید کوتاه => تعریف) — ترتیب آرایه فقط برای تساوی sortها */
    public const ITEMS = [
        'home' => [
            'label'  => 'خانه',
            'icon'   => 'home',
            'routes' => ['shop.home'],
        ],
        'packages' => [
            'label'  => 'پکیج‌ها',
            'icon'   => 'package',
            'routes' => ['shop.packages', 'shop.package'],
        ],
        'products' => [
            'label'  => 'پروژه‌ها',
            'icon'   => 'rocket',
            'routes' => ['shop.products', 'shop.product'],
        ],
        'templates' => [
            'label'  => 'قالب‌ها',
            'icon'   => 'layout-template',
            'routes' => ['shop.templates', 'shop.template', 'payment.result.template'],
        ],
        'changelog' => [
            'label'   => 'تغییرات',
            'icon'    => 'history',
            'routes'  => ['shop.changelog'],
            'feature' => 'changelog', // علاوه بر سوییچ منو، پرچم ویژگی هم لازم است
        ],
        'subscriptions' => [
            'label'  => 'طرح‌های اشتراک',
            'icon'   => 'crown',
            'routes' => ['shop.subscriptions', 'subscription.*'],
        ],
    ];

    /** پیش‌فرض‌های تنظیمات منو (گروه menu) */
    public const DEFAULTS = [
        /* -------------------- روشن/خاموش آیتم‌ها -------------------- */
        'menu_home_enabled'          => '1',
        'menu_packages_enabled'      => '1',
        'menu_products_enabled'      => '1',
        'menu_templates_enabled'     => '1',
        'menu_changelog_enabled'     => '1',
        'menu_subscriptions_enabled' => '1',

        /* -------------------- ترتیب آیتم‌ها (پنل قابل جابه‌جایی) ------------ */
        'menu_sort_home'          => '10',
        'menu_sort_packages'      => '20',
        'menu_sort_products'      => '30',
        'menu_sort_templates'     => '40',
        'menu_sort_changelog'     => '50',
        'menu_sort_subscriptions' => '60',
    ];

    /** کلیدهای بولی (toggle) */
    public const BOOL_KEYS = [
        'menu_home_enabled', 'menu_packages_enabled', 'menu_products_enabled',
        'menu_templates_enabled', 'menu_changelog_enabled', 'menu_subscriptions_enabled',
    ];

    /** کلیدهای کوتاه آیتم‌های منو */
    public static function keys(): array
    {
        return array_keys(self::ITEMS);
    }

    /** گروه جدول settings برای همهٔ کلیدهای این ماژول */
    public static function group(string $key): string
    {
        return 'menu';
    }

    /** آیا آیتم منو روشن است؟ */
    public static function on(string $key): bool
    {
        return filter_var(
            (string) setting('menu_'.$key.'_enabled', self::DEFAULTS['menu_'.$key.'_enabled'] ?? '0'),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /** آیتم‌های روشن منو به ترتیب ذخیره‌شدهٔ پنل (menu_sort_*؛ هرچه کوچک‌تر، بالاتر) */
    public static function orderedItems(): array
    {
        $sorted = [];

        foreach (self::keys() as $key) {
            $sorted[$key] = (int) setting('menu_sort_'.$key, self::DEFAULTS['menu_sort_'.$key] ?? '0');
        }

        asort($sorted); // پایدار است — تساوی = ترتیب پیش‌فرض

        $items = [];

        foreach (array_keys($sorted) as $key) {
            if (! self::on($key)) {
                continue;
            }

            $item = self::ITEMS[$key];

            // آیتم وابسته به پرچم ویژگی (مثل «تغییرات» ← Features::changelog)
            if (isset($item['feature']) && ! Features::{$item['feature']}()) {
                continue;
            }

            $items[] = ['key' => $key] + $item;
        }

        return $items;
    }
}
