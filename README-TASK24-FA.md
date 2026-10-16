# 🎟️ Task 24 — سیستم کد تخفیف برای همه خریدها (پکیج / محصول / اشتراک)

> قابلیت جدید: کدهای تخفیف (کوپن) با **تاریخ انقضا**، **محدودیت تعداد استفاده** و
> **محدوده اعتبار** — قابل اعمال روی هر سه نوع خرید فروشگاه: پکیج‌ها، محصولات (پروژه‌ها)
> و طرح‌های اشتراک.

---

## ۱) مدل داده

### جدول جدید `discount_codes`

| ستون | توضیح |
|---|---|
| `code` | کد یکتا (۶۴ کاراکتر) — همیشه با حروف بزرگ ذخیره می‌شود |
| `type` | `percent` (درصدی) یا `fixed` (مبلغ ثابت به تومان) |
| `value` | درصد (۱..۱۰۰) یا مبلغ ثابت (حداقل ۱٬۰۰۰ تومان در اعتبارسنجی) |
| `max_uses` | حداکثر تعداد استفاده — **null = نامحدود** |
| `used_count` | شمارنده مصرف (با increment اتمیک بالا می‌رود) |
| `expires_at` | تاریخ انقضا — **null = بدون انقضا** |
| `applies_packages` / `applies_products` / `applies_subscriptions` | محدوده اعتبار کد (حداقل یکی باید فعال باشد) |
| `is_active` | فعال/غیرفعال |
| `notes` | یادداشت داخلی مدیر |

### ستون‌های جدید در جدول‌های خرید

- `package_purchases` و `product_purchases`:
  - `discount_code_id` (FK → `discount_codes`، `nullOnDelete` — سابقه خرید بعد از حذف کد حفظ می‌شود)
  - `original_amount` — مبلغ قبل از کد تخفیف
  - `discount_amount` — مبلغ کسرشده (پیش‌فرض ۰)
  - ⚠️ **ستون `amount` از این به بعد «مبلغ نهایی بعد از تخفیف» است** — درگاه هم بر همین مبنا شارژ و verify می‌کند. رکوردهای قدیمی (بدون کوپن) چون `discount_amount = 0` دارند کاملاً سازگارند.
- `subscription_orders`: فقط `discount_code_id` (ستون‌های `amount/discount/final_amount` از قبل وجود داشتند). snapshot کوپن علاوه بر این در `meta.coupon` ذخیره می‌شود تا حتی بعد از حذف کد هم در گزارش‌ها بماند.

**اجرای مایگریشن روی سرور پس از pull الزامی است:**

```bash
php artisan migrate
```

---

## ۲) منطق اعمال تخفیف — `DiscountService::resolve()`

```php
DiscountService::resolve(
    ?string $codeInput,       // ورودی کاربر (خالی = بدون تخفیف → ok)
    string  $context,         // DiscountCode::CONTEXT_PACKAGE | _PRODUCT | _SUBSCRIPTION
    int     $amount           // مبلغ مبنا (مثلاً final_price طرح)
): array { ok, message, code, discount, final }
```

ترتیب اعتبارسنجی: وجود کد → فعال‌بودن → انقضا → ظرفیت → محدوده اعمال.
خروجی `resolve` همیشه سمت سرور و **قبل از ایجاد هر خرید** دوباره محاسبه می‌شود؛
پیش‌نمایش قیمت در UI فقط نمایشی است (منبع حقیقت سرور است).

- تخفیف درصدی: `floor(amount × value / 100)`
- تخفیف ثابت: `value` (بیشتر از مبلغ خرید کاپ می‌شود؛ مبلغ نهایی هرگز منفی نمی‌شود)
- در اشتراک: تخفیف کوپن روی «مبلغ بعد از تخفیف خودِ طرح» محاسبه می‌شود.

---

## ۳) مصرف شمارنده — دقیقاً یک‌بار در هر خرید

`DiscountCode::recordUsage()` یک `increment` اتمیک با گارد ظرفیت است:

```sql
UPDATE discount_codes SET used_count = used_count + 1
WHERE id = ? AND (max_uses IS NULL OR used_count < max_uses)
```

نقاط مصرف:

| مسیر | کجا مصرف می‌شود |
|---|---|
| پکیج/محصول/اشتراکِ پرداختی | داخل `markAsPaid()` هر سه مدل — همزمان با تأیید پرداخت درگاه |
| مسیر رایگان (پکیج رایگان، پروژه رایگان، طرح رایگان یا کدِ ۱۰۰٪) | مستقیماً بعد از `create()` خرید (چون از `markAsPaid` عبور نمی‌کند) |

### 🛡️ گارد idempotency (جدید)

هر سه مدل `PackagePurchase`، `ProductPurchase` و `ProductPurchase` الان در ابتدای
`markAsPaid()` چک می‌کنند:

```php
if ($this->isPaid()) {
    return; // کال‌بک تکراری درگاه → هیچ‌چیز دوباره مصرف نمی‌شود
}
```

این گارد در برابر **کال‌بک تکراری درگاه‌ها** (که در دنیای واقعی رخ می‌دهد) از
شمارش دوباره‌ی شمارنده کوپن، `incrementSales()` محصول و صدور لایسنس تکراری جلوگیری می‌کند.

---

## ۴) UI فروشگاه — پارشیال مشترک

`resources/views/livewire/shop/partials/discount-field.blade.php` در هر سه صفحه‌ی
خرید (`package-show`، `product-show`، `subscriptions-index`) استفاده می‌شود.

**قرارداد کامپوننت والد:**

```php
public string $discountCode = '';   // ورودی کاربر
public string $appliedCode  = '';   // کد اعمال‌شده (نرمال‌شده، بعد از اعتبارسنجی سرور)

#[Computed]
public function appliedDiscount(): ?array { ... } // resolve() مجدد برای وضعیت فعلی

public function applyDiscount(DiscountService $s): void {}  // «اعمال»
public function removeDiscount(): void {}                    // ✕ حذف
```

- کد اعمال‌شده: جعبه سبز با مقدار تخفیف («۱۵٪ تخفیف · ۲۳۲٬۵۰۰ تومان کمتر») + دکمه حذف
- تخفیف ۱۰۰٪ → مسیر رایگان: درگاه مخفی، دکمه «دریافت رایگان با کد تخفیف»، ثبت فوری
- ⚠️ گاتچا: اگر مبلغ مبنا صفر باشد (پکیج رایگان/طرح رایگان) کدِ تایپ‌شده‌ی نیمه‌کاره
  **نادیده** گرفته می‌شود نه خطا — تا جلوی دریافت رایگان گرفته نشود.

---

## ۵) پنل مدیریت — `/admin/discounts`

کامپوننت `Livewire\Discounts\Index` + ویو `livewire/discounts/index.blade.php`:

- مودال ایجاد/ویرایش با **تولید خودکار کد** (دکمه ✨ — بدون حروف مبهم I/O/0/1)
- نوع تخفیف: درصدی / مبلغ ثابت (با تغییر برچسب hint)
- حداکثر تعداد استفاده (خالی = نامحدود) + تاریخ انقضا (`datetime-local`، خالی = بدون انقضا)
- چک‌باکس محدوده اعتبار: پکیج / محصول / اشتراک (حداقل یکی — خطای سرور اگر هیچ‌کدام)
- فیلتر وضعیت: فعال و قابل استفاده / غیرفعال / منقضی / تکمیل ظرفیت + جستجو
- جدول: کد، مقدار، محدوده (badge)، استفاده `n / m (∞)`، انقضا (شمسی با `verta_date`)،
  وضعیت (کلیک = toggle فعال/غیرفعال)، ویرایش/حذف
- حذف: خریدهای قبلی فقط `discount_code_id`شان null می‌شود؛ سابقه و snapshot حفظ است
- لینک در سایدبار ادمین: «کدهای تخفیف» با آیکون `ticket-percent`

---

## ۶) فایل‌های تغییر یافته/جدید

```
🆕 app/Models/DiscountCode.php
🆕 app/Services/DiscountService.php
🆕 app/Livewire/Discounts/Index.php
🆕 resources/views/livewire/discounts/index.blade.php
🆕 resources/views/livewire/shop/partials/discount-field.blade.php
🆕 database/migrations/2026_10_17_000001_create_discount_codes_table.php
🆕 database/migrations/2026_10_17_000002_add_discount_fields_to_purchases_tables.php

✏️ app/Livewire/Shop/PackageShow.php        (applyDiscount/removeDiscount/appliedDiscount + buy)
✏️ app/Livewire/Shop/ProductShow.php        (همان + دکمه واکنش‌گرا به تخفیف ۱۰۰٪)
✏️ app/Livewire/Shop/SubscriptionsIndex.php (همان + priceWithDiscount() برای پیش‌نمایش هر طرح)
✏️ app/Services/SubscriptionService.php     (purchase(..., ?DiscountCode $coupon) + meta.coupon)
✏️ app/Models/{PackagePurchase,ProductPurchase,SubscriptionOrder}.php (رابطه + markAsPaid + idempotency)
✏️ app/View/Components/Icon.php             (آیکون ticket-percent)
✏️ resources/views/components/layouts/app.blade.php (لینک سایدبار)
✏️ resources/views/livewire/shop/*.blade.php (سه صفحه خرید + سه صفحه نتیجه)
✏️ resources/views/livewire/purchases/{index,show}.blade.php (badge تخفیف در لیست/جزئیات ادمین)
✏️ resources/views/livewire/subscriptions/orders.blade.php  (badge تخفیف در سفارش‌های اشتراک)
✏️ routes/web.php                            (admin.discounts.index)
```

---

## ۷) چک‌لیست تست دستی (پس از deploy)

1. `php artisan migrate` ← دو مایگریشن جدید
2. `/admin/discounts` → کد بسازید (مثلاً `WELCOME10` درصده، ظرفیت ۵، انقضا +۷ روز، همه محدوده‌ها)
3. خرید محصول پولی → کد را وارد و «اعمال» → پیش‌نمایش قیمت کم شود → پرداخت (درگاه تست) →
   در صفحه نتیجه و لیست خریدهای ادمین، badge تخفیف و شمارنده `used_count = 1`
4. همان کد در خرید پکیج و خرید طرح اشتراک → کار کند (چون محدوده شامل هر سه است)
5. کدی با محدوده «فقط محصول» در صفحه اشتراک → خطای «برای این نوع خرید قابل استفاده نیست»
6. کد با `max_uses=1` را دو بار مصرف کنید → بار دوم: «ظرفیت استفاده تکمیل شده است»
7. کد منقضی‌شده → «این کد تخفیف منقضی شده است»
8. کد ۱۰۰٪ روی محصول پولی → «رایگان با کد تخفیف» + دریافت فوری بدون درگاه
