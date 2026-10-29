<?php

namespace App\Livewire\Shop;

use App\Livewire\Concerns\WithToasts;
use App\Models\Customer;
use App\Models\DiscountCode;
use App\Models\Gateway;
use App\Models\Package;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanDuration;
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

    /** مدت اعتبار انتخابی هر طرح: [plan_id => duration_months] */
    public array $selectedDurations = [];

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
            ->with([
                'packages:id,name,slug,category',
                'durations',
                // پیکربندی اختصاصی هر مدت — پکیج‌های فعال (Task 63)
                'durations.packages' => fn ($q) => $q->where('status', Package::STATUS_ACTIVE),
            ])
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();
    }

    /* ---------------------------------------------------------------- */
    /*  مدت اعتبار (چندگزینه‌ای)                                          */
    /* ---------------------------------------------------------------- */

    /** مدت انتخاب‌شدهٔ یک طرح — یا null (فال‌بک به تک‌مدت پیش‌فرض طرح) */
    public function selectedDurationFor(SubscriptionPlan $plan): ?SubscriptionPlanDuration
    {
        $months = $this->selectedDurations[$plan->id] ?? null;

        return $months === null ? null : $plan->durationOption((int) $months);
    }

    /**
     * مدتِ نمایشی کارت/فرم: انتخاب مشتری، وگرنه اولین گزینهٔ فعال طرح
     * (هم‌راستا با پیش‌انتخاب فرم خرید — کارت هرگز قیمت گمراه‌کننده نشان نمی‌دهد)
     */
    public function displayDurationFor(SubscriptionPlan $plan): ?SubscriptionPlanDuration
    {
        if (!$plan->hasDurations()) {
            return null;
        }

        return $this->selectedDurationFor($plan) ?? $plan->durationOptions()->first();
    }

    /** مبلغ نهایی مؤثر طرح (مدت انتخابی یا پیش‌فرض) — پایهٔ محاسبهٔ کد تخفیف و پرداخت */
    public function effectiveBaseFor(SubscriptionPlan $plan): int
    {
        $option = $this->selectedDurationFor($plan);

        return $option ? $option->final_price : $plan->final_price;
    }

    /** انتخاب مدت اعتبار از کارت/فرم خرید */
    public function selectDuration(int $planId, int $months): void
    {
        /** @var SubscriptionPlan|null $plan */
        $plan = $this->plans->firstWhere('id', $planId);

        if (!$plan || !$plan->durationOption($months)) {
            return; // مدت نامعتبر نادیده گرفته می‌شود
        }

        // با تغییر مدت، پیش‌نمایش تخفیف قبلی بر اساس مبلغ جدید به‌روز می‌شود
        $this->selectedDurations[$planId] = $months;
    }

    /* ---------------------------------------------------------------- */
    /*  جزئیات هر مدت (مودال نمایشی کارت — Task 64)                       */
    /* ---------------------------------------------------------------- */

    /**
     * پیکربندی مؤثر هر مدت اعتبار یک طرح (قابلیت‌ها + پکیج‌های همراه) برای
     * مودال «جزئیات مدت» در کارت فروشگاه.
     *
     * خروجی به‌صورت آرایهٔ سادهٔ JSON-پذیر است تا با @js به Alpine داده شود و
     * باز/بسته شدن مودال کاملاً سمت مرورگر باشد (بدون رفت‌وبرگشت سرور — سریع).
     * ارث‌بری رعایت می‌شود: مدت بدون پیکربندی اختصاصی = قابلیت‌ها/پکیج‌های خود طرح.
     *
     * @return array<int, array{label:string, price:string, original:string, percent:int, custom:bool, features:array<int,string>, packages:array<int, array{name:string, free:string}}>
     */
    public function durationDetailsFor(SubscriptionPlan $plan): array
    {
        if (!$plan->hasDurations()) {
            return [];
        }

        return $plan->durationOptions()
            ->map(fn (SubscriptionPlanDuration $d) => [
                'label'    => $d->duration_label,
                'price'    => $d->final_price <= 0 ? 'رایگان' : money($d->final_price, false) . ' تومان',
                'original' => ($d->has_discount && $d->final_price > 0) ? money($d->price, false) . ' تومان' : '',
                'percent'  => (int) $d->discount_percent,
                'custom'   => $d->hasOwnFeatures() || $d->hasOwnPackages(),
                // قابلیت‌های مؤثر (اختصاصی مدت یا ارث‌بری از طرح)
                'features' => array_values($d->resolvedFeatures($plan->features ?? [])),
                // پکیج‌های همراه مؤثر (اختصاصی مدت یا ارث‌بری از طرح) با برچسب ماه رایگان
                'packages' => $d->resolvedPackages($plan->packages)
                    ->map(fn ($p) => [
                        'name' => $p->name,
                        'free' => \App\Models\SubscriptionPlan::freeMonthsLabel((int) $p->pivot->free_months),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
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

        // باز کردن فرم خرید: پیش‌فرضِ انتخاب مدت = گزینهٔ اول؛
        // اگر انتخاب قبلی دیگر معتبر نباشد (مثلاً آن مدت در پنل حذف شده) هم گزینهٔ اول جایگزین می‌شود
        $plan = SubscriptionPlan::active()->where('slug', trim($slug))->first();

        if ($plan && $plan->hasDurations()) {
            $current = isset($this->selectedDurations[$plan->id])
                ? $plan->durationOption((int) $this->selectedDurations[$plan->id])
                : null;

            if (!$current) {
                $first = $plan->durationOptions()->first();

                if ($first) {
                    $this->selectedDurations[$plan->id] = (int) $first->duration_months;
                } else {
                    unset($this->selectedDurations[$plan->id]);
                }
            }
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
        $base = $plan ? $this->effectiveBaseFor($plan) : 0;

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
     * پایهٔ محاسبه: مدت اعتبار انتخابی مشتری (یا تک‌مدت پیش‌فرض طرح).
     * خروجی: [base, discount, final] — بدون تخفیف معتبر → discount = 0
     */
    public function priceWithDiscount(SubscriptionPlan $plan): array
    {
        $base = $this->effectiveBaseFor($plan);

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
        $base = $plan ? $this->effectiveBaseFor($plan) : 0;

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

        // ---- مدت اعتبار انتخابی (طرح‌های چندمدتی) ----
        /** @var SubscriptionPlanDuration|null $durationOption */
        $durationOption = $this->selectedDurationFor($plan);

        if ($plan->hasDurations() && !$durationOption) {
            $this->toast('یک مدت اعتبار برای این طرح انتخاب کنید.', 'error');

            return;
        }

        $durationMonths = $durationOption ? (int) $durationOption->duration_months : null;

        // مبلغ مؤثر بر اساس مدت انتخابی (فال‌بک: تک‌مدت پیش‌فرض طرح)
        $effectiveFinal = $durationOption ? $durationOption->final_price : $plan->final_price;

        // ---- کد تخفیف (راستی‌آزمایی مجدد سمت سرور) ----
        $coupon = null;
        $base   = $effectiveFinal;

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
                $effectiveFinal > 0 && $this->gatewayKey !== '' ? $this->gatewayKey : null,
                null,
                $coupon,
                $durationMonths,
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
