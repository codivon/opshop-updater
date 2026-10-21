<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * دسته‌بندی‌های پیش‌فرض قالب‌ها (type=template) — عیناً مانند الگوی پکیج/محصول.
 * idempotent: اجرای چندباره تکرار نمی‌کند (firstOrCreate بر اساس type+slug).
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
                'slug'        => 'web-templates',
                'name'        => 'قالب وب‌سایت',
                'description' => 'قالب‌های آماده وب برای انواع پروژه',
                'sort_order'  => 10,
                'children'    => [
                    ['slug' => 'corporate-templates',   'name' => 'قالب شرکتی و اداری',       'sort_order' => 10],
                    ['slug' => 'shop-templates',        'name' => 'قالب فروشگاهی',            'sort_order' => 20],
                    ['slug' => 'blog-magazine-templates', 'name' => 'قالب وبلاگ و مجله‌ای',   'sort_order' => 30],
                    ['slug' => 'news-templates',        'name' => 'قالب خبری',                'sort_order' => 40],
                    ['slug' => 'personal-templates',    'name' => 'قالب شخصی و رزومه',        'sort_order' => 50],
                ],
            ],
            [
                'slug'        => 'landing-templates',
                'name'        => 'لندینگ‌پیج',
                'description' => 'صفحات فرود تک‌صفحه‌ای برای کمپین و معرفی محصول',
                'sort_order'  => 20,
                'children'    => [
                    ['slug' => 'product-landing',  'name' => 'معرفی محصول', 'sort_order' => 10],
                    ['slug' => 'event-landing',    'name' => 'رویداد و همایش', 'sort_order' => 20],
                    ['slug' => 'app-landing',      'name' => 'معرفی اپلیکیشن', 'sort_order' => 30],
                ],
            ],
            [
                'slug'        => 'dashboard-templates',
                'name'        => 'پنل و داشبورد',
                'description' => 'قالب‌های ادمین‌پنل، داشبورد مدیریتی و CRM',
                'sort_order'  => 30,
                'children'    => [
                    ['slug' => 'admin-dashboard', 'name' => 'داشبورد مدیریت', 'sort_order' => 10],
                    ['slug' => 'crm-templates',   'name' => 'CRM و پنل سازمانی', 'sort_order' => 20],
                ],
            ],
            [
                'slug'        => 'html-ui-templates',
                'name'        => 'HTML و UI کیت',
                'description' => 'قالب‌های HTML خام، کامپوننت‌ها و کیت‌های رابط کاربری',
                'sort_order'  => 40,
                'children'    => [
                    ['slug' => 'html-templates', 'name' => 'قالب HTML خام', 'sort_order' => 10],
                    ['slug' => 'ui-kits',        'name' => 'کیت رابط کاربری (UI Kit)', 'sort_order' => 20],
                    ['slug' => 'email-templates', 'name' => 'قالب ایمیل', 'sort_order' => 30],
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
};
