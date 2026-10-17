<?php

namespace App\Livewire\Products;

use App\Livewire\Concerns\WithToasts;
use App\Models\Category;
use App\Models\Product;
use App\Services\ImageUploadService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('محصولات (پروژه‌های قابل‌فروش)')]
class Index extends Component
{
    use WithToasts, WithPagination, WithFileUploads;

    public string $search = '';
    public string $categoryFilter = '';
    public string $statusFilter = '';

    /* ---------------- Form (create/edit modal) ---------------- */
    public bool $showModal = false;
    public ?int $editingId = null;
    public $thumbnailFile; // UploadedFile (اختیاری — فقط هنگام تغییر)
    public bool $removeThumbnail = false;
    public array $form = [
        'name'              => '',
        'slug'              => '',
        'category_id'       => '',
        'short_description' => '',
        'description'       => '',
        'price'             => 0,
        'discount'          => 0,
        'is_free'           => false,
        'status'            => Product::STATUS_DRAFT,
        'sort_order'        => 0,
    ];

    protected function rules(): array
    {
        return [
            'form.name'              => ['required', 'string', 'max:190'],
            'form.slug'              => ['nullable', 'string', 'max:190', 'alpha_dash:ascii'],
            'form.category_id'       => ['nullable', 'exists:categories,id'],
            'form.short_description' => ['nullable', 'string', 'max:10000'],
            'form.description'       => ['nullable', 'string', 'max:100000'],
            'form.price'             => ['nullable', 'integer', 'min:0'],
            'form.discount'          => ['nullable', 'integer', 'min:0', 'max:100'],
            'form.is_free'           => ['boolean'],
            'form.status'            => ['required', 'in:draft,active,archived'],
            'form.sort_order'        => ['nullable', 'integer', 'min:0', 'max:9999'],
            'thumbnailFile'          => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:3072'],
        ];
    }

    protected $messages = [
        'form.name.required' => 'نام محصول الزامی است.',
        'form.status.required' => 'وضعیت محصول الزامی است.',
        'thumbnailFile.image' => 'فایل تصویر شاخص معتبر نیست.',
        'thumbnailFile.max'   => 'حجم تصویر شاخص حداکثر ۳ مگابایت.',
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    /* ---------------------------------------------------------------- */
    /*  CRUD                                                             */
    /* ---------------------------------------------------------------- */

    public function create(): void
    {
        $this->resetErrorBag();
        $this->editingId = null;
        $this->thumbnailFile = null;
        $this->removeThumbnail = false;
        $this->form = [
            'name'              => '',
            'slug'              => '',
            'category_id'       => '',
            'short_description' => '',
            'description'       => '',
            'price'             => 0,
            'discount'          => 0,
            'is_free'           => false,
            'status'            => Product::STATUS_DRAFT,
            'sort_order'        => 0,
        ];
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->resetErrorBag();
        $product = Product::findOrFail($id);

        $this->editingId = $product->id;
        $this->thumbnailFile = null;
        $this->removeThumbnail = false;
        $this->form = [
            'name'              => $product->name,
            'slug'              => $product->slug,
            'category_id'       => (string) $product->category_id,
            'short_description' => (string) $product->short_description,
            'description'       => (string) $product->description,
            'price'             => $product->price,
            'discount'          => $product->discount,
            'is_free'           => $product->is_free,
            'status'            => $product->status,
            'sort_order'        => $product->sort_order,
        ];
        $this->showModal = true;
    }

    public function save(ImageUploadService $uploader): void
    {
        $data = $this->validate();

        $payload = [
            'name'              => trim($data['form']['name']),
            'slug'              => trim((string) ($data['form']['slug'] ?? '')),
            'category_id'       => $data['form']['category_id'] !== '' ? (int) $data['form']['category_id'] : null,
            'short_description' => $data['form']['short_description'] ?: null,
            'description'       => $data['form']['description'] ?: null,
            'price'             => (int) ($data['form']['price'] ?? 0),
            'discount'          => (int) ($data['form']['discount'] ?? 0),
            'is_free'           => (bool) $data['form']['is_free'],
            'status'            => $data['form']['status'],
            'sort_order'        => (int) ($data['form']['sort_order'] ?? 0),
        ];

        // پروژه‌ی رایگان قیمت ندارد
        if ($payload['is_free']) {
            $payload['price'] = 0;
            $payload['discount'] = 0;
        }

        try {
            if ($this->editingId) {
                $product = Product::findOrFail($this->editingId);

                // slug
                if ($payload['slug'] === '') {
                    $payload['slug'] = Product::generateSlug($payload['name'], $product->id);
                } elseif ($payload['slug'] !== $product->slug) {
                    $exists = Product::where('slug', $payload['slug'])->where('id', '!=', $product->id)->exists();
                    if ($exists) {
                        $this->addError('form.slug', 'این نامک قبلاً استفاده شده است.');
                        return;
                    }
                }

                // حذف تصویر
                if ($this->removeThumbnail) {
                    $uploader->deleteFile($product->thumbnail);
                    $payload['thumbnail'] = null;
                }

                // آپلود تصویر جدید
                if ($this->thumbnailFile) {
                    if ($product->thumbnail) {
                        $uploader->deleteFile($product->thumbnail);
                    }
                    $payload['thumbnail'] = $uploader->uploadThumbnailProduct($this->thumbnailFile, $payload['slug'] ?: 'product');
                }

                $product->update($payload);
                $message = 'محصول ویرایش شد.';
            } else {
                if ($payload['slug'] === '') {
                    $payload['slug'] = Product::generateSlug($payload['name']);
                } elseif (Product::where('slug', $payload['slug'])->exists()) {
                    $this->addError('form.slug', 'این نامک قبلاً استفاده شده است.');
                    return;
                }

                if ($this->thumbnailFile) {
                    $payload['thumbnail'] = $uploader->uploadThumbnailProduct($this->thumbnailFile, $payload['slug']);
                }

                $product = Product::create($payload);
                $message = 'محصول جدید ایجاد شد.';
            }
        } catch (\RuntimeException $e) {
            $this->toast($e->getMessage(), 'error');
            return;
        } catch (\Throwable $e) {
            report($e);
            $this->toast('ذخیره محصول ناموفق بود: ' . $e->getMessage(), 'error');
            return;
        }

        $this->showModal = false;
        $this->toast($message);
    }

    public function toggleStatus(int $id): void
    {
        $product = Product::findOrFail($id);

        $product->update([
            'status' => $product->status === Product::STATUS_ACTIVE ? Product::STATUS_DRAFT : Product::STATUS_ACTIVE,
        ]);

        $this->toast($product->status === Product::STATUS_ACTIVE
            ? 'محصول منتشر شد (فعال).'
            : 'محصول به پیش‌نویس برگشت.');
    }

    public function delete(int $id): void
    {
        $product = Product::findOrFail($id);

        if ($product->purchases()->where('status', 'paid')->exists()) {
            $this->toast('این محصول خرید موفق دارد و قابل حذف نیست؛ آن را آرشیو کنید.', 'error');
            return;
        }

        $product->delete();
        $this->toast('محصول حذف شد.');
    }

    /* ---------------------------------------------------------------- */
    /*  Computed                                                         */
    /* ---------------------------------------------------------------- */

    #[Computed]
    public function categories()
    {
        return Category::flatOptions(Category::TYPE_PRODUCT);
    }

    #[Computed]
    public function records()
    {
        return Product::query()
            ->with(['category:id,name', 'latestVersion:id,product_id,version'])
            ->withCount(['versions', 'purchases' => fn ($q) => $q->where('status', 'paid'), 'comments' => fn ($q) => $q->where('status', 'pending')])
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('slug', 'like', "%{$this->search}%")))
            ->when($this->categoryFilter !== '', fn ($q) => $q->where('category_id', $this->categoryFilter))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->orderBy('sort_order')
            ->latest('id')
            ->paginate(12);
    }

    public function render()
    {
        return view('livewire.products.index');
    }
}
