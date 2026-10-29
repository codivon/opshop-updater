<?php

namespace App\Livewire\Plans;

use App\Livewire\Concerns\WithToasts;
use App\Models\Package;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanDuration;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * فرم افزودن/ویرایش طرح اشتراک — کامپوننت مجزا از لیست (Plans\Index).
 *
 * چرا جداست؟ (کندی گزارش‌شدهٔ کاربر — Task 64)
 * وقتی فرم داخل همان کامپوننت لیست بود، هر کلیک داخل مودال (افزودن/حذف ردیف
 * قابلیت یا پکیج، جابه‌جایی مدت‌ها و…) یک درخواست کامل می‌ساخت که علاوه بر
 * خودِ مودال، جدول + آمار + فیلترهای صفحه را هم دوباره کوئری و رندر و morph
 * می‌کرد (ده‌ها کیلوبایت HTML در هر تعامل). الان هر تعامل فقط همین مودال
 * کوچک را رندر می‌کند و لیست دست‌نخورده می‌ماند؛ بعد از ذخیره هم با رویداد
 * «plan-saved» فقط یک‌بار لیست تازه‌سازی می‌شود.
 *
 * باز شدن مودال هم بدون رفت‌وبرگشتِ لیست است: دکمه‌های جدول با
 * $dispatch('plan-form-open') مستقیماً همین کامپوننت را صدا می‌زنند.
 */
class Form extends Component
{
    use WithToasts;

    public bool $showModal = false;
    public ?int $editingId = null;

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

    /** مدت‌های اعتبار (حداقل یک ردیف الزامی): [['duration_months' => '1', 'price' => '0', 'discount_price' => '', 'is_active' => true, 'features' => [''], 'packages' => [], 'packageToAdd' => ''], …] */
    public array $formDurations = [];

    /** کدام بخش «قابلیت‌ها و پکیج‌های این مدت» باز است — با state کامپوننت تا بعد از افزودن/حذف ردیف باز بماند (morph جدول <details> را می‌بندد) */
    public array $openDurationConfigs = [];

    /** یک ردیف خالی برای ریپیتر مدت‌های اعتبار — هر مدت می‌تواند قابلیت‌ها/پکیج‌های اختصاصی خودش را داشته باشد (Task 63) */
    protected function emptyDurationRow(): array
    {
        return [
            'duration_months' => '',
            'price'           => '',
            'discount_price'  => '',
            'is_active'       => true,
            // پیکربندی اختصاصی این مدت — خالی = ارث‌بری از طرح (Task 63)
            'features'        => [''], // هر ردیف یک قابلیت — افزودن/حذف مثل قابلیت‌های طرح
            'packages'        => [], // [['package_id' => '', 'free_months' => '1'], …]
            'packageToAdd'    => '',
        ];
    }

    #[Computed]
    public function packages()
    {
        return Package::query()->orderBy('name')->get(['id', 'name', 'slug']);
    }

    /* ---------------------------------------------------------------- */
    /*  باز کردن مودال (ایجاد/ویرایش) — مستقیماً از دکمه‌های جدول          */
    /* ---------------------------------------------------------------- */

    /** شنوندهٔ رویداد دکمه‌های جدول: $dispatch('plan-form-open', {id}) — بدون id = ایجاد */
    #[On('plan-form-open')]
    public function open(?int $id = null): void
    {
        if ($id && $id > 0) {
            $this->openEdit($id);
        } else {
            $this->openCreate();
        }
    }

    public function openCreate(): void
    {
        $this->reset(['editingId']);
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
        $this->openDurationConfigs = [];
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        /** @var SubscriptionPlan|null $plan */
        $plan = SubscriptionPlan::with(['packages', 'durations', 'durations.packages'])->find($id);

        if (!$plan) {
            $this->toast('طرح موردنظر یافت نشد؛ ممکن است حذف شده باشد.', 'warning');

            return;
        }

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
                // پیکربندی اختصاصی مدت — Task 63 (هر ردیف یک قابلیت)
                'features'        => array_values($d->features ?: ['']),
                'packages'        => $d->packages
                    ->map(fn ($p) => ['package_id' => (string) $p->id, 'free_months' => (string) $p->pivot->free_months])
                    ->values()
                    ->all(),
                'packageToAdd'    => '',
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
                'features'        => [''],
                'packages'        => [],
                'packageToAdd'    => '',
            ]];
        }

        $this->openDurationConfigs = []; // همهٔ بخش‌های پیکربندی از حالت بسته شروع شوند
        $this->showModal = true;
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

    /** افزودن ردیف قابلیت به پیکربندی اختصاصی یک مدت اعتبار (مثل قابلیت‌های طرح) */
    public function addDurationFeature(int $durationIndex): void
    {
        if (!isset($this->formDurations[$durationIndex])) {
            return;
        }

        $this->formDurations[$durationIndex]['features'][] = '';
    }

    /** حذف یک ردیف قابلیت از پیکربندی اختصاصی یک مدت اعتبار */
    public function removeDurationFeature(int $durationIndex, int $featureIndex): void
    {
        if (!isset($this->formDurations[$durationIndex]['features'][$featureIndex])) {
            return;
        }

        unset($this->formDurations[$durationIndex]['features'][$featureIndex]);
        $this->formDurations[$durationIndex]['features'] = array_values($this->formDurations[$durationIndex]['features']);
    }

    /** افزودن پکیج به یک مدت اعتبار (پیکربندی اختصاصی آن مدت — Task 63) */
    public function addDurationPackage(int $durationIndex): void
    {
        if (!isset($this->formDurations[$durationIndex])) {
            return;
        }

        $id = (int) ($this->formDurations[$durationIndex]['packageToAdd'] ?? 0);

        if ($id > 0 && !collect($this->formDurations[$durationIndex]['packages'] ?? [])
            ->contains(fn ($row) => (int) ($row['package_id'] ?? 0) === $id)) {
            $this->formDurations[$durationIndex]['packages'][] = ['package_id' => (string) $id, 'free_months' => '1'];
        }

        $this->formDurations[$durationIndex]['packageToAdd'] = '';
    }

    /** حذف یک پکیج از پیکربندی یک مدت اعتبار */
    public function removeDurationPackage(int $durationIndex, int $packageIndex): void
    {
        if (!isset($this->formDurations[$durationIndex]['packages'][$packageIndex])) {
            return;
        }

        unset($this->formDurations[$durationIndex]['packages'][$packageIndex]);
        $this->formDurations[$durationIndex]['packages'] = array_values($this->formDurations[$durationIndex]['packages']);
    }

    /* ---------------------------------------------------------------- */
    /*  Durations editor (مدت‌های اعتبار چندگزینه‌ای)                      */
    /* ---------------------------------------------------------------- */

    /** باز/بسته کردن بخش پیکربندی اختصاصی یک مدت (به‌جای <details> که morph آن را می‌بندد) */
    public function toggleDurationConfig(int $index): void
    {
        $this->openDurationConfigs[$index] = ! ($this->openDurationConfigs[$index] ?? false);
    }

    public function addDurationRow(): void
    {
        $this->formDurations[] = $this->emptyDurationRow();
    }

    public function removeDurationRow(int $index): void
    {
        unset($this->formDurations[$index]);
        $this->formDurations = array_values($this->formDurations);

        // همگام‌سازی بازشدگی بخش‌های پیکربندی با جابه‌جایی ایندکس‌ها
        $old = $this->openDurationConfigs;
        $remapped = [];
        foreach ($this->formDurations as $ni => $_) {
            $oldIndex = $ni < $index ? $ni : $ni + 1;
            $remapped[$ni] = (bool) ($old[$oldIndex] ?? false);
        }
        $this->openDurationConfigs = $remapped;
    }

    /** انتقال یک ردیف مدت به بالا (ترتیب نمایش در فروشگاه) */
    public function moveDurationUp(int $index): void
    {
        if ($index <= 0 || $index >= count($this->formDurations)) {
            return;
        }

        [$this->formDurations[$index - 1], $this->formDurations[$index]] = [$this->formDurations[$index], $this->formDurations[$index - 1]];
        $this->formDurations = array_values($this->formDurations);

        [$this->openDurationConfigs[$index - 1], $this->openDurationConfigs[$index]] = [$this->openDurationConfigs[$index] ?? false, $this->openDurationConfigs[$index - 1] ?? false];
    }

    /** انتقال یک ردیف مدت به پایین (ترتیب نمایش در فروشگاه) */
    public function moveDurationDown(int $index): void
    {
        if ($index < 0 || $index >= count($this->formDurations) - 1) {
            return;
        }

        [$this->formDurations[$index + 1], $this->formDurations[$index]] = [$this->formDurations[$index], $this->formDurations[$index + 1]];
        $this->formDurations = array_values($this->formDurations);

        // جابه‌جایی دو ردیف → جابه‌جایی وضعیت بازشدگی آن‌ها (ایندکس‌های درست)
        [$this->openDurationConfigs[$index + 1], $this->openDurationConfigs[$index]] = [$this->openDurationConfigs[$index] ?? false, $this->openDurationConfigs[$index + 1] ?? false];
    }

    /* ---------------------------------------------------------------- */
    /*  ذخیره                                                            */
    /* ---------------------------------------------------------------- */

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

            // پیکربندی اختصاصی هر مدت (Task 63): قابلیت‌ها + پکیج‌ها با مدت دسترسی رایگان
            'formDurations.*.features'                      => ['array'],
            'formDurations.*.features.*'                    => ['nullable', 'string', 'max:500'],
            'formDurations.*.packages'                      => ['array'],
            'formDurations.*.packages.*.package_id'         => ['nullable', 'integer', 'exists:packages,id'],
            'formDurations.*.packages.*.free_months'        => ['required_with:formDurations.*.packages.*.package_id', 'integer', 'min:0', 'max:120'],
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

            // پیکربندی اختصاصی هر مدت — Task 63
            'formDurations.*.features.*.max'                       => 'هر قابلیت حداکثر ۵۰۰ کاراکتر است.',
            'formDurations.*.packages.array'                       => 'ساختار پکیج‌های مدت نامعتبر است.',
            'formDurations.*.packages.*.package_id.exists'         => 'پکیج انتخابی این مدت معتبر نیست.',
            'formDurations.*.packages.*.free_months.required_with' => 'مدت دسترسی رایگان پکیج‌های این مدت را مشخص کنید.',
            'formDurations.*.packages.*.free_months.integer'       => 'مدت دسترسی رایگان باید عدد صحیح باشد.',
            'formDurations.*.packages.*.free_months.min'           => 'مدت دسترسی رایگان حداقل ۰ است (۰ = نامحدود).',
            'formDurations.*.packages.*.free_months.max'           => 'مدت دسترسی رایگان حداکثر ۱۲۰ ماه است.',
        ]);

        $features = array_values(array_filter(
            array_map('trim', $this->formFeatures),
            fn ($f) => $f !== ''
        ));

        // همگام‌سازی مدت‌های اعتبار (چندگزینه‌ای) — بازسازی کامل از ردیف‌های فرم؛
        // ترتیبِ ردیف‌ها در فرم = ترتیب نمایش گزینه‌ها (sort_order از جایگاه ردیف)
        // هر ردیف می‌تواند قابلیت‌ها (هر ردیف فرم یک قابلیت) و پکیج‌های اختصاصی خودش را داشته باشد — Task 63
        $durationRows = collect($this->formDurations)
            ->map(function ($row, $i) {
                // قابلیت‌های اختصاصی مدت: هر ردیف فرم یک قابلیت — خالی = ارث‌بری از طرح
                $features = collect($row['features'] ?? [])
                    ->map(fn ($f) => trim((string) $f))
                    ->filter(fn ($f) => $f !== '')
                    ->values()
                    ->all();

                // پکیج‌های اختصاصی مدت (بدون تکرار) — خالی = ارث‌بری از پکیج‌های طرح
                $packages = collect($row['packages'] ?? [])
                    ->filter(fn ($p) => !empty($p['package_id']))
                    ->keyBy(fn ($p) => (int) $p['package_id'])
                    ->map(fn ($p) => ['free_months' => (int) ($p['free_months'] ?: 1)])
                    ->all();

                return [
                    'duration_months' => (int) $row['duration_months'],
                    'price'           => (int) ($row['price'] ?: 0),
                    'discount_price'  => blank($row['discount_price'] ?? null) ? null : (int) $row['discount_price'],
                    'is_active'       => (bool) ($row['is_active'] ?? true),
                    'sort_order'      => $i + 1,
                    'features'        => $features ?: null,
                    '__packages'      => $packages, // جدا از ستون‌های مدل — بعد از create سینک می‌شود
                ];
            })
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
            $packages = $row['__packages'];
            unset($row['__packages']);

            $duration = $plan->durations()->create($row);

            // سینک پکیج‌های اختصاصی این مدت (pivot جدید — خالی = ارث‌بری از طرح)
            if ($packages !== []) {
                $duration->packages()->sync($packages);
            }
        }

        $wasEditing = (bool) $this->editingId;
        $this->showModal = false;
        $this->toast($wasEditing ? 'طرح اشتراک ویرایش شد.' : 'طرح اشتراک جدید ایجاد شد.');
        $this->editingId = null;

        // لیست (کامپوننت والد) فقط همین‌جا تازه‌سازی می‌شود
        $this->dispatch('plan-saved');
    }

    public function render()
    {
        return view('livewire.plans.form');
    }
}
