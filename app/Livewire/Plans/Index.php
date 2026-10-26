<?php

namespace App\Livewire\Plans;

use App\Livewire\Concerns\WithBulkActions;
use App\Livewire\Concerns\WithToasts;
use App\Models\Package;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanDuration;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
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

    public bool $showModal = false;
    public ?int $editingId = null;
    public ?int $deleteId = null;

    /** فرم اصلی طرح — مدت/قیمت/تخفیف دیگر اینجا نیست؛ همه از مدت‌های اعتبار می‌آیند */
    public array $form = [
        'name'        => '',
        'slug'        => '',
        'description' => '',
        'is_one_time' => false,
        'is_active'   => true,
        'sort_order'  => '0',
    ];

    /** قابلیت‌های طرح (لیست رشته‌ای) */
    public array $formFeatures = [''];

    /** پکیج‌های همراه: [['package_id' => '', 'free_months' => '1'], …] */
    public array $formPackages = [];

    /** مدت‌های اعتبار (حداقل یک ردیف الزامی): [['duration_months' => '1', 'price' => '0', 'discount_price' => '', 'is_active' => true], …] */
    public array $formDurations = [];

    /** یک ردیف خالی برای ریپیتر مدت‌های اعتبار */
    protected function emptyDurationRow(): array
    {
        return ['duration_months' => '', 'price' => '', 'discount_price' => '', 'is_active' => true];
    }

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
            ->with('durations')
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

    #[Computed]
    public function packages()
    {
        return Package::query()->orderBy('name')->get(['id', 'name', 'slug']);
    }

    /* ---------------------------------------------------------------- */
    /*  Features / packages editors                                      */
    /* ---------------------------------------------------------------- */

    public function addFeature(): void
    {
        $this->formFeatures[] = '';
    }

    public function removeFeature(int $index): void
    {
        unset($this->formFeatures[$index]);
        $this->formFeatures = array_values($this->formFeatures);
    }

    public function addPackageRow(): void
    {
        $this->formPackages[] = ['package_id' => '', 'free_months' => '1'];
    }

    public function removePackageRow(int $index): void
    {
        unset($this->formPackages[$index]);
        $this->formPackages = array_values($this->formPackages);
    }

    /* ---------------------------------------------------------------- */
    /*  Durations editor (مدت‌های اعتبار چندگزینه‌ای)                      */
    /* ---------------------------------------------------------------- */

    public function addDurationRow(): void
    {
        $this->formDurations[] = $this->emptyDurationRow();
    }

    public function removeDurationRow(int $index): void
    {
        unset($this->formDurations[$index]);
        $this->formDurations = array_values($this->formDurations);
    }

    /** انتقال یک ردیف مدت به بالا (ترتیب نمایش در فروشگاه) */
    public function moveDurationUp(int $index): void
    {
        if ($index <= 0 || $index >= count($this->formDurations)) {
            return;
        }

        [$this->formDurations[$index - 1], $this->formDurations[$index]] = [$this->formDurations[$index], $this->formDurations[$index - 1]];
        $this->formDurations = array_values($this->formDurations);
    }

    /** انتقال یک ردیف مدت به پایین (ترتیب نمایش در فروشگاه) */
    public function moveDurationDown(int $index): void
    {
        if ($index < 0 || $index >= count($this->formDurations) - 1) {
            return;
        }

        [$this->formDurations[$index + 1], $this->formDurations[$index]] = [$this->formDurations[$index], $this->formDurations[$index + 1]];
        $this->formDurations = array_values($this->formDurations);
    }

    /* ---------------------------------------------------------------- */
    /*  CRUD                                                             */
    /* ---------------------------------------------------------------- */

    public function openCreate(): void
    {
        $this->reset(['editingId', 'deleteId']);
        $this->form = [
            'name'        => '',
            'slug'        => '',
            'description' => '',
            'is_one_time' => false,
            'is_active'   => true,
            'sort_order'  => '0',
        ];
        $this->formFeatures = [''];
        $this->formPackages = [];
        // حداقل یک مدت الزامی است — یک ردیف خالی از ابتدا آماده است
        $this->formDurations = [$this->emptyDurationRow()];
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $plan = SubscriptionPlan::with(['packages', 'durations'])->findOrFail($id);

        $this->reset(['deleteId']);
        $this->editingId = $plan->id;
        $this->form = [
            'name'        => $plan->name,
            'slug'        => $plan->slug,
            'description' => (string) $plan->description,
            'is_one_time' => (bool) $plan->is_one_time,
            'is_active'   => (bool) $plan->is_active,
            'sort_order'  => (string) $plan->sort_order,
        ];
        $this->formFeatures = array_values($plan->features ?: ['']);
        $this->formPackages = $plan->packages
            ->map(fn ($p) => ['package_id' => (string) $p->id, 'free_months' => (string) $p->pivot->free_months])
            ->values()
            ->all();
        $this->formDurations = $plan->durations
            ->map(fn (SubscriptionPlanDuration $d) => [
                'duration_months' => (string) $d->duration_months,
                'price'           => (string) $d->price,
                'discount_price'  => $d->discount_price === null ? '' : (string) $d->discount_price,
                'is_active'       => (bool) $d->is_active,
            ])
            ->values()
            ->all();

        // طرح‌های قدیمی بدون مدت ثبت‌شده: فیلدهای تک‌مدتِ خود طرح به‌صورت یک ردیف آماده وارد فرم می‌شوند
        if (count($this->formDurations) === 0) {
            $this->formDurations = [[
                'duration_months' => (string) $plan->duration_months,
                'price'           => (string) $plan->price,
                'discount_price'  => $plan->discount_price !== null ? (string) $plan->discount_price : '',
                'is_active'       => true,
            ]];
        }

        $this->showModal = true;
    }

    public function save(): void
    {
        $planId = $this->editingId;

        // ردیف‌های کاملاً خالی حذف می‌شوند تا خطای «الزامی» برای ردیف‌های نیمه‌کاره نیاید
        $this->formDurations = collect($this->formDurations)
            ->filter(fn ($row) => trim((string) ($row['duration_months'] ?? '')) !== ''
                || trim((string) ($row['price'] ?? '')) !== ''
                || trim((string) ($row['discount_price'] ?? '')) !== '')
            ->values()
            ->all();

        $this->validate([
            'form.name'            => ['required', 'string', 'max:100'],
            'form.slug'            => ['nullable', 'string', 'max:120', 'alpha_dash', \Illuminate\Validation\Rule::unique('subscription_plans', 'slug')->ignore($planId)],
            'form.description'     => ['nullable', 'string', 'max:2000'],
            'form.sort_order'      => ['nullable', 'integer', 'min:0', 'max:999'],
            'formPackages.*.package_id' => ['nullable', 'integer', 'exists:packages,id'],
            'formPackages.*.free_months' => ['required_with:formPackages.*.package_id', 'integer', 'min:0', 'max:120'],

            // مدت‌های اعتبار: حداقل یک ردیف الزامی است (مدت/قیمت/تخفیف پیش‌فرض جداگانه وجود ندارد)
            'formDurations'                       => ['required', 'array', 'min:1', 'max:12'],
            'formDurations.*.duration_months'     => ['required', 'integer', 'min:0', 'max:120', 'distinct'],
            'formDurations.*.price'               => ['required', 'integer', 'min:0'],
            'formDurations.*.discount_price'      => ['nullable', 'integer', 'min:0', 'lt:formDurations.*.price'],
            'formDurations.*.is_active'           => ['boolean'],
        ], [
            'form.name.required'          => 'نام طرح الزامی است.',
            'form.name.max'               => 'نام طرح حداکثر ۱۰۰ کاراکتر است.',
            'form.slug.alpha_dash'        => 'شناسه فقط می‌تواند شامل حروف، عدد، خط تیره و زیرخط باشد.',
            'form.slug.unique'            => 'این شناسه قبلاً استفاده شده است.',
            'formPackages.*.free_months.required_with' => 'مدت دسترسی رایگان هر پکیج را مشخص کنید.',
            'formPackages.*.free_months.integer'       => 'مدت دسترسی رایگان باید عدد صحیح باشد.',
            'formPackages.*.free_months.max'           => 'مدت دسترسی رایگان حداکثر ۱۲۰ ماه است.',

            'formDurations.required'                 => 'حداقل یک مدت اعتبار برای طرح الزامی است (مثلاً ۱ ماهه).',
            'formDurations.min'                      => 'حداقل یک مدت اعتبار برای طرح الزامی است (مثلاً ۱ ماهه).',
            'formDurations.array'                    => 'ساختار مدت‌های اعتبار نامعتبر است.',
            'formDurations.max'                      => 'حداکثر ۱۲ مدت اعتبار برای هر طرح می‌توانید ثبت کنید.',
            'formDurations.*.duration_months.required' => 'مدت هر ردیف الزامی است (۰ = نامحدود).',
            'formDurations.*.duration_months.integer'  => 'مدت باید عدد صحیح (ماه) باشد.',
            'formDurations.*.duration_months.min'      => 'مدت حداقل ۰ است (۰ = نامحدود).',
            'formDurations.*.duration_months.max'      => 'مدت حداکثر ۱۲۰ ماه است.',
            'formDurations.*.duration_months.distinct' => 'این مدت قبلاً در این طرح ثبت شده است؛ هر مدت فقط یک‌بار.',
            'formDurations.*.price.required'           => 'قیمت هر مدت الزامی است (۰ = رایگان).',
            'formDurations.*.price.integer'            => 'قیمت باید عدد صحیح باشد.',
            'formDurations.*.price.min'                => 'قیمت نمی‌تواند منفی باشد.',
            'formDurations.*.discount_price.integer'   => 'تخفیف باید عدد صحیح باشد.',
            'formDurations.*.discount_price.min'       => 'تخفیف نمی‌تواند منفی باشد.',
            'formDurations.*.discount_price.lt'        => 'تخفیف هر مدت باید کمتر از قیمت همان مدت باشد.',
        ]);

        $features = array_values(array_filter(
            array_map('trim', $this->formFeatures),
            fn ($f) => $f !== ''
        ));

        // همگام‌سازی مدت‌های اعتبار (چندگزینه‌ای) — بازسازی کامل از ردیف‌های فرم؛
        // ترتیبِ ردیف‌ها در فرم = ترتیب نمایش گزینه‌ها (sort_order از جایگاه ردیف)
        $durationRows = collect($this->formDurations)
            ->map(fn ($row, $i) => [
                'duration_months' => (int) $row['duration_months'],
                'price'           => (int) ($row['price'] ?: 0),
                'discount_price'  => blank($row['discount_price'] ?? null) ? null : (int) $row['discount_price'],
                'is_active'       => (bool) ($row['is_active'] ?? true),
                'sort_order'      => $i + 1,
            ])
            ->values()
            ->all();

        // فیلدهای تک‌مدتِ طرح (برای سازگاری با فال‌بک‌های قدیمی، مرتب‌سازی بر اساس قیمت و آمار «رایگان»)
        // از «اولین ردیف مدت» مشتق می‌شوند تا همیشه هم‌راستا با گزینهٔ اول فروشگاه باشند.
        $firstRow = $durationRows[0] ?? ['duration_months' => 1, 'price' => 0, 'discount_price' => null];

        $data = [
            'name'            => trim($this->form['name']),
            'slug'            => $this->form['slug'] !== '' ? trim($this->form['slug']) : SubscriptionPlan::generateSlug($this->form['name']),
            'description'     => $this->form['description'] !== '' ? $this->form['description'] : null,
            'duration_months' => (int) $firstRow['duration_months'],
            'price'           => (int) $firstRow['price'],
            'discount_price'  => $firstRow['discount_price'],
            'is_one_time'     => (bool) $this->form['is_one_time'],
            'is_active'       => (bool) $this->form['is_active'],
            'sort_order'      => (int) ($this->form['sort_order'] ?: 0),
            'features'        => $features ?: null,
        ];

        $plan = $this->editingId
            ? SubscriptionPlan::findOrFail($this->editingId)->fill($data)
            : new SubscriptionPlan($data);

        $plan->save();

        // همگام‌سازی پکیج‌های همراه (unique plan+package)
        $sync = [];
        foreach ($this->formPackages as $row) {
            if (!empty($row['package_id'])) {
                $sync[(int) $row['package_id']] = ['free_months' => (int) ($row['free_months'] ?: 1)];
            }
        }
        $plan->packages()->sync($sync);

        $plan->durations()->delete();
        foreach ($durationRows as $row) {
            $plan->durations()->create($row);
        }

        $this->showModal = false;
        $this->toast($this->editingId ? 'طرح اشتراک ویرایش شد.' : 'طرح اشتراک جدید ایجاد شد.');
        $this->editingId = null;
    }

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

    public function render()
    {
        return view('livewire.plans.index');
    }
}
