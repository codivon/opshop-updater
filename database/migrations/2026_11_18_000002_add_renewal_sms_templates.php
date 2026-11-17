<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Task 87 — قالب‌های پیامک رزرو/فعال‌سازی خودکار تمدید
 *
 *  - package_reserved        : خرید در ۵ روز پایانی ثبت و «رزرو» شد (پس از تأیید پرداخت)
 *  - package_renewal_activated : لایسنس رزروشده پس از پایان دورهٔ فعلی به‌صورت خودکار فعال شد
 *  - subscription_reserved   : خرید طرح در ۵ روز پایانی ثبت و «رزرو» شد (پس از تأیید مدیر)
 *
 * متن‌ها و کد پترن هر درایور از پنل «پیامک‌ها ← قالب‌ها» قابل ویرایش است.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $defaults = [
            [
                'key' => 'package_reserved',
                'title' => 'رزرو خرید پکیج (فعال‌سازی خودکار پس از پایان دوره)',
                'body' => "{customer_name} عزیز\nپرداخت مبلغ {amount} تومان برای پکیج «{package_name}» با موفقیت انجام شد.\nاین خرید به‌صورت رزرو ذخیره شد و پس از پایان دورهٔ فعلی شما (تاریخ {end_date}) به‌طور خودکار فعال می‌شود.\n{site_name}",
                'variables' => ['customer_name', 'package_name', 'amount', 'end_date', 'site_name'],
            ],
            [
                'key' => 'package_renewal_activated',
                'title' => 'فعال‌شدن خودکار لایسنس رزروشده',
                'body' => "{customer_name} عزیز\nخرید رزروشدهٔ شما برای پکیج «{package_name}» فعال شد.\nکد لایسنس جدید: {license_key}\nاعتبار تا تاریخ {end_date}.\n{site_name}",
                'variables' => ['customer_name', 'package_name', 'license_key', 'end_date', 'site_name'],
            ],
            [
                'key' => 'subscription_reserved',
                'title' => 'رزرو خرید طرح اشتراک (فعال‌سازی خودکار پس از پایان دوره)',
                'body' => "{customer_name} عزیز\nخرید طرح «{plan_name}» شما ثبت شد.\nاین خرید به‌صورت رزرو ذخیره شده و پس از پایان اشتراک فعلی شما (تاریخ {end_date}) به‌طور خودکار فعال می‌شود.\n{site_name}",
                'variables' => ['customer_name', 'plan_name', 'end_date', 'site_name'],
            ],
        ];

        foreach ($defaults as $tpl) {
            DB::table('sms_templates')->updateOrInsert(
                ['key' => $tpl['key']],
                [
                    'title' => $tpl['title'],
                    'body' => $tpl['body'],
                    'variables' => json_encode($tpl['variables']),
                    'pattern_codes' => json_encode((object) []),
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('sms_templates')->whereIn('key', [
            'package_reserved',
            'package_renewal_activated',
            'subscription_reserved',
        ])->delete();
    }
};
