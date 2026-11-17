<?php

namespace App\Livewire\SubscriptionRequests;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Livewire\Concerns\WithToasts;
use App\Models\SubscriptionRequest;
use App\Services\SubscriptionService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

#[Layout('components.layouts.app')]
#[Title('درخواست‌ها و تأییدها')]
class Index extends Component
{
    use AuthorizesAdmin;
    use WithPagination, WithToasts;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $payment = '';

    #[Url]
    public string $sort = 'newest';

    /** مودال جزئیات درخواست */
    public ?int $viewId = null;

    /** مودال تأیید و فعال‌سازی */
    public ?int $approveId = null;

    public string $adminNote = '';

    /** مودال رد درخواست */
    public ?int $rejectId = null;

    public string $rejectNote = '';

    /* ---------------------------------------------------------------- */
    /*  فیلترها */
    /* ---------------------------------------------------------------- */

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedPayment(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'status', 'payment', 'sort']);
        $this->resetPage();
    }

    /* ---------------------------------------------------------------- */
    /*  داده‌ها */
    /* ---------------------------------------------------------------- */

    #[Computed]
    public function records()
    {
        return SubscriptionRequest::query()
            ->with([
                'customer:id,name,email',
                'plan:id,name,duration_months,is_free,is_one_time,features,price,discount_price',
                'plan.packages:id,name,slug',
            ])
            ->withCount('licenses')
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->whereHas('customer', fn ($c) => $c
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%"))
                ->orWhereHas('plan', fn ($p) => $p
                    ->where('name', 'like', "%{$this->search}%"))
                ->orWhere('transaction_id', 'like', "%{$this->search}%")))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->payment, fn ($q) => $q->where('payment_status', $this->payment))
            ->when(in_array($this->sort, ['oldest', 'amount_desc', 'amount_asc'], true), function ($q) {
                match ($this->sort) {
                    'oldest' => $q->oldest(),
                    'amount_desc' => $q->orderByDesc('amount'),
                    'amount_asc' => $q->orderBy('amount'),
                };
            }, fn ($q) => $q->latest())
            ->paginate(12);
    }

    /** آمار کارت‌های بالای صفحه */
    #[Computed]
    public function stats(): array
    {
        $pendingSettled = SubscriptionRequest::query()
            ->where('status', SubscriptionRequest::STATUS_PENDING)
            ->whereIn('payment_status', [SubscriptionRequest::PAYMENT_PAID, SubscriptionRequest::PAYMENT_FREE])
            ->get(['amount']);

        return [
            'pending_count' => $pendingSettled->count(),
            'total_pending_payment' => (int) $pendingSettled->sum('amount'),
            'awaiting_payment_count' => SubscriptionRequest::query()
                ->where('status', SubscriptionRequest::STATUS_PENDING)
                ->where('payment_status', SubscriptionRequest::PAYMENT_PENDING)
                ->count(),
            'approved_count' => SubscriptionRequest::query()
                ->where('status', SubscriptionRequest::STATUS_APPROVED)
                ->count(),
            'rejected_count' => SubscriptionRequest::query()
                ->where('status', SubscriptionRequest::STATUS_REJECTED)
                ->count(),
        ];
    }

    /** رکورد مودال جزئیات */
    #[Computed]
    public function viewRecord(): ?SubscriptionRequest
    {
        if (! $this->viewId) {
            return null;
        }

        return $this->loadTarget($this->viewId)?->loadCount('licenses');
    }

    /** رکورد مودال تأیید */
    #[Computed]
    public function approveTarget(): ?SubscriptionRequest
    {
        return $this->loadTarget($this->approveId);
    }

    /** رکورد مودال رد */
    #[Computed]
    public function rejectTarget(): ?SubscriptionRequest
    {
        return $this->loadTarget($this->rejectId);
    }

    private function loadTarget(?int $id): ?SubscriptionRequest
    {
        if (! $id) {
            return null;
        }

        return SubscriptionRequest::query()
            ->with([
                'customer:id,name,email',
                'plan:id,name,duration_months,is_free,is_one_time,features,price,discount_price',
                'plan.packages:id,name,slug',
            ])
            ->find($id);
    }

    /* ---------------------------------------------------------------- */
    /*  مودال‌ها / اکشن‌ها */
    /* ---------------------------------------------------------------- */

    public function viewRequest(int $id): void
    {
        $this->viewId = $id;
    }

    public function openApprove(int $id): void
    {
        $this->resetValidation();
        $this->viewId = null;
        $this->adminNote = '';
        $this->approveId = $id;
    }

    public function openReject(int $id): void
    {
        $this->resetValidation();
        $this->viewId = null;
        $this->rejectNote = '';
        $this->rejectId = $id;
    }

    /* ---------------------------------------------------------------- */
    /*  تأیید / رد */
    /* ---------------------------------------------------------------- */

    public function approve(): void
    {
        $this->resetValidation();
        $this->validate([
            'adminNote' => ['nullable', 'string', 'max:1000'],
        ], [
            'adminNote.string' => 'یادداشت مدیر باید متن باشد.',
            'adminNote.max' => 'یادداشت مدیر حداکثر ۱۰۰۰ حرف است.',
        ]);

        $request = SubscriptionRequest::query()->find($this->approveId ?? 0);

        if (! $request) {
            $this->approveId = null;

            return;
        }

        try {
            $licenses = app(SubscriptionService::class)->approve($request, $this->adminNote ?: null);
        } catch (RuntimeException $e) {
            $this->toast($e->getMessage(), 'error');
            $this->approveId = null;

            return;
        }

        $this->approveId = null;
        $this->adminNote = '';

        // بازگشایی مودال جزئیات برای نمایش لایسنس‌های صادرشده
        $this->viewId = $request->id;

        unset($this->records, $this->stats, $this->viewRecord);

        $this->toast('درخواست تأیید و طرح فعال شد — '.fa_num(count($licenses)).' لایسنس صادر شد.');
    }

    public function reject(): void
    {
        $this->resetValidation();
        $this->validate([
            'rejectNote' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'rejectNote.required' => 'برای رد درخواست، ذکر دلیل الزامی است.',
            'rejectNote.min' => 'دلیل رد باید حداقل ۵ حرف باشد.',
            'rejectNote.string' => 'دلیل رد باید متن باشد.',
            'rejectNote.max' => 'دلیل رد حداکثر ۱۰۰۰ حرف است.',
        ]);

        $request = SubscriptionRequest::query()->find($this->rejectId ?? 0);

        if (! $request) {
            $this->rejectId = null;

            return;
        }

        try {
            app(SubscriptionService::class)->reject($request, $this->rejectNote);
        } catch (RuntimeException $e) {
            $this->toast($e->getMessage(), 'error');
            $this->rejectId = null;

            return;
        }

        $this->rejectId = null;
        $this->rejectNote = '';

        unset($this->records, $this->stats);

        $this->toast('درخواست رد شد.');
    }

    public function render()
    {
        return view('livewire.subscription-requests.index');
    }
}
