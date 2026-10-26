<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * مستندات تعاملی API نسخهٔ ۱ — /admin/api-docs
 *
 * قرارداد هر اندپوینت (متد، مسیر، پارامترها، نمونهٔ درخواست/پاسخ) از روی
 * سورسِ واقعی کنترلرها نوشته شده است (ApiPackageController، ApiTemplateController،
 * ApiSubscriptionController، ApiPackageDownloadController، ApiTemplateDownloadController،
 * ApiPaymentCallbackController، UpdateController) تا مستندات همیشه با کد یکی باشد.
 *
 * Base URL به‌صورت پویا از url('/api/v1') ساخته می‌شود؛ نمونه‌های curl آمادهٔ کپی هستند.
 */
#[Layout('components.layouts.app')]
#[Title('مستندات API')]
class ApiDocs extends Component
{
    /** Base URL واقعی API (پویا — در هر هاستی درست نمایش داده می‌شود) */
    public function baseUrl(): string
    {
        return rtrim(url('/api/v1'), '/');
    }

    /** جمع کل اندپوینت‌ها (برای نشان آماری بالای صفحه) */
    public function endpointsCount(): int
    {
        return collect($this->groups())->sum(fn ($g) => count($g['endpoints']));
    }

    /** جدول کدهای خطای رایج */
    public function errorCodes(): array
    {
        return [
            ['code' => '403', 'color' => 'danger', 'when' => 'توکن نامعتبر / حساب غیرفعال / دامنهٔ غیرمجاز (X-Project-Url با دامنهٔ ثبت‌شدهٔ مشتری نمی‌خواند) / اشتراک یا لایسنس فعال ندارید'],
            ['code' => '404', 'color' => 'warning', 'when' => 'پکیج، قالب، طرح، تراکنش یا فایل درخواستی یافت نشد'],
            ['code' => '422', 'color' => 'warning', 'when' => 'خطای اعتبارسنجی — طرح نامعتبر، درگاه غیرفعال، callback_url ارسال‌نشده یا فرمت اشتباه پارامترها'],
            ['code' => '400', 'color' => 'danger', 'when' => 'پرداخت هنوز تأیید نشده یا تراکنش در وضعیت قابل‌تأیید نیست'],
            ['code' => '429', 'color' => 'danger', 'when' => 'تجاوز از سقف ۱۲۰ درخواست در دقیقه برای هر IP'],
            ['code' => '500', 'color' => 'danger', 'when' => 'خطای داخلی پنل — با پشتیبانی تماس بگیرید'],
        ];
    }

    /** مجموع گروه‌های اندپوینت برای تب‌های صفحه */
    public function groups(): array
    {
        $b = $this->baseUrl();

        // هدرهای احراز — در همهٔ نمونه‌های اندپوینت‌های محافظت‌شده تکرار می‌شود
        $H = 'Authorization: Bearer YOUR_UPDATE_CODE'."\n".'X-Project-Url: https://your-site.com';
        // نمونهٔ curl با ادامهٔ خط ( \ ) — قابل کپی و اجرا مستقیم در ترمینال
        $auth = function (string $url, string $extra = '') use ($H) {
            $parts = ['curl "'.$url.'"'];
            foreach (explode("\n", $H) as $h) {
                $parts[] = '  -H "'.$h.'"';
            }
            if ($extra !== '') {
                foreach (explode("\n", $extra) as $x) {
                    $parts[] = '  '.trim($x);
                }
            }

            return implode(" \\\n", $parts);
        };

        return [
            /* ========================================================== */
            [
                'id' => 'packages',
                'label' => 'پکیج‌ها',
                'icon' => 'package',
                'desc' => 'فروشگاه پکیج‌های پروژه‌ای؛ لیست، خرید با درگاه پرداخت، لایسنس، بررسی نسخه و دانلود.',
                'endpoints' => [
                    [
                        'method' => 'GET',
                        'path' => '/packages',
                        'title' => 'لیست پکیج‌های در دسترس',
                        'desc' => 'پکیج‌های فعالِ پروژه‌هایی که مشتری اشتراک فعال دارد. برای هر آیتم پرچم is_purchased (لایسنس فعال دارد؟)، تعداد خرید، بلوک subscription (پکیجِ رایگانِ طرح اشتراک)، thumbnail_url (لینک کامل تصویر شاخص)، گالری فعال (images با لینک کامل هر تصویر) و category_details (زنجیرهٔ کامل دسته برای ساخت درخت در فروشگاه خریدار) برگردانده می‌شود.',
                        'params' => [
                            ['name' => 'search', 'in' => 'query', 'type' => 'string', 'required' => false, 'desc' => 'جست‌وجو در نام و نامک پکیج'],
                            ['name' => 'category', 'in' => 'query', 'type' => 'string', 'required' => false, 'desc' => 'کلید/نامک دسته‌بندی'],
                            ['name' => 'per_page', 'in' => 'query', 'type' => 'integer', 'required' => false, 'desc' => 'تعداد در هر صفحه (پیش‌فرض ۱۵)'],
                        ],
                        'request' => $auth($b.'/packages?per_page=15'),
                        'sample' => $this->samples('GET', $b.'/packages?per_page=15'),
                        'response' => <<<'JSON'
{
  "data": [
    {
      "id": 1,
      "name": "پکیج فروشگاهی",
      "slug": "shop-package",
      "short_description": "...",
      "thumbnail": "uploads/packages/thumbnails/shop_thumb.png",
      "thumbnail_url": "https://panel.example.com/uploads/packages/thumbnails/shop_thumb.png",
      "images": [
        { "id": 11, "url": "https://panel.example.com/uploads/packages/gallery/shop_1.png", "sort_order": 1 }
      ],
      "is_free": false,
      "is_purchased": true,
      "purchases_count": 12,
      "purchased_license": {
        "license_key": "LIC-XXXX-XXXX",
        "expires_at": "2026-05-01 00:00:00",
        "days_remaining": 92,
        "is_unlimited": false
      },
      "subscription": null,
      "category_details": {
        "id": 3, "name": "افزونه", "slug": "plugins",
        "parents": [{ "id": 1, "name": "وردپرس", "slug": "wordpress" }],
        "path": "وردپرس › افزونه",
        "path_slugs": ["wordpress", "plugins"]
      },
      "latest_version": { "version": "2.1.0", "file_size": 48219340 }
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "total": 1, "per_page": 15 },
  "gateways": [
    { "key": "zarinpal", "title": "زرین‌پال", "logo": "https://...", "is_test": false }
  ],
  "subscription_summary": {
    "has_active_subscription": true,
    "plan_name": "طرح طلایی",
    "expires_at": "2026-06-01 00:00:00",
    "days_remaining": 120
  }
}
JSON,
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/packages/{slug}',
                        'title' => 'جزئیات یک پکیج',
                        'desc' => 'اطلاعات کامل پکیج شامل همهٔ نسخه‌های فعال، طرح‌های قیمت‌گذاری، گالری تصاویر فعال (با لینک کامل هر تصویر) و وضعیت خرید مشتری.',
                        'params' => [
                            ['name' => 'slug', 'in' => 'path', 'type' => 'string', 'required' => true, 'desc' => 'نامک یکتای پکیج'],
                        ],
                        'request' => $auth($b.'/packages/shop-package'),
                        'sample' => $this->samples('GET', $b.'/packages/shop-package'),
                        'response' => <<<'JSON'
{
  "data": {
    "id": 1,
    "name": "پکیج فروشگاهی",
    "slug": "shop-package",
    "thumbnail_url": "https://panel.example.com/uploads/packages/thumbnails/shop_thumb.png",
    "images": [
      { "id": 11, "url": "https://panel.example.com/uploads/packages/gallery/shop_1.png", "sort_order": 1 },
      { "id": 12, "url": "https://panel.example.com/uploads/packages/gallery/shop_2.png", "sort_order": 2 }
    ],
    "is_purchased": true,
    "purchased_license": { "license_key": "LIC-XXXX-XXXX", "expires_at": "2026-05-01 00:00:00", "days_remaining": 92 },
    "active_versions": [ { "version": "2.1.0" }, { "version": "2.0.3" } ],
    "active_pricing_plans": [
      { "id": 7, "title": "لایسنس یک‌ساله", "final_price": 980000, "is_one_time": false }
    ]
  }
}
JSON,
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/packages/{slug}/purchase',
                        'title' => 'خرید پکیج / صدور لایسنس',
                        'desc' => 'سه مسیر دارد: (۱) پکیج رایگان → لایسنس فوری؛ (۲) پوشش‌داده‌شده با اشتراک فعال → لایسنس رایگان بدون درگاه (via_subscription)؛ (۳) خرید پولی → payment_url برای ریدایرکت به درگاه.',
                        'params' => [
                            ['name' => 'pricing_plan_id', 'in' => 'body', 'type' => 'integer', 'required' => 'if-paid', 'desc' => 'شناسه طرح قیمت‌گذاری — برای خرید پولی الزامی'],
                            ['name' => 'callback_url', 'in' => 'body', 'type' => 'url', 'required' => 'if-paid', 'desc' => 'آدرس بازگشت کاربر به فروشگاه شما پس از پرداخت'],
                            ['name' => 'gateway', 'in' => 'body', 'type' => 'string', 'required' => false, 'desc' => 'کلید درگاه (مثل zarinpal) — پیش‌فرض از پنل؛ برای تست از local'],
                        ],
                        'request' => $auth($b.'/packages/shop-package/purchase', '  -X POST '."\n".'  -H "Content-Type: application/json" '."\n".'  -d \'{"pricing_plan_id": 7, "callback_url": "https://your-site.com/payment-result", "gateway": "zarinpal"}\''),
                        'sample' => $this->samples('POST', $b.'/packages/shop-package/purchase', ['pricing_plan_id' => 7, 'callback_url' => 'https://your-site.com/payment-result', 'gateway' => 'zarinpal']),
                        'response' => <<<'JSON'
{
  "payment_url": "https://gateway.example.com/start?...",
  "transaction_id": "tr_9f8a2b1c",
  "amount": 980000,
  "gateway": "zarinpal",
  "purchase_id": 42
}
JSON,
                        'alt_response_label' => 'پاسخ پکیج رایگان / پوشش‌داده‌شده با اشتراک',
                        'alt_response' => <<<'JSON'
{
  "is_free": true,
  "via_subscription": true,
  "plan": "طرح طلایی",
  "license_key": "LIC-XXXX-XXXX",
  "expires_at": "2026-07-01 00:00:00",
  "days_remaining": 30,
  "download_token": "dl_a1b2c3d4...",
  "message": "این پکیج با اشتراک فعال شما رایگان است."
}
JSON,
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/payments/{transactionId}/verify',
                        'title' => 'تأیید پرداخت',
                        'desc' => 'پس از بازگشت کاربر از درگاه، وضعیت تراکنش را استعلام و در صورت موفقیت لایسنس بگیرید. اگر پرداخت هنوز pending باشد، تأیید درگاه از همین‌جا تلاش می‌شود.',
                        'params' => [
                            ['name' => 'transactionId', 'in' => 'path', 'type' => 'string', 'required' => true, 'desc' => 'شناسهٔ تراکنش دریافتی از پاسخ purchase'],
                        ],
                        'request' => $auth($b.'/payments/tr_9f8a2b1c/verify', '  -X POST'),
                        'sample' => $this->samples('POST', $b.'/payments/tr_9f8a2b1c/verify'),
                        'response' => <<<'JSON'
{
  "paid": true,
  "license_key": "LIC-XXXX-XXXX",
  "expires_at": "2026-05-01 00:00:00",
  "days_remaining": 365,
  "download_token": "dl_a1b2c3d4...",
  "signature": "e3b0c44298fc1c14...",
  "version": "2.1.0"
}
JSON,
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/packages/{slug}/verify-license',
                        'title' => 'اعتبارسنجی لایسنس',
                        'desc' => 'هر بار اجرای برنامهٔ خریدار، لایسنس ذخیره‌شده را با این اندپوینت چک کنید؛ هم‌زمان یک download_token تازه (۱۵ دقیقه‌ای) هم می‌گیرید.',
                        'params' => [
                            ['name' => 'license_key', 'in' => 'body', 'type' => 'string', 'required' => true, 'desc' => 'کلید لایسنس ذخیره‌شده در پروژهٔ خریدار'],
                        ],
                        'request' => $auth($b.'/packages/shop-package/verify-license', '  -X POST '."\n".'  -H "Content-Type: application/json" '."\n".'  -d \'{"license_key": "LIC-XXXX-XXXX"}\''),
                        'sample' => $this->samples('POST', $b.'/packages/shop-package/verify-license', ['license_key' => 'LIC-XXXX-XXXX']),
                        'response' => <<<'JSON'
{
  "valid": true,
  "expires_at": "2026-05-01 00:00:00",
  "days_remaining": 92,
  "is_unlimited": false,
  "version": "2.1.0",
  "signature": "e3b0c44298fc1c14...",
  "download_token": "dl_a1b2c3d4..."
}
JSON,
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/packages/{slug}/check-update',
                        'title' => 'بررسی نسخهٔ جدید',
                        'desc' => 'با version_compare مقایسه می‌کند. خروجی شامل تیترهای تغییرات (changelog/what_added/what_changed/what_fixed)، الزامی‌بودن آپدیت و حداقل نسخهٔ PHP/Laravel و وابستگی‌هاست.',
                        'params' => [
                            ['name' => 'current_version', 'in' => 'query', 'type' => 'string', 'required' => true, 'desc' => 'نسخهٔ نصب‌شده فعلی، مثل 1.0.0'],
                        ],
                        'request' => $auth($b.'/packages/shop-package/check-update?current_version=1.0.0'),
                        'sample' => $this->samples('GET', $b.'/packages/shop-package/check-update?current_version=1.0.0'),
                        'response' => <<<'JSON'
{
  "has_update": true,
  "latest_version": "2.1.0",
  "current_version": "1.0.0",
  "is_mandatory": false,
  "changelog": "نسخهٔ ۲.۱.۰ — ...",
  "what_added": "...",
  "what_changed": "...",
  "what_fixed": "...",
  "min_php_version": "8.2",
  "min_laravel_version": "11",
  "dependencies": "...",
  "file_size": 48219340
}
JSON,
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/packages/{slug}/download-url',
                        'title' => 'ساخت لینک دانلود یک‌بارمصرف',
                        'desc' => 'لینکی ۱۵ دقیقه‌ای و یک‌بارمصرف می‌سازد که مستقیم در href مرورگر کاربر کار می‌کند (بدون نیاز به هدر احراز) — مناسب دکمهٔ «دانلود» در فروشگاه شما.',
                        'params' => [
                            ['name' => 'version', 'in' => 'query', 'type' => 'string', 'required' => false, 'desc' => 'نسخهٔ خاص؛ پیش‌فرض آخرین نسخهٔ فعال'],
                        ],
                        'request' => $auth($b.'/packages/shop-package/download-url', '  -X POST'),
                        'sample' => $this->samples('POST', $b.'/packages/shop-package/download-url'),
                        'response' => <<<'JSON'
{
  "download_url": "https://panel.example.com/api/v1/packages/download/dl_a1b2c3d4...",
  "expires_in_seconds": 900,
  "is_one_time": true,
  "slug": "shop-package",
  "version": "2.1.0",
  "file_size": 48219340,
  "file_hash": "e3b0c44298fc1c14..."
}
JSON,
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/packages/{slug}/download',
                        'title' => 'دانلود مستقیم (پروکسی سمت سرور)',
                        'desc' => 'فایل ZIP آخرین نسخهٔ پکیجِ خریداری‌شده را استریم می‌کند — برای وقتی که پروژهٔ خریدار خودش فایل را دریافت و پردازش می‌کند. صحت فایل با هدر X-Package-Hash (SHA-256) قابل کنترل است.',
                        'params' => [
                            ['name' => 'version', 'in' => 'query', 'type' => 'string', 'required' => false, 'desc' => 'نسخهٔ خاص؛ پیش‌فرض آخرین نسخه'],
                        ],
                        'request' => $auth($b.'/packages/shop-package/download'),
                        'sample' => $this->samples('GET', $b.'/packages/shop-package/download', binary: 'package.zip'),
                        'response' => 'فایل باینری ZIP — با هدرهای: Content-Disposition: attachment; X-Package-Version: 2.1.0; X-Package-Hash: e3b0c442... (SHA-256)',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/packages/download/{dlToken}',
                        'title' => 'دانلود با توکن یک‌بارمصرف',
                        'desc' => 'مقصد لینک خروجی download-url. توکن معتبر به‌تنهایی گواهی دسترسی است؛ با هدرهای احراز هم کار می‌کند (توکن باید به همان مشتری تعلق داشته باشد).',
                        'params' => [
                            ['name' => 'dlToken', 'in' => 'path', 'type' => 'string', 'required' => true, 'desc' => 'توکن دانلود — فقط یک بار و تا ۱۵ دقیقه معتبر است'],
                        ],
                        'request' => 'curl -L -o package.zip "'.$b.'/packages/download/dl_a1b2c3d4..."',
                        'sample' => $this->samples('GET', $b.'/packages/download/dl_a1b2c3d4...', noHeaders: true, binary: 'package.zip'),
                        'response' => 'فایل باینری ZIP (همان هدرهای X-Package-Version و X-Package-Hash)',
                    ],
                ],
            ],

            /* ========================================================== */
            [
                'id' => 'templates',
                'label' => 'قالب‌ها',
                'icon' => 'layout-template',
                'desc' => 'همان قرارداد پکیج‌ها برای قالب‌ها؛ با تفاوت نوع خرید (معمولی/اختصاصی) و بدون لایسنس.',
                'endpoints' => [
                    [
                        'method' => 'GET',
                        'path' => '/templates',
                        'title' => 'لیست قالب‌ها',
                        'desc' => 'قالب‌های فعال؛ قالب‌های واگذارشده (خرید اختصاصی) از فهرست حذف می‌شوند. برای هر آیتم is_purchased و purchases_count، thumbnail_url (لینک کامل تصویر شاخص) و gallery (لینک کامل تصاویر گالری) برمی‌گردد تا پروژهٔ خریدار بتواند تصاویر را مستقیم در سایت خودش بارگذاری کند.',
                        'params' => [
                            ['name' => 'search', 'in' => 'query', 'type' => 'string', 'required' => false, 'desc' => 'جست‌وجو در نام و نامک'],
                            ['name' => 'category', 'in' => 'query', 'type' => 'integer', 'required' => false, 'desc' => 'شناسه دسته‌بندی'],
                            ['name' => 'per_page', 'in' => 'query', 'type' => 'integer', 'required' => false, 'desc' => 'تعداد در هر صفحه (پیش‌فرض ۱۵)'],
                        ],
                        'request' => $auth($b.'/templates'),
                        'sample' => $this->samples('GET', $b.'/templates'),
                        'response' => <<<'JSON'
{
  "data": [
    {
      "id": 4,
      "name": "قالب خبری نیوز",
      "slug": "news-template",
      "thumbnail": "uploads/templates/thumbnails/news_thumb.jpg",
      "thumbnail_url": "https://panel.example.com/uploads/templates/thumbnails/news_thumb.jpg",
      "gallery": [
        { "id": 31, "url": "https://panel.example.com/uploads/templates/gallery/news_1.jpg", "sort_order": 1 },
        { "id": 32, "url": "https://panel.example.com/uploads/templates/gallery/news_2.jpg", "sort_order": 2 }
      ],
      "is_purchased": true,
      "purchases_count": 31,
      "latest_version": { "version": "1.4.2", "file_size": 12904212 }
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "total": 1, "per_page": 15 },
  "gateways": [ { "key": "zarinpal", "title": "زرین‌پال", "logo": "https://...", "is_test": false } ]
}
JSON,
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/templates/{slug}',
                        'title' => 'جزئیات قالب',
                        'desc' => 'اطلاعات کامل قالب شامل نسخه‌های فعال، قیمت عادی و قیمت خرید اختصاصی، thumbnail_url (لینک کامل تصویر شاخص) و gallery (لینک کامل تصاویر گالری).',
                        'params' => [
                            ['name' => 'slug', 'in' => 'path', 'type' => 'string', 'required' => true, 'desc' => 'نامک یکتای قالب'],
                        ],
                        'request' => $auth($b.'/templates/news-template'),
                        'sample' => $this->samples('GET', $b.'/templates/news-template'),
                        'response' => <<<'JSON'
{
  "data": {
    "id": 4,
    "name": "قالب خبری نیوز",
    "slug": "news-template",
    "thumbnail_url": "https://panel.example.com/uploads/templates/thumbnails/news_thumb.jpg",
    "gallery": [
      { "id": 31, "url": "https://panel.example.com/uploads/templates/gallery/news_1.jpg", "sort_order": 1 },
      { "id": 32, "url": "https://panel.example.com/uploads/templates/gallery/news_2.jpg", "sort_order": 2 }
    ],
    "price": 690000,
    "discount_price": null,
    "exclusive_price": 9500000,
    "is_sold_exclusively": false,
    "is_purchased": true,
    "active_versions": [ { "version": "1.4.2" } ]
  }
}
JSON,
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/templates/{slug}/purchase',
                        'title' => 'خرید قالب',
                        'desc' => 'purchase_type=normal (پیش‌فرض) خرید معمولی است؛ exclusive واگذاری کامل قالب است و پس از اولین خرید، قالب از فروشگاه حذف می‌شود. خرید تکراری معمولی → مستقیم download_token می‌گیرید.',
                        'params' => [
                            ['name' => 'purchase_type', 'in' => 'body', 'type' => 'string', 'required' => false, 'desc' => 'normal یا exclusive (پیش‌فرض normal)'],
                            ['name' => 'callback_url', 'in' => 'body', 'type' => 'url', 'required' => 'if-paid', 'desc' => 'آدرس بازگشت پس از پرداخت — برای خرید پولی الزامی'],
                            ['name' => 'gateway', 'in' => 'body', 'type' => 'string', 'required' => false, 'desc' => 'کلید درگاه فعال'],
                        ],
                        'request' => $auth($b.'/templates/news-template/purchase', '  -X POST '."\n".'  -H "Content-Type: application/json" '."\n".'  -d \'{"callback_url": "https://your-site.com/payment-result"}\''),
                        'sample' => $this->samples('POST', $b.'/templates/news-template/purchase', ['callback_url' => 'https://your-site.com/payment-result']),
                        'response' => <<<'JSON'
{
  "payment_url": "https://gateway.example.com/start?...",
  "transaction_id": "tr_5d4e3f2a",
  "amount": 690000,
  "gateway": "zarinpal",
  "purchase_id": 88
}
JSON,
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/templates/payments/{transactionId}/verify',
                        'title' => 'تأیید پرداخت قالب',
                        'desc' => 'استعلام وضعیت پرداخت قالب؛ در صورت موفقیت download_token برای نسخهٔ خریداری‌شده برمی‌گردد.',
                        'params' => [
                            ['name' => 'transactionId', 'in' => 'path', 'type' => 'string', 'required' => true, 'desc' => 'شناسهٔ تراکنش دریافتی از پاسخ purchase'],
                        ],
                        'request' => $auth($b.'/templates/payments/tr_5d4e3f2a/verify', '  -X POST'),
                        'sample' => $this->samples('POST', $b.'/templates/payments/tr_5d4e3f2a/verify'),
                        'response' => <<<'JSON'
{
  "paid": true,
  "download_token": "dl_c3d4e5f6...",
  "version": "1.4.2",
  "signature": "a1b2c3d4e5f6...",
  "message": "پرداخت با موفقیت تأیید شد."
}
JSON,
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/templates/{slug}/check-update',
                        'title' => 'بررسی نسخهٔ جدید قالب',
                        'desc' => 'مقایسهٔ نسخهٔ نصب‌شدهٔ قالب با آخرین نسخهٔ فعال.',
                        'params' => [
                            ['name' => 'current_version', 'in' => 'query', 'type' => 'string', 'required' => true, 'desc' => 'نسخهٔ فعلی، مثل 1.4.0'],
                        ],
                        'request' => $auth($b.'/templates/news-template/check-update?current_version=1.4.0'),
                        'sample' => $this->samples('GET', $b.'/templates/news-template/check-update?current_version=1.4.0'),
                        'response' => <<<'JSON'
{
  "has_update": true,
  "latest_version": "1.4.2",
  "current_version": "1.4.0",
  "changelog": "...",
  "file_size": 12904212
}
JSON,
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/templates/{slug}/download-url',
                        'title' => 'ساخت لینک دانلود یک‌بارمصرف قالب',
                        'desc' => 'لینک ۱۵ دقیقه‌ای یک‌بارمصرف برای قالبِ خریداری‌شده — قابل استفاده در مرورگر بدون هدر.',
                        'params' => [
                            ['name' => 'version', 'in' => 'query', 'type' => 'string', 'required' => false, 'desc' => 'نسخهٔ خاص؛ پیش‌فرض آخرین نسخه'],
                        ],
                        'request' => $auth($b.'/templates/news-template/download-url', '  -X POST'),
                        'sample' => $this->samples('POST', $b.'/templates/news-template/download-url'),
                        'response' => <<<'JSON'
{
  "download_url": "https://panel.example.com/api/v1/templates/download/dl_c3d4e5f6...",
  "expires_in_seconds": 900,
  "is_one_time": true,
  "version": "1.4.2",
  "file_size": 12904212,
  "file_hash": "a1b2c3d4e5f6..."
}
JSON,
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/templates/{slug}/download',
                        'title' => 'دانلود مستقیم قالب',
                        'desc' => 'استریم فایل ZIP آخرین نسخهٔ قالبِ خریداری‌شده — برای پروکسی سمت سرور.',
                        'params' => [
                            ['name' => 'version', 'in' => 'query', 'type' => 'string', 'required' => false, 'desc' => 'نسخهٔ خاص؛ پیش‌فرض آخرین نسخه'],
                        ],
                        'request' => $auth($b.'/templates/news-template/download'),
                        'sample' => $this->samples('GET', $b.'/templates/news-template/download', binary: 'template.zip'),
                        'response' => 'فایل باینری ZIP — با هدرهای X-Package-Version و X-Package-Hash',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/templates/download/{dlToken}',
                        'title' => 'دانلود قالب با توکن',
                        'desc' => 'مقصد لینک خروجی download-url قالب؛ توکن به‌تنهایی گواهی دسترسی است.',
                        'params' => [
                            ['name' => 'dlToken', 'in' => 'path', 'type' => 'string', 'required' => true, 'desc' => 'توکن یک‌بارمصرف ۱۵ دقیقه‌ای'],
                        ],
                        'request' => 'curl -L -o template.zip "'.$b.'/templates/download/dl_c3d4e5f6..."',
                        'sample' => $this->samples('GET', $b.'/templates/download/dl_c3d4e5f6...', noHeaders: true, binary: 'template.zip'),
                        'response' => 'فایل باینری ZIP',
                    ],
                ],
            ],

            /* ========================================================== */
            [
                'id' => 'subscriptions',
                'label' => 'اشتراک‌ها',
                'icon' => 'crown',
                'desc' => 'طرح‌های اشتراک دوره‌ای؛ پکیج‌های همراه طرح پس از تأیید مدیر به‌صورت رایگان فعال می‌شوند.',
                'endpoints' => [
                    [
                        'method' => 'GET',
                        'path' => '/subscription-plans',
                        'title' => 'لیست طرح‌های اشتراک',
                        'desc' => 'طرح‌های فعال با امکانات (features)، پکیج‌های همراه (با مدت رایگان هر کدام)، مدت‌های اعتبار چندگزینه‌ای (durations) و درگاه‌های فعال — برای رندر صفحهٔ فروش اشتراک در پروژهٔ خریدار.',
                        'params' => [
                            ['name' => 'search', 'in' => 'query', 'type' => 'string', 'required' => false, 'desc' => 'جست‌وجو در نام و نامک طرح'],
                            ['name' => 'per_page', 'in' => 'query', 'type' => 'integer', 'required' => false, 'desc' => 'تعداد در هر صفحه (پیش‌فرض ۱۵)'],
                        ],
                        'request' => $auth($b.'/subscription-plans'),
                        'sample' => $this->samples('GET', $b.'/subscription-plans'),
                        'response' => <<<'JSON'
{
  "data": [
    {
      "id": 2,
      "name": "طرح طلایی",
      "slug": "gold",
      "duration_months": 12,
      "duration_label": "یک‌ساله",
      "price": 4900000,
      "discount_price": 3900000,
      "final_price": 3900000,
      "is_free": false,
      "is_one_time": false,
      "has_durations": true,
      "durations": [
        { "id": 7, "duration_months": 1, "duration_label": "۱ ماه", "price": 490000, "discount_price": null, "final_price": 490000, "is_free": false, "sort_order": 1 },
        { "id": 8, "duration_months": 3, "duration_label": "۳ ماه", "price": 1290000, "discount_price": 100000, "final_price": 1190000, "is_free": false, "sort_order": 2 },
        { "id": 9, "duration_months": 12, "duration_label": "۱۲ ماه", "price": 4900000, "discount_price": 1000000, "final_price": 3900000, "is_free": false, "sort_order": 3 }
      ],
      "features": ["پشتیبانی ویژه", "آپدیت نامحدود"],
      "packages": [
        { "slug": "shop-package", "name": "پکیج فروشگاهی", "free_months": 6, "free_label": "۶ ماه رایگان" }
      ]
    }
  ],
  "gateways": [ { "key": "zarinpal", "title": "زرین‌پال", "logo": "https://...", "is_test": false } ],
  "meta": { "current_page": 1, "last_page": 1, "total": 1, "per_page": 15 }
}
JSON,
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/subscription-plans/{slug}/purchase',
                        'title' => 'خرید اشتراک',
                        'desc' => 'طرح پولی → payment_url (همان قرارداد خرید پکیج). طرح رایگان → سفارش با admin_status=pending ثبت می‌شود و پس از تأیید مدیر فعال می‌گردد. برای طرح‌های چندمدتی، مدت انتخابی را با duration_months بفرستید (از لیست durations پاسخ subscription-plans).',
                        'params' => [
                            ['name' => 'callback_url', 'in' => 'body', 'type' => 'url', 'required' => true, 'desc' => 'آدرس بازگشت پس از پرداخت'],
                            ['name' => 'gateway', 'in' => 'body', 'type' => 'string', 'required' => false, 'desc' => 'کلید درگاه فعال'],
                            ['name' => 'duration_months', 'in' => 'body', 'type' => 'integer', 'required' => false, 'desc' => 'مدت اعتبار انتخابی (۰ = نامحدود) — برای طرح‌های چندمدتی؛ خالی = مدت پیش‌فرض طرح'],
                        ],
                        'request' => $auth($b.'/subscription-plans/gold/purchase', '  -X POST '."\n".'  -H "Content-Type: application/json" '."\n".'  -d \'{"callback_url": "https://your-site.com/payment-result", "duration_months": 3}\''),
                        'sample' => $this->samples('POST', $b.'/subscription-plans/gold/purchase', ['callback_url' => 'https://your-site.com/payment-result', 'duration_months' => 3]),
                        'response' => <<<'JSON'
{
  "payment_url": "https://gateway.example.com/start?...",
  "transaction_id": "tr_7g8h9i0j",
  "amount": 1190000,
  "gateway": "zarinpal",
  "order_id": 12,
  "duration_months": 3
}
JSON,
                        'alt_response_label' => 'پاسخ طرح رایگان',
                        'alt_response' => <<<'JSON'
{
  "is_free": true,
  "order_id": 13,
  "plan": "طرح آزمایشی",
  "admin_status": "pending",
  "status": "pending",
  "message": "درخواست اشتراک ثبت شد؛ پس از تأیید مدیر فعال می‌شود."
}
JSON,
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/subscriptions/payments/{transactionId}/verify',
                        'title' => 'تأیید پرداخت اشتراک',
                        'desc' => 'استعلام وضعیت سفارش اشتراک. لایسنس پکیج‌های همراه طرح پس از تأیید مدیر صادر می‌شود (نه در همین مرحله) — با my-subscriptions پیگیری کنید.',
                        'params' => [
                            ['name' => 'transactionId', 'in' => 'path', 'type' => 'string', 'required' => true, 'desc' => 'شناسهٔ تراکنش دریافتی از پاسخ purchase'],
                        ],
                        'request' => $auth($b.'/subscriptions/payments/tr_7g8h9i0j/verify', '  -X POST'),
                        'sample' => $this->samples('POST', $b.'/subscriptions/payments/tr_7g8h9i0j/verify'),
                        'response' => <<<'JSON'
{
  "paid": true,
  "order_id": 12,
  "plan": "طرح طلایی",
  "status": "paid",
  "admin_status": "approved",
  "subscription_id": 34,
  "expires_at": "2027-01-01 00:00:00"
}
JSON,
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/my-subscriptions',
                        'title' => 'اشتراک‌ها و سفارش‌های من',
                        'desc' => 'اشتراک‌های طرح‌محور + ۲۰ سفارش اخیر + free_packages (پکیج‌های رایگانِ طرح‌های فعال، همراه با لایسنسِ صادرشدهٔ هر کدام).',
                        'params' => [],
                        'request' => $auth($b.'/my-subscriptions'),
                        'sample' => $this->samples('GET', $b.'/my-subscriptions'),
                        'response' => <<<'JSON'
{
  "data": {
    "subscriptions": [
      {
        "id": 34,
        "plan": "طرح طلایی",
        "plan_slug": "gold",
        "status": "active",
        "starts_at": "2026-01-01",
        "expires_at": "2027-01-01 00:00:00",
        "is_unlimited": false,
        "days_remaining": 334
      }
    ],
    "orders": [
      { "id": 12, "plan": "طرح طلایی", "amount": 3900000, "status": "paid", "admin_status": "approved", "rejected_reason": null, "created_at": "2026-01-01 10:30:00" }
    ],
    "free_packages": [
      {
        "slug": "shop-package",
        "name": "پکیج فروشگاهی",
        "free_months": 6,
        "free_label": "۶ ماه رایگان",
        "subscription_plan": "طرح طلایی",
        "license_key": "LIC-XXXX-XXXX",
        "expires_at": "2026-07-01 00:00:00"
      }
    ]
  }
}
JSON,
                    ],
                ],
            ],

            /* ========================================================== */
            [
                'id' => 'misc',
                'label' => 'پرداخت و دسته‌ها',
                'icon' => 'credit-card',
                'desc' => 'کال‌بک خودکار درگاه (نیازی به فراخوانی دستی ندارد) و درخت دسته‌بندی‌ها برای ساخت دسته در فروشگاه خریدار.',
                'endpoints' => [
                    [
                        'method' => 'GET',
                        'path' => '/payments/callback',
                        'title' => 'کال‌بک درگاه (خودکار)',
                        'desc' => 'درگاه پرداخت، کاربر را به این مسیر برمی‌گرداند؛ پنل پرداخت را تأیید می‌کند و به صفحهٔ «نتیجهٔ پرداخت» با شمارش معکوس ۱۰ ثانیه‌ای هدایت می‌کند تا به callback_url فروشگاهِ شما برگردد. این اندپوینت را خودتان صدا نمی‌زنید.',
                        'params' => [
                            ['name' => 'transaction_id', 'in' => 'query', 'type' => 'string', 'required' => true, 'desc' => 'از سمت درگاه ارسال می‌شود (یا Authority زرین‌پال / tracking_code / token)'],
                        ],
                        'request' => 'فراخوانی خودکار توسط درگاه پرداخت — نیازی به اقدام دستی نیست.',
                        'response' => 'ریدایرکت HTML به صفحهٔ نتیجهٔ پرداخت پنل (نمایش وضعیت + بازگشت خودکار به callback_url شما)',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/categories',
                        'title' => 'درخت دسته‌بندی‌ها',
                        'desc' => 'دسته‌های فعال به‌صورت درخت تو در تو (children) — تا پروژهٔ خریدار (مثل افزونهٔ وردپرس) هنگام نصب پکیج، دستهٔ فروشگاه را درختی انتخاب یا بسازد.',
                        'params' => [
                            ['name' => 'type', 'in' => 'query', 'type' => 'string', 'required' => false, 'desc' => 'package یا product (پیش‌فرض package)'],
                        ],
                        'request' => $auth($b.'/categories?type=package'),
                        'sample' => $this->samples('GET', $b.'/categories?type=package'),
                        'response' => <<<'JSON'
{
  "data": [
    {
      "id": 1,
      "name": "وردپرس",
      "slug": "wordpress",
      "description": null,
      "children": [
        { "id": 3, "name": "افزونه", "slug": "plugins", "description": "...", "children": [] }
      ]
    }
  ]
}
JSON,
                    ],
                ],
            ],

            /* ========================================================== */
            [
                'id' => 'legacy',
                'label' => 'سیستم آپدیت (قدیمی)',
                'icon' => 'git-branch',
                'desc' => 'اندپوینت‌های نسل اول برای پروژه‌های قدیمی — بدون هدرِ دامنه؛ فقط پارامتر token. برای کارهای جدید از اندپوینت‌های پکیج‌ها استفاده کنید.',
                'endpoints' => [
                    [
                        'method' => 'GET',
                        'path' => '/check-update',
                        'title' => 'بررسی آپدیت (قدیمی)',
                        'desc' => 'آخرین آپدیت منتشرشدهٔ پروژه‌های مشتری را با نسخهٔ فعلی مقایسه می‌کند؛ اگر جدیدتر باشد لینک دانلود امن می‌دهد. دسترسی از هر دامنه‌ای که مشتری ثبت کرده باشد بررسی می‌شود.',
                        'params' => [
                            ['name' => 'token', 'in' => 'query', 'type' => 'string', 'required' => true, 'desc' => 'کد آپدیت مشتری (update_code)'],
                            ['name' => 'version', 'in' => 'query', 'type' => 'string', 'required' => true, 'desc' => 'نسخهٔ فعلی برنامه، مثل 1.0.0'],
                        ],
                        'request' => 'curl "'.$b.'/check-update?token=YOUR_UPDATE_CODE&version=1.0.0"',
                        'sample' => $this->samples('GET', $b.'/check-update?token=YOUR_UPDATE_CODE&version=1.0.0', noHeaders: true),
                        'response' => <<<'JSON'
{
  "update_available": true,
  "version": "3.2.0",
  "description": "توضیحات نسخهٔ جدید",
  "download_url": "https://panel.example.com/api/v1/download-update/17?token=YOUR_UPDATE_CODE",
  "file_size": 34928120
}
JSON,
                        'alt_response_label' => 'وقتی آپدیتی نیست',
                        'alt_response' => '{ "update_available": false }',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/download-update/{updateId}',
                        'title' => 'دانلود آپدیت (قدیمی)',
                        'desc' => 'مقصد download_url پاسخ قبلی؛ فایل آپدیت را استریم می‌کند. توکن و اشتراک فعال مجدداً چک می‌شود.',
                        'params' => [
                            ['name' => 'updateId', 'in' => 'path', 'type' => 'integer', 'required' => true, 'desc' => 'شناسهٔ آپدیت از پاسخ check-update'],
                            ['name' => 'token', 'in' => 'query', 'type' => 'string', 'required' => true, 'desc' => 'کد آپدیت مشتری'],
                        ],
                        'request' => 'curl -L -o update.zip "'.$b.'/download-update/17?token=YOUR_UPDATE_CODE"',
                        'sample' => $this->samples('GET', $b.'/download-update/17?token=YOUR_UPDATE_CODE', noHeaders: true, binary: 'update.zip'),
                        'response' => 'فایل باینری آپدیت (attachment)',
                    ],
                ],
            ],
        ];
    }

    /** نمونهٔ «اولین درخواست» شروع سریع به هر سه زبان (cURL در blade ساخته می‌شود) */
    public function quickStartSamples(): array
    {
        return $this->samples('GET', $this->baseUrl().'/packages?per_page=15');
    }

    /* ===================================================================
     *  نمونه‌سازها — Laravel HTTP Client و Guzzle
     *  کد آمادهٔ کپی از روی متد/آدرس/بدنهٔ ساختاریافته ساخته می‌شود تا هر سه
     *  زبان (cURL / Laravel / Guzzle) همیشه با سورس کنترلرها هماهنگ بمانند.
     * =================================================================== */

    /** ساخت جفت نمونهٔ [laravel, guzzle] برای یک اندپوینت */
    private function samples(string $method, string $url, array $body = [], bool $noHeaders = false, ?string $binary = null): array
    {
        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $query);

        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? 'localhost');
        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }
        $path = $parts['path'] ?? '/';

        $headers = $noHeaders ? [] : [
            'Authorization' => 'Bearer YOUR_UPDATE_CODE',
            'X-Project-Url' => 'https://your-site.com',
        ];

        // اعداد داخل کوئری برای نمونهٔ تمیزتر به اینتجر تبدیل شوند (per_page=15 نه '15')
        $query = array_map(fn ($v) => is_string($v) && ctype_digit($v) ? (int) $v : $v, $query);

        return [
            'laravel' => $this->laravelSample(strtolower($method), $origin.$path, $query, $body, $headers, $binary),
            'guzzle'  => $this->guzzleSample($origin, $path, $query, $body, $headers, strtolower($method), $binary),
        ];
    }

    /** آرایهٔ PHP با کلیدهای هم‌تراز برای درج داخل نمونه‌کد */
    private function phpArray(array $items, int $indent): string
    {
        if ($items === []) {
            return '[]';
        }

        $pad = str_repeat(' ', $indent);
        $width = max(array_map('strlen', array_keys($items)));
        $rows = [];

        foreach ($items as $key => $value) {
            $rows[] = $pad."'".addslashes((string) $key)."'"
                .str_repeat(' ', $width - strlen((string) $key)).' => '.$this->phpValue($value).',';
        }

        return "[\n".implode("\n", $rows)."\n".str_repeat(' ', max(0, $indent - 4)).']';
    }

    /** مقدار PHP داخل نمونه‌کد — رشته با کوتیشن، عدد/بولین خام */
    private function phpValue(mixed $value): string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return "'".addslashes((string) $value)."'";
    }

    /** نمونهٔ Laravel HTTP Client */
    private function laravelSample(string $method, string $url, array $query, array $body, array $headers, ?string $binary): string
    {
        $chainParts = [];
        if ($headers !== []) {
            $chainParts[] = 'withHeaders('.$this->phpArray($headers, 4).')';
        }
        if ($binary !== null) {
            $chainParts[] = "withOptions([\n    'sink' => '".addslashes($binary)."', // فایل در همین مسیر ذخیره می‌شود\n])";
        }

        // بدون هدر/سینک → فراخوانی استاتیک Http::get(...)؛ با هدر → Http::withHeaders([...])->get(...)
        $verb = $method === 'post' ? 'post' : 'get';
        if ($method === 'post') {
            $args = $body === []
                ? "'".$url."'"
                : "'".$url."', ".$this->phpArray($body, 4);
        } else {
            $args = $query === []
                ? "'".$url."'"
                : "'".$url."', ".$this->phpArray($query, 4);
        }
        $call = $chainParts === []
            ? "Http::".$verb."(".$args.')'
            : 'Http::'.implode('->', $chainParts).'->'.$verb.'('.$args.')';

        $trailer = $binary !== null
            ? "\necho \$response->header('X-Package-Version');\n// صحت فایل: hash_file('sha256', '".addslashes($binary)."') را با هدر X-Package-Hash مقایسه کنید"
            : "\n\$data = \$response->throw()->json();";

        return 'use Illuminate\\Support\\Facades\\Http;'."\n\n\$response = ".$call.';'.$trailer;
    }

    /** نمونهٔ Guzzle */
    private function guzzleSample(string $origin, string $path, array $query, array $body, array $headers, string $method, ?string $binary): string
    {
        if ($headers !== []) {
            $headers['Accept'] = 'application/json';
        }

        $parts = [];
        if ($headers !== []) {
            $parts[] = "'headers' => ".$this->phpArray($headers, 8);
        }
        if ($query !== []) {
            $parts[] = "'query' => ".$this->phpArray($query, 8);
        }
        if ($body !== []) {
            $parts[] = "'json' => ".$this->phpArray($body, 8);
        }
        if ($binary !== null) {
            $parts[] = "'sink' => '".addslashes($binary)."'";
        }

        $opts = $parts === [] ? '' : ", [\n    ".implode(",\n    ", $parts).",\n]";
        $call = "\$client->".$method."('".addslashes($path)."'".$opts.')';

        $trailer = $binary !== null
            ? "\necho \$response->getHeaderLine('X-Package-Version');\n// صحت فایل: hash_file('sha256', '".addslashes($binary)."') را با هدر X-Package-Hash مقایسه کنید"
            : "\$data = json_decode((string) \$response->getBody(), true);";

        return 'use GuzzleHttp\\Client;'."\n\n\$client = new Client(['base_uri' => '".$origin."', 'timeout' => 30]);\n\n\$response = ".$call.";\n\n".$trailer;
    }

    public function render()
    {
        return view('livewire.api-docs');
    }
}
