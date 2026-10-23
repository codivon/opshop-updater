<?php

namespace App\Rules;

use App\Services\CaptchaService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * اعتبارسنج کپچای خودمیزبان (سؤال/پاسخ در سشن) — برای فرم‌های کلاسیک HTTP
 * مثل ورود/ثبت‌نام مدیر. در فرم‌های Livewire بررسی به‌صورت دستی انجام می‌شود.
 */
class CaptchaRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! CaptchaService::verify($value)) {
            $fail('پاسخ سؤال امنیتی نادرست است؛ سؤال جدید آماده شد.');
        }
    }
}
