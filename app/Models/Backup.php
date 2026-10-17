<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * یک عملیات پشتیبان‌گیری یا بازیابی (پس‌زمینه‌ای و تکه‌ای).
 *
 * فایل نهایی backup در  storage/app/backups/{name}.zip  و
 * فایل‌های آپلودی برای بازیابی در  storage/app/backups/incoming/  نگهداری می‌شوند.
 */
class Backup extends Model
{
    public const TYPE_BACKUP  = 'backup';
    public const TYPE_RESTORE = 'restore';

    public const STATUS_PENDING   = 'pending';
    public const STATUS_RUNNING   = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED    = 'failed';

    protected $guarded = [];

    protected $casts = [
        'include_files' => 'boolean',
        'progress'      => 'integer',
        'size'          => 'integer',
        'cursor'        => 'array',
        'report'        => 'array',
    ];

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_RUNNING]);
    }

    public function isRunning(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_RUNNING], true);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isBackup(): bool
    {
        return $this->type === self::TYPE_BACKUP;
    }

    /** مسیر مطلق فایل ZIP (در صورت وجود) */
    public function filePath(): ?string
    {
        $path = $this->isBackup()
            ? storage_path('app/backups/' . $this->name . '.zip')
            : storage_path('app/' . (string) $this->disk_path);

        return is_file($path) ? $path : null;
    }

    /** برچسب وضعیت برای UI */
    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING   => 'در صف اجرا',
            self::STATUS_RUNNING   => 'در حال اجرا',
            self::STATUS_COMPLETED => 'تکمیل شد',
            self::STATUS_FAILED    => 'ناموفق',
            default                => $this->status,
        };
    }

    /** برچسب نوع برای UI */
    public function typeLabel(): string
    {
        return $this->isBackup() ? 'پشتیبان‌گیری' : 'بازیابی';
    }

    public function statusVariant(): string
    {
        return match ($this->status) {
            self::STATUS_COMPLETED => 'success',
            self::STATUS_RUNNING   => 'primary',
            self::STATUS_FAILED    => 'danger',
            default                => 'neutral',
        };
    }
}
