<?php

namespace App\Livewire\Templates;

use App\Livewire\Concerns\WithToasts;
use App\Models\Comment;
use App\Models\Template;
use App\Models\TemplateImage;
use App\Models\TemplateLicense;
use App\Models\TemplateVersion;
use App\Services\ImageUploadService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use RuntimeException;

#[Layout('components.layouts.app')]
#[Title('جزئیات قالب')]
class Show extends Component
{
    use WithToasts, WithFileUploads, WithPagination;

    public Template $template;

    /** تب فعال: info | versions | gallery | purchases | licenses | comments */
    public string $tab = 'info';

    /* ================= Template edit modal (مثل Packages\Show) ================= */
    public bool $showModal = false;
    public array $form = [];
    public bool $removeThumbnail = false;
    public $thumbnailFile = null;

    /** شمارنده برای رندر مجدد input تصویر (رفع باگ «تصویر قالب تغییر نمی‌کند») */
    public int $thumbNonce = 0;

    /** تصاویر گالری جدید (چندتایی) */
    public array $newImages = [];
    public ?int $deleteImageId = null;

    /* ================= Version form ================= */
    public bool $showVersionModal = false;
    public ?int $editingVersionId = null;
    public $versionFile; // UploadedFile ZIP
    public array $versionForm = [
        'version'      => '',
        'type'         => TemplateVersion::TYPE_MINOR,
        'changelog'    => '',
        'what_added'   => '',
        'what_changed' => '',
        'what_fixed'   => '',
        'is_mandatory' => false,
        'status'       => TemplateVersion::STATUS_ACTIVE,
        'release_date' => '',
    ];

    protected function rules(): array
    {
        return [
            'versionForm.version'      => ['required', 'string', 'max:50'],
            'versionForm.type'         => ['required', 'in:major,minor,patch'],
            'versionForm.changelog'    => ['nullable', 'string', 'max:10000'],
            'versionForm.what_added'   => ['nullable', 'string', 'max:10000'],
            'versionForm.what_changed' => ['nullable', 'string', 'max:10000'],
            'versionForm.what_fixed'   => ['nullable', 'string', 'max:10000'],
            'versionForm.is_mandatory' => ['boolean'],
            'versionForm.status'       => ['required', 'in:draft,active,archived'],
            'versionForm.release_date' => ['nullable', 'date'],
            'versionFile'              => ['nullable', 'file', 'mimes:zip', 'max:327680'],
        ];
    }

    protected $messages = [
        'versionForm.version.required' => 'شماره نسخه الزامی است.',
        'versionFile.mimes'            => 'فقط فایل ZIP مجاز است.',
        'versionFile.max'              => 'حجم فایل حداکثر ۳۲۰ مگابایت.',
    ];

    public function mount(Template $template): void
    {
        $this->template = $template->load(['category', 'versions', 'comments.commentable']);
    }

    /* ================= Tab switching ================= */

    public function switchTab(string $tab): void
    {
        $this->tab = in_array($tab, ['info', 'versions', 'gallery', 'purchases', 'licenses', 'comments']) ? $tab : 'info';
    }

    /* ================================================================
     *  ویرایش قالب (اطلاعات + قیمت‌گذاری دوگانه + تصویر شاخص)
     * ================================================================ */

    public function openEditTemplate(ImageUploadService $uploader): void
    {
        $this->resetValidation();
        $this->form = $this->template->only([
            'name', 'slug', 'short_description', 'description',
            'price', 'discount', 'exclusive_price', 'exclusive_discount',
            'exclusive_note', 'is_free', 'status', 'sort_order',
        ]);
        $this->form['category_id'] = (string) ($this->template->category_id ?? '');
        $this->thumbnailFile = null;
        $this->removeThumbnail = false;
        $this->thumbNonce++;
        $this->showModal = true;
    }

    public function saveTemplate(ImageUploadService $uploader): void
    {
        $validated = $this->validate([
            'form.name'               => ['required', 'string', 'max:190'],
            'form.slug'               => ['nullable', 'string', 'max:190', 'alpha_dash:ascii', 'unique:templates,slug,' . $this->template->id],
            'form.category_id'        => ['nullable', 'exists:categories,id'],
            'form.short_description'  => ['nullable', 'string', 'max:10000'],
            'form.description'        => ['nullable', 'string', 'max:100000'],
            'form.price'              => ['nullable', 'integer', 'min:0'],
            'form.discount'           => ['nullable', 'integer', 'min:0', 'max:100'],
            'form.exclusive_price'    => ['nullable', 'integer', 'min:0'],
            'form.exclusive_discount' => ['nullable', 'integer', 'min:0', 'max:100'],
            'form.exclusive_note'     => ['nullable', 'string', 'max:2000'],
            'form.is_free'            => ['nullable', 'boolean'],
            'form.status'             => ['required', 'in:draft,active,archived'],
            'form.sort_order'         => ['nullable', 'integer', 'min:0', 'max:9999'],
            'thumbnailFile'           => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:3072'],
            'newImages'               => ['nullable', 'array', 'max:10'],
            'newImages.*'             => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:3072'],
        ], [
            'form.name.required' => 'نام قالب الزامی است.',
            'form.slug.unique'   => 'این نامک قبلاً استفاده شده است.',
            'form.status.required' => 'وضعیت را انتخاب کنید.',
        ]);

        $form = $validated['form'];

        // تولید slug اگر خالی باشد
        if (blank($form['slug'] ?? null)) {
            $form['slug'] = Template::generateSlug($form['name'], $this->template->id);
        }

        $form['is_free'] = (bool) ($form['is_free'] ?? false);
        $form['price'] = (int) ($form['price'] ?? 0);
        $form['discount'] = (int) ($form['discount'] ?? 0);
        $form['exclusive_price'] = (int) ($form['exclusive_price'] ?? 0);
        $form['exclusive_discount'] = (int) ($form['exclusive_discount'] ?? 0);
        $form['exclusive_note'] = ($form['exclusive_note'] ?? '') ?: null;
        $form['sort_order'] = (int) ($form['sort_order'] ?? 0);
        $form['category_id'] = filled($form['category_id'] ?? '') ? (int) $form['category_id'] : null;

        if ($form['is_free']) {
            $form['price'] = 0;
            $form['discount'] = 0;
            $form['exclusive_price'] = 0;
            $form['exclusive_discount'] = 0;
        }

        try {
            // تصویر شاخص
            if ($this->removeThumbnail) {
                $uploader->deleteFile($this->template->thumbnail);
                $form['thumbnail'] = null;
            } elseif ($this->thumbnailFile) {
                if ($this->template->thumbnail) {
                    $uploader->deleteFile($this->template->thumbnail);
                }
                $form['thumbnail'] = $uploader->uploadThumbnailTemplate($this->thumbnailFile, $form['slug']);
            }

            $this->template->update($form);

            // گالری جدید (به گالری موجود اضافه می‌شود)
            if ($this->newImages) {
                $uploader->uploadTemplateGalleryImages($this->newImages, $this->template);
            }
        } catch (RuntimeException $e) {
            $this->toast($e->getMessage(), 'error');
            return;
        }

        $this->template->refresh();

        // رفع باگ «تصویر قالب تغییر نمی‌کند» — پاک‌سازی کامل فایل از state
        $this->thumbnailFile = null;
        $this->newImages = [];
        $this->removeThumbnail = false;
        $this->thumbNonce++;
        $this->showModal = false;
        $this->toast('قالب با موفقیت به‌روزرسانی شد.');
    }

    /* ================================================================
     *  گالری تصاویر
     * ================================================================ */

    public function uploadImages(ImageUploadService $uploader): void
    {
        $this->validate([
            'newImages'   => ['required', 'array', 'max:10'],
            'newImages.*' => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:3072'],
        ], [
            'newImages.required' => 'ابتدا تصاویر را انتخاب کنید.',
            'newImages.max'      => 'حداکثر ۱۰ تصویر در هر بار.',
        ]);

        try {
            $uploader->uploadTemplateGalleryImages($this->newImages, $this->template);
        } catch (RuntimeException $e) {
            $this->toast($e->getMessage(), 'error');
            return;
        }

        $count = count($this->newImages);
        $this->newImages = [];
        $this->toast('تعداد ' . fa_num($count) . ' تصویر به گالری اضافه شد.');
    }

    public function deleteImage(ImageUploadService $uploader): void
    {
        $image = TemplateImage::where('template_id', $this->template->id)->findOrFail($this->deleteImageId ?? 0);
        $uploader->deleteTemplateGalleryImage($image);
        $this->deleteImageId = null;
        $this->toast('تصویر حذف شد.');
    }

    /** جابه‌جایی تصویر با همسایه بالا/پایین */
    public function moveImage(int $id, string $direction): void
    {
        $ids = $this->template->images()->orderBy('sort_order')->pluck('id')->values()->all();
        $index = array_search($id, $ids, true);

        if ($index === false) {
            return;
        }

        $swapWith = $direction === 'up' ? $index - 1 : $index + 1;

        if ($swapWith < 0 || $swapWith >= count($ids)) {
            return;
        }

        [$ids[$index], $ids[$swapWith]] = [$ids[$swapWith], $ids[$index]];

        app(ImageUploadService::class)->updateTemplateImageOrder(array_values($ids));
        $this->template->refresh();
    }

    /* ================================================================
     *  لایسنس‌های قالب
     * ================================================================ */

    public function revokeLicense(int $id): void
    {
        $license = TemplateLicense::where('template_id', $this->template->id)->findOrFail($id);
        $license->revoke();
        $this->toast('لایسنس باطل شد.');
    }

    public function restoreLicense(int $id): void
    {
        $license = TemplateLicense::where('template_id', $this->template->id)->findOrFail($id);
        $license->restore();
        $this->toast('لایسنس دوباره فعال شد.');
    }

    /* ================================================================
     *  نسخه‌ها
     * ================================================================ */

    public function createVersion(): void
    {
        $this->resetErrorBag();
        $this->editingVersionId = null;
        $this->versionFile = null;
        $this->versionForm = [
            'version'      => $this->nextVersionSuggestion(),
            'type'         => TemplateVersion::TYPE_MINOR,
            'changelog'    => '',
            'what_added'   => '',
            'what_changed' => '',
            'what_fixed'   => '',
            'is_mandatory' => false,
            'status'       => TemplateVersion::STATUS_ACTIVE,
            'release_date' => now()->format('Y-m-d'),
        ];
        $this->showVersionModal = true;
    }

    public function editVersion(int $id): void
    {
        $this->resetErrorBag();
        $version = TemplateVersion::where('template_id', $this->template->id)->findOrFail($id);

        $this->editingVersionId = $version->id;
        $this->versionFile = null;
        $this->versionForm = [
            'version'      => $version->version,
            'type'         => $version->type,
            'changelog'    => (string) $version->changelog,
            'what_added'   => (string) $version->what_added,
            'what_changed' => (string) $version->what_changed,
            'what_fixed'   => (string) $version->what_fixed,
            'is_mandatory' => $version->is_mandatory,
            'status'       => $version->status,
            'release_date' => $version->release_date?->format('Y-m-d'),
        ];
        $this->showVersionModal = true;
    }

    public function saveVersion(): void
    {
        $data = $this->validate();

        try {
            if ($this->editingVersionId) {
                $version = TemplateVersion::where('template_id', $this->template->id)->findOrFail($this->editingVersionId);

                $payload = [
                    'version'      => trim($data['versionForm']['version']),
                    'type'         => $data['versionForm']['type'],
                    'changelog'    => $data['versionForm']['changelog'] ?: null,
                    'what_added'   => $data['versionForm']['what_added'] ?: null,
                    'what_changed' => $data['versionForm']['what_changed'] ?: null,
                    'what_fixed'   => $data['versionForm']['what_fixed'] ?: null,
                    'is_mandatory' => (bool) $data['versionForm']['is_mandatory'],
                    'status'       => $data['versionForm']['status'],
                    'release_date' => $data['versionForm']['release_date'] ?: null,
                ];

                // فایل جدید (اختیاری هنگام ویرایش)
                if ($this->versionFile) {
                    $this->deleteVersionFile($version);
                    $stored = $this->storeVersionFile($this->versionFile, $payload['version']);
                    $payload = array_merge($payload, $stored);
                }

                $version->update($payload);
                $message = 'نسخه ویرایش شد.';
            } else {
                $stored = $this->versionFile ? $this->storeVersionFile($this->versionFile, trim($data['versionForm']['version'])) : [
                    'file_path' => null, 'file_hash' => null, 'file_size' => null,
                ];

                $version = $this->template->versions()->create([
                    'version'      => trim($data['versionForm']['version']),
                    'type'         => $data['versionForm']['type'],
                    'changelog'    => $data['versionForm']['changelog'] ?: null,
                    'what_added'   => $data['versionForm']['what_added'] ?: null,
                    'what_changed' => $data['versionForm']['what_changed'] ?: null,
                    'what_fixed'   => $data['versionForm']['what_fixed'] ?: null,
                    'is_mandatory' => (bool) $data['versionForm']['is_mandatory'],
                    'status'       => $data['versionForm']['status'],
                    'release_date' => $data['versionForm']['release_date'] ?: now(),
                    ...$stored,
                ]);

                $message = 'نسخه جدید ایجاد شد.';
            }
        } catch (\Throwable $e) {
            report($e);
            $this->toast('ذخیره نسخه ناموفق بود: ' . $e->getMessage(), 'error');
            return;
        }

        $this->showVersionModal = false;
        $this->template->refresh();
        $this->toast($message);
    }

    public function deleteVersion(int $id): void
    {
        $version = TemplateVersion::where('template_id', $this->template->id)->findOrFail($id);

        if ($version->purchases()->where('status', 'paid')->exists()) {
            $this->toast('این نسخه خرید موفق دارد؛ ابتدا وضعیت آن را «آرشیو» کنید.', 'error');
            return;
        }

        $this->deleteVersionFile($version);
        $version->delete();

        $this->template->refresh();
        $this->toast('نسخه حذف شد.');
    }

    /* ================= Comments moderation ================= */

    public function approveComment(int $id): void
    {
        $comment = Comment::where('commentable_type', Comment::TYPE_TEMPLATE)
            ->where('commentable_id', $this->template->id)
            ->findOrFail($id);
        $comment->approve();

        $this->toast('دیدگاه تأیید و منتشر شد.');
    }

    public function rejectComment(int $id): void
    {
        $comment = Comment::where('commentable_type', Comment::TYPE_TEMPLATE)
            ->where('commentable_id', $this->template->id)
            ->findOrFail($id);
        $comment->reject();

        $this->toast('دیدگاه رد شد.');
    }

    public function deleteComment(int $id): void
    {
        $comment = Comment::where('commentable_type', Comment::TYPE_TEMPLATE)
            ->where('commentable_id', $this->template->id)
            ->findOrFail($id);
        $comment->delete();

        $this->toast('دیدگاه حذف شد.');
    }

    /* ================= Helpers ================= */

    private function storeVersionFile($file, string $version): array
    {
        if (!$file || !$file->isValid()) {
            throw new \RuntimeException('فایل ZIP معتبر نیست.');
        }

        $filename = $this->template->slug . '-v' . Str::replace([' ', '/'], ['-', ''], $version) . '-' . time() . '.zip';

        // ذخیره روی disk «local» (storage/app/private/templates/{slug}/)
        $path = $file->storeAs("templates/{$this->template->slug}", $filename, 'local');

        $absolute = Storage::disk('local')->path($path);
        $hash     = hash_file('sha256', $absolute);

        return [
            'file_path' => $path,
            'file_hash' => $hash,
            'file_size' => filesize($absolute),
        ];
    }

    private function deleteVersionFile(TemplateVersion $version): void
    {
        if ($version->file_path && Storage::disk('local')->exists($version->file_path)) {
            Storage::disk('local')->delete($version->file_path);
        }
    }

    private function nextVersionSuggestion(): string
    {
        $last = $this->template->versions()->orderByDesc('id')->first();

        if (!$last || !preg_match('/^(\d+)\.(\d+)\.(\d+)/', (string) $last->version, $m)) {
            return '1.0.0';
        }

        return "{$m[1]}." . ((int) $m[2] + 1) . ".0";
    }

    /* ================= Computed ================= */

    #[Computed]
    public function versions()
    {
        return $this->template->versions()->orderByDesc('id')->get();
    }

    #[Computed]
    public function images()
    {
        return $this->template->images()->orderBy('sort_order')->get();
    }

    #[Computed]
    public function purchases()
    {
        return $this->template->purchases()
            ->with(['customer:id,name,phone', 'version:id,template_id,version', 'discountCode:id,code', 'license:id,license_key'])
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'purchasesPage');
    }

    #[Computed]
    public function licenses()
    {
        return $this->template->licenses()
            ->with(['customer:id,name,phone', 'purchase:id,transaction_id,purchase_type'])
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'licensesPage');
    }

    #[Computed]
    public function comments()
    {
        return $this->template->comments()
            ->latest('id')
            ->paginate(15, pageName: 'commentsPage');
    }

    /** آمار تب اطلاعات */
    #[Computed]
    public function stats(): array
    {
        $paid = $this->template->purchases()->where('status', 'paid');

        return [
            'normal_sales'    => (clone $paid)->where('purchase_type', 'normal')->count(),
            'exclusive_sales' => (clone $paid)->where('purchase_type', 'exclusive')->count(),
            'revenue'         => (int) $paid->sum('amount'),
            'licenses'        => $this->template->licenses()->where('status', TemplateLicense::STATUS_ACTIVE)->count(),
        ];
    }

    public function render()
    {
        return view('livewire.templates.show');
    }
}
