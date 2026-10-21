<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * توکن‌های دانلود قالب — یک‌بارمصرف با انقضای ۱۵ دقیقه‌ای.
 * customer_id برای خریدهای API ست می‌شود (کنترل تعلق توکن به مشتری).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_download_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 80)->unique();
            $table->foreignId('template_id')->constrained('templates')->cascadeOnDelete();
            $table->foreignId('template_version_id')->constrained('template_versions')->cascadeOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained('template_purchases')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_download_tokens');
    }
};
