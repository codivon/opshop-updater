<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Task 85 — امنیت (CRITICAL C3/C4): توکن محرمانهٔ مخصوص هر خرید.
 *
 * صفحات «نتیجهٔ پرداخت / بازگشت پرداخت» لایسنس و لینک دانلود نشان می‌دهند؛
 * قبل از این تغییر با شمردن شناسهٔ خرید (۱، ۲، ۳، …) هر غریبه‌ای می‌توانست
 * لایسنس دیگران را ببیند و فایل‌های خریداری‌شده را دانلود کند.
 * حالا هر خرید یک توکن تصادفی ۶۴ نویسه‌ای دارد و صفحات فقط با آن توکن باز می‌شوند.
 */
return new class extends Migration
{
    private const TABLES = ['package_purchases', 'product_purchases', 'template_purchases', 'subscription_orders'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('access_token', 64)->nullable()->unique()->after('id');
            });

            // توکن برای رکوردهای موجود (لینک‌های قدیمی فقط با توکن باز می‌شوند)
            $rows = DB::table($table)->whereNull('access_token')->select('id')->get();

            foreach ($rows as $row) {
                DB::table($table)->where('id', $row->id)->update(['access_token' => Str::random(64)]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('access_token');
            });
        }
    }
};
