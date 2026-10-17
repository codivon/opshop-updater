<?php

namespace App\Livewire\Categories;

use App\Livewire\Concerns\WithToasts;
use App\Models\Category;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('دسته‌بندی‌ها')]
class Index extends Component
{
    use WithToasts, WithPagination;

    public string $search = '';
    public string $typeFilter = '';

    /** فرم مودال ایجاد/ویرایش */
    public bool $showModal = false;
    public ?int $editingId = null;
    public array $form = [
        'name'        => '',
        'slug'        => '',
        'type'        => Category::TYPE_PRODUCT,
        'parent_id'   => '',
        'description' => '',
        'is_active'   => true,
        'sort_order'  => 0,
    ];

    protected function rules(): array
    {
        return [
            'form.name'        => ['required', 'string', 'max:120'],
            'form.slug'        => ['nullable', 'string', 'max:120', 'alpha_dash:ascii'],
            'form.type'        => ['required', 'in:product,package'],
            'form.parent_id'   => ['nullable', 'integer', 'exists:categories,id'],
            'form.description' => ['nullable', 'string', 'max:500'],
            'form.is_active'   => ['boolean'],
            'form.sort_order'  => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    protected $messages = [
        'form.name.required' => 'نام دسته‌بندی الزامی است.',
        'form.type.required' => 'نوع دسته‌بندی الزامی است.',
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    /** با تغییر نوع دسته، والدِ انتخاب‌شده نامعتبر می‌شود */
    public function updatedFormType(): void
    {
        $this->form['parent_id'] = '';
    }

    /* ---------------------------------------------------------------- */
    /*  CRUD                                                             */
    /* ---------------------------------------------------------------- */

    public function create(): void
    {
        $this->resetErrorBag();
        $this->editingId = null;
        $this->form = [
            'name'        => '',
            'slug'        => '',
            'type'        => $this->typeFilter !== '' ? $this->typeFilter : Category::TYPE_PRODUCT,
            'parent_id'   => '',
            'description' => '',
            'is_active'   => true,
            'sort_order'  => 0,
        ];
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->resetErrorBag();
        $category = Category::findOrFail($id);

        $this->editingId = $category->id;
        $this->form = [
            'name'        => $category->name,
            'slug'        => $category->slug,
            'type'        => $category->type,
            'parent_id'   => $category->parent_id ?? '',
            'description' => (string) $category->description,
            'is_active'   => $category->is_active,
            'sort_order'  => $category->sort_order,
        ];
        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        $name = trim($data['form']['name']);
        $slug = trim((string) ($data['form']['slug'] ?? ''));

        if ($slug === '') {
            $slug = $this->uniqueSlug($name, $this->editingId);
        } else {
            // slug دستی هم باید یکتا باشد
            $exists = Category::where('slug', $slug)
                ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))
                ->exists();

            if ($exists) {
                $this->addError('form.slug', 'این نامک قبلاً استفاده شده است.');
                return;
            }
        }

        /* ---- اعتبارسنجی سلسله‌مراتب (دسته والد) ---- */
        $parentId = filled($data['form']['parent_id'] ?? '') ? (int) $data['form']['parent_id'] : null;

        if ($parentId !== null) {
            $parent = Category::find($parentId);

            if (!$parent || $parent->type !== $data['form']['type']) {
                $this->addError('form.parent_id', 'دسته والد باید از همان نوع این دسته باشد.');
                return;
            }

            if ($this->editingId && Category::inSubtree($this->editingId, $parentId)) {
                $this->addError('form.parent_id', 'نمی‌توان خود دسته یا زیردسته‌هایش را به‌عنوان والد انتخاب کرد.');
                return;
            }
        }

        $id = $this->editingId;

        Category::updateOrCreate(
            ['id' => $id],
            [
                'name'        => $name,
                'slug'        => $slug,
                'type'        => $data['form']['type'],
                'parent_id'   => $parentId,
                'description' => $data['form']['description'] ?: null,
                'is_active'   => (bool) $data['form']['is_active'],
                'sort_order'  => (int) ($data['form']['sort_order'] ?? 0),
            ]
        );

        $this->showModal = false;
        $this->toast($id ? 'دسته‌بندی ویرایش شد.' : 'دسته‌بندی جدید ایجاد شد.');
    }

    public function toggleActive(int $id): void
    {
        $category = Category::findOrFail($id);
        $category->update(['is_active' => !$category->is_active]);

        $this->toast($category->is_active ? 'دسته‌بندی فعال شد.' : 'دسته‌بندی غیرفعال شد.');
    }

    public function delete(int $id): void
    {
        $category = Category::findOrFail($id);

        // دسته دارای زیردسته قابل حذف نیست (زیردسته‌ها باید اول جابه‌جا شوند)
        $childrenCount = $category->children()->count();

        if ($childrenCount > 0) {
            $this->toast('این دسته‌بندی دارای ' . fa_num($childrenCount) . ' زیردسته است؛ ابتدا زیردسته‌ها را حذف یا جابه‌جا کنید.', 'error');
            return;
        }

        $usage = $category->usageCount();

        if ($usage > 0) {
            $this->toast("این دسته‌بندی در " . fa_num($usage) . " مورد استفاده شده و قابل حذف نیست. ابتدا آن‌ها را جابه‌جا کنید.", 'error');
            return;
        }

        $category->delete();
        $this->toast('دسته‌بندی حذف شد.');
    }

    private function uniqueSlug(string $name, ?int $ignoreId): string
    {
        $base = \Illuminate\Support\Str::slug($name);

        if ($base === '') {
            $base = 'cat-' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6));
        }

        $slug = $base;
        $i = 2;

        while (Category::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    /* ---------------------------------------------------------------- */
    /*  Computed                                                         */
    /* ---------------------------------------------------------------- */

    public function getTypesProperty(): array
    {
        return [
            Category::TYPE_PRODUCT => 'محصولات (پروژه‌ها)',
            Category::TYPE_PACKAGE => 'پکیج‌ها',
        ];
    }

    #[Computed]
    public function records()
    {
        return Category::query()
            ->with('parent:id,name')
            ->withCount(['products', 'packages', 'children'])
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('slug', 'like', "%{$this->search}%")))
            ->when($this->typeFilter !== '', fn ($q) => $q->where('type', $this->typeFilter))
            ->orderBy('type')
            ->orderByRaw('COALESCE(parent_id, id)')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20);
    }

    /** گزینه‌های دسته والد برای مودال (فقط هم‌نوع، بدون خود دسته و زیردرختش) */
    #[Computed]
    public function parentOptions(): array
    {
        return Category::flatOptions($this->form['type'] ?: Category::TYPE_PRODUCT, $this->editingId);
    }

    public function render()
    {
        return view('livewire.categories.index');
    }
}
