<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * نوع خرید قالب: normal (معمولی) | exclusive (اختصاصی) + پیوند لایسنس.
 * رکوردهای قبلی → normal.
 *
 * idempotent و مقاوم به اجرای ناقص قبلی: چون DDL در MySQL تراکنشی نیست،
 * ممکن است در اجرای قبلی (که روی خطای FK متوقف شد) ستون‌ها ایندکس ساخته شده باشند
 * ولی کلید خارجی نه — بنابراین هر مرحله با گارد جداگانه اجرا می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('template_purchases')) {
            return;
        }

        // ---------- ۱) ستون‌ها (فقط اگر وجود ندارند) ----------
        Schema::table('template_purchases', function (Blueprint $table) {
            if (!Schema::hasColumn('template_purchases', 'purchase_type')) {
                $table->string('purchase_type', 20)->default('normal')->after('discount_amount');
            }

            if (!Schema::hasColumn('template_purchases', 'license_id')) {
                $table->unsignedBigInteger('license_id')->nullable()->after('purchase_type');
            }
        });

        // ---------- ۲) ایندکس purchase_type (فقط اگر وجود ندارد) ----------
        if (! $this->indexExists('template_purchases', 'template_purchases_purchase_type_index')) {
            Schema::table('template_purchases', function (Blueprint $table) {
                $table->index('purchase_type', 'template_purchases_purchase_type_index');
            });
        }

        // ---------- ۳) کلید خارجی به template_licenses (فقط اگر وجود ندارد) ----------
        if (! $this->foreignKeyExists('template_purchases', 'template_purchases_license_id_foreign')) {
            Schema::table('template_purchases', function (Blueprint $table) {
                $table->foreign('license_id')
                    ->references('id')
                    ->on('template_licenses')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('template_purchases')) {
            return;
        }

        if ($this->foreignKeyExists('template_purchases', 'template_purchases_license_id_foreign')) {
            Schema::table('template_purchases', function (Blueprint $table) {
                $table->dropForeign('template_purchases_license_id_foreign');
            });
        }

        Schema::table('template_purchases', function (Blueprint $table) {
            if ($this->indexExists('template_purchases', 'template_purchases_purchase_type_index')) {
                $table->dropIndex('template_purchases_purchase_type_index');
            }
        });

        Schema::table('template_purchases', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('template_purchases', 'license_id')     ? 'license_id'     : null,
                Schema::hasColumn('template_purchases', 'purchase_type')  ? 'purchase_type'  : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    /**
     * بررسی وجود ایندکس از information_schema (سازگار با MySQL و MariaDB).
     */
    private function indexExists(string $table, string $index): bool
    {
        try {
            $row = DB::selectOne(
                'SELECT COUNT(*) AS c FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
                [$table, $index],
            );

            return (bool) ($row?->c ?? 0);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * بررسی وجود کلید خارجی از information_schema.
     */
    private function foreignKeyExists(string $table, string $constraint): bool
    {
        try {
            $row = DB::selectOne(
                'SELECT COUNT(*) AS c FROM information_schema.TABLE_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ?
                   AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = \'FOREIGN KEY\'',
                [$table, $constraint],
            );

            return (bool) ($row?->c ?? 0);
        } catch (\Throwable) {
            return false;
        }
    }
};
