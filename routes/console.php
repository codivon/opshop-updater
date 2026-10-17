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
