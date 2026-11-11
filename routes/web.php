<?php

use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\CaptchaImageController;
use App\Http\Controllers\Front\PaymentFormController;
use App\Http\Controllers\Front\PaymentReturnController;
use App\Http\Controllers\Front\ProductDownloadController;
use App\Http\Controllers\Front\TemplateDownloadController;
use App\Http\Controllers\Front\UpdateDownloadController;
use App\Http\Controllers\Front\UploadsServeController;
use App\Http\Controllers\Front\WebPaymentController;
use App\Http\Middleware\EnsureAdminRole;
use App\Livewire\ApiDocs;
use App\Livewire\Dashboard;
use App\Livewire\Gateways;
use App\Livewire\Messages\Index;
use App\Livewire\Packages\Show;
use App\Livewire\Settings\Developer;
use App\Livewire\Settings\HomePage;
use App\Livewire\Settings\Profile;
use App\Livewire\Settings\Security;
use App\Livewire\Shop\Changelog;
use App\Livewire\Shop\CustomerLogin;
use App\Livewire\Shop\CustomerProfile;
use App\Livewire\Shop\Home;
use App\Livewire\Shop\PackageCheckout;
use App\Livewire\Shop\PackageShow;
use App\Livewire\Shop\PackagesIndex;
use App\Livewire\Shop\PaymentResult;
use App\Livewire\Shop\ProductPaymentResult;
use App\Livewire\Shop\ProductShow;
use App\Livewire\Shop\ProductsIndex;
use App\Livewire\Shop\SubscriptionResult;
use App\Livewire\Shop\SubscriptionsIndex;
use App\Livewire\Shop\TemplatePaymentResult;
use App\Livewire\Shop\TemplateShow;
use App\Livewire\Shop\TemplatesIndex;
use App\Livewire\Sms\Logs;
use App\Livewire\Sms\Templates;
use App\Livewire\Subscriptions\Orders;
use App\Livewire\Tools\Backup;
use App\Services\CaptchaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|----------------------------------------------------------------------
| سرو فایل‌های uploads (مسیر جایگزین وقتی public قابل نوشتن نیست)
|----------------------------------------------------------------------
*/
Route::get('uploads/{path}', UploadsServeController::class)
    ->where('path', '(.*)')
    ->name('uploads.serve');

/*
|--------------------------------------------------------------------------
| فروشگاه عمومی (Storefront)
|--------------------------------------------------------------------------
*/
Route::get('/', Home::class)->name('shop.home');
Route::get('packages/{slug}', PackageShow::class)->name('shop.package');

// صفحات مستقل فروشگاه (لیست کامل با فیلترها)
Route::get('shop/packages', PackagesIndex::class)->name('shop.packages');
Route::get('shop/products', ProductsIndex::class)->name('shop.products');
Route::get('shop/templates', TemplatesIndex::class)->name('shop.templates');

// قالب‌های قابل‌فروش (خرید مهمان بدون ثبت‌نام — مانند محصولات)
Route::get('templates/{slug}', TemplateShow::class)->name('shop.template');
Route::get('payment/result/template/{purchase}', TemplatePaymentResult::class)->name('payment.result.template');
Route::get('templates/download/{token}', [TemplateDownloadController::class, 'download'])->name('templates.download');

// محصولات قابل‌فروش (پروژه‌ها — خرید مهمان بدون ثبت‌نام)
Route::get('products/{slug}', ProductShow::class)->name('shop.product');
Route::get('payment/result/product/{purchase}', ProductPaymentResult::class)->name('payment.result.product');
Route::get('products/download/{token}', [ProductDownloadController::class, 'download'])->name('products.download');

/*
|--------------------------------------------------------------------------
| احراز هویت مشتریان فروشگاه (ورود فقط با کد آپدیت)
|--------------------------------------------------------------------------
*/
Route::get('customer/login', CustomerLogin::class)
    ->name('shop.login')
    ->middleware('guest:customer');

// خروج مشتری
// نکته: session()->invalidate() نمی‌زنیم چون کل سشن (از جمله لاگین مدیر در همان مرورگر) را نابود می‌کند؛
// فقط داده‌های گارد مشتری پاک می‌شود و توکن CSRF تازه می‌شود.
Route::post('customer/logout', function () {
    Auth::guard('customer')->logout();
    session()->forget('shop_update_code');
    session()->regenerateToken();

    return redirect()->route('shop.home');
})->name('shop.logout')->middleware('auth:customer');

Route::get('customer/profile', CustomerProfile::class)
    ->name('customer.profile')
    ->middleware('auth:customer');

// ⚠️ دانلود مستقیم پکیج برای مشتری عمداً وجود ندارد:
// مشتری پکیج را فقط با «کد آپدیت» داخل فروشگاه‌ساز خودش نصب/به‌روزرسانی می‌کند
// (جریان API آپدیت‌سنتر) و فایل از طریق پنل قابل دریافت نیست.

// طرح‌های اشتراک (فروش عمومی + نتیجه خرید اشتراک)
Route::get('subscriptions', SubscriptionsIndex::class)->name('shop.subscriptions');
Route::get('subscription/result/{order}', SubscriptionResult::class)->name('subscription.result');

// صفحهٔ عمومی تغییرات (changelog) — از پنل «امنیت و امکانات» قابل خاموش‌کردن (خاموش = 404)
Route::get('changelog', Changelog::class)->name('shop.changelog');

// پرداخت (عمومی)
Route::get('payment/callback', [WebPaymentController::class, 'callback'])->name('payment.callback');
Route::post('payment/callback', [WebPaymentController::class, 'callback'])->name('payment.callback.post');
Route::get('payment/result/{purchase}', PaymentResult::class)->name('payment.result');

// صفحه تسویه خریدهای API (مرحله میانی پیش از درگاه):
// نام/تصویر پکیج + طرح انتخابی + کد تخفیف + دکمه پرداخت/انصراف
// لینک امضادار ۲۴ساعته از پاسخ API (checkout_url) می‌آید.
Route::get('checkout/package/{purchase}', PackageCheckout::class)
    ->name('shop.checkout')
    ->middleware('signed');

// صفحه «نتیجه پرداخت» (مرحله میانی پس از درگاه):
// وضعیت پرداخت + شمارش معکوس ۱۰ ثانیه‌ای + دکمه بازگشت به callback_url فروشگاه
Route::get('payment/return/{purchase}', [PaymentReturnController::class, 'show'])
    ->name('payment.return');

// همان صفحه برای سفارش‌های اشتراک (API با callback_url بیرونی)
Route::get('payment/return/subscription/{order}', [PaymentReturnController::class, 'showSubscription'])
    ->name('payment.return.subscription');

// فرم پرداخت درایورهای فرم‌محور + شبیه‌ساز درگاه آزمایشی (local)
// payment_url خریدهای API برای این درایورها به این مسیر امضادار اشاره می‌کند.
Route::get('payment/form/{purchase}', [PaymentFormController::class, 'show'])
    ->name('payment.form')
    ->middleware('signed');

// همان فرم برای سفارش‌های اشتراک
Route::get('payment/form/subscription/{order}', [PaymentFormController::class, 'showSubscription'])
    ->name('payment.form.subscription')
    ->middleware('signed');

// همان فرم برای خرید محصولات قابل‌فروش
Route::get('payment/form/product/{productPurchase}', [PaymentFormController::class, 'showProduct'])
    ->name('payment.form.product')
    ->middleware('signed');

// همان فرم برای خرید قالب‌ها
Route::get('payment/form/template/{templatePurchase}', [PaymentFormController::class, 'showTemplate'])
    ->name('payment.form.template')
    ->middleware('signed');

// مسیر عمومی دانلود آپدیت (بدون احراز هویت) — سقف ۳۰ درخواست در دقیقه برای هر IP (ضد جست‌وجوی کد)
Route::get('get-update/{code}', [UpdateDownloadController::class, 'download'])
    ->name('public.download')
    ->middleware('throttle:30,1');

/*
|--------------------------------------------------------------------------
| کپچای خودمیزبان — تصویر سؤال + تازه‌سازی (لینک «سؤال دیگر» فرم‌های کلاسیک)
|--------------------------------------------------------------------------
*/
Route::get('captcha/image', [CaptchaImageController::class, 'show'])
    ->name('captcha.image')
    ->middleware('throttle:120,1');

Route::get('captcha/refresh', function () {
    CaptchaService::fresh();

    return back();
})->name('captcha.refresh');

Auth::routes();

/*
|--------------------------------------------------------------------------
| ورود دومرحله‌ای مدیر (2FA) — بیرون از گروه auth (کاربر هنوز کامل لاگین نیست)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('two-factor', [TwoFactorController::class, 'show'])->name('2fa.challenge');
    Route::post('two-factor', [TwoFactorController::class, 'verify'])->name('2fa.verify');
    Route::post('two-factor/resend', [TwoFactorController::class, 'resend'])->name('2fa.resend');
});

/*
|--------------------------------------------------------------------------
| پنل مدیریت – کامپوننت‌های تمام‌صفحه Livewire (SPA با wire:navigate)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', EnsureAdminRole::class])->group(function () {
    Route::get('/', Dashboard::class)->name('dashboard');

    // پروفایل مدیر (ویرایش نام/ایمیل + تغییر رمز عبور)
    Route::get('profile', Profile::class)->name('profile');

    // محتوای فروشگاه — صفحهٔ اصلی (ویجت‌ها) + پیام‌های تماس (Task 56)
    Route::get('settings/home', HomePage::class)->name('settings.home');
    Route::get('messages', Index::class)->name('messages.index');

    // محصولات
    Route::get('projects', App\Livewire\Projects\Index::class)->name('projects.index');
    Route::get('updates', App\Livewire\Updates\Index::class)->name('updates.index');
    Route::get('packages', App\Livewire\Packages\Index::class)->name('packages.index');
    Route::get('packages/{package}', Show::class)->name('packages.show');

    // محصولات (پروژه‌های قابل‌فروش) + قالب‌ها + دسته‌بندی‌ها + دیدگاه‌ها
    Route::get('products', App\Livewire\Products\Index::class)->name('products.index');
    Route::get('products/{product}', App\Livewire\Products\Show::class)->name('products.show');
    Route::get('templates', App\Livewire\Templates\Index::class)->name('templates.index');
    Route::get('templates/{template}', App\Livewire\Templates\Show::class)->name('templates.show');
    Route::get('categories', App\Livewire\Categories\Index::class)->name('categories.index');
    Route::get('discounts', App\Livewire\Discounts\Index::class)->name('discounts.index');
    Route::get('comments', App\Livewire\Comments\Index::class)->name('comments.index');

    // فروش و مشتریان
    Route::get('licenses', App\Livewire\Licenses\Index::class)->name('licenses.index');
    Route::get('licenses/{license}', App\Livewire\Licenses\Show::class)->name('licenses.show');
    Route::get('purchases', App\Livewire\Purchases\Index::class)->name('purchases.index');
    Route::get('purchases/{purchase}', App\Livewire\Purchases\Show::class)->name('purchases.show');
    Route::get('customers', App\Livewire\Customers\Index::class)->name('customers.index');
    Route::get('subscriptions', App\Livewire\Subscriptions\Index::class)->name('subscriptions.index');

    // طرح‌های اشتراک + درخواست‌ها/تأییدها
    Route::get('subscription-plans', App\Livewire\Plans\Index::class)->name('plans.index');
    Route::get('subscription-orders', Orders::class)->name('subscriptions.orders');

    // تیکت‌های پشتیبانی (فقط مشتریِ دارای اشتراک فعال می‌تواند تیکت ثبت کند)
    Route::get('tickets', App\Livewire\Tickets\Index::class)->name('tickets.index');

    // سیستم
    Route::get('reports', App\Livewire\Reports\Index::class)->name('reports.index');
    Route::get('logs', App\Livewire\Logs\Index::class)->name('logs.index');
    Route::get('api-docs', ApiDocs::class)->name('api-docs');

    // دانلود فایل مشخصات کامل API برای ایجنت هوش مصنوعی (Markdown)
    Route::get('api-docs/agent-spec', function () {
        $path = base_path('docs/API-AGENT-SPEC.md');
        abort_unless(is_file($path), 404, 'فایل مشخصات ایجنت یافت نشد.');

        return response()->download($path, 'opshop-api-agent-spec.md', ['Content-Type' => 'text/markdown; charset=UTF-8']);
    })->name('api-docs.agent-spec');
    Route::get('settings', App\Livewire\Settings\Index::class)->name('settings.index');
    Route::get('settings/gateways', Gateways::class)->name('settings.gateways');
    Route::get('settings/security', Security::class)->name('settings.security');

    // تنظیمات برنامه‌نویس: محیط/دیباگ (.env)، کش‌ها، بهینه‌سازی و مایگریشن‌های در انتظار
    Route::get('settings/developer', Developer::class)->name('settings.developer');
    Route::get('users', App\Livewire\Users\Index::class)->name('users.index');
    Route::get('backup', Backup::class)->name('tools.backup');

    // پیامک (قالب‌ها + لاگ ارسال)
    Route::get('sms-templates', Templates::class)->name('sms.templates');
    Route::get('sms-logs', Logs::class)->name('sms.logs');
});
