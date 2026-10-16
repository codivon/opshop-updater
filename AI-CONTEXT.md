# AI-CONTEXT — آپدیت‌سنتر (Project Handoff Document)

> **این فایل چیست؟** سند انتقال پروژه برای هوش مصنوعی (یا توسعه‌دهنده) جدید که می‌خواهد کار روی این پروژه را ادامه دهد.
> همه‌چیز که «در جریان پروژه» بودن نیاز دارد اینجاست: معماری، قرار دادها، سرویس‌ها، جریان پرداخت، گاتچاهای مهم و روش تست.
> This document is written primarily in Persian (the project's language) with English technical terms. All UI text, validation messages and domain logic are Persian/RTL.

---

## ۱. تصویر کلی پروژه

**آپدیت‌سنتر** — پنل مدیریت «مصرف‌کننده-محور» برای فروش و توزیع پکیج‌های نرم‌افزاری لاراول به مشتریان (agency-style update center):

- **پنل مدیریت** (`/admin/*`): مدیریت پروژه‌ها، پکیج‌ها، نسخه‌ها، طرح‌های قیمت، لایسنس‌ها، خریدها، مشتریان، اشتراک‌ها، کاربران، گزارش‌ها، لاگ‌ها، تنظیمات و درگاه‌های پرداخت.
- **فروشگاه عمومی** (`/` و `/packages/{slug}`): مشتریان پکیج‌ها را می‌بینند و **با «کد آپدیت» (update_code)** بدون ثبت‌نم خرید می‌کنند؛ پکیج رایگان → لایسنس فوری؛ پکیج پرداختی → انتقال به درگاه → صفحه نتیجه.
- **فروشگاه محصولات (پروژه‌های قابل‌فروش)** (`/products/{slug}`): 🆕 فروش مستقیم پروژه‌های آماده (اسکریپت، قالب، اپلیکیشن و…) — **خرید مهمان بدون هیچ ورود اطلاعات**، صفحه تکی با دیدگاه‌ها (تأیید مدیر)، نسخه‌بندی و دانلود ZIP توکنی. تب «پروژه‌ها» در صفحه اصلی فروشگاه + فیلتر دسته‌بندی/قیمت/جستجو/مرتب‌سازی روی هر دو تب.
- **API عمومی v1** (`/api/v1/*`): برای سیستم‌های لاراولیِ خودِ مشتریان — چک آپدیت، خرید، تأیید پرداخت، لایسنس، دانلود توکنی (۱۵ دقیقه). احراز هویت با همان `update_code` + هدر `X-Project-Url` (قفل دامنه).

**اصل مهم:** پنل کاملاً با **Livewire 3** بازنویسی شده (SPA با `wire:navigate`)، طراحی همه المان‌ها از صفر با Tailwind CSS 4 انجام شده و لایه API v1 دست‌نخورده مانده (backward-compat با کلاینت‌های موجود).

---

## ۲. استک و الزامات

| لایه | نسخه/ابزار |
|---|---|
| زبان | PHP **8.3+** (dev با 8.4.13) — Laravel **13** |
| فرانت‌اند | Livewire **3** + Alpine 3 + Vite 7 + Tailwind CSS **4** |
| فونت | Vazirmatn (ادمین + شاپ)، RTL کامل |
| دیتابیس | **SQLite** (`database/database.sqlite`) — قابلیت تغییر به MySQL از `.env` |
| پرداخت | `shetabit/payment` v6 **از پکیج محلی `packages/shetabit/`** (نه packagist! — psr-4 در composer.json: `Shetabit\Payment\` و `Shetabit\Multipay\` به `packages/shetabit/*/src` مپ شده) |
| تاریخ | `morilog/jalali` (helper: `verta_date()`) + `fa_num()` برای اعداد فارسی |
| UI | کامپوننت‌های Blade خودساخته (`resources/views/components/`) + رجیستری آیکون‌های Lucide inline |

**نصب:**
```bash
composer install
# .env و database.sqlite داخل بسته موجودند (APP_KEY آماده)
php artisan serve          # http://localhost:8000
# اگر خواستید دیتای دمو را از نو بسازید: php demo-seed.php
# تغییر CSS/JS: bun install && bun run build  (یا npm)
```

**ورود پنل:** `admin@panel.test` / `secret123` — **کد آپدیت مشتری دمو:** `1F61148198FD` (علی رضایی، active)، `DDE00593DD90` (مریم حسینی)، `C28ACDCDF536` (غیرفعال — برای تست خطا).

---

## ۳. مدل داده (خلاصه — جزئیات در `database/migrations/`)

- **Customer** (`customers`): name, email, phone, website_url, **update_code (کلید احراز هویت everywhere!)**, status.boot() → تولید خودکار کد ۱۲ کاراکتری hex.
- **Project** (`projects`): name, slug, description, status.
- **Update** (`updates`): project_id, title, **version**, type(major/minor/patch), status(draft/active/archived), description (متن ساده — به API می‌رود)، download_link, file_size, release_date, is_mandatory.
- **Package** (`packages`): project_id, name, slug, category, short_description, **description (HTML از CKEditor)**, thumbnail, is_free, default_price, status, sort_order, purchases_count. Relations: `latestVersion`, `activeVersions`, `activePricingPlans`, `images`. Accessor: `thumbnail_url` (fallback به `public/images/package-default.png`).
- **PackageVersion**: package_id, version, changelog/what_added/what_changed/what_fixed, file_path, file_hash(SHA256), file_size, min_php_version, min_laravel_version, dependencies(json), is_mandatory, status.
- **PackagePricingPlan**: package_id, name, description, duration_months (0=نامحدود), price, discount, **final_price**, is_one_time, is_active.
- **PackagePurchase**: package_id, version_id, pricing_plan_id, customer_id, license_id, **transaction_id**, callback_url, amount, gateway, payment_url, status(pending/paid/failed/refunded), paid_at, meta(json). `markAsPaid/Failed()`.
- **PackageLicense**: license_key(`PKG-...`), package_id, customer_id, purchase_id, **renewed_from**, status(active/revoked/expired), starts_at, expires_at, duration_months.
- **PackageDownloadToken**: token, license_id, version_id, customer_id, expires_at(+15m), used_at, ip.
- **Subscription**: customer_id, project_id, price, start/end/expires_at, status(active/expired/suspended), payment_status, **code** (کد آپدیت قدیمی سطح اشتراک), description.
- **Gateway** (`gateways`): key (یکی از ۱۱ کلید `config/general.php → supported_gateways`)، name, ordering, is_active. **GatewayConfig**: gateway_id, key, value (مثلا merchantId). helper سراسری: `get_gateway_configs($key)` از bootstrap/helpers.php.
- **Setting** (`settings`): key, value, type, group (general/email). `Setting::set()`.
- **Transaction**: جدول shetabit (`transactions`).

### 🆕 مدل محصولات قابل‌فروش (فروشگاه پروژه‌ها — Task 23)
- **Category** (`categories`): دسته‌بندی مشترک با `type` (`product` | `package`). ۶ دسته پیش‌فرض محصول در مایگریشن seed می‌شود؛ دسته‌های قدیمی متنی پکیج‌ها هم در مایگریشن backfill به این جدول منتقل شده‌اند (`packages.category_id` + ستون قدیمی `category` برای سازگاری API هم‌زمان sync می‌شود).
- **Product** (`products`): category_id, name, slug, short_description(TEXT), description(HTML CKEditor), thumbnail, price, discount(درصد), is_free, status(draft/active/archived), sort_order, sales_count, views_count. Accessorها: `final_price` (تخفیف‌خورده)، `is_effectively_free`، `rating` (میانگین دیدگاه‌های تأییدشده)، `thumbnail_url` (fallback به placeholder پکیج). ⚠️ در مدل Package رابطه دسته‌بندی `categoryModel()` است نه `category()` — چون ستون متنی `category` از قبل هست و attribute بر relation اولویت دارد.
- **ProductVersion**: product_id, version, type(major/minor/patch), changelog/what_added/what_changed/what_fixed, file_path (disk `local` → `storage/app/private/products/{slug}/`), file_hash(SHA256), file_size, is_mandatory, status, downloads_count, release_date.
- **ProductPurchase** (`product_purchases`): product_id, version_id, transaction_id, amount, gateway, payment_url, callback_url, status(pending/paid/failed/refunded), paid_at, meta(json). markAsPaid → `product->incrementSales()`. `createDownloadToken()` → توکن یک‌بارمصرف ۱۵ دقیقه‌ای.
- **ProductComment**: product_id, name (مهمان), email?, rating(1..5 nullable), body, status(pending/approved/rejected), approved_at, ip. نمایش در صفحه تکی فقط approved؛ ثبت همیشه pending → تأیید مدیر از پنل.
- **ProductDownloadToken**: token (`pdt_…`), product_id, product_version_id, purchase_id, expires_at(+15m), used_at (یک‌بارمصرف), ip.
- **جریان پرداخت محصول**: `PaymentService::createProductPayment()` (نکته resolveFormUrl سه‌گانه: PackagePurchase | SubscriptionOrder | ProductPurchase → مسیر امضادار `payment.form.product`) + `verifyProductPayment()`. کال‌بک مشترک `/payment/callback` در WebPaymentController سه نوع تراکنش را به ترتیب تشخیص می‌دهد: SubscriptionOrder → ProductPurchase → PackagePurchase. پیامک مدیر `admin_product_paid` هنگام فروش موفق.
- **خرید مهمان در صفحه تکی** (`Shop\ProductShow::buy`): رایگان → خرید paid فوری + ریدایرکت به `payment.result.product`؛ پولی → انتخاب درگاه (همان picker لوگودار مشترک `shop/partials/gateway-picker`) → درگاه → کال‌بک → نتیجه. صفحه نتیجه (`Shop\ProductPaymentResult`) دکمه دانلود با توکن تازه هر کلیک دارد + پیگیری خرید مهمان با شماره تراکنش (کامپوننت `Shop\PurchaseTrack` در تب پروژه‌های صفحه اصلی).

### 🆕 سیستم کد تخفیف (کوپن) — برای همه خریدها (پکیج/محصول/اشتراک)
- **DiscountCode** (`discount_codes`): `code` (یکتا، ذخیره با حروف بزرگ)، `type` (`percent` | `fixed`)، `value` (درصد ۱..۱۰۰ یا مبلغ ثابت تومان)، **`max_uses`** (null=نامحدود) + **`used_count`**، **`expires_at`** (null=بدون انقضا)، سه فلگ محدوده `applies_packages/products/subscriptions`، `is_active`، `notes`.
- **DiscountService::resolve($input, $context, $amount)** → `[ok, message, code, discount, final]`. همه چک‌ها: فعال‌بودن، انقضا، ظرفیت (`hasRemainingUses`)، محدوده (`appliesTo`). ⚠️ همیشه سمت سرور قبل از ایجاد خرید دوباره resolve می‌شود؛ پیش‌نمایش UI فقط نمایشی است.
- **مصرف شمارنده**: `DiscountCode::recordUsage()` — increment اتمیک با گارد ظرفیت (`used_count < max_uses`). فقط **یک‌بار** در هر خرید: مسیر پرداختی داخل `markAsPaid()` هر سه مدل خرید (با گارد idempotency `isPaid()` در برابر کال‌بک تکراری درگاه)، مسیر رایگان/تخفیف ۱۰۰٪ مستقیم بعد از `create` (چون از `markAsPaid` عبور نمی‌کند).
- **ستون‌های جدید در `package_purchases` و `product_purchases`**: `discount_code_id` (FK، nullOnDelete)، `original_amount` (مبلغ قبل از کوپن)، `discount_amount` — ⚠️ ستون `amount` از این به بعد **مبلغ نهایی بعد از تخفیف** است و درگاه بر همین مبنا شارژ/verify می‌کند (سایت‌های قدیمی با amount=۰ هم سازگارند چون discount_amount پیش‌فرض ۰). در `subscription_orders` فقط `discount_code_id` اضافه شد (ستون‌های amount/discount/final_amount از قبل بود)؛ snapshot کوپن داخل `meta.coupon` ذخیره می‌شود تا بعد از حذف کد هم گزارش بماند.
- **UI فروشگاه**: پارشیال مشترک `shop/partials/discount-field.blade.php` (قرارداد: پراپرتی‌های public `discountCode`/`appliedCode` + `#[Computed] appliedDiscount()` + اکشن‌های `applyDiscount()`/`removeDiscount()`)، در هر سه صفحه: package-show، product-show، subscriptions-index. تخفیف ۱۰۰٪ → مسیر رایگان («رایگان با کد تخفیف» + بدون نیاز به درگاه).
- **پنل ادمین**: `/admin/discounts` (`Livewire\Discounts\Index`) — CRUD مودال، تولید خودکار کد (بدون حروف مبهم)، فیلتر وضعیت (فعال/غیرفعال/منقضی/تکمیل‌ظرفیت)، شمارش استفاده از هر سه جدول خرید. لینک در سایدبار با آیکون `ticket-percent`.
- ⚠️ گاتچا: وقتی مبلغ مبنا صفر است (پکیج رایگان/طرح رایگان) کدِ تایپ‌شده نیمه‌کاره **نادیده** گرفته می‌شود نه خطا — تا جلوی دریافت رایگان گرفته نشود.

---

## ۴. نقشه مسیرها

### وب (`routes/web.php`)
```
GET  /                              → Shop\Home            (فروشگاه — تب‌های پکیج‌ها/پروژه‌ها + فیلترها)
GET  /packages/{slug}               → Shop\PackageShow     (جزئیات + checkout)
GET  /products/{slug}               → Shop\ProductShow     (🆕 صفحه تکی محصول قابل‌فروش + دیدگاه‌ها)
GET  /payment/result/product/{purchase} → Shop\ProductPaymentResult (🆕 نتیجه خرید محصول + دانلود)
GET  /products/download/{token}     → ProductDownloadController (🆕 دانلود ZIP توکنی یک‌بارمصرف)
GET  /payment/callback              → WebPaymentController (بازگشت از درگاه، GET)
POST /payment/callback              → WebPaymentController (POST بانک، CSRF-exempt)
GET  /payment/result/{purchase}     → Shop\PaymentResult   (صفحه نتیجه)
GET  /get-update/{code}             → UpdateDownloadController (دانلود مستقیم آپدیت)
     + Auth::routes() (laravel/ui)
GET  /admin/...                     → ۱۳ صفحه Livewire پنل (auth middleware)
```

### API v1 (`routes/api/v1.php`)
```
GET  /api/v1/check-update?...                          → UpdateController@check
GET  /api/v1/download-update/{id}                      → UpdateController@download
GET  /api/v1/packages                                  → ApiPackageController@index    (پکیج‌های پروژه‌های مشتری)
                                                        ⭐ هر آیتم: is_purchased + purchased_license {license_key, expires_at, days_remaining, is_unlimited} (+ installed_license قدیمی)
                                                        ⭐⭐ هر آیتم (1405/07): subscription → null یا بلوک «رایگان با اشتراک فعال»:
                                                           {is_free_with_subscription, plan_name, plan_slug, subscription_expires_at,
                                                            days_remaining, free_months, license_key (اگر لایسنس فعال دارد)}
                                                        ⭐⭐ کنار meta: subscription_summary {has_active_subscription, plan_name, expires_at, days_remaining}
GET  /api/v1/packages/{slug}                           → show (+ is_purchased + purchased_license + installed_license + subscription)
POST /api/v1/packages/{slug}/purchase                  → purchase  body: {callback_url?, pricing_plan_id?, gateway?} → {payment_url, transaction_id, amount, gateway}
     (اگر رایگان: مستقیم {is_free, license_key, expires_at, download_token})
     ⭐⭐ (1405/07) اگر پکیج با اشتراک فعالِ طرح‌محور مشتری پوشش داده شود → بدون درگاه و بدون pricing_plan_id/callback_url:
        {is_free: true, via_subscription: true, plan, license_key, expires_at, days_remaining, download_token, message} — idempotent (لایسنس فعال موجود → همان برمی‌گردد)
     ⚠️ اگر پوشش نبود: pricing_plan_id الزامی (بدون آن → 422 «انتخاب طرح قیمت‌گذاری الزامی است.») و برای مسیر پولی callback_url الزامی (بدون آن → 422 «آدرس بازگشت (callback_url) برای پرداخت الزامی است.»)
POST /api/v1/payments/{transactionId}/verify           → verifyPayment → {paid, license_key, expires_at, days_remaining, download_token, signature, version}
GET|POST /api/v1/payments/callback                     → ApiPaymentCallbackController (بازگشت مرورگر؛ بدون callback_url → ریدایرکت به payment.result)
POST /api/v1/packages/{slug}/verify-license            → {valid, expires_at, days_remaining, version, signature, download_token}
GET  /api/v1/packages/{slug}/check-update?current_version= → {has_update, latest_version, changelog, ...}

--- طرح‌های اشتراک (1405/07) ---
GET  /api/v1/subscription-plans                        → ApiSubscriptionController@index (لیست طرح‌های فعال + پکیج‌های همراه)
POST /api/v1/subscription-plans/{slug}/purchase        → ثبت سفارش (callback_url + gateway?) — رایگان: {is_free, order_id, admin_status}
POST /api/v1/subscriptions/payments/{transactionId}/verify → تأیید پرداخت سفارش اشتراک
GET  /api/v1/my-subscriptions                          → {subscriptions, orders, ⭐free_packages: پکیج‌های رایگانِ طرح‌های فعال
                                                        + لایسنس فعال هر کدام {slug, name, free_months, free_label, subscription_plan, license_key, expires_at}}

--- دانلود پکیج خریداری‌شده (جدید ۱۴۰۵/۰۶/۳۱) ---
GET  /api/v1/packages/{slug}/download[?version=x.y.z]  → ⭐ دانلود مستقیم ZIP آخرین نسخه (یا نسخه خاص) با هدرهای احراز — برای proxy از سمت پروژه خریدار
POST /api/v1/packages/{slug}/download-url              → ⭐ ساخت لینک یک‌بارمصرف ۱۵دقیقه‌ای → {download_url, expires_in_seconds, version, file_size, file_hash} — لینک بدون هدر هم کار می‌کند (قابل قرار دادن در href)
GET  /api/v1/packages/download/{dlToken}               → فایل ZIP؛ ⭐ حالا بدون هدرهای احراز هم کار می‌کند (توکن خودش گواهی است) — سازگار با قبل (با هدر هم صحیح)
```
**احراز هویت API** (`PackageApiAuthService`): توکن از `Authorization: Bearer` یا `X-Project-Key` یا `?token=` → Customer با `update_code` + الزام هدر `X-Project-Url` (هوست باید با `customer.website_url` یا زیردامنه‌اش بخواند).

### ⭐ قرارداد یکپارچه‌سازی «پروژه فروشگاه» (store project مشتری)
پروژه فروشگاه مشتری باید برای هر مشتری (با update_code + website_url ثبت‌شده در پنل):
1. **لیست پکیج‌ها**: `GET /api/v1/packages` با هدرهای `Authorization: Bearer {update_code}` + `X-Project-Url: https://{مشتری-دامنه}`.
2. **نمایش دکمه**: برای هر آیتم اگر `is_purchased == true` → دکمه «دانلود» (نه «خرید»); `purchased_license.days_remaining` هم برای هشدار انقضای نزدیک. (طرح‌ها در `active_pricing_plans[]` با فیلدهای `price`/`discount_price` — `final_price` در JSON نیست.)
3. **دانلود**: دو راه —
   - ساده: `POST /api/v1/packages/{slug}/download-url` → بگذار `download_url` را مستقیم در `href` دکمه (مرورگر مشتری بدون هدر دانلود می‌کند؛ ۱۵ دقیقه/یک‌بار).
   - یا: `GET /api/v1/packages/{slug}/download` با هدرها از بک‌اند خودتان proxy کنید (برای شمارش/IP یا هر منطق دیگر).
4. **خرید/تمدید**: `POST /packages/{slug}/purchase` با Body: `{callback_url, pricing_plan_id, gateway?}`:
   - `gateway` اختیاری — درگاه فعال پنل (پیش‌فرض zarinpal)؛ برای تست کل جریان بدون پول واقعی: `local`.
   - پاسخ: `{payment_url, transaction_id, amount, gateway, purchase_id}` — **payment_url را فقط ریدایرکت کنید**: برای درایورهای URL (زرین‌پال) مستقیمِ بانک است؛ برای درایورهای فرم‌محور (به‌پرداخت و…) مسیر امضادار `payment/form/{id}` روی پنل است که فرم خود-ارسال را رندر می‌کند (نیازی به منطق اضافه سمت کلاینت نیست).
   - رایگان (is_free یا final_price≤0): `{is_free: true, license_key, expires_at, download_token}` — بدون درگاه.
   - ⭐⭐ **پکیج رایگانِ طرح اشتراک (1405/07):** اگر مشتری اشتراک فعالِ طرح‌محور دارد و پکیج در پکیج‌های همراهِ آن طرح است، همان `POST /packages/{slug}/purchase` را **بدون body** (بدون callback_url/pricing_plan_id/gateway) صدا بزنید:
     ```json
     {"is_free": true, "via_subscription": true, "plan": "اشتراک پایه", "license_key": "LIC-...",
      "expires_at": "2026-10-21 20:45:31", "days_remaining": 25, "download_token": "tok...",
      "message": "این پکیج با اشتراک فعال شما رایگان است."}
     ```
     idempotent است (لایسنس فعال موجود → همان کلید برمی‌گردد)؛ دانلود مثل بقیه مسیرها با download_token انجام می‌شود. برای تشخیص از قبل: در لیست/جزئیات پکیج، آیتم دارای `subscription.is_free_with_subscription=true` است؛ یا `GET /my-subscriptions → free_packages[].license_key` را بخوانید — اگر پر بود «نصب» است، اگر null بود با purchase همان‌جا رایگان نصبش کنید.
   - در بازگشت درگاه (GET/POST به callback_url شما با `transactionId`/`transaction_id`/`Authority`): سمت سرور `POST /api/v1/payments/{trx}/verify` را با هدرهای احراز صدا بزنید → `{paid, license_key, expires_at, days_remaining, download_token, signature, version}` → ذخیره در `installed_modules` (slug + license_key + license_expires_at + last_verified_at — همان چیزی که LicenseGuard مشتری می‌خواند). اگر لایسنس منقضی شده → همان خرید = تمدید.
   - ⚠️ برای درایور آزمایشی `local`: در فراخوانی verify پارامتر `?transactionId={trx}` را هم در query بفرستید (درایور local آن را از request می‌خواند؛ برای درگاه‌های واقعی بی‌اثر).
5. **کیت آماده**: پوشه `download/shop-payment-kit/` (۵ part + README-FA) برای پروژه فروشگاه مشتری + مرجع زنده `mini-services/shop-sim` (port 3001).
همه پاسخ‌های خطا فارسی‌اند: 403 «شما این پکیج را نخریده‌اید یا لایسنس شما فعال نیست.» وقتی لایسنس فعال نیست.

---

## ۵. جریان پرداخت (دو مسیر، یک سرویس)

### سرویس‌ها
- `PaymentService::createPayment(PackagePurchase $purchase, ?string $gateway = null)`:
  gateway پیش‌فرض `zarinpal`؛ `get_gateway_configs($gateway)` تنظیمات را از جدول gateways می‌خواند؛ callbackUrl = `$purchase->callback_url`؛ بعد از purchase با `transaction_id`/`gateway` آپدیت می‌شود؛ خروجی `payment_url`.
- `PaymentService::verifyPayment(string $transactionId, bool $renew = false)`:
  اگر pending → `Payment::via(...)->verify()`؛ موفق → `markAsPaid` + صدور لایسنس:
  `$renew` فقط از مسیر **وب** true می‌شود → `LicenseService::issueOrRenew` (اگر مشتری برای همین پکیج لایسنس active/expired دارد → `renewLicense`: انقضا جدید = انقضای قبلی + ماه‌های طرح، لایسنس قدیمی revoked، `renewed_from` ثبت می‌شود)؛ در API همان `issueLicense` مستقیم.
- مسیر رایگان (is_free یا final_price=0): بدون درگاه، خرید paid + لایسنس فوری.

### مسیر API (کلاینت لاراولی مشتری)
purchase با `callback_url` سایت خود مشتری → مشتری به درگاه می‌رود → درگاه برمی‌گردد به سایت مشتری → سایت مشتری `POST /payments/{trx}/verify` را صدا می‌زند → لایسنس/توکن می‌گیرد. (کال‌بک داخلی `/api/v1/payments/callback` فقط برای حالت بدون callback_url → به `payment.result` ریدایرکت می‌شود.)

### مسیر وب (فروشگاه)
checkout در `Shop\PackageShow::buy()`: کد آپدیت → Customer → گارد one_time (`hasCustomerUsed`) → رایگان؟ → لایسنس فوری + ریدایرکت به `payment.result` : خرید pending با `callback_url = route('payment.callback')` + gateway انتخابی → `createPayment($purchase, $gateway)` → ریدایرکت به `payment_url` (بانک) → بازگشت به `/payment/callback` (GET/POST، بدون CSRF) → `verifyPayment($trx, renew: true)` → ریدایرکت `/payment/result/{purchase}`:
- paid: پیام «پکیج «X» در پروژه شما تمدید شد (یا برای پروژه شما فعال شد) و می‌توانید از صفحه پکیج‌ها آن را دانلود کنید» + کلید لایسنس + انقضا (دکمه دانلود حذف شده — دانلود از صفحه پکیج‌های پروژه فروشگاه مشتری انجام می‌شود)
- failed: دلیل (`meta.fail_reason`) + تلاش مجدد؛ pending: دکمه «بررسی مجدد» → `recheck()`.

> **نکته:** درگاه‌ها در جدول `gateways` به‌صورت پیش‌فرض ۱۱ ردیف `is_active=0` دارند؛ برای فعال‌شدن خریدِ پرداختی باید در `/admin/settings/gateways` فعال + پیکربندی شوند. (در دمو فعال نیست — صفحه checkout پیام آمبر نشان می‌دهد.)
> درگateway «toman» داخل `get_gateway_configs` کد legacy به `auth()->user()->orders` دارد (از پروژه قبلی) — اگر فعالش کنید خطا می‌دهد؛ بقیه درگاه‌ها ساده‌اند.

---

## ۶. معماری فرانت‌اند

- **Vite** ورودی‌ها: `resources/css/app.css` + `resources/js/app.js` → خروجی `public/build` (هش‌دار، با manifest). بعد از هر تغییر JS/CSS: `bun run build`.
- **app.js** ماژول‌ها: `livewire-config` (CSRF + uri + progressBar)، `ckeditor`، `loading`؛ بعد `Alpine.data`ها (tabs/shell/toasts store) + eventهای `livewire:navigating/navigated` (نوار پیشرفت + initDarkMode) + `Livewire.start()`.
- **Layoutها**: `components/layouts/app` (پنل: سایدبار + هدر + دراور موبایل)، `components/layouts/guest` (لاگین/ریجیستر)، `components/layouts/shop` (فروشگاه: هدر sticky + فوتر + توست type-aware + گرادیان‌های `.shop-*` داخل بلاک style خودش).
- **کامپوننت‌های UI** (`components/`): btn (variants + **:loading** → wire:loading)، input، select، textarea، field (label+error)، modal (entangle)، tabs، badge، card، stat، avatar، empty، toggle، chart (SVG)، icon.
- **آیکون‌ها**: فقط از رجیستری `app/View/Components/Icon.php` (inline lucide). فهرست کامل در آن فایل؛ آیکون جدید = افزودن به مپ.
- **صفحه‌بندی RTL**: override در `resources/views/vendor/livewire/tailwind.blade.php` (نام‌گذاری مهم! Livewire 3 آن را با نام `livewire::tailwind` resolve می‌کند) → `$records->links()` خودکار RTL فارسی است.
- **توست**: trait `WithToasts` → `$this->toast('msg', 'error|warning|success')` → event → Alpine store → نمایش؛ در layoutهای guest/shop هم پشتیبانی می‌شود.
- **دارک‌مود**: کلاس `dark` روی html + localStorage؛ `initDarkMode()` در head برای جلوگیری از فلش.
- **SPA**: همه لینک‌های داخلی `wire:navigate` (به‌جز خروجی به بانک و دانلود).
- **CKEditor 4.25.1-lts** در `public/assets/ckeditor/` (self-hosted):
  - لودر lazy: `window.loadCkeditor()` (فقط با اولین باز شدن مودال اسکریپت تزریق می‌شود).
  - الگوی sync: `<x-ckeditor model="form.description" :value="..." />` → marker div بیرون از `wire:ignore` (با `data-editor-value` — Livewire آن را morph می‌کند) + textarea مخفی `wire:model` داخل `wire:ignore`؛ change → textarea.value + event `input` (Livewire property آپدیت می‌شود)؛ `Livewire.hook('morphed')` + MutationObserver روی marker → `setData` (server→ادیتور هنگام openEdit).
  - زبان fa، RTL، toolbar سفارشی (داخل `resources/js/ckeditor.js`).
  - ⚠️ **لایسنس**: نسخه-lts بدون کلید معتبر خودش را نابود می‌کند؛ در `ckeditor.js` یک placeholder key با کامنت گذاشته شده — برای پروداکشن کلید واقعی بخرید یا به 4.22.x متن‌باز سوئیچ کنید.
- **پیش‌نمایش آپلود تصویر**: Alpine `filePreview({multiple})` روی input فایل (فقط مودال ویرایش پکیج: thumbnail + gallery) — FileReader → data-URL.
- **لودینگ (جمع‌بندی UX):**
  - `resources/js/loading.js`: نوار پیشرفت (`.livewire-progress` در `document.head` — برای مقاومت به morph) روی **هر** commit (hook `Livewire.hook('commit')` + eventهای window `livewire:busy/idle`)؛ + auto-spinner/disabled روی هر دکمه wire:click/submit که :loading ندارد (رد دکمه‌های دارای `wire:loading.attr`).
  - در CSS: `[wire\:loading] { display:none }` چون باندل ESM لایووایر ۳ این استایل را تزریق نمی‌کند (bg مهم — بدون آن اسپینرها در حالت idle دیده می‌شوند).
  - دکمه‌های `x-btn :loading`: اسپینر `wire:loading` (مخفی پیش‌فرض) + آیکون عادی `wire:loading.remove`.

---

## ۶.۵ سیستم پیامک (Task 20)
- **SmsManager::send(templateKey, mobile, vars, loggable, once)** — تنها نقطه ارسال؛ هرگز Exception نمی‌دهد.
  - الگو: قالب از `sms_templates` (کلید + متن + variables + pattern_codes{driver}) → رندر → ارسال پترنی (اگر کد پترن درایور فعال ثبت شده) وگرنه متن خام (کاوه‌نگار/ملی‌پیامک) → ثبت در `sms_logs`.
  - `once:true` = idempotent برای callbackهای درگاه (تکرار صفحه کال‌بک → یک پیامک).
  - `notifyAdmin()` = کپی به `sms_admin_mobile` اگر `sms_notify_admin=1`.
- **۵ درایور** (`app/Services/Sms/Drivers/`): kavenegar (VerifyLookup + send.json)، melipayamak (REST BaseServiceNumber/SendSMS — بدون SOAP)، ippanel (patterns/pattern)، farazsms (api.iranpayamak.com/ws/v1/sms/pattern + Api-Key)، idehpardazan (RestfulSms UltraFastSend/direct).
- **تریگرها**: purchase_paid (WebPayment+ApiPaymentCallback)، subscription_paid (WebPayment)، subscription_activated (SubscriptionService::approve + DB::afterCommit)، subscription_renewed (grantFreeAccess وقتی لایسنس قبلی بود)، subscription_expiring/expired (فرمان `sms:check-subscriptions` روزانه ۹:00 — کران‌جاب schedule:run روی هاست لازم دارد).
- **تنظیمات**: تب «پیامک» در admin/settings (کلیدهای group=sms در جدول settings — helper `setting()` با کش ۶۰ثانیه).
- **UI**: admin/sms-templates (کارت‌گرید toggle+ویرایش+ارسال آزمایشی) + admin/sms-logs (جدول+مودال پاسخ خام).
- **قالب‌های سیستمی** (غیرقابل حذف): ۶ کلید بالا — متن/پترن‌ها قابل ویرایش.

## ۶.۶ رفع درگاه سامان (Task 20)
- خطای هاست اشتراکی «SOAP-ERROR: Parsing WSDL: Couldn't load from sep.shaparak.ir» → **SamanCached** (`config/payment.php map.saman`): WSDL با cURL(TLS1.2) دانلود → `WsdlCache` (storage/app/wsdl-cache، TTL ۲۴h، فقط importها لوکال می‌شوند — soap:address دست‌نخورده) → SoapClient با فایل لوکال.
- **درایور sep** (REST جدید — بدون SOAP): token از `onlinepg/onlinepg` + ریدایرکت `OnlinePG/OnlinePG` + verify `verifyTxnRandomSessionkey/ipg/VerifyTransaction`. در پنل: «سامان SEP (REST جدید)».
- `public/.user.ini` برای هاست CGI: upload_max_filesize=320M (رفع خطای «versionFile failed to upload»).
- آپلود تصویر: `ImageUploadService` حالا self-healing است (mkdir+chmod) و اگر public قابل نوشتن نبود → storage/app/public/uploads + سرو با روت `uploads/{path}` (UploadsServeController).

## ۷. گاتچاهای حیاتی (خلاصه تجربه — حتماً رعایت شود)

1. **Blade**: کلاس bare ممنوع (`Route::` → `\Illuminate\Support\Facades\Route::`)؛ `@php(...)` inline را با بلاک `@php @endphp` قاطی نکنید؛ interpolation `{{ }}` **داخل تگ کامپوننت** (`<x-icon ... {{ $x }} />`) تگ را غیرقابل‌کامپایل می‌کند → با `@if` شاخه‌بندی کنید.
2. **Livewire 3**: event `livewire:morphed` روی document **وجود ندارد** (فقط `Livewire.hook('morphed')`)؛ `wire:loading` بدون CSS خودتان در حالت اولیه مخفی نیست؛ id های SQLite AUTOINCREMENT بعد از rollback هم مصرف‌شده می‌مانند (در تست id را از DB بخوانید)؛ صفات فرم آرایه‌ای (`form[description]`) با wire:model خط‌نقطه‌ای (`form.description`) کار می‌کنند ولی فایل آپلودی باید null بماند نه [].
3. **tinker**: فایل تعاملی هنگ می‌کند → فقط `artisan tinker --execute="..."` با timeout.
4. **Carbon 3**: `diffInDays/Months` علامت‌دار است (آینده = مثبت)؛ جهت آرگومان‌ها را با abs تست کنید.
5. **curl**: `-L -X POST` متد را روی ریدایرکت نگه می‌دارد (405) → برای POST redirect-test از `--data` بدون `-X`.
6. **CSRF**: POST بانکی به `/payment/callback` بدون توکن می‌آید → در `bootstrap/app.php` با `validateCsrfTokens(except: ['payment/callback'])` مستثنا شده.
7. **سرویس مشترک**: `storage/logs/laravel.log` بین همه پروسه‌ها مشترک است — قبل از تست truncate کنید (`: > storage/logs/laravel.log`) و بعد چک کنید ۰ بایت است.
8. **PHP در این sandbox**: `/home/z/php-runtime/php` (استاتیک 8.4.13 با gd/sqlite3/zip...) — کامند `php` خالی موجود نیست.
9. **تست بدون رد гряз DB**: همیشه `DB::beginTransaction()` … `rollBack()` (در Livewire::test هم همین).
10. **route:cache** روی این اپ کار نمی‌کرد (closure route) — بعد از حذف closure `/` احتمالاً کار می‌کند ولی در dev نیازی نیست.
11. **agent-browser**: eval با $wire را در کوتیشن تکی بدهید (bash $wire را می‌خورد)؛ اسکرین‌شات full-page مودال‌های fixed را برش می‌زند → `scrollintoview` + viewport screenshot.
12. **rebuild فرانت**: بعد از هر تغییر `resources/js` یا `resources/css` → `bun run build` وگرنه `public/build` قدیمی سرو می‌شود.
13. **`$errors` همیشه باید share شود**: `AppServiceProvider::boot()` یک `View::share('errors', new ViewErrorBag)` پایه ست می‌کند. دلیل: Livewire در هر رندر `shareWithViews('errors', …)` می‌زند و اگر قبلاً چیزی share نشده باشد ('notfound')، پس از رندر آن را **unset** می‌کند → در رندرهای بعدی همان request «Undefined variable $errors» می‌شود (روی هاست‌هایی که middleware وب کامل اجرا نمی‌شود دیده شد). علاوه بر این، فایل‌های کلیدی (field/ckeditor/settings/packages-show) با الگوی `$errors ?? null` + `?->has` هم مقاوم شده‌اند.
14. **x-field کلید خطا**: کلیدهای اعتبارسنجی Livewire با پیشوند کامل ذخیره می‌شوند (`form.site_name`) ولی x-field فقط `for` ساده را می‌شناخت → حالا کاندیدها: `$error` صریح، `$for`، و `form.$for`.
15. **استقرار روی هاست اشتراکی (cPanel)**: اگر استایل‌ها نصفه/بدون padding لود شدند، CSS ساخته‌شده را چک کنید: docroot دامنه باید **روی پوشه `public`** باشد (نه ریشه پروژه) وگرنه `/build/assets/*` که URL مطلق است 404 می‌شود؛ بعد از تغییر فایل‌های ساخت، `php artisan view:clear` + هارد‌ریفرش (Ctrl+Shift+R) مرورگر.
16. **shetabit Multipay این نسخه (v6.4):**
    - `pay()` یک `RedirectionForm` برمی‌گرداند؛ متد `getPaymentUrl()` **وجود ندارد** → قبلاً createPayment با هر درگاه واقعی کرش می‌کرد. حالا: درایورهای GET بدون-ورودی (زرین‌پال) → payment_url = اکشن فرم؛ درایورهای POST → HTML فرم در `meta->payment_form` ذخیره + ریدایرکت به مسیر امضادار `payment.form` (روت وب + middleware signed، ۳۰ دقیقه)؛ درایور `local` → صفحه شبیه‌ساز `payment.fake-gateway` (داده‌ها از رکورد خرید ساخته می‌شوند، نیازی به فرم نیست).
    - کال‌بک `purchase($invoice, fn($driver, $trx))` — `$driver` **آبجکت درایور است نه رشته** → همیشه کلید gateway را ذخیره کنید.
    - `verify()` برگرداندن Receipt = موفق؛ ناموفق = `InvalidPaymentException` → متد `isPaid()` وجود ندارد (حذف شد؛ منطق بر اساس رسید/Exception).
    - درایور `local`: ① باید در `config/payment.php` بخش `drivers` (نه فقط `map`) ثبت شود وگرنه `Driver not found`؛ ② `verify()` درایور `Request::input('transactionId')` را از **درخواست جاری** می‌خواند → فراخوانِ verify باید `?transactionId=` در query بفرستد؛ ③ بازگشت درگاه با پارامتر camelCase `transactionId` است → کال‌بک‌های پنل (web+api) این کلید را هم می‌خوانند.
    - درایور local تنظیمات فرم (title/description/payButton/...) را از `get_gateway_configs` می‌گیرد (case «local» در helper با fallback‌ها)؛ ردیف gateway با key=local هم seed شده (پیش‌فرض غیرفعال).
17. **`latestVersion()` رابطه است** — `$package->latestVersion()` خودِ HasOne را برمی‌گرداند (truthy!)؛ همیشه `->first()` صدا بزنید. باگ API verifyPayment همین بود (TypeError در createDownloadToken).
18. **رکورد pending یتیم**: اگر `createPayment` بعد از `PackagePurchase::create` شکست بخورد، رکورد pending می‌ماند → در purchase API حالا `$purchase->delete()` در catch.


---

## ۸. روش تست استاندارد (playbook)

```bash
PHP=/home/z/php-runtime/php
# لاگین curl:
rm -f /tmp/cj.txt
T=$(curl -s -c /tmp/cj.txt http://localhost:8000/login | grep -o 'name="_token" value="[^"]*"' | sed 's/.*value="//;s/"//')
curl -s -b /tmp/cj.txt -c /tmp/cj.txt -X POST http://localhost:8000/login -d "_token=$T&email=admin@panel.test&password=secret123" -o /dev/null
curl -s -b /tmp/cj.txt http://localhost:8000/admin/projects | grep ...

# منطق بدون کثیف‌کردن DB:
$PHP artisan tinker --execute="
\ Livewire\Livewire::test(\App\Livewire\Shop\PackageShow::class, ['slug' => 'modern-shop-theme'])
  ->set('updateCode', '1F61148198FD') ->call('buy') ->assertRedirect(...);
"   # برای DB-write ها داخل beginTransaction/rollBack بگذارید
```
مرورگر: `agent-browser open/snapshot -i/fill/click/eval` (سشن نام‌دار برای جداسازی: `--session x`).

---

## ۹. وضعیت فعلی و دیتای دمو

- همه ۱۳ بخش ادمین + فروشگاه + پرداخت + CKEditor + لودینگ **کامل و تست‌شده** (curl + Livewire::test + browser E2E)؛ `laravel.log` پاک.
- دیتای دمو (seed با `demo-seed.php`): ۳ پروژه، ۵ پکیج (۴ فعال؛ `modern-shop-theme` رایگان)، ۱۵ نسخه، ۸ پلن، ۸ مشتری، ۶ لایسنس، ۶ خرید، ۵ اشتراک، ۴ آپدیت، ۱۱ درگاه (غیرفعال).
- فایل‌های ZIP دموِ کوچک (README داخل هر zip) در `storage/app/private/packages/*.zip` موجودند تا مسیر دانلود کاملاً تست‌شودنی باشد؛ در محیط واقعی فایل‌های ZIP واقعی هر نسخه را از پنل ادمین آپلود کنید (فایل دقیقاً در `file_path` ثبت‌شده ذخیره می‌شود).

### محدودیت‌ها / پیشنهادهای ادامه کار
1. **تنظیم درگاه واقعی** لازم است تا خریدِ پرداختی وب/API کار کند (فعال‌سازی + merchantId در `/admin/settings/gateways`).
2. کلید لایسنس CKEditor (یا سوئیچ به 4.22.x) برای پروداکشن.
3. ایمیل‌ها `MAIL_MAILER=log` — برای ریست پسورد واقعی تنظیم شود.
4. `verify-license` فقط برای همان customer است؛ خرید repeat در API همیشه لایسنس جدید صادر می‌کند (تمدید فقط مسیر وب) — اگر API هم renewal بخواهد: `verifyPayment($trx, renew: true)` در ApiPackageController.
5. آپلود فایل نسخه/آپدیت به ۱۲MB محدود (config/livewire.php قابل تغییر).
6. صف/وب‌هوک بانکی (IPN) پیاده نشده — فقط بازگشت مرورگر + verify کشیده‌شده.
7. تست‌های خودکار PHPUnit برای جریان خرید اضافه نشده (تست‌ها دستی/Livewire::test در کارهای قبلی بوده).

---

## ۱۰. درخت کلیدی فایل‌ها

```
admin-panel/
├─ app/
│  ├─ Livewire/            Dashboard, Projects/, Updates/, Packages/(Index+Show+…), Licenses/,
│  │                       Purchases/, Customers/, Subscriptions/, Users/, Reports/, Logs/,
│  │                       Settings/, Gateways, Sms/, Categories/(Index 🆕), Products/(Index+Show 🆕),
│  │                       Comments/(Index 🆕), Discounts/(Index 🆕 کدتخفیف), Shop/(Home+PackageShow
│  │                       +ProductShow 🆕+CustomerLogin/Profile 🆕+PackagesIndex/ProductsIndex 🆕
│  │                       +CommentSection 🆕+ProductPaymentResult+PurchaseTrack+PaymentResult
│  │                       +Subscriptions*), Concerns/WithToasts
│  ├─ Http/Controllers/    Api/(ApiPackageController, ApiPaymentCallbackController, UpdateController,
│  │                       ApiPackageDownloadController), Front/(WebPaymentController, UpdateDownloadController,
│  │                       ProductDownloadController 🆕, PaymentForm/Return, UploadsServeController)
│  ├─ Services/            PaymentService (با createProductPayment/verifyProductPayment 🆕), LicenseService,
│  │                       PackageApiAuthService, ImageUploadService (+uploadThumbnailProduct 🆕),
│  │                       SubscriptionService, Sms/(SmsManager + Drivers/۵درایور + Contracts),
│  │                       Payments/(SamanCached + SepRest + WsdlCache)
│  ├─ Models/              (بخش ۳)
│  └─ View/Components/     Icon.php (رجیستری آیکون)
├─ bootstrap/              app.php (CSRF except) + helpers.php (setting(), normalize_mobile(), fa_num, money, verta_date, get_gateway_configs…)
├─ config/general.php      supported_gateways (۱۲ درگاه: saman + sep جدید)
├─ database/               migrations/ + database.sqlite (دمو) + demo-seed.php در ریشه پروژه
├─ packages/shetabit/      پکیج محلی پرداخت (source of truth — با composer نصب نمی‌شود دوباره)
├─ public/assets/ckeditor/ CKEditor 4.25.1-lts (self-hosted)
├─ public/images/          placeholder های پکیج
├─ resources/
│  ├─ css/app.css          Tailwind 4 + توکن‌ها + .rich-content + [wire:loading] + .livewire-progress
│  ├─ js/{app.js, livewire-config.js, ckeditor.js, loading.js}
│  └─ views/
│     ├─ components/       (UI + layouts/(app|guest|shop) + ckeditor)
│     ├─ livewire/         (صفحات پنل + shop/ + products/ 🆕 + categories/ 🆕 + comments/ 🆕)
│     ├─ auth/             (login/register/passwords — طراحی جدید)
│     └─ vendor/livewire/tailwind.blade.php   (صفحه‌بندی RTL)
├─ routes/                 web.php + api/v1.php
└─ AI-CONTEXT.md           (همین فایل)
```

**سابقه کامل کارها**: `/home/z/my-project/worklog.md` (در محیط توسعه‌ی sandbox) — هر تسک با ID + جزئیات + گاتچاها ثبت شده.

---

**گاتچای WSDL (۲۸)**: کشِ دیسک خود PHP (`/tmp/wsdl-*`) می‌تواند WSDLِ خراب قبلی را نگه دارد و حتی بعد از رفع فایل، `SoapFault: Unable to parse URL` بدهد → `rm /tmp/wsdl-*` و تکرار تست.
**گاتچای WSDL (۲۹)**: در بازنویسی ارجاعات WSDL فقط `xsd:import/wsdl:import` را لوکال کنید؛ `soap:address location` آدرس واقعی درگاه است و نباید عوض شود.

**گاتچای Task 23 (محصولات)**:
- (۳۰) ستون `category` متنی در جدول packages با رابطه هم‌نام تداخل دارد → رابطه حتماً `categoryModel()` نام‌گذاری شد (attribute همیشه بر relation اولویت دارد و رشته برمی‌گرداند).
- (۳۱) `final_price` در PackagePricingPlan accessor است (نه ستون) → در SQL‌های مرتب‌سازی/فیلتر قیمت باید `MIN(COALESCE(pp.discount_price, pp.price))` استفاده شود نه `pp.final_price`.
- (۳۲) مایگریشن `mimes:zip` روی محتوای تصادفی فیک fail می‌شود (content-sniffing) — در تست با فایل ZIP واقعی (امضای PK) تست کنید.
- (۳۳) ستون `payment.result.product/{purchase}` و توکن‌های دانلود محصول public هستند؛ URL صفحه نتیجه id ساده دارد (مثل packages) — پس از پرداخت فقط دارنده لینک دانلود می‌گیرد؛ اگر نیاز به محرمانگی بیشتر شد signed URL اضافه شود.
- (۳۴) ریدایرکت درایور local به `APP_URL` ساخته می‌شود — برای تست محلی `APP_URL=http://127.0.0.1:8000` باشد وگرنه پورت از URL می‌افتد (Connection refused).

*آخرین به‌روزرسانی: بعد از Task 23 (فروشگاه محصولات قابل‌فروش: دسته‌بندی مشترک + صفحه تکی + دیدگاه‌ها + نسخه‌بندی + خرید مهمان + دانلود توکنی + فیلترهای فروشگاه). همه‌چیز E2E تست‌شده و سبز است.*
