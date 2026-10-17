<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * دسته‌بندی درختی: افزودن والد (parent_id) به دسته‌بندی‌ها.
 *
 * کاربرد: مشتری می‌تواند ساختار درختی دسته‌بندی فروشگاه خودش (مثلاً ووکامرس)
 * را روی پلتفرم بازسازی کند؛ هنگام نصب پکیج در فروشگاه، زنجیره کامل دسته
 * (path) از طریق API برگردانده می‌شود تا دسته فروشگاه به‌درستی انتخاب/ساخته شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('type')
                ->constrained('categories')
                ->nullOnDelete();

            $table->index(['type', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropIndex(['type', 'parent_id']);
        });
    }
};
