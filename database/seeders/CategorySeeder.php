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
