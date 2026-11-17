<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 82 — فیلدهای سئوی اختصاصی هر پکیج/محصول/قالب:
 * meta_title و meta_description اختیاری؛ خالی = عنوان/توضیح خود رکورد استفاده می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->string('meta_title')->nullable()->after('sort_order');
            $table->text('meta_description')->nullable()->after('meta_title');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('meta_title')->nullable()->after('sort_order');
            $table->text('meta_description')->nullable()->after('meta_title');
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->string('meta_title')->nullable()->after('sort_order');
            $table->text('meta_description')->nullable()->after('meta_title');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'meta_description']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'meta_description']);
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'meta_description']);
        });
    }
};
