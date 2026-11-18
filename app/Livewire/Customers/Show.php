<?php

namespace App\Livewire\Customers;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Livewire\Concerns\WithToasts;
use App\Models\Customer;
use App\Models\Package;
use App\Models\PackageLicense;
use App\Models\PackagePurchase;
use App\Models\Subscription;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanDuration;
use App\Models\TemplateLicense;
use App\Models\TemplatePurchase;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Task 88 — پروندهٔ کامل مشتری در پنل مدیریت.
 *
 * همهٔ اطلاعات مشتری + جمع خریدها + لایسنس‌ها + خریدهای تفکیک‌شده (پکیج/قالب/اشتراک)
 * با لیست و نمودار + مدیریت کامل:
 *   - اشتراک: افزودن (دستی یا از طرح) / ویرایش / افزایش و کاهش تاریخ انضا / حذف
 *   - لایسنس پکیج: افزودن / ویرایش تاریخ انضا / افزایش و کاهش ماه / ابطال و فعال‌سازی / حذف
 *   - لایسنس قالب: ابطال و فعال‌سازی / حذف
 *   - سفارش‌های در انتظار: تأیید / رد
 */
#[Layout('components.layouts.app')]
#[Title('پروندهٔ مشتری')]
class Show extends Component
{
    use AuthorizesAdmin;
    use WithToasts;

    public Customer $customer;

    /* ---------- اشتراک: فرم افزودن/ویرایش ---------- */

    public bool $showSubscriptionModal = false;

    public ?int $subscriptionId = null;

    /** manual = اشتراک دستی | plan = صدور از طرح اشتراک */
    public string $subscriptionMode = 'manual';

    /** @var array<string, mixed> */
    public array $subscriptionForm = [];

    /** در حالت طرح: شناسهٔ گزینهٔ مدت انتخابی (subscription_plan_durations.id) — «0» = مدت پیش‌فرض طرح */
    public string $subscriptionDurationId = '0';

    /** در حالت طرح: صدور لایسنس پکیج‌های همراه طرح */
    public bool $grantPlanPackages = true;

    public ?int $deleteSubscriptionId = null;

    /* ---------- لایسنس پکیج: فرم افزودن/ویرایش ---------- */

    public bool $showLicenseModal = false;

    public ?int $licenseId = null;

    /** @var array<string, mixed> */
    public array $licenseForm = [];

    public ?int $deleteLicenseId = null;

    /* ---------- لایسنس قالب ---------- */

    public ?int $deleteTemplateLicenseId = null;

    /* ---------- سفارش اشتراک ---------- */

    public bool $showRejectModal = false;

    public ?int $rejectOrderId = null;

    public string $rejectReason = '';

    public function mount(Customer $customer): void
    {
        $this->customer = $customer;
    }

    /* ================================================================ */
    /*  داده‌های نمایش */
    /* ================================================================ */

    /** خریدهای پکیج (همهٔ وضعیت‌ها — جدیدترین اول) */
    #[Computed]
    public function packagePurchases()
    {
        return PackagePurchase::query()
            ->where('customer_id', $this->customer->id)
            ->with(['package:id,name,slug', 'pricingPlan:id,name,duration_months', 'license:id,license_key,status'])
            ->latest('id')
            ->limit(100)
            ->get();
    }

    /** لایسنس‌های پکیج */
    #[Computed]
    public function packageLicenses()
    {
        return PackageLicense::query()
            ->where('customer_id', $this->customer->id)
            ->with('package:id,name,slug')
            ->withCount('renewals')
            ->latest('id')
            ->limit(100)
            ->get();
    }

    /** خریدهای قالب */
    #[Computed]
    public function templatePurchases()
    {
        return TemplatePurchase::query()
            ->where('customer_id', $this->customer->id)
            ->with(['template:id,name,slug', 'license:id,license_key,status'])
            ->latest('id')
            ->limit(100)
            ->get();
    }

    /** لایسنس‌های قالب */
    #[Computed]
    public function templateLicenses()
    {
        return TemplateLicense::query()
            ->where('customer_id', $this->customer->id)
            ->with('template:id,name,slug')
            ->latest('id')
            ->limit(100)
            ->get();
    }

    /** اشتراک‌ها (طرح‌محور + دستی) */
    #[Computed]
    public function subscriptions()
    {
        return Subscription::query()
            ->where('customer_id', $this->customer->id)
            ->with(['plan:id,name,slug', 'project:id,name'])
            ->latest('id')
            ->limit(100)
            ->get();
    }

    /** سفارش‌های طرح اشتراک */
    #[Computed]
    public function subscriptionOrders()
    {
        return SubscriptionOrder::query()
            ->where('customer_id', $this->customer->id)
            ->with('plan:id,name,slug')
            ->latest('id')
            ->limit(100)
            ->get();
    }

    /**
     * KPIهای پرونده — حسابداری ضد دوبارشماری (هم‌راستا با صفحهٔ درآمد):
     * اشتراک = SubscriptionOrder(paid) + Subscription(دستیِ paid بدون order).
     */
    #[Computed]
    public function stats(): array
    {
        $packagePaid = PackagePurchase::query()
            ->where('customer_id', $this->customer->id)
            ->where('status', PackagePurchase::STATUS_PAID)
            ->selectRaw('COUNT(*) as c, COALESCE(SUM(amount), 0) as s')
            ->first();

        $templatePaid = TemplatePurchase::query()
            ->where('customer_id', $this->customer->id)
            ->where('status', TemplatePurchase::STATUS_PAID)
            ->selectRaw('COUNT(*) as c, COALESCE(SUM(amount), 0) as s')
            ->first();

        $orderPaid = SubscriptionOrder::query()
            ->where('customer_id', $this->customer->id)
            ->where('status', SubscriptionOrder::STATUS_PAID)
            ->selectRaw('COUNT(*) as c, COALESCE(SUM(final_amount), 0) as s')
            ->first();

        $manualPaid = Subscription::query()
            ->where('customer_id', $this->customer->id)
            ->where('payment_status', 'paid')
            ->whereNull('subscription_order_id')
            ->selectRaw('COUNT(*) as c, COALESCE(SUM(final_amount), 0) as s')
            ->first();

        $licenses = PackageLicense::query()
            ->where('customer_id', $this->customer->id)
            ->selectRaw("COUNT(*) as total, COALESCE(SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END), 0) as active")
            ->first();

        $templateLicenses = TemplateLicense::query()
            ->where('customer_id', $this->customer->id)
            ->selectRaw("COUNT(*) as total, COALESCE(SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END), 0) as active")
            ->first();

        $subs = Subscription::query()
            ->where('customer_id', $this->customer->id)
            ->selectRaw("COUNT(*) as total,
                COALESCE(SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END), 0) as active,
                COALESCE(SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END), 0) as reserved")
            ->first();

        $pendingOrders = SubscriptionOrder::query()
            ->where('customer_id', $this->customer->id)
            ->where('admin_status', SubscriptionOrder::ADMIN_STATUS_PENDING)
            ->count();

        return [
            'package_revenue' => (int) $packagePaid->s,
            'package_count' => (int) $packagePaid->c,
            'template_revenue' => (int) $templatePaid->s,
            'template_count' => (int) $templatePaid->c,
            'order_revenue' => (int) $orderPaid->s,
            'order_count' => (int) $orderPaid->c,
            'manual_revenue' => (int) $manualPaid->s,
            'manual_count' => (int) $manualPaid->c,
            'total_revenue' => (int) $packagePaid->s + (int) $templatePaid->s + (int) $orderPaid->s + (int) $manualPaid->s,
            'total_purchases' => (int) $packagePaid->c + (int) $templatePaid->c + (int) $orderPaid->c + (int) $manualPaid->c,
            'licenses_total' => (int) $licenses->total,
            'licenses_active' => (int) $licenses->active,
            'template_licenses_total' => (int) $templateLicenses->total,
            'template_licenses_active' => (int) $templateLicenses->active,
            'subs_total' => (int) $subs->total,
            'subs_active' => (int) $subs->active,
            'subs_reserved' => (int) $subs->reserved,
            'pending_orders' => (int) $pendingOrders,
        ];
    }

    /** نمودار تفکیک مبلغ خریدها بر اساس نوع */
    #[Computed]
    public function spendByType(): array
    {
        $s = $this->stats;

        return [
            'title' => 'تفکیک مبلغ خریدها بر اساس نوع',
            'series' => [
                ['label' => 'پکیج‌ها', 'value' => $s['package_revenue']],
                ['label' => 'قالب‌ها', 'value' => $s['template_revenue']],
                ['label' => 'اشتراک‌ها', 'value' => $s['order_revenue'] + $s['manual_revenue']],
            ],
        ];
    }

    /** نمودار روند خرید ۱۲ ماه اخیر (همهٔ منابع) */
    #[Computed]
    public function monthlyTrend(): array
    {
        $start = now()->subMonths(11)->startOfMonth();

        $buckets = [];
        for ($i = 0; $i < 12; $i++) {
            $key = $start->copy()->addMonths($i)->format('Y-m');
            $buckets[$key] = ['value' => 0, 'count' => 0];
        }

        $add = function (string $date, int $amount) use (&$buckets): void {
            $key = Carbon::parse($date)->format('Y-m');

            if (isset($buckets[$key])) {
                $buckets[$key]['value'] += $amount;
                $buckets[$key]['count']++;
            }
        };

        PackagePurchase::query()
            ->where('customer_id', $this->customer->id)
            ->where('status', PackagePurchase::STATUS_PAID)
            ->where('paid_at', '>=', $start)
            ->get(['amount', 'paid_at'])
            ->each(fn ($r) => $add($r->paid_at, (int) $r->amount));

        TemplatePurchase::query()
            ->where('customer_id', $this->customer->id)
            ->where('status', TemplatePurchase::STATUS_PAID)
            ->where('paid_at', '>=', $start)
            ->get(['amount', 'paid_at'])
            ->each(fn ($r) => $add($r->paid_at, (int) $r->amount));

        SubscriptionOrder::query()
            ->where('customer_id', $this->customer->id)
            ->where('status', SubscriptionOrder::STATUS_PAID)
            ->where('paid_at', '>=', $start)
            ->get(['final_amount', 'paid_at'])
            ->each(fn ($r) => $add($r->paid_at, (int) $r->final_amount));

        Subscription::query()
            ->where('customer_id', $this->customer->id)
            ->where('payment_status', 'paid')
            ->whereNull('subscription_order_id')
            ->where('start_date', '>=', $start->toDateString())
            ->get(['final_amount', 'start_date'])
            ->each(fn ($r) => $add($r->start_date, (int) $r->final_amount));

        $series = [];

        foreach ($buckets as $key => $row) {
            $series[] = [
                'label' => verta_date($key.'-15', 'Y/m'),
                'value' => $row['value'],
                'secondary' => $row['count'],
            ];
        }

        return ['title' => 'روند خرید ۱۲ ماه اخیر', 'series' => $series];
    }

    /* ================================================================ */
    /*  گزینه‌های فرم‌ها */
    /* ================================================================ */

    #[Computed]
    public function packageOptions()
    {
        return Package::query()->where('status', Package::STATUS_ACTIVE)->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function planOptions()
    {
        return SubscriptionPlan::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']);
    }

    /** گزینه‌های مدت اعتبار طرح انتخابی در فرم اشتراک (حالت plan) */
    #[Computed]
    public function durationOptions(): array
    {
        $planId = (int) ($this->subscriptionForm['subscription_plan_id'] ?? 0);

        if (! $planId) {
            return [];
        }

        $plan = SubscriptionPlan::find($planId);

        if (! $plan) {
            return [];
        }

        $options = [];

        foreach ($plan->durationOptions() as $d) {
            $options[(string) $d->id] = $d->duration_label.' — '.money($d->final_price, false).' تومان';
        }

        if ($options !== []) {
            return $options;
        }

        // تک‌مدتی طرح
        return ['0' => $plan->duration_label.' — '.money($plan->final_price, false).' تومان'];
    }

    /* ================================================================ */
    /*  اشتراک‌ها: افزودن / ویرایش / حذف / تغییر انضا */
    /* ================================================================ */

    public function setSubscriptionMode(string $mode): void
    {
        $this->subscriptionMode = $mode === 'plan' ? 'plan' : 'manual';
        $this->subscriptionDurationId = '0';
    }

    public function openSubscriptionCreate(): void
    {
        $this->resetValidation();
        $this->subscriptionId = null;
        $this->subscriptionMode = 'manual';
        $this->subscriptionDurationId = '0';
        $this->grantPlanPackages = true;
        $this->subscriptionForm = [
            'subscription_plan_id' => '',
            'price' => '',
            'discount' => 0,
            'payment_status' => 'paid',
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'description' => '',
        ];
        $this->showSubscriptionModal = true;
    }

    public function openSubscriptionEdit(int $id): void
    {
        $subscription = Subscription::findOrFail($id);

        if ($subscription->customer_id !== $this->customer->id) {
            abort(404);
        }

        if ($subscription->isReserved()) {
            $this->toast('اشتراک رزروشده قابل ویرایش نیست؛ پس از پایان دورهٔ فعلی به‌صورت خودکار فعال می‌شود.', 'warning');

            return;
        }

        $this->resetValidation();
        $this->subscriptionId = $subscription->id;
        $this->subscriptionMode = 'manual';
        $this->subscriptionForm = [
            'subscription_plan_id' => $subscription->subscription_plan_id,
            'price' => $subscription->price !== null ? (string) $subscription->price : '',
            'discount' => $subscription->discount !== null ? (string) $subscription->discount : 0,
            'payment_status' => $subscription->payment_status ?? 'paid',
            'status' => $subscription->status,
            'start_date' => $subscription->start_date?->format('Y-m-d') ?? now()->toDateString(),
            'end_date' => ($subscription->end_date ?? $subscription->expires_at)?->format('Y-m-d') ?? now()->addMonth()->toDateString(),
            'description' => $subscription->description ?? '',
        ];
        $this->showSubscriptionModal = true;
    }

    public function saveSubscription(): void
    {
        $isEdit = $this->subscriptionId !== null;

        $rules = [
            'subscriptionForm.price' => ['nullable', 'numeric', 'min:0'],
            'subscriptionForm.discount' => ['nullable', 'numeric', 'min:0'],
            'subscriptionForm.payment_status' => ['required', 'in:pending,paid,failed'],
            'subscriptionForm.status' => ['required', 'in:active,expired,suspended'],
            'subscriptionForm.start_date' => ['required', 'date'],
            'subscriptionForm.end_date' => ['required', 'date', 'after_or_equal:subscriptionForm.start_date'],
            'subscriptionForm.description' => ['nullable', 'string', 'max:1000'],
        ];

        if (! $isEdit && $this->subscriptionMode === 'plan') {
            $rules['subscriptionForm.subscription_plan_id'] = ['required', 'integer', 'exists:subscription_plans,id'];
        }

        $messages = [
            'subscriptionForm.subscription_plan_id.required' => 'انتخاب طرح الزامی است.',
            'subscriptionForm.end_date.after_or_equal' => 'تاریخ پایان باید بعد از تاریخ شروع باشد.',
            'subscriptionForm.status.required' => 'وضعیت اشتراک الزامی است.',
            'subscriptionForm.payment_status.required' => 'وضعیت پرداخت الزامی است.',
        ];

        $this->validate($rules, $messages);

        $data = $this->subscriptionForm;

        if ($isEdit) {
            $subscription = Subscription::findOrFail($this->subscriptionId);

            if ($subscription->customer_id !== $this->customer->id) {
                abort(404);
            }

            $subscription->update([
                'price' => $data['price'] !== '' && $data['price'] !== null ? (float) $data['price'] : $subscription->price,
                'discount' => $data['discount'] !== '' && $data['discount'] !== null ? (float) $data['discount'] : 0,
                'payment_status' => $data['payment_status'],
                'status' => $data['status'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'expires_at' => Carbon::parse($data['end_date'])->endOfDay(),
                'description' => $data['description'] ?: null,
            ]);

            $this->toast('اشتراک با موفقیت به‌روزرسانی شد.');
        } else {
            if ($this->subscriptionMode === 'plan') {
                $this->createPlanSubscription();

                return;
            }

            $expiresAt = Carbon::parse($data['end_date'])->endOfDay();

            Subscription::create([
                'customer_id' => $this->customer->id,
                'price' => $data['price'] !== '' && $data['price'] !== null ? (float) $data['price'] : 0,
                'discount' => $data['discount'] !== '' && $data['discount'] !== null ? (float) $data['discount'] : 0,
                'payment_status' => $data['payment_status'],
                'status' => $data['status'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'expires_at' => $expiresAt,
                'description' => $data['description'] ?: null,
            ]);

            $this->toast('اشتراک دستی جدید برای مشتری ثبت شد.');
        }

        $this->showSubscriptionModal = false;
    }

    /** صدور اشتراک از طرح (+ لایسنس پکیج‌های همراه) — Task 88 */
    private function createPlanSubscription(): void
    {
        $plan = SubscriptionPlan::findOrFail((int) $this->subscriptionForm['subscription_plan_id']);

        /** @var SubscriptionPlanDuration|null $duration */
        $duration = ((int) $this->subscriptionDurationId) > 0
            ? $plan->durations()->where('id', (int) $this->subscriptionDurationId)->first()
            : null;

        $months = $duration ? (int) $duration->duration_months : (int) $plan->duration_months;
        $price = (int) ($duration ? $duration->final_price : $plan->final_price);

        $subscription = Subscription::create([
            'customer_id' => $this->customer->id,
            'subscription_plan_id' => $plan->id,
            'price' => $price,
            'discount' => 0,
            'payment_status' => 'paid',
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'end_date' => $months > 0 ? now()->addMonths($months)->toDateString() : null,
            'expires_at' => $months > 0 ? now()->addMonths($months)->endOfDay() : null,
            'description' => 'صدور دستی از طرح «'.$plan->name.'» توسط مدیر',
        ]);

        // صدور لایسنس پکیج‌های همراه طرح (مطابق جریان تأیید سفارش)
        if ($this->grantPlanPackages) {
            $packages = $plan->packagesForDuration($duration);
            $service = app(SubscriptionService::class);

            foreach ($packages as $package) {
                $freeMonths = (int) ($package->pivot->free_months ?? 1);
                $service->grantFreeAccess($this->customer, $package, $freeMonths, 'صدور دستی از طرح «'.$plan->name.'»');
            }
        }

        $this->toast('اشتراک «'.$plan->name.'» برای مشتری صادر شد'.($this->grantPlanPackages && $packages->isNotEmpty() ? ' و لایسنس پکیج‌های همراه صادر شد.' : '.'));

        $this->showSubscriptionModal = false;
    }

    /** تغییر سریع تاریخ انضای اشتراک: +N ماه (تمدید) یا -N ماه */
    public function adjustSubscriptionExpiry(int $id, int $months): void
    {
        $subscription = Subscription::findOrFail($id);

        if ($subscription->customer_id !== $this->customer->id) {
            abort(404);
        }

        if ($subscription->isReserved()) {
            $this->toast('اشتراک رزروشده تاریخ انضا ندارد تا پایان دورهٔ فعلی.', 'warning');

            return;
        }

        $base = ($subscription->expires_at && $subscription->expires_at->isFuture())
            ? $subscription->expires_at->copy()
            : now();

        $new = $months >= 0
            ? $base->copy()->addMonths($months)
            : $base->copy()->subMonths(abs($months));

        if ($new->isPast()) {
            $this->toast('تاریخ انضای جدید در گذشته است؛ از مودال ویرایش برای تغییر دستی استفاده کنید.', 'error');

            return;
        }

        $subscription->update([
            'end_date' => $new->toDateString(),
            'expires_at' => $new,
        ]);

        $this->toast(($months >= 0 ? 'اعتبار اشتراک '.fa_num($months).' ماه افزایش یافت (تا '.verta_date($new).').' : 'اعتبار اشتراک '.fa_num(abs($months)).' ماه کاهش یافت (تا '.verta_date($new).').'));
    }

    public function confirmDeleteSubscription(int $id): void
    {
        $this->deleteSubscriptionId = $id;
    }

    public function deleteSubscription(): void
    {
        $subscription = Subscription::findOrFail($this->deleteSubscriptionId ?? 0);

        if ($subscription->customer_id !== $this->customer->id) {
            abort(404);
        }

        $label = $subscription->plan?->name ?? 'اشتراک دستی';
        $subscription->delete();
        $this->deleteSubscriptionId = null;
        $this->toast('اشتراک «'.$label.'» حذف شد.');
    }

    /* ================================================================ */
    /*  لایسنس پکیج: افزودن / ویرایش / تغییر انضا / ابطال / حذف */
    /* ================================================================ */

    public function openLicenseCreate(): void
    {
        $this->resetValidation();
        $this->licenseId = null;
        $this->licenseForm = [
            'package_id' => '',
            'duration_months' => '12',
            'status' => 'active',
            'expires_at' => '',
            'notes' => 'صدور دستی توسط مدیر',
        ];
        $this->showLicenseModal = true;
    }

    public function openLicenseEdit(int $id): void
    {
        $license = PackageLicense::findOrFail($id);

        if ($license->customer_id !== $this->customer->id) {
            abort(404);
        }

        $this->resetValidation();
        $this->licenseId = $license->id;
        $this->licenseForm = [
            'package_id' => $license->package_id,
            'duration_months' => (string) $license->duration_months,
            'status' => $license->status,
            'expires_at' => $license->expires_at?->format('Y-m-d') ?? '',
            'notes' => $license->notes ?? '',
        ];
        $this->showLicenseModal = true;
    }

    public function saveLicense(): void
    {
        $isEdit = $this->licenseId !== null;

        $rules = [
            'licenseForm.status' => ['required', 'in:active,expired,revoked'],
            'licenseForm.expires_at' => ['nullable', 'date'],
            'licenseForm.notes' => ['nullable', 'string', 'max:1000'],
        ];

        if (! $isEdit) {
            $rules['licenseForm.package_id'] = ['required', 'integer', 'exists:packages,id'];
            $rules['licenseForm.duration_months'] = ['required', 'integer', 'min:0', 'max:120'];
        }

        $messages = [
            'licenseForm.package_id.required' => 'انتخاب پکیج الزامی است.',
            'licenseForm.expires_at.date' => 'تاریخ انضا معتبر نیست.',
            'licenseForm.status.required' => 'وضعیت لایسنس الزامی است.',
        ];

        $this->validate($rules, $messages);

        if ($isEdit) {
            $license = PackageLicense::findOrFail($this->licenseId);

            if ($license->customer_id !== $this->customer->id) {
                abort(404);
            }

            $license->update([
                'status' => $this->licenseForm['status'],
                'expires_at' => $this->licenseForm['expires_at'] !== ''
                    ? Carbon::parse($this->licenseForm['expires_at'])->endOfDay()
                    : null,
                'notes' => $this->licenseForm['notes'] ?: null,
            ]);

            $this->toast('لایسنس به‌روزرسانی شد.');
        } else {
            $months = (int) $this->licenseForm['duration_months'];
            $expires = $months > 0 ? now()->addMonths($months)->endOfDay() : null;

            PackageLicense::create([
                'license_key' => PackageLicense::generateKey(),
                'package_id' => (int) $this->licenseForm['package_id'],
                'customer_id' => $this->customer->id,
                'status' => $this->licenseForm['status'],
                'starts_at' => now(),
                'expires_at' => $expires,
                'duration_months' => $months,
                'notes' => $this->licenseForm['notes'] ?: null,
            ]);

            $this->toast($months > 0
                ? 'لایسنس جدید صادر شد (اعتبار تا '.verta_date($expires).').'
                : 'لایسنس نامحدود جدید صادر شد.');
        }

        $this->showLicenseModal = false;
    }

    /** تغییر سریع اعتبار لایسنس: +N یا -N ماه */
    public function adjustLicenseExpiry(int $id, int $months): void
    {
        $license = PackageLicense::findOrFail($id);

        if ($license->customer_id !== $this->customer->id) {
            abort(404);
        }

        if ($license->expires_at === null) {
            $this->toast('لایسنس نامحدود است؛ تاریخ انضا ندارد.', 'warning');

            return;
        }

        $base = $license->expires_at->isFuture() ? $license->expires_at->copy() : now();

        $new = $months >= 0
            ? $base->copy()->addMonths($months)
            : $base->copy()->subMonths(abs($months));

        if ($new->isPast()) {
            $this->toast('تاریخ انضای جدید در گذشته است؛ از مودال ویرایش استفاده کنید.', 'error');

            return;
        }

        $license->update([
            'expires_at' => $new,
            'status' => $license->status === PackageLicense::STATUS_REVOKED ? $license->status : PackageLicense::STATUS_ACTIVE,
        ]);

        $this->toast(($months >= 0 ? 'اعتبار لایسنس '.fa_num($months).' ماه افزایش یافت (تا '.verta_date($new).').' : 'اعتبار لایسنس '.fa_num(abs($months)).' ماه کاهش یافت (تا '.verta_date($new).').'));
    }

    /** ابطال / فعال‌سازی لایسنس پکیج */
    public function toggleLicense(int $id): void
    {
        $license = PackageLicense::findOrFail($id);

        if ($license->customer_id !== $this->customer->id) {
            abort(404);
        }

        if ($license->status === PackageLicense::STATUS_REVOKED) {
            $license->update(['status' => PackageLicense::STATUS_ACTIVE]);
            $this->toast('لایسنس دوباره فعال شد.');
        } else {
            $license->revoke();
            $this->toast('لایسنس ابطال شد.');
        }
    }

    public function confirmDeleteLicense(int $id): void
    {
        $this->deleteLicenseId = $id;
    }

    public function deleteLicense(): void
    {
        $license = PackageLicense::findOrFail($this->deleteLicenseId ?? 0);

        if ($license->customer_id !== $this->customer->id) {
            abort(404);
        }

        $key = $license->license_key;
        $license->delete();
        $this->deleteLicenseId = null;
        $this->toast('لایسنس '.$key.' حذف شد.');
    }

    /* ================================================================ */
    /*  لایسنس قالب */
    /* ================================================================ */

    public function toggleTemplateLicense(int $id): void
    {
        $license = TemplateLicense::findOrFail($id);

        if ($license->customer_id !== $this->customer->id) {
            abort(404);
        }

        if ($license->status === TemplateLicense::STATUS_REVOKED) {
            $license->restore();
            $this->toast('لایسنس قالب دوباره فعال شد.');
        } else {
            $license->revoke();
            $this->toast('لایسنس قالب ابطال شد.');
        }
    }

    public function confirmDeleteTemplateLicense(int $id): void
    {
        $this->deleteTemplateLicenseId = $id;
    }

    public function deleteTemplateLicense(): void
    {
        $license = TemplateLicense::findOrFail($this->deleteTemplateLicenseId ?? 0);

        if ($license->customer_id !== $this->customer->id) {
            abort(404);
        }

        $key = $license->license_key;
        $license->delete();
        $this->deleteTemplateLicenseId = null;
        $this->toast('لایسنس قالب '.$key.' حذف شد.');
    }

    /* ================================================================ */
    /*  سفارش‌های اشتراک: تأیید / رد */
    /* ================================================================ */

    public function approveOrder(int $id, SubscriptionService $service): void
    {
        $order = SubscriptionOrder::findOrFail($id);

        if ($order->customer_id !== $this->customer->id) {
            abort(404);
        }

        $service->approve($order);
        $this->toast('سفارش طرح «'.$order->plan_name.'» تأیید و اشتراک صادر شد.');
    }

    public function openRejectOrder(int $id): void
    {
        $this->rejectOrderId = $id;
        $this->rejectReason = '';
        $this->showRejectModal = true;
    }

    public function rejectOrder(SubscriptionService $service): void
    {
        $order = SubscriptionOrder::findOrFail($this->rejectOrderId ?? 0);

        if ($order->customer_id !== $this->customer->id) {
            abort(404);
        }

        $service->reject($order, $this->rejectReason ?: null);
        $this->showRejectModal = false;
        $this->rejectOrderId = null;
        $this->toast('سفارش طرح «'.$order->plan_name.'» رد شد.');
    }

    /* ================================================================ */
    /*  هلپرهای نمایش */
    /* ================================================================ */

    /** برچسب + واریانت وضعیت خرید */
    public function purchaseStatusView(string $status): array
    {
        return match ($status) {
            PackagePurchase::STATUS_PAID, TemplatePurchase::STATUS_PAID => ['پرداخت شده', 'success'],
            PackagePurchase::STATUS_PENDING, TemplatePurchase::STATUS_PENDING => ['در انتظار پرداخت', 'warning'],
            PackagePurchase::STATUS_REFUNDED, TemplatePurchase::STATUS_REFUNDED => ['بازپرداخت', 'neutral'],
            default => ['ناموفق', 'danger'],
        };
    }

    /** برچسب وضعیت لایسنس با احتساب انقضای مؤثر */
    public function licenseStatusView(PackageLicense $license): array
    {
        if ($license->status === PackageLicense::STATUS_REVOKED) {
            return ['ابطال شده', 'danger'];
        }

        if ($license->expires_at && $license->expires_at->isPast()) {
            return ['منقضی', 'warning'];
        }

        return [$license->expires_at === null ? 'نامحدود' : 'فعال', 'success'];
    }

    public function render()
    {
        return view('livewire.customers.show');
    }
}
