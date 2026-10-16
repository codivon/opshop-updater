<?php

namespace App\Livewire\Shop;

use App\Livewire\Concerns\WithToasts;
use App\Models\ProductPurchase;
use App\Services\PaymentService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.shop')]
#[Title('نتیجه خرید پروژه')]
class ProductPaymentResult extends Component
{
    use WithToasts;

    public ProductPurchase $purchase;

    /** لینک دانلود یک‌بارمصرف تولیدشده برای همین رندر */
    public ?string $downloadUrl = null;

    public function mount(ProductPurchase $purchase): void
    {
        $purchase->load(['product:id,name,slug,thumbnail', 'version:id,product_id,version,file_size']);

        $this->purchase = $purchase;
    }

    /**
     * بررسی مجدد وضعیت پرداخت در انتظار (callback ممکن است رسیده نباشد).
     */
    public function recheck(PaymentService $paymentService): void
    {
        if ($this->purchase->status !== ProductPurchase::STATUS_PENDING) {
            $this->toast('این خرید در انتظار تأیید نیست.');
            return;
        }

        if (!$this->purchase->transaction_id) {
            $this->toast('شناسه تراکنش برای این خرید ثبت نشده است.', 'error');
            return;
        }

        $result = $paymentService->verifyProductPayment($this->purchase->transaction_id);

        $this->purchase->refresh();

        if ($result['paid'] ?? false) {
            $this->toast($result['message'] ?? 'پرداخت با موفقیت تأیید شد.');
        } else {
            $this->toast($result['message'] ?? 'پرداخت هنوز تأیید نشده است.', 'error');
        }
    }

    /**
     * تولید لینک دانلود یک‌بارمصرف ۱۵ دقیقه‌ای.
     * (هر بار دکمه بزنید لینک تازه‌ای می‌گیرید — لینک قبلی باطل نمی‌شود اما یک‌بارمصرف است.)
     */
    public function getDownload(): void
    {
        if (!$this->purchase->isPaid()) {
            $this->toast('ابتدا پرداخت باید با موفقیت انجام شود.', 'error');
            return;
        }

        $token = $this->purchase->createDownloadToken();

        if (!$token) {
            $this->toast('فایل نسخه محصول یافت نشد. لطفاً با پشتیبانی تماس بگیرید.', 'error');
            return;
        }

        $this->downloadUrl = route('products.download', $token->token);

        // دانلود فوری در همان کلیک (browser navigation)
        $this->redirect($this->downloadUrl, navigate: false);
    }

    public function render()
    {
        return view('livewire.shop.product-payment-result');
    }
}
