<?php

namespace App\Livewire\Shop;

use App\Livewire\Concerns\WithToasts;
use App\Models\Customer;
use App\Services\CaptchaService;
use App\Support\Features;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * ورود مشتری فروشگاه — فقط با «کد آپدیت» + ثبت‌نام مشتری جدید.
 * جدول مشتریان همان customers است (فیلد update_code).
 *
 * لایه‌های ضدربات (هرکدام از پنل «امنیت و امکانات» قابل تنظیم):
 * کپچای خودمیزبان + هانی‌پات + قفل تلاش‌های ناموفق برای هر IP
 * (جلوگیری از جست‌وجوی کد آپدیت و پرشدن جدول مشتریان با ربات).
 */
#[Layout('components.layouts.shop')]
#[Title('ورود مشتری')]
class CustomerLogin extends Component
{
    use WithToasts;

    public const TAB_LOGIN = 'login';

    public const TAB_REGISTER = 'register';

    /** تب فعال صفحه: ورود یا ثبت‌نام */
    public string $tab = self::TAB_LOGIN;

    public string $updateCode = '';

    public string $redirect = '';

    /** پاسخ سؤال امنیتی (کپچای خودمیزبان) */
    public string $captcha = '';

    /** هانی‌پات — انسان این فیلد مخفی را پر نمی‌کند */
    public string $website = '';

    /** فرم ثبت‌نام مشتری جدید */
    public array $reg = [
        'name' => '',
        'email' => '',
        'phone' => '',
        'website_url' => '',
    ];

    public function mount(): void
    {
        // اگر از قبل وارد شده، به پروفایل برو
        if (auth('customer')->check()) {
            $this->redirect(route('customer.profile'), navigate: true);
        }

        $this->redirect = (string) request()->query('redirect', '');

        if (Features::captcha('customer_login')) {
            CaptchaService::fresh('customer_login');
        }
    }

    /** تغییر تب (ورود ↔ ثبت‌نام) — خطاهای اعتبارسنجی هر تب جدا می‌ماند */
    public function switchTab(string $tab): void
    {
        $this->tab = in_array($tab, [self::TAB_LOGIN, self::TAB_REGISTER], true)
            ? $tab
            : self::TAB_LOGIN;

        $this->resetValidation();
    }

    /** سؤال امنیتی جدید (دکمهٔ «سؤال دیگر» — بدون رفرش صفحه) */
    public function refreshCaptcha(): void
    {
        CaptchaService::fresh('customer_login');
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

    /* ------------------------------------------------------------------ */
    /*  ورود با کد آپدیت */
    /* ------------------------------------------------------------------ */

    public function login(): void
    {
        // ۱) قفل تلاش‌های ناموفق (ضد جست‌وجوی کد آپدیت) — اعداد از پنل
        [$maxAttempts, $decayMinutes] = Features::lockoutConfig('customer_login');
        $limiterKey = 'cust-login:'.request()->ip();

        if (RateLimiter::tooManyAttempts($limiterKey, $maxAttempts)) {
            $minutes = (int) ceil(RateLimiter::availableIn($limiterKey) / 60);

            $this->addError('updateCode', 'تلاش‌های ناموفق بیش از حد مجاز است؛ حدود '.fa_num(max(1, $minutes)).' دقیقهٔ دیگر دوباره تلاش کنید.');

            return;
        }

        $data = $this->validate();

        // ۲) هانی‌پات: فیلد مخفی پُر است = ربات → پیام عمومی و خروج
        if (Features::honeypot() && trim($this->website) !== '') {
            CaptchaService::fresh('customer_login');
            $this->reset('captcha');
            $this->addError('updateCode', 'کد آپدیت نامعتبر است.');

            return;
        }

        // ۳) کپچای خودمیزبان (پاسخ مصرف و سؤال تازه می‌شود)
        if (Features::captcha('customer_login') && ! CaptchaService::verify($this->captcha, 'customer_login')) {
            $this->reset('captcha');
            $this->addError('captcha', 'پاسخ سؤال امنیتی نادرست است؛ سؤال جدید آماده شد.');

            return;
        }

        $customer = Customer::query()
            ->where('update_code', strtoupper(trim($data['updateCode'])))
            ->first();

        if (! $customer) {
            RateLimiter::hit($limiterKey, $decayMinutes * 60);
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

        $this->toast('خوش آمدید، '.$customer->name.'!');

        $this->redirect($this->safeRedirectTarget(), navigate: true);
    }

    /* ------------------------------------------------------------------ */
    /*  ثبت‌نام مشتری جدید */
    /* ------------------------------------------------------------------ */

    /** @return array<string, array<int, string>> */
    protected function regRules(): array
    {
        return [
            'reg.name' => ['required', 'string', 'min:3', 'max:120'],
            'reg.email' => ['required', 'email', 'max:190', 'unique:customers,email'],
            'reg.phone' => ['nullable', 'string', 'max:20'],
            // آدرس سایت خیلی مهم است: پکیج‌ها و لایسنس‌ها فقط روی همین دامنه کار می‌کنند
            'reg.website_url' => ['required', 'url', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    protected function regMessages(): array
    {
        return [
            'reg.name.required' => 'نام و نام خانوادگی الزامی است.',
            'reg.name.min' => 'نام و نام خانوادگی حداقل ۳ حرف است.',
            'reg.name.max' => 'نام و نام خانوادگی حداکثر ۱۲۰ حرف است.',
            'reg.email.required' => 'ایمیل الزامی است.',
            'reg.email.email' => 'فرمت ایمیل معتبر نیست.',
            'reg.email.unique' => 'این ایمیل قبلاً در سیستم ثبت شده است.',
            'reg.phone.max' => 'شماره تلفن حداکثر ۲۰ نویسه است.',
            'reg.website_url.required' => 'آدرس سایت الزامی است؛ پکیج‌ها و لایسنس‌ها روی همین دامنه فعال می‌شوند.',
            'reg.website_url.url' => 'آدرس سایت معتبر نیست؛ مثلاً myshop.ir وارد کنید.',
            'reg.website_url.max' => 'آدرس سایت حداکثر ۲۵۵ نویسه است.',
        ];
    }

    public function register(): void
    {
        // ۱) قفل ثبت‌نام برای هر IP (ضد پرشدن جدول مشتریان با ربات)
        $limiterKey = 'cust-reg:'.request()->ip();
        $maxAttempts = 6;
        $decaySeconds = 30 * 60;

        if (RateLimiter::tooManyAttempts($limiterKey, $maxAttempts)) {
            $minutes = (int) ceil(RateLimiter::availableIn($limiterKey) / 60);

            $this->addError('reg.email', 'تلاش‌های ثبت‌نام بیش از حد مجاز است؛ حدود '.fa_num(max(1, $minutes)).' دقیقهٔ دیگر دوباره تلاش کنید.');

            return;
        }

        // ۲) نرمال‌سازی ورودی‌ها — ارقام فارسی تلفن، ایمیل کوچک، اسکیمِ آدرس سایت
        $this->reg['name'] = trim($this->reg['name']);
        $this->reg['email'] = mb_strtolower(trim($this->reg['email']));
        $this->reg['phone'] = trim(en_num($this->reg['phone']));
        $this->reg['website_url'] = $this->normalizeWebsiteUrl($this->reg['website_url']);

        // اعتبارسنجی فیلدها (خطاها کپچا را مصرف نمی‌کنند)
        $data = $this->validate($this->regRules(), $this->regMessages());

        // ۳) هانی‌پات: فیلد مخفی پُر است = ربات → پیام عمومی و خروج
        if (Features::honeypot() && trim($this->website) !== '') {
            CaptchaService::fresh('customer_login');
            $this->reset('captcha');
            $this->addError('reg.email', 'ثبت‌نام انجام نشد؛ لطفاً دوباره تلاش کنید.');

            return;
        }

        // هر تلاشِ رسیده به مرحلهٔ کپچا شمرده می‌شود (ضد ربات‌های حل‌کنندهٔ کپچا)
        RateLimiter::hit($limiterKey, $decaySeconds);

        // ۴) کپچای خودمیزبان (پاسخ مصرف و سؤال تازه می‌شود)
        if (Features::captcha('customer_login') && ! CaptchaService::verify($this->captcha, 'customer_login')) {
            $this->reset('captcha');
            $this->addError('captcha', 'پاسخ سؤال امنیتی نادرست است؛ سؤال جدید آماده شد.');

            return;
        }

        // ۵) یکتایی دامنهٔ سایت — دو مشتری نباید روی یک دامنه پکیج/لایسنس داشته باشند
        $host = $this->domainHost((string) $data['reg']['website_url']);

        if ($host !== '' && $this->domainAlreadyRegistered($host)) {
            $this->addError('reg.website_url', 'این دامنه قبلاً برای مشتری دیگری ثبت شده است؛ اگر سایت شما همین است، با پشتیبانی تماس بگیرید.');

            return;
        }

        // ۶) ساخت مشتری — وضعیت پیش‌فرض «فعال»؛ کد آپدیت خودکار در هوک creating مدل
        $customer = Customer::create([
            'name' => $data['reg']['name'],
            'email' => $data['reg']['email'],
            'phone' => $data['reg']['phone'] !== '' ? $data['reg']['phone'] : null,
            'website_url' => $data['reg']['website_url'],
            'status' => Customer::STATUS_ACTIVE,
        ]);

        RateLimiter::clear($limiterKey);

        // ورود خودکار بعد از ثبت‌نام — هم‌گام با جریان ورود
        Auth::guard('customer')->login($customer, remember: true);
        session()->put('shop_update_code', $customer->update_code);

        $this->reset('reg');
        $this->resetValidation();

        $this->toast('ثبت‌نام با موفقیت انجام شد؛ خوش آمدید! کد آپدیت شما: '.$customer->update_code);

        $this->redirect($this->safeRedirectTarget(), navigate: true);
    }

    /* ------------------------------------------------------------------ */
    /*  کمک‌کارها */
    /* ------------------------------------------------------------------ */

    /** آدرس سایت را نرمال می‌کند: بدون اسکیم → https فرض می‌شود */
    protected function normalizeWebsiteUrl(string $value): string
    {
        $site = trim($value);

        if ($site === '') {
            return '';
        }

        if (! preg_match('#^https?://#i', $site)) {
            $site = 'https://'.$site;
        }

        return rtrim($site, '/');
    }

    /** هاست نرمال‌شدهٔ یک آدرس (بدون http/https و www) */
    protected function domainHost(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return (string) preg_replace('#^www\.#', '', $host);
    }

    /** آیا این دامنه از قبل برای مشتری دیگری ثبت شده است؟ */
    protected function domainAlreadyRegistered(string $host): bool
    {
        $needle = addcslashes($host, '%_\\');

        return Customer::query()
            ->where('website_url', 'like', "%{$needle}%")
            ->get()
            ->contains(fn (Customer $c) => $this->domainHost((string) $c->website_url) === $host);
    }

    /**
     * مقصد امن پس از ورود/ثبت‌نام (Task 85 + اصلاح redirect کامل):
     * - مسیر نسبی داخل سایت → امن اگر با / شروع شود و // یا /\ نباشد
     * - URL کامل → فقط اگر هاستش همان هاست سایت است (ضد open-redirect)
     *   (لینک‌های فروشگاه «?redirect=url()->current()» می‌فرستند — کامل)
     */
    protected function safeRedirectTarget(): string
    {
        $redirect = trim($this->redirect);

        if ($redirect === '') {
            return route('customer.profile');
        }

        if (str_starts_with($redirect, '/')
            && ! str_starts_with($redirect, '//')
            && ! str_starts_with($redirect, '/\\')) {
            return $redirect;
        }

        $host = strtolower((string) parse_url($redirect, PHP_URL_HOST));

        $allowedHosts = array_filter(array_unique([
            strtolower((string) request()->getHost()),
            strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST)),
        ]));

        if ($host !== '' && in_array($host, $allowedHosts, true)) {
            return $redirect;
        }

        return route('customer.profile');
    }

    public function render()
    {
        return view('livewire.shop.customer-login');
    }
}
