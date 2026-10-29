<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * پیکربندی مستقل «قابلیت‌ها» و «پکیج‌های همراه» برای هر مدت اعتبار طرح — Task 63.
 *
 * تاکنون قابلیت‌ها (features JSON) و پکیج‌های همراه (pivot free_months) فقط
 * در سطح طرح بودند و برای همهٔ مدت‌ها یکسان؛ حالا هر مدت می‌تواند پیکربندی
 * خودش را داشته باشد (مثلاً مدت ۳ ماهه → پکیج‌ها با ۳ ماه دسترسی رایگان).
 *  - features خالی/null روی مدت = ارث‌بری از قابلیت‌های خود طرح
 *  - نبود ردیف در جدول pivot جدید برای یک مدت = ارث‌بری از پکیج‌های خود طرح
 */
return new class extends Migration
{
    public function up(): void
    {
        // قابلیت‌های اختصاصی هر مدت (خالی = همان قابلیت‌های طرح)
        Schema::table('subscription_plan_durations', function (Blueprint $table) {
            $table->json('features')->nullable()->after('sort_order');
        });

        // پکیج‌های همراه هر مدت (نبود ردیف = همان پکیج‌های سطح طرح)
        Schema::create('subscription_plan_duration_package', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_plan_duration_id')->constrained('subscription_plan_durations')->cascadeOnDelete();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->unsignedSmallInteger('free_months')->default(1); // مدت دسترسی رایگان (ماه) — ۰ = نامحدود
            $table->timestamps();

            $table->unique(['subscription_plan_duration_id', 'package_id'], 'plan_duration_package_unique');
            $table->index('package_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plan_duration_package');

        Schema::table('subscription_plan_durations', function (Blueprint $table) {
            $table->dropColumn('features');
        });
    }
};
