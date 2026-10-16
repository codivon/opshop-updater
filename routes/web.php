<?php

use App\Http\Controllers\Front\UpdateDownloadController;
use App\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;

/*
|----------------------------------------------------------------------
| سرو فایل‌های uploads (مسیر جایگزین وقتی public قابل نوشتن نیست)
|----------------------------------------------------------------------
*/
Route::get('uploads/{path}', \App\Http\Controllers\Front\UploadsServeController::class)
    ->where('path', '(.*)')
    ->name('uploads.serve');

/*
|--------------------------------------------------------------------------
| فروشگاه عمومی (Storefront)
|--------------------------------------------------------------------------
*/
Route::get('/', \App\Livewire\Shop\Home::class)->name('shop.home');
Route::get('packages/{slug}', \App\Livewire\Shop\PackageShow::class)->name('shop.package');

// صفحات مستقل فروشگاه (لیست کامل با فیلترها)
Route::get('shop/packages', \App\Livewire\Shop\PackagesIndex::class)->name('shop.packages');
Route::get('shop/products', \App\Livewire\Shop\ProductsIndex::class)->name('shop.products');

// محصولات قابل‌فروش (پروژه‌ها — خرید مهمان بدون ثبت‌نام)
Route::get('products/{slug}', \App\Livewire\Shop\ProductShow::class)->name('shop.product');
Route::get('payment/result/product/{purchase}', \App\Livewire\Shop\ProductPaymentResult::class)->name('payment.result.product');
Route::get('products/download/{token}', [\App\Http\Controllers\Front\ProductDownloadController::class, 'download'])->name('products.download');

/*
|--------------------------------------------------------------------------
| احراز هویت مشتریان فروشگاه (ورود فقط با کد آپدیت)
|--------------------------------------------------------------------------
*/
Route::get('customer/login', \App\Livewire\Shop\CustomerLogin::class)
    ->name('shop.login')
    ->middleware('guest:customer');

// خروج مشتری
// نکته: session()->invalidate() نمی‌زنیم چون کل سشن (از جمله لاگین مدیر در همان مرورگر) را نابود می‌کند؛
// فقط داده‌های گارد مشتری پاک می‌شود و توکن CSRF تازه می‌شود.
Route::post('customer/logout', function () {
    \Illuminate\Support\Facades\Auth::guard('customer')->logout();
    session()->forget('shop_update_code');
    session()->regenerateToken();

    return redirect()->route('shop.home');
})->name('shop.logout')->middleware('auth:customer');

Route::get('customer/profile', \App\Livewire\Shop\CustomerProfile::class)
    ->name('customer.profile')
    ->middleware('auth:customer');

// ⚠️ دانلود مستقیم پکیج برای مشتری عمداً وجود ندارد:
// مشتری پکیج را فقط با «کد آپدیت» داخل فروشگاه‌ساز خودش نصب/به‌روزرسانی می‌کند
// (جریان API آپدیت‌سنتر) و فایل از طریق پنل قابل دریافت نیست.

// طرح‌های اشتراک (فروش عمومی + نتیجه خرید اشتراک)
Route::get('subscriptions', \App\Livewire\Shop\SubscriptionsIndex::class)->name('shop.subscriptions');
Route::get('subscription/result/{order}', \App\Livewire\Shop\SubscriptionResult::class)->name('subscription.result');

// پرداخت (عمومی)
Route::get('payment/callback', [\App\Http\Controllers\Front\WebPaymentController::class, 'callback'])->name('payment.callback');
Route::post('payment/callback', [\App\Http\Controllers\Front\WebPaymentController::class, 'callback'])->name('payment.callback.post');
Route::get('payment/result/{purchase}', \App\Livewire\Shop\PaymentResult::class)->name('payment.result');

// صفحه «نتیجه پرداخت» (مرحله میانی پس از درگاه):
// وضعیت پرداخت + شمارش معکوس ۱۰ ثانیه‌ای + دکمه بازگشت به callback_url فروشگاه
Route::get('payment/return/{purchase}', [\App\Http\Controllers\Front\PaymentReturnController::class, 'show'])
    ->name('payment.return');

// همان صفحه برای سفارش‌های اشتراک (API با callback_url بیرونی)
Route::get('payment/return/subscription/{order}', [\App\Http\Controllers\Front\PaymentReturnController::class, 'showSubscription'])
    ->name('payment.return.subscription');

// فرم پرداخت درایورهای فرم‌محور + شبیه‌ساز درگاه آزمایشی (local)
// payment_url خریدهای API برای این درایورها به این مسیر امضادار اشاره می‌کند.
Route::get('payment/form/{purchase}', [\App\Http\Controllers\Front\PaymentFormController::class, 'show'])
    ->name('payment.form')
    ->middleware('signed');

// همان فرم برای سفارش‌های اشتراک
Route::get('payment/form/subscription/{order}', [\App\Http\Controllers\Front\PaymentFormController::class, 'showSubscription'])
    ->name('payment.form.subscription')
    ->middleware('signed');

// همان فرم برای خرید محصولات قابل‌فروش
Route::get('payment/form/product/{productPurchase}', [\App\Http\Controllers\Front\PaymentFormController::class, 'showProduct'])
    ->name('payment.form.product')
    ->middleware('signed');

// مسیر عمومی دانلود آپدیت (بدون احراز هویت)
Route::get('get-update/{code}', [UpdateDownloadController::class, 'download'])->name('public.download');

\Illuminate\Support\Facades\Auth::routes();

/*
|--------------------------------------------------------------------------
| پنل مدیریت – کامپوننت‌های تمام‌صفحه Livewire (SPA با wire:navigate)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware(['auth'])->group(function () {
    Route::get('/', Dashboard::class)->name('dashboard');

    // محصولات
    Route::get('projects', \App\Livewire\Projects\Index::class)->name('projects.index');
    Route::get('updates', \App\Livewire\Updates\Index::class)->name('updates.index');
    Route::get('packages', \App\Livewire\Packages\Index::class)->name('packages.index');
    Route::get('packages/{package}', \App\Livewire\Packages\Show::class)->name('packages.show');

    // محصولات (پروژه‌های قابل‌فروش) + دسته‌بندی‌ها + دیدگاه‌ها
    Route::get('products', \App\Livewire\Products\Index::class)->name('products.index');
    Route::get('products/{product}', \App\Livewire\Products\Show::class)->name('products.show');
    Route::get('categories', \App\Livewire\Categories\Index::class)->name('categories.index');
    Route::get('discounts', \App\Livewire\Discounts\Index::class)->name('discounts.index');
    Route::get('comments', \App\Livewire\Comments\Index::class)->name('comments.index');

    // فروش و مشتریان
    Route::get('licenses', \App\Livewire\Licenses\Index::class)->name('licenses.index');
    Route::get('licenses/{license}', \App\Livewire\Licenses\Show::class)->name('licenses.show');
    Route::get('purchases', \App\Livewire\Purchases\Index::class)->name('purchases.index');
    Route::get('purchases/{purchase}', \App\Livewire\Purchases\Show::class)->name('purchases.show');
    Route::get('customers', \App\Livewire\Customers\Index::class)->name('customers.index');
    Route::get('subscriptions', \App\Livewire\Subscriptions\Index::class)->name('subscriptions.index');

    // طرح‌های اشتراک + درخواست‌ها/تأییدها
    Route::get('subscription-plans', \App\Livewire\Plans\Index::class)->name('plans.index');
    Route::get('subscription-orders', \App\Livewire\Subscriptions\Orders::class)->name('subscriptions.orders');

    // سیستم
    Route::get('reports', \App\Livewire\Reports\Index::class)->name('reports.index');
    Route::get('logs', \App\Livewire\Logs\Index::class)->name('logs.index');
    Route::get('settings', \App\Livewire\Settings\Index::class)->name('settings.index');
    Route::get('settings/gateways', \App\Livewire\Gateways::class)->name('settings.gateways');
    Route::get('users', \App\Livewire\Users\Index::class)->name('users.index');

    // پیامک (قالب‌ها + لاگ ارسال)
    Route::get('sms-templates', \App\Livewire\Sms\Templates::class)->name('sms.templates');
    Route::get('sms-logs', \App\Livewire\Sms\Logs::class)->name('sms.logs');
});
