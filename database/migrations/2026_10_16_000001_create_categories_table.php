<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * جدول دسته‌بندی‌های مشترک فروشگاه (پروژه‌های قابل‌فروش + پکیج‌ها).
 * type: product (پروژه‌ی قابل‌فروش) | package (پکیج آپدیت‌سنتر)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type', 20)->default('product')->index(); // product | package
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });

        // ---- دسته‌بندی‌های پیش‌فرض پروژه‌های قابل‌فروش ----
        $defaults = [
            ['name' => 'اسکریپت',   'slug' => 'script',      'sort_order' => 1],
            ['name' => 'قالب',      'slug' => 'template',    'sort_order' => 2],
            ['name' => 'اپلیکیشن',  'slug' => 'application', 'sort_order' => 3],
            ['name' => 'افزونه',    'slug' => 'plugin',      'sort_order' => 4],
            ['name' => 'پنل آماده', 'slug' => 'ready-panel', 'sort_order' => 5],
            ['name' => 'سایر',      'slug' => 'misc',        'sort_order' => 99],
        ];

        foreach ($defaults as $row) {
            \App\Models\Category::firstOrCreate(
                ['type' => 'product', 'slug' => $row['slug']],
                ['name' => $row['name'], 'sort_order' => $row['sort_order'], 'is_active' => true]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
