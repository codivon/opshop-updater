<?php

use App\Models\Category;
use App\Models\Package;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * پکیج‌ها هم دسته‌بندی ساختاریافته بگیرند:
 *  ۱) افزودن ستون category_id
 *  ۲) انتقال مقادیر متنی قدیمی category → رکوردهای جدول categories (type=package)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('category')->constrained('categories')->nullOnDelete();
        });

        // ---- انتقال دسته‌های متنی قدیمی به دسته‌بندی ساختاریافته ----
        $legacy = Package::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        foreach ($legacy as $name) {
            $cat = Category::firstOrCreate(
                ['type' => 'package', 'name' => trim($name)],
                [
                    'slug'      => Str::slug($name) ?: ('pkg-' . Str::lower(Str::random(6))),
                    'is_active' => true,
                    'sort_order' => 50,
                ]
            );

            Package::where('category', $name)->update(['category_id' => $cat->id]);
        }
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
