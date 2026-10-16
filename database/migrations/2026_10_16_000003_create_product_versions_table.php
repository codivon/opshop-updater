<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نسخه‌های محصول قابل‌فروش — فایل ZIP + changelog (مانند PackageVersion اما ساده‌تر).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('version', 50);
            $table->string('type', 20)->default('minor'); // major | minor | patch
            $table->text('changelog')->nullable();
            $table->text('what_added')->nullable();
            $table->text('what_changed')->nullable();
            $table->text('what_fixed')->nullable();
            $table->string('file_path')->nullable();       // روی disk «local» (storage/app/private/products/…)
            $table->string('file_hash', 64)->nullable();   // SHA256
            $table->unsignedBigInteger('file_size')->nullable(); // بایت
            $table->boolean('is_mandatory')->default(false);
            $table->string('status', 20)->default('active')->index(); // draft | active | archived
            $table->unsignedBigInteger('downloads_count')->default(0);
            $table->timestamp('release_date')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_versions');
    }
};
