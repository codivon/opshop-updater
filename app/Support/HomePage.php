<?php

namespace App\Support;

/**
 * مرجع یکتای تنظیمات «صفحهٔ اصلی فروشگاه» (گروه home در جدول settings) — Task 56.
 *
 * - همهٔ ویجت‌های صفحهٔ اصلی (هیرو، معرفی، آمار، چرا ما، نظرات، محصولات، تماس)
 *   از پنل /admin/settings/home قابل فعال/غیرفعال‌کردن و شخصی‌سازی هستند.
 * - پیش‌فرض‌ها همان محتوای معرفی است که کاربر خواسته («از اول تا بزرگ شدن
 *   استارتاپ کنار شما هستیم») — برگرفته از رزومهٔ weblak.ir صاحب سایت.
 * - خواندن از helper سراسری setting() (کش ۶۰ ثانیه‌ای)؛ هنگام ذخیره در پنل،
 *   کش هر کلید پاک می‌شود (Cache::forget('setting:'.key)).
 */
class HomePage
{
    /** پیش‌فرض همهٔ کلیدها — مرجع مشترک پنل و ویوها */
    public const DEFAULTS = [
        /* -------------------- روشن/خاموش ویجت‌ها -------------------- */
        'home_hero_enabled' => '1',
        'home_showcase_enabled' => '1',
        'home_stats_enabled' => '1',
        'home_about_enabled' => '1',
        'home_why_enabled' => '1',
        'home_testimonials_enabled' => '1',
        'home_categories_enabled' => '1',
        'home_packages_enabled' => '1',
        'home_products_enabled' => '1',
        'home_templates_enabled' => '1',
        'home_subscription_active_enabled' => '1',
        'home_subscription_enabled' => '1',
        'home_track_enabled' => '1',
        'home_contact_enabled' => '1',
        'contact_form_enabled' => '1',

        /* -------------------- ترتیب بخش‌ها (پنل قابل جابه‌جایی) ------------ */
        'home_sort_hero' => '10',
        'home_sort_showcase' => '15',
        'home_sort_stats' => '20',
        'home_sort_about' => '30',
        'home_sort_why' => '40',
        'home_sort_testimonials' => '50',
        'home_sort_categories' => '60',
        'home_sort_packages' => '70',
        'home_sort_products' => '80',
        'home_sort_templates' => '90',
        'home_sort_subscription_active' => '95',
        'home_sort_subscription' => '100',
        'home_sort_track' => '110',
        'home_sort_contact' => '120',

        /* -------------------- اسلایدر معرفی محصول (Task 71) --------------- */
        // محتوای دستی (جدول home_slides از تب «اسلایدر معرفی» پنل) اولویت دارد؛
        // اگر اسلاید دستی فعالی نباشد، پیش‌فرض‌ها ساخته می‌شوند:
        'home_showcase_packages_enabled' => '1', // اسلایدهای خودکار از پکیج/محصول (انتخابی یا ۶ پکیج آخر)
        'home_showcase_instashop_enabled' => '1', // بنر ویژهٔ InstaShop
        'home_showcase_instashop_position' => 'first', // جایگاه بنر InstaShop: first | last
        // ترتیب اسلایدهای خودکار از پکیج/محصول — JSON مثل [{"type":"package","id":3},…]
        // خالی = رفتار قدیمی: ۶ پکیج فعالِ آخر
        'home_showcase_auto_slides' => '',
        'home_showcase_instashop_badge' => 'بیزینس آماده',
        'home_showcase_instashop_title' => 'پیج اینستاگرامت را به یک فروشگاه آنلاین تبدیل کن',
        'home_showcase_instashop_text' => 'InstaShop — پلتفرم فروش اینستاگرامی با پرداخت امن ایرانی (۵ درگاه آماده)، اپ مستقل فروشنده و خریدار، کیف پول و تسویهٔ شفاف و ارسال با پست پیشتاز؛ همه‌چیز آماده است تا فقط به فروش فکر کنی.',
        'home_showcase_instashop_link' => 'https://insta-shop.opashop.ir',
        'home_showcase_instashop_link_text' => 'مشاهدهٔ دمو',

        // رفتار و ظاهر اسلایدر (Task 73 — همه قابل شخصی‌سازی از پنل):
        'home_showcase_header_enabled' => '1',   // سربرگ بخش (برچسب + عنوان)
        'home_showcase_eyebrow' => 'ویترین محصولات',
        'home_showcase_heading' => 'محصولات ویژهٔ ما را ببینید',
        'home_showcase_autoplay' => '1',          // پخش خودکار
        'home_showcase_autoplay_speed' => '6',    // هر چند ثانیه (۳ تا ۱۵)
        'home_showcase_dots' => '1',              // دات‌های ناوبری
        'home_showcase_arrows' => '1',            // فلش‌های دسکتاپ

        /* -------------------- هیرو (معرفی بالا) -------------------- */
        'home_hero_badge' => 'آماده همکاری و پروژه جدید',
        'home_hero_title' => 'از اول تا بزرگ شدن استارتاپ کنار شما هستیم',
        'home_hero_subtitle' => 'ما تیم توسعه وب هستیم؛ بیش از ۷ سال تجربه در طراحی سایت، توسعه پروژه‌های اختصاصی و ساخت سیستم‌های تحت وب با Laravel. محصولات آماده را ببینید یا ایده‌تان را برای ساخت اختصاصی با ما در میان بگذارید.',
        'home_hero_cta_text' => 'مشاهده محصولات',
        'home_hero_cta_link' => '#shop-packages',
        'home_hero_cta2_text' => 'درخواست مشاوره',
        'home_hero_cta2_link' => '#contact',
        // دکمهٔ سوم — لینک به سایت برند (نمونه‌کارها/درباره من) — Task 62
        'home_hero_cta3_text' => 'بیشتر درباره من و نمونه‌کارها',
        'home_hero_cta3_link' => 'https://weblak.ir',

        /* -------------------- آمار اعتماد -------------------- */
        'home_stat1_value' => '+۷',
        'home_stat1_label' => 'سال تجربه',
        'home_stat2_value' => '+۱۲',
        'home_stat2_label' => 'پروژه تخصصی',
        'home_stat3_value' => '+۱۱',
        'home_stat3_label' => 'نمونه‌کار آماده',
        'home_stat4_value' => '۲۴/۷',
        'home_stat4_label' => 'پشتیبانی واقعی',

        /* -------------------- معرفی (درباره ما) -------------------- */
        'home_about_title' => 'معرفی',
        'home_about_body' => "ما بیش از ۷ سال است که سایت‌ها و برنامه‌های کاربردی تحت وب طراحی، توسعه و راه‌اندازی می‌کنیم؛ از فروشگاه‌های چندفروشندگی و پلتفرم‌های مدیریت تا چت‌بات هوش مصنوعی و قالب‌های اختصاصی.\nتخصص اصلی ما توسعه با فریم‌ورک Laravel است؛ از طراحی معماری دیتابیس و ساخت REST API تا پیاده‌سازی درگاه‌های پرداخت ایرانی و امن‌سازی اپلیکیشن‌ها بر اساس اصول OWASP.\nدر این مسیر بیش از ۱۲ پروژه تخصصی تحویل داده‌ایم و حالا کنار شما هستیم؛ از اول ایده تا بزرگ شدن استارتاپ، شریک فنی شما خواهیم بود.",
        // لینک «بیشتر/نمونه‌کارها» به سایت اصلی برند — Task 62 (متن خالی = مخفی)
        'home_about_more_text' => 'مشاهده نمونه‌کارها در وب‌لاک',
        'home_about_more_link' => 'https://weblak.ir',

        /* -------------------- چرا ما (۶ مورد) -------------------- */
        'home_why_title' => 'چرا به ما اعتماد می‌کنند؟',
        'home_why_intro' => 'این‌ها وعده‌های تبلیغاتی نیست؛ حاصل سال‌ها پروژه واقعی است.',
        'home_why1_icon' => 'code',
        'home_why1_title' => 'توسعه اختصاصی با Laravel',
        'home_why1_text' => 'سیستم‌های مقیاس‌پذیر و ماژولار؛ از REST API تا پنل مدیریت با استانداردهای روز.',
        'home_why2_icon' => 'credit-card',
        'home_why2_title' => 'درگاه‌های پرداخت ایرانی',
        'home_why2_text' => 'اتصال واقعی به زرین‌پال، دیجی‌پی، Pay.ir و ده‌ها درگاه دیگر بدون دردسر.',
        'home_why3_icon' => 'shield-check',
        'home_why3_title' => 'امنیت بر اساس OWASP',
        'home_why3_text' => 'از CSRF و XSS تا SQL Injection و Rate Limiting؛ از روز اول جدی گرفته می‌شود.',
        'home_why4_icon' => 'zap',
        'home_why4_title' => 'سرعت و بهینه‌سازی',
        'home_why4_text' => 'کش، کوئری‌های بهینه و CDN برای بارگذاری سریع صفحات و تجربه بهتر کاربر.',
        'home_why5_icon' => 'bot',
        'home_why5_title' => 'هوش مصنوعی کاربردی',
        'home_why5_text' => 'چت‌بات و ابزارهای مولد محتوا برای اتوماسیون و رشد کسب‌وکار شما.',
        'home_why6_icon' => 'heart-handshake',
        'home_why6_title' => 'پشتیبانی واقعی',
        'home_why6_text' => 'پاسخ‌گویی مستمر و همراهی بعد از تحویل؛ رضایت شما سرمایه ماست.',

        /* -------------------- نظرات مشتریان (۳ مورد) -------------------- */
        'home_testimonials_title' => 'نظرات مشتریان',
        'home_testimonial1_text' => 'معماری ماژولاری که برای اوپا شاپ طراحی شد، توسعه ماژول‌های جدید را بسیار ساده کرده است. درگاه پرداخت جدید بدون تغییر در منطق Checkout اضافه شد — دقیقاً همان چیزی که می‌خواستیم.',
        'home_testimonial1_author' => 'کارفرمای پروژه اوپا شاپ',
        'home_testimonial2_text' => 'همکاری در پروژه‌های متنوع از جمله پلتفرم حمل‌ونقل و چت‌بات هوش مصنوعی، همواره دقیق، منظم و حرفه‌ای بوده است. پروژه‌ها سریع‌تر از انتظار به نتیجه رسیدند.',
        'home_testimonial2_author' => 'مدیر فنی وب کام',
        'home_testimonial3_text' => 'پیگیری مستمر، کدنویسی تمیز و رعایت ددلاین‌ها از ویژگی‌های بارز کار در پروژه فروشگاه اینترنتی بود؛ دقیقاً طبق نیازسنجی تحویل داده شد.',
        'home_testimonial3_author' => 'مدیر پروژه — شرکت توسعه اقتصادی شهر قزوین',

        /* -------------------- تماس با ما -------------------- */
        'home_contact_title' => 'تماس با ما',
        'home_contact_intro' => 'هر ایده‌ای در سر دارید، برای ما بنویسید. فرم تماس برای سه نوع درخواست آماده است: قالب اختصاصی، پروژه اختصاصی و استارتاپ اختصاصی. فرم را پر کنید تا در سریع‌ترین زمان ممکن با شما تماس بگیریم و درباره جزئیات، زمان تحویل و هزینه صحبت کنیم.',
        'contact_type_template_desc' => 'اگر هیچ‌کدام از قالب‌های آماده دقیقاً نیاز شما را پوشش نمی‌دهند، قالب اختصاصی با هویت بصری برند شما طراحی و توسعه می‌کنیم.',
        'contact_type_project_desc' => 'سایت فروشگاهی، پنل مدیریتی، سامانه رزرو یا هر سیستم تحت وب — پروژه شما را از صفر تا تحویل و راه‌اندازی کامل انجام می‌دهیم.',
        'contact_type_startup_desc' => 'ایده‌ای برای استارتاپ دارید؟ از طراحی معماری و ساخت نسخه اول (MVP) تا توسعه و بزرگ شدن، شریک فنی شما هستیم.',
        'contact_phone' => '09190478451',
        'contact_email' => 'info@weblak.ir',
        'contact_telegram' => 'https://t.me/weblak_ir',
        'contact_address' => 'قزوین / تهران',

        /* -------------------- فوتر — اینماد -------------------- */
        'footer_enamad_code' => '',
    ];

    /** کلیدهای بولی (toggle) */
    public const BOOL_KEYS = [
        'home_hero_enabled', 'home_showcase_enabled', 'home_stats_enabled', 'home_about_enabled',
        'home_why_enabled', 'home_testimonials_enabled',
        'home_categories_enabled',
        'home_packages_enabled', 'home_products_enabled', 'home_templates_enabled',
        'home_subscription_active_enabled', 'home_subscription_enabled', 'home_contact_enabled', 'contact_form_enabled',
        'home_track_enabled',
        'home_showcase_packages_enabled', 'home_showcase_instashop_enabled',
        'home_showcase_header_enabled', 'home_showcase_autoplay',
        'home_showcase_dots', 'home_showcase_arrows',
    ];

    /** بخش‌های صفحهٔ اصلی (کلید کوتاه) — به ترتیب پیش‌فرض؛ ترتیب واقعی از home_sort_* خوانده می‌شود */
    public const SECTION_KEYS = [
        'hero', 'showcase', 'stats', 'about', 'why', 'testimonials', 'categories',
        'packages', 'products', 'templates', 'subscription_active', 'subscription', 'track', 'contact',
    ];

    /** گروه جدول settings برای همهٔ کلیدهای این ماژول */
    public static function group(string $key): string
    {
        return 'home';
    }

    /** جایگاه‌های مجاز بنر InstaShop در اسلایدهای خودکار (کلید => برچسب) */
    public const INSTA_POSITIONS = [
        'first' => 'اول — بالای اسلایدهای پکیج/محصول',
        'last' => 'آخر — پایین اسلایدهای پکیج/محصول',
    ];

    /* --------------------------------------------------------------- */
    /*  خواندن (برای استفاده در ویوها و ویجت‌ها) */
    /* --------------------------------------------------------------- */

    /** مقدار متنی یک کلید (trim شده با پیش‌فرض) */
    public static function str(string $key): string
    {
        return trim((string) setting($key, self::DEFAULTS[$key] ?? ''));
    }

    /** آیا ویجت روشن است؟ */
    public static function on(string $key): bool
    {
        return filter_var((string) setting($key, self::DEFAULTS[$key] ?? '0'), FILTER_VALIDATE_BOOLEAN);
    }

    /** کلیدهای بخش‌ها به ترتیب ذخیره‌شدهٔ پنل (home_sort_*؛ هرچه کوچک‌تر، بالاتر) */
    public static function orderedSections(): array
    {
        $sections = [];

        foreach (self::SECTION_KEYS as $key) {
            $sections[$key] = (int) setting('home_sort_'.$key, self::DEFAULTS['home_sort_'.$key] ?? '0');
        }

        asort($sections); // PHP 8+ پایدار است — ترتیب برابر = ترتیب پیش‌فرض

        return array_keys($sections);
    }

    /** کد HTML اینماد فوتر (خالی = نمایش داده نمی‌شود) */
    public static function enamadCode(): string
    {
        return self::str('footer_enamad_code');
    }

    /** آیتم‌های «چرا ما» به‌صورت آرایه — فقط موارد پُر */
    public static function whyItems(): array
    {
        $items = [];

        foreach (range(1, 6) as $i) {
            $title = self::str("home_why{$i}_title");
            $text = self::str("home_why{$i}_text");

            if ($title === '' && $text === '') {
                continue;
            }

            $items[] = [
                'icon' => self::str("home_why{$i}_icon") ?: 'sparkles',
                'title' => $title,
                'text' => $text,
            ];
        }

        return $items;
    }

    /** نظرات مشتریان به‌صورت آرایه — فقط موارد پُر */
    public static function testimonials(): array
    {
        $items = [];

        foreach (range(1, 3) as $i) {
            $text = self::str("home_testimonial{$i}_text");
            $author = self::str("home_testimonial{$i}_author");

            if ($text === '') {
                continue;
            }

            $items[] = ['text' => $text, 'author' => $author];
        }

        return $items;
    }

    /** آمار اعتماد به‌صورت آرایه — فقط موارد پُر */
    public static function stats(): array
    {
        $items = [];

        foreach (range(1, 4) as $i) {
            $value = self::str("home_stat{$i}_value");
            $label = self::str("home_stat{$i}_label");

            if ($value === '' && $label === '') {
                continue;
            }

            $items[] = ['value' => $value, 'label' => $label];
        }

        return $items;
    }
}
