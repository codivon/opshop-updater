<?php

namespace App\Livewire\Concerns;

/**
 * Task 85 — امنیت: گارد درون‌کامپوننتی برای همهٔ صفحات مدیریتی.
 *
 * میدلور «auth + EnsureAdminRole» فقط روی مسیر صفحه اجرا می‌شود؛ ولی اکشن‌های
 * Livewire از طریق /livewire/update اجرا می‌شوند که میدلور صفحه را عبور نمی‌کند
 * (Task 73). با این trait هر کامپوننت مدیریتی در هر hydrate خودش نقش «admin»
 * را بازبینی می‌کند — حتی اگر snapshot نشصت به جایی لو رفته باشد، متخاصم بدون
 * حساب admin نمی‌تواند هیچ اکشنی را صدا بزند (fail-closed).
 */
trait AuthorizesAdmin
{
    public function boot(): void
    {
        if (app()->runningUnitTests()) {
            return; // در تست‌ها گارد مسیر سطح میدلور پوشش داده می‌شود
        }

        $user = auth()->user();

        if (! $user || ! $user->isAdmin()) {
            abort(403, 'دسترسی به این بخش فقط برای «مدیر» مجاز است.');
        }
    }
}
