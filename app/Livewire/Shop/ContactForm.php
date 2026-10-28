<?php

namespace App\Livewire\Shop;

use App\Livewire\Concerns\WithToasts;
use App\Models\ContactMessage;
use App\Services\CaptchaService;
use App\Support\Features;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * فرم «تماس با ما» صفحهٔ اصلی فروشگاه (کامپوننت فرزند — Task 56).
 * بازدیدکننده درخواست قالب اختصاصی / پروژه اختصاصی / استارتاپ اختصاصی / مشاوره ثبت می‌کند.
 *
 * لایه‌های ضداسپم (هرکدام از پنل «امنیت و امکانات» قابل تنظیم):
 * کپچای خودمیزبان (نقطهٔ contact) + هانی‌پات + سقف ارسال برای هر IP.
 *
 * استفاده: <livewire:shop.contact-form />
 */
class ContactForm extends Component
{
    use WithToasts;

    /* ---- form ---- */
    public string $name = '';
    public string $phone = '';
    public string $email = '';
    public string $requestType = 'project';
    public string $message = '';

    /** پاسخ سؤال امنیتی (کپچای خودمیزبان) */
    public string $captcha = '';

    /** هانی‌پات — انسان این فیلد مخفی را پر نمی‌کند */
    public string $website = '';

    public function mount(): void
    {
        // پیش‌پر کردن نام/ایمیل مشتری واردشده (اختیاری — برای راحتی)
        $customer = auth('customer')->user();

        if ($customer) {
            $this->name  = (string) ($customer->name ?: '');
            $this->email = (string) ($customer->email ?: '');
        }

        if (Features::captcha('contact')) {
            CaptchaService::fresh();
        }
    }

    /** سؤال امنیتی جدید (دکمهٔ «سؤال دیگر» — بدون رفرش صفحه) */
    public function refreshCaptcha(): void
    {
        CaptchaService::fresh();
        $this->reset('captcha');
    }

    /** برچسب فارسی انواع درخواست برای سلکت فرم */
    #[Computed]
    public function types(): array
    {
        return ContactMessage::TYPES;
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => [
                'required', 'string', 'max:20',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (normalize_mobile((string) $value) === null) {
                        $fail('شماره موبایل معتبر نیست؛ مثال: ۰۹۱۲۳۴۵۶۷۸۹');
                    }
                },
            ],
            'email'       => ['nullable', 'email', 'max:190'],
            'requestType' => ['required', 'in:'.implode(',', array_keys(ContactMessage::TYPES))],
            'message'     => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    protected $messages = [
        'name.required'      => 'نام شما الزامی است.',
        'name.max'           => 'نام حداکثر ۱۲۰ کاراکتر است.',
        'phone.required'     => 'شماره موبایل الزامی است.',
        'phone.max'          => 'شماره موبایل حداکثر ۲۰ کاراکتر است.',
        'email.email'        => 'ایمیل معتبر نیست.',
        'requestType.required' => 'نوع درخواست را انتخاب کنید.',
        'requestType.in'     => 'نوع درخواست معتبر نیست.',
        'message.required'   => 'توضیح درخواست الزامی است.',
        'message.min'        => 'توضیح درخواست خیلی کوتاه است؛ کمی بیشتر بنویسید (حداقل ۱۰ کاراکتر).',
        'message.max'        => 'توضیح درخواست حداکثر ۵٬۰۰۰ کاراکتر است.',
    ];

    public function submit(): void
    {
        // ۱) سقف ارسال برای هر IP — اعداد از پنل «امنیت و امکانات»
        [$maxPerWindow, $decayMinutes] = Features::lockoutConfig('contact');
        $limiterKey = 'shop-contact:'.request()->ip();

        if (RateLimiter::tooManyAttempts($limiterKey, $maxPerWindow)) {
            $minutes = (int) ceil(RateLimiter::availableIn($limiterKey) / 60);

            $this->addError('captcha', 'تعداد درخواست‌های ارسالی بیش از حد مجاز است؛ حدود '.fa_num(max(1, $minutes)).' دقیقهٔ دیگر دوباره تلاش کنید.');
            return;
        }

        $data = $this->validate();

        // ۲) هانی‌پات: فیلد مخفی پُر است = ربات → ذخیره نمی‌شود
        if (Features::honeypot() && trim($this->website) !== '') {
            CaptchaService::fresh();
            $this->reset('captcha');
            $this->addError('captcha', 'پاسخ سؤال امنیتی نادرست است؛ سؤال جدید آماده شد.');
            return;
        }

        // ۳) کپچای خودمیزبان (پاسخ مصرف و سؤال تازه می‌شود)
        if (Features::captcha('contact') && ! CaptchaService::verify($this->captcha)) {
            $this->reset('captcha');
            $this->addError('captcha', 'پاسخ سؤال امنیتی نادرست است؛ سؤال جدید آماده شد.');
            return;
        }

        RateLimiter::hit($limiterKey, $decayMinutes * 60);

        ContactMessage::create([
            'name'         => trim($data['name']),
            'phone'        => normalize_mobile($data['phone']),
            'email'        => $data['email'] ?: null,
            'request_type' => $data['requestType'],
            'message'      => trim($data['message']),
            'status'       => ContactMessage::STATUS_NEW,
            'ip'           => request()->ip(),
        ]);

        $this->reset('message', 'captcha');

        $this->toast('درخواست شما ثبت شد؛ کارشناسان ما در سریع‌ترین زمان با شما تماس می‌گیرند. 🌟');
    }

    public function render()
    {
        return view('livewire.shop.contact-form');
    }
}
