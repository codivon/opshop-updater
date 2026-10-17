<?php

namespace App\Console\Commands;

use App\Models\Backup;
use App\Services\BackupService;
use Illuminate\Console\Command;

/**
 * ادامه‌دادن عملیات در جریان پشتیبان‌گیری/بازیابی از خط فرمان.
 *
 * کاربرد در کران‌جاب هاست (هر دقیقه):
 *   * * * * * cd /home3/webtproi/repositories/opshop-updater && php artisan schedule:run >> /dev/null 2>&1
 *
 * (زمان‌بندی schedule:run در routes/console.php ثبت شده و این دستور را هر دقیقه صدا می‌زند.)
 * به این ترتیب حتی اگر مرورگر بسته شود، عملیات تا تکمیل ادامه پیدا می‌کند.
 */
class BackupTick extends Command
{
    protected $signature = 'backups:tick {--budget=45 : بودجهٔ زمانی هر عملیات (ثانیه)}';

    protected $description = 'پردازش/ادامهٔ عملیات پشتیبان‌گیری و بازیابی در جریان (برای کران‌جاب)';

    public function handle(): int
    {
        $records = Backup::query()
            ->active()
            ->orderBy('id')
            ->get();

        if ($records->isEmpty()) {
            $this->line('No pending backup/restore operations.');

            return self::SUCCESS;
        }

        $budget = max(5, (int) $this->option('budget'));

        foreach ($records as $record) {
            $this->line("Processing [{$record->id}] {$record->type} — {$record->name} ({$record->progress}%)");
            BackupService::runSlice($record, $budget);

            $record->refresh();
            $this->line("  → {$record->status} {$record->progress}%");
        }

        return self::SUCCESS;
    }
}
