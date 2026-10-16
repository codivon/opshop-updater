<?php

namespace App\Livewire\Shop;

use App\Livewire\Concerns\WithToasts;
use App\Models\Customer;
use App\Models\DiscountCode;
use App\Models\Gateway;
use App\Models\Package;
use App\Models\PackageLicense;
use App\Models\PackagePricingPlan;
use App\Models\PackagePurchase;
use App\Models\Subscription;
use App\Services\DiscountService;
use App\Services\LicenseService;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.shop')]
#[Title('جزئیات پکیج')]
class PackageShow extends Component
{
    use WithToasts;

    public Package $package;

    /** کد آپدیت مشتری (شناسه خرید) */
    public string $updateCode = '';

    /** طرح انتخاب‌شده */
    public string $planId = '';

    /** درگاه پرداخت انتخاب‌شده */
    public string $gatewayKey = '';

    /** کد تخفیف تایپ‌شده (پیش از اعمال) */
    public string $discountCode = '';

    /** کد تخفیف اعمال‌شده (پس از اعتبارسنجی سمت سرور — کد نرمال‌شده) */
    public string $appliedCode = '';

    public function mount(string $slug): void
    {
        $package = Package::query()
            ->with(['activePricingPlans', 'images', 'project:id,name', 'categoryModel:id,name'])
            ->where('slug', $slug)
            ->where('status', Package::STATUS_ACTIVE)
            ->first();

        if (!$package) {
            abort(404);
        }

        $this->package = $package;

        // پیش‌انتخاب اولین طرح و اولین درگاه فعال
        $this->planId = (string) ($this->plans->first()?->id ?? '');
        $this->gatewayKey = (string) ($this->gateways->first()?->key ?? '');

        // FIX (خرید پکیج برای مشتری واردشده): کد آپدیت از حساب مشتریِ لاگین‌شده پر می‌شود؛
        // در نبود لاگین، از کد معتبر ذخیره‌شده در session (مکانیزم قدیمی) استفاده می‌کنیم.
        $this->updateCode = auth('customer')->user()?->update_code
            ?? (string) session('shop_update_code', '');
    }

    /* ---------------------------------------------------------------- */
    /*  Computed                                                         */
    /* ---------------------------------------------------------------- */

    #[Computed]
    public function plans()
    {
        return $this->package->activePricingPlans()->get();
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
        return $this->package->activeVersions()->limit(5)->get();
    }

    #[Computed]
    public function images()
    {
        return $this->package->images->where('is_active', true);
    }

    /** طرح‌های اشتراکی که این پکیج را رایگان می‌کنند (پیشنهاد جایگزین خرید تکی) */
    #[Computed]
    public function subscriptionPlansWithThisPackage()
    {
        if ($this->package->is_free) {
            return collect();
        }

        return \App\Models\SubscriptionPlan::query()
            ->active()
            ->whereHas('packages', fn ($q) => $q->where('packages.id', $this->package->id))
            ->with(['packages' => fn ($q) => $q->where('packages.id', $this->package->id)])
            ->orderBy('sort_order')
            ->limit(3)
            ->get();
    }

    /** آیا این پکیج مسیر رایگان دارد؟ */
    #[Computed]
    public function isFreeRoute(): bool
    {
        if ($this->package->is_free) {
            return true;
        }

        return $this->planId !== ''
            && ($plan = $this->plans->firstWhere('id', (int) $this->planId))
            && $plan->final_price <= 0;
    }

    /** مشتریِ جاری: اولویت با مشتریِ لاگین‌شده، سپس کد ذخیره‌شده در session */
    protected function currentCustomer(): ?Customer
    {
        if ($customer = auth('customer')->user()) {
            return $customer;
        }

        $code = trim((string) session('shop_update_code', ''));

        if ($code === '') {
            return null;
        }

        return Customer::query()
            ->where('update_code', $code)
            ->where('status', 'active')
            ->first();
    }

    /* ---------------------------------------------------------------- */
    /*  کد تخفیف                                                          */
    /* ---------------------------------------------------------------- */

    /** مبلغ مبنا برای کد تخفیف: final_price طرح انتخاب‌شده (۰ اگر طرحی انتخاب نشده) */
    protected function discountBaseAmount(): int
    {
        if ($this->package->is_free) {
            return 0;
        }

        $plan = $this->planId !== ''
            ? $this->plans->firstWhere('id', (int) $this->planId)
            : null;

        return $plan ? (int) $plan->final_price : 0;
    }

    /** اعمال کد تخفیف (اعتبارسنجی سمت سرور + پیش‌نمایش) */
    public function applyDiscount(DiscountService $discountService): void
    {
        $base = $this->discountBaseAmount();

        if ($base <= 0) {
            $this->toast(!$this->package->is_free && $this->plans->isNotEmpty()
                ? 'ابتدا طرح قیمت‌گذاری را انتخاب کنید.'
                : 'برای این پکیج امکان استفاده از کد تخفیف وجود ندارد.', 'warning');
            return;
        }

        $result = $discountService->resolve($this->discountCode, DiscountCode::CONTEXT_PACKAGE, $base);

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
     * رزول مجدد کد اعمال‌شده برای نمایش زنده‌ی مبلغ پس از تخفیف (مبنای طرحِ فعلی).
     * اگر کد نامعتبر شده باشد null برمی‌گردد.
     */
    #[Computed]
    public function appliedDiscount(): ?array
    {
        if ($this->appliedCode === '') {
            return null;
        }

        $base = $this->discountBaseAmount();

        if ($base <= 0) {
            return null;
        }

        $result = app(DiscountService::class)->resolve($this->appliedCode, DiscountCode::CONTEXT_PACKAGE, $base);

        return $result['ok'] ? $result : null;
    }

    /** اشتراک فعالِ طرح‌محور مشتریِ session (با plan و packages) یا null */
    #[Computed]
    public function myActiveSubscription()
    {
        $customer = $this->currentCustomer();

        return $customer ? $this->findActivePlanSubscription($customer) : null;
    }

    /** آیا این پکیج با طرحِ اشتراک فعالِ session برای مشتری رایگان است؟ */
    #[Computed]
    public function isCoveredBySubscription(): bool
    {
        // پکیج رایگان از قبل رایگان است؛ نشان اشتراک نمی‌خواهد
        if ($this->package->is_free) {
            return false;
        }

        $plan = $this->myActiveSubscription?->plan;

        if (!$plan) {
            return false;
        }

        return $plan->packages->contains(fn ($pkg) => $pkg->id === $this->package->id);
    }

    /* ---------------------------------------------------------------- */
    /*  Buy                                                              */
    /* ---------------------------------------------------------------- */

    public function buy(PaymentService $paymentService, LicenseService $licenseService, DiscountService $discountService): void
    {
        // ---- کد آپدیت ----
        // مشتری واردشده نیازی به تایپ کد ندارد؛ در غیر این صورت کد اجباری است.
        $customer = auth('customer')->user();

        if (!$customer) {
            $this->validate([
                'updateCode' => ['required', 'string', 'max:64'],
            ], [
                'updateCode.required' => 'کد آپدیت الزامی است.',
            ]);

            $customer = Customer::query()
                ->where('update_code', strtoupper(trim($this->updateCode)))
                ->first();

            if (!$customer) {
                $this->toast('کد آپدیت نامعتبر است.', 'error');
                return;
            }

            if ($customer->status !== 'active') {
                $this->toast('حساب این مشتری غیرفعال است.', 'error');
                return;
            }
        } else {
            // هم‌گام نگه‌داشتن فیلد نمایش‌داده‌شده
            $this->updateCode = $customer->update_code;
        }

        // ---- شاخه اشتراک فعال: این پکیج با طرحِ همین مشتری رایگان است ----
        // (قبل از الزام انتخاب طرح — قواعد planId نباید مسیر رایگان اشتراک را ببندند)
        $subscription = $this->findActivePlanSubscription($customer);
        if (
            $subscription?->plan
            && !$this->package->is_free
            && $subscription->plan->packages->contains(fn ($pkg) => $pkg->id === $this->package->id)
        ) {
            $this->fulfillWithSubscription($customer, $subscription);
            return;
        }

        // ---- طرح ----
        // (validate با آرایهٔ خالی در Livewire 3 به دنبال rules() می‌گردد → فقط وقتی قاعده داریم صدا می‌زنیم)
        if (!$this->package->is_free && $this->plans->isNotEmpty()) {
            $this->validate(['planId' => ['required']], [
                'planId.required' => 'لطفاً طرح قیمت‌گذاری را انتخاب کنید.',
            ]);
        }

        /** @var \App\Models\PackagePricingPlan|null $plan */
        $plan = null;
        if ($this->planId !== '') {
            $plan = $this->package->activePricingPlans()->where('id', (int) $this->planId)->first();

            if (!$plan) {
                $this->toast('طرح قیمت‌گذاری انتخاب‌شده معتبر نیست.', 'error');
                return;
            }
        }

        if (!$this->package->is_free && $this->plans->isNotEmpty() && !$plan) {
            $this->toast('لطفاً طرح قیمت‌گذاری را انتخاب کنید.', 'error');
            return;
        }

        // جلوگیری از خرید مجدد طرح یک‌بار مصرف (مثل جریان API)
        if ($plan && $plan->is_one_time && $plan->hasCustomerUsed($customer->id)) {
            $this->toast('شما قبلاً از این طرح استفاده کرده‌اید. این طرح فقط یک‌بار قابل خریداری است. لطفاً طرح دیگری انتخاب کنید.', 'error');
            return;
        }

        // ---- نسخه ----
        $latestVersion = $this->package->latestVersion()->first();

        if (!$latestVersion) {
            $this->toast('نسخه فعالی برای این پکیج وجود ندارد.', 'error');
            return;
        }

        // ---- کد تخفیف (راستی‌آزمایی مجدد سمت سرور) ----
        // فقط وقتی مبلغ مبنا > ۰ کد اعتبارسنجی می‌شود؛ در خرید رایگانِ بدون مبلغ،
        // کدِ تایپ‌شده‌ی نیمه‌کاره نباید جلوی دریافت رایگان را بگیرد.
        $couponBase = $this->discountBaseAmount();

        if ($couponBase > 0) {
            $couponResult = $discountService->resolve(
                $this->appliedCode !== '' ? $this->appliedCode : $this->discountCode,
                DiscountCode::CONTEXT_PACKAGE,
                $couponBase
            );

            if (!$couponResult['ok']) {
                $this->appliedCode = '';
                $this->toast($couponResult['message'], 'error');
                return;
            }

            /** @var \App\Models\DiscountCode|null $coupon */
            $coupon         = $couponResult['code'];
            $couponDiscount = $couponResult['discount'];
            $finalPrice     = $couponResult['final'];
        } else {
            $coupon         = null;
            $couponDiscount = 0;
            $finalPrice     = 0;
        }

        // ---- مسیر رایگان ----
        // (پکیج رایگان، طرح صفر تومانی، یا کد تخفیفِ پوشش‌دهنده‌ی کامل مبلغ)
        if ($this->package->is_free || ($plan && $plan->final_price <= 0) || $finalPrice <= 0) {
            // پکیج رایگان بدون طرح → لایسنس نامحدود
            if (!$plan) {
                $plan = new PackagePricingPlan([
                    'duration_months' => 0,
                    'is_one_time'     => false,
                ]);
            }

            $purchase = PackagePurchase::create([
                'package_id'       => $this->package->id,
                'version_id'       => $latestVersion->id,
                'pricing_plan_id'  => $plan->exists ? $plan->id : null,
                'customer_id'      => $customer->id,
                'discount_code_id' => $coupon?->id,
                'original_amount'  => $couponBase,
                'amount'           => 0,
                'discount_amount'  => $couponDiscount,
                'status'           => PackagePurchase::STATUS_PAID,
                'paid_at'          => now(),
            ]);

            $licenseService->issueOrRenew($purchase, $plan);

            // مسیر رایگان از markAsPaid عبور نمی‌کند → مصرف شمارنده کد اینجا انجام می‌شود
            $coupon?->recordUsage();

            // به‌جای flash از put استفاده می‌کنیم تا پس از رفت‌وبرگشت از درگاه هم
            // کد آپدیت در صفحه پکیج باقی بماند (flash با اولین request بعدی مصرف می‌شود).
            session()->put('shop_update_code', trim($this->updateCode));

            $this->toast('پکیج رایگان با موفقیت دریافت شد؛ لایسنس صادر شد.');
            $this->redirect(route('payment.result', $purchase), navigate: true);

            return;
        }

        // ---- مسیر پرداخت ----
        if ($this->gatewayKey === '' || !$this->gateways->firstWhere('key', $this->gatewayKey)) {
            $this->toast('درگاه پرداخت فعالی تنظیم نشده است.', 'error');
            return;
        }

        $purchase = PackagePurchase::create([
            'package_id'       => $this->package->id,
            'version_id'       => $latestVersion->id,
            'pricing_plan_id'  => $plan->id,
            'customer_id'      => $customer->id,
            'discount_code_id' => $coupon?->id,
            'callback_url'     => route('payment.callback'),
            'original_amount'  => (int) $plan->final_price,
            'amount'           => $finalPrice,
            'discount_amount'  => $couponDiscount,
            'gateway'          => $this->gatewayKey,
            'status'           => PackagePurchase::STATUS_PENDING,
        ]);

        session()->put('shop_update_code', trim($this->updateCode));

        try {
            $payment = $paymentService->createPayment($purchase, $this->gatewayKey);
        } catch (\Throwable $e) {
            $this->toast($e->getMessage(), 'error');
            return;
        }

        $this->redirect($payment['payment_url'], navigate: false);
    }

    /* ---------------------------------------------------------------- */
    /*  دریافت رایگان با اشتراک فعال (بدون درگاه)                        */
    /* ---------------------------------------------------------------- */

    /**
     * دریافت رایگان این پکیج برای مشتریِ دارای اشتراک فعالِ طرح‌محور.
     *
     * - اگر لایسنس فعال دارد → فقط توست (idempotent، بدون تغییر DB).
     * - وگرنه خرید رایگان (amount=0, paid) + صدور/تمدید لایسنس با مدتِ pivot
     *   (کپی الگوی SubscriptionService::grantPackageAccess — عمداً مستقل نوشته
     *   شده تا به آن سرویس وابسته نباشد) + ریدایرکت به صفحه نتیجه.
     */
    protected function fulfillWithSubscription(Customer $customer, Subscription $subscription): void
    {
        // idempotent — لایسنس فعال موجود؟ هیچ تغییری در DB ایجاد نمی‌شود
        $activeLicense = PackageLicense::query()
            ->where('customer_id', $customer->id)
            ->where('package_id', $this->package->id)
            ->where('status', PackageLicense::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('id')
            ->first();

        if ($activeLicense) {
            $this->toast('این پکیج از قبل با اشتراک شما فعال است.');
            return;
        }

        // ---- نسخه ----
        $latestVersion = $this->package->latestVersion()->first();

        if (!$latestVersion) {
            $this->toast('نسخه فعالی برای این پکیج وجود ندارد.', 'error');
            return;
        }

        $plan = $subscription->plan;
        $freeMonths = (int) ($plan->packages->firstWhere('id', $this->package->id)?->pivot->free_months ?? 1);

        // ---- خرید رایگان (بدون درگاه) ----
        $purchase = PackagePurchase::create([
            'package_id'      => $this->package->id,
            'version_id'      => $latestVersion->id,
            'pricing_plan_id' => null,
            'customer_id'     => $customer->id,
            'amount'          => 0,
            'status'          => PackagePurchase::STATUS_PAID,
            'paid_at'         => now(),
        ]);

        // ---- صدور/تمدید لایسنس رایگان با مدتِ طرح (pivot free_months) ----
        $license = DB::transaction(function () use ($customer, $freeMonths, $plan) {
            $existing = PackageLicense::query()
                ->where('package_id', $this->package->id)
                ->where('customer_id', $customer->id)
                ->whereIn('status', [PackageLicense::STATUS_ACTIVE, PackageLicense::STATUS_EXPIRED])
                ->latest('id')
                ->first();

            $startsAt = now();
            $baseDate = ($existing && $existing->isActive() && $existing->expires_at)
                ? Carbon::parse($existing->expires_at)
                : now();
            $expiresAt = $freeMonths > 0 ? (clone $baseDate)->addMonths($freeMonths) : null;

            $license = PackageLicense::create([
                'license_key'     => PackageLicense::generateKey(),
                'package_id'      => $this->package->id,
                'customer_id'     => $customer->id,
                'renewed_from'    => $existing?->id,
                'status'          => PackageLicense::STATUS_ACTIVE,
                'starts_at'       => $startsAt,
                'expires_at'      => $expiresAt,
                'duration_months' => $freeMonths,
                'notes'           => 'دسترسی رایگان از طریق اشتراک «' . $plan->name . '».',
            ]);

            if ($existing) {
                $existing->update(['status' => PackageLicense::STATUS_REVOKED]);
            }

            return $license;
        });

        $license->update(['purchase_id' => $purchase->id]);

        session()->put('shop_update_code', trim($this->updateCode));

        $this->toast('پکیج با اشتراک رایگان فعال شد.');
        $this->redirect(route('payment.result', $purchase), navigate: true);
    }

    /** اولین اشتراک فعالِ طرح‌محور مشتری (با plan.packages eager) */
    protected function findActivePlanSubscription(Customer $customer): ?Subscription
    {
        return $customer->subscriptions()
            ->whereNotNull('subscription_plan_id')
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->with('plan.packages')
            ->orderByDesc('id')
            ->first();
    }

    public function render()
    {
        return view('livewire.shop.package-show');
    }
}
