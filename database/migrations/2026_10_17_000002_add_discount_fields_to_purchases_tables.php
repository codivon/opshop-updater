<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * افزودن ستون‌های کد تخفیف به هر سه جدول خرید:
 *   - package_purchases / product_purchases:
 *       amount همان «مبلغ نهایی قابل پرداخت» می‌ماند (درگاه بر همین مبنا شارژ می‌کند)؛
 *       original_amount مبلغ قبل از کد تخفیف و discount_amount مبلغ کسرشده را نگه می‌دارد.
 *   - subscription_orders: amount/discount/final_amount از قبل وجود دارد → فقط discount_code_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['package_purchases', 'product_purchases'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('discount_code_id')->nullable()->after('transaction_id')
                    ->constrained('discount_codes')->nullOnDelete();
                $t->unsignedBigInteger('original_amount')->nullable()->after('amount');
                $t->unsignedBigInteger('discount_amount')->default(0)->after('original_amount');
            });
        }

        Schema::table('subscription_orders', function (Blueprint $t) {
            $t->foreignId('discount_code_id')->nullable()->after('customer_id')
                ->constrained('discount_codes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['package_purchases', 'product_purchases'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('discount_code_id');
                $t->dropColumn(['original_amount', 'discount_amount']);
            });
        }

        Schema::table('subscription_orders', function (Blueprint $t) {
            $t->dropConstrainedForeignId('discount_code_id');
        });
    }
};
