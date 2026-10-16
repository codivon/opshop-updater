<?php

namespace App\Livewire\Shop;

use App\Livewire\Concerns\WithToasts;
use App\Models\Gateway;
use App\Models\Product;
use App\Models\ProductPurchase;
use App\Services\PaymentService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.shop')]
#[Title('جزئیات پروژه')]
class ProductShow extends Component
{
    use WithToasts;

    public Product $product;

    /** درگاه پرداخت انتخاب‌شده */
    public string $gatewayKey = '';

    public function mount(string $slug): void
    {
        $product = Product::query()
            ->with(['category:id,name,slug', 'latestVersion'])
            ->where('slug', $slug)
            ->where('status', Product::STATUS_ACTIVE)
            ->first();

        if (!$product) {
            abort(404);
        }

        $this->product = $product;
        $this->gatewayKey = (string) (Gateway::query()->active()->orderBy('ordering')->first()?->key ?? '');

        // شمارش بازدید (هر بازدید کامل صفحه)
        $product->incrementViews();
    }

    #[Computed]
    public function gateways()
    {
        return Gateway::query()
            ->active()
            ->orderBy('ordering')
            ->orderBy('name')
            ->get();
    }

    /** آخرین ۵ نسخه فعال */
    #[Computed]
    public function versions()
    {
        return $this->product->activeVersions()->limit(5)->get();
    }

    /* ================================================================
     *  خرید (مهمان — بدون هیچ اطلاعات)
     * ================================================================ */
    public function buy(PaymentService $paymentService): void
    {
        // ---- نسخه ----
        $latestVersion = $this->product->latestVersion()->first();

        if (!$latestVersion || !$latestVersion->hasFile()) {
            $this->toast('فایل قابل دانلود برای این پروژه هنوز آماده نیست. لطفاً بعداً تلاش کنید.', 'error');
            return;
        }

        // ---- مسیر رایگان ----
        if ($this->product->is_effectively_free) {
            $purchase = ProductPurchase::create([
                'product_id' => $this->product->id,
                'version_id' => $latestVersion->id,
                'amount'     => 0,
                'status'     => ProductPurchase::STATUS_PAID,
                'paid_at'    => now(),
            ]);

            $this->toast('پروژه رایگان دریافت شد؛ می‌توانید همین حالا دانلود کنید.');
            $this->redirect(route('payment.result.product', $purchase), navigate: true);

            return;
        }

        // ---- مسیر پرداخت ----
        if ($this->gatewayKey === '' || !$this->gateways->firstWhere('key', $this->gatewayKey)) {
            $this->toast('درگاه پرداخت فعالی تنظیم نشده است. لطفاً با پشتیبانی تماس بگیرید.', 'error');
            return;
        }

        $purchase = ProductPurchase::create([
            'product_id'   => $this->product->id,
            'version_id'   => $latestVersion->id,
            'callback_url' => route('payment.callback'),
            'amount'       => $this->product->final_price,
            'gateway'      => $this->gatewayKey,
            'status'       => ProductPurchase::STATUS_PENDING,
        ]);

        try {
            $payment = $paymentService->createProductPayment($purchase, $this->gatewayKey);
        } catch (\Throwable $e) {
            // رکورد pending یتیم را پاک کن (الگوی گاتچای پروژه)
            optional($purchase)->delete();
            $this->toast($e->getMessage(), 'error');
            return;
        }

        $this->redirect($payment['payment_url'], navigate: false);
    }

    public function render()
    {
        return view('livewire.shop.product-show');
    }
}
