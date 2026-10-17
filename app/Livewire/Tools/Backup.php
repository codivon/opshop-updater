<?php

namespace App\Livewire\Tools;

use App\Livewire\Concerns\WithToasts;
use App\Services\BackupService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

/**
 * پشتیبان‌گیری و بازیابی کامل دیتابیس از پنل مدیریت:
 *  - دریافت نسخه پشتیبان (فایل JSON قابل دانلود)
 *  - بازیابی فایل پشتیبان: رکوردهای موجود رد می‌شوند، فقط رکوردهای جدید درج می‌شوند
 */
#[Layout('components.layouts.app')]
#[Title('پشتیبان‌گیری')]
class Backup extends Component
{
    use WithFileUploads, WithToasts;

    /** فایل پشتیبان آپلودشده برای بازیابی */
    public $backupFile;

    /** مودال تأیید بازیابی */
    public bool $confirmRestore = false;

    /** حداکثر حجم فایل پشتیبان (۵۰ مگابایت) */
    protected int $maxFileKb = 51200;

    protected function rules(): array
    {
        return [
            'backupFile' => ['required', 'file', 'extensions:json', 'max:' . $this->maxFileKb],
        ];
    }

    protected $messages = [
        'backupFile.required'   => 'انتخاب فایل پشتیبان الزامی است.',
        'backupFile.extensions' => 'فقط فایل JSON پشتیبان معتبر است.',
        'backupFile.max'        => 'حجم فایل نباید بیشتر از ۵۰ مگابایت باشد.',
    ];

    /* ================================================================
     *  آمار جاری (برای نمایش در صفحه)
     * ================================================================ */

    #[Computed]
    public function stats(): array
    {
        $tables = BackupService::tableNames();
        $rows   = 0;
        $broken = [];

        foreach ($tables as $table) {
            try {
                $rows += (int) DB::table($table)->count();
            } catch (Throwable $e) {
                // جدول خراب/غیرقابل‌خواندن (مثلاً tablespace گم‌شده در لوکال)
                // → نباید کل صفحه را ببَرد؛ فقط گزارش می‌شود.
                $broken[$table] = $e->getMessage();
            }
        }

        return [
            'tables' => count($tables),
            'rows'   => $rows,
            'driver' => (string) DB::getDriverName(),
            'broken' => $broken,
        ];
    }

    #[Computed]
    public function tableNames(): array
    {
        return BackupService::tableNames();
    }

    /* ================================================================
     *  Actions
     * ================================================================ */

    /** دانلود نسخه پشتیبان کامل (JSON) */
    public function download()
    {
        try {
            $payload = BackupService::export();
            $json    = (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            report($e);
            $this->toast('ایجاد نسخه پشتیبان ناموفق بود: ' . $e->getMessage(), 'error');

            return null;
        }

        $errors = $payload['meta']['errors'] ?? [];

        if ($errors !== []) {
            $this->toast(
                'بک‌آپ ساخته شد، اما ' . fa_num(count($errors)) . ' جدول غیرقابل‌خواندن رد شد: ' . implode('، ', array_keys($errors)),
                'warning'
            );
        } else {
            $this->toast('نسخه پشتیبان ساخته شد و دانلود آغاز شد.');
        }

        return response()->streamDownload(
            fn () => print $json,
            BackupService::fileName(),
            [
                'Content-Type'        => 'application/json',
                'Content-Disposition' => 'attachment; filename="' . BackupService::fileName() . '"',
                'Cache-Control'       => 'no-store, private',
            ]
        );
    }

    /** بازیابی فایل پشتیبان (فراخوانی از مودال تأیید) */
    public function restore(): void
    {
        $this->validate();

        try {
            $json    = (string) file_get_contents((string) $this->backupFile->getRealPath());
            $summary = BackupService::restoreFromJson($json);
        } catch (Throwable $e) {
            report($e);
            $this->toast('بازیابی ناموفق بود: ' . $e->getMessage(), 'error');

            return;
        } finally {
            $this->reset('backupFile', 'confirmRestore');
        }

        if ($summary['restored'] === 0 && $summary['skipped'] > 0) {
            $this->toast('چیز جدیدی برای بازیابی نبود — ' . fa_num($summary['skipped']) . ' رکورد از قبل موجود بود و رد شد.', 'warning');

            return;
        }

        $message = 'بازیابی کامل شد: ' . fa_num($summary['restored']) . ' رکورد افزوده شد، '
            . fa_num($summary['skipped']) . ' رکورد از قبل موجود و رد شد.';

        if ($summary['unknown_tables'] !== []) {
            $message .= ' (جداول ناشناخته: ' . fa_num(count($summary['unknown_tables'])) . ')';
        }

        $this->toast($message, 'success');
    }

    public function render()
    {
        return view('livewire.tools.backup');
    }
}
