<?php

namespace App\Livewire\Tools;

use App\Livewire\Concerns\WithToasts;
use App\Models\Backup;
use App\Services\BackupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

/**
 * پشتیبان‌گیری و بازیابی کامل (دیتابیس + فایل‌ها) در پنل مدیریت:
 *  - ساخت ZIP شامل کل دیتابیس + تصاویر/فایل‌ها (پس‌زمینه‌ای و تکه‌ای، ضدتایم‌اوت)
 *  - مدیریت فایل‌های پشتیبان: مشاهده، دانلود، حذف
 *  - بازیابی از فایل آپلودی یا فایل‌های FTP‌شده در incoming — با گزارش کامل
 */
#[Layout('components.layouts.app')]
#[Title('پشتیبان‌گیری')]
class Backup extends Component
{
    use WithFileUploads, WithToasts;

    /** فایل ZIP آپلودشده برای بازیابی */
    public $backupFile;

    /** مشمول شدن فایل‌ها و تصاویر در پشتیبان */
    public bool $includeFiles = true;

    /** منبع در انتظار تأیید بازیابی: 'upload' یا نام فایل incoming */
    public ?string $pendingRestore = null;

    /** مورد در انتظار تأیید حذف: 'backup:{id}' یا 'incoming:{name}' */
    public ?string $pendingDelete = null;

    /** آخرین عملیات فعال که برایش اعلان پایان داده‌ایم (جلوگیری از تکرار toast) */
    public ?int $notifiedId = null;

    /** id آخرین عملیات در جریان — برای تشخیص پایان پس از poll */
    public ?int $lastActiveId = null;

    /** حداکثر حجم آپلود (۵۱۲ مگابایت) — برای فایل‌های بزرگ‌تر از FTP استفاده کنید */
    protected int $maxFileKb = 524288;

    protected function rules(): array
    {
        return [
            'backupFile' => ['nullable', 'file', 'extensions:zip', 'max:' . $this->maxFileKb],
        ];
    }

    protected $messages = [
        'backupFile.extensions' => 'فقط فایل ZIP پشتیبان معتبر است.',
        'backupFile.max'        => 'حجم فایل نباید بیشتر از ۵۱۲ مگابایت باشد؛ برای فایل حجیم‌تر، آن را از طریق FTP/فایل‌منیجر در پوشهٔ storage/app/backups/incoming آپلود کنید.',
    ];

    /* ================================================================
     *  داده‌های صفحه
     * ================================================================ */

    /** عملیات در جریان (اگر باشد نوار پیشرفت + poll نمایش داده می‌شود) */
    #[Computed]
    public function active(): ?Backup
    {
        return Backup::query()->active()->orderBy('id')->first();
    }

    /** فایل‌های پشتیبان ساخته‌شده + عملیات انجام‌شده */
    #[Computed]
    public function records()
    {
        return Backup::query()->latest('id')->limit(50)->get();
    }

    /** آخرین بازیابی تکمیل‌شده (برای نمایش گزارش کامل) */
    #[Computed]
    public function latestRestore(): ?Backup
    {
        return Backup::query()
            ->where('type', Backup::TYPE_RESTORE)
            ->where('status', Backup::STATUS_COMPLETED)
            ->whereNotNull('report')
            ->latest('id')
            ->first();
    }

    /** فایل‌های ZIP موجود در پوشهٔ incoming */
    #[Computed]
    public function incoming(): array
    {
        return BackupService::incomingFiles();
    }

    /** آمار دیتابیس فعلی — فقط جداول همین دیتابیس؛ جدول خراب صفحه را نمی‌بَرد */
    #[Computed]
    public function stats(): array
    {
        $tables   = BackupService::tableNames();
        $rows     = 0;
        $broken   = [];
        $perTable = [];

        foreach ($tables as $table) {
            try {
                $count = (int) DB::table($table)->count();
                $rows += $count;
                $perTable[$table] = $count;
            } catch (Throwable $e) {
                // جدول خراب/غیرقابل‌خواندن (مثل Tablespace is missing در لوکال)
                $broken[$table] = Str::limit($e->getMessage(), 140);
            }
        }

        $files = BackupService::filesStat();

        return [
            'tables'   => count($tables),
            'rows'     => $rows,
            'per_table'=> $perTable,
            'broken'   => $broken,
            'driver'   => (string) DB::getDriverName(),
            'files_count' => $files['count'],
            'files_size'  => $files['size'],
        ];
    }

    /* ================================================================
     *  تیک پس‌زمینه (poll)
     * ================================================================ */

    public function tick(): void
    {
        $active = $this->active;

        if ($active !== null) {
            $this->lastActiveId = $active->id;
            BackupService::runSlice($active);

            return;
        }

        // عملیات تمام شده — یک‌بار اعلان بده
        if ($this->lastActiveId !== null) {
            $record = Backup::find($this->lastActiveId);
            $this->lastActiveId = null;

            if ($record !== null && $record->id !== $this->notifiedId) {
                $this->notifiedId = $record->id;

                if ($record->status === Backup::STATUS_COMPLETED) {
                    $this->toast($record->isBackup()
                        ? 'نسخه پشتیبان «' . $record->name . '.zip» با موفقیت ساخته شد.'
                        : 'بازیابی تکمیل شد؛ گزارش کامل در همین صفحه نمایش داده شده است.', 'success');
                } elseif ($record->status === Backup::STATUS_FAILED) {
                    $this->toast('عملیات ناموفق بود: ' . ($record->error ?: 'خطای نامشخص'), 'error');
                }
            }
        }
    }

    /* ================================================================
     *  پشتیبان‌گیری
     * ================================================================ */

    public function createBackup(): void
    {
        try {
            $record = BackupService::startBackup($this->includeFiles);
        } catch (Throwable $e) {
            report($e);
            $this->toast('آغاز پشتیبان‌گیری ناموفق بود: ' . $e->getMessage(), 'error');

            return;
        }

        $this->toast('عملیات پشتیبان‌گیری آغاز شد؛ می‌توانید صفحه را باز نگه دارید یا ببندید.', 'success');
        BackupService::runSlice($record); // تکهٔ اول بلافاصله اجرا شود
    }

    /** دانلود فایل ZIP پشتیبان */
    public function download(int $id)
    {
        $record = Backup::query()->where('type', Backup::TYPE_BACKUP)->find($id);

        if ($record === null) {
            $this->toast('این نسخه پشتیبان یافت نشد.', 'error');

            return;
        }

        $path = $record->filePath();

        if ($record->status !== Backup::STATUS_COMPLETED || $path === null) {
            $this->toast('فایل این نسخه پشتیبان موجود نیست.', 'warning');

            return;
        }

        return response()->streamDownload(function () use ($path) {
            $fp = fopen($path, 'rb');

            if ($fp) {
                fpassthru($fp);
                fclose($fp);
            }
        }, $record->name . '.zip', [
            'Content-Type'        => 'application/zip',
            'Content-Disposition' => 'attachment; filename="' . $record->name . '.zip"',
            'Cache-Control'       => 'no-store, private',
        ]);
    }

    /* ================================================================
     *  بازیابی
     * ================================================================ */

    /** دکمهٔ «بازیابی اطلاعات» — منبع را مشخص و مودال تأیید را باز می‌کند */
    public function requestRestore(string $source): void
    {
        if ($source === 'upload' && ! $this->backupFile) {
            $this->toast('ابتدا فایل پشتیبان را انتخاب کنید یا از فهرست فایل‌های موجود یکی را برگزینید.', 'warning');

            return;
        }

        $this->pendingRestore = $source;
    }

    /** دکمهٔ «شروع بازیابی» داخل مودال تأیید */
    public function confirmRestore(): void
    {
        $source = $this->pendingRestore;
        $this->reset('pendingRestore');

        try {
            if ($source === 'upload') {
                $this->validateOnly('backupFile');

                if (! $this->backupFile) {
                    $this->toast('فایلی انتخاب نشده است.', 'warning');

                    return;
                }

                $original = (string) $this->backupFile->getClientOriginalName();
                $path     = BackupService::storeIncoming(
                    (string) $this->backupFile->getRealPath(),
                    $original
                );
                $record = BackupService::startRestore($path, $original);
                $this->reset('backupFile');
            } else {
                $name = basename((string) $source);
                $path = BackupService::incomingDir() . '/' . $name;

                if (! is_file($path)) {
                    $this->toast('فایل «' . $name . '» در پوشهٔ incoming یافت نشد.', 'error');

                    return;
                }

                $record = BackupService::startRestore($path, $name);
            }
        } catch (Throwable $e) {
            report($e);
            $this->toast('آغاز بازیابی ناموفق بود: ' . $e->getMessage(), 'error');

            return;
        }

        $this->toast('عملیات بازیابی آغاز شد؛ پیشرفت و گزارش کامل در همین صفحه نمایش داده می‌شود.', 'success');
        BackupService::runSlice($record);
    }

    /* ================================================================
     *  حذف فایل‌ها
     * ================================================================ */

    public function requestDelete(string $target): void
    {
        $this->pendingDelete = $target;
    }

    public function confirmDelete(): void
    {
        $target = (string) $this->pendingDelete;
        $this->reset('pendingDelete');

        [$kind, $value] = array_pad(explode(':', $target, 2), 2, null);

        try {
            if ($kind === 'backup') {
                $record = Backup::find((int) $value);

                if ($record === null) {
                    $this->toast('نسخه پشتیبان یافت نشد.', 'error');

                    return;
                }

                BackupService::deleteArchive($record);
                $record->delete();
                $this->toast('نسخه پشتیبان حذف شد.', 'success');
            } elseif ($kind === 'incoming') {
                BackupService::deleteIncoming((string) $value);
                $this->toast('فایل آپلودی حذف شد.', 'success');
            }
        } catch (Throwable $e) {
            report($e);
            $this->toast('حذف ناموفق بود: ' . $e->getMessage(), 'error');
        }
    }

    public function render()
    {
        return view('livewire.tools.backup');
    }
}
