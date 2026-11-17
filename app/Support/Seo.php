<?php

namespace App\Support;

use App\Models\Package;
use App\Models\Product;
use App\Models\Template;

/**
 * سیستم سئوی مرکزی سایت (Task 82).
 *
 * همهٔ متاها (title/description/canonical/robots/OG/Twitter/JSON-LD) از همین
 * کلاس برای هر درخواست محاسبه می‌شوند و در لایوت فروشگاه به‌صورت سرورساید
 * رندر می‌شوند — خزنده‌ها (گوگل و موتورهای هوش مصنوعی) HTML کامل هر URL را
 * با متاهای درست می‌گیرند و مشکل «صفحات SPA» منتفی است؛ در مرورگر هم اسکریپت
 * مکملِ لایوت، متاها را هنگام ناوبری wire:navigate به‌روز می‌کند.
 *
 * تنظیمات از جدول settings (گروه seo) خوانده می‌شود و از پنل «سئو»
 * (/admin/settings/seo) مدیریت می‌شود؛ به‌علاوه هر پکیج/محصول/قالب فیلدهای
 * اختصاصی meta_title / meta_description دارد که بر پیش‌فرض‌ها اولویت دارند.
 */
class Seo
{
    /** پیش‌فرض‌های تنظیمات سئو (کلید => مقدار) */
    public const DEFAULTS = [
        /* ---------- عمومی ---------- */
        'seo_title_template' => '%page% · %site%',
        'seo_default_desc' => 'خرید و دانلود پکیج، پروژه و قالب آماده — بدون ثبت‌نام، دانلود فوری پس از پرداخت.',
        'seo_site_desc' => '',
        'seo_keywords' => '',
        'seo_og_image' => '',
        'seo_canonical_host' => '',
        'seo_twitter_handle' => '',

        /* ---------- تأیید مالکیت (ابزارهای وبمستران) ---------- */
        'seo_verif_google' => '',
        'seo_verif_bing' => '',
        'seo_verif_yandex' => '',

        /* ---------- سئوی اختصاصی هر صفحه (خالی = پیش‌فرض هوشمند) ---------- */
        'seo_page_home_title' => '',
        'seo_page_home_desc' => '',
        'seo_page_packages_title' => '',
        'seo_page_packages_desc' => '',
        'seo_page_products_title' => '',
        'seo_page_products_desc' => '',
        'seo_page_templates_title' => '',
        'seo_page_templates_desc' => '',
        'seo_page_subscriptions_title' => '',
        'seo_page_subscriptions_desc' => '',
        'seo_page_changelog_title' => '',
        'seo_page_changelog_desc' => '',

        /* ---------- خزنده‌های هوش مصنوعی (هدف: رتبه در جستجوی AI → پیش‌فرض مجاز) ---------- */
        'seo_ai_gptbot' => '1',
        'seo_ai_claudebot' => '1',
        'seo_ai_claudeweb' => '1',
        'seo_ai_perplexitybot' => '1',
        'seo_ai_googleextended' => '1',
        'seo_ai_metaexternalagent' => '1',
        'seo_ai_applebotextended' => '1',
        'seo_ai_bytespider' => '1',
        'seo_ai_ccbot' => '1',

        /* ---------- ابزارها ---------- */
        'seo_sitemap_enabled' => '1',
        'seo_llms_enabled' => '1',
        'seo_llms_text' => '',
        'seo_schema_enabled' => '1',
    ];

    /** خزنده‌های هوش مصنوعی: کلید تنظیمات => نام User-agent در robots.txt */
    public const AI_BOTS = [
        'seo_ai_gptbot' => 'GPTBot',
        'seo_ai_claudebot' => 'ClaudeBot',
        'seo_ai_claudeweb' => 'Claude-Web',
        'seo_ai_perplexitybot' => 'PerplexityBot',
        'seo_ai_googleextended' => 'Google-Extended',
        'seo_ai_metaexternalagent' => 'Meta-ExternalAgent',
        'seo_ai_applebotextended' => 'Applebot-Extended',
        'seo_ai_bytespider' => 'Bytespider',
        'seo_ai_ccbot' => 'CCBot',
    ];

    /** متن پیش‌فرض توضیح هر صفحه (کلید روت => توضیح) */
    private const PAGE_DEFAULTS = [
        'shop.home' => ['فروشگاه', 'خرید و دانلود پکیج، پروژه و قالب آماده — بدون ثبت‌نام، دانلود فوری پس از پرداخت.'],
        'shop.packages' => ['پکیج‌ها', 'فهرست کامل پکیج‌های آماده با قیمت و امکانات — خرید مستقیم و بدون ثبت‌نام؛ پس از پرداخت همان‌جا فایل را دانلود کنید.'],
        'shop.products' => ['پروژه‌های آماده', 'پروژه‌های آمادهٔ کامل برای شروع سریع کار — خرید مستقیم بدون واسطه و دانلود فوری فایل پروژه پس از پرداخت.'],
        'shop.templates' => ['قالب‌های آماده', 'قالب‌های آمادهٔ حرفه‌ای برای سایت شما — خرید مستقیم بدون ثبت‌نام و دانلود فوری فایل قالب پس از پرداخت.'],
        'shop.subscriptions' => ['طرح‌های اشتراک', 'اشتراک‌های دوره‌ای برای دسترسی به آپدیت‌های همهٔ پکیج‌ها — طرح‌های ماهانه، سه‌ماهه و سالانه.'],
        'shop.changelog' => ['تاریخچه تغییرات', 'آخرین نسخه‌ها و تغییرات پکیج‌ها — به‌روزرسانی‌های جدید و گزارش کامل تغییرات هر نسخه.'],
    ];

    public static function group(string $key): string
    {
        return 'seo';
    }

    /** مقدار متنی یک کلید (trim شده با پیش‌فرض) */
    public static function str(string $key): string
    {
        return trim((string) setting($key, self::DEFAULTS[$key] ?? ''));
    }

    /** آیا کلید بولی روشن است؟ */
    public static function on(string $key): bool
    {
        return filter_var((string) setting($key, self::DEFAULTS[$key] ?? '0'), FILTER_VALIDATE_BOOLEAN);
    }

    /** عنوان کامل صفحه با اعمال قالب «%page% · %site%» */
    public static function pageTitle(string $page): string
    {
        $site = Features::siteName();
        $page = trim($page);

        if ($page === '' || $page === $site) {
            return $site;
        }

        $template = self::str('seo_title_template');

        if ($template === '' || ! str_contains($template, '%page%')) {
            $template = '%page% · %site%';
        }

        return str_replace(['%page%', '%site%'], [$page, $site], $template);
    }

    /**
     * متاهای کامل صفحهٔ جاری — منبع یکتای حقیقت برای لایوت.
     *
     * @return array{
     *     title: string, description: string, canonical: string, robots: string,
     *     image: string, ogType: string, jsonld: array<int, array<string, mixed>>,
     *     indexable: bool
     * }
     */
    public static function meta(): array
    {
        $route = request()->route();
        $name = (string) ($route?->getName() ?? '');
        $siteName = Features::siteName();
        $canonical = self::canonicalUrl();
        $indexable = true;
        $title = '';
        $description = '';
        $image = '';
        $ogType = 'website';
        $jsonld = [];

        // ---------- صفحات جزئیات (متای پویا از خود رکورد) ----------
        switch ($name) {
            case 'shop.package':
                $entity = self::packageMeta((string) $route->parameter('slug'), $canonical);
                if ($entity) {
                    $title = $entity['title'];
                    $description = $entity['description'];
                    $image = $entity['image'];
                    $ogType = 'product';
                    $jsonld = $entity['jsonld'];
                }
                break;

            case 'shop.product':
                $entity = self::productMeta((string) $route->parameter('slug'), $canonical);
                if ($entity) {
                    $title = $entity['title'];
                    $description = $entity['description'];
                    $image = $entity['image'];
                    $ogType = 'product';
                    $jsonld = $entity['jsonld'];
                }
                break;

            case 'shop.template':
                $entity = self::templateMeta((string) $route->parameter('slug'), $canonical);
                if ($entity) {
                    $title = $entity['title'];
                    $description = $entity['description'];
                    $image = $entity['image'];
                    $ogType = 'product';
                    $jsonld = $entity['jsonld'];
                }
                break;
        }

        // ---------- صفحات ایستا و فهرست‌ها ----------
        if ($title === '' && isset(self::PAGE_DEFAULTS[$name])) {
            [$defaultTitle, $defaultDesc] = self::PAGE_DEFAULTS[$name];
            $key = match ($name) {
                'shop.home' => 'home',
                'shop.packages' => 'packages',
                'shop.products' => 'products',
                'shop.templates' => 'templates',
                'shop.subscriptions' => 'subscriptions',
                'shop.changelog' => 'changelog',
                default => '',
            };

            $title = $key !== '' ? (self::str("seo_page_{$key}_title") ?: $defaultTitle) : $defaultTitle;
            $description = $key !== '' ? (self::str("seo_page_{$key}_desc") ?: $defaultDesc) : $defaultDesc;

            // صفحهٔ اصلی: عنوان پیش‌فرض = خود نام سایت (بدون قالب)
            if ($name === 'shop.home' && self::str('seo_page_home_title') === '') {
                $title = $siteName;
            }

            // اسکیمای پایه سایت (WebSite) +Breadcrumb این صفحه — اسکیمای پایه در لایوت هم هست
            // ولی برای صفحات فهرست داخل صفحه هم WebSite تکرار نمی‌شود؛ فقط breadcrumb:
            $jsonld[] = self::breadcrumbSchema([['نام' => 'فروشگاه', 'url' => route('shop.home')], ['نام' => $title, 'url' => $canonical]]);
        }

        // ---------- صفحات غیرقابل ایندکس (پرداخت/ورود/پنل/دانلود و...) ----------
        if (! self::isIndexableRoute($name)) {
            $indexable = false;
            $title = $title !== '' ? $title : $siteName;
            $description = $description !== '' ? $description : '';
            $jsonld = [];
        }

        if ($title === '') {
            $title = $siteName;
        }

        if ($description === '') {
            $description = self::str('seo_default_desc');
        }

        if ($image === '') {
            $image = self::str('seo_og_image');
        }

        return [
            'title' => self::pageTitle($title),
            'description' => $description,
            'canonical' => $canonical,
            'robots' => $indexable ? 'index, follow, max-image-preview:large, max-snippet:-1' : 'noindex, nofollow',
            'image' => $image !== '' ? self::absoluteUrl($image) : '',
            'ogType' => $ogType,
            'jsonld' => self::on('seo_schema_enabled') ? $jsonld : [],
            'indexable' => $indexable,
        ];
    }

    /** آیا این روت قابل ایندکس است؟ */
    public static function isIndexableRoute(string $name): bool
    {
        // صفحات عمومی فروشگاه
        if (in_array($name, ['shop.home', 'shop.packages', 'shop.products', 'shop.templates', 'shop.subscriptions', 'shop.changelog', 'shop.package', 'shop.product', 'shop.template'], true)) {
            return true;
        }

        // بقیه (پنل، ورود، پرداخت، دانلود، کپچا و...) — noindex
        return false;
    }

    /* ------------------------------------------------------------------ */
    /*  متای پویای صفحات جزئیات */
    /* ------------------------------------------------------------------ */

    /** خلاصهٔ متن برای متا description (حداکثر ~۱۶۰ کاراکتر) */
    private static function excerpt(?string $html, int $limit = 160): string
    {
        $text = trim(html_entity_decode(strip_tags((string) $html))); // فاصله‌های اضافی
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $cut = mb_substr($text, 0, $limit);

        return preg_replace('/\s+\S*$/u', '', $cut).'…';
    }

    /** متای صفحهٔ پکیج (SoftwareApplication) */
    private static function packageMeta(string $slug, string $canonical): ?array
    {
        $package = Package::query()
            ->where('slug', $slug)
            ->where('status', Package::STATUS_ACTIVE)
            ->first();

        if (! $package) {
            return null;
        }

        $title = trim((string) $package->meta_title) ?: $package->name;
        $description = trim((string) $package->meta_description)
            ?: self::excerpt($package->short_description)
            ?: self::excerpt($package->description)
            ?: self::str('seo_default_desc');
        $image = $package->thumbnail_url ?: '';

        // قیمت مؤثر: کمترین قیمت نهاییِ طرح‌های پولی فعال؛ بدون طرح پولی = رایگان
        $paid = $package->activePricingPlans
            ->filter(fn ($plan) => (int) $plan->final_price > 0);

        $price = $paid->isNotEmpty()
            ? (int) $paid->min('final_price')
            : 0;

        $rating = null;

        if (method_exists($package, 'ratingValue')) {
            $rating = ['count' => (int) $package->approvedCommentsCount, 'value' => (float) $package->ratingValue];
        }

        $jsonld = [
            self::softwareSchema([
                'name' => $package->name,
                'slug' => $package->slug,
                'desc' => $description,
                'image' => $image,
                'url' => '/packages/'.$package->slug,
                'priceToman' => $price,
                'currencyNote' => 'toman',
                'category' => $package->category?->name,
                'rating' => $rating,
                'version' => $package->latestVersion?->version,
                'kind' => 'package',
            ]),
            self::breadcrumbSchema([
                ['نام' => 'فروشگاه', 'url' => route('shop.home')],
                ['نام' => 'پکیج‌ها', 'url' => route('shop.packages')],
                ['نام' => $package->name, 'url' => $canonical],
            ]),
        ];

        return ['title' => $title, 'description' => $description, 'image' => $image, 'jsonld' => $jsonld];
    }

    /** متای صفحهٔ محصول (پروژهٔ قابل‌فروش) */
    private static function productMeta(string $slug, string $canonical): ?array
    {
        $product = Product::query()
            ->where('slug', $slug)
            ->where('status', Product::STATUS_ACTIVE)
            ->first();

        if (! $product) {
            return null;
        }

        $title = trim((string) $product->meta_title) ?: $product->name;
        $description = trim((string) $product->meta_description)
            ?: self::excerpt($product->short_description)
            ?: self::excerpt($product->description)
            ?: self::str('seo_default_desc');
        $image = $product->thumbnail_url ?: '';
        $price = $product->is_free ? 0 : (int) $product->final_price;

        $jsonld = [
            self::softwareSchema([
                'name' => $product->name,
                'slug' => $product->slug,
                'desc' => $description,
                'image' => $image,
                'url' => '/products/'.$product->slug,
                'priceToman' => $price,
                'currencyNote' => 'toman',
                'category' => $product->category?->name,
                'rating' => null,
                'version' => $product->latestVersion?->version,
                'kind' => 'product',
            ]),
            self::breadcrumbSchema([
                ['نام' => 'فروشگاه', 'url' => route('shop.home')],
                ['نام' => 'پروژه‌های آماده', 'url' => route('shop.products')],
                ['نام' => $product->name, 'url' => $canonical],
            ]),
        ];

        return ['title' => $title, 'description' => $description, 'image' => $image, 'jsonld' => $jsonld];
    }

    /** متای صفحهٔ قالب */
    private static function templateMeta(string $slug, string $canonical): ?array
    {
        $template = Template::query()
            ->where('slug', $slug)
            ->where('status', Template::STATUS_ACTIVE)
            ->first();

        if (! $template) {
            return null;
        }

        $title = trim((string) $template->meta_title) ?: $template->name;
        $description = trim((string) $template->meta_description)
            ?: self::excerpt($template->short_description)
            ?: self::excerpt($template->description)
            ?: self::str('seo_default_desc');
        $image = $template->thumbnail_url ?: '';
        $price = $template->is_free ? 0 : (int) $template->final_price;

        $jsonld = [
            self::softwareSchema([
                'name' => $template->name,
                'slug' => $template->slug,
                'desc' => $description,
                'image' => $image,
                'url' => '/templates/'.$template->slug,
                'priceToman' => $price,
                'currencyNote' => 'toman',
                'category' => $template->category?->name,
                'rating' => null,
                'version' => $template->latestVersion?->version,
                'kind' => 'template',
            ]),
            self::breadcrumbSchema([
                ['نام' => 'فروشگاه', 'url' => route('shop.home')],
                ['نام' => 'قالب‌های آماده', 'url' => route('shop.templates')],
                ['نام' => $template->name, 'url' => $canonical],
            ]),
        ];

        return ['title' => $title, 'description' => $description, 'image' => $image, 'jsonld' => $jsonld];
    }

    /** آدرس canonical صفحهٔ جاری (با بازنویسی دامنه در صورت تنظیم) */
    public static function canonicalUrl(): string
    {
        $current = url()->current();

        $host = self::str('seo_canonical_host');

        if ($host !== '') {
            // دامنهٔ تنظیم‌شده جایگزین دامنهٔ فعلی می‌شود؛ پروتکل از خودِ تنظیم خوانده می‌شود
            // (بدون پروتکل = https — سایت‌های واقعی همیشه https هستند)
            $scheme = str_starts_with(strtolower($host), 'http://') ? 'http://' : 'https://';
            $host = preg_replace('#^https?://#i', '', rtrim($host, '/'));
            $current = preg_replace('#^https?://([^/]+)(.*)$#i', $scheme.$host.'$2', $current);
        }

        return $current;
    }

    /** تبدیل مسیر نسبی به URL مطلق */
    public static function absoluteUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $host = self::str('seo_canonical_host');

        if ($host !== '' && str_starts_with($path, '/')) {
            return 'https://'.preg_replace('#^https?://#i', '', rtrim($host, '/')).$path;
        }

        return asset($path);
    }

    /* ------------------------------------------------------------------ */
    /*  Schema.org (JSON-LD) */
    /* ------------------------------------------------------------------ */

    /** اسکیمای پایه سایت — WebSite + Organization (روی همهٔ صفحات فروشگاه) */
    public static function siteSchemas(): array
    {
        $siteName = Features::siteName();
        $url = self::canonicalHostRoot();
        $desc = self::str('seo_site_desc') ?: self::str('seo_default_desc');
        $logo = self::str('seo_og_image') ?: 'uploads/images/package-default.png';

        $schemas = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $siteName,
            'url' => $url,
            'description' => $desc,
            'inLanguage' => 'fa-IR',
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $url.'/shop/packages?search={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];

        return [$schemas];
    }

    /** BreadcrumbList */
    public static function breadcrumbSchema(array $items): array
    {
        $list = [];
        $position = 1;

        foreach ($items as $item) {
            $entry = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $item['نام'],
            ];

            if (! empty($item['url'])) {
                $entry['item'] = self::absoluteUrl($item['url']);
            }

            $list[] = $entry;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $list,
        ];
    }

    /**
     * اسکیمای محصول نرم‌افزاری برای صفحات جزئیات (SoftwareApplication + Offer).
     *
     * @param  array{name: string, slug: string, desc: string, image: string, url: string, priceToman: int, currencyNote: string, category: ?string, rating: ?array{count: int, value: float}, version: ?string, kind: string}  $data
     */
    public static function softwareSchema(array $data): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => $data['name'],
            'url' => self::absoluteUrl($data['url']),
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'inLanguage' => 'fa-IR',
        ];

        if ($data['desc'] !== '') {
            $schema['description'] = $data['desc'];
        }

        if ($data['image'] !== '') {
            $schema['image'] = self::absoluteUrl($data['image']);
        }

        if ($data['category']) {
            $schema['applicationSubCategory'] = $data['category'];
        }

        if ($data['version']) {
            $schema['softwareVersion'] = $data['version'];
        }

        // قیمت: پول سایت تومان است — در اسکیما با IRR (استاندارد ISO 4217) ضربدر ۱۰ اعلام می‌شود
        $priceToman = max(0, (int) $data['priceToman']);

        $schema['offers'] = [
            '@type' => 'Offer',
            'price' => $priceToman * 10,
            'priceCurrency' => 'IRR',
            'url' => self::absoluteUrl($data['url']),
            'availability' => 'https://schema.org/InStock',
            'itemCondition' => 'https://schema.org/NewCondition',
        ];

        if ($data['rating'] && $data['rating']['count'] > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => round((float) $data['rating']['value'], 1),
                'ratingCount' => (int) $data['rating']['count'],
                'bestRating' => 5,
                'worstRating' => 1,
            ];
        }

        return $schema;
    }

    /** ریشهٔ دامنهٔ canonical (برای اسکیما/سایت‌مپ) */
    public static function canonicalHostRoot(): string
    {
        $host = self::str('seo_canonical_host');

        if ($host !== '') {
            return 'https://'.preg_replace('#^https?://#i', '', rtrim($host, '/'));
        }

        return rtrim(config('app.url') ?: url('/'), '/');
    }

    /* ------------------------------------------------------------------ */
    /*  robots.txt / llms.txt */
    /* ------------------------------------------------------------------ */

    /** محتوای robots.txt — پویا بر اساس تنظیمات پنل */
    public static function robotsTxt(): string
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /customer',
            'Disallow: /payment',
            'Disallow: /checkout',
            'Disallow: /subscription/result',
            'Disallow: /get-update',
            'Disallow: /captcha',
            'Disallow: /templates/download',
            'Disallow: /products/download',
            'Disallow: /uploads/',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /password/*',
            'Allow: /',
            '',
        ];

        foreach (self::AI_BOTS as $key => $bot) {
            $lines[] = '# '.(self::on($key) ? 'مجاز' : 'مسدود').': '.$bot;
            $lines[] = 'User-agent: '.$bot;
            $lines[] = self::on($key) ? 'Disallow:' : 'Disallow: /';
            $lines[] = '';
        }

        if (self::on('seo_sitemap_enabled')) {
            $lines[] = 'Sitemap: '.self::canonicalHostRoot().'/sitemap.xml';
        }

        if (self::on('seo_llms_enabled')) {
            $lines[] = 'Sitemap: '.self::canonicalHostRoot().'/llms.txt';
        }

        return implode("\n", $lines);
    }

    /** محتوای llms.txt — خلاصهٔ قابل‌مصرف برای مدل‌های زبانی و موتورهای جستجوی هوش مصنوعی */
    public static function llmsTxt(): string
    {
        if ((self::str('seo_llms_text')) !== '') {
            return self::str('seo_llms_text');
        }

        $root = self::canonicalHostRoot();
        $siteName = Features::siteName();
        $desc = self::str('seo_site_desc') ?: self::str('seo_default_desc');

        $lines = [
            '# '.$siteName,
            '',
            '> '.$desc,
            '',
            '## صفحات اصلی',
            '- [فروشگاه]('.$root.'): صفحهٔ اصلی و معرفی همهٔ محصولات',
            '- [پکیج‌ها]('.$root.'/shop/packages): فهرست پکیج‌های آماده با قیمت',
            '- [پروژه‌های آماده]('.$root.'/shop/products): پروژه‌های کامل قابل‌خرید',
            '- [قالب‌های آماده]('.$root.'/shop/templates): قالب‌های آمادهٔ سایت',
            '- [طرح‌های اشتراک]('.$root.'/subscriptions): اشتراک‌های دوره‌ای آپدیت',
            '- [تاریخچه تغییرات]('.$root.'/changelog): آخرین نسخه‌های پکیج‌ها',
            '',
            '## خرید',
            'خرید مهمان بدون ثبت‌نام انجام می‌شود؛ پس از پرداخت، فایل همان‌جا دانلود می‌شود. پرداخت فقط از IP ایران و با درگاه‌های بانکی ایرانی.',
        ];

        return implode("\n", $lines);
    }

    /** محتوای llms-full.txt — فهرست کامل رکوردها برای ایجنت‌های هوش مصنوعی */
    public static function llmsFullTxt(): string
    {
        $root = self::canonicalHostRoot();
        $siteName = Features::siteName();

        $head = self::llmsTxt();

        $lines = [$head, '', '## فهرست کامل پکیج‌ها'];

        $packages = Package::query()
            ->where('status', Package::STATUS_ACTIVE)
            ->orderBy('name')
            ->get(['name', 'slug', 'short_description']);

        foreach ($packages as $p) {
            $lines[] = '- ['.$p->name.']('.$root.'/packages/'.$p->slug.'): '.($p->short_description ?: 'پکیج آماده');
        }

        $lines[] = '';
        $lines[] = '## فهرست کامل پروژه‌های آماده';

        $products = Product::query()
            ->where('status', Product::STATUS_ACTIVE)
            ->orderBy('name')
            ->get(['name', 'slug', 'short_description']);

        foreach ($products as $p) {
            $lines[] = '- ['.$p->name.']('.$root.'/products/'.$p->slug.'): '.($p->short_description ?: 'پروژه آماده');
        }

        $lines[] = '';
        $lines[] = '## فهرست کامل قالب‌های آماده';

        $templates = Template::query()
            ->where('status', Template::STATUS_ACTIVE)
            ->where('is_sold_exclusively', false)
            ->orderBy('name')
            ->get(['name', 'slug', 'short_description']);

        foreach ($templates as $p) {
            $lines[] = '- ['.$p->name.']('.$root.'/templates/'.$p->slug.'): '.($p->short_description ?: 'قالب آماده');
        }

        $lines[] = '';
        $lines[] = 'آخرین به‌روزرسانی: '.verta_date(now(), 'Y/m/d');
        $lines[] = '';
        $lines[] = $siteName;

        return implode("\n", $lines);
    }
}
