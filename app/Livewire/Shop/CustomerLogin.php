<?php

namespace App\Livewire\Shop;

use App\Livewire\Concerns\WithToasts;
use App\Models\Customer;
use App\Services\CaptchaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * ورود مشتری فروشگاه — فقط با «کد آپدیت».
 * جدول مشتریان همان customers است (فیلد update_code).
 *
 * لایه‌های ضدربات: کپچای خودمیزبان + هانی‌پات + قفل تلاش‌های ناموفق
 * (۸ کد اشتباه → قفل ۱۵ دقیقه‌ای برای همان IP — جلوگیری از جست‌وجوی کد).
 */
#[Layout('components.layouts.shop')]
#[Title('ورود مشتری')]
class CustomerLogin extends Component
{
    use WithToasts;

    /** حداکثر تلاش ناموفق پیش از قفل */
    protected const MAX_ATTEMPTS = 8;

    /** مدت قفل (ثانیه) */
    protected const LOCK_SECONDS = 900;

    public string $updateCode = '';

    public string $redirect = '';

    /** پاسخ سؤال امنیتی (کپچای خودمیزبان) */
    public string $captcha = '';

    /** هانی‌پات — انسان این فیلد مخفی را پر نمی‌کند */
    public string $website = '';

    public function mount(): void
    {
        // اگر از قبل وارد شده، به پروفایل برو
        if (auth('customer')->check()) {
            $this->redirect(route('customer.profile'), navigate: true);
        }

        $this->redirect = (string) request()->query('redirect', '');

        CaptchaService::fresh();
    }

    /** سؤال امنیتی جدید (دکمهٔ «سؤال دیگر» — بدون رفرش صفحه) */
    public function refreshCaptcha(): void
    {
        CaptchaService::fresh();
        $this->reset('captcha');
    }

    protected function rules(): array
    {
        return [
            'updateCode' => ['required', 'string', 'max:64'],
        ];
    }

    protected $messages = [
        'updateCode.required' => 'کد آپدیت الزامی است.',
    ];

    public function login(): void
    {
        $limiterKey = 'cust-login:' . request()->ip();

        // ۱) قفل تلاش‌های ناموفق (ضد جست‌وجوی کد آپدیت)
        if (RateLimiter::tooManyAttempts($limiterKey, self::MAX_ATTEMPTS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($limiterKey) / 60);

            $this->addError('captcha', 'تلاش‌های ناموفق بیش از حد مجاز است؛ حدود ' . fa_num(max(1, $minutes)) . ' دقیقهٔ دیگر دوباره تلاش کنید.');
            return;
        }

        $data = $this->validate();

        // ۲) هانی‌پات: فیلد مخفی پُر است = ربات → پیام عمومی و خروج
        if (trim($this->website) !== '') {
            CaptchaService::fresh();
            $this->reset('captcha');
            $this->addError('updateCode', 'کد آپدیت نامعتبر است.');
            return;
        }

        // ۳) کپچای خودمیزبان (پاسخ مصرف و سؤال تازه می‌شود)
        if (! CaptchaService::verify($this->captcha)) {
            $this->reset('captcha');
            $this->addError('captcha', 'پاسخ سؤال امنیتی نادرست است؛ سؤال جدید آماده شد.');
            return;
        }

        $customer = Customer::query()
            ->where('update_code', strtoupper(trim($data['updateCode'])))
            ->first();

        if (!$customer) {
            RateLimiter::hit($limiterKey, self::LOCK_SECONDS);
            $this->addError('updateCode', 'کد آپدیت نامعتبر است.');
            return;
        }

        if ($customer->status !== Customer::STATUS_ACTIVE) {
            $this->addError('updateCode', 'حساب این مشتری غیرفعال است. با پشتیبانی تماس بگیرید.');
            return;
        }

        RateLimiter::clear($limiterKey);

        Auth::guard('customer')->login($customer, remember: true);

        // هم‌گام با مکانیزم قدیمی (کد در session برای نشان اشتراک)
        session()->put('shop_update_code', $customer->update_code);

        $this->toast('خوش آمدید، ' . $customer->name . '!');

        $target = ($this->redirect !== '' && str_starts_with($this->redirect, '/'))
            ? $this->redirect
            : route('customer.profile');

        $this->redirect($target, navigate: true);
    }

    public function render()
    {
        return view('livewire.shop.customer-login');
    }
}
