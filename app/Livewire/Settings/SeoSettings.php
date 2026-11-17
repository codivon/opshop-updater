<?php

namespace App\Livewire\Settings;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Livewire\Concerns\WithToasts;
use App\Models\Setting;
use App\Support\Seo;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * پنل «سئو» (/admin/settings/seo — Task 82):
 * تنظیمات کامل سئوی سایت — قالب عنوان، توضیح پیش‌فرض، سئوی اختصاصی هر صفحه،
 * تأیید مالکیت (Search Console و...)، خزنده‌های هوش مصنوعی، سایت‌مپ و llms.txt.
 *
 * متاها در لایوت فروشگاه از App\Support\Seo::meta() خوانده می‌شوند؛
 * robots.txt / sitemap.xml / llms.txt هم پویا از همین تنظیمات ساخته می‌شوند.
 * سئوی هر پکیج/محصول/قالب جداگانه در فرم خودِ آن رکورد پر می‌شود.
 */
#[Layout('components.layouts.app')]
#[Title('سئو')]
class SeoSettings extends Component
{
    use AuthorizesAdmin;
    use WithToasts;

    public array $form = [];

    public function mount(): void
    {
        foreach (Seo::DEFAULTS as $key => $default) {
            $this->form[$key] = $default;
        }

        $stored = Setting::query()->where('group', 'seo')->pluck('value', 'key');

        foreach ($stored as $key => $value) {
            if (array_key_exists($key, $this->form)) {
                $this->form[$key] = (string) $value;
            }
        }
    }

    public function save(): void
    {
        $this->validate();

        foreach (Seo::DEFAULTS as $key => $default) {
            if (! array_key_exists($key, $this->form)) {
                continue;
            }

            $value = in_array($key, self::BOOL_KEYS, true)
                ? ($this->form[$key] ? '1' : '0')
                : (string) $this->form[$key];

            Setting::set($key, $value, 'string', Seo::group($key));
        }

        foreach (array_keys(Seo::DEFAULTS) as $key) {
            Cache::forget('setting:'.$key);
        }

        Cache::forget('settings');
        Cache::forget('seo:sitemap');

        $this->toast('تنظیمات سئو ذخیره شد — متاها، robots.txt و سایت‌مپ بلافاصله به‌روز می‌شوند.');
    }

    /** کلیدهای بولی پنل سئو */
    public const BOOL_KEYS = [
        'seo_ai_gptbot', 'seo_ai_claudebot', 'seo_ai_claudeweb', 'seo_ai_perplexitybot',
        'seo_ai_googleextended', 'seo_ai_metaexternalagent', 'seo_ai_applebotextended',
        'seo_ai_bytespider', 'seo_ai_ccbot',
        'seo_sitemap_enabled', 'seo_llms_enabled', 'seo_schema_enabled',
    ];

    protected function rules(): array
    {
        $max = fn (int $chars) => ['nullable', 'string', 'max:'.$chars];

        return [
            'form.seo_title_template' => ['required', 'string', 'max:120'],
            'form.seo_default_desc' => $max(500),
            'form.seo_site_desc' => $max(500),
            'form.seo_keywords' => $max(500),
            'form.seo_og_image' => $max(500),
            'form.seo_canonical_host' => ['nullable', 'string', 'max:190', 'regex:#^https?://|^$#'],
            'form.seo_twitter_handle' => $max(60),

            'form.seo_verif_google' => $max(200),
            'form.seo_verif_bing' => $max(200),
            'form.seo_verif_yandex' => $max(200),

            'form.seo_page_home_title' => $max(120),
            'form.seo_page_home_desc' => $max(500),
            'form.seo_page_packages_title' => $max(120),
            'form.seo_page_packages_desc' => $max(500),
            'form.seo_page_products_title' => $max(120),
            'form.seo_page_products_desc' => $max(500),
            'form.seo_page_templates_title' => $max(120),
            'form.seo_page_templates_desc' => $max(500),
            'form.seo_page_subscriptions_title' => $max(120),
            'form.seo_page_subscriptions_desc' => $max(500),
            'form.seo_page_changelog_title' => $max(120),
            'form.seo_page_changelog_desc' => $max(500),

            'form.seo_llms_text' => $max(10000),
        ];
    }

    protected function messages(): array
    {
        return [
            'form.seo_title_template.required' => 'قالب عنوان سایت الزامی است.',
            'form.seo_title_template.max' => 'قالب عنوان حداکثر ۱۲۰ کاراکتر است.',
            'form.seo_canonical_host.regex' => 'دامنهٔ canonical باید با http:// یا https:// شروع شود یا خالی باشد.',
        ];
    }

    public function render()
    {
        return view('livewire.settings.seo');
    }
}
