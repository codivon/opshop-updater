<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * دسته‌بندی‌های پیش‌فرض قالب‌ها (type=template) — عیناً مانند الگوی پکیج/محصول.
 * idempotent: اجرای چندباره تکرار نمی‌کند (firstOrCreate بر اساس type+slug).
 *
 * ⚠️ نامک‌ها با پیشوند «tpl-» هستند تا با درخت دسته‌بندی «محصولات» هم‌نام نشوند —
 * categories.slug سراسری یکتاست و نام‌های قبلی (مثل html-templates) با درخت محصول
 * تداخل داشت → خطای 1062 Duplicate entry. نامک‌های قدیمی ابتدا توسط
 * Category::remapTemplateTreeSlugs() به شکل جدید منتقل می‌شوند.
 *
 * همین ساختار در CategorySeeder (متد seedTemplates) هم قرار دارد تا
 * «php artisan db:seed --class=CategorySeeder» نیز همان‌ها را بسازد.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('categories')) {
            return;
        }

        // ۱) انتقال نامک‌های قدیمی درخت قالب به نامک‌های tpl-* (اگر موجود باشند)
        \App\Models\Category::remapTemplateTreeSlugs();

        // ۲) ساخت شاخه‌های ناقص درخت (idempotent)
        foreach ($this->tree() as $node) {
            $this->createBranch($node, null);
        }
    }

    public function down(): void
    {
        // دسته‌بندی‌های پیش‌فرض حذف نمی‌شوند (اگر ادمین داده‌های دیگری زیرشان ساخته باشد)
    }

    private function createBranch(array $node, ?int $parentId): void
    {
        $category = \App\Models\Category::firstOrCreate(
            ['type' => \App\Models\Category::TYPE_TEMPLATE, 'slug' => $node['slug']],
            [
                'name'        => $node['name'],
                'parent_id'   => $parentId,
                'description' => $node['description'] ?? null,
                'is_active'   => true,
                'sort_order'  => $node['sort_order'] ?? 0,
            ],
        );

        foreach ($node['children'] ?? [] as $i => $child) {
            $this->createBranch($child, $category->id);
        }
    }

    private function tree(): array
    {
        return [
            [
                'slug'        => 'tpl-web-templates',
                'name'        => 'قالب وب‌سایت',
                'description' => 'قالب‌های آماده وب برای انواع پروژه',
                'sort_order'  => 10,
                'children'    => [
                    ['slug' => 'tpl-corporate-templates',   'name' => 'قالب شرکتی و اداری',       'sort_order' => 10],
                    ['slug' => 'tpl-shop-templates',        'name' => 'قالب فروشگاهی',            'sort_order' => 20],
                    ['slug' => 'tpl-blog-magazine-templates', 'name' => 'قالب وبلاگ و مجله‌ای',   'sort_order' => 30],
                    ['slug' => 'tpl-news-templates',        'name' => 'قالب خبری',                'sort_order' => 40],
                    ['slug' => 'tpl-personal-templates',    'name' => 'قالب شخصی و رزومه',        'sort_order' => 50],
                ],
            ],
            [
                'slug'        => 'tpl-landing-templates',
                'name'        => 'لندینگ‌پیج',
                'description' => 'صفحات فرود تک‌صفحه‌ای برای کمپین و معرفی محصول',
                'sort_order'  => 20,
                'children'    => [
                    ['slug' => 'tpl-product-landing',  'name' => 'معرفی محصول', 'sort_order' => 10],
                    ['slug' => 'tpl-event-landing',    'name' => 'رویداد و همایش', 'sort_order' => 20],
                    ['slug' => 'tpl-app-landing',      'name' => 'معرفی اپلیکیشن', 'sort_order' => 30],
                ],
            ],
            [
                'slug'        => 'tpl-dashboard-templates',
                'name'        => 'پنل و داشبورد',
                'description' => 'قالب‌های ادمین‌پنل، داشبورد مدیریتی و CRM',
                'sort_order'  => 30,
                'children'    => [
                    ['slug' => 'tpl-admin-dashboard', 'name' => 'داشبورد مدیریت', 'sort_order' => 10],
                    ['slug' => 'tpl-crm-templates',   'name' => 'CRM و پنل سازمانی', 'sort_order' => 20],
                ],
            ],
            [
                'slug'        => 'tpl-html-ui-templates',
                'name'        => 'HTML و UI کیت',
                'description' => 'قالب‌های HTML خام، کامپوننت‌ها و کیت‌های رابط کاربری',
                'sort_order'  => 40,
                'children'    => [
                    ['slug' => 'tpl-html-templates', 'name' => 'قالب HTML خام', 'sort_order' => 10],
                    ['slug' => 'tpl-ui-kits',        'name' => 'کیت رابط کاربری (UI Kit)', 'sort_order' => 20],
                    ['slug' => 'tpl-email-templates', 'name' => 'قالب ایمیل', 'sort_order' => 30],
                ],
            ],
            [
                'slug'        => 'tpl-special-templates',
                'name'        => 'سایر قالب‌ها',
                'description' => 'قالب‌های متفرقه و کاربردهای خاص',
                'sort_order'  => 50,
            ],
        ];
    }
};
