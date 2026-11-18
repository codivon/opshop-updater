<?php

namespace App\Livewire\Shop;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * صفحهٔ «پرداخت لغو شد / ناتمام ماند» — Task 92.
 *
 * جایگاه: وقتی درگاه پرداخت بدون هیچ شناسه‌ای کاربر را برمی‌گرداند
 * (مثلاً کاربر دکمهٔ بازگشت مرورگر را در درگاه زده یا پرداخت را لغو کرده)
 * قبلاً مستقیم به صفحهٔ اصلی هدایت می‌شد و کاربر هیچ بازخوردی نمی‌گرفت؛
 * حالا این صفحهٔ اختصاصی با توضیح شفاف و دکمه‌های ادامه نمایش داده می‌شود.
 */
#[Layout('components.layouts.shop')]
#[Title('پرداخت ناموفق')]
class PaymentCanceled extends Component
{
    public function render()
    {
        return view('livewire.shop.payment-canceled');
    }
}
