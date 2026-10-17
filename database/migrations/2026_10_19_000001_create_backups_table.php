<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * عملیات پشتیبان‌گیری/بازیابی به‌صورت پس‌زمینه‌ای و تکه‌ای (time-sliced) اجرا می‌شود
 * تا حتی با دیتابیس و فایل‌های حجیم، ارور تایم‌اوت گرفته نشود.
 *
 * هر رکورد = یک عملیات:
 *  - type: backup (ساخت ZIP شامل دیتابیس + فایل‌ها) | restore (بازیابی همان ZIP)
 *  - status: pending → running → completed | failed
 *  - cursor: نقطهٔ توقف (جدول/فایل/بایت) برای ادامه از همان‌جا در تیک بعدی
 *  - report: گزارش کامل بازیابی/ساخت (تعداد رکورد و فایل، ردشده‌ها، خطاها)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);                                   // backup | restore
            $table->string('status', 20)->default('pending');             // pending | running | completed | failed
            $table->string('name');                                       // نام نمایشی (نام فایل ZIP بدون پسوند)
            $table->string('original_filename')->nullable();              // نام اصلی فایل آپلودی (برای بازیابی)
            $table->string('disk_path')->nullable();                      // مسیر نسبی زیر storage/app
            $table->unsignedBigInteger('size')->default(0);               // حجم فایل نهایی (بایت)
            $table->boolean('include_files')->default(true);              // مشمول فایل‌ها و تصاویر؟
            $table->unsignedTinyInteger('progress')->default(0);          // ۰ تا ۱۰۰
            $table->string('phase')->nullable();                          // توضیح مرحلهٔ جاری
            $table->json('cursor')->nullable();                           // نقطهٔ توقف برای ادامه
            $table->json('report')->nullable();                           // گزارش نهایی
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
