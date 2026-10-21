<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * خریدهای قالب:
 *  - فروشگاه (مهمان): customer_id = null
 *  - API با کد آپدیت مشتری (مانند جریان پکیج‌ها): customer_id ست می‌شود
 *    تا دانلود فقط برای «خریدارِ همان مشتری» مجاز باشد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('templates')->cascadeOnDelete();
            $table->foreignId('version_id')->nullable()->constrained('template_versions')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('transaction_id')->nullable()->index();
            $table->foreignId('discount_code_id')->nullable()->constrained('discount_codes')->nullOnDelete();
            $table->unsignedBigInteger('original_amount')->nullable();
            $table->unsignedBigInteger('amount')->default(0);
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->string('gateway', 50)->nullable();
            $table->text('payment_url')->nullable();
            $table->text('callback_url')->nullable();
            $table->string('status', 20)->default('pending')->index(); // pending | paid | failed | refunded
            $table->timestamp('paid_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['status', 'paid_at']);
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_purchases');
    }
};
