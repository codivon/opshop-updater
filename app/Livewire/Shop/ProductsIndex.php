<?php

namespace App\Livewire\Shop;

use App\Livewire\Concerns\WithToasts;
use App\Models\Category;
use App\Models\Product;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * صفحه‌ی مستقل «پروژه‌های قابل‌فروش» — فهرست کامل با فیلترهای مدرن.
 * خرید مهمان بدون ثبت‌نام؛ پس از پرداخت همان‌جا دانلود می‌شود.
 */
#[Layout('components.layouts.shop')]
#[Title('پروژه‌های آماده')]
class ProductsIndex extends Component
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

    /** عبارت SQL «قیمت مؤثر» محصول */
    private function productEffectivePriceSql(): string
    {
        return "CASE WHEN products.is_free = 1 OR products.price <= 0 OR products.discount >= 100 THEN 0 "
            . "WHEN products.discount > 0 THEN ROUND(products.price * (100 - products.discount) / 100) "
            . "ELSE products.price END";
    }

    #[Computed]
    public function records()
    {
        $effective = $this->productEffectivePriceSql();

        return Product::query()
            ->with(['category:id,name', 'latestVersion:id,product_id,version'])
            ->withCount(['comments' => fn ($q) => $q->where('status', 'approved')])
            ->where('status', Product::STATUS_ACTIVE)
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('slug', 'like', "%{$this->search}%")
                ->orWhere('short_description', 'like', "%{$this->search}%")))
            ->when($this->category !== '', function ($q) {
                // دسته والد + همه زیردسته‌هایش (فیلتر درختی)
                $ids = Category::subtreeIds((int) $this->category)->all();
                $q->whereIn('category_id', $ids);
            })
            ->when($this->priceFilter === 'free', fn ($q) => $q->whereRaw("{$effective} <= 0"))
            ->when($this->priceFilter === 'paid', fn ($q) => $q->whereRaw("{$effective} > 0"))
            ->when($this->sort === 'newest', fn ($q) => $q->orderBy('sort_order')->orderByDesc('id'))
            ->when($this->sort === 'oldest', fn ($q) => $q->orderBy('sort_order')->orderBy('id'))
            ->when($this->sort === 'cheap', fn ($q) => $q->orderByRaw("{$effective} ASC")->orderByDesc('id'))
            ->when($this->sort === 'expensive', fn ($q) => $q->orderByRaw("{$effective} DESC")->orderByDesc('id'))
            ->when($this->sort === 'popular', fn ($q) => $q->orderByDesc('sales_count')->orderByDesc('views_count')->orderByDesc('id'))
            ->paginate(12);
    }

    /** دسته‌بندی‌های محصول با تعداد */
    #[Computed]
    public function categories()
    {
        return Category::shopTree(Category::TYPE_PRODUCT);
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
        return view('livewire.shop.products-index');
    }
}
