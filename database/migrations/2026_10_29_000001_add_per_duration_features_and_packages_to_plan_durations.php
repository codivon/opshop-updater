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
 *
 * نکتهٔ MySQL (خطای 1059): نام پیش‌فرض کلید خارجی
 * subscription_plan_duration_package_subscription_plan_duration_id_foreign
 * (۷۲ کاراکتر) از سقف ۶۴ کاراکتری شناسه‌های MySQL عبور می‌کند؛ بنابراین همهٔ
 * محدودیت‌ها نام کوتاه صریح دارند. اجرای مایگریشن هم «خودترمیم» است: اگر اجرای
 * قبلی روی میزبانی نیمه‌کاره مانده باشد (چون DDL تراکنشی MySQL نیست، ستون و
 * جدول ساخته شده ولی FKها ثبت نشده‌اند)، بدون خطا فقط محدودیت‌های جاافتاده
 * اضافه می‌شوند و مایگریشن به‌عنوان اجراشده ثبت می‌شود.
 */
return new class extends Migration
{
    /** نام‌های صریح و کوتاه کلیدهای خارجی (سقف ۶۴ کاراکتر MySQL) */
    private const FK_DURATION = 'plan_duration_package_duration_fk'; // ۳۳ کاراکتر

    private const FK_PACKAGE = 'plan_duration_package_package_fk'; // ۳۲ کاراکتر

    private const PIVOT_TABLE = 'subscription_plan_duration_package';

    public function up(): void
    {
        // قابلیت‌های اختصاصی هر مدت (خالی = همان قابلیت‌های طرح)
        if (! Schema::hasColumn('subscription_plan_durations', 'features')) {
            Schema::table('subscription_plan_durations', function (Blueprint $table) {
                $table->json('features')->nullable()->after('sort_order');
            });
        }

        // پکیج‌های همراه هر مدت (نبود ردیف = همان پکیج‌های سطح طرح)
        if (! Schema::hasTable(self::PIVOT_TABLE)) {
            Schema::create(self::PIVOT_TABLE, function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('subscription_plan_duration_id');
                $table->unsignedBigInteger('package_id');
                $table->unsignedSmallInteger('free_months')->default(1); // مدت دسترسی رایگان (ماه) — ۰ = نامحدود
                $table->timestamps();

                $table->unique(['subscription_plan_duration_id', 'package_id'], 'plan_duration_package_unique');
                $table->index('package_id', 'plan_duration_package_package_idx');

                $table->foreign('subscription_plan_duration_id', self::FK_DURATION)
                    ->references('id')->on('subscription_plan_durations')
                    ->cascadeOnDelete();
                $table->foreign('package_id', self::FK_PACKAGE)
                    ->references('id')->on('packages')
                    ->cascadeOnDelete();
            });

            return; // نصب تازه: محدودیت‌ها همین‌جا ساخته شده‌اند
        }

        // ترمیم اجرای نیمه‌کارهٔ قبلی روی MySQL: جدول هست ولی FKها ثبت نشده‌اند
        $this->addForeignKeyIfMissing(self::FK_DURATION, 'subscription_plan_duration_id', 'subscription_plan_durations');
        $this->addForeignKeyIfMissing(self::FK_PACKAGE, 'package_id', 'packages');
    }

    /**
     * افزودن کلید خارجی فقط اگر از قبل وجود نداشته باشد.
     * فقط برای MySQL؛ در SQLite افزودن FK پس از ساخت جدول پشتیبانی نمی‌شود
     * و محیط توسعه (SQLite) نیازی به این ترمیم ندارد.
     */
    private function addForeignKeyIfMissing(string $constraint, string $column, string $referencedTable): void
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() !== 'mysql') {
            return;
        }

        $exists = $connection->table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $connection->getDatabaseName())
            ->where('TABLE_NAME', self::PIVOT_TABLE)
            ->where('CONSTRAINT_NAME', $constraint)
            ->exists();

        if (! $exists) {
            Schema::table(self::PIVOT_TABLE, function (Blueprint $table) use ($column, $referencedTable, $constraint) {
                $table->foreign($column, $constraint)
                    ->references('id')->on($referencedTable)
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(self::PIVOT_TABLE);

        if (Schema::hasColumn('subscription_plan_durations', 'features')) {
            Schema::table('subscription_plan_durations', function (Blueprint $table) {
                $table->dropColumn('features');
            });
        }
    }
};
