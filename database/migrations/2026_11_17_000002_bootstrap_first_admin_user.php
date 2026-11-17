<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Task 85 — امنیت: با اجبار نقش admin روی /admin/*، باید حداقل یک مدیر واقعی وجود داشته باشد.
 * اگر هیچ کاربری با role=admin نبود، قدیمی‌ترین حساب (اولین کاربر = مالک سایت)
 * به نقش admin ارتقا می‌یابد تا مالک پنل را از دست ندهد.
 * اگر کاربر admin وجود دارد، هیچ تغییری انجام نمی‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        $hasAdmin = DB::table('users')->where('role', 'admin')->exists();

        if (! $hasAdmin) {
            $firstUserId = DB::table('users')->orderBy('id')->value('id');

            if ($firstUserId !== null) {
                DB::table('users')->where('id', $firstUserId)->update(['role' => 'admin']);
            }
        }
    }

    public function down(): void
    {
        // بازگشت‌پذیر نیست — نقش مدیر حفظ می‌شود (امنیت مهم‌تر است)
    }
};
