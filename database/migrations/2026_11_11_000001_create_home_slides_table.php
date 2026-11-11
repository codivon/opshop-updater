<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * اسلایدهای «اسلایدر معرفی» صفحهٔ اصلی فروشگاه (Task 71).
 *
 * - محتوای دستی از پنل /admin/settings/home (تب اسلایدر معرفی):
 *   تصویر + عنوان + متن + لینک/متن دکمه + نشان (badge) + ترتیب + فعال.
 * - وقتی هیچ اسلاید دستی فعالی نباشد، ویو به‌صورت خودکار بنرهای پکیج‌های
 *   فعال + بنر ویژهٔ InstaShop را می‌سازد (App\Support\Showcase) — یعنی
 *   «پیش‌فرض» بدون رکورد هم کار می‌کند و بعد از اولین اسلاید دستی، دستی‌ها
 *   اولویت دارند.
 * - رابطه/FK نداریم؛ نام ایندکس‌ها صریح و کوتاه (درس خطای 1059 MySQL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_slides', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('text', 500)->nullable();
            $table->string('badge', 50)->nullable();
            $table->string('image')->nullable();          // مسیر نسبی (uploads/home-slides/...)
            $table->string('link', 300)->nullable();      // خالی = فقط نمایش
            $table->string('link_text', 50)->nullable();  // پیش‌فرض «مشاهده»
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('is_active', 'home_slides_active_idx');
            $table->index('sort_order', 'home_slides_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_slides');
    }
};
