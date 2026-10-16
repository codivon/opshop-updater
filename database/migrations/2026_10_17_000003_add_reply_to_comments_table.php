<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * پاسخ مدیر به دیدگاه‌ها (چندریختی — محصول/پکیج):
 *   - reply:      متن پاسخ مدیر (null = پاسخ داده نشده)
 *   - replied_at: زمان ثبت آخرین پاسخ
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->text('reply')->nullable()->after('admin_note');
            $table->timestamp('replied_at')->nullable()->after('reply');
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropColumn(['reply', 'replied_at']);
        });
    }
};
