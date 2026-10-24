<?php

namespace App\Livewire\Settings;

use App\Livewire\Concerns\WithToasts;
use App\Models\Setting;
use App\Support\Features;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * پنل «امنیت و امکانات» (/admin/settings/security):
 * هر قابلیت امنیتی و امکان جدید با کلید جداگانه از همین صفحه
 * فعال/غیرفعال و شخصی‌سازی می‌شود.
 *
 * الگو: مطابق Settings\Index — فرم $form + حلقهٔ ذخیره + پاک‌سازی کش هر کلید.
 */
#[Layout('components.layouts.app')]
#[Title('امنیت و امکانات')]
class Security extends Component
{
    use WithToasts;

    public array $form = [];

    public function mount(): void
    {
        // پیش‌فرض‌ها + مقادیر ذخیره‌شده
        foreach (Features::DEFAULTS as $key => $default) {
            $this->form[$key] = $default;
        }

        $stored = Setting::query()
            ->whereIn('group', ['security', 'features'])
            ->pluck('value', 'key');

        foreach ($stored as $key => $value) {
            if (array_key_exists($key, $this->form)) {
                $this->form[$key] = in_array($key, Features::BOOL_KEYS, true)
                    ? (bool) filter_var($value, FILTER_VALIDATE_BOOLEAN)
                    : (string) $value;
            }
        }
    }

    public function save(): void
    {
        $this->validate();

        foreach (Features::DEFAULTS as $key => $default) {
            if (! array_key_exists($key, $this->form)) {
                continue;
            }

            $value = $this->form[$key];

            if (in_array($key, Features::BOOL_KEYS, true)) {
                $value = $value ? '1' : '0';
            }

            Setting::set($key, (string) $value, 'string', Features::group($key));
        }

        // پاک‌سازی کش تنظیمات (helper setting از کش ۶۰ثانیه‌ای می‌خواند)
        foreach (array_keys(Features::DEFAULTS) as $key) {
            \Illuminate\Support\Facades\Cache::forget('setting:'.$key);
        }
        \Illuminate\Support\Facades\Cache::forget('settings');

        $this->toast('تنظیمات امنیت و امکانات ذخیره شد.');
    }

    public function render()
    {
        return view('livewire.settings.security');
    }

    /* ------------------------------------------------------------------ */
    /*  قواعد اعتبارسنجی                                                   */
    /* ------------------------------------------------------------------ */

    protected function rules(): array
    {
        return [
            // کپچا
            'form.sec_captcha_min_seconds' => ['required', 'integer', 'min:0', 'max:60'],

            // قفل تلاش‌ها
            'form.sec_lockout_admin_max'      => ['required', 'integer', 'min:1', 'max:100'],
            'form.sec_lockout_admin_decay'    => ['required', 'integer', 'min:1', 'max:1440'],
            'form.sec_lockout_customer_max'   => ['required', 'integer', 'min:1', 'max:100'],
            'form.sec_lockout_customer_decay' => ['required', 'integer', 'min:1', 'max:1440'],
            'form.sec_lockout_comment_max'    => ['required', 'integer', 'min:1', 'max:100'],
            'form.sec_lockout_comment_decay'  => ['required', 'integer', 'min:1', 'max:1440'],

            // 2FA
            'form.sec_2fa_channel' => ['required', 'in:email,sms,both'],

            // نوار اعلان
            'form.feat_announcement_text'      => ['nullable', 'string', 'max:500'],
            'form.feat_announcement_bg'        => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'form.feat_announcement_color'     => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'form.feat_announcement_link'      => ['nullable', 'string', 'max:500'],
            'form.feat_announcement_link_text' => ['nullable', 'string', 'max:100'],

            // حالت تعمیر
            'form.feat_maintenance_message' => ['nullable', 'string', 'max:1000'],

            // تغییرات عمومی
            'form.feat_changelog_title' => ['nullable', 'string', 'max:100'],

            // داشبورد — آستانهٔ هشدار انقضای لایسنس
            'form.feat_lic_expiry_days' => ['required', 'integer', 'min:1', 'max:365'],

            // فوتر
            'form.feat_footer_text'        => ['nullable', 'string', 'max:300'],
            'form.feat_footer_description' => ['nullable', 'string', 'max:200'],
            'form.feat_social_telegram'    => ['nullable', 'string', 'max:500'],
            'form.feat_social_instagram'   => ['nullable', 'string', 'max:500'],
            'form.feat_social_whatsapp'    => ['nullable', 'string', 'max:500'],
            'form.feat_social_email'       => ['nullable', 'email', 'max:190'],
        ];
    }

    protected function messages(): array
    {
        return [
            'form.sec_captcha_min_seconds.required' => 'حداقل ثانیهٔ تلهٔ زمان را وارد کنید.',
            'form.sec_captcha_min_seconds.integer'  => 'تلهٔ زمان باید عدد باشد.',
            'form.sec_lockout_admin_max.integer'    => 'سقف تلاش باید عدد باشد.',
            'form.sec_lockout_admin_decay.integer'  => 'پنجرهٔ قفل باید عدد باشد.',
            'form.sec_lockout_customer_max.integer' => 'سقف تلاش باید عدد باشد.',
            'form.sec_lockout_customer_decay.integer' => 'پنجرهٔ قفل باید عدد باشد.',
            'form.sec_lockout_comment_max.integer'  => 'سقف ارسال باید عدد باشد.',
            'form.sec_lockout_comment_decay.integer' => 'پنجرهٔ زمان باید عدد باشد.',
            'form.sec_2fa_channel.in'               => 'کانال ارسال کد معتبر نیست.',
            'form.feat_announcement_text.max'       => 'متن اعلان حداکثر ۵۰۰ کاراکتر است.',
            'form.feat_announcement_bg.regex'       => 'رنگ پس‌زمینه باید کد HEX باشد (مثل #10b981).',
            'form.feat_announcement_color.regex'    => 'رنگ متن باید کد HEX باشد (مثل #ffffff).',
            'form.feat_social_email.email'          => 'ایمیل معتبر نیست.',
            'form.feat_maintenance_message.max'     => 'پیام حالت تعمیر حداکثر ۱٬۰۰۰ کاراکتر است.',
            'form.feat_lic_expiry_days.required'    => 'آستانهٔ روزهای هشدار انقضا را وارد کنید.',
            'form.feat_lic_expiry_days.integer'     => 'آستانهٔ هشدار باید عدد باشد.',
            'form.feat_lic_expiry_days.min'         => 'آستانهٔ هشدار حداقل ۱ روز است.',
            'form.feat_lic_expiry_days.max'         => 'آستانهٔ هشدار حداکثر ۳۶۵ روز است.',
        ];
    }
}
