<?php

namespace App\Livewire\Sms;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Livewire\Concerns\WithToasts;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Services\Sms\SmsManager;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('قالب‌های پیامک')]
class Templates extends Component
{
    use AuthorizesAdmin;
    use WithPagination, WithToasts;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $sort = 'newest';

    public bool $showModal = false;

    public bool $testModal = false;

    public ?int $deleteId = null;

    public ?int $editingId = null;

    public string $testMobile = '';

    /** فرم اصلی قالب */
    public array $form = [
        'key' => '',
        'title' => '',
        'body' => '',
        'is_active' => true,
    ];

    /** متغیرها: ['customer_name', 'amount', …] */
    public array $formVariables = [];

    /** کد پترن هر درایور: ['kavenegar' => '…', 'melipayamak' => '…'] */
    public array $formPatterns = [];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function records()
    {
        return SmsTemplate::query()
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$this->search}%")
                ->orWhere('key', 'like', "%{$this->search}%")
                ->orWhere('body', 'like', "%{$this->search}%")))
            ->when($this->status !== '', fn ($q) => $q->where('is_active', $this->status === 'active'))
            ->when($this->sort === 'oldest', fn ($q) => $q->orderBy('id'))
            ->when($this->sort === 'title', fn ($q) => $q->orderBy('title'))
            ->when($this->sort === 'key', fn ($q) => $q->orderBy('key'))
            ->when($this->sort === 'newest', fn ($q) => $q->orderByDesc('id'))
            ->paginate(12);
    }

    /* ---------------------------------------------------------------- */

    public function openCreate(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->form = [
            'key' => '',
            'title' => '',
            'body' => '',
            'is_active' => true,
        ];
        $this->formVariables = [];
        $this->formPatterns = array_fill_keys(array_keys(SmsTemplate::DRIVERS), '');
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $template = SmsTemplate::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $template->id;

        $this->form = [
            'key' => $template->key,
            'title' => $template->title,
            'body' => (string) $template->body,
            'is_active' => (bool) $template->is_active,
        ];
        $this->formVariables = array_values((array) $template->variables);

        $this->formPatterns = [];
        foreach (array_keys(SmsTemplate::DRIVERS) as $driver) {
            $this->formPatterns[$driver] = (string) ($template->pattern_codes[$driver] ?? '');
        }

        $this->showModal = true;
    }

    public function addVariable(): void
    {
        $this->formVariables[] = '';
    }

    public function removeVariable(int $index): void
    {
        unset($this->formVariables[$index]);
        $this->formVariables = array_values($this->formVariables);
    }

    public function save(): void
    {
        $isEdit = $this->editingId !== null;

        $this->validate([
            'form.key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/', Rule::unique('sms_templates', 'key')->ignore($this->editingId)],
            'form.title' => ['required', 'string', 'max:191'],
            'form.body' => ['required', 'string', 'max:2000'],
            'formVariables' => ['nullable', 'array', 'max:20'],
            'formVariables.*' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'formPatterns' => ['nullable', 'array'],
        ], [
            'form.key.required' => 'کلید قالب الزامی است.',
            'form.key.regex' => 'کلید فقط حروف کوچک لاتین، عدد و ٍunderline باشد.',
            'form.key.unique' => 'این کلید قبلاً ثبت شده است.',
            'form.title.required' => 'عنوان قالب الزامی است.',
            'form.body.required' => 'متن قالب الزامی است.',
            'formVariables.*.regex' => 'نام متغیر فقط حروف کوچک لاتین، عدد و underline باشد.',
        ]);

        // کلید در حالت ویرایش قابل تغییر نیست
        if ($isEdit) {
            $template = SmsTemplate::findOrFail($this->editingId);
        } else {
            $template = new SmsTemplate;
            $template->key = $this->form['key'];
        }

        $variables = array_values(array_filter(array_map(fn ($v) => trim((string) $v), $this->formVariables)));

        // pattern codes خالی را ذخیره نکن
        $patterns = [];
        foreach ($this->formPatterns as $driver => $code) {
            if (trim((string) $code) !== '') {
                $patterns[$driver] = trim((string) $code);
            }
        }

        $template->title = $this->form['title'];
        $template->body = $this->form['body'];
        $template->variables = $variables;
        $template->pattern_codes = $patterns;
        $template->is_active = (bool) $this->form['is_active'];
        $template->save();

        $this->showModal = false;
        $this->toast($isEdit ? 'قالب پیامک بروزرسانی شد.' : 'قالب پیامک جدید ساخته شد.');
    }

    public function toggle(int $id): void
    {
        $template = SmsTemplate::findOrFail($id);
        $template->update(['is_active' => ! $template->is_active]);

        $this->toast($template->is_active ? 'قالب فعال شد.' : 'قالب غیرفعال شد.');
    }

    public function askDelete(int $id): void
    {
        $template = SmsTemplate::find($id);

        if ($template && in_array($template->key, SmsTemplate::SYSTEM_KEYS, true)) {
            $this->toast('قالب‌های سیستمی قابل حذف نیستند؛ فقط می‌توانید غیرفعالشان کنید.', 'warning');

            return;
        }

        $this->deleteId = $id;
    }

    public function delete(): void
    {
        if ($this->deleteId) {
            SmsTemplate::whereKey($this->deleteId)
                ->whereNotIn('key', SmsTemplate::SYSTEM_KEYS)
                ->delete();

            $this->toast('قالب حذف شد.');
        }

        $this->deleteId = null;
    }

    /* ---------------- ارسال آزمایشی ---------------- */

    public function openTest(int $id): void
    {
        $template = SmsTemplate::findOrFail($id);
        $this->editingId = $template->id;
        $this->testMobile = (string) setting('sms_admin_mobile', '');
        $this->testModal = true;
    }

    public function sendTest(SmsManager $sms): void
    {
        $this->validate([
            'testMobile' => ['required', 'regex:/^09\d{9}$/'],
        ], [
            'testMobile.required' => 'شماره موبایل را وارد کنید.',
            'testMobile.regex' => 'شماره موبایل معتبر نیست (۰۹xxxxxxxxx).',
        ]);

        $template = SmsTemplate::findOrFail($this->editingId);

        $log = $sms->sendTest($template->key, $this->testMobile);

        if ($log && $log->status === SmsLog::STATUS_SENT) {
            $this->toast('پیامک آزمایشی ارسال شد ✅');
            $this->testModal = false;
        } else {
            $reason = $log?->error
                ?? match (true) {
                    ! filter_var(setting('sms_enabled', '0'), FILTER_VALIDATE_BOOLEAN) => 'سیستم پیامک در تنظیمات غیرفعال است.',
                    default => 'ارسال ناموفق بود؛ پاسخ سرویس را در «لاگ پیامک‌ها» ببینید.',
                };
            $this->toast('ارسال ناموفق: '.$reason, 'error');
        }
    }

    public function render()
    {
        return view('livewire.sms.templates', [
            'drivers' => SmsTemplate::DRIVERS,
            'driverName' => (string) setting('sms_driver', 'kavenegar'),
            'smsEnabled' => (bool) filter_var(setting('sms_enabled', '0'), FILTER_VALIDATE_BOOLEAN),
        ]);
    }
}
