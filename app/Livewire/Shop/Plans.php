<?php

namespace App\Livewire\Shop;

use App\Livewire\Concerns\WithToasts;
use App\Models\Customer;
use App\Models\Gateway;
use App\Models\Package;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanDuration;
use App\Services\SubscriptionService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;

#[Layout('components.layouts.shop')]
#[Title('طرح‌های اشتراک')]
class Plans extends Component
{
    use WithToasts;

    /** آیا مودال خرید باز است؟ */
    public bool $showCheckout = false;

    /** شناسه طرح انتخاب‌شده برای خرید */
    public ?int $checkoutPlanId = null;

    /** کد آپدیت مشتری (شناسه خرید) */
    public string $updateCode = '';

    /** کلید درگاه پرداخت انتخاب‌شده */
    public string $gateway = '';

    /** مدت اعتبار انتخابی هر طرح: [plan_id => duration_months] */
    public array $selectedDurations = [];

    /** طرح‌هایی که کاربر فهرست کامل پکیج‌هایشان را باز کرده است */
    public array $expandedPlans = [];

    public function mount(): void
    {
        // پیش‌انتخاب اولین درگاه فعال (مثل صفحه پکیج)
        $this->gateway = (string) ($this->gateways->first()?->key ?? '');

        // کد آپدیت معتبر قبلی (session) برای راحتی مشتری
        $this->updateCode = (string) (session('last_update_code') ?: session('shop_update_code', ''));
    }

    /* ---------------------------------------------------------------- */
    /*  Computed                                                         */
    /* ---------------------------------------------------------------- */

    /** طرح‌های فعالِ دارای حداقل یک پکیج فعال، مرتب بر اساس ترتیب نمایش */
    #[Computed]
    public function plans()
    {
        return SubscriptionPlan::query()
            ->with([
                'packages' => fn ($q) => $q
                    ->with('latestVersion:id,package_id,version')
                    ->where('status', Package::STATUS_ACTIVE),
                'durations',
                // پیکربندی اختصاصی هر مدت — پکیج‌های فعال (Task 63)
                'durations.packages' => fn ($q) => $q->where('status', Package::STATUS_ACTIVE),
            ])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (SubscriptionPlan $plan) => $plan->packages->isNotEmpty())
            ->values();
    }

    /** گزینه‌های مدت فعال یک طرح */
    public function durationOptionsFor(SubscriptionPlan $plan)
    {
        return $plan->durationOptions();
    }

    /** مدت انتخاب‌شدهٔ یک طرح (یا null = مدت پیش‌فرض طرح) */
    public function selectedDurationFor(SubscriptionPlan $plan): ?SubscriptionPlanDuration
    {
        $months = (int) ($this->selectedDurations[$plan->id] ?? 0);

        return $months > 0 || $months === 0 ? $plan->durationOption($months) : null;
    }

    /** مبلغ نمایشی کارت: مدت انتخابی یا پیش‌فرض طرح */
    public function displayOptionFor(SubscriptionPlan $plan): ?SubscriptionPlanDuration
    {
        return $this->selectedDurationFor($plan);
    }

    /** آمار کلی صفحه (ردیف آمار زیر هیرو) */
    #[Computed]
    public function stats(): array
    {
        $plans = $this->plans;

        return [
            'plans'    => $plans->count(),
            'packages' => $plans->flatMap->packages->unique('id')->count(),
            'free'     => $plans->filter(fn (SubscriptionPlan $plan) => $plan->final_price <= 0)->count(),
        ];
    }

    /** درگاه‌های پرداخت فعال (همان ساختار صفحه پکیج) */
    #[Computed]
    public function gateways()
    {
        return Gateway::query()
            ->active()
            ->orderBy('ordering')
            ->orderBy('name')
            ->get();
    }

    /* ---------------------------------------------------------------- */
    /*  Actions                                                          */
    /* ---------------------------------------------------------------- */

    public function openCheckout(int $planId): void
    {
        /** @var SubscriptionPlan|null $plan */
        $plan = $this->plans->firstWhere('id', $planId);

        if (!$plan) {
            return;
        }

        // اگر مدتی انتخاب نشده، اولین گزینهٔ مدت فعال طرح پیش‌انتخاب می‌شود
        if (!isset($this->selectedDurations[$planId]) && $plan->hasDurations()) {
            $first = $plan->durationOptions()->first();

            if ($first) {
                $this->selectedDurations[$planId] = (int) $first->duration_months;
            }
        }

        $this->checkoutPlanId = $planId;
        $this->showCheckout = true;
    }

    /** انتخاب مدت اعتبار از کارت/مودال */
    public function selectDuration(int $planId, int $months): void
    {
        /** @var SubscriptionPlan|null $plan */
        $plan = $this->plans->firstWhere('id', $planId);

        if (!$plan || !$plan->durationOption($months)) {
            return; // مدت نامعتبر نادیده گرفته می‌شود
        }

        $this->selectedDurations[$planId] = $months;
    }

    public function toggleExpand(int $planId): void
    {
        $key = array_search($planId, $this->expandedPlans, true);

        if ($key !== false) {
            unset($this->expandedPlans[$key]);
            $this->expandedPlans = array_values($this->expandedPlans);
        } else {
            $this->expandedPlans[] = $planId;
        }
    }

    /**
     * ثبت درخواست خرید طرح (پولی → هدایت به درگاه / رایگان → در انتظار تأیید مدیر)
     */
    public function buy(int $planId): void
    {
        $this->validate([
            'updateCode' => ['required', 'string', 'max:64'],
        ], [
            'updateCode.required' => 'کد آپدیت الزامی است.',
        ]);

        // ---- مشتری ----
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

        // ---- طرح ----
        /** @var SubscriptionPlan|null $plan */
        $plan = $this->plans->firstWhere('id', $planId);

        if (!$plan) {
            $this->toast('طرح انتخاب‌شده معتبر نیست.', 'error');
            return;
        }

        // ---- مدت اعتبار انتخابی ----
        /** @var SubscriptionPlanDuration|null $durationOption */
        $durationOption = $this->selectedDurationFor($plan);

        if ($plan->hasDurations() && !$durationOption) {
            $this->toast('یک مدت اعتبار برای این طرح انتخاب کنید.', 'error');
            return;
        }

        $durationMonths = $durationOption ? (int) $durationOption->duration_months : null;

        // مبلغ مؤثر بر اساس مدت انتخابی (فال‌بک: تک‌مدت پیش‌فرض طرح)
        $effectiveFinal = $durationOption ? $durationOption->final_price : $plan->final_price;

        // طرح پولی → انتخاب درگاه الزامی است
        if ($effectiveFinal > 0 && ($this->gateway === '' || !$this->gateways->firstWhere('key', $this->gateway))) {
            $this->toast('درگاه پرداخت فعالی تنظیم نشده است.', 'error');
            return;
        }

        // ---- ثبت سفارش (پیام‌های خطای سرویس فارسی‌اند) ----
        try {
            $result = app(SubscriptionService::class)->purchase(
                $customer,
                $plan,
                $effectiveFinal > 0 && $this->gateway !== '' ? $this->gateway : null,
                null,
                null,
                $durationMonths,
            );
        } catch (RuntimeException $e) {
            $this->toast($e->getMessage(), 'error');
            return;
        } catch (\Throwable $e) {
            $this->toast($e->getMessage(), 'error');
            return;
        }

        // کد آپدیت را نگه می‌داریم تا بعد از رفت‌وبرگشت از درگاه هم در دسترس باشد
        $this->persistUpdateCode();

        /** @var \App\Models\SubscriptionOrder $order */
        $order = $result['order'];

        // ---- طرح رایگان/کوپنِ کامل: مستقیم به صفحه نتیجه سفارش (در انتظار تأیید مدیر) ----
        if (($result['payment_url'] ?? null) === null) {
            $this->showCheckout = false;
            $this->redirect(route('subscription.result', $order), navigate: true);

            return;
        }

        // ---- طرح پولی: هدایت به درگاه پرداخت (خروجی external → بدون wire:navigate) ----
        $this->showCheckout = false;
        $this->redirect($result['payment_url'], navigate: false);
    }

    /* ---------------------------------------------------------------- */
    /*  Internals                                                        */
    /* ---------------------------------------------------------------- */

    /**
     * کد آپدیت را در session می‌گذارد؛ علاوه بر کلید اختصاصی این صفحه
     * («last_update_code»)، کلید مشترک صفحه پکیج («shop_update_code») هم
     * به‌روز می‌شود تا رفت‌وبرگشت بین فروشگاه و درگاه کد را از دست ندهد.
     */
    private function persistUpdateCode(): void
    {
        session()->put('last_update_code', trim($this->updateCode));
        session()->put('shop_update_code', trim($this->updateCode));
    }

    public function render()
    {
        return view('livewire.shop.plans');
    }
}
