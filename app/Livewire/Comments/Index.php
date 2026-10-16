<?php

namespace App\Livewire\Comments;

use App\Livewire\Concerns\WithToasts;
use App\Models\Comment;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('دیدگاه‌ها')]
class Index extends Component
{
    use WithToasts, WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $typeFilter = '';   // product | package

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    public function approve(int $id): void
    {
        Comment::findOrFail($id)->approve();
        $this->toast('دیدگاه تأیید و منتشر شد.');
    }

    public function reject(int $id): void
    {
        Comment::findOrFail($id)->reject();
        $this->toast('دیدگاه رد شد.');
    }

    public function delete(int $id): void
    {
        Comment::findOrFail($id)->delete();
        $this->toast('دیدگاه حذف شد.');
    }

    public function getTypesProperty(): array
    {
        return [
            Comment::TYPE_PRODUCT => 'محصولات (پروژه‌ها)',
            Comment::TYPE_PACKAGE => 'پکیج‌ها',
        ];
    }

    #[Computed]
    public function records()
    {
        return Comment::query()
            ->with('commentable')
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('body', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->typeFilter !== '', fn ($q) => $q->where('commentable_type', $this->typeFilter))
            ->latest('id')
            ->paginate(15);
    }

    public function render()
    {
        return view('livewire.comments.index');
    }
}
