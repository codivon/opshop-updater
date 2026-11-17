<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Task 85 — امنیت (CRITICAL C3/C4): توکن محرمانهٔ هر خرید.
 *
 * صفحات نتیجهٔ پرداخت/بازگشت، لایسنس و لینک دانلود نشان می‌دهند؛ هر خرید
 * یک توکن تصادفی ۶۴ نویسه‌ای می‌گیرد و صفحه فقط با همان توکن باز می‌شود —
 * شنیدن شناسهٔ عددی خرید برای متخاصم دیگر هیچ فایده‌ای ندارد.
 */
trait HasPurchaseAccessToken
{
    public static function bootHasPurchaseAccessToken(): void
    {
        static::creating(function ($model) {
            if (blank($model->access_token)) {
                $model->access_token = Str::random(64);
            }
        });
    }

    /** آیا توکن ارائه‌شده با توکن محرمانهٔ این خرید مطابقت دارد؟ */
    public function hasValidAccessToken(?string $token): bool
    {
        return (string) $this->access_token !== ''
            && (string) $token !== ''
            && hash_equals((string) $this->access_token, (string) $token);
    }
}
