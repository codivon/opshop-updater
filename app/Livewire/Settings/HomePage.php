<?php

namespace App\Livewire\Settings;

use App\Livewire\Concerns\WithToasts;
use App\Models\Setting;
use App\Support\HomePage as HomeSettings;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * پنل «صفحهٔ اصلی فروشگاه» (/admin/settings/home) — Task 56:
 * همهٔ ویجت‌های صفحهٔ اصلی (هیرو، آمار اعتماد، معرفی، چرا ما، نظرات،
 * پکیج/پروژه/قالب، اشتراک، تماس با ما) و کد اینماد فوتر از همین صفحه
 * فعال/غیرفعال و شخصی‌سازی می‌شوند.
 *
 * الگو: مطابق Settings\Security — فرم $form + حلقهٔ ذخیره + پاک‌سازی کش هر کلید.
 */
#[Layout('components.layouts.app')]
#[Title('صفحهٔ اصلی فروشگاه')]
class HomePage extends Component
{
    use WithToasts;

    public array $form = [];

    public function mount(): void
    {
        // پیش‌فرض‌ها + مقادیر ذخیره‌شده
        foreach (HomeSettings::DEFAULTS as $key => $default) {
            $this->form[$key] = $default;
        }

        $stored = Setting::query()
            ->where('group', 'home')
            ->pluck('value', 'key');

        foreach ($stored as $key => $value) {
            if (array_key_exists($key, $this->form)) {
                $this->form[$key] = in_array($key, HomeSettings::BOOL_KEYS, true)
                    ? (bool) filter_var($value, FILTER_VALIDATE_BOOLEAN)
                    : (string) $value;
            }
        }
    }

    public function save(): void
    {
        $this->validate();

        foreach (HomeSettings::DEFAULTS as $key => $default) {
            if (! array_key_exists($key, $this->form)) {
                continue;
            }

            $value = $this->form[$key];

            if (in_array($key, HomeSettings::BOOL_KEYS, true)) {
                $value = $value ? '1' : '0';
            }

            Setting::set($key, (string) $value, 'string', HomeSettings::group($key));
        }

        // پاک‌سازی کش تنظیمات (helper setting از کش ۶۰ثانیه‌ای می‌خواند)
        foreach (array_keys(HomeSettings::DEFAULTS) as $key) {
            \Illuminate\Support\Facades\Cache::forget('setting:'.$key);
        }
        \Illuminate\Support\Facades\Cache::forget('settings');

        $this->toast('تنظیمات صفحهٔ اصلی ذخیره شد.');
    }

    /** فهرست آیکون‌های مجاز برای «چرا ما» (کلید => برچسب) */
    public function getIconOptionsProperty(): array
    {
        return [
            'code'            => 'کد (توسعه)',
            'credit-card'     => 'کارت بانکی (پرداخت)',
            'shield-check'    => 'سپر تیک‌دار (امنیت)',
            'zap'             => 'آذرخش (سرعت)',
            'bot'             => 'ربات (هوش مصنوعی)',
            'heart-handshake' => 'قلب و دست (پشتیبانی)',
            'users'           => 'کاربران (تیم)',
            'sparkles'        => 'ستاره (ویژه)',
            'rocket'          => 'موشک (رشد)',
            'monitor'         => 'مانیتور (نمایش)',
            'globe'           => 'کره زمین (وب)',
            'wrench'          => 'آچار (فنی)',
            'database'        => 'دیتابیس',
            'lightbulb'       => 'لامپ (ایده)',
        ];
    }

    /** برچسب و آیکون هر بخش (کلید کوتاه => [برچسب، آیکون]) */
    public const SECTION_META = [
        'hero'         => ['هیرو (معرفی بالا)', 'sparkles'],
        'stats'        => ['آمار اعتماد', 'bar-chart'],
        'about'        => ['معرفی (درباره ما)', 'users'],
        'why'          => ['چرا ما', 'badge-check'],
        'testimonials' => ['نظرات مشتریان', 'quote'],
        'categories'   => ['دسته‌بندی محصولات', 'folder-tree'],
        'packages'     => ['پکیج‌ها (محصولات)', 'package'],
        'products'     => ['پروژه‌های آماده', 'rocket'],
        'templates'    => ['قالب‌های آماده', 'layout-template'],
        'subscription_active' => ['اشتراک فعال (وضعیت/بررسی کد)', 'user-check'],
        'subscription' => ['اشتراک‌ها', 'crown'],
        'track'        => ['پیگیری خرید مهمان', 'search'],
        'contact'      => ['تماس با ما', 'message-circle'],
    ];

    /** ردیف‌های بخش‌ها به ترتیب فعلی فرم (home_sort_*) برای تب «بخش‌ها» */
    public function getSectionRowsProperty(): array
    {
        $rows = [];

        foreach (HomeSettings::SECTION_KEYS as $key) {
            [$label, $icon] = self::SECTION_META[$key];

            $rows[] = [
                'key'   => $key,
                'label' => $label,
                'icon'  => $icon,
                'sort'  => (int) ($this->form['home_sort_'.$key] ?? 0),
            ];
        }

        usort($rows, fn ($a, $b) => $a['sort'] <=> $b['sort']);

        return $rows;
    }

    /** جابه‌جایی یک بخش یک ردیف به بالا (فرم ذخیره‌نشده — با «ذخیره تنظیمات» اعمال می‌شود) */
    public function moveSectionUp(string $key): void
    {
        $this->moveSection($key, -1);
    }

    /** جابه‌جایی یک بخش یک ردیف به پایین */
    public function moveSectionDown(string $key): void
    {
        $this->moveSection($key, +1);
    }

    private function moveSection(string $key, int $direction): void
    {
        // مقدار جدید را نسبت به ترتیب «فرم» محاسبه می‌کنیم (نه دیتابیس)
        $keys  = array_column($this->sectionRows, 'key');
        $index = array_search($key, $keys, true);

        if ($index === false) {
            return;
        }

        $neighbor = $index + $direction;

        if ($neighbor < 0 || $neighbor >= count($keys)) {
            return; // بالاترین/پایین‌ترین ردیف — حرکتی نیست
        }

        $other = $keys[$neighbor];

        $tmp = $this->form['home_sort_'.$key];
        $this->form['home_sort_'.$key]   = $this->form['home_sort_'.$other];
        $this->form['home_sort_'.$other] = $tmp;

        // رفع باگ «فلش‌ها کار نمی‌کنند»: computed sectionRows در همین درخواست کش
        // شده بود (requestCachedValue) و رندر بعدی ترتیب کهنه را نشان می‌داد؛
        // کش computed باید پاک شود تا لیست با مقادیر جدید فرم دوباره ساخته شود.
        unset($this->sectionRows);
    }

    public function render()
    {
        return view('livewire.settings.home');
    }

    /* ------------------------------------------------------------------ */
    /*  قواعد اعتبارسنجی                                                   */
    /* ------------------------------------------------------------------ */

    protected function rules(): array
    {
        $max = fn (int $chars) => ['nullable', 'string', 'max:'.$chars];

        $rules = [
            // هیرو
            'form.home_hero_badge'    => $max(100),
            'form.home_hero_title'    => ['nullable', 'string', 'max:200'],
            'form.home_hero_subtitle' => $max(500),
            'form.home_hero_cta_text' => $max(60),
            'form.home_hero_cta_link' => $max(300),
            'form.home_hero_cta2_text' => $max(60),
            'form.home_hero_cta2_link' => $max(300),

            // آمار
            'form.home_stat1_value' => $max(30),
            'form.home_stat1_label' => $max(60),
            'form.home_stat2_value' => $max(30),
            'form.home_stat2_label' => $max(60),
            'form.home_stat3_value' => $max(30),
            'form.home_stat3_label' => $max(60),
            'form.home_stat4_value' => $max(30),
            'form.home_stat4_label' => $max(60),

            // معرفی
            'form.home_about_title' => $max(120),
            'form.home_about_body'  => ['nullable', 'string', 'max:5000'],

            // چرا ما
            'form.home_why_title' => $max(120),
            'form.home_why_intro' => $max(300),
            'form.home_why1_icon' => ['nullable', 'in:'.implode(',', array_keys($this->iconOptions))],
            'form.home_why2_icon' => ['nullable', 'in:'.implode(',', array_keys($this->iconOptions))],
            'form.home_why3_icon' => ['nullable', 'in:'.implode(',', array_keys($this->iconOptions))],
            'form.home_why4_icon' => ['nullable', 'in:'.implode(',', array_keys($this->iconOptions))],
            'form.home_why5_icon' => ['nullable', 'in:'.implode(',', array_keys($this->iconOptions))],
            'form.home_why6_icon' => ['nullable', 'in:'.implode(',', array_keys($this->iconOptions))],
            'form.home_why1_title' => $max(120),
            'form.home_why2_title' => $max(120),
            'form.home_why3_title' => $max(120),
            'form.home_why4_title' => $max(120),
            'form.home_why5_title' => $max(120),
            'form.home_why6_title' => $max(120),
            'form.home_why1_text' => $max(500),
            'form.home_why2_text' => $max(500),
            'form.home_why3_text' => $max(500),
            'form.home_why4_text' => $max(500),
            'form.home_why5_text' => $max(500),
            'form.home_why6_text' => $max(500),

            // نظرات
            'form.home_testimonials_title' => $max(120),
            'form.home_testimonial1_text'   => $max(1000),
            'form.home_testimonial1_author' => $max(120),
            'form.home_testimonial2_text'   => $max(1000),
            'form.home_testimonial2_author' => $max(120),
            'form.home_testimonial3_text'   => $max(1000),
            'form.home_testimonial3_author' => $max(120),

            // تماس
            'form.home_contact_title' => $max(120),
            'form.home_contact_intro' => $max(2000),
            'form.contact_type_template_desc' => $max(500),
            'form.contact_type_project_desc'  => $max(500),
            'form.contact_type_startup_desc'  => $max(500),
            'form.contact_phone'    => $max(20),
            'form.contact_email'    => ['nullable', 'email', 'max:190'],
            'form.contact_telegram' => $max(300),
            'form.contact_address'  => $max(200),

            // فوتر — اینماد
            'form.footer_enamad_code' => ['nullable', 'string', 'max:5000'],
        ];

        // ترتیب بخش‌ها — عدد مثبت؛ از فلش‌های پنل تغییر می‌کند
        foreach (HomeSettings::SECTION_KEYS as $key) {
            $rules['form.home_sort_'.$key] = ['required', 'integer', 'min:1', 'max:999'];
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'form.home_hero_title.max'     => 'عنوان اصلی حداکثر ۲۰۰ کاراکتر است.',
            'form.home_hero_subtitle.max'  => 'توضیح هیرو حداکثر ۵۰۰ کاراکتر است.',
            'form.home_about_body.max'     => 'متن معرفی حداکثر ۵٬۰۰۰ کاراکتر است.',
            'form.home_why1_icon.in'       => 'آیکون انتخابی معتبر نیست.',
            'form.home_why2_icon.in'       => 'آیکون انتخابی معتبر نیست.',
            'form.home_why3_icon.in'       => 'آیکون انتخابی معتبر نیست.',
            'form.home_why4_icon.in'       => 'آیکون انتخابی معتبر نیست.',
            'form.home_why5_icon.in'       => 'آیکون انتخابی معتبر نیست.',
            'form.home_why6_icon.in'       => 'آیکون انتخابی معتبر نیست.',
            'form.contact_email.email'     => 'ایمیل معتبر نیست.',
            'form.contact_email.max'       => 'ایمیل حداکثر ۱۹۰ کاراکتر است.',
            'form.footer_enamad_code.max'  => 'کد اینماد حداکثر ۵٬۰۰۰ کاراکتر است.',
        ];
    }
}
