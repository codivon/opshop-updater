<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * قالب پیامک «کد ورود دومرحله‌ای مدیر» — برای 2FA با کانال پیامک
 * (پنل امنیت و امکانات → ورود دومرحله‌ای مدیر → کانال: پیامک/هر دو).
 */
return new class extends Migration
{
    private const KEY = 'admin_2fa_code';

    public function up(): void
    {
        if (! Schema::hasTable('sms_templates')) {
            return; // جدول پیامک موجود نیست (نصب قدیمی) → بی‌صدا رد شو
        }

        DB::table('sms_templates')->updateOrInsert(
            ['key' => self::KEY],
            [
                'title'     => 'مدیر: کد ورود دومرحله‌ای',
                'body'      => "🔐 کد ورود دومرحله‌ای شما: {code}\nاعتبار: ۱۰ دقیقه\n{site_name}",
                'variables' => json_encode(['code', 'site_name']),
                'pattern_codes' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('sms_templates')) {
            return;
        }

        DB::table('sms_templates')->where('key', self::KEY)->delete();
    }
};
