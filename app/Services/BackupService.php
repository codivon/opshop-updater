<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * پشتیبان‌گیری و بازیابی کامل دیتابیس (JSON).
 *
 *  خروجی: { meta: {...}, tables: { نام‌جدول: [رکورد, رکورد, …] } }
 *
 *  بازیابی («skip if exists» — طبق درخواست کاربر):
 *   - هیچ رکوردی حذف یا بازنویسی نمی‌شود؛
 *   - فقط رکوردهایی درج می‌شوند که شناسه (id) آن‌ها در دیتابیس فعلی وجود ندارد؛
 *   - رکورد تکراری (id موجود یا کلید یکتا مثل slug/email) رد می‌شود و روند ادامه می‌یابد؛
 *   - درج رکوردها با id اصلی انجام می‌شود تا روابط (FK) سالم بمانند؛
 *   - جداول سیستمی (migrations، cache، session، jobs و…) پشتیبان/بازیابی نمی‌شوند؛
 *   - قیود کلید خارجی هنگام بازیابی موقتاً خاموش و در پایان روشن می‌شود.
 */
class BackupService
{
    /** نسخه ساختار فایل بک‌آپ */
    public const FORMAT_VERSION = 1;

    /** جداول سیستمی/فریمورک که دادهٔ کسب‌وکاری ندارند */
    public const EXCLUDED_TABLES = [
        'migrations', 'sessions', 'cache', 'cache_locks',
        'jobs', 'job_batches', 'failed_jobs',
        'personal_access_tokens', 'password_reset_tokens',
        'sqlite_sequence', 'sqlite_stat1',
    ];

    /**
     * ترتیب ترجیحی درج (جدول‌های والد قبل از فرزند).
     * فقط اسامی موجود واقعاً لحاظ می‌شوند؛ بقیه جداول بعداً الفبایی اضافه می‌شوند.
     */
    protected const PRIORITY_ORDER = [
        'users', 'projects', 'updates', 'categories',
        'packages', 'package_versions', 'package_pricing_plans', 'package_images',
        'products', 'product_versions',
        'subscription_plans', 'subscription_plan_package',
        'customers', 'gateways', 'gateway_configs', 'settings',
        'sms_templates', 'comments', 'discount_codes',
        'subscriptions', 'subscription_requests',
        'package_licenses', 'package_purchases', 'package_download_tokens',
        'product_purchases', 'product_download_tokens',
        'subscription_orders', 'sms_logs',
    ];

    /* ================================================================
     *  Export
     * ================================================================ */

    /** فهرست جداول داده‌ای به ترتیب امن برای بازیابی */
    public static function tableNames(): array
    {
        $all = collect(Schema::getTableListing())
            ->map(fn ($name) => strtolower((string) $name))
            ->reject(fn ($name) => in_array($name, self::EXCLUDED_TABLES, true))
            ->unique()
            ->values();

        $priority = collect(self::PRIORITY_ORDER)
            ->filter(fn ($name) => $all->contains($name))
            ->values();

        $rest = $all->diff($priority->all())->sort()->values();

        return $priority->merge($rest)->all();
    }

    /**
     * خروجی کامل: meta + همه جداول (مرتب‌شده بر اساس id).
     *
     * @return array<string, mixed>
     */
    public static function export(): array
    {
        $payload = [
            'meta'   => [
                'app'          => config('app.name', 'opshop-updater'),
                'url'          => config('app.url'),
                'generated_at' => now()->toIso8601String(),
                'db_driver'    => DB::getDriverName(),
                'php'          => PHP_VERSION,
                'format'       => self::FORMAT_VERSION,
            ],
            'tables' => [],
        ];

        foreach (self::tableNames() as $table) {
            try {
                $query = DB::table($table);

                // مرتب‌سازی بر اساس id (اگر ستونش باشد) تا در بازیابی هم والد قبل از فرزند درج شود
                if (Schema::hasColumn($table, 'id')) {
                    $query->orderBy('id');
                }

                $payload['tables'][$table] = $query->get()
                    ->map(fn ($row) => (array) $row)
                    ->all();
            } catch (Throwable $e) {
                // جدول خراب/غیرقابل‌خواندن (مثلاً Tablespace is missing) → کل بک‌آپ نباید بشکند؛
                // جدول رد و خطایش در متای فایل ثبت می‌شود تا مدیر در جریان باشد.
                $payload['meta']['errors'][$table] = $e->getMessage();
            }
        }

        return $payload;
    }

    /** خروجی JSON آمادهٔ دانلود */
    public static function exportToJson(): string
    {
        return (string) json_encode(self::export(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** نام فایل پیشنهادی برای دانلود */
    public static function fileName(): string
    {
        return 'backup-' . now()->format('Ymd-His') . '.json';
    }

    /* ================================================================
     *  Restore
     * ================================================================ */

    /**
     * بازیابی از JSON با قانون «اگر از قبل بود، رد شو».
     *
     * @param  string  $json  محتوای فایل پشتیبان
     * @return array{restored:int, skipped:int, tables:int, skipped_tables:string[], unknown_tables:string[], errors:array<string,int>}
     *
     * @throws Throwable وقتی ساختار فایل معتبر نیست یا خطای کلی رخ می‌دهد
     */
    public static function restoreFromJson(string $json): array
    {
        $data = json_decode($json, true);

        if (! is_array($data) || ! isset($data['tables']) || ! is_array($data['tables'])) {
            throw new \RuntimeException('ساختار فایل پشتیبان معتبر نیست.');
        }

        $driver   = strtolower((string) DB::getDriverName());
        $isMySql  = in_array($driver, ['mysql', 'mariadb'], true);
        $isSqlite = $driver === 'sqlite';

        $summary = [
            'restored'       => 0,
            'skipped'        => 0,
            'tables'         => 0,
            'skipped_tables' => [],
            'unknown_tables' => [],
            'errors'         => [],
        ];

        if ($isMySql) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        } elseif ($isSqlite) {
            DB::statement('PRAGMA foreign_keys = OFF');
        }

        try {
            foreach ($data['tables'] as $name => $rows) {
                $table = strtolower((string) $name);

                // جدول سیستمی؟ کاری نداریم (نه بازیابی نه گزارش)
                if (in_array($table, self::EXCLUDED_TABLES, true)) {
                    continue;
                }

                // جدول در دیتابیس فعلی وجود ندارد (نسخهٔ قدیمی/جدیدتر کد)
                if (! Schema::hasTable($table)) {
                    $summary['unknown_tables'][] = $table;
                    continue;
                }

                // بدون ستون id نمی‌توان «موجود بودن» را تشخیص داد
                if (! Schema::hasColumn($table, 'id')) {
                    $summary['skipped_tables'][] = $table;
                    continue;
                }

                $rows = is_array($rows) ? $rows : [];
                if ($rows === []) {
                    continue;
                }

                // والد قبل از فرزند (id صعودی)
                usort($rows, fn ($a, $b) => ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0)));

                $inserted = 0;
                $skipped  = 0;

                try {
                    foreach ($rows as $row) {
                        if (! is_array($row) || ! array_key_exists('id', $row)) {
                            $skipped++;
                            continue;
                        }

                        // شرط اصلی: اگر از قبل وجود دارد، رد شو (بدون بازنویسی)
                        if (DB::table($table)->where('id', (int) $row['id'])->exists()) {
                            $skipped++;
                            continue;
                        }

                        try {
                            DB::table($table)->insert($row);
                            $inserted++;
                        } catch (Throwable) {
                            // کلید یکتای تکراری (slug/email/…) یا ناسازگاری ستون‌ها → رد شدن، نه توقف
                            $skipped++;
                            $summary['errors'][$table] = ($summary['errors'][$table] ?? 0) + 1;
                        }
                    }
                } catch (Throwable) {
                    // خطای سطح جدول (مثلاً جدولِ مقصدِ خراب با Tablespace is missing)
                    // → این جدول کلاً رد می‌شود اما بقیه جداول بازیابی می‌شوند.
                    $summary['skipped_tables'][] = $table;
                    continue;
                }

                $summary['restored'] += $inserted;
                $summary['skipped']  += $skipped;
                $summary['tables']++;
            }
        } finally {
            if ($isMySql) {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            } elseif ($isSqlite) {
                DB::statement('PRAGMA foreign_keys = ON');
            }
        }

        return $summary;
    }
}
