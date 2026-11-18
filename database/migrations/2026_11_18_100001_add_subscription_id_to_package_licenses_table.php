<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 89 — پیوند لایسنس پکیج به اشتراکِ صادرکننده
 *
 * لایسنس‌هایی که از طریق اشتراکِ طرح‌محور صادر می‌شوند (تأیید سفارش طرح،
 * فعال‌سازی رزرو تمدید، صدور دستی از طرح و مسیر API) باید تا انضای همان
 * اشتراک معتبر باشند — نه بیشتر. با این ستون:
 *  ۱) انقضای اشتراک → لایسنس هم غیرفعال می‌شود (اشتراک تمام شد؛ پکیج بسته).
 *  ۲) تغییر انضای اشتراک توسط مدیر (+۳م/-۱م/ویرایش) → لایسنس‌ها همگام می‌شوند.
 *  ۳) کالکسیون subscriptions:sync-licenses داده‌های خراب قدیمی را اصلاح می‌کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_licenses', function (Blueprint $table) {
            $table->foreignId('subscription_id')
                ->nullable()
                ->after('purchase_id')
                ->constrained('subscriptions')
                ->nullOnDelete();

            $table->index('subscription_id');
        });
    }

    public function down(): void
    {
        Schema::table('package_licenses', function (Blueprint $table) {
            if (Schema::hasColumn('package_licenses', 'subscription_id')) {
                // SQLite: حذف ستون با FK از طریق dropColumn (لاراول ۱۱ dropConstrainedForeignId را پشتیبانی می‌کند)
                $table->dropConstrainedForeignId('subscription_id');
            }
        });
    }
};
