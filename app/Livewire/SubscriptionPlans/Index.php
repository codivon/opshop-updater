<?php

namespace App\Livewire\SubscriptionPlans;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Livewire\Concerns\WithToasts;
use App\Models\Package;
use App\Models\SubscriptionPlan;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('پلن‌ها و طرح‌های اشتراک')]
class Index extends Component
{
    use AuthorizesAdmin;
    use WithPagination, WithToasts;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $sort = 'order';

    /** @var array<string, mixed> */
    public array $form = [];

    /** @var array<int, string> قابلیت‌های طرح (repeater) */
    public array $formFeatures = [];

    /** @var array<int, array{package_id: int|string, duration_months: string|null}> پکیج‌های همراه طرح */
    public array $formPackages = [];

    /** پکیج انتخاب‌شده در دراپ‌داون «افزودن پکیج» */
    public string $packageToAdd = '';

    public bool $showModal = false;

    public ?int $editingId = null;

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

    public function resetFilters(): void
    {
        $this->reset(['search', 'status', 'sort']);
        $this->resetPage();
    }

    #[Computed]
    public function records()
    {
        return SubscriptionPlan::query()
            ->withCount('packages')
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('description', 'like', "%{$this->search}%")))
            ->when($this->status !== '', fn ($q) => $q->where('is_active', $this->status === 'active'))
            ->when($this->sort === 'order', fn ($q) => $q->orderBy('sort_order')->orderBy('id'))
            ->when($this->sort === 'newest', fn ($q) => $q->latest('id'))
            ->when($this->sort === 'oldest', fn ($q) => $q->oldest('id'))
            ->when($this->sort === 'price_desc', fn ($q) => $q->orderByDesc('price')->orderByDesc('id'))
            ->when($this->sort === 'price_asc', fn ($q) => $q->orderBy('price')->orderBy('id'))
            ->paginate(12);
    }

    /** پکیج‌های فعال برای انتخاب در مودال */
    #[Computed]
    public function packages()
    {
        return Package::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }

    /** نام همه پکیج‌ها (فعال و غیرفعال) برای نمایش ردیف‌های ویرایش */
    #[Computed]
    public function packageNames()
    {
        return Package::query()->pluck('name', 'id');
    }

    /* ---------------------------------------------------------------- */
    /*  CRUD */
    /* ---------------------------------------------------------------- */

    public function openCreate(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->form = [
            'name' => '',
            'description' => '',
            'duration_months' => '1',
            'price' => '0',
            'discount_price' => '',
            'is_free' => false,
            'is_one_time' => false,
            'is_active' => true,
            'sort_order' => '0',
        ];
        $this->formFeatures = [''];
        $this->formPackages = [];
        $this->packageToAdd = '';
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $plan = SubscriptionPlan::with('packages')->findOrFail($id);
        $this->resetValidation();
        $this->editingId = $plan->id;
        $this->form = [
            'name' => (string) $plan->name,
            'description' => (string) ($plan->description ?? ''),
            'duration_months' => (string) $plan->duration_months,
            'price' => (string) $plan->price,
            'discount_price' => $plan->discount_price === null ? '' : (string) $plan->discount_price,
            'is_free' => (bool) $plan->is_free,
            'is_one_time' => (bool) $plan->is_one_time,
            'is_active' => (bool) $plan->is_active,
            'sort_order' => (string) $plan->sort_order,
        ];
        $this->formFeatures = collect($plan->features ?? [])->map(fn ($f) => (string) $f)->values()->all();
        if ($this->formFeatures === []) {
            $this->formFeatures = [''];
        }
        $this->formPackages = $plan->packages
            ->map(fn ($p) => [
                'package_id' => (string) $p->id,
                'duration_months' => $p->pivot->duration_months === null ? '' : (string) $p->pivot->duration_months,
            ])
            ->values()
            ->all();
        $this->packageToAdd = '';
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate();

        $data = $validated['form'];
        $data['duration_months'] = (int) $data['duration_months'];
        $data['price'] = (int) $data['price'];
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['discount_price'] = blank($data['discount_price'] ?? null) ? null : (int) $data['discount_price'];
        $data['description'] = blank($data['description'] ?? null) ? null : $data['description'];

        // طرح رایگان → قیمت صفر و بدون تخفیف
        if (! empty($data['is_free'])) {
            $data['price'] = 0;
            $data['discount_price'] = null;
        }

        // قابلیت‌ها: حذف ردیف‌های خالی و ایندکس مجدد
        $features = collect($this->formFeatures)
            ->map(fn ($f) => trim((string) $f))
            ->filter(fn ($f) => $f !== '')
            ->values()
            ->all();

        if ($this->editingId) {
            $plan = SubscriptionPlan::findOrFail($this->editingId);
            $plan->update($data + ['features' => $features]);
            $this->toast('طرح اشتراک با موفقیت به‌روزرسانی شد.');
        } else {
            $plan = SubscriptionPlan::create($data + ['features' => $features]);
            $this->toast('طرح اشتراک با موفقیت ثبت شد.');
        }

        // همگام‌سازی پکیج‌های همراه طرح (pivot: مدت اختصاصی یا null = مدت پیش‌فرض طرح)
        $mapping = collect($this->formPackages)
            ->mapWithKeys(fn ($row) => [
                (int) $row['package_id'] => [
                    'duration_months' => blank($row['duration_months'] ?? null) ? null : (int) $row['duration_months'],
                ],
            ])
            ->all();
        $plan->packages()->sync($mapping);

        $this->showModal = false;
    }

    public function delete(): void
    {
        $plan = SubscriptionPlan::findOrFail($this->deleteId ?? 0);

        // حذف کاسکادی پکیج‌های همراه و درخواست‌ها از طریق FK
        $plan->delete();

        $this->deleteId = null;
        $this->toast('طرح اشتراک حذف شد.');
    }

    /* ---------------------------------------------------------------- */
    /*  Repeater: قابلیت‌ها */
    /* ---------------------------------------------------------------- */

    public function addFeature(): void
    {
        $this->formFeatures[] = '';
    }

    public function removeFeature(int $i): void
    {
        unset($this->formFeatures[$i]);
        $this->formFeatures = array_values($this->formFeatures);
    }

    /* ---------------------------------------------------------------- */
    /*  Packages editor */
    /* ---------------------------------------------------------------- */

    public function addPackage(): void
    {
        $id = (int) $this->packageToAdd;
        if ($id > 0 && ! collect($this->formPackages)->contains(fn ($row) => (int) ($row['package_id'] ?? 0) === $id)) {
            $this->formPackages[] = ['package_id' => (string) $id, 'duration_months' => ''];
        }
        $this->packageToAdd = '';
    }

    public function removePackage(int $i): void
    {
        unset($this->formPackages[$i]);
        $this->formPackages = array_values($this->formPackages);
    }

    /** @return array<string, array<int, string>|string> */
    protected function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:100'],
            'form.description' => ['nullable', 'string', 'max:1000'],
            'form.duration_months' => ['required', 'integer', 'min:0', 'max:120'],
            'form.price' => ['required', 'integer', 'min:0'],
            'form.discount_price' => ['nullable', 'integer', 'min:0', 'lt:form.price'],
            'form.is_free' => ['boolean'],
            'form.is_one_time' => ['boolean'],
            'form.is_active' => ['boolean'],
            'form.sort_order' => ['nullable', 'integer', 'min:0'],

            'formFeatures' => ['array'],
            'formFeatures.*' => ['nullable', 'string', 'max:150'],

            'formPackages' => ['array'],
            'formPackages.*.package_id' => ['required', 'integer', 'distinct', 'exists:packages,id'],
            'formPackages.*.duration_months' => ['nullable', 'integer', 'min:0', 'max:120'],
        ];
    }

    protected function messages(): array
    {
        return [
            'form.name.required' => 'نام طرح الزامی است.',
            'form.name.string' => 'نام طرح باید متن باشد.',
            'form.name.max' => 'نام طرح حداکثر ۱۰۰ کاراکتر باشد.',
            'form.description.string' => 'توضیحات باید متن باشد.',
            'form.description.max' => 'توضیحات حداکثر ۱۰۰۰ کاراکتر باشد.',
            'form.duration_months.required' => 'مدت اعتبار طرح را انتخاب کنید.',
            'form.duration_months.integer' => 'مدت اعتبار باید عدد صحیح باشد.',
            'form.duration_months.min' => 'مدت اعتبار حداقل ۰ است (۰ = نامحدود).',
            'form.duration_months.max' => 'مدت اعتبار حداکثر ۱۲۰ ماه است.',
            'form.price.required' => 'قیمت طرح الزامی است.',
            'form.price.integer' => 'قیمت باید عدد صحیح (تومان) باشد.',
            'form.price.min' => 'قیمت نمی‌تواند منفی باشد.',
            'form.discount_price.integer' => 'قیمت با تخفیف باید عدد صحیح باشد.',
            'form.discount_price.min' => 'قیمت با تخفیف نمی‌تواند منفی باشد.',
            'form.discount_price.lt' => 'قیمت با تخفیف باید کمتر از قیمت اصلی باشد.',
            'form.is_free.boolean' => 'مقدار گزینه «رایگان» نامعتبر است.',
            'form.is_one_time.boolean' => 'مقدار گزینه «یک‌بار مصرف» نامعتبر است.',
            'form.is_active.boolean' => 'مقدار گزینه «فعال» نامعتبر است.',
            'form.sort_order.integer' => 'ترتیب نمایش باید عدد صحیح باشد.',
            'form.sort_order.min' => 'ترتیب نمایش نمی‌تواند منفی باشد.',

            'formFeatures.array' => 'ساختار قابلیت‌ها نامعتبر است.',
            'formFeatures.*.string' => 'هر قابلیت باید متن باشد.',
            'formFeatures.*.max' => 'هر قابلیت حداکثر ۱۵۰ کاراکتر باشد.',

            'formPackages.array' => 'ساختار پکیج‌های طرح نامعتبر است.',
            'formPackages.*.package_id.required' => 'پکیج هر ردیف را انتخاب کنید.',
            'formPackages.*.package_id.integer' => 'پکیج انتخاب‌شده معتبر نیست.',
            'formPackages.*.package_id.distinct' => 'این پکیج قبلاً به طرح اضافه شده است.',
            'formPackages.*.package_id.exists' => 'پکیج انتخاب‌شده معتبر نیست.',
            'formPackages.*.duration_months.integer' => 'مدت پکیج باید عدد صحیح (ماه) باشد.',
            'formPackages.*.duration_months.min' => 'مدت پکیج حداقل ۰ است (۰ = نامحدود).',
            'formPackages.*.duration_months.max' => 'مدت پکیج حداکثر ۱۲۰ ماه است.',
        ];
    }

    public function render()
    {
        return view('livewire.subscription-plans.index');
    }
}
