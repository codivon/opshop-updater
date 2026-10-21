<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * لایسنس‌های قالب — گواهی مالکیت/حق استفاده پس از خرید موفق.
 *
 * خرید قالب یک‌باره است (نه اشتراک) → انقضا null یعنی «دائمی».
 * برای خریدهای اختصاصی نیز همین لایسنس صادر می‌شود.
 * ساختار هم‌تراز package_orders... (package_licenses) تا گزارش‌ها یکسان کار کنند.
 *
 * ⚠️ این مایگریشن باید «قبل از» 2026_10_22_000003 اجرا شود چون
 * template_purchases.license_id کلید خارجی به این جدول دارد.
 * (قبلاً بعد از آن بود و خطای 1824 «Failed to open the referenced table» می‌داد)
 *
 * idempotent: اگر جدول قبلاً ساخته شده باشد (اجرای ناقص قبلی)، رد می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('template_licenses')) {
            return;
        }

        Schema::create('template_licenses', function (Blueprint $table) {
            $table->id();
            $table->string('license_key')->unique(); // TMP-XXXX-XXXX-XXXX-XXXX
            $table->foreignId('template_id')->constrained('templates')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained('template_purchases')->nullOnDelete();
            $table->string('purchase_type', 20)->default('normal'); // normal | exclusive
            $table->string('status', 20)->default('active')->index(); // active | expired | revoked
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // null = دائمی
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['template_id', 'status']);
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_licenses');
    }
};
