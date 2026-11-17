<?php

namespace App\Livewire\Purchases;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Livewire\Concerns\WithBulkActions;
use App\Livewire\Concerns\WithToasts;
use App\Models\PackagePurchase;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('خریدها')]
class Index extends Component
{
    use AuthorizesAdmin;
    use WithBulkActions, WithPagination, WithToasts;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    /** فیلتر نمایش/ترتیب: جدیدترین، قدیمی‌ترین، مبلغ، تاریخ پرداخت و… */
    #[Url]
    public string $sort = 'newest';

    /** آی‌دی خرید برای حذف تکی */
    public ?int $deleteId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function records()
    {
        return PackagePurchase::query()
            ->with(['package:id,name,slug', 'customer:id,name,phone', 'version:id,version'])
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('transaction_id', 'like', "%{$this->search}%")
                ->orWhereHas('customer', fn ($cq) => $cq
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%"))))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->sort === 'newest', fn ($q) => $q->latest())
            ->when($this->sort === 'oldest', fn ($q) => $q->oldest())
            ->when($this->sort === 'id_desc', fn ($q) => $q->orderByDesc('id'))
            ->when($this->sort === 'id_asc', fn ($q) => $q->orderBy('id'))
            ->when($this->sort === 'amount_desc', fn ($q) => $q->orderByDesc('amount'))
            ->when($this->sort === 'amount_asc', fn ($q) => $q->orderBy('amount'))
            ->when($this->sort === 'paid_newest', fn ($q) => $q->orderByDesc('paid_at'))
            ->when($this->sort === 'paid_oldest', fn ($q) => $q->orderByRaw('paid_at is null')->orderBy('paid_at'))
            ->paginate(12);
    }

    /* ---------------------------------------------------------------- */
    /*  Bulk selection + delete (WithBulkActions) */
    /* ---------------------------------------------------------------- */

    public function bulkPageIds(): array
    {
        return $this->records->getCollection()->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    public function deleteSelectedRecords(): void
    {
        $ids = array_map('intval', $this->selectedIds);

        // حذف خرید امن است: لایسنس‌های مرتبط با purchase_id=null باقی می‌مانند (nullOnDelete)
        $count = PackagePurchase::query()->whereIn('id', $ids)->delete();

        $this->toast(fa_num($count).' خرید حذف شد.');
    }

    /** حذف تکی خرید */
    public function delete(): void
    {
        $purchase = PackagePurchase::findOrFail($this->deleteId ?? 0);

        $id = $purchase->id;
        $purchase->delete();

        $this->deleteId = null;
        $this->toast('خرید #'.fa_num($id).' حذف شد.');
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'revenue' => (int) PackagePurchase::where('status', PackagePurchase::STATUS_PAID)->sum('amount'),
            'pending' => PackagePurchase::where('status', PackagePurchase::STATUS_PENDING)->count(),
            'failed' => PackagePurchase::where('status', PackagePurchase::STATUS_FAILED)->count(),
        ];
    }

    public function render()
    {
        return view('livewire.purchases.index');
    }
}
