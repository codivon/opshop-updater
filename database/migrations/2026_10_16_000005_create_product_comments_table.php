<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * دیدگاه‌های محصول — مهمان با تأیید مدیر (pending/approved/rejected).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('email')->nullable();
            $table->unsignedTinyInteger('rating')->nullable(); // 1..5
            $table->text('body');
            $table->string('status', 20)->default('pending')->index(); // pending | approved | rejected
            $table->string('admin_note')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_comments');
    }
};
