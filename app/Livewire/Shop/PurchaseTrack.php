<?php

namespace App\Livewire\Shop;

use App\Livewire\Concerns\WithToasts;
use App\Models\ProductPurchase;
use App\Models\TemplatePurchase;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * پیگیری خرید مهمان: با شماره تراکنش، صفحه نتیجه خرید دوباره باز می‌شود
 * (برای دانلود محصول/قالب پس از بستن صفحه اولیه).
 */
class PurchaseTrack extends Component
{
    use WithToasts;

    public string $transactionId = '';

    public function track(): void
    {
        $trx = trim($this->transactionId);

        if ($trx === '') {
            $this->toast('شماره تراکنش را وارد کنید.', 'error');
            return;
        }

        // اول قالب‌ها، بعد محصولات (هر دو خرید مهمان‌اند)
        $purchase = TemplatePurchase::query()->where('transaction_id', $trx)->first();

        if ($purchase) {
            $this->redirect(route('payment.result.template', $purchase), navigate: true);
            return;
        }

        $purchase = ProductPurchase::query()->where('transaction_id', $trx)->first();

        if (!$purchase) {
            $this->toast('خریدی با این شماره تراکنش یافت نشد.', 'error');
            return;
        }

        $this->redirect(route('payment.result.product', $purchase), navigate: true);
    }

    public function render()
    {
        return view('livewire.shop.purchase-track');
    }
}
