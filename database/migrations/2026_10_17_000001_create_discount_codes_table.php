<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * کدهای تخفیف (کوپن) — قابل اعمال روی همه‌ی خریدهای فروشگاه:
 *   - پکیج‌ها (package_purchases)
 *   - محصولات قابل‌فروش (product_purchases)
 *   - طرح‌های اشتراک (subscription_orders)
 *
 *  - type  = percent → «value» درصد تخفیف (۱..۱۰۰)
 *            fixed   → «value» مبلغ ثابت تخفیف به تومان (بیش از مبلغ خرید کاپ می‌شود)
 *  - max_uses  = null → بدون محدودیت تعداد
 *  - expires_at = null → بدون انقضا
 *  - ستون‌های applies_* → محدوده‌ی اعتبار کد روی هر نوع خرید (چندمقداری)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique(); // کد یکتا (ذخیره با حروف بزرگ)
            $table->string('type', 20)->default('percent'); // percent | fixed
            $table->unsignedInteger('value'); // درصد یا مبلغ ثابت (تومان)
            $table->unsignedInteger('max_uses')->nullable(); // null = نامحدود
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('expires_at')->nullable()->index();
            $table->boolean('applies_packages')->default(true);
            $table->boolean('applies_products')->default(true);
            $table->boolean('applies_subscriptions')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable(); // یادداشت مدیر
            $table->timestamps();

            $table->index(['is_active', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_codes');
    }
};
