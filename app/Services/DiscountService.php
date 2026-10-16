<?php

namespace App\Services;

use App\Models\DiscountCode;

/**
 * راستی‌آزمایی و اعمال کد تخفیف روی خریدهای فروشگاه.
 *
 * قرارداد خروجی resolve():
 *   [
 *     'ok'       => bool,          // آیا کد معتبر و قابل اعمال است؟
 *     'message'  => ?string,       // پیام فارسی (خطا یا توضیح موفقیت)
 *     'code'     => ?DiscountCode, // کد معتبر (در حالت ناموفق null)
 *     'discount' => int,           // مبلغ تخفیف (تومان)
 *     'final'    => int,           // مبلغ نهایی قابل پرداخت
 *   ]
 *
 * مهم: همیشه سمت سرور (قبل از ایجاد خرید) دوباره resolve می‌شود؛
 * نتیجه‌ی «اعمال» در UI فقط پیش‌نمایش است.
 */
class DiscountService
{
    /**
     * بررسی کامل یک کد تخفیف برای یک خرید.
     *
     * @param  string|null $codeInput  ورودی کاربر (خالی = بدون تخفیف → ok با discount صفر)
     * @param  string      $context    DiscountCode::CONTEXT_* (package|product|subscription)
     * @param  int         $amount     مبلغ اصلی خرید (قبل از کد تخفیف — مثلاً final_price طرح)
     */
    public function resolve(?string $codeInput, string $context, int $amount): array
    {
        $code = DiscountCode::normalize($codeInput);

        if ($code === '') {
            return ['ok' => true, 'message' => null, 'code' => null, 'discount' => 0, 'final' => max(0, $amount)];
        }

        if ($amount <= 0) {
            return [
                'ok'       => false,
                'message'  => 'برای خرید رایگان نمی‌توان از کد تخفیف استفاده کرد.',
                'code'     => null,
                'discount' => 0,
                'final'    => 0,
            ];
        }

        /** @var DiscountCode|null $model */
        $model = DiscountCode::query()->where('code', $code)->first();

        if (!$model) {
            return $this->fail('کد تخفیف یافت نشد؛ لطفاً کد را بررسی کنید.');
        }

        if (!$model->is_active) {
            return $this->fail('این کد تخفیف غیرفعال است.');
        }

        if ($model->expires_at && $model->expires_at->isPast()) {
            return $this->fail('این کد تخفیف منقضی شده است.');
        }

        if (!$model->hasRemainingUses()) {
            return $this->fail('ظرفیت استفاده از این کد تخفیف تکمیل شده است.');
        }

        if (!$model->appliesTo($context)) {
            return $this->fail('این کد تخفیف برای این نوع خرید قابل استفاده نیست.');
        }

        $discount = $model->calculateDiscount($amount);
        $final    = max(0, $amount - $discount);

        return [
            'ok'       => true,
            'message'  => 'کد تخفیف «' . $model->code . '» اعمال شد — ' . $model->valueLabel(),
            'code'     => $model,
            'discount' => $discount,
            'final'    => $final,
        ];
    }

    /** خروجی استاندارد ناموفق */
    protected function fail(string $message): array
    {
        return ['ok' => false, 'message' => $message, 'code' => null, 'discount' => 0, 'final' => 0];
    }
}
