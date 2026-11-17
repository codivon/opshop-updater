<?php

namespace App\Livewire\Subscriptions;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Livewire\Concerns\WithBulkActions;
use App\Livewire\Concerns\WithToasts;
use App\Models\Subscription;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('درخواست‌های اشتراک')]
class Orders extends Component
{
    use AuthorizesAdmin;
    use WithBulkActions, WithPagination, WithToasts;

    #[Url]
    public string $search = '';

    /** وضعیت پرداخت: ''=همه، paid/pending/failed */
    #[Url]
    public string $status = '';

    /** وضعیت تأیید مدیر: ''=همه، pending/approved/rejected */
    #[Url]
    public string $admin_status = '';

    #[Url]
    public string $plan_id = '';

    /** فیلتر نمایش/ترتیب */
    #[Url]
    public string $sort = 'newest';

    public ?int $rejectId = null;

    public string $rejectReason = '';

    public ?int $viewId = null;

    /** آی‌دی سفارش برای حذف تکی */
    public ?int $deleteId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedAdminStatus(): void
    {
        $this->resetPage();
    }

    public function updatedPlanId(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'status', 'admin_status', 'plan_id', 'sort');
        $this->resetPage();
    }

    #[Computed]
    public function records()
    {
        return SubscriptionOrder::query()
            ->with(['customer:id,name,email,phone', 'plan:id,name,slug,duration_months', 'subscription:id,status'])
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('transaction_id', 'like', "%{$this->search}%")
                ->orWhereHas('customer', fn ($cq) => $cq
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%"))
                ->orWhereHas('plan', fn ($pq) => $pq->where('name', 'like', "%{$this->search}%"))))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->admin_status, fn ($q) => $q->where('admin_status', $this->admin_status))
            ->when($this->plan_id, fn ($q) => $q->where('subscription_plan_id', (int) $this->plan_id))
            ->when($this->sort === 'newest', fn ($q) => $q->latest())
            ->when($this->sort === 'oldest', fn ($q) => $q->oldest())
            ->when($this->sort === 'id_desc', fn ($q) => $q->orderByDesc('id'))
            ->when($this->sort === 'id_asc', fn ($q) => $q->orderBy('id'))
            ->when($this->sort === 'amount_desc', fn ($q) => $q->orderByDesc('final_amount'))
            ->when($this->sort === 'amount_asc', fn ($q) => $q->orderBy('final_amount'))
            ->when($this->sort === 'paid_newest', fn ($q) => $q->latest('paid_at'))
            ->when($this->sort === 'approved_newest', fn ($q) => $q->latest('approved_at'))
            ->paginate(12);
    }

    #[Computed]
    public function plans()
    {
        return SubscriptionPlan::query()->orderBy('name')->get(['id', 'name']);
    }

    /* ---------------------------------------------------------------- */
    /*  Actions */
    /* ---------------------------------------------------------------- */

    public function approve(int $id, SubscriptionService $service): void
    {
        $order = SubscriptionOrder::findOrFail($id);

        try {
            $service->approve($order);

            $this->toast('درخواست تأیید شد؛ اشتراک فعال و لایسنس پکیج‌های همراه صادر گردید.');
        } catch (\Throwable $e) {
            $this->toast($e->getMessage(), 'error');
        }
    }

    public function reject(): void
    {
        $order = SubscriptionOrder::findOrFail($this->rejectId ?? 0);

        try {
            app(SubscriptionService::class)->reject(
                $order,
                trim($this->rejectReason) !== '' ? trim($this->rejectReason) : null
            );

            $this->rejectId = null;
            $this->rejectReason = '';
            $this->toast('درخواست اشتراک رد شد.');
        } catch (\Throwable $e) {
            $this->toast($e->getMessage(), 'error');
        }
    }

    public function recheck(int $id): void
    {
        $order = SubscriptionOrder::findOrFail($id);

        if (! $order->transaction_id) {
            $this->toast('این سفارش تراکنش درگاه ندارد (رایگان).', 'warning');

            return;
        }

        $result = app(SubscriptionService::class)->verifyPayment($order->transaction_id);

        $this->toast($result['message'] ?? 'وضعیت به‌روزرسانی شد.', $result['paid'] ?? false ? 'success' : 'warning');
    }

    /** حذف تکی سفارش */
    public function delete(): void
    {
        $order = SubscriptionOrder::findOrFail($this->deleteId ?? 0);

        // حذف اشتراک صادرشده همزمان با سفارش (اگر exists)
        $order->subscription?->delete();

        $id = $order->id;
        $order->delete();

        $this->deleteId = null;
        $this->toast("سفارش #{$id} حذف شد.");
    }

    /* ---------------------------------------------------------------- */
    /*  Bulk selection (WithBulkActions) */
    /* ---------------------------------------------------------------- */

    public function bulkPageIds(): array
    {
        return $this->records->getCollection()->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    public function deleteSelectedRecords(): void
    {
        $ids = array_map('intval', $this->selectedIds);

        // اشتراک‌های صادرشده از این سفارش‌ها هم حذف شوند
        $subscriptionIds = SubscriptionOrder::whereIn('id', $ids)->whereNotNull('subscription_id')->pluck('subscription_id');
        Subscription::whereIn('id', $subscriptionIds)->delete();

        $count = SubscriptionOrder::query()->whereIn('id', $ids)->delete();

        $this->toast(fa_num($count).' سفارش حذف شد.');
    }

    public function render()
    {
        return view('livewire.subscriptions.orders');
    }
}
