<?php

namespace App\Livewire\Shop;

use App\Livewire\Concerns\WithToasts;
use App\Models\Comment;
use App\Models\Customer;
use App\Models\Package;
use App\Models\PackageLicense;
use App\Models\PackagePurchase;
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

    /** تب فعال: purchases | licenses | info */
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

        if (!in_array($this->tab, ['purchases', 'licenses', 'info'], true)) {
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
        $this->tab = in_array($tab, ['purchases', 'licenses', 'info'], true) ? $tab : 'purchases';
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
