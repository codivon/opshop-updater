<?php

namespace App\Livewire\Products;

use App\Livewire\Concerns\WithToasts;
use App\Models\Product;
use App\Models\ProductComment;
use App\Models\ProductVersion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('جزئیات محصول')]
class Show extends Component
{
    use WithToasts, WithFileUploads;

    public Product $product;

    /** تب فعال: info | versions | comments */
    public string $tab = 'info';

    /* ================= Version form ================= */
    public bool $showVersionModal = false;
    public ?int $editingVersionId = null;
    public $versionFile; // UploadedFile ZIP
    public array $versionForm = [
        'version'      => '',
        'type'         => ProductVersion::TYPE_MINOR,
        'changelog'    => '',
        'what_added'   => '',
        'what_changed' => '',
        'what_fixed'   => '',
        'is_mandatory' => false,
        'status'       => ProductVersion::STATUS_ACTIVE,
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
            'versionFile'              => [$this->editingVersionId ? 'nullable' : 'nullable', 'file', 'mimes:zip', 'max:327680'],
        ];
    }

    protected $messages = [
        'versionForm.version.required' => 'شماره نسخه الزامی است.',
        'versionFile.mimes'            => 'فقط فایل ZIP مجاز است.',
        'versionFile.max'              => 'حجم فایل حداکثر ۳۲۰ مگابایت.',
    ];

    public function mount(Product $product): void
    {
        $this->product = $product->load(['category', 'versions', 'comments.product']);
    }

    /* ================= Tab switching ================= */

    public function switchTab(string $tab): void
    {
        $this->tab = in_array($tab, ['info', 'versions', 'comments']) ? $tab : 'info';
    }

    /* ================= Versions CRUD ================= */

    public function createVersion(): void
    {
        $this->resetErrorBag();
        $this->editingVersionId = null;
        $this->versionFile = null;
        $this->versionForm = [
            'version'      => $this->nextVersionSuggestion(),
            'type'         => ProductVersion::TYPE_MINOR,
            'changelog'    => '',
            'what_added'   => '',
            'what_changed' => '',
            'what_fixed'   => '',
            'is_mandatory' => false,
            'status'       => ProductVersion::STATUS_ACTIVE,
            'release_date' => now()->format('Y-m-d'),
        ];
        $this->showVersionModal = true;
    }

    public function editVersion(int $id): void
    {
        $this->resetErrorBag();
        $version = ProductVersion::where('product_id', $this->product->id)->findOrFail($id);

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
                $version = ProductVersion::where('product_id', $this->product->id)->findOrFail($this->editingVersionId);

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

                $version = $this->product->versions()->create([
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
        $this->product->refresh();
        $this->toast($message);
    }

    public function deleteVersion(int $id): void
    {
        $version = ProductVersion::where('product_id', $this->product->id)->findOrFail($id);

        if ($version->purchases()->where('status', 'paid')->exists()) {
            $this->toast('این نسخه خرید موفق دارد؛ ابتدا وضعیت آن را «آرشیو» کنید.', 'error');
            return;
        }

        $this->deleteVersionFile($version);
        $version->delete();

        $this->product->refresh();
        $this->toast('نسخه حذف شد.');
    }

    /* ================= Comments moderation ================= */

    public function approveComment(int $id): void
    {
        $comment = ProductComment::where('product_id', $this->product->id)->findOrFail($id);
        $comment->approve();

        $this->toast('دیدگاه تأیید و منتشر شد.');
    }

    public function rejectComment(int $id): void
    {
        $comment = ProductComment::where('product_id', $this->product->id)->findOrFail($id);
        $comment->reject();

        $this->toast('دیدگاه رد شد.');
    }

    public function deleteComment(int $id): void
    {
        $comment = ProductComment::where('product_id', $this->product->id)->findOrFail($id);
        $comment->delete();

        $this->toast('دیدگاه حذف شد.');
    }

    /* ================= Helpers ================= */

    private function storeVersionFile($file, string $version): array
    {
        if (!$file || !$file->isValid()) {
            throw new \RuntimeException('فایل ZIP معتبر نیست.');
        }

        $filename = $this->product->slug . '-v' . Str::replace([' ', '/'], ['-', ''], $version) . '-' . time() . '.zip';

        // ذخیره روی disk «local» (storage/app/private/products/{slug}/)
        $path = $file->storeAs("products/{$this->product->slug}", $filename, 'local');

        $absolute = Storage::disk('local')->path($path);
        $hash     = hash_file('sha256', $absolute);

        return [
            'file_path' => $path,
            'file_hash' => $hash,
            'file_size' => filesize($absolute),
        ];
    }

    private function deleteVersionFile(ProductVersion $version): void
    {
        if ($version->file_path && Storage::disk('local')->exists($version->file_path)) {
            Storage::disk('local')->delete($version->file_path);
        }
    }

    private function nextVersionSuggestion(): string
    {
        $last = $this->product->versions()->orderByDesc('id')->first();

        if (!$last || !preg_match('/^(\d+)\.(\d+)\.(\d+)/', (string) $last->version, $m)) {
            return '1.0.0';
        }

        return "{$m[1]}." . ((int) $m[2] + 1) . ".0";
    }

    /* ================= Computed ================= */

    #[Computed]
    public function versions()
    {
        return $this->product->versions()->orderByDesc('id')->get();
    }

    #[Computed]
    public function comments()
    {
        return $this->product->comments()
            ->latest('id')
            ->paginate(15, pageName: 'commentsPage');
    }

    public function render()
    {
        return view('livewire.products.show');
    }
}
