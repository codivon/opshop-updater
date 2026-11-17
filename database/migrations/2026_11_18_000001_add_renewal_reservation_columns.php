<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 87 — رزرو تمدید (Renewal Reservation)
 *
 * وقتی مشتری در ۵ روزِ پایانیِ اعتبار، همان پکیج/طرح را دوباره می‌خرد:
 *  - پکیج: purchase پرداخت می‌شود اما لایسنس «رزرو» است تا دورهٔ فعلی تمام شود؛
 *    کالکسیون زمان‌بندی‌شدهٔ renewals:activate پس از انقضا لایسنس را صادر و پیامک می‌دهد.
 *  - اشتراک: سفارش با مسیر عادی تأیید می‌شود اما اشتراکِ ساخته‌شده با وضعیت
 *    «reserved» و تاریخ فعال‌سازی مشخص ذخیره می‌شود تا بعد از پایان اشتراک فعلی
 *    به‌صورت خودکار فعال شود.
 *
 * ستون‌ها به‌جای متا — تا کوئری‌های کالکسیون (SQLite/MySQL) بدون JSON_EXTRACT
 * به‌صورت ایندکس‌پذیر و مقاوم اجرا شوند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_purchases', function (Blueprint $table) {
            $table->dateTime('reserved_activate_at')->nullable()->after('meta')
                ->comment('زمان فعال‌سازی خودکار لایسنس رزروشده (پایان دورهٔ فعلی مشتری)');
            $table->unsignedBigInteger('reserved_source_license_id')->nullable()->after('reserved_activate_at')
                ->comment('لایسنس فعلی مشتری که این خرید پس از پایان آن فعال می‌شود');
            $table->dateTime('reserved_activated_at')->nullable()->after('reserved_source_license_id')
                ->comment('زمان واقعی فعال‌سازی خودکار (null = هنوز در انتظار)');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dateTime('reserved_activate_at')->nullable()->after('description')
                ->comment('زمان فعال‌سازی خودکار اشتراک رزروشده (پایان اشتراک فعلی مشتری)');
        });
    }

    public function down(): void
    {
        Schema::table('package_purchases', function (Blueprint $table) {
            $table->dropColumn(['reserved_activate_at', 'reserved_source_license_id', 'reserved_activated_at']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('reserved_activate_at');
        });
    }
};
