<?php

namespace App\Livewire\Shop;

use App\Livewire\Concerns\WithToasts;
use App\Models\DiscountCode;
use App\Models\Gateway;
use App\Models\Template;
use App\Models\TemplatePurchase;
use App\Services\DiscountService;
use App\Services\PaymentService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.shop')]
#[Title('جزئیات قالب')]
class TemplateShow extends Component
{
    use WithToasts;

    public Template $template;

    /** درگاه پرداخت انتخاب‌شده */
    public string $gatewayKey = '';

    /** کد تخفیف تایپ‌شده توسط کاربر (پیش از اعمال) */
    public string $discountCode = '';

    /** کد تخفیف اعمال‌شده (پس از اعتبارسنجی سمت سرور — کد نرمال‌شده) */
    public string $appliedCode = '';

    /** نوع خرید انتخاب‌شده: normal | exclusive */
    public string $purchaseType = Template::PURCHASE_NORMAL;

    public function mount(string $slug): void
    {
        $template = Template::query()
            ->with(['category:id,name,slug', 'latestVersion', 'activeImages'])
            ->where('slug', $slug)
            ->where('status', Template::STATUS_ACTIVE)
            ->first();

        if (!$template) {
            abort(404);
        }

        $this->template = $template;
        $this->gatewayKey = (string) (Gateway::query()->active()->orderBy('ordering')->first()?->key ?? '');
        $this->purchaseType = Template::PURCHASE_NORMAL;

        // شمارش بازدید (هر بازدید کامل صفحه)
        $template->incrementViews();
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
        return $this->template->activeVersions()->limit(5)->get();
    }

    /* ================================================================
     *  نوع خرید (معمولی / اختصاصی)
     * ================================================================ */

    /** مقدار مجاز نوع خرید — ضد دستکاری کلاینت */
    public function updatedPurchaseType(string $value): void
    {
        if (!in_array($value, [Template::PURCHASE_NORMAL, Template::PURCHASE_EXCLUSIVE], true)) {
            $this->purchaseType = Template::PURCHASE_NORMAL;
        }

        // تغییر نوع خرید → کد تخفیف اعمال‌شده دیگر معتبر نیست (مبلغ پایه عوض شده)
        $this->appliedCode = '';
    }

    /** قیمت پایه برای نوع خرید انتخاب‌شده */
    public function currentBasePrice(): int
    {
        return $this->purchaseType === Template::PURCHASE_EXCLUSIVE
            ? $this->template->exclusive_price
            : $this->template->price;
    }

    /** قیمت نهایی برای نوع خرید انتخاب‌شده */
    public function currentFinalPrice(): int
    {
        return $this->purchaseType === Template::PURCHASE_EXCLUSIVE
            ? $this->template->exclusive_final_price
            : $this->template->final_price;
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
        if ((int) $this->currentFinalPrice() <= 0) {
            $this->toast('برای این نوع خرید نیازی به کد تخفیف نیست.', 'warning');
            return;
        }

        $result = $discountService->resolve(
            $this->discountCode,
            DiscountCode::CONTEXT_TEMPLATE,
            (int) $this->currentFinalPrice()
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

        if ((int) $this->currentFinalPrice() <= 0) {
            return null;
        }

        $result = app(DiscountService::class)->resolve(
            $this->appliedCode,
            DiscountCode::CONTEXT_TEMPLATE,
            (int) $this->currentFinalPrice()
        );

        return $result['ok'] ? $result : null;
    }

    /* ================================================================
     *  خرید (مهمان — بدون هیچ اطلاعات) — معمولی یا اختصاصی
     * ================================================================ */
    public function buy(PaymentService $paymentService, DiscountService $discountService): void
    {
        // ---- نوع خرید معتبر؟ ----
        $purchaseType = in_array($this->purchaseType, [Template::PURCHASE_NORMAL, Template::PURCHASE_EXCLUSIVE], true)
            ? $this->purchaseType
            : Template::PURCHASE_NORMAL;

        // ---- خرید اختصاصی: شرایط ----
        if ($purchaseType === Template::PURCHASE_EXCLUSIVE) {
            if ($this->template->is_sold_exclusively) {
                $this->toast('این قالب قبلاً به‌صورت اختصاصی واگذار شده و دیگر قابل خرید نیست.', 'error');
                return;
            }

            if ((int) $this->template->exclusive_price <= 0) {
                $this->toast('خرید اختصاصی برای این قالب فعال نشده است.', 'error');
                return;
            }
        }

        // ---- نسخه ----
        $latestVersion = $this->template->latestVersion()->first();

        if (!$latestVersion || !$latestVersion->hasFile()) {
            $this->toast('فایل قابل دانلود برای این قالب هنوز آماده نیست. لطفاً بعداً تلاش کنید.', 'error');
            return;
        }

        // ---- مبالغ بر اساس نوع خرید ----
        // مبلغ مبنا = قیمت نهایی «پس از تخفیف خود قالب»؛ کد تخفیف روی همین اعمال می‌شود
        // (هماهنگ با پیش‌نمایش فیلد کد تخفیف در Blade)
        $baseAmount = $purchaseType === Template::PURCHASE_EXCLUSIVE
            ? (int) $this->template->exclusive_final_price
            : (int) $this->template->final_price;

        // ---- کد تخفیف (راستی‌آزمایی مجدد سمت سرور) ----
        // مبلغ صفر (رایگان) → کد نادیده گرفته می‌شود (نباید جلوی دریافت را بگیرد)
        if ($baseAmount > 0) {
            $couponResult = $discountService->resolve(
                $this->appliedCode !== '' ? $this->appliedCode : $this->discountCode,
                DiscountCode::CONTEXT_TEMPLATE,
                $baseAmount
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

        // ---- مسیر رایگان (قالب رایگان یا کد تخفیفِ پوشش‌دهنده‌ی کامل مبلغ) ----
        if ($baseAmount <= 0 || $finalPrice <= 0) {
            if ($purchaseType === Template::PURCHASE_EXCLUSIVE) {
                // خرید اختصاصی رایگان بی‌معناست — واگذاری کامل حقوق بدون پول مجاز نیست
                $this->toast('خرید اختصاصی باید با پرداخت کامل انجام شود؛ کد تخفیف کامل برای آن مجاز نیست.', 'error');
                return;
            }

            $purchase = TemplatePurchase::create([
                'template_id'      => $this->template->id,
                'version_id'       => $latestVersion->id,
                'discount_code_id' => $coupon?->id,
                'original_amount'  => $baseAmount,
                'amount'           => 0,
                'discount_amount'  => $couponDiscount,
                'purchase_type'    => $purchaseType,
                'status'           => TemplatePurchase::STATUS_PAID,
                'paid_at'          => now(),
            ]);

            // مسیر رایگان از markAsPaid عبور نمی‌کند → شمارنده‌ها اینجا دستی:
            // فروش قالب (رایگان هم فروش است) + مصرف شمارنده کد تخفیف
            $this->template->incrementSales();
            $coupon?->recordUsage();

            $this->toast($couponDiscount > 0
                ? 'قالب با کد تخفیف به‌صورت رایگان دریافت شد؛ می‌توانید همین حالا دانلود کنید.'
                : 'قالب رایگان دریافت شد؛ می‌توانید همین حالا دانلود کنید.');
            $this->redirect(route('payment.result.template', $purchase), navigate: true);

            return;
        }

        // ---- مسیر پرداخت ----
        if ($this->gatewayKey === '' || !$this->gateways->firstWhere('key', $this->gatewayKey)) {
            $this->toast('درگاه پرداخت فعالی تنظیم نشده است. لطفاً با پشتیبانی تماس بگیرید.', 'error');
            return;
        }

        $purchase = TemplatePurchase::create([
            'template_id'      => $this->template->id,
            'version_id'       => $latestVersion->id,
            'discount_code_id' => $coupon?->id,
            'callback_url'     => route('payment.callback'),
            'original_amount'  => $baseAmount,
            'amount'           => $finalPrice,
            'discount_amount'  => $couponDiscount,
            'purchase_type'    => $purchaseType,
            'gateway'          => $this->gatewayKey,
            'status'           => TemplatePurchase::STATUS_PENDING,
        ]);

        try {
            $payment = $paymentService->createTemplatePayment($purchase, $this->gatewayKey);
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
        return view('livewire.shop.template-show');
    }
}
