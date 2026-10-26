<?php

namespace App\Livewire\Shop;

use App\Livewire\Concerns\WithToasts;
use App\Models\DiscountCode;
use App\Models\PackagePurchase;
use App\Services\DiscountService;
use App\Services\LicenseService;
use App\Services\PaymentService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;

/**
 * صفحه تسویه خریدهای API (مرحله میانی پیش از درگاه).
 *
 *  کاربرِ پروژه خریدار با لینک امضادار (checkout_url پاسخ API) به این صفحه می‌آید و:
 *   - نام/تصویر پکیج، طرح انتخابی و مبلغ را می‌بیند
 *   - می‌تواند کد تخفیف وارد و اعمال کند (پیش‌نمایش لحظه‌ای مبلغ)
 *   - با «پرداخت» وارد درگاه می‌شود (تراکنش درگاه همین‌جا ساخته می‌شود تا مبلغ
 *     نهایی پس از کد تخفیف مبنای شارژ باشد)
 *   - با «انصراف» خرید لغو و به فروشگاه مبدأ بازمی‌گردد
 *
 *  مسیر با middleware('signed') محافظت شده است (لینک ۲۴ ساعته از API).
 */
#[Layout('components.layouts.shop')]
#[Title('تسویه سفارش')]
class PackageCheckout extends Component
{
    use WithToasts;

    public PackagePurchase $purchase;

    /** ورودی کد تخفیف کاربر */
    public string $codeInput = '';

    /** کد تخفیف اعمال‌شده (نرمال‌شده) — فقط پیش‌نمایش؛ هنگام پرداخت دوباره resolve می‌شود */
    public ?string $appliedCode = null;

    /** پیام وضعیت کد (موفق/خطا) برای نمایش زیر فیلد */
    public ?string $codeMessage = null;

    /** نوع پیام کد: ok | error */
    public string $codeMessageType = 'ok';

    /** مبلغ تخفیف کد (تومان) */
    public int $codeDiscount = 0;

    /** خطای عمومی صفحه (مثلاً شکست ساخت تراکنش درگاه) */
    public ?string $pageError = null;

    public function mount(PackagePurchase $purchase): void
    {
        $purchase->load([
            'package:id,name,slug,thumbnail,is_free,status',
            'pricingPlan:id,package_id,name,duration_months,price,discount_price,is_one_time,description,is_active',
            'version:id,version',
            'customer:id,name,email',
        ]);

        $this->purchase = $purchase;

        // اگر کد تخفیفی هنگام ایجاد خرید ثبت شده باشد (پشتیبانی رکوردهای قدیمی)
        if ($this->purchase->discount_code_id) {
            $this->appliedCode = $this->purchase->discountCode?->code;
            $this->codeDiscount = (int) $this->purchase->discount_amount;
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Computed                                                          */
    /* ------------------------------------------------------------------ */

    /** مبلغ پایه خرید = مبلغ نهایی طرح (پیش از کد تخفیف) */
    public function getBaseAmountProperty(): int
    {
        return (int) ($this->purchase->pricingPlan?->final_price ?? $this->purchase->original_amount);
    }

    /** مبلغ قابل پرداخت پس از کد تخفیف */
    public function getPayableAmountProperty(): int
    {
        return max(0, $this->baseAmount - $this->codeDiscount);
    }

    /** آیا تراکنش درگاه قبلاً برای این خرید شروع شده است؟ */
    public function getPaymentStartedProperty(): bool
    {
        return $this->purchase->transaction_id !== null
            && $this->purchase->payment_url !== null
            && $this->purchase->status === PackagePurchase::STATUS_PENDING;
    }

    /** آیا خرید در وضعیت قابل پرداخت است؟ */
    public function getIsPayableProperty(): bool
    {
        return $this->purchase->status === PackagePurchase::STATUS_PENDING;
    }

    /* ------------------------------------------------------------------ */
    /*  Actions                                                           */
    /* ------------------------------------------------------------------ */

    /** اعمال/به‌روزرسانی کد تخفیف (پیش‌نمایش — هنگام پرداخت دوباره سروری resolve می‌شود) */
    public function applyDiscount(DiscountService $discountService): void
    {
        $this->resetErrorBag();
        $this->pageError = null;

        $code = trim($this->codeInput);

        if ($code === '') {
            $this->removeDiscount();

            return;
        }

        $result = $discountService->resolve($code, DiscountCode::CONTEXT_PACKAGE, $this->baseAmount);

        if (!$result['ok']) {
            $this->appliedCode = null;
            $this->codeDiscount = 0;
            $this->codeMessage = $result['message'];
            $this->codeMessageType = 'error';

            return;
        }

        // کد معتبر بدون تخفیف؟ (کد معتبر اما مبلغ صفر — عملاً بی‌اثر)
        $this->appliedCode = $result['code']?->code;
        $this->codeDiscount = (int) $result['discount'];
        $this->codeMessage = $result['message'];
        $this->codeMessageType = 'ok';
    }

    /** حذف کد تخفیف اعمال‌شده */
    public function removeDiscount(): void
    {
        $this->appliedCode = null;
        $this->codeInput = '';
        $this->codeDiscount = 0;
        $this->codeMessage = null;
        $this->codeMessageType = 'ok';
    }

    /**
     * شروع پرداخت: اعمال نهایی کد تخفیف روی رکورد خرید + ساخت تراکنش درگاه + ریدایرکت.
     */
    public function pay(PaymentService $paymentService, LicenseService $licenseService): mixed
    {
        $this->resetErrorBag();
        $this->pageError = null;

        if (!$this->isPayable) {
            return null;
        }

        $plan = $this->purchase->pricingPlan;

        if (!$plan) {
            $this->pageError = 'طرح قیمت‌گذاری این خرید یافت نشد.';

            return null;
        }

        // امنیت: کد تخفیف همیشه سمت سرور و پیش از ساخت تراکنش دوباره resolve می‌شود
        // (کد اعمال‌شده در UI ملاک است؛ اگر کاربر متن جدیدی نوشته ولی «اعمال» نزده،
        //  همان کد نمایش‌داده‌شده در خلاصه سفارش شارژ می‌شود)
        $codeToApply = ($this->appliedCode !== null && $this->appliedCode !== '')
            ? $this->appliedCode
            : $this->codeInput;

        $result = app(DiscountService::class)->resolve(
            $codeToApply,
            DiscountCode::CONTEXT_PACKAGE,
            $this->baseAmount
        );

        if (!$result['ok']) {
            $this->appliedCode = null;
            $this->codeDiscount = 0;
            $this->codeMessage = $result['message'];
            $this->codeMessageType = 'error';
            $this->pageError = 'کد تخفیف معتبر نیست؛ پس از اصلاح، دوباره تلاش کنید.';

            return null;
        }

        $coupon   = $result['code'];
        $discount = (int) $result['discount'];
        $final    = (int) $result['final'];

        // ثبت تخفیف روی رکورد خرید — amount همان «مبلغ نهایی قابل پرداخت» است
        $this->purchase->update([
            'discount_code_id' => $coupon?->id,
            'original_amount'  => $this->baseAmount,
            'discount_amount'  => $discount,
            'amount'           => $final,
        ]);

        $this->purchase->refresh();
        $this->appliedCode   = $coupon?->code;
        $this->codeDiscount  = $discount;

        // ---------- کد تخفیف کل مبلغ را پوشش داده → بدون درگاه، لایسنس فوری ----------
        if ($final <= 0) {
            $latestVersion = $this->purchase->package->latestVersion()->first();

            if (!$latestVersion) {
                $this->pageError = 'نسخه فعالی برای این پکیج وجود ندارد؛ لطفاً با پشتیبانی تماس بگیرید.';

                return null;
            }

            $licenseService->issueLicense($this->purchase, $plan);
            $this->purchase->markAsPaid($this->purchase->gateway);

            $this->toast('کد تخفیف کل مبلغ را پوشش داد — لایسنس شما صادر شد.');

            return $this->redirect(route('payment.result', $this->purchase), navigate: false);
        }

        // ---------- ادامه پرداخت در جریان (کاربر دکمه پرداخت درگاه را زده) ----------
        if ($this->paymentStarted) {
            return $this->redirectToGateway($this->purchase->payment_url);
        }

        try {
            $payment = $paymentService->createPayment($this->purchase, $this->purchase->gateway ?: null);
        } catch (RuntimeException $e) {
            $this->pageError = $e->getMessage();

            return null;
        }

        return $this->redirectToGateway($payment['payment_url']);
    }

    /**
     * انصراف از خرید: لغو رکورد pending + بازگشت به فروشگاه مبدأ (callback_url).
     */
    public function cancel(): mixed
    {
        if (!$this->isPayable) {
            return null;
        }

        $callbackUrl = $this->purchase->callback_url;

        $this->purchase->markAsFailed('انصراف کاربر از پرداخت');

        if ($callbackUrl) {
            // بازگشت به فروشگاه مبدأ با نشان لغو (مطابق عرف درگاه‌ها)
            $separator = str_contains($callbackUrl, '?') ? '&' : '?';

            return $this->redirect($callbackUrl . $separator . 'status=canceled', navigate: false);
        }

        $this->toast('خرید لغو شد.', 'warning');

        return null;
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                           */
    /* ------------------------------------------------------------------ */

    private function redirectToGateway(string $url): mixed
    {
        // خروج کامل از صفحه (navigate: false → ریدایرکت کامل مرورگر،
        // هم برای درگاه بیرونی و هم برای فرم امضادار روی همین پنل)
        $this->redirect($url, navigate: false);

        return null;
    }

    public function render()
    {
        return view('livewire.shop.package-checkout');
    }
}
