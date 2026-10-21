<?php

namespace App\Livewire\Licenses;

use App\Livewire\Concerns\WithBulkActions;
use App\Livewire\Concerns\WithToasts;
use App\Models\Package;
use App\Models\PackageLicense;
use App\Models\Template;
use App\Models\TemplateLicense;
use App\Services\LicenseService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('لایسنس‌ها')]
class Index extends Component
{
    use WithPagination, WithToasts, WithBulkActions;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $package_id = '';

    /** فیلتر نمایش/ترتیب: جدیدترین، قدیمی‌ترین، شناسه، تاریخ انقضا و… */
    #[Url]
    public string $sort = 'newest';

    /** نوع لایسنس: package | template (تب) */
    #[Url]
    public string $type = 'package';

    public ?int $revokeId = null;

    /** آی‌دی لایسنس برای حذف تکی */
    public ?int $deleteId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedPackageId(): void
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
        return PackageLicense::query()
            ->with(['package:id,name,slug', 'customer:id,name,phone'])
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('license_key', 'like', "%{$this->search}%")
                ->orWhereHas('customer', fn ($cq) => $cq
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%"))))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->package_id, fn ($q) => $q->where('package_id', (int) $this->package_id))
            ->when($this->sort === 'newest', fn ($q) => $q->latest())
            ->when($this->sort === 'oldest', fn ($q) => $q->oldest())
            ->when($this->sort === 'id_desc', fn ($q) => $q->orderByDesc('id'))
            ->when($this->sort === 'id_asc', fn ($q) => $q->orderBy('id'))
            ->when($this->sort === 'expires_soon', fn ($q) => $q->orderByRaw('expires_at is null')->orderBy('expires_at'))
            ->when($this->sort === 'expires_late', fn ($q) => $q->orderByRaw('expires_at is null')->orderByDesc('expires_at'))
            ->paginate(12);
    }

    #[Computed]
    public function packages()
    {
        return Package::query()->orderBy('name')->get(['id', 'name']);
    }

    /** سوییچ تب لایسنس‌ها */
    public function switchType(string $type): void
    {
        if (!in_array($type, ['package', 'template'], true)) {
            return;
        }

        $this->type = $type;
        $this->resetPage();
    }

    /** لایسنس‌های قالب (تب دوم) */
    #[Computed]
    public function templateRecords()
    {
        return TemplateLicense::query()
            ->with(['template:id,name,slug', 'customer:id,name,phone', 'purchase:id,transaction_id,purchase_type'])
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('license_key', 'like', "%{$this->search}%")
                ->orWhereHas('customer', fn ($cq) => $cq
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%"))
                ->orWhereHas('template', fn ($tq) => $tq
                    ->where('name', 'like', "%{$this->search}%"))))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->sort === 'newest', fn ($q) => $q->latest())
            ->when($this->sort === 'oldest', fn ($q) => $q->oldest())
            ->when($this->sort === 'id_desc', fn ($q) => $q->orderByDesc('id'))
            ->when($this->sort === 'id_asc', fn ($q) => $q->orderBy('id'))
            ->paginate(12);
    }

    /** پرفروش‌ترین قالب‌ها برای فیلتر (فهرست ساده) */
    #[Computed]
    public function templates()
    {
        return Template::query()->orderBy('name')->get(['id', 'name']);
    }

    /* ---------------------------------------------------------------- */
    /*  Actions — لایسنس‌های قالب                                        */
    /* ---------------------------------------------------------------- */

    public function revokeTemplateLicense(): void
    {
        $license = TemplateLicense::findOrFail($this->revokeId ?? 0);
        $license->revoke();

        $this->revokeId = null;
        $this->toast('لایسنس قالب باطل شد.');
    }

    public function activateTemplateLicense(int $id): void
    {
        $license = TemplateLicense::findOrFail($id);
        $license->restore();

        $this->toast('لایسنس قالب فعال شد.');
    }

    public function deleteTemplateLicense(): void
    {
        $license = TemplateLicense::findOrFail($this->deleteId ?? 0);

        $key = $license->license_key;
        $license->delete();

        $this->deleteId = null;
        $this->toast("لایسنس «{$key}» حذف شد.");
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

        // توکن‌های دانلود مرتبط (FK cascade نیست در برخی ست‌آپ‌ها → دستی برای اطمینان)
        \App\Models\PackageDownloadToken::whereIn('license_id', $ids)->delete();

        $count = PackageLicense::query()->whereIn('id', $ids)->delete();

        $this->toast(fa_num($count) . ' لایسنس حذف شد.');
    }

    /* ---------------------------------------------------------------- */
    /*  Actions (ported from old PackageLicenseController)               */
    /* ---------------------------------------------------------------- */

    public function revoke(): void
    {
        $license = PackageLicense::findOrFail($this->revokeId ?? 0);

        $license->revoke();

        $this->revokeId = null;
        $this->toast('لایسنس باطل شد.');
    }

    public function activate(int $id): void
    {
        $license = PackageLicense::findOrFail($id);

        $license->update(['status' => PackageLicense::STATUS_ACTIVE]);

        $this->toast('لایسنس فعال شد.');
    }

    /** حذف تکی لایسنس */
    public function delete(): void
    {
        $license = PackageLicense::findOrFail($this->deleteId ?? 0);

        \App\Models\PackageDownloadToken::where('license_id', $license->id)->delete();

        $key = $license->license_key;
        $license->delete();

        $this->deleteId = null;
        $this->toast("لایسنس «{$key}» حذف شد.");
    }

    public function expireOld(): void
    {
        $count = app(LicenseService::class)->expireOldLicenses();

        $this->toast(fa_num($count) . ' لایسنس منقضی به‌روزرسانی شد.');
    }

    public function render()
    {
        return view('livewire.licenses.index');
    }
}
