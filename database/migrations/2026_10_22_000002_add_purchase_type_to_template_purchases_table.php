<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نوع خرید قالب: normal (معمولی) | exclusive (اختصاصی) + پیوند لایسنس.
 * رکوردهای قبلی → normal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_purchases', function (Blueprint $table) {
            $table->string('purchase_type', 20)->default('normal')->after('discount_amount')->index();
            $table->foreignId('license_id')->nullable()->after('purchase_type')->constrained('template_licenses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('template_purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('license_id');
            $table->dropIndex(['purchase_type']);
            $table->dropColumn('purchase_type');
        });
    }
};
