<?php

namespace App\Livewire\Shop;

use App\Livewire\Concerns\WithToasts;
use App\Models\DiscountCode;
use App\Models\Gateway;
use App\Models\Product;
use App\Models\ProductPurchase;
use App\Services\DiscountService;
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

    /** کد تخفیف تایپ‌شده توسط کاربر (پیش از اعمال) */
    public string $discountCode = '';

    /** کد تخفیف اعمال‌شده (پس از اعتبارسنجی سمت سرور — کد نرمال‌شده) */
    public string $appliedCode = '';

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
     *  کد تخفیف
     * ================================================================ */

    /**
     * اعمال کد تخفیف: اعتبارسنجی سمت سرور + پیش‌نمایش قیمت.
     * (در buy() هم دوباره resolve می‌شود — منبع حقیقت همیشه سرور است)
     */
    public function applyDiscount(DiscountService $discountService): void
    {
        $result = $discountService->resolve(
            $this->discountCode,
            DiscountCode::CONTEXT_PRODUCT,
            (int) $this->product->final_price
        );

        if (!$result['ok']) {
            $this->appliedCode = '';
            $this->toast($result['message'], 'error');
            return;
        }

        if ($result['code'] === null) {
            $this->appliedCode = '';
            $this->toast('ابتدا یک کد تخفیف وارد کنید.', 'warning');
            return;
        }

        $this->appliedCode = $result['code']->code;
        $this->toast($result['message']);
    }

    /** حذف کد تخفیف اعمال‌شده */
    public function removeDiscount(): void
    {
        $this->discountCode = '';
        $this->appliedCode = '';
    }

    /**
     * رزول مجدد کد اعمال‌شده (برای نمایش زنده‌ی مبلغ پس از تخفیف در Blade).
     * اگر کد بین اعمال و پرداخت نامعتبر شده باشد null برمی‌گردد.
     */
    #[Computed]
    public function appliedDiscount(): ?array
    {
        if ($this->appliedCode === '') {
            return null;
        }

        $result = app(DiscountService::class)->resolve(
            $this->appliedCode,
            DiscountCode::CONTEXT_PRODUCT,
            (int) $this->product->final_price
        );

        return $result['ok'] ? $result : null;
    }

    /* ================================================================
     *  خرید (مهمان — بدون هیچ اطلاعات)
     * ================================================================ */
    public function buy(PaymentService $paymentService, DiscountService $discountService): void
    {
        // ---- نسخه ----
        $latestVersion = $this->product->latestVersion()->first();

        if (!$latestVersion || !$latestVersion->hasFile()) {
            $this->toast('فایل قابل دانلود برای این پروژه هنوز آماده نیست. لطفاً بعداً تلاش کنید.', 'error');
            return;
        }

        // ---- کد تخفیف (راستی‌آزمایی مجدد سمت سرور) ----
        // در پروژه رایگان مبلغی وجود ندارد → کد نادیده گرفته می‌شود (نباید جلوی دریافت را بگیرد)
        if ((int) $this->product->final_price > 0) {
            $couponResult = $discountService->resolve(
                $this->appliedCode !== '' ? $this->appliedCode : $this->discountCode,
                DiscountCode::CONTEXT_PRODUCT,
                (int) $this->product->final_price
            );

            if (!$couponResult['ok']) {
                $this->appliedCode = '';
                $this->toast($couponResult['message'], 'error');
                return;
            }

            /** @var \App\Models\DiscountCode|null $coupon */
            $coupon          = $couponResult['code'];
            $couponDiscount  = $couponResult['discount'];
            $finalPrice      = $couponResult['final'];
        } else {
            $coupon         = null;
            $couponDiscount = 0;
            $finalPrice     = 0;
        }

        // ---- مسیر رایگان (پروژه رایگان یا کد تخفیفِ پوشش‌دهنده‌ی کامل مبلغ) ----
        if ($this->product->is_effectively_free || $finalPrice <= 0) {
            $purchase = ProductPurchase::create([
                'product_id'       => $this->product->id,
                'version_id'       => $latestVersion->id,
                'discount_code_id' => $coupon?->id,
                'original_amount'  => $this->product->final_price,
                'amount'           => 0,
                'discount_amount'  => $couponDiscount,
                'status'           => ProductPurchase::STATUS_PAID,
                'paid_at'          => now(),
            ]);

            // مسیر رایگان از markAsPaid عبور نمی‌کند → مصرف شمارنده کد اینجا انجام می‌شود
            $coupon?->recordUsage();

            $this->toast($couponDiscount > 0
                ? 'پروژه با کد تخفیف به‌صورت رایگان دریافت شد؛ می‌توانید همین حالا دانلود کنید.'
                : 'پروژه رایگان دریافت شد؛ می‌توانید همین حالا دانلود کنید.');
            $this->redirect(route('payment.result.product', $purchase), navigate: true);

            return;
        }

        // ---- مسیر پرداخت ----
        if ($this->gatewayKey === '' || !$this->gateways->firstWhere('key', $this->gatewayKey)) {
            $this->toast('درگاه پرداخت فعالی تنظیم نشده است. لطفاً با پشتیبانی تماس بگیرید.', 'error');
            return;
        }

        $purchase = ProductPurchase::create([
            'product_id'       => $this->product->id,
            'version_id'       => $latestVersion->id,
            'discount_code_id' => $coupon?->id,
            'callback_url'     => route('payment.callback'),
            'original_amount'  => $this->product->final_price,
            'amount'           => $finalPrice,
            'discount_amount'  => $couponDiscount,
            'gateway'          => $this->gatewayKey,
            'status'           => ProductPurchase::STATUS_PENDING,
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
