<?php

namespace App\Livewire\Settings;

use App\Livewire\Concerns\WithToasts;
use App\Models\Setting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('تنظیمات')]
class Index extends Component
{
    use WithToasts;

    /** نتیجه تست اتصال به شاپرک */
    public array $shaparakTest = [];

    public bool $testingShaparak = false;

    /** @var array<string, mixed> کلیدها مطابق SettingController قدیمی */
    public array $form = [
        'site_name' => 'پنل مدیریت آپدیت',
        'site_description' => '',
        'site_logo' => '',
        'theme_color' => '#10b981',
        'mail_enabled' => false,
        // اتصال تنظیمات SMTP بالا به پیکربندی ایمیل در زمان اجرا (پیش‌نیاز ایمیل‌های سیستمی مثل 2FA)
        'mail_use_settings' => false,
        'mail_from_address' => '',
        'mail_from_name' => '',
        'mail_host' => '',
        'mail_port' => '587',
        'mail_username' => '',
        'mail_password' => '',

        // ---- پرداخت ----
        'payment_shaparak_proxy' => '',

        // ---- اشتراک ----
        // تأیید خودکار خریدهای اشتراک: پس از پرداخت موفق/خرید رایگان، بدون نیاز به تأیید مدیر
        'subscriptions_auto_approve' => false,

        // ---- پیامک ----
        'sms_enabled' => false,
        'sms_driver' => 'kavenegar',
        'sms_admin_mobile' => '',
        'sms_notify_admin' => false,
        'sms_expire_days' => '3',
        // kavenegar
        'sms_kavenegar_apikey' => '',
        // melipayamak
        'sms_melipayamak_username' => '',
        'sms_melipayamak_password' => '',
        'sms_melipayamak_from' => '',
        // ippanel
        'sms_ippanel_username' => '',
        'sms_ippanel_password' => '',
        'sms_ippanel_from' => '',
        // farazsms
        'sms_farazsms_apikey' => '',
        'sms_farazsms_from' => '',
        // idehpardazan
        'sms_idehpardazan_apikey' => '',
        'sms_idehpardazan_secretkey' => '',
    ];

    public bool $confirmCache = false;
    public bool $confirmOptimize = false;

    /** کلیدهای بولی فرم (ذخیره ۰/۱ و خواندن bool) */
    protected const BOOL_KEYS = [
        'mail_enabled', 'mail_use_settings', 'sms_enabled', 'sms_notify_admin',
        'subscriptions_auto_approve',
    ];

    /** گروه ذخیره هر کلید (مطابق ساختار جدول settings) */
    protected const GROUPS = [
        'site_name' => 'general',
        'site_description' => 'general',
        'site_logo' => 'general',
        'theme_color' => 'general',
        'mail_enabled' => 'email',
        'mail_use_settings' => 'email',
        'mail_from_address' => 'email',
        'mail_from_name' => 'email',
        'mail_host' => 'email',
        'mail_port' => 'email',
        'mail_username' => 'email',
        'mail_password' => 'email',

        // ---- پرداخت ----
        'payment_shaparak_proxy' => 'payments',

        // ---- اشتراک ----
        'subscriptions_auto_approve' => 'subscriptions',

        // ---- پیامک ----
        'sms_enabled' => 'sms',
        'sms_driver' => 'sms',
        'sms_admin_mobile' => 'sms',
        'sms_notify_admin' => 'sms',
        'sms_expire_days' => 'sms',
        'sms_kavenegar_apikey' => 'sms',
        'sms_melipayamak_username' => 'sms',
        'sms_melipayamak_password' => 'sms',
        'sms_melipayamak_from' => 'sms',
        'sms_ippanel_username' => 'sms',
        'sms_ippanel_password' => 'sms',
        'sms_ippanel_from' => 'sms',
        'sms_farazsms_apikey' => 'sms',
        'sms_farazsms_from' => 'sms',
        'sms_idehpardazan_apikey' => 'sms',
        'sms_idehpardazan_secretkey' => 'sms',
    ];

    public function mount(): void
    {
        $stored = Setting::query()
            ->whereIn('group', ['general', 'email', 'sms', 'payments', 'subscriptions'])
            ->pluck('value', 'key');

        foreach (array_keys($this->form) as $key) {
            if ($stored->has($key)) {
                $this->form[$key] = in_array($key, self::BOOL_KEYS, true)
                    ? (bool) filter_var($stored->get($key), FILTER_VALIDATE_BOOLEAN)
                    : (string) $stored->get($key);
            }
        }
    }

    /* ---------------------------------------------------------------- */
    /*  Actions (پورت‌شده از SettingController)                          */
    /* ---------------------------------------------------------------- */

    /** ذخیره تنظیمات عمومی و ایمیل (پورت SettingController@update) */
    public function save(): void
    {
        $this->validate();

        foreach (self::GROUPS as $key => $group) {
            $value = $this->form[$key];

            if (in_array($key, self::BOOL_KEYS, true)) {
                $value = $value ? '1' : '0';
            }

            Setting::set($key, (string) $value, 'string', $group);
        }

        // پاک کردن کش تنظیمات (مطابق کنترلر قدیمی)
        Cache::forget('settings');

        // کش کلیدهای sms_* (helper setting از کش ۶۰ثانیه‌ای استفاده می‌کند)
        foreach (array_keys(self::GROUPS) as $key) {
            Cache::forget('setting:' . $key);
        }

        $this->toast('تنظیمات با موفقیت ذخیره شد.');
    }

    /** پاک‌سازی کش سیستم (پورت SettingController@clearCache) */
    public function clearCache(): void
    {
        $this->confirmCache = false;

        try {
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('view:clear');

            $this->toast('کش سیستم با موفقیت پاک شد.');
        } catch (\Throwable $e) {
            $this->toast('خطا در پاک‌سازی کش: '.$e->getMessage(), 'error');
        }
    }

    /** بهینه‌سازی سیستم (پورت SettingController@optimize) */
    public function optimize(): void
    {
        $this->confirmOptimize = false;

        $failed = [];
        foreach (['config:cache', 'route:cache', 'view:cache'] as $command) {
            try {
                Artisan::call($command);
            } catch (\Throwable) {
                $failed[] = $command;
            }
        }

        if ($failed !== []) {
            $this->toast('بهینه‌سازی انجام شد اما این مراحل ناموفق بود: '.implode('، ', $failed), 'warning');
        } else {
            $this->toast('سیستم بهینه‌سازی شد.');
        }
    }

    public function render()
    {
        return view('livewire.settings.index');
    }

    /* ---------------------------------------------------------------- */
    /*  تست اتصال به شاپرک                                               */
    /* ---------------------------------------------------------------- */

    /**
     * تست مرحله‌ای اتصال به درگاه‌های شاپرکی (سامان/سپ):
     * DNS → TCP → TLS/HTTP — با و بدون پروکسی.
     *
     * شاپرک به IPهای خارج از ایران در سطح TCP پاسخ نمی‌دهد؛ این تست علت را
     * دقیق مشخص می‌کند تا راه‌حل (پروکسی ایرانی یا هاست داخلی) روشن شود.
     */
    public function testShaparakConnection(): void
    {
        $this->testingShaparak = true;
        $this->shaparakTest = [];

        // پروکسی فرم (اگر مدیر تازه وارد کرده و ذخیره نکرده، همین مقدار تست می‌شود)
        $proxy = trim((string) ($this->form['payment_shaparak_proxy'] ?? ''));

        // ذخیره موقت پروکسی تا helper shaparak_proxy() آن را ببیند
        if ($proxy !== '') {
            Setting::set('payment_shaparak_proxy', $proxy, 'string', 'payments');
            Cache::forget('setting:payment_shaparak_proxy');
        }

        $host = 'sep.shaparak.ir';
        $url  = 'https://' . $host . '/Payments/InitPayment.asmx?WSDL';

        // ۱) DNS
        $ip = gethostbynamel($host)[0] ?? '';

        if ($ip === '' || $ip === $host) {
            $this->shaparakTest = [
                'ok'      => false,
                'proxy'   => $proxy,
                'title'   => 'DNS ناموفق',
                'message' => 'دامنه sep.shaparak.ir روی سرور شما resolve نشد؛ DNS هاست را بررسی کنید.',
            ];
            $this->testingShaparak = false;

            return;
        }

        // ۲) TCP + ۳) HTTP با cURL (بدون/با پروکسی)
        $ch = curl_init($url);

        $options = [
            CURLOPT_NOBODY         => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_SSL_VERIFYPEER => false, // فقط تست اتصال
            CURLOPT_FOLLOWLOCATION => false,
        ];

        if ($proxy !== '') {
            $options[CURLOPT_PROXY] = $proxy;

            if (preg_match('#^https?://#i', $proxy)) {
                $options[CURLOPT_HTTPPROXYTUNNEL] = true;
            }
        }

        curl_setopt_array($ch, $options);

        $body     = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errno    = (int) curl_errno($ch);
        $error    = (string) curl_error($ch);
        curl_close($ch);

        $usedProxy = $proxy !== '';

        if ($body !== false && $httpCode > 0) {
            $this->shaparakTest = [
                'ok'      => true,
                'proxy'   => $proxy,
                'title'   => 'اتصال برقرار است ✓',
                'message' => 'سرور شما به شاپرک دسترسی دارد' . ($usedProxy ? ' (از طریق پروکسی)' : '') . '. اگر تراکنش هنوز خطا می‌دهد، درگاه را با «سامان SEP (REST)» برای ترمینال‌های جدید (UUID) امتحان کنید.',
            ];
        } else {
            $isTimeout = in_array($errno, [CURLE_OPERATION_TIMEDOUT, CURLE_COULDNT_CONNECT, CURLE_COULDNT_RESOLVE_PROXY], true)
                || str_contains($error, 'timed out');

            $this->shaparakTest = [
                'ok'      => false,
                'proxy'   => $proxy,
                'title'   => $isTimeout ? 'سرور به شاپرک دسترسی ندارد (Timeout)' : 'خطای اتصال به شاپرک',
                'message' => $isTimeout
                    ? 'شاپرک به IPهای خارج از ایران پاسخ نمی‌دهد. اگر هاست شما خارج از ایران است: (۱) یک پروکسی/سرور واسط ایرانی تهیه و آدرس آن را در فیلد «پروکسی شاپرک» وارد کنید (مثل http://IP:PORT یا socks5://IP:PORT) و دوباره تست بگیرید؛ (۲) یا پنل را روی هاست ایرانی منتقل کنید.'
                    : ('خطا: ' . ($error !== '' ? $error : 'unknown') . ' — آدرس IP مقصد: ' . $ip),
            ];
        }

        $this->testingShaparak = false;
    }

    /** @return array<string, array<int, string>|string> */
    protected function rules(): array
    {
        return [
            'form.site_name' => ['nullable', 'string', 'max:255'],
            'form.site_description' => ['nullable', 'string', 'max:2000'],
            'form.site_logo' => ['nullable', 'string', 'max:255'],
            'form.theme_color' => ['nullable', 'string', 'max:32'],
            'form.mail_from_address' => ['nullable', 'email', 'max:255'],
            'form.mail_from_name' => ['nullable', 'string', 'max:255'],
            'form.mail_host' => ['nullable', 'string', 'max:255'],
            'form.mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'form.mail_username' => ['nullable', 'string', 'max:255'],
            'form.mail_password' => ['nullable', 'string', 'max:255'],
            'form.payment_shaparak_proxy' => ['nullable', 'string', 'max:255'],
            'form.subscriptions_auto_approve' => ['nullable', 'boolean'],

            'form.sms_driver' => ['nullable', 'string', 'in:kavenegar,melipayamak,ippanel,farazsms,idehpardazan'],
            'form.sms_admin_mobile' => ['nullable', 'string', 'max:20'],
            'form.sms_expire_days' => ['nullable', 'integer', 'min:1', 'max:60'],
            'form.sms_kavenegar_apikey' => ['nullable', 'string', 'max:255'],
            'form.sms_melipayamak_username' => ['nullable', 'string', 'max:255'],
            'form.sms_melipayamak_password' => ['nullable', 'string', 'max:255'],
            'form.sms_melipayamak_from' => ['nullable', 'string', 'max:255'],
            'form.sms_ippanel_username' => ['nullable', 'string', 'max:255'],
            'form.sms_ippanel_password' => ['nullable', 'string', 'max:255'],
            'form.sms_ippanel_from' => ['nullable', 'string', 'max:255'],
            'form.sms_farazsms_apikey' => ['nullable', 'string', 'max:255'],
            'form.sms_farazsms_from' => ['nullable', 'string', 'max:255'],
            'form.sms_idehpardazan_apikey' => ['nullable', 'string', 'max:255'],
            'form.sms_idehpardazan_secretkey' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function messages(): array
    {
        return [
            'form.mail_from_address.email' => 'آدرس ایمیل فرستنده معتبر نیست.',
            'form.mail_port.integer' => 'پورت باید عدد باشد.',
            'form.mail_port.max' => 'شماره پورت معتبر نیست.',
            'form.sms_driver.in' => 'درایور پیامک معتبر نیست.',
            'form.sms_expire_days.integer' => 'تعداد روز انقضا باید عدد باشد.',
            'form.sms_expire_days.min' => 'حداقل ۱ روز.',
            'form.sms_expire_days.max' => 'حداکثر ۶۰ روز.',
        ];
    }
}
