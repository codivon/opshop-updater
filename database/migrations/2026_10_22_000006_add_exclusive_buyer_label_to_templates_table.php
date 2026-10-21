<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * برچسب عمومی خریدارِ اختصاصی قالب.
 *
 * پس از خرید اختصاصی، قالب از فروشگاه/API حذف می‌شود؛ این فیلد متن نمایشی
 * خریدار روی صفحهٔ قالب است (مثلاً «واگذارشده به: شرکت فلان»).
 * - هنگام پرداخت موفق خرید اختصاصی از نام مشتری (خرید API) خودکار پر می‌شود.
 * - برای خریدهای مهمانِ فروشگاه (بدون هویت) ادمین می‌تواند بعداً آن را ویرایش کند.
 * - ادمین می‌تواند قالب را دوباره «عمومی» کند یا دستی «فروخته‌شده» علامت بزند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            if (!Schema::hasColumn('templates', 'exclusive_buyer_label')) {
                $table->string('exclusive_buyer_label')->nullable()->after('sold_exclusively_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            if (Schema::hasColumn('templates', 'exclusive_buyer_label')) {
                $table->dropColumn('exclusive_buyer_label');
            }
        });
    }
};
