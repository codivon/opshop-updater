<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\ViewErrorBag;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // تضمین وجود متغیر errors در «همه» ویوها:
        // برخی محیط‌ها (رندر Livewire خارج از middleware وب، رندر CLI و…)
        // ممکن است ShareErrorsFromSession اجرا نشود و Livewire پس از هر رندر
        // مقدار قبلی را حذف کند → خطای «Undefined variable errors».
        // این سهمِ پایه (کیس خالی) همیشه ست می‌شود؛ در درخواست‌های وب عادی،
        // middleware آن را با کیس واقعی بازنویسی می‌کند.
        View::share('errors', new ViewErrorBag);

        // Morph map — ستون‌های *_type در DB کوتاه و پایدار می‌مانند
        // (دیدگاه‌های چندریختی: محصول/پکیج).
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([
            \App\Models\Comment::TYPE_PRODUCT => \App\Models\Product::class,
            \App\Models\Comment::TYPE_PACKAGE => \App\Models\Package::class,
        ]);
    }
}
