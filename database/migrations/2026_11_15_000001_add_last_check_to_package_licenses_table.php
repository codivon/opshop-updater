<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سخت‌سازی امنیتی لایسنس‌ها (فاز ۱) — ضربان قلب لایسنس روی سرور بازار.
 *
 * ستون‌های last_check_* روی package_licenses:
 *   • last_check_at     — آخرین باری که کلاینت (پروژهٔ فروشگاه) verify-license زده
 *   • last_check_ip     — IP درخواست‌کنندهٔ آخرین چک
 *   • last_check_domain — هدر X-Project-Url درخواست‌کنندهٔ آخرین چک
 *
 * کاربرد: در پنل بازار، لایسنس‌هایی که مدت‌ها چک نکرده‌اند (احتمال فروشگاه
 * غیرفعال/بسته‌شده) و ناهنجاری‌ها (یک لایسنس روی چند دامنه یا چند IP) قابل
 * شناسایی هستند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_licenses', function (Blueprint $table) {
            $table->timestamp('last_check_at')->nullable()->after('duration_months');
            $table->string('last_check_ip', 45)->nullable()->after('last_check_at');
            $table->string('last_check_domain', 191)->nullable()->after('last_check_ip');

            $table->index('last_check_at');
        });
    }

    public function down(): void
    {
        Schema::table('package_licenses', function (Blueprint $table) {
            $table->dropIndex(['last_check_at']);
            $table->dropColumn(['last_check_at', 'last_check_ip', 'last_check_domain']);
        });
    }
};
