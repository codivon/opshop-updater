<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * قالب پیامک مدیر: فروش موفق قالب.
 */
return new class extends Migration
{
    private const KEY = 'admin_template_paid';

    public function up(): void
    {
        DB::table('sms_templates')->updateOrInsert(
            ['key' => self::KEY],
            [
                'title'     => 'مدیر: فروش موفق قالب',
                'body'      => "📥 فروش موفق قالب\nقالب: {template_name}\nمبلغ: {amount} تومان\nدرگاه: {gateway}\n{site_name}",
                'variables' => json_encode(['template_name', 'amount', 'gateway', 'site_name']),
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
