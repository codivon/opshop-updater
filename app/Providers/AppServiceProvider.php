<?php

namespace App\Providers;

use App\Models\Comment;
use App\Models\Package;
use App\Models\Product;
use App\Models\Template;
use Illuminate\Cache\RateLimiter;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\ViewErrorBag;
use Shetabit\Multipay\Constants\IranCurrency;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // RateLimiter (throttle مسیرها + تلاش‌های ورود + سقف کامنت) روی استور «file»
        // — مستقل از جدول cache دیتابیس؛ روی هاست اشتراکی همیشه در دسترس است.
        $this->app->singleton(RateLimiter::class, function ($app) {
            return new RateLimiter($app['cache']->store('file'));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // shetabit/multipay v3: درایورهای بانکی مقدار currency را به‌صورت enum IranCurrency
        // می‌خوانند (settings->currency->ratio()). در فایل config رشتهٔ R/T نگه می‌داریم چون
        // «config:cache» با var_export نمی‌تواند enum را serialize کند — اینجا در زمان اجرا تبدیل می‌شود.
        foreach (config('payment.drivers', []) as $driverKey => $driverConfig) {
            $currency = is_array($driverConfig) ? ($driverConfig['currency'] ?? null) : null;

            if (is_string($currency)) {
                config([
                    "payment.drivers.{$driverKey}.currency" => strtoupper($currency) === 'R'
                        ? IranCurrency::RIAL
                        : IranCurrency::TOMAN,
                ]);
            }
        }

        // تضمین وجود متغیر errors در «همه» ویوها:
        // برخی محیط‌ها (رندر Livewire خارج از middleware وب، رندر CLI و…)
        // ممکن است ShareErrorsFromSession اجرا نشود و Livewire پس از هر رندر
        // مقدار قبلی را حذف کند → خطای «Undefined variable errors».
        // این سهمِ پایه (کیس خالی) همیشه ست می‌شود؛ در درخواست‌های وب عادی،
        // middleware آن را با کیس واقعی بازنویسی می‌کند.
        View::share('errors', new ViewErrorBag);

        // Morph map — ستون‌های *_type در DB کوتاه و پایدار می‌مانند
        // (دیدگاه‌های چندریختی: محصول/پکیج/قالب).
        Relation::morphMap([
            Comment::TYPE_PRODUCT => Product::class,
            Comment::TYPE_PACKAGE => Package::class,
            Comment::TYPE_TEMPLATE => Template::class,
        ]);

        // اتصال تنظیمات SMTP پنل به پیکربندی ایمیل در زمان اجرا:
        // فقط وقتی مدیر «استفاده از تنظیمات ایمیل پنل» را روشن کرده و هاست پر باشد.
        // (پیش‌نیاز ارسال ایمیل‌های سیستمی مثل کد ورود دومرحله‌ای روی هاست اشتراکی)
        try {
            if (filter_var(setting('mail_use_settings', '0'), FILTER_VALIDATE_BOOLEAN)
                && trim((string) setting('mail_host', '')) !== '') {
                config([
                    'mail.default' => 'smtp',
                    'mail.mailers.smtp.host' => setting('mail_host'),
                    'mail.mailers.smtp.port' => (int) setting('mail_port', '587'),
                    'mail.mailers.smtp.username' => setting('mail_username'),
                    'mail.mailers.smtp.password' => setting('mail_password'),
                    'mail.from.address' => setting('mail_from_address', config('mail.from.address')),
                    'mail.from.name' => setting('mail_from_name', config('mail.from.name')),
                ]);
            }
        } catch (\Throwable) {
            // جدول settings آماده نیست (نصب تازه/تست) → پیکربندی پیش‌فرض بماند
        }
    }
}
