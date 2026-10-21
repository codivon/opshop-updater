<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * قیمت‌گذاری دوگانه قالب‌ها:
 *  - خرید معمولی: price/discount (موجود)
 *  - خرید اختصاصی: exclusive_price/exclusive_discount (جدید)
 *
 * خرید اختصاصی = واگذاری کامل و انحصاری قالب به یک خریدار؛
 * پس از نخستین خرید اختصاصی موفق، قالب با پرچم is_sold_exclusively از فروشگاه
 * و API حذف می‌شود (فروش مجدد ندارد).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->unsignedBigInteger('exclusive_price')->default(0)->after('discount');
            $table->unsignedTinyInteger('exclusive_discount')->default(0)->after('exclusive_price');
            $table->text('exclusive_note')->nullable()->after('exclusive_discount'); // توضیح خرید اختصاصی
            $table->boolean('is_sold_exclusively')->default(false)->after('exclusive_note');
            $table->timestamp('sold_exclusively_at')->nullable()->after('is_sold_exclusively');

            $table->index('is_sold_exclusively');
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropIndex(['is_sold_exclusively']);
            $table->dropColumn([
                'exclusive_price', 'exclusive_discount', 'exclusive_note',
                'is_sold_exclusively', 'sold_exclusively_at',
            ]);
        });
    }
};
