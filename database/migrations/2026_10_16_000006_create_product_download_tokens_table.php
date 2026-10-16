<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * توکن‌های دانلود محصول — یک‌بارمصرف با انقضای ۱۵ دقیقه‌ای.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_download_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 80)->unique();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_version_id')->constrained('product_versions')->cascadeOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained('product_purchases')->nullOnDelete();
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_download_tokens');
    }
};
