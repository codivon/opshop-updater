<?php

namespace App\Livewire\Customers;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Livewire\Concerns\WithBulkActions;
use App\Livewire\Concerns\WithToasts;
use App\Models\Customer;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('مشتریان')]
class Index extends Component
{
    use AuthorizesAdmin;
    use WithBulkActions, WithPagination, WithToasts;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    /** فیلتر نمایش/ترتیب: جدیدترین، قدیمی‌ترین، شناسه، نام و… */
    #[Url]
    public string $sort = 'newest';

    /** @var array<string, mixed> */
    public array $form = [];

    public bool $showModal = false;

    public ?int $editingId = null;

    public ?int $deleteId = null;

    /** نمایش کد آپدیت در مودال ویرایش (فقط‌خواندنی) */
    public ?string $updateCode = null;

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
        return Customer::query()
            ->withCount('subscriptions')
            ->withSum('subscriptions as total_spent', 'final_amount')
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%")
                ->orWhere('website_url', 'like', "%{$this->search}%")
                ->orWhere('update_code', 'like', "%{$this->search}%")))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->sort === 'newest', fn ($q) => $q->latest())
            ->when($this->sort === 'oldest', fn ($q) => $q->oldest())
            ->when($this->sort === 'id_desc', fn ($q) => $q->orderByDesc('id'))
            ->when($this->sort === 'id_asc', fn ($q) => $q->orderBy('id'))
            ->when($this->sort === 'name_asc', fn ($q) => $q->orderBy('name'))
            ->when($this->sort === 'name_desc', fn ($q) => $q->orderByDesc('name'))
            ->paginate(12);
    }

    /* ---------------------------------------------------------------- */
    /*  CRUD */
    /* ---------------------------------------------------------------- */

    public function openCreate(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->updateCode = null;
        $this->form = ['name' => '', 'email' => '', 'phone' => '', 'website_url' => '', 'status' => 'active'];
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $customer = Customer::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $customer->id;
        $this->updateCode = $customer->update_code;
        $this->form = $customer->only(['name', 'email', 'phone', 'website_url', 'status']);
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate();

        // update_code به‌صورت خودکار در هوک creating مدل ساخته می‌شود
        if ($this->editingId) {
            Customer::findOrFail($this->editingId)->update($validated['form']);
            $this->toast('اطلاعات مشتری با موفقیت به‌روزرسانی شد.');
        } else {
            Customer::create($validated['form']);
            $this->toast('مشتری با موفقیت ایجاد شد.');
        }

        $this->showModal = false;
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

        // اشتراک‌ها/لایسنس‌ها/خریدهای مرتبط با FK cascade حذف می‌شوند
        $count = Customer::query()->whereIn('id', $ids)->delete();

        $this->toast(fa_num($count).' مشتری حذف شد.');
    }

    public function delete(): void
    {
        $customer = Customer::findOrFail($this->deleteId ?? 0);

        $name = $customer->name;
        $customer->delete();
        $this->deleteId = null;
        $this->toast("مشتری «{$name}» حذف شد.");
    }

    /** @return array<string, array<int, string>|string> */
    protected function rules(): array
    {
        $emailUnique = $this->editingId
            ? 'unique:customers,email,'.$this->editingId
            : 'unique:customers,email';

        return [
            'form.name' => ['required', 'string', 'max:255'],
            'form.email' => ['required', 'email', 'max:255', $emailUnique],
            'form.phone' => ['nullable', 'string', 'max:20'],
            'form.website_url' => ['required', 'url', 'max:255'],
            'form.status' => ['required', 'in:active,inactive'],
        ];
    }

    protected function messages(): array
    {
        return [
            'form.name.required' => 'نام مشتری الزامی است.',
            'form.email.required' => 'ایمیل الزامی است.',
            'form.email.email' => 'فرمت ایمیل معتبر نیست.',
            'form.email.unique' => 'این ایمیل قبلاً ثبت شده است.',
            'form.website_url.required' => 'آدرس سایت الزامی است.',
            'form.website_url.url' => 'آدرس سایت معتبر نیست (با http یا https شروع شود).',
            'form.status.required' => 'وضعیت را انتخاب کنید.',
        ];
    }

    public function render()
    {
        return view('livewire.customers.index');
    }
}
