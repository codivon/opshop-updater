<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مدت‌های اعتبار طرح اشتراک (چندگزینه‌ای):
 *
 * هر طرح می‌تواند چند مدت اعتبار داشته باشد (مثلاً ۱ ماهه / ۳ ماهه / ۶ ماهه / سالانه)
 * و هر مدت قیمت و تخفیف مستقل خودش را دارد؛ مشتری هنگام خرید یکی را انتخاب می‌کند.
 *
 * طرح‌هایی که ردیفی ندارند دقیقاً مثل قبل از فیلدهای تک‌مدتِ خودِ طرح
 * (duration_months + price + discount_price) کار می‌کنند — سازگاری کامل با داده‌های فعلی.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plan_durations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->unsignedSmallInteger('duration_months');           // مدت اعتبار (ماه) — ۰ = نامحدود
            $table->unsignedBigInteger('price')->default(0);           // قیمت این مدت (تومان) — ۰ = رایگان
            $table->unsignedBigInteger('discount_price')->nullable();  // مبلغ تخفیف این مدت
            $table->boolean('is_active')->default(true);               // فعال در فروشگاه
            $table->integer('sort_order')->default(0);                 // ترتیب نمایش گزینه‌ها
            $table->timestamps();

            $table->unique(['subscription_plan_id', 'duration_months']); // هر مدت فقط یک‌بار در هر طرح
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plan_durations');
    }
};
