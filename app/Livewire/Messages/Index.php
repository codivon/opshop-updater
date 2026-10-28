<?php

namespace App\Livewire\Messages;

use App\Livewire\Concerns\WithToasts;
use App\Models\ContactMessage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * صندوق پیام‌های «تماس با ما» (/admin/messages) — Task 56.
 * درخواست‌های قالب/پروژه/استارتاپ اختصاصی و مشاوره؛
 * مشاهده → خوانده‌شده، خواندن/نخوانده toggle، حذف.
 */
#[Layout('components.layouts.app')]
#[Title('پیام‌های تماس')]
class Index extends Component
{
    use WithToasts, WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $typeFilter = '';

    /** مودال مشاهدهٔ پیام */
    public bool $showViewModal = false;
    public ?int $viewingId = null;

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

    /** باز کردن مودال مشاهده + خوانده‌شده کردن */
    public function view(int $id): void
    {
        $message = ContactMessage::findOrFail($id);

        if ($message->isNew()) {
            $message->update(['status' => ContactMessage::STATUS_READ]);
        }

        $this->viewingId = $message->id;
        $this->showViewModal = true;
    }

    /** تغییر وضعیت خوانده‌شده/جدید */
    public function toggleRead(int $id): void
    {
        $message = ContactMessage::findOrFail($id);
        $message->update([
            'status' => $message->isNew() ? ContactMessage::STATUS_READ : ContactMessage::STATUS_NEW,
        ]);

        $this->toast($message->isNew() ? 'پیام به «جدید» برگشت.' : 'پیام خوانده‌شده شد.');
    }

    public function delete(int $id): void
    {
        ContactMessage::findOrFail($id)->delete();
        $this->toast('پیام حذف شد.');
    }

    public function getTypesProperty(): array
    {
        return ContactMessage::TYPES;
    }

    #[Computed]
    public function records()
    {
        return ContactMessage::query()
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")
                ->orWhere('message', 'like', "%{$this->search}%")))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->typeFilter !== '', fn ($q) => $q->where('request_type', $this->typeFilter))
            ->latest('id')
            ->paginate(15);
    }

    public function render()
    {
        return view('livewire.messages.index');
    }
}
