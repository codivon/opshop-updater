<?php

namespace App\Livewire\Shop;

use App\Livewire\Concerns\WithToasts;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Subscription;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.shop')]
#[Title('فروشگاه')]
class Home extends Component
{
    use WithPagination, WithToasts;

    /** تب فعال فروشگاه: packages | products */
    #[Url]
    public string $tab = 'packages';

    /* ---------------- Filters: پکیج‌ها ---------------- */
    #[Url]
    public string $search = '';

    #[Url]
    public string $category = '';

    #[Url]
    public string $priceFilter = '';   // '' | free | paid

    #[Url]
    public string $sort = 'newest';

    /* ---------------- Filters: محصولات ---------------- */
    #[Url]
    public string $productSearch = '';

    #[Url]
    public string $productCategory = '';

    #[Url]
    public string $productPrice = '';  // '' | free | paid

    #[Url]
    public string $productSort = 'newest';

    /** کد آپدیت مشتری برای بررسی اشتراک فعال (از session پیش‌پر می‌شود) */
    public string $updateCode = '';

    public function mount(): void
    {
        $this->updateCode = (string) session('shop_update_code', '');

        if (!in_array($this->tab, ['packages', 'products'], true)) {
            $this->tab = 'packages';
        }
    }

    public function switchTab(string $tab): void
    {
        $this->tab = in_array($tab, ['packages', 'products'], true) ? $tab : 'packages';
        $this->resetPage();
    }

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

    public function updatedProductSearch(): void
    {
        $this->resetPage();
    }

    public function updatedProductCategory(): void
    {
        $this->resetPage();
    }

    public function updatedProductPrice(): void
    {
        $this->resetPage();
    }

    public function updatedProductSort(): void
    {
        $this->resetPage();
    }

    /* ================================================================
     *  عبارت SQL «قیمت مؤثر» پکیج (برای فیلتر و مرتب‌سازی در دیتابیس)
     *  رایگان = 0؛ وگرنه کمترین طرح فعال یا قیمت پیش‌فرض.
     * ================================================================ */
    private function packageEffectivePriceSql(): string
    {
        // final_price پلن‌ها = COALESCE(discount_price, price) — همان accessor مدل
        return "CASE WHEN packages.is_free = 1 THEN 0 ELSE COALESCE("
            . "(SELECT MIN(COALESCE(pp.discount_price, pp.price)) FROM package_pricing_plans pp WHERE pp.package_id = packages.id AND pp.is_active = 1), "
            . "packages.default_price, 0) END";
    }

    /** عبارت SQL «قیمت مؤثر» محصول */
    private function productEffectivePriceSql(): string
    {
        return "CASE WHEN products.is_free = 1 OR products.price <= 0 OR products.discount >= 100 THEN 0 "
            . "WHEN products.discount > 0 THEN ROUND(products.price * (100 - products.discount) / 100) "
            . "ELSE products.price END";
    }

    /* ---------------------------------------------------------------- */
    /*  اشتراک فعال مشتری (نشان پکیج‌های رایگان طرح)                     */
    /* ---------------------------------------------------------------- */

    public function checkMySubscription(): void
    {
        $code = trim($this->updateCode);

        if ($code === '') {
            $this->toast('کد آپدیت الزامی است.', 'error');
            return;
        }

        $customer = Customer::query()->where('update_code', $code)->first();

        if (!$customer) {
            $this->toast('کد آپدیت نامعتبر است.', 'error');
            return;
        }

        if ($customer->status !== 'active') {
            $this->toast('حساب این مشتری غیرفعال است.', 'error');
            return;
        }

        $subscription = $this->findActivePlanSubscription($customer);

        if (!$subscription?->plan) {
            $this->toast('هیچ اشتراک فعالی برای این کد یافت نشد.', 'error');
            return;
        }

        session()->put('shop_update_code', $code);

        unset($this->myActiveSubscription, $this->coveredPackageIds);

        $this->toast('اشتراک فعال شما پیدا شد؛ پکیج‌های طرح «' . $subscription->plan->name . '» برای شما رایگان‌اند.');
    }

    public function forgetMySubscription(): void
    {
        session()->forget('shop_update_code');
        $this->reset('updateCode');

        unset($this->myActiveSubscription, $this->coveredPackageIds);

        $this->toast('کد آپدیت شما فراموش شد؛ نشان پکیج‌های رایگان اشتراک برداشته شد.');
    }

    #[Computed]
    public function myActiveSubscription()
    {
        $code = trim((string) session('shop_update_code', ''));

        if ($code === '') {
            return null;
        }

        $customer = Customer::query()
            ->where('update_code', $code)
            ->where('status', 'active')
            ->first();

        return $customer ? $this->findActivePlanSubscription($customer) : null;
    }

    #[Computed]
    public function coveredPackageIds(): array
    {
        $plan = $this->myActiveSubscription?->plan;

        if (!$plan) {
            return [];
        }

        return $plan->packages->pluck('id')->values()->all();
    }

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

    /* ================================================================
     *  پکیج‌ها
     * ================================================================ */

    #[Computed]
    public function records()
    {
        $effective = $this->packageEffectivePriceSql();

        return Package::query()
            ->with(['latestVersion', 'activePricingPlans', 'categoryModel:id,name'])
            ->where('status', Package::STATUS_ACTIVE)
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('slug', 'like', "%{$this->search}%")))
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

    /** دسته‌بندی‌های ساختاریافته پکیج (از جدول categories) */
    #[Computed]
    public function categories()
    {
        return Category::query()
            ->ofType(Category::TYPE_PACKAGE)
            ->active()
            ->ordered()
            ->get()
            ->mapWithKeys(fn ($cat) => [$cat->id => $cat->name])
            ->all();
    }

    /* ================================================================
     *  محصولات (پروژه‌های قابل‌فروش)
     * ================================================================ */

    #[Computed]
    public function products()
    {
        $effective = $this->productEffectivePriceSql();

        return \App\Models\Product::query()
            ->with(['category:id,name', 'latestVersion:id,product_id,version'])
            ->withCount(['comments' => fn ($q) => $q->where('status', 'approved')])
            ->where('status', \App\Models\Product::STATUS_ACTIVE)
            ->when($this->productSearch, fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$this->productSearch}%")
                ->orWhere('slug', 'like', "%{$this->productSearch}%")
                ->orWhere('short_description', 'like', "%{$this->productSearch}%")))
            ->when($this->productCategory !== '', fn ($q) => $q->where('category_id', $this->productCategory))
            ->when($this->productPrice === 'free', fn ($q) => $q->whereRaw("{$effective} <= 0"))
            ->when($this->productPrice === 'paid', fn ($q) => $q->whereRaw("{$effective} > 0"))
            ->when($this->productSort === 'newest', fn ($q) => $q->orderBy('sort_order')->latest('id'))
            ->when($this->productSort === 'oldest', fn ($q) => $q->orderBy('sort_order')->oldest('id'))
            ->when($this->productSort === 'cheap', fn ($q) => $q->orderByRaw("{$effective} ASC")->orderByDesc('id'))
            ->when($this->productSort === 'expensive', fn ($q) => $q->orderByRaw("{$effective} DESC")->orderByDesc('id'))
            ->when($this->productSort === 'popular', fn ($q) => $q->orderByDesc('sales_count')->orderByDesc('views_count')->orderByDesc('id'))
            ->paginate(12, pageName: 'productsPage');
    }

    /** دسته‌بندی‌های محصولات */
    #[Computed]
    public function productCategories()
    {
        return Category::query()
            ->ofType(Category::TYPE_PRODUCT)
            ->active()
            ->ordered()
            ->get()
            ->mapWithKeys(fn ($cat) => [$cat->id => $cat->name])
            ->all();
    }

    /** گزینه‌های مرتب‌سازی */
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

    public function getPriceOptionsProperty(): array
    {
        return [
            'free' => 'فقط رایگان',
            'paid' => 'فقط پولی',
        ];
    }

    /* ================================================================
     *  آمار + طرح‌ها
     * ================================================================ */

    #[Computed]
    public function stats(): array
    {
        return [
            'products'    => \App\Models\Product::where('status', \App\Models\Product::STATUS_ACTIVE)->count(),
            'packages'    => Package::where('status', Package::STATUS_ACTIVE)->count(),
            'versions'    => \App\Models\PackageVersion::where('status', \App\Models\PackageVersion::STATUS_ACTIVE)->count(),
            'plans'       => \App\Models\SubscriptionPlan::where('is_active', true)->count(),
            'customers'   => \App\Models\PackagePurchase::distinct('customer_id')->count('customer_id'),
        ];
    }

    #[Computed]
    public function featuredPlans()
    {
        return \App\Models\SubscriptionPlan::query()
            ->active()
            ->with(['packages:id,name,slug'])
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->limit(3)
            ->get();
    }

    public function render()
    {
        return view('livewire.shop.home');
    }
}
