<?php

namespace App\Livewire\Shop;

use App\Livewire\Concerns\WithToasts;
use App\Models\Customer;
use App\Models\Package;
use App\Models\PackageLicense;
use App\Models\PackagePurchase;
use App\Models\Subscription;
use App\Models\SubscriptionOrder;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Services\LicenseSigningKeyService;
use App\Services\SubscriptionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * پروفایل مشتری فروشگاه:
 * - مشاهده پکیج‌های خریده + پکیج‌های همراهِ اشتراک + وضعیت لایسنس
 *   (نصب فقط با کد آپدیت در فروشگاه‌ساز — بدون دانلود مستقیم)
 * - مشاهده اشتراک‌های خریداری‌شده + سفارش‌های در انتظار تأیید مدیر
 * - ویرایش مشخصات (نام، ایمیل، موبایل)
 *
 * Task 96 — WithPagination: بدون این تریت، ویوهای صفحه‌بندیِ منتشرشدهٔ اپ
 * (<a href>) در درخواست‌های update لایووایر آدرسشان به livewire/update حل می‌شد
 * و کلیک صفحهٔ ۲ خطای «The GET method is not supported for route livewire/update»
 * می‌داد؛ با تریت، ویوهای لایووایر (wire:click) استفاده می‌شوند.
 */
#[Layout('components.layouts.shop')]
#[Title('پروفایل من')]
class CustomerProfile extends Component
{
    use WithPagination, WithToasts;

    /** تب فعال: purchases | subscriptions | licenses | tickets | info */
    public string $tab = 'purchases';

    /* ---- فیلتر بخش لایسنس‌ها ---- */
    public string $licenseSearch = '';

    /** '' | active | expired | revoked */
    public string $licenseStatus = '';

    /* ---- form: مشخصات ---- */
    public array $form = [
        'name' => '',
        'email' => '',
        'phone' => '',
        'website_url' => '',
    ];

    /* ---- تیکت‌های پشتیبانی (فقط با اشتراک فعال) ---- */
    public bool $showTicketForm = false;

    public string $ticketSubject = '';

    public string $ticketBody = '';

    /** گفتگوی باز (شناسهٔ تیکت) */
    public ?int $openTicketId = null;

    public string $replyBody = '';

    protected const TABS = ['purchases', 'subscriptions', 'licenses', 'tickets', 'info'];

    public function mount(): void
    {
        $customer = $this->customer();

        if (! $customer) {
            $this->redirect(route('shop.login', ['redirect' => url()->current()]), navigate: true);

            return;
        }

        $this->form = [
            'name' => (string) $customer->name,
            'email' => (string) $customer->email,
            'phone' => (string) $customer->phone,
            'website_url' => (string) $customer->website_url,
        ];

        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'purchases';
        }
    }

    protected function customer(): ?Customer
    {
        /** @var Customer|null $customer */
        $customer = auth('customer')->user();

        return $customer;
    }

    public function switchTab(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'purchases';
    }

    public function updatedLicenseSearch(): void
    {
        $this->resetPage('licensesPage');
    }

    public function updatedLicenseStatus(): void
    {
        $this->resetPage('licensesPage');
    }

    /* ---------------------------------------------------------------- */
    /*  ویرایش مشخصات */
    /* ---------------------------------------------------------------- */

    protected function rules(): array
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        return [
            'form.name' => ['required', 'string', 'max:120'],
            // Task 85 — ایمیل مشتری یکتا باشد (ولی خالی هم مجاز)
            'form.email' => ['nullable', 'email', 'max:190', Rule::unique('customers', 'email')->ignore($customer?->id)],
            'form.phone' => ['nullable', 'string', 'max:20'],
            'form.website_url' => ['nullable', 'string', 'max:190'],
        ];
    }

    protected $messages = [
        'form.name.required' => 'نام الزامی است.',
        'form.email.email' => 'ایمیل معتبر نیست.',
    ];

    public function saveInfo(): void
    {
        $data = $this->validate();
        $customer = $this->customer();

        $customer->update([
            'name' => trim($data['form']['name']),
            'email' => trim($data['form']['email']) ?: null,
            'phone' => trim($data['form']['phone']) ?: null,
            'website_url' => trim($data['form']['website_url']) ?: null,
        ]);

        $this->toast('مشخصات شما ذخیره شد.');
    }

    public function logout(): void
    {
        // نکته: بدون invalidate کردن کل سشن — مدیر در همان مرورگر لاگین بماند.
        Auth::guard('customer')->logout();
        session()->forget('shop_update_code');
        session()->regenerateToken();

        $this->redirect(route('shop.home'), navigate: true);
    }

    /* ---------------------------------------------------------------- */
    /*  تیکت‌های پشتیبانی (گات: فقط با اشتراک فعال) */
    /* ---------------------------------------------------------------- */

    /** پیام یکسان گات — هم در UI هم در اکشن‌ها */
    public static function ticketGateMessage(): string
    {
        return 'برای استفاده از پشتیبانی (ثبت تیکت) باید اشتراک فعال داشته باشید.';
    }

    public function openTicketForm(): void
    {
        if (! $this->canUseTickets()) {
            $this->toast(self::ticketGateMessage(), 'error');

            return;
        }

        $this->showTicketForm = true;
        $this->openTicketId = null;
    }

    public function hideTicketForm(): void
    {
        $this->showTicketForm = false;
    }

    /** ثبت تیکت جدید (سؤال اصلی + اولین پاسخ مشتری) */
    public function createTicket(): void
    {
        if (! $this->canUseTickets()) {
            $this->toast(self::ticketGateMessage(), 'error');

            return;
        }

        $data = $this->validate([
            'ticketSubject' => ['required', 'string', 'min:3', 'max:150'],
            'ticketBody' => ['required', 'string', 'min:10', 'max:5000'],
        ], [
            'ticketSubject.required' => 'موضوع تیکت الزامی است.',
            'ticketSubject.min' => 'موضوع تیکت حداقل ۳ حرف است.',
            'ticketSubject.max' => 'موضوع تیکت حداکثر ۱۵۰ حرف است.',
            'ticketBody.required' => 'متن تیکت الزامی است.',
            'ticketBody.min' => 'متن تیکت حداقل ۱۰ حرف است — مشکل را کامل توضیح دهید.',
            'ticketBody.max' => 'متن تیکت حداکثر ۵۰۰۰ حرف است.',
        ]);

        $customer = $this->customer();
        $subscription = app(SubscriptionService::class)->activePlanSubscriptions($customer)->first();

        $ticket = Ticket::create([
            'customer_id' => $customer->id,
            'subscription_id' => $subscription?->id,
            'subject' => trim($data['ticketSubject']),
            'status' => Ticket::STATUS_OPEN,
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'author_type' => TicketReply::AUTHOR_CUSTOMER,
            'body' => trim($data['ticketBody']),
        ]);

        $this->reset('ticketSubject', 'ticketBody', 'showTicketForm');
        $this->openTicketId = $ticket->id;
        unset($this->myTickets);

        $this->toast('تیکت شما ثبت شد؛ کارشناسان ما به‌زودی پاسخ می‌دهند.');
    }

    /** باز/بسته کردن گفتگوی یک تیکت */
    public function toggleConversation(int $ticketId): void
    {
        $this->openTicketId = ($this->openTicketId === $ticketId) ? null : $ticketId;
        $this->replyBody = '';
    }

    /** پاسخ مشتری به تیکت خودش */
    public function replyToTicket(int $ticketId): void
    {
        $customer = $this->customer();

        // گات: پاسخ‌دادن هم مثل ثبت تیکت نیاز به اشتراک فعال دارد
        if (! $customer || ! app(SubscriptionService::class)->canCreateTicket($customer)) {
            $this->toast(self::ticketGateMessage(), 'error');

            return;
        }

        $ticket = Ticket::query()
            ->where('customer_id', $customer->id)
            ->find($ticketId);

        if (! $ticket) {
            $this->toast('تیکت یافت نشد.', 'error');

            return;
        }

        if ($ticket->isClosed()) {
            $this->toast('این تیکت بسته شده است؛ برای موضوع جدید یک تیکت تازه بزنید.', 'warning');

            return;
        }

        $data = $this->validate([
            'replyBody' => ['required', 'string', 'min:2', 'max:5000'],
        ], [
            'replyBody.required' => 'متن پاسخ الزامی است.',
            'replyBody.min' => 'متن پاسخ حداقل ۲ حرف است.',
            'replyBody.max' => 'متن پاسخ حداکثر ۵۰۰۰ حرف است.',
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'author_type' => TicketReply::AUTHOR_CUSTOMER,
            'body' => trim($data['replyBody']),
        ]);

        // پاسخ مشتری → وضعیت برمی‌گردد به «در انتظار پاسخ»
        $ticket->update(['status' => Ticket::STATUS_OPEN, 'last_reply_at' => now()]);

        $this->reset('replyBody');
        unset($this->myTickets);

        $this->toast('پاسخ شما ثبت شد.');
    }

    /** بستن تیکت توسط مشتری (مشکل حل شد) */
    public function closeTicket(int $ticketId): void
    {
        $customer = $this->customer();

        $ticket = Ticket::query()
            ->where('customer_id', $customer?->id ?? 0)
            ->find($ticketId);

        if (! $ticket || $ticket->isClosed()) {
            $this->toast('این تیکت بسته است.', 'warning');

            return;
        }

        $ticket->update(['status' => Ticket::STATUS_CLOSED, 'last_reply_at' => now()]);
        unset($this->myTickets);

        $this->toast('تیکت بسته شد. برای موضوع جدید یک تیکت تازه بزنید.');
    }

    /* ---------------------------------------------------------------- */
    /*  Computed */
    /* ---------------------------------------------------------------- */

    /**
     * خریدهای موفق پکیج (به‌همراه پکیج و نسخه)
     * بدون تکرار: اگر مشتری یک پکیج را چند بار خریده/تمدید کرده،
     * فقط آخرین خریدِ هر پکیج نمایش داده می‌شود.
     */
    #[Computed]
    public function purchases()
    {
        $customer = $this->customer();

        if (! $customer) {
            return PackagePurchase::query()->whereRaw('1 = 0')->paginate(10, pageName: 'purchasesPage');
        }

        // آخرین خریدِ موفق هر پکیج (MAX(id) per package)
        $latestIds = PackagePurchase::query()
            ->where('customer_id', $customer->id)
            ->where('status', PackagePurchase::STATUS_PAID)
            ->groupBy('package_id')
            ->selectRaw('MAX(id) as id')
            ->pluck('id');

        return PackagePurchase::query()
            ->with(['package:id,name,slug,thumbnail,is_free', 'version:id,version', 'pricingPlan:id,name,duration_months,is_one_time'])
            ->whereIn('id', $latestIds)
            ->orderByDesc('id')
            ->paginate(10, pageName: 'purchasesPage');
    }

    /** لایسنس‌های مشتری با فیلتر جستجو و وضعیت */
    #[Computed]
    public function licenses()
    {
        $search = trim($this->licenseSearch);

        return $this->customer()
            ?->licenses()
            ->with('package:id,name,slug')
            ->when($this->licenseStatus !== '', fn ($q) => $q->where('status', $this->licenseStatus))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('license_key', 'like', "%{$search}%")
                ->orWhereHas('package', fn ($p) => $p->where('name', 'like', "%{$search}%"))))
            ->orderByDesc('id')
            ->paginate(10, pageName: 'licensesPage');
    }

    #[Computed]
    public function mySubscription()
    {
        return $this->customer()?->activePlanSubscription();
    }

    /* ---------------------------------------------------------------- */
    /*  Task 96 — اشتراک‌های خریداری‌شده */
    /* ---------------------------------------------------------------- */

    /** تاریخچهٔ اشتراک‌های مشتری (فعال/منقضی/رزروشده) — با طرح و پروژه و سفارش */
    #[Computed]
    public function mySubscriptions()
    {
        $customer = $this->customer();

        if (! $customer) {
            return Subscription::query()->whereRaw('1 = 0')->paginate(10, pageName: 'subsPage');
        }

        return $customer->subscriptions()
            ->with(['plan:id,name,slug,duration_months', 'project:id,name,slug', 'order:id,meta'])
            ->orderByDesc('id')
            ->paginate(10, pageName: 'subsPage');
    }

    /** سفارش‌های پرداخت‌شده که هنوز اشتراکِ صادرشده ندارند (در انتظار تأیید مدیر) */
    #[Computed]
    public function pendingSubscriptionOrders(): Collection
    {
        $customer = $this->customer();

        if (! $customer) {
            return collect();
        }

        return $customer->subscriptionOrders()
            ->whereNull('subscription_id')
            ->where('status', SubscriptionOrder::STATUS_PAID)
            ->where('admin_status', SubscriptionOrder::ADMIN_STATUS_PENDING)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * پکیج‌های همراهِ اشتراک‌های فعالِ پرداخت‌شده — Task 96 (درخواست کاربر):
     * «بعضی از اشتراک‌ها چندتا پکیج رایگان همراه خود دارند، اون پکیج‌ها رو نمایش بده
     * در تب پکیج‌ها و بنویسه که به واسطهٔ اشتراک هست که این پکیج را دارد.»
     *
     * خروجی: مجموعه‌ای از ['subscription' => ..., 'packages' => Collection<Package>]
     * فقط اشتراک‌های فعالِ پرداخت‌شده و منقضی‌نشده.
     */
    #[Computed]
    public function subscriptionBundles(): Collection
    {
        $customer = $this->customer();

        if (! $customer) {
            return collect();
        }

        return $customer->subscriptions()
            ->whereNotNull('subscription_plan_id')
            ->where('status', 'active')
            ->where('payment_status', 'paid')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->with('order:id,meta')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Subscription $s) => ['subscription' => $s, 'packages' => $s->effectivePackages()])
            ->filter(fn (array $bundle) => $bundle['packages']->isNotEmpty())
            ->values();
    }

    /**
     * Task 96 — هشدار انقضای اشتراک: رکوردی برای بنر بالای تب‌ها.
     *  - expiring: اشتراک فعال تا ۷ روز دیگر منقضی می‌شود
     *  - expired: اشتراک فعالی نیست ولی اشتراکِ منقضی‌شده در پروفایل هست
     */
    #[Computed]
    public function subscriptionNotice(): ?array
    {
        $customer = $this->customer();

        if (! $customer) {
            return null;
        }

        $active = $this->mySubscription;

        if ($active && $active->effective_expires_at) {
            $days = (int) floor(now()->diffInDays($active->effective_expires_at, false));

            if ($days >= 0 && $days <= 7) {
                return [
                    'type' => 'expiring',
                    'days' => $days,
                    'plan' => $active->plan?->name,
                ];
            }
        }

        if (! $active && $customer->subscriptions()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->exists()) {
            return ['type' => 'expired', 'days' => null, 'plan' => null];
        }

        return null;
    }

    /**
     * Task 92/95 — کلید عمومی لایسنس برای فروشگاه‌ساز مشتری:
     * کلید «عمومی» است و راز نیست؛ در فیلد پنل نمایش داده می‌شود و با دکمهٔ
     * «کپی کلید» کپی می‌شود. بدون این کلید در فروشگاه، امضای پکیج‌ها تأیید
     * نمی‌شود (fail-closed).
     */
    #[Computed]
    public function licensePublicKey(): ?array
    {
        $status = app(LicenseSigningKeyService::class)->status();

        if (($status['state'] ?? '') !== 'ok' || empty($status['public_key']) || empty($status['shop_env_value'])) {
            return null;
        }

        return [
            'public_key' => (string) $status['public_key'],
            'env_line' => (string) $status['shop_env_value'],
        ];
    }

    /**
     * Task 92/95 — محتوای کپی دکمهٔ «کپی کلید» (کلید عمومی خام PEM).
     * کلید در فیلد نمایش داده می‌شود ولی کپی همچنان از سمت سرور تأمین می‌شود
     * تا در تعویض کلید، همیشه آخرین نسخهٔ معتبر کپی شود.
     */
    public function publicKeyForCopy(string $variant = 'raw'): ?string
    {
        $pk = $this->licensePublicKey;

        if (! $pk) {
            return null;
        }

        return $variant === 'raw' ? $pk['public_key'] : $pk['env_line'];
    }

    /** گات تیکت: آیا مشتری اجازهٔ استفاده از سیستم تیکت را دارد؟ (اشتراک فعال = شرط)
     *  هم به‌صورت $this->canUseTickets (نشان Computed در ویو) و هم $this->canUseTickets() قابل استفاده است. */
    #[Computed]
    public function canUseTickets(): bool
    {
        $customer = $this->customer();

        return $customer && app(SubscriptionService::class)->canCreateTicket($customer);
    }

    /** تیکت‌های مشتری با پاسخ‌ها */
    #[Computed]
    public function myTickets()
    {
        $customer = $this->customer();

        if (! $customer) {
            return Ticket::query()->whereRaw('1 = 0')->paginate(8, pageName: 'ticketsPage');
        }

        return $customer->tickets()
            ->with(['replies', 'subscription:id,subscription_plan_id,status,expires_at'])
            ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'answered' THEN 1 ELSE 2 END")
            ->orderByDesc('id')
            ->paginate(8, pageName: 'ticketsPage');
    }

    #[Computed]
    public function ticketsCount(): int
    {
        $customer = $this->customer();

        return $customer ? $customer->tickets()->count() : 0;
    }

    #[Computed]
    public function stats(): array
    {
        $customer = $this->customer();

        if (! $customer) {
            return ['purchases' => 0, 'licenses' => 0, 'licenses_total' => 0, 'spent' => 0];
        }

        // Task 96 — مجموع خرید = خریدهای پکیج + خریدهای اشتراک (پرداخت‌شده)
        // گزارش کاربر: با خرید اشتراک، «مجموع خرید» صفر نشان می‌شد چون فقط پکیج‌ها شمرده می‌شدند.
        $packageSpent = (int) $customer->purchases()
            ->where('status', PackagePurchase::STATUS_PAID)
            ->sum('amount');

        $subscriptionSpent = (int) $customer->subscriptionOrders()
            ->where('status', SubscriptionOrder::STATUS_PAID)
            ->sum('final_amount');

        return [
            // تعداد پکیج‌های متمایز خریده‌شده (بدون تکرار خریدهای مجدد یک پکیج)
            'purchases' => $customer->purchases()
                ->where('status', PackagePurchase::STATUS_PAID)
                ->distinct('package_id')
                ->count('package_id'),
            'licenses' => $customer->licenses()->where('status', PackageLicense::STATUS_ACTIVE)->count(),
            'licenses_total' => $customer->licenses()->count(),
            'spent' => $packageSpent + $subscriptionSpent,
        ];
    }

    public function render()
    {
        return view('livewire.shop.customer-profile');
    }
}
