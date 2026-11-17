<?php

namespace App\Livewire\Subscriptions;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Livewire\Concerns\WithBulkActions;
use App\Livewire\Concerns\WithToasts;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Subscription;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('اشتراک‌ها')]
class Index extends Component
{
    use AuthorizesAdmin;
    use WithBulkActions, WithPagination, WithToasts;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $customer_id = '';

    #[Url]
    public string $project_id = '';

    /** فیلتر نمایش/ترتیب: جدیدترین، قدیمی‌ترین، انقضا، تاریخ شروع و… */
    #[Url]
    public string $sort = 'newest';

    /** @var array<string, mixed> */
    public array $form = [];

    public bool $showModal = false;

    public ?int $editingId = null;

    public ?int $deleteId = null;

    /** مودال تمدید */
    public ?int $extendId = null;

    public int $extendMonths = 12;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedCustomerId(): void
    {
        $this->resetPage();
    }

    public function updatedProjectId(): void
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
        return Subscription::query()
            ->with(['customer:id,name,email', 'project:id,name'])
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->whereHas('customer', fn ($c) => $c
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%"))
                ->orWhereHas('project', fn ($p) => $p
                    ->where('name', 'like', "%{$this->search}%"))
                ->orWhere('description', 'like', "%{$this->search}%")))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->customer_id, fn ($q) => $q->where('customer_id', $this->customer_id))
            ->when($this->project_id, fn ($q) => $q->where('project_id', $this->project_id))
            ->when($this->sort === 'newest', fn ($q) => $q->latest())
            ->when($this->sort === 'oldest', fn ($q) => $q->oldest())
            ->when($this->sort === 'id_desc', fn ($q) => $q->orderByDesc('id'))
            ->when($this->sort === 'id_asc', fn ($q) => $q->orderBy('id'))
            ->when($this->sort === 'expires_soon', fn ($q) => $q->orderByRaw('expires_at is null')->orderBy('expires_at'))
            ->when($this->sort === 'expires_late', fn ($q) => $q->orderByRaw('expires_at is null')->orderByDesc('expires_at'))
            ->when($this->sort === 'newest_start', fn ($q) => $q->orderByDesc('start_date'))
            ->when($this->sort === 'oldest_start', fn ($q) => $q->orderBy('start_date'))
            ->paginate(12);
    }

    #[Computed]
    public function customers()
    {
        return Customer::query()->orderBy('name')->get(['id', 'name', 'status']);
    }

    #[Computed]
    public function projects()
    {
        return Project::query()->orderBy('name')->get(['id', 'name', 'status']);
    }

    /* ---------------------------------------------------------------- */
    /*  CRUD */
    /* ---------------------------------------------------------------- */

    public function openCreate(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->form = [
            'customer_id' => '',
            'project_id' => '',
            'price' => '',
            'discount' => '',
            'payment_status' => 'pending',
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(12)->toDateString(),
            'description' => '',
        ];
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $subscription = Subscription::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $subscription->id;
        $this->form = [
            'customer_id' => (string) $subscription->customer_id,
            'project_id' => (string) $subscription->project_id,
            'price' => (string) (float) $subscription->price,
            'discount' => (string) (float) ($subscription->discount ?? 0),
            'payment_status' => $subscription->payment_status,
            'status' => $subscription->status,
            'start_date' => $subscription->start_date?->toDateString() ?? now()->toDateString(),
            'end_date' => $subscription->end_date?->toDateString()
                ?? $subscription->expires_at?->toDateString()
                ?? now()->addMonths(12)->toDateString(),
            'description' => (string) ($subscription->description ?? ''),
        ];
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate();

        $data = $validated['form'];
        $data['customer_id'] = (int) $data['customer_id'];
        $data['project_id'] = (int) $data['project_id'];
        $data['discount'] = blank($data['discount'] ?? null) ? 0 : $data['discount'];
        $data['description'] = blank($data['description'] ?? null) ? null : $data['description'];

        // تاریخ انقضا از تاریخ پایان محاسبه می‌شود؛
        // مبلغ نهایی (final_amount) توسط هوک saving مدل به‌صورت خودکار محاسبه می‌شود.
        $data['expires_at'] = Carbon::parse($data['end_date'])->endOfDay();

        if ($this->editingId) {
            Subscription::findOrFail($this->editingId)->update($data);
            $this->toast('اشتراک با موفقیت به‌روزرسانی شد.');
        } else {
            Subscription::create($data);
            $this->toast('اشتراک با موفقیت ثبت شد.');
        }

        $this->showModal = false;
    }

    /* ---------------------------------------------------------------- */
    /*  تمدید اشتراک */
    /* ---------------------------------------------------------------- */

    public function openExtend(int $id): void
    {
        $this->resetValidation();
        $this->extendId = $id;
        $this->extendMonths = 12;
    }

    public function extend(): void
    {
        $this->validate([
            'extendMonths' => ['required', 'integer', 'min:1', 'max:36'],
        ], [
            'extendMonths.required' => 'مدت تمدید الزامی است.',
            'extendMonths.integer' => 'مدت تمدید باید عدد صحیح باشد.',
            'extendMonths.min' => 'مدت تمدید حداقل ۱ ماه است.',
            'extendMonths.max' => 'مدت تمدید حداکثر ۳۶ ماه است.',
        ]);

        $subscription = Subscription::findOrFail($this->extendId ?? 0);

        // اگر اشتراک هنوز منقضی نشده، از تاریخ انقضای فعلی تمدید می‌شود؛
        // در غیر این صورت از امروز شروع می‌شود.
        $base = ($subscription->expires_at && $subscription->expires_at->isFuture())
            ? $subscription->expires_at->copy()
            : now();

        $newExpiry = $base->copy()->addMonths((int) $this->extendMonths);

        $subscription->update([
            'end_date' => $newExpiry->toDateString(),
            'expires_at' => $newExpiry,
            'status' => 'active',
        ]);

        $this->extendId = null;
        $this->toast('اشتراک تا '.fa_num(verta_date($newExpiry)).' تمدید شد.');
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

        $count = Subscription::query()->whereIn('id', $ids)->delete();

        $this->toast(fa_num($count).' اشتراک حذف شد.');
    }

    public function delete(): void
    {
        $subscription = Subscription::findOrFail($this->deleteId ?? 0);

        $subscription->delete();
        $this->deleteId = null;
        $this->toast('اشتراک حذف شد.');
    }

    /** @return array<string, array<int, string>|string> */
    protected function rules(): array
    {
        return [
            'form.customer_id' => ['required', 'integer', 'exists:customers,id'],
            'form.project_id' => ['required', 'integer', 'exists:projects,id'],
            'form.price' => ['required', 'numeric', 'min:0'],
            'form.discount' => ['nullable', 'numeric', 'min:0'],
            'form.payment_status' => ['required', 'in:pending,paid,failed'],
            'form.status' => ['required', 'in:active,expired,suspended'],
            'form.start_date' => ['required', 'date'],
            'form.end_date' => ['required', 'date', 'after_or_equal:form.start_date'],
            'form.description' => ['nullable', 'string'],
        ];
    }

    protected function messages(): array
    {
        return [
            'form.customer_id.required' => 'مشتری را انتخاب کنید.',
            'form.customer_id.exists' => 'مشتری انتخاب‌شده معتبر نیست.',
            'form.project_id.required' => 'پروژه را انتخاب کنید.',
            'form.project_id.exists' => 'پروژه انتخاب‌شده معتبر نیست.',
            'form.price.required' => 'قیمت اشتراک الزامی است.',
            'form.price.numeric' => 'قیمت باید عدد باشد.',
            'form.discount.numeric' => 'تخفیف باید عدد باشد.',
            'form.payment_status.required' => 'وضعیت پرداخت را انتخاب کنید.',
            'form.status.required' => 'وضعیت اشتراک را انتخاب کنید.',
            'form.start_date.required' => 'تاریخ شروع الزامی است.',
            'form.end_date.required' => 'تاریخ پایان الزامی است.',
            'form.end_date.after_or_equal' => 'تاریخ پایان باید بعد از تاریخ شروع باشد.',
        ];
    }

    public function render()
    {
        return view('livewire.subscriptions.index');
    }
}
