<?php

namespace App\Livewire\Discounts;

use App\Livewire\Concerns\WithToasts;
use App\Models\DiscountCode;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('کدهای تخفیف')]
class Index extends Component
{
    use WithToasts, WithPagination;

    public string $search = '';

    /** '' | active | inactive | expired | full */
    public string $statusFilter = '';

    /** فرم مودال ایجاد/ویرایش */
    public bool $showModal = false;
    public ?int $editingId = null;
    public array $form = [
        'code'                   => '',
        'type'                   => DiscountCode::TYPE_PERCENT,
        'value'                  => 10,
        'max_uses'               => '',
        'expires_at'             => '',
        'applies_packages'       => true,
        'applies_products'       => true,
        'applies_subscriptions'  => true,
        'is_active'              => true,
        'notes'                  => '',
    ];

    protected function rules(): array
    {
        $isPercent = $this->form['type'] === DiscountCode::TYPE_PERCENT;

        return [
            'form.code' => [
                'required', 'string', 'max:64', 'alpha_dash:ascii',
                Rule::unique('discount_codes', 'code')->ignore($this->editingId),
            ],
            'form.type'       => ['required', 'in:' . DiscountCode::TYPE_PERCENT . ',' . DiscountCode::TYPE_FIXED],
            'form.value'      => ['required', 'integer', $isPercent ? 'min:1' : 'min:1000', $isPercent ? 'max:100' : 'max:1000000000'],
            'form.max_uses'   => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'form.expires_at' => ['nullable', 'date'],
            'form.applies_packages'      => ['boolean'],
            'form.applies_products'      => ['boolean'],
            'form.applies_subscriptions' => ['boolean'],
            'form.is_active' => ['boolean'],
            'form.notes'     => ['nullable', 'string', 'max:500'],
        ];
    }

    protected $messages = [
        'form.code.required'   => 'کد تخفیف الزامی است.',
        'form.code.unique'     => 'این کد قبلاً استفاده شده است؛ کد دیگری وارد کنید.',
        'form.code.alpha_dash' => 'کد فقط می‌تواند شامل حروف انگلیسی، عدد، خط تیره و زیرخط باشد.',
        'form.value.required'  => 'مقدار تخفیف الزامی است.',
        'form.value.min'       => 'مقدار تخفیف نامعتبر است (درصد: حداقل ۱ — مبلغ ثابت: حداقل ۱٬۰۰۰ تومان).',
        'form.value.max'       => 'مقدار تخفیف بیش از حد مجاز است.',
        'form.max_uses.min'    => 'حداقل تعداد استفاده باید ۱ باشد.',
        'form.expires_at.date' => 'تاریخ انقضا نامعتبر است.',
    ];

    public function updatingSearch(): void
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
        $this->form = [
            'code'                   => '',
            'type'                   => DiscountCode::TYPE_PERCENT,
            'value'                  => 10,
            'max_uses'               => '',
            'expires_at'             => '',
            'applies_packages'       => true,
            'applies_products'       => true,
            'applies_subscriptions'  => true,
            'is_active'              => true,
            'notes'                  => '',
        ];
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->resetErrorBag();
        $code = DiscountCode::findOrFail($id);

        $this->editingId = $code->id;
        $this->form = [
            'code'                   => $code->code,
            'type'                   => $code->type,
            'value'                  => $code->value,
            'max_uses'               => $code->max_uses !== null ? (string) $code->max_uses : '',
            'expires_at'             => $code->expires_at?->format('Y-m-d\TH:i') ?? '',
            'applies_packages'       => $code->applies_packages,
            'applies_products'       => $code->applies_products,
            'applies_subscriptions'  => $code->applies_subscriptions,
            'is_active'              => $code->is_active,
            'notes'                  => (string) ($code->notes ?? ''),
        ];
        $this->showModal = true;
    }

    /** تولید خودکار کد یکتا (بدون حروف مبهم) */
    public function generateCode(): void
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // بدون I,O,0,1
        $code     = '';

        do {
            $code = substr(str_shuffle(str_repeat($alphabet, 3)), 0, 8);
        } while (DiscountCode::where('code', $code)->exists());

        $this->form['code'] = $code;
    }

    public function save(): void
    {
        $data = $this->validate();

        // حداقل یک محدوده باید انتخاب شده باشد
        if (!$data['form']['applies_packages'] && !$data['form']['applies_products'] && !$data['form']['applies_subscriptions']) {
            $this->addError('form.applies_packages', 'حداقل یکی از محدوده‌های اعتبار (پکیج/محصول/اشتراک) باید فعال باشد.');
            return;
        }

        $payload = [
            'code'                   => DiscountCode::normalize($data['form']['code']),
            'type'                   => $data['form']['type'],
            'value'                  => (int) $data['form']['value'],
            'max_uses'               => $data['form']['max_uses'] !== '' && $data['form']['max_uses'] !== null
                                        ? (int) $data['form']['max_uses']
                                        : null,
            'expires_at'             => filled($data['form']['expires_at']) ? Carbon::parse($data['form']['expires_at']) : null,
            'applies_packages'       => (bool) $data['form']['applies_packages'],
            'applies_products'       => (bool) $data['form']['applies_products'],
            'applies_subscriptions'  => (bool) $data['form']['applies_subscriptions'],
            'is_active'              => (bool) $data['form']['is_active'],
            'notes'                  => $data['form']['notes'] ?: null,
        ];

        $id = $this->editingId;

        DiscountCode::updateOrCreate(['id' => $id], $payload);

        $this->showModal = false;
        $this->toast($id ? 'کد تخفیف ویرایش شد.' : 'کد تخفیف جدید ایجاد شد.');
    }

    public function toggleActive(int $id): void
    {
        $code = DiscountCode::findOrFail($id);
        $code->update(['is_active' => !$code->is_active]);

        $this->toast($code->is_active ? 'کد تخفیف فعال شد.' : 'کد تخفیف غیرفعال شد.');
    }

    public function delete(int $id): void
    {
        $code = DiscountCode::findOrFail($id);

        // خریدهای ثبت‌شده با این کد فقط ارجاعشان null می‌شود (nullOnDelete) — سابقه حفظ می‌شود
        $code->delete();
        $this->toast('کد تخفیف حذف شد.');
    }

    /* ---------------------------------------------------------------- */
    /*  Computed                                                         */
    /* ---------------------------------------------------------------- */

    public function getStatusesProperty(): array
    {
        return [
            ''         => 'همه وضعیت‌ها',
            'active'   => 'فعال و قابل استفاده',
            'inactive' => 'غیرفعال',
            'expired'  => 'منقضی شده',
            'full'     => 'تکمیل ظرفیت',
        ];
    }

    #[Computed]
    public function records()
    {
        $now = now();

        return DiscountCode::query()
            ->withCount(['packagePurchases', 'productPurchases', 'subscriptionOrders'])
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', '%' . strtoupper(trim($this->search)) . '%')
                ->orWhere('notes', 'like', "%{$this->search}%")))
            ->when($this->statusFilter, function ($q) use ($now) {
                match ($this->statusFilter) {
                    'inactive' => $q->where('is_active', false),
                    'expired'  => $q->whereNotNull('expires_at')->where('expires_at', '<=', $now),
                    'full'     => $q->whereNotNull('max_uses')->whereColumn('used_count', '>=', 'max_uses'),
                    default    => $q->where('is_active', true)
                        ->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', $now))
                        ->where(fn ($w) => $w->whereNull('max_uses')->orWhereColumn('used_count', '<', 'max_uses')),
                };
            })
            ->orderBy('is_active', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(20);
    }

    /** تعداد کل استفاده‌های ثبت‌شده از یک کد (هر سه نوع خرید) */
    public function totalUses($code): int
    {
        return (int) $code->package_purchases_count
            + (int) $code->product_purchases_count
            + (int) $code->subscription_orders_count;
    }

    public function render()
    {
        return view('livewire.discounts.index');
    }
}
