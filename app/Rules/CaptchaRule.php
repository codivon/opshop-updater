<?php

namespace App\Rules;

use App\Services\CaptchaService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * اعتبارسنج کپچا برای فرم‌های کلاسیک HTTP (ورود/ثبت‌نام مدیر).
 * نقطهٔ فرم را می‌پذیرد تا درایور درست (ریاضی/حرف‌عددی/ابری) از پنل خوانده شود:
 *
 *     $rules['captcha'] = [new CaptchaRule('admin_login')];
 *
 * در فرم‌های Livewire بررسی به‌صورت دستی با CaptchaService::verify($input, $spot) انجام می‌شود.
 */
class CaptchaRule implements ValidationRule
{
    public function __construct(
        protected string $spot = 'generic',
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! CaptchaService::verify($value, $this->spot)) {
            $fail('پاسخ سؤال امنیتی نادرست است؛ سؤال جدید آماده شد.');
        }
    }
}
