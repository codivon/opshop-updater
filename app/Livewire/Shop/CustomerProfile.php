<?php

namespace App\Livewire\Shop;

use App\Livewire\Concerns\WithToasts;
use App\Models\Comment;
use App\Models\Customer;
use App\Models\Package;
use App\Models\PackageLicense;
use App\Models\PackagePurchase;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * پروفایل مشتری فروشگاه:
 * - مشاهده پکیج‌های خریده + وضعیت لایسنس (نصب فقط با کد آپدیت در فروشگاه‌ساز — بدون دانلود مستقیم)
 * - مشاهده اشتراک فعال
 * - ویرایش مشخصات (نام، ایمیل، موبایل، آدرس سایت)
 */
#[Layout('components.layouts.shop')]
#[Title('پروفایل من')]
class CustomerProfile extends Component
{
    use WithToasts;

    /** تب فعال: purchases | licenses | tickets | info */
    public string $tab = 'purchases';

    /* ---- فیلتر بخش لایسنس‌ها ---- */
    public string $licenseSearch = '';

    /** '' | active | expired | revoked */
    public string $licenseStatus = '';

    /* ---- form: مشخصات ---- */
    public array $form = [
        'name'        => '',
        'email'       => '',
        'phone'       => '',
        'website_url' => '',
    ];

    /* ---- تیکت‌های پشتیبانی (فقط با اشتراک فعال) ---- */
    public bool $showTicketForm = false;

    public string $ticketSubject = '';

    public string $ticketBody = '';

    /** گفتگوی باز (شناسهٔ تیکت) */
    public ?int $openTicketId = null;

    public string $replyBody = '';

    protected const TABS = ['purchases', 'licenses', 'tickets', 'info'];

    public function mount(): void
    {
        $customer = $this->customer();

        if (!$customer) {
            $this->redirect(route('shop.login', ['redirect' => url()->current()]), navigate: true);
            return;
        }

        $this->form = [
            'name'        => (string) $customer->name,
            'email'       => (string) $customer->email,
            'phone'       => (string) $customer->phone,
            'website_url' => (string) $customer->website_url,
        ];

        if (!in_array($this->tab, self::TABS, true)) {
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
    /*  ویرایش مشخصات                                                    */
    /* ---------------------------------------------------------------- */

    protected function rules(): array
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        return [
            'form.name'        => ['required', 'string', 'max:120'],
            'form.email'       => ['nullable', 'email', 'max:190'],
            'form.phone'       => ['nullable', 'string', 'max:20'],
            'form.website_url' => ['nullable', 'string', 'max:190'],
        ];
    }

    protected $messages = [
        'form.name.required' => 'نام الزامی است.',
        'form.email.email'   => 'ایمیل معتبر نیست.',
    ];

    public function saveInfo(): void
    {
        $data   = $this->validate();
        $customer = $this->customer();

        $customer->update([
            'name'        => trim($data['form']['name']),
            'email'       => trim($data['form']['email']) ?: null,
            'phone'       => trim($data['form']['phone']) ?: null,
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
    /*  تیکت‌های پشتیبانی (گات: فقط با اشتراک فعال)                       */
    /* ---------------------------------------------------------------- */

    /** پیام یکسان گات — هم در UI هم در اکشن‌ها */
    public static function ticketGateMessage(): string
    {
        return 'برای استفاده از پشتیبانی (ثبت تیکت) باید اشتراک فعال داشته باشید.';
    }

    public function openTicketForm(): void
    {
        if (!$this->canUseTickets()) {
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
        if (!$this->canUseTickets()) {
            $this->toast(self::ticketGateMessage(), 'error');

            return;
        }

        $data = $this->validate([
            'ticketSubject' => ['required', 'string', 'min:3', 'max:150'],
            'ticketBody'    => ['required', 'string', 'min:10', 'max:5000'],
        ], [
            'ticketSubject.required' => 'موضوع تیکت الزامی است.',
            'ticketSubject.min'      => 'موضوع تیکت حداقل ۳ حرف است.',
            'ticketSubject.max'      => 'موضوع تیکت حداکثر ۱۵۰ حرف است.',
            'ticketBody.required'    => 'متن تیکت الزامی است.',
            'ticketBody.min'         => 'متن تیکت حداقل ۱۰ حرف است — مشکل را کامل توضیح دهید.',
            'ticketBody.max'         => 'متن تیکت حداکثر ۵۰۰۰ حرف است.',
        ]);

        $customer = $this->customer();
        $subscription = app(SubscriptionService::class)->activePlanSubscriptions($customer)->first();

        $ticket = Ticket::create([
            'customer_id'    => $customer->id,
            'subscription_id' => $subscription?->id,
            'subject'        => trim($data['ticketSubject']),
            'status'         => Ticket::STATUS_OPEN,
        ]);

        TicketReply::create([
            'ticket_id'   => $ticket->id,
            'author_type' => TicketReply::AUTHOR_CUSTOMER,
            'body'        => trim($data['ticketBody']),
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
        if (!$customer || !app(SubscriptionService::class)->canCreateTicket($customer)) {
            $this->toast(self::ticketGateMessage(), 'error');

            return;
        }

        $ticket = Ticket::query()
            ->where('customer_id', $customer->id)
            ->find($ticketId);

        if (!$ticket) {
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
            'replyBody.min'      => 'متن پاسخ حداقل ۲ حرف است.',
            'replyBody.max'      => 'متن پاسخ حداکثر ۵۰۰۰ حرف است.',
        ]);

        TicketReply::create([
            'ticket_id'   => $ticket->id,
            'author_type' => TicketReply::AUTHOR_CUSTOMER,
            'body'        => trim($data['replyBody']),
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

        if (!$ticket || $ticket->isClosed()) {
            $this->toast('این تیکت بسته است.', 'warning');

            return;
        }

        $ticket->update(['status' => Ticket::STATUS_CLOSED, 'last_reply_at' => now()]);
        unset($this->myTickets);

        $this->toast('تیکت بسته شد. برای موضوع جدید یک تیکت تازه بزنید.');
    }

    /* ---------------------------------------------------------------- */
    /*  Computed                                                         */
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

        if (!$customer) {
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

        if (!$customer) {
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

        if (!$customer) {
            return ['purchases' => 0, 'licenses' => 0, 'licenses_total' => 0, 'spent' => 0];
        }

        return [
            // تعداد پکیج‌های متمایز خریده‌شده (بدون تکرار خریدهای مجدد یک پکیج)
            'purchases'      => $customer->purchases()
                ->where('status', PackagePurchase::STATUS_PAID)
                ->distinct('package_id')
                ->count('package_id'),
            'licenses'       => $customer->licenses()->where('status', PackageLicense::STATUS_ACTIVE)->count(),
            'licenses_total' => $customer->licenses()->count(),
            'spent'          => (int) $customer->purchases()->where('status', PackagePurchase::STATUS_PAID)->sum('amount'),
        ];
    }

    public function render()
    {
        return view('livewire.shop.customer-profile');
    }
}
