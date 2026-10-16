<?php

namespace App\Livewire\Comments;

use App\Livewire\Concerns\WithToasts;
use App\Models\Product;
use App\Models\ProductComment;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('دیدگاه‌های محصولات')]
class Index extends Component
{
    use WithToasts, WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $productFilter = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingProductFilter(): void
    {
        $this->resetPage();
    }

    public function approve(int $id): void
    {
        ProductComment::findOrFail($id)->approve();
        $this->toast('دیدگاه تأیید و منتشر شد.');
    }

    public function reject(int $id): void
    {
        ProductComment::findOrFail($id)->reject();
        $this->toast('دیدگاه رد شد.');
    }

    public function delete(int $id): void
    {
        ProductComment::findOrFail($id)->delete();
        $this->toast('دیدگاه حذف شد.');
    }

    #[Computed]
    public function products()
    {
        return Product::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    #[Computed]
    public function records()
    {
        return ProductComment::query()
            ->with('product:id,name,slug')
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('body', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->productFilter !== '', fn ($q) => $q->where('product_id', $this->productFilter))
            ->latest('id')
            ->paginate(15);
    }

    public function render()
    {
        return view('livewire.comments.index');
    }
}
