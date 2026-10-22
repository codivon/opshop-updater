<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| زمان‌بندی پیامک اشتراک‌ها
|--------------------------------------------------------------------------
| روی هاست اشتراکی یک کران‌جاب روزانه تنظیم کنید که اجرا شود:
|   php artisan schedule:run
| (مثلاً: 0 9 * * * cd /path/to/project && php artisan schedule:run)
*/
Schedule::command('sms:check-subscriptions')->dailyAt('09:00');

/*
|--------------------------------------------------------------------------
| ادامهٔ عملیات پشتیبان‌گیری/بازیابی
|--------------------------------------------------------------------------
| عملیات پشتیبان‌گیری تکه‌ای اجرا می‌شود (ضد تایم‌اوت). با کران‌جاب زیر،
| حتی اگر مرورگر بسته شود عملیات تا تکمیل ادامه پیدا می‌کند:
|   * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
*/
Schedule::command('backups:tick')->everyMinute()->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| پاک‌سازی فایل‌های موقت آپلود تکه‌ای
|--------------------------------------------------------------------------
| فایل‌های .part نیمه‌کاره (آپلود رهاشده) بعد از یک روز خودکار حذف می‌شوند
| تا دیسک اشغال نماند.
*/
Schedule::call(function () {
    $dir = storage_path('app/chunk-uploads');

    if (! is_dir($dir)) {
        return;
    }

    $cutoff = now()->subDay()->getTimestamp();

    foreach (glob($dir . '/*.part') ?: [] as $file) {
        if (@filemtime($file) !== false && @filemtime($file) < $cutoff) {
            @unlink($file);
        }
    }
})->dailyAt('03:30')->name('cleanup-chunk-uploads');
