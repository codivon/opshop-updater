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
use Livewire\Component;

/**
 * صفحه‌ی اصلی فروشگاه — همه‌چیز در یک صفحه:
 *   ۱) اشتراک (وضعیت اشتراک کاربر/بررسی کد + طرح‌های اشتراک)
 *   ۲) پکیج‌ها (پیش‌نمایش)
 *   ۳) پروژه‌های قابل‌فروش (پیش‌نمایش)
 * هر بخش صفحه‌ی مستقل کامل با فیلتر هم دارد:
 *   /shop/packages  (shop.packages)
 *   /shop/products  (shop.products)
 */
#[Layout('components.layouts.shop')]
#[Title('فروشگاه')]
class Home extends Component
{
    use WithToasts;

    /** تعداد کارت پیش‌نمایش هر بخش در صفحه اصلی */
    public const PREVIEW_LIMIT = 6;

    /** کد آپدیت مشتری برای بررسی اشتراک (از session پیش‌پر می‌شود) */
    public string $updateCode = '';

    public function mount(): void
    {
        // FIX (نمایش اشتراک کاربر لاگین‌شده): اولویت با مشتریِ واردشده است؛
        // فیلد فقط برای مهمان‌ها (که با کد بررسی می‌کنند) پر می‌شود.
        $this->updateCode = auth('customer')->user()?->update_code
            ?? (string) session('shop_update_code', '');
    }

    /* ================================================================
     *  مشتری جاری و اشتراک فعال
     * ================================================================ */

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

    public function checkMySubscription(): void
    {
        // کاربر لاگین‌شده نیازی به بررسی با کد ندارد — اشتراکش خودکار نمایش داده می‌شود
        if (auth('customer')->check()) {
            $this->reset('updateCode');
            $this->toast('شما وارد حساب خود شده‌اید؛ اشتراک فعال شما در همین صفحه نمایش داده می‌شود.');
            return;
        }

        $code = strtoupper(trim($this->updateCode));

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

        $subscription = $customer->activePlanSubscription();

        if (!$subscription?->plan) {
            $this->toast('هیچ اشتراک فعالی برای این کد یافت نشد.', 'error');
            return;
        }

        session()->put('shop_update_code', $code);

        unset($this->currentCustomer, $this->myActiveSubscription, $this->coveredPackageIds);

        $this->toast('اشتراک فعال شما پیدا شد؛ پکیج‌های طرح «' . $subscription->plan->name . '» برای شما رایگان‌اند.');
    }

    public function forgetMySubscription(): void
    {
        session()->forget('shop_update_code');
        $this->reset('updateCode');

        unset($this->currentCustomer, $this->myActiveSubscription, $this->coveredPackageIds);

        $this->toast('کد آپدیت شما فراموش شد؛ نشان پکیج‌های رایگان اشتراک برداشته شد.');
    }

    #[Computed]
    public function myActiveSubscription(): ?Subscription
    {
        return $this->currentCustomer?->activePlanSubscription();
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

    /* ================================================================
     *  پیش‌نمایش‌های صفحه اصلی
     * ================================================================ */

    #[Computed]
    public function packages()
    {
        return Package::query()
            ->with(['latestVersion:id,package_id,version', 'activePricingPlans', 'categoryModel:id,name'])
            ->where('status', Package::STATUS_ACTIVE)
            ->orderByDesc('id')
            ->limit(self::PREVIEW_LIMIT)
            ->get();
    }

    #[Computed]
    public function products()
    {
        return \App\Models\Product::query()
            ->with(['category:id,name', 'latestVersion:id,product_id,version'])
            ->withCount(['comments' => fn ($q) => $q->where('status', 'approved')])
            ->where('status', \App\Models\Product::STATUS_ACTIVE)
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->limit(self::PREVIEW_LIMIT)
            ->get();
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

    #[Computed]
    public function packageCategories()
    {
        return Category::query()
            ->ofType(Category::TYPE_PACKAGE)
            ->active()
            ->ordered()
            ->get();
    }

    #[Computed]
    public function productCategories()
    {
        return Category::query()
            ->ofType(Category::TYPE_PRODUCT)
            ->active()
            ->ordered()
            ->get();
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'products'  => \App\Models\Product::where('status', \App\Models\Product::STATUS_ACTIVE)->count(),
            'packages'  => Package::where('status', Package::STATUS_ACTIVE)->count(),
            'versions'  => \App\Models\PackageVersion::where('status', \App\Models\PackageVersion::STATUS_ACTIVE)->count(),
            'plans'     => \App\Models\SubscriptionPlan::where('is_active', true)->count(),
            'customers' => \App\Models\PackagePurchase::where('status', 'paid')->distinct('customer_id')->count('customer_id'),
        ];
    }

    public function render()
    {
        return view('livewire.shop.home');
    }
}
