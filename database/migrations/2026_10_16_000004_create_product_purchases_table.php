<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * خریدهای محصول (مهمان — بدون کد آپدیت/ثبت‌نام).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('version_id')->nullable()->constrained('product_versions')->nullOnDelete();
            $table->string('transaction_id')->nullable()->index();
            $table->unsignedBigInteger('amount')->default(0);
            $table->string('gateway', 50)->nullable();
            $table->text('payment_url')->nullable();
            $table->text('callback_url')->nullable();
            $table->string('status', 20)->default('pending')->index(); // pending | paid | failed | refunded
            $table->timestamp('paid_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['status', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_purchases');
    }
};
