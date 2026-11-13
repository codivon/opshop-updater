<?php

namespace App\Livewire\Inventory;

use App\Models\Package;
use App\Models\PackageLicense;
use App\Models\PackagePurchase;
use App\Models\PackageVersion;
use App\Models\Product;
use App\Models\ProductPurchase;
use App\Models\ProductVersion;
use App\Models\TemplateLicense;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * نمای کامل فروشگاه (/admin/inventory) — همهٔ پکیج‌ها، محصولات و لایسنس‌ها
 * در یک صفحه با جزئیات کامل: این پکیج چند نسخه دارد؟ چند طرح قیمت دارد؟
 * لایسنس‌هایش در چه وضعیتی‌اند؟ چند فروش پرداخت‌شده داشته؟ و… (درخواست کاربر)
 *
 * - سه تب سمت سرور (پکیج‌ها / محصولات / لایسنس‌ها)؛ هر تب فقط کوئری خودش را اجرا می‌کند.
 * - ردیف پکیج/محصول قابل بازشدن است: جدول نسخه‌ها (و برای پکیج: جدول طرح‌های قیمت)
 *   داخل همان ردیف رندر می‌شود.
 * - تب لایسنس‌ها دو زیرزبانه دارد: لایسنس پکیج و لایسنس قالب (مثل صفحهٔ اختصاصی لایسنس‌ها).
 * - این صفحه فقط «نمایش» است؛ ویرایش‌ها به صفحات اختصاصی هر بخش ارجاع می‌شوند.
 */
#[Layout('components.layouts.app')]
#[Title('نمای کامل فروشگاه')]
class Index extends Component
{
    use WithPagination;

    /** تب فعال: packages | products | licenses */
    #[Url]
    public string $tab = 'packages';

    /** زیرزبانهٔ تب لایسنس‌ها: package | template */
    #[Url]
    public string $license_type = 'package';

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    /** شناسهٔ پکیج/محصولی که ردیف جزئیاتش باز است (null = همه بسته) */
    public ?int $expandedId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function switchTab(string $tab): void
    {
        if (! in_array($tab, ['packages', 'products', 'licenses'], true)) {
            return;
        }

        $this->tab = $tab;
        $this->expandedId = null;
        $this->resetPage();
    }

    public function switchLicenseType(string $type): void
    {
        if (! in_array($type, ['package', 'template'], true)) {
            return;
        }

        $this->license_type = $type;
        $this->resetPage();
    }

    /** باز/بستن ردیف جزئیات (نسخه‌ها و طرح‌های قیمت) */
    public function toggleExpand(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    /* ---------------------------------------------------------------- */
    /*  Queries — هر تب فقط دادهٔ خودش */
    /* ---------------------------------------------------------------- */

    #[Computed]
    public function records()
    {
        return match ($this->tab) {
            'products' => $this->products(),
            'licenses' => $this->license_type === 'template' ? $this->templateLicenses() : $this->packageLicenses(),
            default => $this->packages(),
        };
    }

    private function packages()
    {
        return Package::query()
            ->with([
                'project:id,name',
                'categoryModel:id,name',
                'latestVersion:id,package_id,version',
                // جزئیات ردیف باز‌شونده: نسخه‌ها + طرح‌های قیمت
                'versions:id,package_id,version,type,status,release_date,file_size,downloads_count,is_mandatory',
                'pricingPlans:id,package_id,name,duration_months,price,discount_price,is_one_time,is_active,sort_order',
            ])
            ->withCount([
                'versions',
                'versions as active_versions_count' => fn ($q) => $q->where('status', PackageVersion::STATUS_ACTIVE),
                'versions as draft_versions_count' => fn ($q) => $q->where('status', PackageVersion::STATUS_DRAFT),
                'versions as archived_versions_count' => fn ($q) => $q->where('status', PackageVersion::STATUS_ARCHIVED),
                'pricingPlans',
                'pricingPlans as active_plans_count' => fn ($q) => $q->where('is_active', true),
                'licenses',
                'licenses as active_licenses_count' => fn ($q) => $q->where('status', PackageLicense::STATUS_ACTIVE),
                'licenses as expired_licenses_count' => fn ($q) => $q->where('status', PackageLicense::STATUS_EXPIRED),
                'licenses as revoked_licenses_count' => fn ($q) => $q->where('status', PackageLicense::STATUS_REVOKED),
                'purchases as paid_purchases_count' => fn ($q) => $q->where('status', PackagePurchase::STATUS_PAID),
            ])
            ->withSum(['purchases as paid_revenue_sum' => fn ($q) => $q->where('status', PackagePurchase::STATUS_PAID)], 'amount')
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('slug', 'like', "%{$this->search}%")))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(10);
    }

    private function products()
    {
        return Product::query()
            ->with([
                'category:id,name',
                'latestVersion:id,product_id,version',
                'versions:id,product_id,version,type,status,release_date,file_size,downloads_count,is_mandatory',
            ])
            ->withCount([
                'versions',
                'versions as active_versions_count' => fn ($q) => $q->where('status', ProductVersion::STATUS_ACTIVE),
                'versions as draft_versions_count' => fn ($q) => $q->where('status', ProductVersion::STATUS_DRAFT),
                'versions as archived_versions_count' => fn ($q) => $q->where('status', ProductVersion::STATUS_ARCHIVED),
                'purchases as paid_purchases_count' => fn ($q) => $q->where('status', ProductPurchase::STATUS_PAID),
            ])
            ->withSum(['purchases as paid_revenue_sum' => fn ($q) => $q->where('status', ProductPurchase::STATUS_PAID)], 'amount')
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('slug', 'like', "%{$this->search}%")))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(10);
    }

    private function packageLicenses()
    {
        return PackageLicense::query()
            ->with(['package:id,name,slug', 'customer:id,name,phone', 'purchase:id,transaction_id,status'])
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('license_key', 'like', "%{$this->search}%")
                ->orWhereHas('customer', fn ($cq) => $cq
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%"))
                ->orWhereHas('package', fn ($pq) => $pq
                    ->where('name', 'like', "%{$this->search}%"))))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->latest()
            ->paginate(12);
    }

    private function templateLicenses()
    {
        return TemplateLicense::query()
            ->with(['template:id,name,slug', 'customer:id,name,phone', 'purchase:id,transaction_id,status'])
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('license_key', 'like', "%{$this->search}%")
                ->orWhereHas('customer', fn ($cq) => $cq
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%"))
                ->orWhereHas('template', fn ($tq) => $tq
                    ->where('name', 'like', "%{$this->search}%"))))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->latest()
            ->paginate(12);
    }

    /** آمار کلی بالای صفحه — مستقل از تب و فیلتر فعال */
    #[Computed]
    public function stats(): array
    {
        return [
            'packages' => Package::count(),
            'products' => Product::count(),
            'versions' => PackageVersion::count() + ProductVersion::count(),
            'licenses_active' => PackageLicense::where('status', PackageLicense::STATUS_ACTIVE)->count()
                + TemplateLicense::where('status', TemplateLicense::STATUS_ACTIVE)->count(),
            'paid_purchases' => PackagePurchase::where('status', PackagePurchase::STATUS_PAID)->count()
                + ProductPurchase::where('status', ProductPurchase::STATUS_PAID)->count(),
        ];
    }

    public function render()
    {
        return view('livewire.inventory.index');
    }
}
