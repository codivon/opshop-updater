<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\PackagePurchase;
use App\Models\ProductPurchase;
use App\Models\SubscriptionOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * سرو کردن فرم پرداختِ درایورهای فرم‌محور و درگاه آزمایشی local.
 *
 * چرا لازم است؟ درایورهای بانکی (مثل به‌پرداخت/سامان) به‌جای URL ساده، فرم HTML
 * خود-ارسال برمی‌گردانند؛ این HTML در meta خرید ذخیره می‌شود و payment_url به
 * مسیر امضادار این کنترلر اشاره می‌کند. مرورگر مشتری (یا مرورگر صاحب فروشگاه)
 * به این مسیر می‌آید، فرم رندر و خودکار به درگاه POST می‌شود.
 *
 * برای درگاه آزمایشی local صفحه شبیه‌ساز درگاه رندر می‌شود تا کل جریان پرداخت
 * بدون درگاه واقعی قابل تست باشد.
 */
class PaymentFormController extends Controller
{
    public function show(Request $request, PackagePurchase $purchase)
    {
        // اعتبارسنجی امضای URL (۳۰ دقیقه اعتبار)
        if (!$request->hasValidSignature()) {
            abort(403, 'لینک پرداخت نامعتبر یا منقضی شده است.');
        }

        // خریدهای ناموفق/تأییدشده فرم ندارند
        if ($purchase->status !== PackagePurchase::STATUS_PENDING) {
            return redirect()->route('shop.home')
                ->with('error', 'این تراکنش پرداخت دیگر در وضعیت انتظار نیست.');
        }

        // ۱) درگاه آزمایشی: صفحه شبیه‌ساز درگاه
        if ($purchase->gateway === 'local') {
            return view('payment.fake-gateway', $this->fakeGatewayData($purchase));
        }

        // ۲) درایورهای فرم‌محور واقعی: HTML ذخیره‌شده را برگردان
        $formHtml = $purchase->meta['payment_form'] ?? null;

        if (!$formHtml) {
            abort(410, 'فرم پرداخت یافت نشد یا منقضی شده است. لطفاً خرید را دوباره آغاز کنید.');
        }

        return response($formHtml, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * نسخه‌ی اشتراک: فرم پرداخت سفارش اشتراک (payment/form/subscription/{order}).
     * قرارداد دقیقاً مانند show() است اما برای SubscriptionOrder.
     */
    public function showSubscription(Request $request, SubscriptionOrder $order)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'لینک پرداخت نامعتبر یا منقضی شده است.');
        }

        if ($order->status !== SubscriptionOrder::STATUS_PENDING) {
            return redirect()->route('subscription.result', $order)
                ->with('error', 'این تراکنش پرداخت دیگر در وضعیت انتظار نیست.');
        }

        // ۱) درگاه آزمایشی: صفحه شبیه‌ساز درگاه (نسخه اشتراک)
        if ($order->gateway === 'local') {
            return view('payment.fake-gateway', $this->fakeGatewayDataForSubscription($order));
        }

        // ۲) درایورهای فرم‌محور واقعی: HTML ذخیره‌شده را برگردان
        $formHtml = $order->meta['payment_form'] ?? null;

        if (!$formHtml) {
            abort(410, 'فرم پرداخت یافت نشد یا منقضی شده است. لطفاً خرید را دوباره آغاز کنید.');
        }

        return response($formHtml, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * داده‌های صفحه شبیه‌ساز درگاه آزمایشی (local)
     * successUrl/cancelUrl به کال‌بک خود پنل اشاره می‌کنند تا پس از پرداخت،
     * صفحه‌ی «نتیجه پرداخت» (وضعیت + شمارش معکوس) نمایش داده شود و سپس
     * کاربر به callback_url فروشگاه برگردد (مطابق منطق PaymentService).
     */
    private function fakeGatewayData(PackagePurchase $purchase): array
    {
        $callback = route('payment.callback');
        $separator = str_contains($callback, '?') ? '&' : '?';
        $trx = (string) $purchase->transaction_id;

        $gateway = \App\Models\Gateway::where('key', 'local')->first();

        return [
            'purchase'   => $purchase,
            'package'    => $purchase->package,
            'plan'       => $purchase->pricingPlan,
            'amount'     => $purchase->amount,
            'title'      => $gateway?->config('title') ?? 'درگاه پرداخت آزمایشی',
            'subtitle'   => $gateway?->config('description') ?? 'این درگاه فقط برای تست جریان پرداخت است — پول واقعی کم نمی‌شود',
            'payButton'  => $gateway?->config('payButton') ?? 'پرداخت (موفق)',
            'cancelButton' => $gateway?->config('cancelButton') ?? 'لغو پرداخت',
            'successUrl' => $callback . $separator . http_build_query(['transactionId' => $trx]),
            'cancelUrl'  => $callback . $separator . http_build_query(['transactionId' => $trx, 'cancel' => 'true']),
        ];
    }

    /**
     * نسخه‌ی محصول قابل‌فروش: فرم پرداخت خرید محصول (payment/form/product/{purchase}).
     */
    public function showProduct(Request $request, ProductPurchase $productPurchase)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'لینک پرداخت نامعتبر یا منقضی شده است.');
        }

        if ($productPurchase->status !== ProductPurchase::STATUS_PENDING) {
            return redirect()
                ->route('payment.result.product', $productPurchase)
                ->with('error', 'این تراکنش پرداخت دیگر در وضعیت انتظار نیست.');
        }

        // ۱) درگاه آزمایشی: صفحه شبیه‌ساز درگاه (نسخه محصول)
        if ($productPurchase->gateway === 'local') {
            return view('payment.fake-gateway', $this->fakeGatewayDataForProduct($productPurchase));
        }

        // ۲) درایورهای فرم‌محور واقعی: HTML ذخیره‌شده را برگردان
        $formHtml = $productPurchase->meta['payment_form'] ?? null;

        if (!$formHtml) {
            abort(410, 'فرم پرداخت یافت نشد یا منقضی شده است. لطفاً خرید را دوباره آغاز کنید.');
        }

        return response($formHtml, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * داده‌های شبیه‌ساز درگاه آزمایشی برای خرید محصول (مهمان).
     */
    private function fakeGatewayDataForProduct(ProductPurchase $purchase): array
    {
        $callback = route('payment.callback');
        $separator = str_contains($callback, '?') ? '&' : '?';
        $trx = (string) $purchase->transaction_id;

        $gateway = \App\Models\Gateway::where('key', 'local')->first();

        return [
            'purchase'   => $purchase,
            'package'    => null,
            'plan'       => null,
            'product'    => $purchase->product,
            'amount'     => $purchase->amount,
            'title'      => $gateway?->config('title') ?? 'درگاه پرداخت آزمایشی',
            'subtitle'   => $gateway?->config('description') ?? 'این درگاه فقط برای تست جریان پرداخت است — پول واقعی کم نمی‌شود',
            'payButton'  => $gateway?->config('payButton') ?? 'پرداخت (موفق)',
            'cancelButton' => $gateway?->config('cancelButton') ?? 'لغو پرداخت',
            'successUrl' => $callback . $separator . http_build_query(['transactionId' => $trx]),
            'cancelUrl'  => $callback . $separator . http_build_query(['transactionId' => $trx, 'cancel' => 'true']),
        ];
    }

    /**
     * داده‌های شبیه‌ساز درگاه آزمایشی برای سفارش اشتراک —
     * همان متغیرها + context اشتراک (قابل‌استفاده در همان ویوی fake-gateway).
     */
    private function fakeGatewayDataForSubscription(SubscriptionOrder $order): array
    {
        $callback = route('payment.callback');
        $separator = str_contains($callback, '?') ? '&' : '?';
        $trx = (string) $order->transaction_id;

        $gateway = \App\Models\Gateway::where('key', 'local')->first();

        return [
            'purchase'   => $order,
            'package'    => null,
            'plan'       => null,
            'subscriptionPlan' => $order->plan,
            'order'      => $order,
            'amount'     => $order->final_amount,
            'title'      => $gateway?->config('title') ?? 'درگاه پرداخت آزمایشی',
            'subtitle'   => $gateway?->config('description') ?? 'این درگاه فقط برای تست جریان پرداخت است — پول واقعی کم نمی‌شود',
            'payButton'  => $gateway?->config('payButton') ?? 'پرداخت (موفق)',
            'cancelButton' => $gateway?->config('cancelButton') ?? 'لغو پرداخت',
            'successUrl' => $callback . $separator . http_build_query(['transactionId' => $trx]),
            'cancelUrl'  => $callback . $separator . http_build_query(['transactionId' => $trx, 'cancel' => 'true']),
        ];
    }
}
