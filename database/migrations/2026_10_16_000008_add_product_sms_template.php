<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * قالب پیامک مدیر: فروش موفق محصول (پروژه‌ی قابل‌فروش).
 */
return new class extends Migration
{
    private const KEY = 'admin_product_paid';

    public function up(): void
    {
        DB::table('sms_templates')->updateOrInsert(
            ['key' => self::KEY],
            [
                'title'     => 'مدیر: فروش موفق محصول',
                'body'      => "📥 فروش موفق محصول\nمحصول: {product_name}\nمبلغ: {amount} تومان\nدرگاه: {gateway}\n{site_name}",
                'variables' => json_encode(['product_name', 'amount', 'gateway', 'site_name']),
                'pattern_codes' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('sms_templates')->where('key', self::KEY)->delete();
    }
};
