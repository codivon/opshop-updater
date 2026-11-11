<?php

namespace App\Livewire\Settings;

use App\Livewire\Concerns\WithToasts;
use App\Models\HomeSlide;
use App\Models\Setting;
use App\Services\ImageUploadService;
use App\Support\HomePage as HomeSettings;
use App\Support\ShopMenu;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * پنل «صفحهٔ اصلی فروشگاه» (/admin/settings/home) — Task 56:
 * همهٔ ویجت‌های صفحهٔ اصلی (هیرو، آمار اعتماد، معرفی، چرا ما، نظرات،
 * پکیج/پروژه/قالب، اشتراک، تماس با ما) و کد اینماد فوتر از همین صفحه
 * فعال/غیرفعال و شخصی‌سازی می‌شوند.
 * Task 60: ترتیب بخش‌ها (home_sort_*) + تب «بخش‌ها» با فلش بالا/پایین.
 * Task 61: تب «منوی هدر» — ترتیب و روشن/خاموشی لینک‌های ناوبری فروشگاه (گروه menu).
 * Task 62: دکمهٔ سوم هیرو (سایت برند weblak.ir) + لینک «نمونه‌کارها» در بخش معرفی.
 * Task 71: تب «اسلایدر معرفی» — CRUD اسلایدها (تصویر/متن/لینک + ترتیب/فعال) +
 *   کلیدهای پیش‌فرض (بنر خودکار پکیج‌ها + بنر ویژهٔ InstaShop).
 *   ⚠️ برخلاف toggle/ترتیب بخش‌ها، اکشن‌های اسلاید بلافاصله در دیتابیس ذخیره
 *   می‌شوند (بدون نیاز به «ذخیره تنظیمات»).
 *
 * الگو: مطابق Settings\Security — فرم $form + حلقهٔ ذخیره + پاک‌سازی کش هر کلید.
 */
#[Layout('components.layouts.app')]
#[Title('صفحهٔ اصلی فروشگاه')]
class HomePage extends Component
{
    use WithFileUploads;
    use WithToasts;

    public array $form = [];

    /** ماژول‌های تنظیماتی این پنل (هرکدام DEFAULTS + BOOL_KEYS + group() دارند) */
    private function modules(): array
    {
        return [HomeSettings::class, ShopMenu::class];
    }

    /** همهٔ کلیدهای بولی هر دو ماژول */
    private function boolKeys(): array
    {
        return array_merge(HomeSettings::BOOL_KEYS, ShopMenu::BOOL_KEYS);
    }

    public function mount(): void
    {
        // پیش‌فرض‌ها + مقادیر ذخیره‌شده (دو ماژول: صفحهٔ اصلی + منوی هدر)
        foreach ($this->modules() as $module) {
            foreach ($module::DEFAULTS as $key => $default) {
                $this->form[$key] = $default;
            }
        }

        $stored = Setting::query()
            ->whereIn('group', ['home', 'menu'])
            ->pluck('value', 'key');

        $boolKeys = $this->boolKeys();

        foreach ($stored as $key => $value) {
            if (array_key_exists($key, $this->form)) {
                $this->form[$key] = in_array($key, $boolKeys, true)
                    ? (bool) filter_var($value, FILTER_VALIDATE_BOOLEAN)
                    : (string) $value;
            }
        }
    }

    public function save(): void
    {
        $this->validate();

        $boolKeys = $this->boolKeys();

        foreach ($this->modules() as $module) {
            foreach ($module::DEFAULTS as $key => $default) {
                if (! array_key_exists($key, $this->form)) {
                    continue;
                }

                $value = $this->form[$key];

                if (in_array($key, $boolKeys, true)) {
                    $value = $value ? '1' : '0';
                }

                Setting::set($key, (string) $value, 'string', $module::group($key));
            }

            // پاک‌سازی کش تنظیمات (helper setting از کش ۶۰ثانیه‌ای می‌خواند)
            foreach (array_keys($module::DEFAULTS) as $key) {
                Cache::forget('setting:'.$key);
            }
        }

        Cache::forget('settings');

        $this->toast('تنظیمات صفحهٔ اصلی ذخیره شد.');
    }

    /** فهرست آیکون‌های مجاز برای «چرا ما» (کلید => برچسب) */
    public function getIconOptionsProperty(): array
    {
        return [
            'code' => 'کد (توسعه)',
            'credit-card' => 'کارت بانکی (پرداخت)',
            'shield-check' => 'سپر تیک‌دار (امنیت)',
            'zap' => 'آذرخش (سرعت)',
            'bot' => 'ربات (هوش مصنوعی)',
            'heart-handshake' => 'قلب و دست (پشتیبانی)',
            'users' => 'کاربران (تیم)',
            'sparkles' => 'ستاره (ویژه)',
            'rocket' => 'موشک (رشد)',
            'monitor' => 'مانیتور (نمایش)',
            'globe' => 'کره زمین (وب)',
            'wrench' => 'آچار (فنی)',
            'database' => 'دیتابیس',
            'lightbulb' => 'لامپ (ایده)',
        ];
    }

    /** برچسب و آیکون هر بخش (کلید کوتاه => [برچسب، آیکون]) */
    public const SECTION_META = [
        'hero' => ['هیرو (معرفی بالا)', 'sparkles'],
        'showcase' => ['اسلایدر معرفی', 'image'],
        'stats' => ['آمار اعتماد', 'bar-chart'],
        'about' => ['معرفی (درباره ما)', 'users'],
        'why' => ['چرا ما', 'badge-check'],
        'testimonials' => ['نظرات مشتریان', 'quote'],
        'categories' => ['دسته‌بندی محصولات', 'folder-tree'],
        'packages' => ['پکیج‌ها (محصولات)', 'package'],
        'products' => ['پروژه‌های آماده', 'rocket'],
        'templates' => ['قالب‌های آماده', 'layout-template'],
        'subscription_active' => ['اشتراک فعال (وضعیت/بررسی کد)', 'user-check'],
        'subscription' => ['اشتراک‌ها', 'crown'],
        'track' => ['پیگیری خرید مهمان', 'search'],
        'contact' => ['تماس با ما', 'message-circle'],
    ];

    /** ردیف‌های بخش‌ها به ترتیب فعلی فرم (home_sort_*) برای تب «بخش‌ها» */
    public function getSectionRowsProperty(): array
    {
        $rows = [];

        foreach (HomeSettings::SECTION_KEYS as $key) {
            [$label, $icon] = self::SECTION_META[$key];

            $rows[] = [
                'key' => $key,
                'label' => $label,
                'icon' => $icon,
                'sort' => (int) ($this->form['home_sort_'.$key] ?? 0),
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
        $keys = array_column($this->sectionRows, 'key');
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
        $this->form['home_sort_'.$key] = $this->form['home_sort_'.$other];
        $this->form['home_sort_'.$other] = $tmp;

        // رفع باگ «فلش‌ها کار نمی‌کنند»: computed sectionRows در همین درخواست کش
        // شده بود (requestCachedValue) و رندر بعدی ترتیب کهنه را نشان می‌داد؛
        // کش computed باید پاک شود تا لیست با مقادیر جدید فرم دوباره ساخته شود.
        unset($this->sectionRows);
    }

    /* ------------------------------------------------------------------ */
    /*  منوی هدر فروشگاه (گروه menu) — Task 61 */
    /* ------------------------------------------------------------------ */

    /** ردیف‌های منوی هدر به ترتیب فعلی فرم (menu_sort_*) برای تب «منوی هدر» */
    public function getMenuRowsProperty(): array
    {
        $rows = [];

        foreach (ShopMenu::keys() as $key) {
            $rows[] = [
                'key' => $key,
                'label' => ShopMenu::ITEMS[$key]['label'],
                'icon' => ShopMenu::ITEMS[$key]['icon'],
                'sort' => (int) ($this->form['menu_sort_'.$key] ?? 0),
            ];
        }

        usort($rows, fn ($a, $b) => $a['sort'] <=> $b['sort']);

        return $rows;
    }

    /** جابه‌جایی یک آیتم منو یک ردیف به بالا (فرم ذخیره‌نشده — با «ذخیره تنظیمات» اعمال می‌شود) */
    public function moveMenuUp(string $key): void
    {
        $this->moveMenu($key, -1);
    }

    /** جابه‌جایی یک آیتم منو یک ردیف به پایین */
    public function moveMenuDown(string $key): void
    {
        $this->moveMenu($key, +1);
    }

    private function moveMenu(string $key, int $direction): void
    {
        $keys = array_column($this->menuRows, 'key');
        $index = array_search($key, $keys, true);

        if ($index === false) {
            return;
        }

        $neighbor = $index + $direction;

        if ($neighbor < 0 || $neighbor >= count($keys)) {
            return;
        }

        $other = $keys[$neighbor];

        $tmp = $this->form['menu_sort_'.$key];
        $this->form['menu_sort_'.$key] = $this->form['menu_sort_'.$other];
        $this->form['menu_sort_'.$other] = $tmp;

        // آموزهٔ Task 60: کش computed باید پاک شود وگرنه رندر ترتیب کهنه را نشان می‌دهد
        unset($this->menuRows);
    }

    /* ------------------------------------------------------------------ */
    /*  اسلایدهای «اسلایدر معرفی» (جدول home_slides) — Task 71 */
    /*  ذخیرهٔ این بخش لحظه‌ای است (بدون «ذخیره تنظیمات») */
    /* ------------------------------------------------------------------ */

    /** فرم اسلاید در حال افزودن/ویرایش */
    public array $slide = [];

    /** تصویر آپلودی اسلاید در حال ویرایش */
    /** @var TemporaryUploadedFile|null */
    public $slideImage;

    public ?int $editingSlideId = null;

    public bool $showSlideForm = false;

    /** همهٔ اسلایدها (فعال+غیرفعال) به ترتیب پنل — برای تب اسلایدر */
    #[Computed]
    public function slides()
    {
        return HomeSlide::query()->orderBy('sort_order')->orderBy('id')->get();
    }

    /** فرم جدید (افزودن اسلاید) */
    public function newSlide(): void
    {
        $this->editingSlideId = null;
        $this->slideImage = null;
        $this->slide = [
            'badge' => '',
            'title' => '',
            'text' => '',
            'link' => '',
            'link_text' => '',
            'content_align' => 'start',
            'content_vpos' => 'center',
            'button_style' => 'solid',
            'overlay' => null,
            'new_tab' => false,
            'is_active' => true,
            'sort_order' => (int) HomeSlide::query()->max('sort_order') + 1,
        ];
        $this->showSlideForm = true;
    }

    /** ویرایش اسلاید موجود */
    public function editSlide(int $id): void
    {
        $slide = HomeSlide::query()->findOrFail($id);

        $this->editingSlideId = $slide->id;
        $this->slideImage = null;
        $this->slide = [
            'badge' => (string) $slide->badge,
            'title' => (string) $slide->title,
            'text' => (string) $slide->text,
            'link' => (string) $slide->link,
            'link_text' => (string) $slide->link_text,
            'content_align' => (string) $slide->content_align,
            'content_vpos' => (string) $slide->content_vpos,
            'button_style' => (string) $slide->button_style,
            'overlay' => $slide->overlay,
            'new_tab' => $slide->new_tab,
            'is_active' => $slide->is_active,
            'sort_order' => (int) $slide->sort_order,
        ];
        $this->showSlideForm = true;
    }

    public function closeSlideForm(): void
    {
        $this->showSlideForm = false;
        $this->editingSlideId = null;
        $this->slideImage = null;
        $this->resetErrorBag('slide');
        $this->resetErrorBag('slideImage');
    }

    /** ذخیرهٔ اسلاید (افزودن/ویرایش) — بلافاصله در دیتابیس */
    public function saveSlide(): void
    {
        $this->validate($this->slideRules(), $this->slideMessages());

        $overlay = $this->slide['overlay'] ?? null;

        $data = [
            'badge' => trim((string) ($this->slide['badge'] ?? '')) ?: null,
            'title' => trim((string) $this->slide['title']),
            'text' => trim((string) ($this->slide['text'] ?? '')) ?: null,
            'link' => trim((string) ($this->slide['link'] ?? '')) ?: null,
            'link_text' => trim((string) ($this->slide['link_text'] ?? '')) ?: null,
            'content_align' => array_key_exists((string) ($this->slide['content_align'] ?? ''), HomeSlide::ALIGNS)
                ? $this->slide['content_align'] : 'start',
            'content_vpos' => array_key_exists((string) ($this->slide['content_vpos'] ?? ''), HomeSlide::VPOSS)
                ? $this->slide['content_vpos'] : 'center',
            'button_style' => array_key_exists((string) ($this->slide['button_style'] ?? ''), HomeSlide::BUTTON_STYLES)
                ? $this->slide['button_style'] : 'solid',
            'overlay' => $overlay === null || $overlay === '' ? null : max(0, min(HomeSlide::OVERLAY_MAX, (int) $overlay)),
            'new_tab' => (bool) ($this->slide['new_tab'] ?? false),
            'is_active' => (bool) ($this->slide['is_active'] ?? true),
            'sort_order' => max(1, (int) ($this->slide['sort_order'] ?? 1)),
        ];

        $imageService = app(ImageUploadService::class);

        if ($this->slideImage) {
            // جایگزینی تصویر قبلی (در ویرایش)
            if ($this->editingSlideId) {
                $old = HomeSlide::query()->find($this->editingSlideId);

                if ($old?->image) {
                    $imageService->deleteFile($old->image);
                }
            }

            $data['image'] = $imageService->uploadShowcaseImage($this->slideImage);
        }

        if ($this->editingSlideId) {
            HomeSlide::query()->whereKey($this->editingSlideId)->update($data);
            $this->toast('اسلاید ویرایش شد.');
        } else {
            // «جایگاه» خواسته‌شدهٔ مدیر: در افزودن هم قابل تعیین است؛
            // پیش‌فرض = آخرِ لیست (بزرگ‌ترین sort_order + ۱)
            $data['sort_order'] ??= (int) HomeSlide::query()->max('sort_order') + 1;
            HomeSlide::query()->create($data);
            $this->toast('اسلاید اضافه شد.');
        }

        // بازنویسی صعودی sort_order تا جایگاه‌های تکراری/شکاف‌دار یکدست شوند
        foreach (HomeSlide::query()->orderBy('sort_order')->orderBy('id')->get() as $i => $slide) {
            if ($slide->sort_order !== $i + 1) {
                $slide->update(['sort_order' => $i + 1]);
            }
        }

        $this->closeSlideForm();
        unset($this->slides);
    }

    /** حذف اسلاید + فایل تصویرش */
    public function deleteSlide(int $id): void
    {
        $slide = HomeSlide::query()->findOrFail($id);

        if ($slide->image) {
            app(ImageUploadService::class)->deleteFile($slide->image);
        }

        $slide->delete();

        unset($this->slides);
        $this->toast('اسلاید حذف شد.');
    }

    /** فعال/غیرفعال‌کردن لحظه‌ای */
    public function toggleSlide(int $id): void
    {
        $slide = HomeSlide::query()->findOrFail($id);
        $slide->update(['is_active' => ! $slide->is_active]);

        unset($this->slides);
    }

    /** جابه‌جایی ترتیب اسلایدها — ذخیرهٔ فوری در دیتابیس */
    public function moveSlide(int $id, string $direction): void
    {
        $ordered = HomeSlide::query()->orderBy('sort_order')->orderBy('id')->get();
        $index = $ordered->search(fn ($s) => $s->id === $id);

        if ($index === false) {
            return;
        }

        $neighborIndex = $direction === 'up' ? $index - 1 : $index + 1;

        if ($neighborIndex < 0 || $neighborIndex >= $ordered->count()) {
            return; // بالاترین/پایین‌ترین ردیف — حرکتی نیست
        }

        $current = $ordered[$index];
        $neighbor = $ordered[$neighborIndex];

        HomeSlide::query()->whereKey($current->id)->update(['sort_order' => $neighbor->sort_order]);
        HomeSlide::query()->whereKey($neighbor->id)->update(['sort_order' => $current->sort_order]);

        // بازنویسی صعودی کامل — جلوگیری از ترتیب برابر بعد از جابه‌جایی
        foreach (HomeSlide::query()->orderBy('sort_order')->orderBy('id')->get() as $i => $slide) {
            $slide->update(['sort_order' => $i + 1]);
        }

        unset($this->slides);
    }

    private function slideRules(): array
    {
        return [
            'slide.badge' => ['nullable', 'string', 'max:50'],
            'slide.title' => ['required', 'string', 'max:150'],
            'slide.text' => ['nullable', 'string', 'max:500'],
            'slide.link' => ['nullable', 'string', 'max:300'],
            'slide.link_text' => ['nullable', 'string', 'max:50'],
            'slide.content_align' => ['nullable', 'in:'.implode(',', array_keys(HomeSlide::ALIGNS))],
            'slide.content_vpos' => ['nullable', 'in:'.implode(',', array_keys(HomeSlide::VPOSS))],
            'slide.button_style' => ['nullable', 'in:'.implode(',', array_keys(HomeSlide::BUTTON_STYLES))],
            'slide.overlay' => ['nullable', 'integer', 'min:0', 'max:'.HomeSlide::OVERLAY_MAX],
            'slide.new_tab' => ['nullable', 'boolean'],
            'slide.is_active' => ['nullable', 'boolean'],
            'slide.sort_order' => ['nullable', 'integer', 'min:1', 'max:999'],
            'slideImage' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:3072'],
        ];
    }

    private function slideMessages(): array
    {
        return [
            'slide.title.required' => 'عنوان اسلاید الزامی است.',
            'slide.title.max' => 'عنوان اسلاید حداکثر ۱۵۰ کاراکتر است.',
            'slide.badge.max' => 'نشان حداکثر ۵۰ کاراکتر است.',
            'slide.text.max' => 'متن اسلاید حداکثر ۵۰۰ کاراکتر است.',
            'slide.link.max' => 'لینک حداکثر ۳۰۰ کاراکتر است.',
            'slide.link_text.max' => 'متن دکمه حداکثر ۵۰ کاراکتر است.',
            'slide.content_align.in' => 'محل افقی محتوا معتبر نیست.',
            'slide.content_vpos.in' => 'محل عمودی محتوا معتبر نیست.',
            'slide.button_style.in' => 'استایل دکمه معتبر نیست.',
            'slide.overlay.integer' => 'شدت تیرگی باید عدد باشد.',
            'slide.overlay.max' => 'شدت تیرگی حداکثر ۹۰ درصد است.',
            'slide.sort_order.integer' => 'جایگاه اسلاید باید عدد باشد.',
            'slide.sort_order.min' => 'جایگاه اسلاید حداقل ۱ است.',
            'slideImage.image' => 'فایل انتخابی تصویر نیست.',
            'slideImage.mimes' => 'فرمت تصویر مجاز نیست؛ فقط JPG، PNG، WEBP یا GIF.',
            'slideImage.max' => 'حجم تصویر حداکثر ۳ مگابایت است.',
        ];
    }

    public function render()
    {
        return view('livewire.settings.home');
    }

    /* ------------------------------------------------------------------ */
    /*  قواعد اعتبارسنجی */
    /* ------------------------------------------------------------------ */

    protected function rules(): array
    {
        $max = fn (int $chars) => ['nullable', 'string', 'max:'.$chars];

        $rules = [
            // هیرو
            'form.home_hero_badge' => $max(100),
            'form.home_hero_title' => ['nullable', 'string', 'max:200'],
            'form.home_hero_subtitle' => $max(500),
            'form.home_hero_cta_text' => $max(60),
            'form.home_hero_cta_link' => $max(300),
            'form.home_hero_cta2_text' => $max(60),
            'form.home_hero_cta2_link' => $max(300),
            'form.home_hero_cta3_text' => $max(60),
            'form.home_hero_cta3_link' => $max(300),

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
            'form.home_about_body' => ['nullable', 'string', 'max:5000'],
            'form.home_about_more_text' => $max(60),
            'form.home_about_more_link' => $max(300),

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
            'form.home_testimonial1_text' => $max(1000),
            'form.home_testimonial1_author' => $max(120),
            'form.home_testimonial2_text' => $max(1000),
            'form.home_testimonial2_author' => $max(120),
            'form.home_testimonial3_text' => $max(1000),
            'form.home_testimonial3_author' => $max(120),

            // تماس
            'form.home_contact_title' => $max(120),
            'form.home_contact_intro' => $max(2000),
            'form.contact_type_template_desc' => $max(500),
            'form.contact_type_project_desc' => $max(500),
            'form.contact_type_startup_desc' => $max(500),
            'form.contact_phone' => $max(20),
            'form.contact_email' => ['nullable', 'email', 'max:190'],
            'form.contact_telegram' => $max(300),
            'form.contact_address' => $max(200),

            // اسلایدر معرفی — پیش‌فرض‌ها (متن‌های بنر InstaShop)
            'form.home_showcase_instashop_badge' => $max(50),
            'form.home_showcase_instashop_title' => ['nullable', 'string', 'max:150'],
            'form.home_showcase_instashop_text' => $max(500),
            'form.home_showcase_instashop_link' => $max(300),
            'form.home_showcase_instashop_link_text' => $max(50),

            // اسلایدر معرفی — رفتار و ظاهر (Task 73)
            'form.home_showcase_eyebrow' => $max(60),
            'form.home_showcase_heading' => $max(120),
            'form.home_showcase_autoplay_speed' => ['required', 'integer', 'min:3', 'max:15'],

            // فوتر — اینماد
            'form.footer_enamad_code' => ['nullable', 'string', 'max:5000'],
        ];

        // ترتیب بخش‌ها — عدد مثبت؛ از فلش‌های پنل تغییر می‌کند
        foreach (HomeSettings::SECTION_KEYS as $key) {
            $rules['form.home_sort_'.$key] = ['required', 'integer', 'min:1', 'max:999'];
        }

        // ترتیب آیتم‌های منوی هدر — عدد مثبت؛ از فلش‌های پنل تغییر می‌کند
        foreach (ShopMenu::keys() as $key) {
            $rules['form.menu_sort_'.$key] = ['required', 'integer', 'min:1', 'max:999'];
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'form.home_hero_title.max' => 'عنوان اصلی حداکثر ۲۰۰ کاراکتر است.',
            'form.home_hero_subtitle.max' => 'توضیح هیرو حداکثر ۵۰۰ کاراکتر است.',
            'form.home_about_body.max' => 'متن معرفی حداکثر ۵٬۰۰۰ کاراکتر است.',
            'form.home_why1_icon.in' => 'آیکون انتخابی معتبر نیست.',
            'form.home_why2_icon.in' => 'آیکون انتخابی معتبر نیست.',
            'form.home_why3_icon.in' => 'آیکون انتخابی معتبر نیست.',
            'form.home_why4_icon.in' => 'آیکون انتخابی معتبر نیست.',
            'form.home_why5_icon.in' => 'آیکون انتخابی معتبر نیست.',
            'form.home_why6_icon.in' => 'آیکون انتخابی معتبر نیست.',
            'form.contact_email.email' => 'ایمیل معتبر نیست.',
            'form.contact_email.max' => 'ایمیل حداکثر ۱۹۰ کاراکتر است.',
            'form.footer_enamad_code.max' => 'کد اینماد حداکثر ۵٬۰۰۰ کاراکتر است.',
            'form.home_showcase_eyebrow.max' => 'برچسب سربرگ اسلایدر حداکثر ۶۰ کاراکتر است.',
            'form.home_showcase_heading.max' => 'عنوان سربرگ اسلایدر حداکثر ۱۲۰ کاراکتر است.',
            'form.home_showcase_autoplay_speed.required' => 'سرعت پخش خودکار الزامی است.',
            'form.home_showcase_autoplay_speed.integer' => 'سرعت پخش خودکار باید عدد باشد.',
            'form.home_showcase_autoplay_speed.min' => 'سرعت پخش خودکار حداقل ۳ ثانیه است.',
            'form.home_showcase_autoplay_speed.max' => 'سرعت پخش خودکار حداکثر ۱۵ ثانیه است.',
        ];
    }
}
