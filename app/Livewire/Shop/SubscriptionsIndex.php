<?php

namespace App\Livewire\Shop;

use App\Livewire\Concerns\WithToasts;
use App\Models\Customer;
use App\Models\DiscountCode;
use App\Models\Gateway;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionPlan;
use App\Services\DiscountService;
use App\Services\SubscriptionService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.shop')]
#[Title('طرح‌های اشتراک')]
class SubscriptionsIndex extends Component
{
    use WithToasts;

    /** کد آپدیت مشتری */
    public string $updateCode = '';

    /** درگاه انتخابی برای خرید */
    public string $gatewayKey = '';

    /** طرح در حال خرید (slug) */
    public string $buyingSlug = '';

    /** کد تخفیف تایپ‌شده (پیش از اعمال) */
    public string $discountCode = '';

    /** کد تخفیف اعمال‌شده (پس از اعتبارسنجی سمت سرور — کد نرمال‌شده) */
    public string $appliedCode = '';

    public function mount(): void
    {
        $this->updateCode = (string) session('shop_update_code', '');
        $this->gatewayKey = (string) $this->gateways->first()?->key ?? '';
    }

    #[Computed]
    public function plans()
    {
        return SubscriptionPlan::query()
            ->active()
            ->with(['packages:id,name,slug,category'])
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();
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

    /** وضعیت سفارش‌های این مشتری برای هر طرح (یک‌بارمصرف/قبلاً خریداری) */
    #[Computed]
    public function myOrders()
    {
        $code = trim($this->updateCode);

        if ($code === '') {
            return collect();
        }

        $customer = Customer::where('update_code', $code)->first();

        if (!$customer) {
            return collect();
        }

        return SubscriptionOrder::query()
            ->where('customer_id', $customer->id)
            ->whereIn('admin_status', [SubscriptionOrder::ADMIN_STATUS_PENDING, SubscriptionOrder::ADMIN_STATUS_APPROVED, SubscriptionOrder::ADMIN_STATUS_REJECTED])
            ->get()
            ->keyBy('subscription_plan_id');
    }

    public function setBuying(string $slug): void
    {
        // با تغییر طرحِ در حال خرید، پیش‌نمایش تخفیف قبلی پاک می‌شود
        if ($this->buyingSlug !== $slug) {
            $this->appliedCode = '';
        }

        $this->buyingSlug = $this->buyingSlug === $slug ? '' : $slug;
    }

    /* ---------------------------------------------------------------- */
    /*  کد تخفیف                                                          */
    /* ---------------------------------------------------------------- */

    /** طرحِ در حال خرید (یا null) */
    protected function buyingPlan(): ?SubscriptionPlan
    {
        return $this->buyingSlug !== ''
            ? SubscriptionPlan::active()->where('slug', trim($this->buyingSlug))->first()
            : null;
    }

    /** اعمال کد تخفیف (اعتبارسنجی سمت سرور + پیش‌نمایش) */
    public function applyDiscount(DiscountService $discountService): void
    {
        $plan = $this->buyingPlan();
        $base = $plan ? (int) $plan->final_price : 0;

        if ($base <= 0) {
            $this->toast('ابتدا طرح موردنظر را برای خرید انتخاب کنید.', 'warning');
            return;
        }

        $result = $discountService->resolve($this->discountCode, DiscountCode::CONTEXT_SUBSCRIPTION, $base);

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
     * مبلغ نهایی طرح با احتساب کد تخفیف اعمال‌شده (برای نمایش در فرم خرید).
     * خروجی: [base, discount, final] — بدون تخفیف معتبر → discount = 0
     */
    public function priceWithDiscount(SubscriptionPlan $plan): array
    {
        $base = (int) $plan->final_price;

        if ($this->appliedCode === '' || $base <= 0) {
            return ['base' => $base, 'discount' => 0, 'final' => $base];
        }

        $result = app(DiscountService::class)->resolve($this->appliedCode, DiscountCode::CONTEXT_SUBSCRIPTION, $base);

        if (!$result['ok']) {
            return ['base' => $base, 'discount' => 0, 'final' => $base];
        }

        return ['base' => $base, 'discount' => $result['discount'], 'final' => $result['final']];
    }

    /**
     * رزول مجدد کد اعمال‌شده برای طرحِ در حال خرید (سازگار با partial مشترک discount-field).
     * اگر کد نامعتبر شده یا طرحی در حال خرید نباشد null برمی‌گردد.
     */
    #[Computed]
    public function appliedDiscount(): ?array
    {
        if ($this->appliedCode === '') {
            return null;
        }

        $plan = $this->buyingPlan();
        $base = $plan ? (int) $plan->final_price : 0;

        if ($base <= 0) {
            return null;
        }

        $result = app(DiscountService::class)->resolve($this->appliedCode, DiscountCode::CONTEXT_SUBSCRIPTION, $base);

        return $result['ok'] ? $result : null;
    }

    public function buy(SubscriptionService $service, DiscountService $discountService): void
    {
        $this->validate([
            'updateCode' => ['required', 'string', 'max:64'],
        ], [
            'updateCode.required' => 'کد آپدیت الزامی است.',
        ]);

        $plan = SubscriptionPlan::active()->where('slug', trim($this->buyingSlug))->first();

        if (!$plan) {
            $this->toast('طرح انتخاب‌شده معتبر نیست.', 'error');

            return;
        }

        $customer = Customer::query()
            ->where('update_code', trim($this->updateCode))
            ->first();

        if (!$customer) {
            $this->toast('کد آپدیت نامعتبر است.', 'error');

            return;
        }

        if ($customer->status !== 'active') {
            $this->toast('حساب این مشتری غیرفعال است.', 'error');

            return;
        }

        // ---- کد تخفیف (راستی‌آزمایی مجدد سمت سرور) ----
        $coupon = null;
        $base   = (int) $plan->final_price;

        if ($base > 0) {
            $couponResult = $discountService->resolve(
                $this->appliedCode !== '' ? $this->appliedCode : $this->discountCode,
                DiscountCode::CONTEXT_SUBSCRIPTION,
                $base
            );

            if (!$couponResult['ok']) {
                $this->appliedCode = '';
                $this->toast($couponResult['message'], 'error');

                return;
            }

            $coupon = $couponResult['code'];
        }

        try {
            $result = $service->purchase(
                $customer,
                $plan,
                $plan->final_price > 0 ? $this->gatewayKey : null,
                null,
                $coupon
            );
        } catch (\Throwable $e) {
            $this->toast($e->getMessage(), 'error');

            return;
        }

        session()->put('shop_update_code', trim($this->updateCode));

        $order = $result['order'];

        // رایگان → مستقیم صفحه نتیجه (در انتظار تأیید مدیر)
        if (($result['is_free'] ?? false)) {
            $this->redirect(route('subscription.result', $order), navigate: true);

            return;
        }

        // پولی → درگاه
        $this->redirect($result['payment_url'], navigate: false);
    }

    public function render()
    {
        return view('livewire.shop.subscriptions-index');
    }
}
