<?php

namespace App\Services;

use App\Models\Backup;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;
use ZipArchive;

/**
 * پشتیبان‌گیری و بازیابی کامل — دیتابیس + فایل‌ها — در قالب ZIP.
 *
 * ساختار فایل (نسخه ۲):
 *   backup-YYYYMMDD-HHMMSS.zip
 *   ├── meta.json                 (اطلاعات نسخه پشتیبان + تعداد رکورد هر جدول)
 *   ├── database/{table}.jsonl    (هر خط = یک رکورد JSON — مناسب دیتابیس حجیم)
 *   └── files/…                   (محتوای storage/app/public — تصاویر و فایل‌ها)
 *
 * ضدتایم‌اوت (طبق درخواست کاربر):
 *   هر عملیات به‌صورت «تکه‌ای» اجرا می‌شود؛ هر تیک (Livewire poll یا کران‌جاب)
 *   فقط چند ثانیه کار می‌کند و نقطهٔ توقف در ستون cursor ذخیره می‌شود تا از
 *   همان‌جا ادامه یابد. بنابراین هرچقدر هم حجم زیاد شود، ارور تایم‌اوت گرفته
 *   نمی‌شود؛ حتی اگر مرورگر بسته شود، کران‌جاب (backups:tick) ادامه می‌دهد.
 *
 * بازیابی (قانون «اگر از قبل وجود داشت، رد بشه»):
 *   - هیچ رکورد/فایلی حذف یا بازنویسی نمی‌شود؛
 *   - رکوردها با id اصلی درج می‌شوند تا روابط سالم بمانند؛
 *   - رکورد/فایل تکراری رد می‌شود و گزارش دقیقش در پایان نمایش داده می‌شود؛
 *   - سازگار با فایل‌های نسخهٔ ۱ (JSON قدیمی — حتی با پیشوند دیتابیس در نام جدول).
 */
class BackupService
{
    public const FORMAT_VERSION = 2;

    /** بودجهٔ زمانی هر تیک (ثانیه) — کم نگه داشته می‌شود تا ارور تایم‌اوت گرفته نشود */
    public const SLICE_SECONDS = 14;

    public const CHUNK_ROWS  = 500;
    public const CHUNK_FILES = 200;

    /** جداول سیستمی/فریمورک + جدول خودِ عملیات پشتیبان */
    public const EXCLUDED_TABLES = [
        'migrations', 'sessions', 'cache', 'cache_locks',
        'jobs', 'job_batches', 'failed_jobs',
        'personal_access_tokens', 'password_reset_tokens',
        'sqlite_sequence', 'sqlite_stat1',
        'backups',
    ];

    /** ترتیب ترجیحی درج (والد قبل از فرزند) */
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

    protected static array $columnCache = [];

    /* ================================================================
     *  مسیرها
     * ================================================================ */

    public static function baseDir(): string
    {
        $dir = storage_path('app/backups');
        if (! is_dir($dir)) { @mkdir($dir, 0775, true); }

        return $dir;
    }

    public static function incomingDir(): string
    {
        $dir = storage_path('app/backups/incoming');
        if (! is_dir($dir)) { @mkdir($dir, 0775, true); }

        return $dir;
    }

    public static function tmpDir(int|string $key): string
    {
        $dir = storage_path('app/backups/tmp/' . $key);
        if (! is_dir($dir)) { @mkdir($dir, 0775, true); }

        return $dir;
    }

    public static function publicRoot(): string
    {
        return storage_path('app/public');
    }

    /* ================================================================
     *  فهرست جداول — فقط دیتابیسِ فعلی (رفع باگ جدول‌های دیتابیس‌های دیگر)
     * ================================================================ */

    /**
     * جداول دیتابیس فعلی (بدون پیشوند دیتابیس، بدون جداول سایر دیتابیس‌ها).
     *
     * باگ قبلی: Schema::getTableListing() در نسخه‌ای از لاراول، نام‌های
     * «با پیشوند دیتابیس» و حتی جداول سایر دیتابیس‌های قابل‌مشاهدهٔ کاربر
     * MySQL را برمی‌گرداند (hlumtllb_seoshop.* و webapp-v1.16.0.* در لوکال)؛
     * نتیجه: خروجی با نام پیشوندی ذخیره و در بازیابی هیچ‌چیز شناخته نمی‌شد.
     * اکنون از SHOW TABLES استفاده می‌شود که فقط دیتابیس جاری را فهرست می‌کند.
     */
    public static function tableNames(): array
    {
        $driver = strtolower((string) DB::connection()->getDriverName());
        $names  = [];

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            foreach (DB::connection()->select('SHOW FULL TABLES') as $row) {
                $values = array_values((array) $row);
                if (count($values) >= 2 && $values[1] !== 'BASE TABLE') {
                    continue; // VIEW ها پشتیبان داده‌ای ندارند
                }
                $names[] = (string) $values[0];
            }
        } elseif ($driver === 'sqlite') {
            foreach (DB::connection()->select(
                "select name from sqlite_master where type = 'table' and name not like 'sqlite_%'"
            ) as $row) {
                $names[] = (string) $row->name;
            }
        } else {
            $names = Schema::getTableListing();
        }

        $all = collect($names)
            ->map(fn ($name) => strtolower((string) $name))
            // اطمینان: اگر نامی به هر دلیلی پیشوند دیتابیس داشت، حذف شود
            ->map(fn ($name) => str_contains($name, '.') ? substr($name, strrpos($name, '.') + 1) : $name)
            ->reject(fn ($name) => $name === '' || in_array($name, self::EXCLUDED_TABLES, true))
            ->unique()
            ->values();

        $priority = collect(self::PRIORITY_ORDER)->filter(fn ($name) => $all->contains($name))->values();
        $rest     = $all->diff($priority->all())->sort()->values();

        return $priority->merge($rest)->all();
    }

    /** ستون‌های یک جدول (کش در طول درخواست) */
    public static function tableColumns(string $table): array
    {
        return static::$columnCache[$table] ??= Schema::getColumnListing($table);
    }

    /** مرتب‌سازی نام جداول بر اساس اولویت درج */
    public static function orderTables(array $tables): array
    {
        $all      = collect($tables)->map(fn ($t) => strtolower((string) $t))->unique()->values();
        $priority = collect(self::PRIORITY_ORDER)->filter(fn ($name) => $all->contains($name))->values();
        $rest     = $all->diff($priority->all())->sort()->values();

        return $priority->merge($rest)->all();
    }

    /** تعداد و حجم فایل‌های رسانه‌ای (با کش ۱۰ دقیقه‌ای) */
    public static function filesStat(): array
    {
        return Cache::remember('backup:files-stat', 600, function () {
            $root = self::publicRoot();
            if (! is_dir($root)) {
                return ['count' => 0, 'size' => 0];
            }

            $count = 0;
            $size  = 0;

            try {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
                );

                foreach ($iterator as $file) {
                    /** @var \SplFileInfo $file */
                    if (! $file->isFile()) {
                        continue;
                    }
                    if (str_contains($file->getPathname(), DIRECTORY_SEPARATOR . 'livewire-tmp' . DIRECTORY_SEPARATOR)) {
                        continue; // فایل موقت آپلود لایو‌وایر
                    }
                    $count++;
                    $size += $file->getSize();
                }
            } catch (Throwable) {
                //
            }

            return ['count' => $count, 'size' => $size];
        });
    }

    public static function filesStatFlush(): void
    {
        Cache::forget('backup:files-stat');
    }

    /* ================================================================
     *  موتور اجرای تکه‌ای
     * ================================================================ */

    /**
     * اجرای یک «تکه» از عملیات (فراخوانی از تیک Livewire یا کران‌جاب).
     * با قفل کش، از اجرای همزمان جلوگیری می‌شود.
     */
    public static function runSlice(Backup $record, int $seconds = self::SLICE_SECONDS): void
    {
        try {
            $lock = Cache::lock('backup-op-' . $record->id, 180);
        } catch (Throwable) {
            $lock = null;
        }

        if ($lock !== null && ! $lock->get()) {
            return; // تیک موازی در حال اجراست؛ این تیک رد می‌شود
        }

        try {
            $record->refresh();

            if (! $record->isRunning()) {
                return;
            }

            if ($record->status === Backup::STATUS_PENDING) {
                $record->update(['status' => Backup::STATUS_RUNNING]);
            }

            if (function_exists('set_time_limit')) { @set_time_limit(0); }
            if (function_exists('ignore_user_abort')) { @ignore_user_abort(true); }

            $record->isBackup()
                ? self::backupSlice($record, $seconds)
                : self::restoreSlice($record, $seconds);
        } catch (Throwable $e) {
            report($e);
            $record->refresh();
            $record->update([
                'status' => Backup::STATUS_FAILED,
                'phase'  => 'متوقف شد',
                'error'  => Str::limit($e->getMessage(), 900),
            ]);
        } finally {
            try { $lock?->release(); } catch (Throwable) { /* بی‌اهمیت */ }
        }
    }

    /* ================================================================
     *  پشتیبان‌گیری
     * ================================================================ */

    public static function startBackup(bool $includeFiles = true): Backup
    {
        $name = 'backup-' . now()->format('Ymd-His');
        $i    = 2;
        while (is_file(self::baseDir() . '/' . $name . '.zip')) {
            $name = 'backup-' . now()->format('Ymd-His') . '-' . $i++;
        }

        return Backup::create([
            'type'          => Backup::TYPE_BACKUP,
            'status'        => Backup::STATUS_PENDING,
            'name'          => $name,
            'include_files' => $includeFiles,
            'progress'      => 1,
            'phase'         => 'در صف اجرا…',
        ]);
    }

    protected static function backupSlice(Backup $rec, int $seconds): void
    {
        $deadline = microtime(true) + max(5, $seconds - 2);
        $cursor   = $rec->cursor ?? [];
        $tmpDir   = self::tmpDir($rec->id);
        $zipPath  = $cursor['zip'] ?? ($tmpDir . '/archive.zip');
        $cursor['zip'] = $zipPath;

        if (($cursor['stage'] ?? 'start') === 'start') {
            $tables    = self::tableNames();
            $tableRows = [];
            $rowsTotal = 0;

            foreach ($tables as $table) {
                try {
                    $count = (int) DB::table($table)->count();
                    $tableRows[$table] = $count;
                    $rowsTotal += $count;
                } catch (Throwable $e) {
                    // جدول غیرقابل‌خواندن (مثلاً Tablespace is missing) → رد می‌شود، نه شکست کل عملیات
                    $tableRows[$table] = -1;
                    $cursor['errors'][$table] = Str::limit($e->getMessage(), 250);
                }
            }

            $cursor['tables']      = $tables;
            $cursor['table_rows']  = $tableRows;
            $cursor['tables_done'] = 0;
            $cursor['rows_done']   = 0;
            $cursor['errors']      = $cursor['errors'] ?? [];
            $cursor['file_index']  = 0;
            $cursor['file_byte']   = 0;
            $cursor['files_done']  = 0;
            $cursor['files_errors'] = 0;
            $cursor['manifest']    = null;
            $cursor['files_per_slice'] = self::CHUNK_FILES;
            $cursor['stage']       = $tables === []
                ? ($rec->include_files ? 'files' : 'finalize')
                : 'tables';

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('ساخت فایل ZIP ممکن نیست — سطح دسترسی پوشه storage را بررسی کنید.');
            }
            $zip->close();

            $rec->update([
                'cursor'   => $cursor,
                'progress' => 2,
                'phase'    => 'آماده‌سازی ساختار…',
            ]);
        }

        if ($cursor['stage'] === 'tables') {
            $done = self::backupTables($rec, $cursor, $deadline, $tmpDir);
            if ($done) {
                $cursor['stage'] = $rec->include_files ? 'files' : 'finalize';
            }
            $rec->update(['cursor' => $cursor]);
        }

        if ($cursor['stage'] === 'files') {
            $done = self::backupFiles($rec, $cursor, $deadline);
            if ($done) {
                $cursor['stage'] = 'finalize';
            }
            $rec->update(['cursor' => $cursor]);
        }

        if ($cursor['stage'] === 'finalize') {
            self::backupFinalize($rec, $cursor, $tmpDir);
        }
    }

    /** @return bool آیا همه جداول تمام شد؟ */
    protected static function backupTables(Backup $rec, array &$cursor, float $deadline, string $tmpDir): bool
    {
        $tables      = $cursor['tables'];
        $tableRows   = $cursor['table_rows'];
        $totalTables = count($tables);

        while ($cursor['tables_done'] < $totalTables && microtime(true) < $deadline) {
            $table = $tables[$cursor['tables_done']];

            if (($tableRows[$table] ?? 0) === -1) {
                // در شمارش اولیه خطا گرفته شد؛ در errors ثبت شده → رد می‌شود
                $cursor['tables_done']++;
                continue;
            }

            try {
                self::dumpTable($rec, $table, $cursor, $deadline, $tmpDir);
            } catch (Throwable $e) {
                $cursor['errors'][$table] = Str::limit($e->getMessage(), 250);
            }

            $cursor['tables_done']++;
            unset($cursor['current']);

            $progress = 5 + (int) floor(65 * $cursor['tables_done'] / max(1, $totalTables));
            $rec->update([
                'cursor'   => $cursor,
                'progress' => $progress,
                'phase'    => "خروجی گرفتن از دیتابیس (" . fa_num($cursor['tables_done']) . ' از ' . fa_num($totalTables) . ' جدول)…',
            ]);
        }

        return $cursor['tables_done'] >= $totalTables;
    }

    /** ریختن یک جدول به فایل jsonl (تکه‌ای و قابل ادامه) */
    protected static function dumpTable(Backup $rec, string $table, array &$cursor, float $deadline, string $tmpDir): void
    {
        $hasId = in_array('id', self::tableColumns($table), true);
        $path  = $tmpDir . '/' . $table . '.jsonl';

        $cur = (isset($cursor['current']['table']) && $cursor['current']['table'] === $table)
            ? $cursor['current']
            : ['table' => $table, 'last_id' => 0, 'offset' => 0];

        $append = $hasId ? $cur['last_id'] > 0 : $cur['offset'] > 0;
        $fp = fopen($path, $append ? 'a' : 'w');
        if ($fp === false) {
            throw new \RuntimeException("نوشتن فایل موقت جدول {$table} ممکن نیست.");
        }

        while (microtime(true) < $deadline) {
            $query = DB::table($table);

            if ($hasId) {
                $query->where('id', '>', $cur['last_id'])->orderBy('id');
            } else {
                $query->offset($cur['offset']);
            }

            $rows = $query->limit(self::CHUNK_ROWS)->get();

            if ($rows->isEmpty()) {
                fclose($fp);
                self::zipAddFile($cursor['zip'], $path, 'database/' . $table . '.jsonl');
                return;
            }

            // نقطهٔ توقف قبل از نوشتن ذخیره می‌شود؛
            // در جداول دارای id اگر وسط قطع شود، رکورد تکراری هنگام بازیابی خودش رد می‌شود.
            $last = $rows->last();
            if ($hasId) {
                $cur['last_id'] = (int) $last->id;
            }
            $cur['offset'] += count($rows);
            $cursor['current']   = $cur;
            $cursor['rows_done'] = ($cursor['rows_done'] ?? 0) + count($rows);

            foreach ($rows as $row) {
                fwrite($fp, json_encode((array) $row, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
            }

            $rec->update(['cursor' => $cursor]);
        }

        fclose($fp);
    }

    /** @return bool آیا همه فایل‌ها اضافه شد؟ */
    protected static function backupFiles(Backup $rec, array &$cursor, float $deadline): bool
    {
        $manifestPath = $cursor['manifest'] ?? null;

        if (! $manifestPath || ! is_file($manifestPath)) {
            $manifestPath = self::tmpDir($rec->id) . '/manifest.txt';
            $cursor['manifest']   = $manifestPath;
            $cursor['files_total'] = self::buildManifest(self::publicRoot(), $manifestPath);
            $rec->update(['cursor' => $cursor]);
        }

        $total = (int) ($cursor['files_total'] ?? 0);
        if ($total === 0) {
            return true;
        }

        $fh = fopen($manifestPath, 'r');
        if ($fh === false) {
            throw new \RuntimeException('خواندن فهرست فایل‌ها ممکن نیست.');
        }

        if (($cursor['file_index'] ?? 0) > 0) {
            fseek($fh, (int) ($cursor['file_byte'] ?? 0));
        }

        $zipPath = $cursor['zip'];
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            fclose($fh);
            throw new \RuntimeException('بازکردن فایل ZIP در حال ساخت ناموفق بود.');
        }

        $perSlice = max(10, (int) ($cursor['files_per_slice'] ?? self::CHUNK_FILES));
        $index    = (int) ($cursor['file_index'] ?? 0);
        $byte     = (int) ($cursor['file_byte'] ?? 0);
        $added    = 0;
        $eof      = false;
        $started  = microtime(true);

        try {
            while ($added < $perSlice && microtime(true) < $deadline) {
                $line = fgets($fh);

                if ($line === false) {
                    $eof = true;
                    break;
                }

                $byte = ftell($fh);
                $rel  = trim($line);

                if ($rel === '') {
                    continue;
                }

                $index++;
                $abs = self::publicRoot() . '/' . $rel;

                if (! is_file($abs)) {
                    $cursor['files_errors'] = ($cursor['files_errors'] ?? 0) + 1;
                    continue;
                }

                // فایل‌های رسانه (تصویر/ویدیو) بدون فشرده‌سازی — سرعت چند برابر، حجم تقریباً یکسان
                $entryName = 'files/' . $rel;
                $zip->addFile($abs, $entryName);
                try { $zip->setCompressionName($entryName, ZipArchive::CM_STORE); } catch (Throwable) { /* نسخه‌های قدیمی */ }
                $cursor['files_done'] = ($cursor['files_done'] ?? 0) + 1;
                $added++;
            }
        } finally {
            $zip->close();
            fclose($fh);
        }

        $cursor['file_index'] = $index;
        $cursor['file_byte']  = $byte;

        $progress = 70 + (int) floor(27 * min(1, $cursor['files_done'] / $total));
        $rec->update([
            'cursor'   => $cursor,
            'progress' => $progress,
            'phase'    => "بایگانی فایل‌ها (" . fa_num($cursor['files_done']) . ' از ' . fa_num($total) . ')…',
        ]);

        if ($eof || $index >= $total) {
            return true;
        }

        // تنظیم خودکار اندازهٔ دسته بر اساس زمان واقعی
        $elapsed = microtime(true) - $started;
        if ($elapsed > self::SLICE_SECONDS) {
            $cursor['files_per_slice'] = max(20, intdiv($perSlice, 2));
        } elseif ($elapsed < self::SLICE_SECONDS / 3 && $perSlice < 1000) {
            $cursor['files_per_slice'] = min(1000, $perSlice * 2);
        }

        return false;
    }

    protected static function backupFinalize(Backup $rec, array &$cursor, string $tmpDir): void
    {
        $metaPath = $tmpDir . '/meta.json';
        $meta = [
            'format'       => self::FORMAT_VERSION,
            'app'          => config('app.name', 'opshop-updater'),
            'url'          => config('app.url'),
            'generated_at' => now()->toIso8601String(),
            'driver'       => DB::getDriverName(),
            'php'          => PHP_VERSION,
            'tables'       => collect($cursor['table_rows'] ?? [])
                ->map(fn ($count) => max(0, (int) $count))
                ->all(),
            'rows_total'   => (int) ($cursor['rows_done'] ?? 0),
            'files'        => (int) ($cursor['files_done'] ?? 0),
            'errors'       => $cursor['errors'] ?? [],
        ];
        file_put_contents($metaPath, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE));

        self::zipAddFile($cursor['zip'], $metaPath, 'meta.json');

        $final = self::baseDir() . '/' . $rec->name . '.zip';
        if (is_file($final)) { @unlink($final); }

        if (! @rename($cursor['zip'], $final)) {
            if (! @copy($cursor['zip'], $final)) {
                throw new \RuntimeException('انتقال فایل ZIP به مقصد نهایی ناموفق بود.');
            }
            @unlink($cursor['zip']);
        }

        self::deleteTree($tmpDir);
        self::filesStatFlush();

        $rec->update([
            'status'   => Backup::STATUS_COMPLETED,
            'progress' => 100,
            'phase'    => 'تکمیل شد',
            'size'     => is_file($final) ? (int) filesize($final) : 0,
            'disk_path'=> 'backups/' . $rec->name . '.zip',
            'cursor'   => null,
            'report'   => [
                'tables' => count($cursor['tables'] ?? []),
                'rows'   => (int) ($cursor['rows_done'] ?? 0),
                'files'  => (int) ($cursor['files_done'] ?? 0),
                'errors' => $cursor['errors'] ?? [],
            ],
        ]);
    }

    /* ================================================================
     *  بازیابی
     * ================================================================ */

    /** فایل آپلودی/FTP‌شده را به پوشهٔ incoming منتقل و رکورد عملیات می‌سازد */
    public static function storeIncoming(string $sourcePath, string $originalName): string
    {
        $safe = preg_replace('/[^A-Za-z0-9\-\._]/', '_', basename($originalName));
        $safe = trim((string) $safe);
        if ($safe === '' || $safe === '.' || $safe === '..') {
            $safe = 'backup.zip';
        }

        if (! str_ends_with(strtolower($safe), '.zip')) {
            $safe .= '.zip';
        }

        $target = self::incomingDir() . '/' . $safe;
        if (is_file($target)) {
            $target = self::incomingDir() . '/' . now()->format('His') . '-' . $safe;
        }

        if (! @copy($sourcePath, $target)) {
            throw new \RuntimeException('انتقال فایل به پوشهٔ پشتیبان‌ها ناموفق بود.');
        }

        return $target;
    }

    public static function startRestore(string $zipPath, ?string $originalName = null): Backup
    {
        if (! is_file($zipPath)) {
            throw new \RuntimeException('فایل پشتیبان یافت نشد.');
        }

        $base = basename($zipPath);

        return Backup::create([
            'type'              => Backup::TYPE_RESTORE,
            'status'            => Backup::STATUS_PENDING,
            'name'              => preg_replace('/\.zip$/i', '', $base),
            'original_filename' => $originalName ?: $base,
            'disk_path'         => 'backups/incoming/' . $base,
            'include_files'     => true,
            'progress'          => 1,
            'phase'             => 'در صف بررسی…',
        ]);
    }

    /** فهرست فایل‌های ZIP موجود در پوشهٔ incoming (برای آپلود حجیم از طریق FTP) */
    public static function incomingFiles(): array
    {
        self::incomingDir();

        $files = glob(self::incomingDir() . '/*.zip') ?: [];
        $out   = [];

        foreach ($files as $file) {
            $out[] = [
                'name'     => basename($file),
                'size'     => (int) @filesize($file),
                'modified' => (int) @filemtime($file),
            ];
        }

        usort($out, fn ($a, $b) => $b['modified'] <=> $a['modified']);

        return $out;
    }

    public static function deleteIncoming(string $name): void
    {
        $safe = basename((string) $name);
        $path = self::incomingDir() . '/' . $safe;

        if (is_file($path)) {
            @unlink($path);
        }
    }

    public static function deleteArchive(Backup $record): void
    {
        $path = $record->filePath();

        if ($path && is_file($path)) {
            @unlink($path);
        }
    }

    protected static function restoreSlice(Backup $rec, int $seconds): void
    {
        $deadline = microtime(true) + max(5, $seconds - 2);
        $cursor   = $rec->cursor ?? [];
        $zipPath  = (string) $rec->filePath();

        if ($zipPath === '') {
            throw new \RuntimeException('فایل ZIP پشتیبان یافت نشد: ' . $rec->disk_path);
        }

        if (($cursor['stage'] ?? 'start') === 'start') {
            self::restoreInspect($rec, $cursor, $zipPath);
        }

        if (($cursor['stage'] ?? '') === 'tables') {
            $done = self::restoreTables($rec, $cursor, $deadline, $zipPath);
            if ($done) {
                $cursor['stage'] = ($cursor['files_total'] ?? 0) > 0 ? 'files' : 'finalize';
            }
            $rec->update(['cursor' => $cursor]);
        }

        if (($cursor['stage'] ?? '') === 'files') {
            $done = self::restoreFiles($rec, $cursor, $deadline, $zipPath);
            if ($done) {
                $cursor['stage'] = 'finalize';
            }
            $rec->update(['cursor' => $cursor]);
        }

        if (($cursor['stage'] ?? '') === 'finalize') {
            self::restoreFinalize($rec, $cursor);
        }
    }

    /** تشخیص ساختار فایل (نسخه ۲ یا ۱) و ساخت برنامهٔ بازیابی */
    protected static function restoreInspect(Backup $rec, array &$cursor, string $zipPath): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new \RuntimeException('افزونهٔ PHP Zip روی سرور فعال نیست؛ برای فایل‌های ZIP لازم است.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('فایل ZIP قابل بازکردن نیست (خراب یا ناقص).');
        }

        try {
            $metaIndex = $zip->locateName('meta.json');
            $v1Index   = $zip->locateName('database.json');

            $cursor['table_index']     = 0;
            $cursor['table_byte']      = 0;
            $cursor['zip_entry_index'] = 0;
            $cursor['files_total']     = 0;
            $cursor['stats'] = [
                'tables' => [], 'skipped_tables' => [], 'errors' => [],
                'restored_files' => 0, 'skipped_files' => 0,
            ];

            if ($metaIndex !== false) {
                $meta = json_decode((string) $zip->getFromIndex($metaIndex), true);

                if (! is_array($meta)) {
                    throw new \RuntimeException('meta.json داخل فایل پشتیبان قابل خواندن نیست.');
                }

                $cursor['format'] = self::FORMAT_VERSION;
                $cursor['meta']   = [
                    'format'       => $meta['format'] ?? null,
                    'app'          => $meta['app'] ?? null,
                    'generated_at' => $meta['generated_at'] ?? null,
                ];

                $tables     = [];
                $filesTotal = 0;

                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entry = (string) $zip->getNameIndex($i);

                    if (preg_match('#^database/(.+)\.jsonl$#i', $entry, $m) === 1) {
                        $tables[] = $m[1];
                    } elseif (str_starts_with($entry, 'files/') && ! str_ends_with($entry, '/')) {
                        $filesTotal++;
                    }
                }

                $cursor['plan_tables'] = self::orderTables($tables);
                $cursor['files_total'] = $filesTotal;
                $cursor['stage']       = 'tables';
            } elseif ($v1Index !== false) {
                $cursor['format'] = 1;
                self::convertV1($rec, $cursor, (string) $zip->getFromIndex($v1Index));
            } else {
                throw new \RuntimeException('این فایل پشتیبان معتبر این سیستم نیست (meta.json یا database.json یافت نشد).');
            }
        } finally {
            $zip->close();
        }

        $rec->update([
            'cursor'   => $cursor,
            'progress' => 3,
            'phase'    => 'بررسی ساختار فایل پشتیبان…',
        ]);
    }

    /** تبدیل فرمت قدیمی (نسخه ۱ — JSON تکی) به jsonl داخل پوشهٔ tmp */
    protected static function convertV1(Backup $rec, array &$cursor, string $json): void
    {
        if (function_exists('ini_set')) { @ini_set('memory_limit', '1024M'); }
        if (function_exists('set_time_limit')) { @set_time_limit(0); }

        $data = json_decode($json, true);

        if (! is_array($data) || ! isset($data['tables']) || ! is_array($data['tables'])) {
            throw new \RuntimeException('ساختار فایل پشتیبان قدیمی قابل خواندن نیست.');
        }

        $importDir = self::tmpDir($rec->id) . '/import';
        if (! is_dir($importDir)) { @mkdir($importDir, 0775, true); }

        $tables = [];

        foreach ($data['tables'] as $name => $rows) {
            $table = strtolower((string) $name);

            // باگ نسخهٔ قبلی: نام جداول با پیشوند دیتابیس ذخیره شده بود (db.table)
            if (str_contains($table, '.')) {
                $table = substr($table, strrpos($table, '.') + 1);
            }

            if ($table === '' || in_array($table, self::EXCLUDED_TABLES, true)) {
                continue;
            }

            $fp = fopen($importDir . '/' . $table . '.jsonl', 'w');
            if ($fp === false) {
                continue;
            }

            foreach ((array) $rows as $row) {
                if (is_array($row)) {
                    fwrite($fp, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
                }
            }
            fclose($fp);

            $tables[] = $table;
        }

        $cursor['import_dir']  = $importDir;
        $cursor['plan_tables'] = self::orderTables(array_unique($tables));
        $cursor['files_total'] = 0;
        $cursor['stage']       = 'tables';

        $rec->update([
            'cursor' => $cursor,
            'phase'  => 'تبدیل فرمت قدیمی به ساختار جدید…',
        ]);
    }

    /** @return bool آیا همه جداول بازیابی شد؟ */
    protected static function restoreTables(Backup $rec, array &$cursor, float $deadline, string $zipPath): bool
    {
        $plan  = $cursor['plan_tables'];
        $total = count($plan);
        $driver = strtolower((string) DB::getDriverName());

        try {
            // درج آزاد (والد قبل از فرزند) — در پایان همان سشن برقرار می‌شود
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
            } elseif ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = OFF');
            }

            while ($cursor['table_index'] < $total && microtime(true) < $deadline) {
                $table = $plan[$cursor['table_index']];

                try {
                    self::restoreTable($rec, $cursor, $table, $deadline, $zipPath);
                } catch (Throwable $e) {
                    $cursor['stats']['skipped_tables'][$table] = Str::limit($e->getMessage(), 200);
                }

                $cursor['table_index']++;
                $cursor['table_byte'] = 0;
                unset($cursor['current_table']);

                $progress = 5 + (int) floor(70 * $cursor['table_index'] / max(1, $total));
                $rec->update([
                    'cursor'   => $cursor,
                    'progress' => $progress,
                    'phase'    => "بازیابی دیتابیس (" . fa_num($cursor['table_index']) . ' از ' . fa_num($total) . " جدول) — {$table}",
                ]);
            }
        } finally {
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                try { DB::statement('SET FOREIGN_KEY_CHECKS=1'); } catch (Throwable) { /* ignore */ }
            } elseif ($driver === 'sqlite') {
                try { DB::statement('PRAGMA foreign_keys = ON'); } catch (Throwable) { /* ignore */ }
            }
        }

        return $cursor['table_index'] >= $total;
    }

    /** بازیابی یک جدول به‌صورت جریانی (خط به خط، حافظهٔ ثابت) */
    protected static function restoreTable(Backup $rec, array &$cursor, string $table, float $deadline, string $zipPath): void
    {
        if (! Schema::hasTable($table)) {
            $cursor['stats']['skipped_tables'][$table] = 'در دیتابیس فعلی وجود ندارد (نسخهٔ کد متفاوت است)';
            return;
        }

        $columns = array_flip(self::tableColumns($table));

        if ($columns === []) {
            $cursor['stats']['skipped_tables'][$table] = 'جدول بدون ستون است';
            return;
        }

        $hasId = isset($columns['id']);
        $isV1  = ($cursor['format'] ?? 2) === 1;

        $zip = null;
        $fp  = null;

        if ($isV1) {
            $source = $cursor['import_dir'] . '/' . $table . '.jsonl';
            if (! is_file($source)) {
                return; // جدول در پشتیبان خالی بوده
            }
            $fp = fopen($source, 'r');
        } else {
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new \RuntimeException('بازکردن ZIP ناموفق بود.');
            }

            $entry = 'database/' . $table . '.jsonl';
            if ($zip->locateName($entry) === false) {
                $zip->close();
                return; // جدول در پشتیبان خالی بوده
            }

            $fp = $zip->getStream($entry);
        }

        if ($fp === false) {
            $zip?->close();
            throw new \RuntimeException("خواندن داده‌های جدول {$table} ناموفق بود.");
        }

        if (($cursor['table_byte'] ?? 0) > 0) {
            fseek($fp, (int) $cursor['table_byte']);
        }

        $tRestored = 0;
        $tSkipped  = 0;
        $tFailed   = 0;

        try {
            while (microtime(true) < $deadline) {
                // خواندن یک دسته خط
                $rows = [];

                while (count($rows) < self::CHUNK_ROWS) {
                    $line = fgets($fp);

                    if ($line === false) {
                        break;
                    }

                    $line = trim($line);

                    if ($line === '') {
                        continue;
                    }

                    $row = json_decode($line, true);

                    if (! is_array($row)) {
                        $tFailed++;
                        continue;
                    }

                    $row = array_intersect_key($row, $columns); // فقط ستون‌های موجود در دیتابیس فعلی

                    if ($row === [] || ($hasId && ! isset($row['id']))) {
                        $tFailed++;
                        continue;
                    }

                    $rows[] = $row;
                }

                $cursor['table_byte'] = ftell($fp);

                if ($rows === []) {
                    break;
                }

                // قانون کاربر: اگر رکورد از قبل وجود داشت، رد بشه
                if ($hasId) {
                    $ids      = array_map(fn ($r) => (int) $r['id'], $rows);
                    $existing = DB::table($table)->whereIn('id', $ids)->pluck('id')->all();
                    $existing = array_flip(array_map(fn ($v) => (int) $v, $existing));

                    $newRows = [];
                    foreach ($rows as $row) {
                        if (isset($existing[(int) $row['id']])) {
                            $tSkipped++;
                        } else {
                            $newRows[] = $row;
                        }
                    }
                } else {
                    $newRows = $rows;
                }

                if ($newRows !== []) {
                    try {
                        DB::table($table)->insert($newRows);
                        $tRestored += count($newRows);
                    } catch (Throwable) {
                        // خطای دسته‌ای → رکورد به رکورد؛ تکراری‌ها رد و بقیه درج می‌شوند
                        foreach ($newRows as $row) {
                            try {
                                DB::table($table)->insert($row);
                                $tRestored++;
                            } catch (Throwable $e) {
                                $message = $e->getMessage();

                                if (str_contains($message, 'Duplicate entry')
                                    || str_contains($message, 'UNIQUE constraint failed')
                                    || str_contains($message, 'duplicate key')) {
                                    $tSkipped++;
                                } else {
                                    $tFailed++;
                                    if (count($cursor['stats']['errors'][$table] ?? []) < 5) {
                                        $cursor['stats']['errors'][$table][] = Str::limit($message, 160);
                                    }
                                }
                            }
                        }
                    }
                }

                $cursor['stats']['tables'][$table] = [
                    'restored' => $tRestored,
                    'skipped'  => $tSkipped,
                    'failed'   => $tFailed,
                ];

                $rec->update(['cursor' => $cursor]);
            }

            if ($tFailed === 0 && $tRestored === 0 && $tSkipped === 0) {
                return;
            }

            $cursor['stats']['tables'][$table] = [
                'restored' => $tRestored,
                'skipped'  => $tSkipped,
                'failed'   => $tFailed,
            ];
        } finally {
            fclose($fp);
            $zip?->close();
        }
    }

    /** @return bool آیا همه فایل‌ها بازیابی شد؟ */
    protected static function restoreFiles(Backup $rec, array &$cursor, float $deadline, string $zipPath): bool
    {
        $total = (int) ($cursor['files_total'] ?? 0);

        if ($total === 0) {
            return true;
        }

        $root = self::publicRoot();
        $zip  = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('بازکردن ZIP ناموفق بود.');
        }

        $i   = (int) ($cursor['zip_entry_index'] ?? 0);
        $num = $zip->numFiles;

        try {
            while ($i < $num && microtime(true) < $deadline) {
                $entry = (string) $zip->getNameIndex($i);
                $i++;

                if ($entry === '' || str_ends_with($entry, '/') || ! str_starts_with($entry, 'files/')) {
                    continue;
                }

                $rel = substr($entry, 6);

                if ($rel === '' || str_contains($rel, '..') || str_starts_with($rel, '/')) {
                    continue; // path traversal
                }

                $target = $root . '/' . $rel;
                $dir    = dirname($target);

                // محافظت: مقصد باید داخل public root بماند
                $realDir = realpath($dir) ?: '';
                $realRoot = realpath($root) ?: '';
                if ($realDir === '' || $realRoot === '' || ! str_starts_with($realDir . DIRECTORY_SEPARATOR, $realRoot . DIRECTORY_SEPARATOR)) {
                    continue;
                }

                if (is_file($target)) {
                    $cursor['stats']['skipped_files']++;
                    continue; // قانون: اگر از قبل وجود داشت، رد بشه
                }

                if (! is_dir($dir)) { @mkdir($dir, 0775, true); }

                $in = $zip->getStream($entry);

                if ($in !== false) {
                    $out = @fopen($target, 'w');

                    if ($out !== false) {
                        stream_copy_to_stream($in, $out);
                        fclose($out);
                        $cursor['stats']['restored_files']++;
                    } else {
                        $cursor['stats']['skipped_files']++;
                    }

                    fclose($in);
                } else {
                    $cursor['stats']['skipped_files']++;
                }
            }
        } finally {
            $zip->close();
        }

        $cursor['zip_entry_index'] = $i;

        $progress = 75 + (int) floor(22 * min(1, $i / max(1, $num)));
        $rec->update([
            'cursor'   => $cursor,
            'progress' => $progress,
            'phase'    => "بازیابی فایل‌ها (" . fa_num($cursor['stats']['restored_files']) . ' از ' . fa_num($total) . ')…',
        ]);

        return $i >= $num;
    }

    protected static function restoreFinalize(Backup $rec, array &$cursor): void
    {
        $stats = $cursor['stats'] ?? [];

        $tables = $stats['tables'] ?? [];

        self::filesStatFlush();

        $rec->update([
            'status'   => Backup::STATUS_COMPLETED,
            'progress' => 100,
            'phase'    => 'بازیابی تکمیل شد',
            'cursor'   => null,
            'report'   => [
                'summary' => [
                    'restored_rows'  => (int) array_sum(array_column($tables, 'restored')),
                    'skipped_rows'   => (int) array_sum(array_column($tables, 'skipped')),
                    'failed_rows'    => (int) array_sum(array_column($tables, 'failed')),
                    'tables_ok'      => count($tables),
                    'restored_files' => (int) ($stats['restored_files'] ?? 0),
                    'skipped_files'  => (int) ($stats['skipped_files'] ?? 0),
                    'format'         => $cursor['format'] ?? null,
                ],
                'tables'         => $tables,
                'skipped_tables' => $stats['skipped_tables'] ?? [],
                'errors'         => $stats['errors'] ?? [],
            ],
        ]);
    }

    /* ================================================================
     *  ابزارها
     * ================================================================ */

    /** افزودن یک فایل به ZIP موجود (باز/بسته کردن درجا) */
    protected static function zipAddFile(string $zipPath, string $filePath, string $entryName): void
    {
        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('بازکردن فایل ZIP ناموفق بود.');
        }

        try {
            $zip->addFile($filePath, $entryName);
        } finally {
            $zip->close();
        }
    }

    /** ساخت manifest فایل‌های رسانه‌ای (مسیر نسبی زیر public root) */
    protected static function buildManifest(string $root, string $targetPath): int
    {
        $count = 0;
        $fp = fopen($targetPath, 'w');

        if ($fp === false) {
            throw new \RuntimeException('ساخت فهرست فایل‌ها ممکن نیست.');
        }

        try {
            if (is_dir($root)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($iterator as $file) {
                    /** @var \SplFileInfo $file */
                    if (! $file->isFile()) {
                        continue;
                    }

                    $path = $file->getPathname();

                    if (str_contains($path, DIRECTORY_SEPARATOR . 'livewire-tmp' . DIRECTORY_SEPARATOR)) {
                        continue;
                    }

                    $rel = str_replace('\\', '/', ltrim(substr($path, strlen($root)), '/\\'));
                    fwrite($fp, $rel . "\n");
                    $count++;
                }
            }
        } finally {
            fclose($fp);
        }

        return $count;
    }

    /** حذف بازگشتی یک پوشه */
    public static function deleteTree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($dir);
    }
}
