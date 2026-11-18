<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 94 — قالب‌ها مثل پکیج‌ها به «پروژه» گره می‌خورند:
 *
 *  - فرم افزودن/ویرایش قالب در پنل، انتخاب پروژه را الزامی می‌کند.
 *  - API قالب‌ها (index/show/purchase) فقط قالب‌های پروژه‌هایی را می‌بیند که
 *    مشتریِ احرازشده رویشان اشتراکِ فعال دارد (همان الگوی API پکیج‌ها).
 *
 * ستون nullable است تا ردیف‌های قدیمی بشکنند؛ اما فرم پنل از این پس پروژه را
 * اجباری می‌گیرد و قالبِ بدون پروژه در API دیده نمی‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('category_id')
                ->constrained('projects')->nullOnDelete();
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropIndex(['project_id']);
            $table->dropConstrainedForeignId('project_id');
        });
    }
};
