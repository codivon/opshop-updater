<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * پروژه‌های قابل‌فروش (محصولات فروشگاه) — اسکریپت، قالب، اپلیکیشن و…
 * مستقل از Package (پکیج آپدیت‌سنتر) و Project (کانتینر آپدیت).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable(); // HTML (CKEditor)
            $table->string('thumbnail')->nullable();      // uploads/products/thumbnails/…
            $table->unsignedBigInteger('price')->default(0);
            $table->unsignedTinyInteger('discount')->default(0); // درصد تخفیف 0..100
            $table->boolean('is_free')->default(false);
            $table->string('status', 20)->default('draft')->index(); // draft | active | archived
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('sales_count')->default(0);
            $table->unsignedBigInteger('views_count')->default(0);
            $table->timestamps();

            $table->index(['status', 'is_free']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
