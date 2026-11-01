<?php

namespace App\Livewire\Tickets;

use App\Livewire\Concerns\WithToasts;
use App\Models\Ticket;
use App\Models\TicketReply;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * تیکت‌های پشتیبانی (/admin/tickets).
 *
 * گات سمت مشتری است: فقط مشتریِ دارای اشتراک فعال می‌تواند تیکت ثبت/پاسخ بدهد
 * (SubscriptionService::canCreateTicket در پروفایل مشتری اعمال می‌شود)؛
 * مدیر فقط می‌بیند، پاسخ می‌دهد و وضعیت را عوض می‌کند.
 */
#[Layout('components.layouts.app')]
#[Title('تیکت‌های پشتیبانی')]
class Index extends Component
{
    use WithToasts, WithPagination;

    public string $search = '';

    /** '' | open | answered | closed */
    public string $statusFilter = '';

    /* ---- مودال مشاهده/گفتگو ---- */
    public bool $showViewModal = false;

    public ?int $viewingId = null;

    public string $replyText = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    /* ---------------------------------------------------------------- */
    /*  مودال گفتگو                                                      */
    /* ---------------------------------------------------------------- */

    public function view(int $id): void
    {
        $this->viewingId = $id;
        $this->replyText = '';
        $this->showViewModal = true;
    }

    /** پاسخ مدیر → وضعیت تیکت «پاسخ داده شد» */
    public function reply(): void
    {
        $ticket = Ticket::find($this->viewingId ?? 0);

        if (!$ticket) {
            $this->toast('تیکت یافت نشد.', 'error');

            return;
        }

        $data = $this->validate([
            'replyText' => ['required', 'string', 'min:2', 'max:5000'],
        ], [
            'replyText.required' => 'متن پاسخ الزامی است.',
            'replyText.min'      => 'متن پاسخ حداقل ۲ حرف است.',
            'replyText.max'      => 'متن پاسخ حداکثر ۵۰۰۰ حرف است.',
        ]);

        TicketReply::create([
            'ticket_id'   => $ticket->id,
            'author_type' => TicketReply::AUTHOR_ADMIN,
            'body'        => trim($data['replyText']),
        ]);

        $ticket->update(['status' => Ticket::STATUS_ANSWERED, 'last_reply_at' => now()]);

        $this->reset('replyText');
        unset($this->records, $this->stats);

        $this->toast('پاسخ ثبت شد و تیکت به «پاسخ داده شد» تغییر کرد.');
    }

    /** تغییر وضعیت دستی: بستن / بازکردن / برگرداندن به انتظار */
    public function setStatus(int $id, string $status): void
    {
        if (!in_array($status, [Ticket::STATUS_OPEN, Ticket::STATUS_ANSWERED, Ticket::STATUS_CLOSED], true)) {
            return;
        }

        $ticket = Ticket::find($id);

        if (!$ticket) {
            $this->toast('تیکت یافت نشد.', 'error');

            return;
        }

        $ticket->update(['status' => $status, 'last_reply_at' => now()]);

        unset($this->records, $this->stats);

        $this->toast('وضعیت تیکت به «' . $ticket->statusLabel() . '» تغییر کرد.');
    }

    public function delete(int $id): void
    {
        Ticket::findOrFail($id)->delete();

        if ($this->viewingId === $id) {
            $this->showViewModal = false;
            $this->viewingId = null;
        }

        unset($this->records, $this->stats);

        $this->toast('تیکت و گفتگوی آن حذف شد.');
    }

    /* ---------------------------------------------------------------- */
    /*  Computed                                                         */
    /* ---------------------------------------------------------------- */

    #[Computed]
    public function records()
    {
        $search = trim($this->search);

        return Ticket::query()
            ->with(['customer:id,name,update_code,phone', 'subscription:id,subscription_plan_id,status'])
            ->withCount('replies')
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('subject', 'like', "%{$search}%")
                ->orWhereHas('customer', fn ($c) => $c->where(fn ($x) => $x
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('update_code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%"))))
                ->orWhereHas('replies', fn ($r) => $r->where('body', 'like', "%{$search}%")))
            ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'answered' THEN 1 ELSE 2 END")
            ->orderByDesc('id')
            ->paginate(12);
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total'    => Ticket::count(),
            'open'     => Ticket::where('status', Ticket::STATUS_OPEN)->count(),
            'answered' => Ticket::where('status', Ticket::STATUS_ANSWERED)->count(),
            'closed'   => Ticket::where('status', Ticket::STATUS_CLOSED)->count(),
        ];
    }

    /** تیکتِ در حال مشاهده (برای مودال) */
    #[Computed]
    public function viewing()
    {
        if (!$this->viewingId) {
            return null;
        }

        return Ticket::query()
            ->with(['customer:id,name,update_code,phone,website_url', 'replies'])
            ->find($this->viewingId);
    }

    public function render()
    {
        return view('livewire.tickets.index');
    }
}
