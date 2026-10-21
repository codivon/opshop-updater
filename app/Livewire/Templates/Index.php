<?php

namespace App\Livewire\Templates;

use App\Livewire\Concerns\WithToasts;
use App\Models\Category;
use App\Models\Template;
use App\Services\ImageUploadService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('قالب‌ها')]
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

    /** شمارنده برای رندر مجدد اجباری input فایل پس از هر باز/بسته شدن مودال (رفع باگ تصویر) */
    public int $thumbNonce = 0;

    public array $form = [
        'name'               => '',
        'slug'               => '',
        'category_id'        => '',
        'short_description'  => '',
        'description'        => '',
        'price'              => 0,
        'discount'           => 0,
        'exclusive_price'    => 0,
        'exclusive_discount' => 0,
        'exclusive_note'     => '',
        'is_free'            => false,
        'status'             => Template::STATUS_DRAFT,
        'sort_order'         => 0,
    ];

    protected function rules(): array
    {
        return [
            'form.name'               => ['required', 'string', 'max:190'],
            'form.slug'               => ['nullable', 'string', 'max:190', 'alpha_dash:ascii'],
            'form.category_id'        => ['nullable', 'exists:categories,id'],
            'form.short_description'  => ['nullable', 'string', 'max:10000'],
            'form.description'        => ['nullable', 'string', 'max:100000'],
            'form.price'              => ['nullable', 'integer', 'min:0'],
            'form.discount'           => ['nullable', 'integer', 'min:0', 'max:100'],
            'form.exclusive_price'    => ['nullable', 'integer', 'min:0'],
            'form.exclusive_discount' => ['nullable', 'integer', 'min:0', 'max:100'],
            'form.exclusive_note'     => ['nullable', 'string', 'max:2000'],
            'form.is_free'            => ['boolean'],
            'form.status'             => ['required', 'in:draft,active,archived'],
            'form.sort_order'         => ['nullable', 'integer', 'min:0', 'max:9999'],
            'thumbnailFile'           => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:3072'],
        ];
    }

    protected $messages = [
        'form.name.required' => 'نام قالب الزامی است.',
        'form.status.required' => 'وضعیت قالب الزامی است.',
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
        $this->resetValidation();
        $this->editingId = null;
        $this->thumbnailFile = null;
        $this->removeThumbnail = false;
        $this->form = [
            'name'               => '',
            'slug'               => '',
            'category_id'        => '',
            'short_description'  => '',
            'description'        => '',
            'price'              => 0,
            'discount'           => 0,
            'exclusive_price'    => 0,
            'exclusive_discount' => 0,
            'exclusive_note'     => '',
            'is_free'            => false,
            'status'             => Template::STATUS_DRAFT,
            'sort_order'         => 0,
        ];
        $this->thumbNonce++;
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->resetErrorBag();
        $this->resetValidation();
        $template = Template::findOrFail($id);

        $this->editingId = $template->id;
        $this->thumbnailFile = null;
        $this->removeThumbnail = false;
        $this->form = [
            'name'               => $template->name,
            'slug'               => $template->slug,
            'category_id'        => (string) $template->category_id,
            'short_description'  => (string) $template->short_description,
            'description'        => (string) $template->description,
            'price'              => $template->price,
            'discount'           => $template->discount,
            'exclusive_price'    => $template->exclusive_price,
            'exclusive_discount' => $template->exclusive_discount,
            'exclusive_note'     => (string) ($template->exclusive_note ?? ''),
            'is_free'            => $template->is_free,
            'status'             => $template->status,
            'sort_order'         => $template->sort_order,
        ];
        $this->thumbNonce++;
        $this->showModal = true;
    }

    public function save(ImageUploadService $uploader): void
    {
        $data = $this->validate();

        $payload = [
            'name'               => trim($data['form']['name']),
            'slug'               => trim((string) ($data['form']['slug'] ?? '')),
            'category_id'        => $data['form']['category_id'] !== '' ? (int) $data['form']['category_id'] : null,
            'short_description'  => $data['form']['short_description'] ?: null,
            'description'        => $data['form']['description'] ?: null,
            'price'              => (int) ($data['form']['price'] ?? 0),
            'discount'           => (int) ($data['form']['discount'] ?? 0),
            'exclusive_price'    => (int) ($data['form']['exclusive_price'] ?? 0),
            'exclusive_discount' => (int) ($data['form']['exclusive_discount'] ?? 0),
            'exclusive_note'     => ($data['form']['exclusive_note'] ?? '') ?: null,
            'is_free'            => (bool) $data['form']['is_free'],
            'status'             => $data['form']['status'],
            'sort_order'         => (int) ($data['form']['sort_order'] ?? 0),
        ];

        // قالب رایگان قیمت ندارد
        if ($payload['is_free']) {
            $payload['price'] = 0;
            $payload['discount'] = 0;
            $payload['exclusive_price'] = 0;
            $payload['exclusive_discount'] = 0;
        }

        try {
            if ($this->editingId) {
                $template = Template::findOrFail($this->editingId);

                // slug
                if ($payload['slug'] === '') {
                    $payload['slug'] = Template::generateSlug($payload['name'], $template->id);
                } elseif ($payload['slug'] !== $template->slug) {
                    $exists = Template::where('slug', $payload['slug'])->where('id', '!=', $template->id)->exists();
                    if ($exists) {
                        $this->addError('form.slug', 'این نامک قبلاً استفاده شده است.');
                        return;
                    }
                }

                // حذف تصویر
                if ($this->removeThumbnail) {
                    $uploader->deleteFile($template->thumbnail);
                    $payload['thumbnail'] = null;
                }

                // آپلود تصویر جدید
                if ($this->thumbnailFile) {
                    if ($template->thumbnail) {
                        $uploader->deleteFile($template->thumbnail);
                    }
                    $payload['thumbnail'] = $uploader->uploadThumbnailTemplate($this->thumbnailFile, $payload['slug'] ?: 'template');
                }

                $template->update($payload);
                $message = 'قالب ویرایش شد.';
            } else {
                if ($payload['slug'] === '') {
                    $payload['slug'] = Template::generateSlug($payload['name']);
                } elseif (Template::where('slug', $payload['slug'])->exists()) {
                    $this->addError('form.slug', 'این نامک قبلاً استفاده شده است.');
                    return;
                }

                if ($this->thumbnailFile) {
                    $payload['thumbnail'] = $uploader->uploadThumbnailTemplate($this->thumbnailFile, $payload['slug']);
                }

                $template = Template::create($payload);
                $message = 'قالب جدید ایجاد شد.';
            }
        } catch (\RuntimeException $e) {
            $this->toast($e->getMessage(), 'error');
            return;
        } catch (\Throwable $e) {
            report($e);
            $this->toast('ذخیره قالب ناموفق بود: ' . $e->getMessage(), 'error');
            return;
        }

        // رفع باگ «تصویر قالب تغییر نمی‌کند»:
        // بعد از ذخیره، فایل آپلودی باید کاملاً از state پاک شود؛ وگرنه در باز
        // شدن بعدی مودال، input قدیمی (wire:ignore) با state همگام دیده می‌شد.
        $this->thumbnailFile = null;
        $this->removeThumbnail = false;
        $this->thumbNonce++;
        $this->showModal = false;
        $this->toast($message);
    }

    public function toggleStatus(int $id): void
    {
        $template = Template::findOrFail($id);

        $template->update([
            'status' => $template->status === Template::STATUS_ACTIVE ? Template::STATUS_DRAFT : Template::STATUS_ACTIVE,
        ]);

        $this->toast($template->status === Template::STATUS_ACTIVE
            ? 'قالب منتشر شد (فعال).'
            : 'قالب به پیش‌نویس برگشت.');
    }

    public function delete(int $id, ImageUploadService $uploader): void
    {
        $template = Template::with('images')->findOrFail($id);

        if ($template->purchases()->where('status', 'paid')->exists()) {
            $this->toast('این قالب خرید موفق دارد و قابل حذف نیست؛ آن را آرشیو کنید.', 'error');
            return;
        }

        // حذف تصویر شاخص + گالری
        if ($template->thumbnail) {
            $uploader->deleteFile($template->thumbnail);
        }
        $uploader->deleteAllTemplateGalleryImages($template);

        // حذف فایل ZIP نسخه‌ها
        foreach ($template->versions as $version) {
            if ($version->file_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($version->file_path)) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($version->file_path);
            }
        }

        $template->delete();
        $this->toast('قالب حذف شد.');
    }

    /* ---------------------------------------------------------------- */
    /*  Computed                                                         */
    /* ---------------------------------------------------------------- */

    #[Computed]
    public function categories()
    {
        return Category::flatOptions(Category::TYPE_TEMPLATE);
    }

    #[Computed]
    public function records()
    {
        return Template::query()
            ->with(['category:id,name', 'latestVersion:id,template_id,version'])
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
        return view('livewire.templates.index');
    }
}
