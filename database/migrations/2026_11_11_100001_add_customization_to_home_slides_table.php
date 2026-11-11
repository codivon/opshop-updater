<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * شخصی‌سازی کامل اسلایدهای «اسلایدر معرفی» (Task 73):
 *
 * - content_align: محل افقی محتوا (start راست / center وسط / end چپ) — RTL
 * - content_vpos:  محل عمودی محتوا (top بالا / center وسط / bottom پایین)
 * - button_style:  استایل دکمهٔ اسلاید (solid پررنگ / soft ملایم / link متنی)
 * - overlay:       شدت تیرگی لایهٔ خوانایی روی تصویر (۰ تا ۹۰ درصد؛ NULL = خودکار)
 * - new_tab:       بازشدن لینک دکمه در تب جدید
 *
 * همهٔ ستون‌ها پیش‌فرضِ همان رفتار فعلی را دارند تا پس از مایگریشن هیچ چیز
 * در ظاهر صفحه عوض نشود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('home_slides', function (Blueprint $table) {
            $table->string('content_align', 10)->default('start')->after('link_text');
            $table->string('content_vpos', 10)->default('center')->after('content_align');
            $table->string('button_style', 10)->default('solid')->after('content_vpos');
            $table->unsignedTinyInteger('overlay')->nullable()->after('button_style'); // null = خودکار
            $table->boolean('new_tab')->default(false)->after('overlay');
        });
    }

    public function down(): void
    {
        Schema::table('home_slides', function (Blueprint $table) {
            $table->dropColumn(['content_align', 'content_vpos', 'button_style', 'overlay', 'new_tab']);
        });
    }
};
