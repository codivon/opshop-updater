<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * دسته‌بندی‌های پیش‌فرض فروشگاه — هم برای پکیج‌ها هم برای پروژه‌های قابل‌فروش.
 *
 *  - idempotent: اجرای چندباره چیزی را تکرار نمی‌کند (firstOrCreate بر اساس type+slug).
 *  - درختی: دسته‌های ریشه با چند زیردستهٔ نمونه ساخته می‌شوند تا فیلتر درختی
 *    فروشگاه و انتخاب «دسته فروشگاه» هنگام استقرار پکیج روی وردپرس (API) از ابتدا معنا داشته باشد.
 *  - دسته‌های «product» که مایگریشن اولیه ساخته (script/template/application/…)
 *    دست‌نخورده می‌مانند و فقط زیردسته‌هایشان اضافه می‌شود.
 *
 * اجرای مستقل:  php artisan db:seed --class=CategorySeeder
 */
class CategorySeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        foreach ($this->packageTree() as $root) {
            $this->createBranch(Category::TYPE_PACKAGE, $root);
        }

        foreach ($this->productTree() as $root) {
            $this->createBranch(Category::TYPE_PRODUCT, $root);
        }

        $this->seedTemplates();
    }

    /**
     * دسته‌بندی‌های پیش‌فرض قالب‌ها (type=template) — همان درخت مایگریشن
     * 2026_10_22_000005. اجرای مستقل:
     *   php artisan db:seed --class=CategorySeeder
     */
    public function seedTemplates(): void
    {
        foreach ($this->templateTree() as $root) {
            $this->createBranch(Category::TYPE_TEMPLATE, $root);
        }
    }

    /**
     * ساخت یک شاخهٔ درخت (ریشه + فرزندان) به‌صورت بازگشتی.
     */
    private function createBranch(string $type, array $node, ?int $parentId = null, int $sortIndex = 0): Category
    {
        $category = Category::firstOrCreate(
            ['type' => $type, 'slug' => $node['slug']],
            [
                'name'        => $node['name'],
                'parent_id'   => $parentId,
                'description' => $node['description'] ?? null,
                'is_active'   => true,
                'sort_order'  => $node['sort_order'] ?? (($sortIndex + 1) * 10),
            ],
        );

        foreach ($node['children'] ?? [] as $i => $child) {
            $this->createBranch($type, $child, $category->id, $i);
        }

        return $category;
    }

    /* ================================================================
     *  قالب‌ها (Templates) — ساختار پیش‌فرض فروش قالب
     * ================================================================ */

    private function templateTree(): array
    {
        return [
            [
                'slug'        => 'web-templates',
                'name'        => 'قالب وب‌سایت',
                'description' => 'قالب‌های آماده وب برای انواع پروژه',
                'sort_order'  => 10,
                'children'    => [
                    ['slug' => 'corporate-templates',     'name' => 'قالب شرکتی و اداری', 'sort_order' => 10],
                    ['slug' => 'shop-templates',          'name' => 'قالب فروشگاهی',      'sort_order' => 20],
                    ['slug' => 'blog-magazine-templates', 'name' => 'قالب وبلاگ و مجله‌ای', 'sort_order' => 30],
                    ['slug' => 'news-templates',          'name' => 'قالب خبری',           'sort_order' => 40],
                    ['slug' => 'personal-templates',      'name' => 'قالب شخصی و رزومه',   'sort_order' => 50],
                ],
            ],
            [
                'slug'        => 'landing-templates',
                'name'        => 'لندینگ‌پیج',
                'description' => 'صفحات فرود تک‌صفحه‌ای برای کمپین و معرفی محصول',
                'sort_order'  => 20,
                'children'    => [
                    ['slug' => 'product-landing', 'name' => 'معرفی محصول',      'sort_order' => 10],
                    ['slug' => 'event-landing',   'name' => 'رویداد و همایش',   'sort_order' => 20],
                    ['slug' => 'app-landing',     'name' => 'معرفی اپلیکیشن',   'sort_order' => 30],
                ],
            ],
            [
                'slug'        => 'dashboard-templates',
                'name'        => 'پنل و داشبورد',
                'description' => 'قالب‌های ادمین‌پنل، داشبورد مدیریتی و CRM',
                'sort_order'  => 30,
                'children'    => [
                    ['slug' => 'admin-dashboard', 'name' => 'داشبورد مدیریت',       'sort_order' => 10],
                    ['slug' => 'crm-templates',   'name' => 'CRM و پنل سازمانی',   'sort_order' => 20],
                ],
            ],
            [
                'slug'        => 'html-ui-templates',
                'name'        => 'HTML و UI کیت',
                'description' => 'قالب‌های HTML خام، کامپوننت‌ها و کیت‌های رابط کاربری',
                'sort_order'  => 40,
                'children'    => [
                    ['slug' => 'html-templates',  'name' => 'قالب HTML خام',            'sort_order' => 10],
                    ['slug' => 'ui-kits',         'name' => 'کیت رابط کاربری (UI Kit)', 'sort_order' => 20],
                    ['slug' => 'email-templates', 'name' => 'قالب ایمیل',               'sort_order' => 30],
                ],
            ],
            [
                'slug'        => 'special-templates',
                'name'        => 'سایر قالب‌ها',
                'description' => 'قالب‌های متفرقه و کاربردهای خاص',
                'sort_order'  => 50,
            ],
        ];
    }

    /* ================================================================
     *  پکیج‌ها (آپدیت‌سنتر) — ساختار پیش‌فرض فروشگاه وردپرسی
     * ================================================================ */

    private function packageTree(): array
    {
        return [
            [
                'slug'        => 'wordpress-themes',
                'name'        => 'قالب وردپرس',
                'description' => 'قالب‌های آماده وردپرس برای انواع وب‌سایت',
                'children'    => [
                    ['slug' => 'corporate-wordpress-themes', 'name' => 'قالب شرکتی'],
                    ['slug' => 'woocommerce-themes',         'name' => 'قالب فروشگاهی (ووکامرس)'],
                    ['slug' => 'magazine-wordpress-themes',  'name' => 'قالب خبری و مجله‌ای'],
                    ['slug' => 'landing-wordpress-themes',   'name' => 'قالب لندینگ‌پیج'],
                ],
            ],
            [
                'slug'        => 'wordpress-plugins',
                'name'        => 'افزونه وردپرس',
                'description' => 'افزونه‌های کاربردی و حرفه‌ای وردپرس',
                'children'    => [
                    ['slug' => 'seo-plugins',        'name' => 'افزونه سئو'],
                    ['slug' => 'security-plugins',   'name' => 'افزونه امنیتی'],
                    ['slug' => 'ecommerce-plugins',  'name' => 'افزونه فروشگاه‌ساز'],
                    ['slug' => 'form-plugins',       'name' => 'افزونه فرم‌ساز'],
                ],
            ],
            [
                'slug'        => 'scripts-panels',
                'name'        => 'اسکریپت و پنل آماده',
                'description' => 'اسکریپت‌ها و پنل‌های آمادهٔ راه‌اندازی سریع',
                'children'    => [
                    ['slug' => 'booking-scripts',  'name' => 'اسکریپت رزرو و نوبت‌دهی'],
                    ['slug' => 'shop-scripts',     'name' => 'اسکریپت فروشگاه‌ساز'],
                ],
            ],
        ];
    }

    /* ================================================================
     *  پروژه‌های قابل‌فروش — زیردسته‌ها زیر دسته‌های موجودِ مایگریشن
     * ================================================================ */

    private function productTree(): array
    {
        return [
            [
                'slug'     => 'script', // دستهٔ موجود از مایگریشن — فقط زیردسته اضافه می‌شود
                'name'     => 'اسکریپت',
                'children' => [
                    ['slug' => 'php-scripts',     'name' => 'اسکریپت PHP'],
                    ['slug' => 'laravel-scripts', 'name' => 'پروژه لاراول'],
                ],
            ],
            [
                'slug'     => 'template', // دستهٔ موجود از مایگریشن
                'name'     => 'قالب',
                'children' => [
                    ['slug' => 'html-templates', 'name' => 'قالب HTML'],
                    ['slug' => 'admin-dashboard', 'name' => 'داشبورد و پنل ادمین'],
                ],
            ],
            [
                'slug'     => 'application', // دستهٔ موجود از مایگریشن
                'name'     => 'اپلیکیشن',
                'children' => [
                    ['slug' => 'android-apps', 'name' => 'اپلیکیشن اندروید'],
                    ['slug' => 'ios-apps',     'name' => 'اپلیکیشن iOS'],
                ],
            ],
            [
                'slug'        => 'graphic-ui-kit',
                'name'        => 'گرافیک و UI کیت',
                'description' => 'فایل‌های گرافیکی، آیکون‌پک و کیت‌های رابط کاربری',
            ],
        ];
    }
}
