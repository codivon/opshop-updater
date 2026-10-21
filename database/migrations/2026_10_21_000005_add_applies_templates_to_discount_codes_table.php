<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * پشتیبانی کد تخفیف از قالب‌ها — ستون applies_templates (مانند applies_products).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discount_codes', function (Blueprint $table) {
            $table->boolean('applies_templates')->default(true)->after('applies_products');
        });
    }

    public function down(): void
    {
        Schema::table('discount_codes', function (Blueprint $table) {
            $table->dropColumn('applies_templates');
        });
    }
};
