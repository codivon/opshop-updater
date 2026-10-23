<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UpdateController;
use App\Http\Controllers\Api\ApiPackageController;
use App\Http\Controllers\Api\ApiPaymentCallbackController;
use App\Http\Controllers\Api\ApiPackageDownloadController;
use App\Http\Controllers\Api\ApiSubscriptionController;
use App\Http\Controllers\Api\ApiTemplateController;
use App\Http\Controllers\Api\ApiTemplateDownloadController;
// سقف ۱۲۰ درخواست در دقیقه برای هر IP روی کل API (ضد DDoS/جست‌وجوی کد؛
// کلاینت‌های آپدیت‌سنتر به‌راحتی زیر این سقف می‌مانند)
Route::prefix('v1')->name('api.')->middleware('throttle:120,1')->group(function () {

    Route::get('/check-update', [UpdateController::class, 'check'])->name('update.check');
    Route::get('/download-update/{updateId}', [UpdateController::class, 'download'])->name('update.download');

    // --- طرح‌های اشتراک (Subscription Plans) ---
    Route::name('subscriptions.')->group(function () {
        Route::get('/subscription-plans', [ApiSubscriptionController::class, 'index'])->name('plans.index');
        Route::post('/subscription-plans/{slug}/purchase', [ApiSubscriptionController::class, 'purchase'])->name('plans.purchase');
        Route::post('/subscriptions/payments/{transactionId}/verify', [ApiSubscriptionController::class, 'verifyPayment'])->name('payments.verify');
        Route::get('/my-subscriptions', [ApiSubscriptionController::class, 'mySubscriptions'])->name('mine');
    });

    // --- دسته‌بندی‌های درختی (برای انتخاب دسته فروشگاه هنگام نصب پکیج در پروژه خریدار) ---
    Route::get('/categories', [ApiPackageController::class, 'categories'])->name('categories.index');

    Route::name('packages.')->group(function () {


        // --- لیست و جزئیات ---
        Route::get('/packages', [ApiPackageController::class, 'index'])->name('index');
        Route::get('/packages/{slug}', [ApiPackageController::class, 'show'])->name('show');

        // --- خرید و پرداخت ---
        Route::post('/packages/{slug}/purchase', [ApiPackageController::class, 'purchase'])->name('purchase');
        Route::post('/payments/{transactionId}/verify', [ApiPackageController::class, 'verifyPayment'])->name('verify');

        // --- کال‌بک درگاه shetabit (کاربر از درگاه اینجا برمی‌گرده) ---
        Route::get('/payments/callback', [ApiPaymentCallbackController::class, 'callback'])
            ->name('payment.callback');
        Route::post('/payments/callback', [ApiPaymentCallbackController::class, 'callback'])
            ->name('payment.callback.post');

        // --- لایسنس ---
        Route::post('/packages/{slug}/verify-license', [ApiPackageController::class, 'verifyLicense'])->name('verify-license');

        // --- آپدیت ---
        Route::get('/packages/{slug}/check-update', [ApiPackageController::class, 'checkUpdate'])->name('check-update');


        // --- دانلود ---
        // لینک مستقیم یک‌بارمصرف (خروجی download-url) — با یا بدون هدرهای احراز کار می‌کند
        Route::get('/packages/download/{dlToken}', [ApiPackageDownloadController::class, 'download'])->name('download');

        // دانلود مستقیم پکیجِ خریداری‌شده (لایسنس فعال لازم است) — برای proxy از سمت پروژه خریدار
        Route::get('/packages/{slug}/download', [ApiPackageDownloadController::class, 'downloadBySlug'])->name('download.direct');

        // ساخت لینک دانلود یک‌بارمصرف ۱۵دقیقه‌ای برای پکیج خریداری‌شده — قابل قراردادن در href
        Route::post('/packages/{slug}/download-url', [ApiPackageDownloadController::class, 'issueDownloadUrl'])->name('download.url');

    });

    // --- قالب‌ها (Templates) — دقیقاً با همان محدودیت‌های API پکیج‌ها ---
    Route::name('templates.')->group(function () {

        // --- لیست و جزئیات ---
        Route::get('/templates', [ApiTemplateController::class, 'index'])->name('index');
        Route::get('/templates/{slug}', [ApiTemplateController::class, 'show'])->name('show');

        // --- خرید و پرداخت ---
        Route::post('/templates/{slug}/purchase', [ApiTemplateController::class, 'purchase'])->name('purchase');
        Route::post('/templates/payments/{transactionId}/verify', [ApiTemplateController::class, 'verifyPayment'])->name('verify');

        // --- آپدیت ---
        Route::get('/templates/{slug}/check-update', [ApiTemplateController::class, 'checkUpdate'])->name('check-update');

        // --- دانلود ---
        // لینک مستقیم یک‌بارمصرف (خروجی download-url) — با یا بدون هدرهای احراز کار می‌کند
        Route::get('/templates/download/{dlToken}', [ApiTemplateDownloadController::class, 'download'])->name('download');

        // دانلود مستقیم قالبِ خریداری‌شده (خرید پرداخت‌شده لازم است) — برای proxy از سمت پروژه خریدار
        Route::get('/templates/{slug}/download', [ApiTemplateDownloadController::class, 'downloadBySlug'])->name('download.direct');

        // ساخت لینک دانلود یک‌بارمصرف ۱۵دقیقه‌ای برای قالب خریداری‌شده — قابل قراردادن در href
        Route::post('/templates/{slug}/download-url', [ApiTemplateDownloadController::class, 'issueDownloadUrl'])->name('download.url');

    });

});
