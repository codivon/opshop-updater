<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * گالری تصاویر قالب‌ها — ساختار عیناً مانند package_images.
 * فایل‌ها با ImageUploadService در uploads/templates/gallery/ ذخیره می‌شوند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('templates')->cascadeOnDelete();
            $table->string('path');            // uploads/templates/gallery/xxx.jpg
            $table->string('original_name')->nullable();
            $table->unsignedBigInteger('size')->nullable(); // بایت
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['template_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_images');
    }
};
