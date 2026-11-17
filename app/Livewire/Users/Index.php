<?php

namespace App\Livewire\Users;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Livewire\Concerns\WithBulkActions;
use App\Livewire\Concerns\WithToasts;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('کاربران پنل')]
class Index extends Component
{
    use AuthorizesAdmin;
    use WithBulkActions, WithPagination, WithToasts;

    #[Url]
    public string $search = '';

    #[Url]
    public string $role = '';

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

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRole(): void
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
        return User::query()
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")))
            ->when($this->role, fn ($q) => $q->where('role', $this->role))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->sort === 'newest', fn ($q) => $q->orderByDesc('created_at'))
            ->when($this->sort === 'oldest', fn ($q) => $q->orderBy('created_at'))
            ->when($this->sort === 'id_desc', fn ($q) => $q->orderByDesc('id'))
            ->when($this->sort === 'id_asc', fn ($q) => $q->orderBy('id'))
            ->when($this->sort === 'name_asc', fn ($q) => $q->orderBy('name'))
            ->when($this->sort === 'name_desc', fn ($q) => $q->orderByDesc('name'))
            ->paginate(12);
    }

    /* ---------------------------------------------------------------- */
    /*  CRUD (ported from old UserController) */
    /* ---------------------------------------------------------------- */

    public function openCreate(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->form = ['name' => '', 'email' => '', 'password' => '', 'role' => 'operator', 'status' => 'active'];
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $user = User::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $user->id;
        $this->form = ['name' => $user->name, 'email' => $user->email, 'password' => '', 'role' => $user->role, 'status' => $user->status];
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate();
        $data = $validated['form'];

        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);

            // خالی بودن رمز = بدون تغییر (مثل کنترلر قدیمی)
            if (blank($data['password'] ?? null)) {
                unset($data['password']);
            }

            $user->update($data);
            $this->toast('اطلاعات کاربر با موفقیت به‌روزرسانی شد.');
        } else {
            User::create($data);
            $this->toast('کاربر جدید با موفقیت ایجاد شد.');
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
        $ids = array_values(array_unique(array_map('intval', $this->selectedIds)));

        $skipped = false;

        // کاربر جاری هرگز حذف نمی‌شود
        if (in_array((int) auth()->id(), $ids, true)) {
            $ids = array_values(array_diff($ids, [(int) auth()->id()]));
            $skipped = true;
            $this->toast('کاربر جاری قابل حذف نیست.', 'warning');
        }

        if ($ids === []) {
            return;
        }

        // آخرین مدیر سیستم حذف نمی‌شود
        $remainingAdmins = User::query()
            ->where('role', 'admin')
            ->whereNotIn('id', $ids)
            ->count();

        $users = User::query()->whereIn('id', $ids)->get();
        $deletable = $users->filter(function (User $user) use ($remainingAdmins) {
            return ! ($user->role === 'admin' && $user->id !== auth()->id() && $remainingAdmins === 0);
        });

        if ($deletable->count() < $users->count()) {
            $skipped = true;
            $this->toast('آخرین مدیر سیستم قابل حذف نیست.', 'warning');
        }

        $count = 0;
        $deletable->each(function (User $user) use (&$count) {
            if ($user->id === (int) auth()->id()) {
                return; // محض احتیاط
            }
            $user->delete();
            $count++;
        });

        if ($count > 0) {
            $this->toast(fa_num($count).' کاربر حذف شد.');
        } elseif (! $skipped) {
            $this->toast('کاربری برای حذف انتخاب نشده است.', 'warning');
        }
    }

    public function delete(): void
    {
        $user = User::findOrFail($this->deleteId ?? 0);

        // جلوگیری از حذف خودتان
        if ($user->id === auth()->id()) {
            $this->toast('شما نمی‌توانید حساب کاربری خودتان را حذف کنید.', 'error');
            $this->deleteId = null;

            return;
        }

        // جلوگیری از حذف آخرین مدیر
        if ($user->role === 'admin' && User::query()->where('role', 'admin')->count() <= 1) {
            $this->toast('آخرین مدیر سیستم قابل حذف نیست.', 'error');
            $this->deleteId = null;

            return;
        }

        $user->delete();
        $this->deleteId = null;
        $this->toast('کاربر با موفقیت حذف شد.');
    }

    // تغییر سریع وضعیت (فعال/غیرفعال)
    public function toggleStatus(int $id): void
    {
        $user = User::findOrFail($id);

        // جلوگیری از تغییر وضعیت خودتان
        if ($user->id === auth()->id()) {
            $this->toast('شما نمی‌توانید وضعیت حساب کاربری خودتان را تغییر دهید.', 'error');

            return;
        }

        $newStatus = $user->status === 'active' ? 'inactive' : 'active';
        $user->update(['status' => $newStatus]);

        $this->toast($newStatus === 'active' ? 'کاربر فعال شد.' : 'کاربر غیرفعال شد.');
    }

    /** @return array<string, array<int, string>|string> */
    protected function rules(): array
    {
        $emailUnique = $this->editingId
            ? 'unique:users,email,'.$this->editingId
            : 'unique:users,email';

        $password = $this->editingId
            ? ['nullable', 'string', 'min:6']
            : ['required', 'string', 'min:6'];

        return [
            'form.name' => ['required', 'string', 'max:255'],
            'form.email' => ['required', 'email', 'max:255', $emailUnique],
            'form.password' => $password,
            'form.role' => ['required', 'in:admin,operator'],
            'form.status' => ['required', 'in:active,inactive'],
        ];
    }

    protected function messages(): array
    {
        return [
            'form.name.required' => 'نام کاربر الزامی است.',
            'form.email.required' => 'ایمیل الزامی است.',
            'form.email.email' => 'ایمیل معتبر نیست.',
            'form.email.unique' => 'این ایمیل قبلاً استفاده شده است.',
            'form.password.required' => 'رمز عبور الزامی است.',
            'form.password.min' => 'رمز عبور باید حداقل ۶ کاراکتر باشد.',
            'form.role.required' => 'نقش کاربر را انتخاب کنید.',
            'form.status.required' => 'وضعیت را انتخاب کنید.',
        ];
    }

    public function render()
    {
        return view('livewire.users.index');
    }
}
