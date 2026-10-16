<?php

namespace App\Livewire\Shop;

use App\Livewire\Concerns\WithToasts;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Package;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * صفحه‌ی مستقل «پکیج‌ها» — فهرست کامل با فیلترهای مدرن:
 * جستجو + چیپ دسته‌بندی + فیلتر قیمت + مرتب‌سازی.
 */
#[Layout('components.layouts.shop')]
#[Title('پکیج‌ها')]
class PackagesIndex extends Component
{
    use WithPagination, WithToasts;

    #[Url]
    public string $search = '';

    #[Url]
    public string $category = '';

    #[Url]
    public string $priceFilter = '';   // '' | free | paid

    #[Url]
    public string $sort = 'newest';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    public function updatedPriceFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'category', 'priceFilter');
        $this->sort = 'newest';
        $this->resetPage();
    }

    /** مشتریِ جاری: اولویت با مشتریِ لاگین‌شده، سپس کد ذخیره‌شده در session */
    #[Computed]
    public function currentCustomer(): ?Customer
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

    /** شناسه‌ی پکیج‌هایی که با اشتراک فعالِ مشتری رایگان می‌شوند */
    #[Computed]
    public function coveredPackageIds(): array
    {
        $plan = $this->currentCustomer?->activePlanSubscription()?->plan;

        if (!$plan) {
            return [];
        }

        return $plan->packages->pluck('id')->values()->all();
    }

    /** عبارت SQL «قیمت مؤثر» پکیج (برای فیلتر و مرتب‌سازی در دیتابیس) */
    private function packageEffectivePriceSql(): string
    {
        return "CASE WHEN packages.is_free = 1 THEN 0 ELSE COALESCE("
            . "(SELECT MIN(COALESCE(pp.discount_price, pp.price)) FROM package_pricing_plans pp WHERE pp.package_id = packages.id AND pp.is_active = 1), "
            . "packages.default_price, 0) END";
    }

    #[Computed]
    public function records()
    {
        $effective = $this->packageEffectivePriceSql();

        return Package::query()
            ->with(['latestVersion:id,package_id,version', 'activePricingPlans', 'categoryModel:id,name'])
            ->withCount(['licenses' => fn ($q) => $q->where('status', 'active')])
            ->where('status', Package::STATUS_ACTIVE)
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('slug', 'like', "%{$this->search}%")
                ->orWhere('short_description', 'like', "%{$this->search}%")))
            ->when($this->category !== '', fn ($q) => $q->where('category_id', $this->category))
            ->when($this->priceFilter === 'free', fn ($q) => $q->whereRaw("{$effective} <= 0"))
            ->when($this->priceFilter === 'paid', fn ($q) => $q->whereRaw("{$effective} > 0"))
            ->when($this->sort === 'newest', fn ($q) => $q->latest())
            ->when($this->sort === 'oldest', fn ($q) => $q->oldest())
            ->when($this->sort === 'cheap', fn ($q) => $q->orderByRaw("{$effective} ASC")->orderByDesc('id'))
            ->when($this->sort === 'expensive', fn ($q) => $q->orderByRaw("{$effective} DESC")->orderByDesc('id'))
            ->when($this->sort === 'popular', fn ($q) => $q->orderByDesc('purchases_count')->orderByDesc('id'))
            ->paginate(12);
    }

    /** دسته‌بندی‌های پکیج با تعداد */
    #[Computed]
    public function categories()
    {
        return Category::query()
            ->ofType(Category::TYPE_PACKAGE)
            ->active()
            ->ordered()
            ->withCount(['packages' => fn ($q) => $q->where('status', Package::STATUS_ACTIVE)])
            ->get();
    }

    public function getSortOptionsProperty(): array
    {
        return [
            'newest'    => 'جدیدترین',
            'oldest'    => 'قدیمی‌ترین',
            'cheap'     => 'ارزان‌ترین',
            'expensive' => 'گران‌ترین',
            'popular'   => 'محبوب‌ترین',
        ];
    }

    public function render()
    {
        return view('livewire.shop.packages-index');
    }
}
