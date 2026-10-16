<?php

namespace App\Livewire\Packages;

use App\Livewire\Concerns\WithToasts;
use App\Models\Package;
use App\Models\PackageImage;
use App\Models\PackagePricingPlan;
use App\Models\PackageVersion;
use App\Services\ImageUploadService;
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
#[Title('جزئیات پکیج')]
class Show extends Component
{
    use WithFileUploads, WithPagination, WithToasts;

    public Package $package;

    /* ---------------- package edit modal ---------------- */

    public bool $showModal = false;

    /** @var array<string, mixed> */
    public array $form = [];

    public bool $removeThumbnail = false;

    /** @var \Livewire\TemporaryUploadedFile|null */
    public $thumbnailFile = null;

    /** @var array<int, \Livewire\TemporaryUploadedFile> */
    public array $newGallery = [];

    public ?int $deleteId = null;

    /* ---------------- version modal ---------------- */

    public bool $showVersionModal = false;

    public ?int $editingVersionId = null;

    /** @var array<string, mixed> */
    public array $versionForm = [];

    /** @var \Livewire\TemporaryUploadedFile|null */
    public $versionFile = null;

    public ?int $deleteVersionId = null;

    /* ---------------- plan modal ---------------- */

    public bool $showPlanModal = false;

    public ?int $editingPlanId = null;

    /** @var array<string, mixed> */
    public array $planForm = [];

    public ?int $deletePlanId = null;

    /* ---------------- images tab ---------------- */

    /** @var array<int, \Livewire\TemporaryUploadedFile> */
    public array $newImages = [];

    public ?int $deleteImageId = null;

    public function mount(Package $package): void
    {
        $this->package = $package;
    }

    /* ---------------------------------------------------------------- */
    /*  Computed lists                                                   */
    /* ---------------------------------------------------------------- */

    #[Computed]
    public function versions()
    {
        return $this->package->versions()->paginate(10, ['*'], 'versionsPage');
    }

    #[Computed]
    public function pricingPlans()
    {
        return $this->package->pricingPlans()->get();
    }

    #[Computed]
    public function licenses()
    {
        return $this->package->licenses()
            ->with('customer:id,name')
            ->paginate(10, ['*'], 'licensesPage');
    }

    #[Computed]
    public function images()
    {
        return $this->package->images()->orderBy('sort_order')->get();
    }

    /** شمارش‌ها برای بخش آمار بالای صفحه */
    #[Computed]
    public function counts(): array
    {
        return [
            'versions'        => $this->package->versions()->count(),
            'active_licenses' => $this->package->licenses()->where('status', 'active')->count(),
            'licenses'        => $this->package->licenses()->count(),
            'plans'           => $this->package->pricingPlans()->count(),
            'images'          => $this->package->images()->count(),
        ];
    }

    #[Computed]
    public function projects()
    {
        return \App\Models\Project::query()->orderBy('name')->get(['id', 'name']);
    }

    /** دسته‌بندی‌های ساختاریافته پکیج (جدول categories) */
    #[Computed]
    public function categories()
    {
        return \App\Models\Category::query()
            ->ofType(\App\Models\Category::TYPE_PACKAGE)
            ->ordered()
            ->get()
            ->mapWithKeys(fn ($cat) => [$cat->id => $cat->name])
            ->all();
    }

    /* ---------------------------------------------------------------- */
    /*  Package edit (all fields of the old edit form)                   */
    /* ---------------------------------------------------------------- */

    public function openEditPackage(): void
    {
        $this->resetValidation();
        $this->form = $this->package->only([
            'project_id', 'name', 'slug', 'short_description', 'description',
            'author', 'is_free', 'default_price', 'status',
            'module_name', 'sort_order',
        ]);
        $this->form['category_id'] = (string) ($this->package->category_id ?? '');
        $this->form['thumbnail_url'] = '';
        $this->removeThumbnail = false;
        $this->thumbnailFile = null;
        $this->newGallery = [];
        $this->showModal = true;
    }

    public function savePackage(): void
    {
        foreach (['default_price', 'sort_order'] as $key) {
            $this->form[$key] = blank($this->form[$key] ?? null) ? null : $this->form[$key];
        }

        $validated = $this->validate([
            'form.project_id'        => ['required', 'exists:projects,id'],
            'form.name'              => ['required', 'string', 'max:255'],
            'form.slug'              => ['nullable', 'string', 'max:100', 'unique:packages,slug,' . $this->package->id],
            'form.short_description' => ['nullable', 'string', 'max:10000'],
            'form.description'       => ['nullable', 'string'],
            'form.author'            => ['nullable', 'string', 'max:100'],
            'form.category_id'       => ['nullable', 'exists:categories,id'],
            'form.thumbnail_url'     => ['nullable', 'url', 'max:255'],
            'form.is_free'           => ['nullable', 'boolean'],
            'form.default_price'     => ['nullable', 'integer', 'min:0'],
            'form.status'            => ['required', 'in:draft,active,archived'],
            'form.module_name'       => ['nullable', 'string', 'max:100'],
            'form.sort_order'        => ['nullable', 'integer', 'min:0'],
            'thumbnailFile'          => ['nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:3072'],
            'newGallery'             => ['nullable', 'array', 'max:10'],
            'newGallery.*'           => ['image', 'mimes:jpeg,png,webp,gif', 'max:3072'],
        ], [
            'form.name.required'       => 'نام پکیج الزامی است.',
            'form.slug.unique'         => 'این نامک قبلاً استفاده شده است.',
            'form.thumbnail_url.url'   => 'آدرس تصویر شاخص معتبر نیست.',
            'form.status.required'     => 'وضعیت را انتخاب کنید.',
        ]);

        $form = $validated['form'];

        // تولید slug / module_name در صورت خالی بودن
        if (blank($form['slug'] ?? null)) {
            $form['slug'] = Str::slug($form['name']);
        }
        if (blank($form['module_name'] ?? null)) {
            $form['module_name'] = ucfirst(Str::camel($form['slug']));
        }

        $form['is_free'] = (bool) ($form['is_free'] ?? false);
        $form['default_price'] = (int) ($form['default_price'] ?? 0);
        $form['sort_order'] = (int) ($form['sort_order'] ?? 0);

        // دسته‌بندی ساختاریافته → هم category_id و هم ستون قدیمی category (سازگاری API)
        $form['category_id'] = filled($form['category_id'] ?? '') ? (int) $form['category_id'] : null;
        $form['category'] = $form['category_id']
            ? (string) \App\Models\Category::find($form['category_id'])?->name
            : null;

        $imageService = app(ImageUploadService::class);

        // مدیریت تصویر شاخص (مطابق منطق قدیم)
        if ($this->removeThumbnail) {
            $imageService->deleteThumbnail($this->package);
            $form['thumbnail'] = null;
        } elseif ($this->thumbnailFile) {
            $imageService->deleteThumbnail($this->package);
            $form['thumbnail'] = $imageService->uploadThumbnail($this->thumbnailFile, $form['slug']);
        } elseif (!blank($form['thumbnail_url'] ?? null)) {
            $imageService->deleteThumbnail($this->package);
            $form['thumbnail'] = $form['thumbnail_url'];
        }

        unset($form['thumbnail_url']);

        try {
            $this->package->update($form);

            // آپلود گالری جدید (به گالری موجود اضافه می‌شود)
            if ($this->newGallery) {
                $imageService->uploadGalleryImages($this->newGallery, $this->package);
            }
        } catch (RuntimeException $e) {
            $this->toast($e->getMessage(), 'error');

            return;
        }

        $this->thumbnailFile = null;
        $this->newGallery = [];
        $this->removeThumbnail = false;
        $this->showModal = false;
        $this->toast('پکیج با موفقیت به‌روزرسانی شد.');
    }

    public function deletePackage(): void
    {
        $imageService = app(ImageUploadService::class);

        // حذف تصویر شاخص و گالری
        $imageService->deleteThumbnail($this->package);
        $imageService->deleteAllGalleryImages($this->package);

        // حذف فایل‌های ZIP نسخه‌ها
        foreach ($this->package->versions()->get() as $version) {
            if ($version->file_path && file_exists(storage_path('app/' . $version->file_path))) {
                @unlink(storage_path('app/' . $version->file_path));
            }
        }

        $name = $this->package->name;
        $this->package->delete();
        $this->toast("پکیج «{$name}» حذف شد.");
        $this->redirect(route('admin.packages.index'), true);
    }

    /* ---------------------------------------------------------------- */
    /*  Versions                                                         */
    /* ---------------------------------------------------------------- */

    public function openVersionCreate(): void
    {
        $this->resetValidation();
        $this->editingVersionId = null;
        $this->versionFile = null;
        $this->versionForm = [
            'version'             => '',
            'type'                => 'minor',
            'changelog'           => '',
            'what_added'          => '',
            'what_changed'        => '',
            'what_fixed'          => '',
            'min_project_version' => '',
            'min_php_version'     => '',
            'min_laravel_version' => '',
            'dependencies'        => '',
            'is_mandatory'        => false,
            'status'              => 'active',
            'release_date'        => now()->format('Y-m-d'),
        ];
        $this->showVersionModal = true;
    }

    public function openVersionEdit(int $id): void
    {
        $version = PackageVersion::where('package_id', $this->package->id)->findOrFail($id);
        $this->resetValidation();
        $this->editingVersionId = $version->id;
        $this->versionFile = null;
        $this->versionForm = [
            'version'             => $version->version,
            'type'                => $version->type,
            'changelog'           => $version->changelog ?? '',
            'what_added'          => $version->what_added ?? '',
            'what_changed'        => $version->what_changed ?? '',
            'what_fixed'          => $version->what_fixed ?? '',
            'min_project_version' => $version->min_project_version ?? '',
            'min_php_version'     => $version->min_php_version ?? '',
            'min_laravel_version' => $version->min_laravel_version ?? '',
            'dependencies'        => filled($version->dependencies) ? json_encode($version->dependencies, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '',
            'is_mandatory'        => (bool) $version->is_mandatory,
            'status'              => $version->status,
            'release_date'        => $version->release_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
        ];
        $this->showVersionModal = true;
    }

    public function saveVersion(): void
    {
        $unique = 'unique:package_versions,version,' . ($this->editingVersionId ?? 'NULL') . ',id,package_id,' . $this->package->id;

        $validated = $this->validate([
            'versionForm.version'             => ['required', 'string', 'max:50', $unique],
            'versionForm.type'                => ['required', 'in:major,minor,patch'],
            'versionForm.changelog'           => ['nullable', 'string'],
            'versionForm.what_added'          => ['nullable', 'string'],
            'versionForm.what_changed'        => ['nullable', 'string'],
            'versionForm.what_fixed'          => ['nullable', 'string'],
            'versionForm.min_project_version' => ['nullable', 'string', 'max:20'],
            'versionForm.min_php_version'     => ['nullable', 'string', 'max:20'],
            'versionForm.min_laravel_version' => ['nullable', 'string', 'max:20'],
            'versionForm.dependencies'        => ['nullable', 'string'],
            'versionForm.is_mandatory'        => ['nullable', 'boolean'],
            'versionForm.status'              => ['required', 'in:draft,active,archived'],
            'versionForm.release_date'        => ['nullable', 'date'],
            'versionFile'                     => array_filter([
                $this->editingVersionId ? 'nullable' : 'required',
                'file', 'mimes:zip', 'max:307200',
            ]),
        ], [
            'versionForm.version.required' => 'شماره نسخه الزامی است.',
            'versionForm.version.unique'   => 'این نسخه برای همین پکیج قبلاً ثبت شده است.',
            'versionFile.required'         => 'فایل ZIP نسخه الزامی است.',
            'versionFile.mimes'            => 'فقط فایل ZIP مجاز است.',
        ]);

        $form = $this->versionForm;

        // پردازش وابستگی‌ها (JSON)
        $dependencies = $this->parseDependenciesJson(trim((string) ($form['dependencies'] ?? '')));
        if ($dependencies === false) {
            $this->addError('versionForm.dependencies', 'وابستگی‌ها باید JSON معتبر باشند (مثلاً {"some-module":"1.2.0"}).');

            return;
        }

        $releaseDate = blank($form['release_date'] ?? null) ? now() : $form['release_date'];

        $data = [
            'package_id'         => $this->package->id,
            'version'            => $form['version'],
            'type'               => $form['type'],
            'changelog'          => $form['changelog'] ?: null,
            'what_added'         => $form['what_added'] ?: null,
            'what_changed'       => $form['what_changed'] ?: null,
            'what_fixed'         => $form['what_fixed'] ?: null,
            'min_project_version' => $form['min_project_version'] ?: null,
            'min_php_version'     => $form['min_php_version'] ?: null,
            'min_laravel_version' => $form['min_laravel_version'] ?: null,
            'dependencies'       => $dependencies,
            'is_mandatory'       => (bool) ($form['is_mandatory'] ?? false),
            'status'             => $form['status'],
            'release_date'       => $releaseDate,
        ];

        // آپلود فایل ZIP جدید (در ایجاد الزامی، در ویرایش اختیاری)
        if ($this->versionFile) {
            if ($this->editingVersionId) {
                $old = PackageVersion::find($this->editingVersionId);
                if ($old?->file_path && Storage::disk('local')->exists($old->file_path)) {
                    Storage::disk('local')->delete($old->file_path);
                }
            }

            $filename = $this->package->slug . '_v' . Str::slug($form['version']) . '_' . time() . '.zip';
            $path = $this->versionFile->storeAs('packages', $filename, 'local');
            $absolutePath = Storage::disk('local')->path($path);

            $data['file_path'] = $path;
            $data['file_hash'] = hash_file('sha256', $absolutePath);
            $data['file_size'] = (int) filesize($absolutePath);
        }

        if ($this->editingVersionId) {
            PackageVersion::findOrFail($this->editingVersionId)->update($data);
            $this->toast('نسخه با موفقیت به‌روزرسانی شد.');
        } else {
            PackageVersion::create($data);
            $this->toast("نسخه {$form['version']} با موفقیت آپلود شد.");
        }

        $this->versionFile = null;
        $this->showVersionModal = false;
    }

    public function deleteVersion(): void
    {
        $version = PackageVersion::where('package_id', $this->package->id)->findOrFail($this->deleteVersionId ?? 0);

        // حذف فایل
        if ($version->file_path && Storage::disk('local')->exists($version->file_path)) {
            Storage::disk('local')->delete($version->file_path);
        }

        $version->delete();
        $this->deleteVersionId = null;
        $this->toast('نسخه حذف شد.');
    }

    /* ---------------------------------------------------------------- */
    /*  Pricing plans                                                    */
    /* ---------------------------------------------------------------- */

    public function openPlanCreate(): void
    {
        $this->resetValidation();
        $this->editingPlanId = null;
        $this->planForm = [
            'name'            => '',
            'duration_months' => 12,
            'price'           => '',
            'discount_price'  => '',
            'is_one_time'     => false,
            'description'     => '',
            'is_active'       => true,
            'sort_order'      => 0,
        ];
        $this->showPlanModal = true;
    }

    public function openPlanEdit(int $id): void
    {
        $plan = PackagePricingPlan::where('package_id', $this->package->id)->findOrFail($id);
        $this->resetValidation();
        $this->editingPlanId = $plan->id;
        $this->planForm = $plan->only([
            'name', 'duration_months', 'price', 'discount_price',
            'is_one_time', 'description', 'is_active', 'sort_order',
        ]);
        $this->showPlanModal = true;
    }

    public function savePlan(): void
    {
        foreach (['price', 'discount_price', 'sort_order'] as $key) {
            $this->planForm[$key] = blank($this->planForm[$key] ?? null) ? null : $this->planForm[$key];
        }

        $validated = $this->validate([
            'planForm.name'            => ['required', 'string', 'max:50'],
            'planForm.duration_months' => ['required', 'integer', 'min:0', 'max:120'],
            'planForm.price'           => ['required', 'integer', 'min:0'],
            'planForm.discount_price'  => ['nullable', 'integer', 'min:0', 'lt:planForm.price'],
            'planForm.is_one_time'     => ['nullable', 'boolean'],
            'planForm.description'     => ['nullable', 'string', 'max:500'],
            'planForm.is_active'       => ['nullable', 'boolean'],
            'planForm.sort_order'      => ['nullable', 'integer', 'min:0'],
        ], [
            'planForm.name.required'           => 'نام طرح الزامی است.',
            'planForm.price.required'          => 'قیمت الزامی است.',
            'planForm.discount_price.lt'       => 'قیمت با تخفیف باید کمتر از قیمت اصلی باشد.',
        ]);

        $form = $validated['planForm'];
        $form['is_one_time'] = (bool) ($form['is_one_time'] ?? false);
        $form['is_active'] = (bool) ($form['is_active'] ?? false);
        $form['discount_price'] = $form['discount_price'] ?? null;
        $form['description'] = $form['description'] ?? null;
        $form['sort_order'] = (int) ($form['sort_order'] ?? 0);

        if ($this->editingPlanId) {
            PackagePricingPlan::where('package_id', $this->package->id)->findOrFail($this->editingPlanId)->update($form);
            $this->toast('طرح قیمت‌گذاری به‌روزرسانی شد.');
        } else {
            $this->package->pricingPlans()->create($form);
            $this->toast('طرح قیمت‌گذاری ایجاد شد.');
        }

        $this->showPlanModal = false;
    }

    public function deletePlan(): void
    {
        $plan = PackagePricingPlan::where('package_id', $this->package->id)->findOrFail($this->deletePlanId ?? 0);
        $plan->delete();
        $this->deletePlanId = null;
        $this->toast('طرح قیمت‌گذاری حذف شد.');
    }

    /* ---------------------------------------------------------------- */
    /*  Gallery images                                                   */
    /* ---------------------------------------------------------------- */

    public function uploadImages(): void
    {
        $this->validate([
            'newImages'   => ['required', 'array', 'max:10'],
            'newImages.*' => ['image', 'mimes:jpeg,png,webp,gif', 'max:3072'],
        ], [
            'newImages.required' => 'ابتدا تصاویر را انتخاب کنید.',
            'newImages.max'      => 'حداکثر ۱۰ تصویر در هر بار.',
        ]);

        try {
            app(ImageUploadService::class)->uploadGalleryImages($this->newImages, $this->package);
        } catch (RuntimeException $e) {
            $this->toast($e->getMessage(), 'error');

            return;
        }

        $count = count($this->newImages);
        $this->newImages = [];
        $this->toast('تعداد ' . fa_num($count) . ' تصویر به گالری اضافه شد.');
    }

    public function deleteImage(): void
    {
        $image = PackageImage::where('package_id', $this->package->id)->findOrFail($this->deleteImageId ?? 0);
        app(ImageUploadService::class)->deleteGalleryImage($image);
        $this->deleteImageId = null;
        $this->toast('تصویر حذف شد.');
    }

    /** جابه‌جایی تصویر با همسایه بالا/پایین (منطق قدیمی updateOrder) */
    public function moveImage(int $id, string $direction): void
    {
        $ids = $this->package->images()->orderBy('sort_order')->pluck('id')->values()->all();
        $index = array_search($id, $ids, true);

        if ($index === false) {
            return;
        }

        $swapWith = $direction === 'up' ? $index - 1 : $index + 1;

        if ($swapWith < 0 || $swapWith >= count($ids)) {
            return;
        }

        [$ids[$index], $ids[$swapWith]] = [$ids[$swapWith], $ids[$index]];

        app(ImageUploadService::class)->updateOrder(array_values($ids));
    }

    /* ---------------------------------------------------------------- */

    public function render()
    {
        return view('livewire.packages.show');
    }

    /* ---------------------------------------------------------------- */
    /*  Helpers                                                          */
    /* ---------------------------------------------------------------- */

    /**
     * JSON وابستگی‌ها را به آرایه نگاشت slug => version تبدیل می‌کند.
     * خروجی false یعنی JSON نامعتبر است.
     *
     * @return array|false|null
     */
    private function parseDependenciesJson(string $raw): array|false|null
    {
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            return false;
        }

        $dependencies = [];

        foreach ($decoded as $key => $value) {
            if (is_int($key) && is_array($value)) {
                // فرمت قدیمی: [{"slug": "...", "version": "..."}]
                $slug = $value['slug'] ?? null;
                $version = $value['version'] ?? null;
                if ($slug && $version) {
                    $dependencies[$slug] = $version;
                }
            } elseif (is_string($key) && is_scalar($value)) {
                $dependencies[$key] = (string) $value;
            }
        }

        return $dependencies ?: null;
    }
}
