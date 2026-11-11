<?php

namespace App\Livewire\Shop;

use App\Livewire\Concerns\WithToasts;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Template;
use App\Support\HomePage;
use App\Support\Showcase;
use Illuminate\Support\Collection;
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

        if (! $customer) {
            $this->toast('کد آپدیت نامعتبر است.', 'error');

            return;
        }

        if ($customer->status !== 'active') {
            $this->toast('حساب این مشتری غیرفعال است.', 'error');

            return;
        }

        $subscription = $customer->activePlanSubscription();

        if (! $subscription?->plan) {
            $this->toast('هیچ اشتراک فعالی برای این کد یافت نشد.', 'error');

            return;
        }

        session()->put('shop_update_code', $code);

        unset($this->currentCustomer, $this->myActiveSubscription, $this->coveredPackageIds);

        $this->toast('اشتراک فعال شما پیدا شد؛ پکیج‌های طرح «'.$subscription->plan->name.'» برای شما رایگان‌اند.');
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

        if (! $plan) {
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
            ->with(['project:id,name', 'latestVersion:id,package_id,version', 'activePricingPlans', 'categoryModel:id,name'])
            ->where('status', Package::STATUS_ACTIVE)
            ->orderByDesc('id')
            ->limit(self::PREVIEW_LIMIT)
            ->get();
    }

    #[Computed]
    public function products()
    {
        return Product::query()
            ->with(['category:id,name', 'latestVersion:id,product_id,version'])
            ->withCount(['comments' => fn ($q) => $q->where('status', 'approved')])
            ->where('status', Product::STATUS_ACTIVE)
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->limit(self::PREVIEW_LIMIT)
            ->get();
    }

    #[Computed]
    public function templates()
    {
        return Template::query()
            ->with(['category:id,name', 'latestVersion:id,template_id,version'])
            ->withCount(['comments' => fn ($q) => $q->where('status', 'approved')])
            ->where('status', Template::STATUS_ACTIVE)
            // قالب‌های واگذارشده (خرید اختصاصی) در صفحه اصلی نمایش داده نمی‌شوند
            ->where('is_sold_exclusively', false)
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->limit(self::PREVIEW_LIMIT)
            ->get();
    }

    #[Computed]
    public function featuredPlans()
    {
        return SubscriptionPlan::query()
            ->active()
            ->with(['packages:id,name,slug'])
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->limit(3)
            ->get();
    }

    /**
     * گره‌های درخت دسته‌بندی برای بخش «دسته‌بندی فروشگاه» صفحه اصلی.
     * خروجی: [ ['model' => Category ریشه, 'children' => Collection زیردسته‌ها], … ]
     * هر گره shop_count (تعداد تجمیعی خود + زیردرخت) دارد.
     */
    #[Computed]
    public function packageCategoryTree(): Collection
    {
        return $this->homeCategoryTree(Category::TYPE_PACKAGE);
    }

    #[Computed]
    public function productCategoryTree(): Collection
    {
        return $this->homeCategoryTree(Category::TYPE_PRODUCT);
    }

    #[Computed]
    public function templateCategoryTree(): Collection
    {
        return $this->homeCategoryTree(Category::TYPE_TEMPLATE);
    }

    private function homeCategoryTree(string $type): Collection
    {
        $flat = Category::shopTree($type);

        if ($flat->isEmpty()) {
            return collect();
        }

        $ids = $flat->pluck('id');

        return $flat
            ->filter(fn ($cat) => $cat->parent_id === null || ! $ids->contains($cat->parent_id))
            ->values()
            ->map(fn ($root) => [
                'model' => $root,
                'children' => $flat->where('parent_id', (int) $root->id)->values(),
            ]);
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'products' => Product::where('status', Product::STATUS_ACTIVE)->count(),
            'templates' => Template::where('status', Template::STATUS_ACTIVE)->count(),
            'packages' => Package::where('status', Package::STATUS_ACTIVE)->count(),
            'versions' => PackageVersion::where('status', PackageVersion::STATUS_ACTIVE)->count(),
            'plans' => SubscriptionPlan::where('is_active', true)->count(),
        ];
    }

    /**
     * تنظیمات ویجت‌های صفحهٔ اصلی (گروه home) — Task 56.
     * همهٔ بخش‌ها (هیرو، معرفی، آمار، چرا ما، نظرات، محصولات، تماس) از پنل
     * /admin/settings/home قابل ویرایش/خاموش‌کردن هستند.
     */
    private function homeSettingsPayload(): array
    {
        $home = HomePage::class;

        return [
            // متن‌ها (کلید => مقدار trim‌شده با پیش‌فرض)
            'hs' => collect($home::DEFAULTS)
                ->keys()
                ->mapWithKeys(fn ($key) => [$key => $home::str($key)])
                ->all(),

            // کلیدهای روشن/خاموش (کلید => bool)
            'ho' => array_combine(
                $home::BOOL_KEYS,
                array_map(fn ($key) => $home::on($key), $home::BOOL_KEYS)
            ),

            'whyItems' => $home::whyItems(),
            'trustStats' => $home::stats(),
            'testimonials' => $home::testimonials(),

            // اسلایدهای «اسلایدر معرفی» — دستی اولویت دارد، وگرنه پیش‌فرض (پکیج‌ها + InstaShop) — Task 71
            'showcaseSlides' => Showcase::slides(),

            // ترتیب رندر بخش‌ها از کلیدهای home_sort_* (پنل ← تب بخش‌ها) — Task 59
            'sections' => $home::orderedSections(),
        ];
    }

    public function render()
    {
        return view('livewire.shop.home', $this->homeSettingsPayload());
    }
}
