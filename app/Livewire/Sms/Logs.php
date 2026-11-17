<?php

namespace App\Livewire\Sms;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Livewire\Concerns\WithToasts;
use App\Models\SmsLog;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('لاگ پیامک‌ها')]
class Logs extends Component
{
    use AuthorizesAdmin;
    use WithPagination, WithToasts;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    public ?int $showId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function records()
    {
        return SmsLog::query()
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('mobile', 'like', "%{$this->search}%")
                ->orWhere('template_key', 'like', "%{$this->search}%")
                ->orWhere('message', 'like', "%{$this->search}%")))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->latest('id')
            ->paginate(20);
    }

    public function show(int $id): void
    {
        $this->showId = $id;
    }

    public function render()
    {
        return view('livewire.sms.logs', [
            'detail' => $this->showId ? SmsLog::find($this->showId) : null,
        ]);
    }
}
