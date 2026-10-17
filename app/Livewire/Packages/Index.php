<?php

namespace App\Livewire\Packages;

use App\Livewire\Concerns\WithBulkActions;
use App\Livewire\Concerns\WithToasts;
use App\Models\Category;
use App\Models\Package;
use App\Models\Project;
use App\Services\ImageUploadService;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('پکیج‌ها')]
class Index extends Component
{
    use WithPagination, WithToasts, WithBulkActions;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $project_id = '';

    #[Url]
    public string $categoryFilter = '';

    /** فیلتر نمایش/ترتیب: جدیدترین، قدیمی‌ترین، نام، قیمت، دانلود و… */
    #[Url]
    public string $sort = 'newest';

    public bool $showModal = false;

    public ?int $editingId = null;

    public ?int $deleteId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public const CATEGORIES = [
        'shop'         => 'فروشگاه',
        'payment'      => 'پرداخت',
        'notification' => 'اعلان',
        'seo'          => 'سئو',
        'blog'         => 'بلاگ',
        'utility'      => 'ابزار',
        'theme'        => 'قالب',
        'other'        => 'سایر',
    ];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedProjectId(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
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
        return Package::query()
            ->with(['project:id,name', 'latestVersion', 'categoryModel:id,name'])
            ->withCount('activeVersions')
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('slug', 'like', "%{$this->search}%")))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->project_id !== '', fn ($q) => $q->where('project_id', (int) $this->project_id))
            ->when($this->categoryFilter !== '', fn ($q) => $q->where('category_id', (int) $this->categoryFilter))
            ->when($this->sort === 'newest', fn ($q) => $q->latest())
            ->when($this->sort === 'oldest', fn ($q) => $q->oldest())
            ->when($this->sort === 'id_desc', fn ($q) => $q->orderByDesc('id'))
            ->when($this->sort === 'id_asc', fn ($q) => $q->orderBy('id'))
            ->when($this->sort === 'name_asc', fn ($q) => $q->orderBy('name'))
            ->when($this->sort === 'name_desc', fn ($q) => $q->orderByDesc('name'))
            ->when($this->sort === 'price_desc', fn ($q) => $q->orderByDesc('default_price'))
            ->when($this->sort === 'price_asc', fn ($q) => $q->orderByRaw('default_price is null')->orderBy('default_price'))
            ->when($this->sort === 'downloads_desc', fn ($q) => $q->orderByDesc('downloads_count'))
            ->paginate(12);
    }

    #[Computed]
    public function projects()
    {
        return Project::query()->orderBy('name')->get(['id', 'name']);
    }

    /** دسته‌بندی‌های ساختاریافته پکیج (جدول categories) — سلسله‌مراتبی برای سلکت */
    #[Computed]
    public function categories()
    {
        return Category::flatOptions(Category::TYPE_PACKAGE);
    }

    /* ---------------------------------------------------------------- */
    /*  Bulk selection (WithBulkActions)                                 */
    /* ---------------------------------------------------------------- */

    public function bulkPageIds(): array
    {
        return $this->records->getCollection()->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    public function deleteSelectedRecords(): void
    {
        $ids = array_map('intval', $this->selectedIds);

        $packages = Package::query()->with(['images', 'versions'])->whereIn('id', $ids)->get();

        $imageService = app(ImageUploadService::class);

        foreach ($packages as $package) {
            // حذف تصویر شاخص و گالری (مثل حذف تکی)
            $imageService->deleteThumbnail($package);
            $imageService->deleteAllGalleryImages($package);

            // حذف فایل‌های ZIP نسخه‌ها
            foreach ($package->versions as $version) {
                if ($version->file_path && file_exists(storage_path('app/' . $version->file_path))) {
                    @unlink(storage_path('app/' . $version->file_path));
                }
            }

            $package->delete();
        }

        $this->toast(fa_num(count($packages)) . ' پکیج و تمام نسخه‌های آن حذف شد.');
    }

    /* ---------------------------------------------------------------- */
    /*  CRUD                                                             */
    /* ---------------------------------------------------------------- */

    public function openCreate(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->form = [
            'project_id'        => '',
            'name'              => '',
            'slug'              => '',
            'short_description' => '',
            'description'       => '',
            'author'            => '',
            'category_id'       => '',
            'is_free'           => false,
            'default_price'     => 0,
            'status'            => 'draft',
            'module_name'       => '',
            'sort_order'        => 0,
        ];
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $package = Package::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $package->id;
        $this->form = $package->only([
            'project_id', 'name', 'slug', 'short_description', 'description',
            'author', 'is_free', 'default_price', 'status',
            'module_name', 'sort_order',
        ]);
        $this->form['category_id'] = (string) ($package->category_id ?? '');
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->normalizeBlankables();
        $validated = $this->validate();

        $form = $validated['form'];

        // تولید slug اگر خالی باشد
        if (blank($form['slug'] ?? null)) {
            $form['slug'] = Str::slug($form['name']);
        }

        // تولید module_name اگر خالی باشد
        if (blank($form['module_name'] ?? null)) {
            $form['module_name'] = ucfirst(Str::camel($form['slug']));
        }

        // مقادیر پیش‌فرض
        $form['is_free'] = (bool) ($form['is_free'] ?? false);
        $form['default_price'] = (int) ($form['default_price'] ?? 0);
        $form['sort_order'] = (int) ($form['sort_order'] ?? 0);

        // دسته‌بندی ساختاریافته → هم category_id و هم ستون قدیمی category (برای سازگاری API)
        $form['category_id'] = filled($form['category_id'] ?? '') ? (int) $form['category_id'] : null;
        $form['category'] = $form['category_id']
            ? (string) Category::find($form['category_id'])?->name
            : null;

        if ($this->editingId) {
            Package::findOrFail($this->editingId)->update($form);
            $this->toast('پکیج با موفقیت به‌روزرسانی شد.');
        } else {
            $package = Package::create($form);
            $this->toast('پکیج جدید ایجاد شد.');
            $this->redirect(route('admin.packages.show', $package), true);

            return;
        }

        $this->showModal = false;
    }

    public function delete(): void
    {
        $package = Package::with('images', 'versions')->findOrFail($this->deleteId ?? 0);
        $imageService = app(ImageUploadService::class);

        // حذف تصویر شاخص و گالری
        $imageService->deleteThumbnail($package);
        $imageService->deleteAllGalleryImages($package);

        // حذف فایل‌های ZIP نسخه‌ها
        foreach ($package->versions as $version) {
            if ($version->file_path && file_exists(storage_path('app/' . $version->file_path))) {
                @unlink(storage_path('app/' . $version->file_path));
            }
        }

        $name = $package->name;
        $package->delete();
        $this->deleteId = null;
        $this->toast("پکیج «{$name}» و تمام نسخه‌های آن حذف شد.");
    }

    /** تبدیل رشته‌های خالی به null تا ولیدیشن nullable درست کار کند. */
    private function normalizeBlankables(): void
    {
        foreach (['default_price', 'sort_order'] as $key) {
            $this->form[$key] = blank($this->form[$key] ?? null) ? null : $this->form[$key];
        }
    }

    /** @return array<string, array<int, string>|string> */
    protected function rules(): array
    {
        $slugUnique = $this->editingId
            ? 'unique:packages,slug,' . $this->editingId
            : 'unique:packages,slug';

        return [
            'form.project_id'        => ['required', 'exists:projects,id'],
            'form.name'              => ['required', 'string', 'max:255'],
            'form.slug'              => ['nullable', 'string', 'max:100', $slugUnique],
            'form.short_description' => ['nullable', 'string', 'max:10000'],
            'form.description'       => ['nullable', 'string'],
            'form.author'            => ['nullable', 'string', 'max:100'],
            'form.category_id'       => ['nullable', 'exists:categories,id'],
            'form.is_free'           => ['nullable', 'boolean'],
            'form.default_price'     => ['nullable', 'integer', 'min:0'],
            'form.status'            => ['required', 'in:draft,active,archived'],
            'form.module_name'       => ['nullable', 'string', 'max:100'],
            'form.sort_order'        => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function messages(): array
    {
        return [
            'form.project_id.required' => 'انتخاب پروژه الزامی است.',
            'form.name.required'        => 'نام پکیج الزامی است.',
            'form.slug.unique'          => 'این نامک قبلاً استفاده شده است.',
            'form.default_price.integer' => 'قیمت باید عدد باشد.',
            'form.status.required'      => 'وضعیت را انتخاب کنید.',
        ];
    }

    public function render()
    {
        return view('livewire.packages.index');
    }
}
