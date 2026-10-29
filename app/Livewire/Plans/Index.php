<?php

namespace App\Livewire\Plans;

use App\Livewire\Concerns\WithBulkActions;
use App\Livewire\Concerns\WithToasts;
use App\Models\SubscriptionPlan;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('طرح‌های اشتراک')]
class Index extends Component
{
    use WithPagination, WithToasts, WithBulkActions;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    /** فیلتر نمایش/ترتیب */
    #[Url]
    public string $sort = 'newest';

    public ?int $deleteId = null;

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
        return SubscriptionPlan::query()
            ->withCount('packages')
            ->withCount(['orders as paid_orders_count' => fn ($q) => $q->where('status', 'paid')])
            ->with(['durations', 'durations.packages']) // پکیج‌های هر مدت برای چیپ‌های جدول (Task 64)
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('slug', 'like', "%{$this->search}%")
                ->orWhere('description', 'like', "%{$this->search}%")))
            ->when($this->status !== '', fn ($q) => $q->where('is_active', $this->status === 'active'))
            ->when($this->sort === 'newest', fn ($q) => $q->latest())
            ->when($this->sort === 'oldest', fn ($q) => $q->oldest())
            ->when($this->sort === 'id_desc', fn ($q) => $q->orderByDesc('id'))
            ->when($this->sort === 'id_asc', fn ($q) => $q->orderBy('id'))
            ->when($this->sort === 'name_asc', fn ($q) => $q->orderBy('name'))
            ->when($this->sort === 'name_desc', fn ($q) => $q->orderByDesc('name'))
            ->when($this->sort === 'price_desc', fn ($q) => $q->orderByDesc('price'))
            ->when($this->sort === 'price_asc', fn ($q) => $q->orderBy('price'))
            ->when($this->sort === 'sort_order', fn ($q) => $q->orderBy('sort_order')->orderByDesc('id'))
            ->paginate(12);
    }

    /* ---------------------------------------------------------------- */
    /*  CRUD                                                             */
    /* ---------------------------------------------------------------- */

    public function delete(): void
    {
        $plan = SubscriptionPlan::findOrFail($this->deleteId ?? 0);

        $name = $plan->name;

        // سفارش‌ها با nullOnDelete حفظ می‌شوند (snapshot در meta)؛ pivot ها cascade
        $plan->delete();

        $this->deleteId = null;
        $this->toast("طرح «{$name}» حذف شد.");
    }

    public function toggleActive(int $id): void
    {
        $plan = SubscriptionPlan::findOrFail($id);
        $plan->update(['is_active' => !$plan->is_active]);

        $this->toast($plan->is_active ? 'طرح فعال شد.' : 'طرح غیرفعال شد.');
    }

    /**
     * کپی کامل یک طرح (Task 65) — نام/قابلیت‌ها/پکیج‌های سطح طرح +
     * همهٔ مدت‌های اعتبار با پیکربندی اختصاصی هر مدت (قابلیت‌ها و پکیج‌ها).
     *
     * کپی به‌صورت «غیرفعال» ساخته می‌شود تا بی‌اجازه در فروشگاه منتشر نشود؛
     * مدیر بعد از ویرایش نام/قیمت خودش آن را فعال می‌کند.
     */
    public function copy(int $id): void
    {
        $plan = SubscriptionPlan::with(['packages', 'durations', 'durations.packages'])->find($id);

        if (!$plan) {
            $this->toast('طرح موردنظر یافت نشد؛ ممکن است حذف شده باشد.', 'warning');

            return;
        }

        // نام و شناسهٔ یکتا برای کپی
        $name = $plan->name . ' (کپی)';
        $base = trim($plan->slug) !== '' ? $plan->slug . '-copy' : (\Illuminate\Support\Str::slug($name) ?: 'plan-copy');
        $slug = $base;

        while (SubscriptionPlan::where('slug', $slug)->exists()) {
            $slug = $base . '-' . strtolower(\Illuminate\Support\Str::random(4));
        }

        $copy = SubscriptionPlan::create([
            'name'            => $name,
            'slug'            => $slug,
            'description'     => $plan->description,
            'duration_months' => $plan->duration_months,
            'price'           => $plan->price,
            'discount_price'  => $plan->discount_price,
            'is_one_time'     => $plan->is_one_time,
            'is_active'       => false, // پس از ویرایش، مدیر فعال می‌کند
            'sort_order'      => $plan->sort_order,
            'features'        => $plan->features,
        ]);

        // پکیج‌های همراه سطح طرح (با مدت دسترسی رایگان هر کدام)
        $planPackages = $plan->packages
            ->mapWithKeys(fn ($p) => [$p->id => ['free_months' => (int) $p->pivot->free_months]])
            ->all();

        if ($planPackages !== []) {
            $copy->packages()->sync($planPackages);
        }

        // همهٔ مدت‌های اعتبار + پیکربندی اختصاصی هر مدت (قابلیت‌ها و پکیج‌ها)
        foreach ($plan->durations as $duration) {
            $durationCopy = $copy->durations()->create([
                'duration_months' => $duration->duration_months,
                'price'           => $duration->price,
                'discount_price'  => $duration->discount_price,
                'is_active'       => $duration->is_active,
                'sort_order'      => $duration->sort_order,
                'features'        => $duration->features,
            ]);

            $durationPackages = $duration->packages
                ->mapWithKeys(fn ($p) => [$p->id => ['free_months' => (int) $p->pivot->free_months]])
                ->all();

            if ($durationPackages !== []) {
                $durationCopy->packages()->sync($durationPackages);
            }
        }

        unset($this->records);

        $this->toast("کپی طرح «{$plan->name}» ساخته شد — کپی غیرفعال است؛ پس از ویرایش فعالش کنید.");
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

        $count = SubscriptionPlan::query()->whereIn('id', $ids)->delete();

        $this->toast(fa_num($count) . ' طرح حذف شد.');
    }

    /**
     * فرم افزودن/ویرایش به کامپوننت مجزا (Plans\Form) منتقل شده (Task 64 — رفع کندی)؛
     * بعد از ذخیره در فرم، این رویداد می‌رسد و فقط همین‌جا لیست تازه می‌شود.
     */
    #[On('plan-saved')]
    public function refreshList(): void
    {
        unset($this->records);
    }

    public function render()
    {
        return view('livewire.plans.index');
    }
}
