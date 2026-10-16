<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\PackagePurchase;
use App\Models\ProductPurchase;
use App\Models\SubscriptionOrder;
use App\Services\PaymentService;
use App\Services\Sms\SmsManager;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * کال‌بک پرداخت فروشگاه عمومی (web):
 * درگاه بانکی پس از پرداخت کاربر را به /payment/callback برمی‌گرداند.
 * هم GET و هم POST پشتیبانی می‌شود (POST درگاه‌ها CSRF ندارند →
 * مسیر payment/callback در استثناهای VerifyCsrfToken قرار دارد).
 *
 * سه نوع تراکنش از این مسیر عبور می‌کنند:
 *  ۱) خرید پکیج (PackagePurchase) — مانند قبل
 *  ۲) سفارش اشتراک (SubscriptionOrder) — پس از تأیید پرداخت، به صفحه نتیجه
 *     اشتراک می‌رود (پرداخت موفق + «در انتظار تأیید مدیر»).
 *  ۳) خرید محصول/پروژه‌ی قابل‌فروش (ProductPurchase) — مهمان؛ صفحه نتیجه محصول
 *     با دکمه دانلود.
 *
 * پس از تأیید پرداخت:
 *  - خریدهای API (callback_url بیرونی) → صفحه‌ی «نتیجه پرداخت» (payment/return)
 *    تا وضعیت + شمارش معکوس ۱۰ ثانیه‌ای نمایش داده شود و سپس به فروشگاه برگردد.
 *  - خریدهای ویترین خود پنل → صفحه‌ی نتیجه‌ی خود پنل (payment/result) مانند قبل.
 */
class WebPaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private SubscriptionService $subscriptionService,
        private SmsManager $smsManager,
    ) {}

    public function callback(Request $request): RedirectResponse
    {
        // شناسه تراکنش با کلیدهای متداول درگاه‌ها (همان جریان API)
        // transactionId (camelCase) → درگاه آزمایشی local
        $transactionId = $request->input('transaction_id')
            ?? $request->input('transactionId')
            ?? $request->input('Authority')
            ?? $request->input('tracking_code')
            ?? $request->input('token');

        if (!$transactionId) {
            return redirect()
                ->route('shop.home')
                ->with('error', 'اطلاعات تراکنش ناقص است.');
        }

        // ---------- ۱) سفارش اشتراک (طرح اشتراک) ----------
        $subscriptionOrder = SubscriptionOrder::where('transaction_id', $transactionId)->first();

        if ($subscriptionOrder) {
            $result = $this->subscriptionService->verifyPayment($transactionId);
            $subscriptionOrder->refresh();

            // 📱 پیامک: پرداخت اشتراک تأیید شد (در انتظار تأیید مدیر) + اطلاع مدیر
            if (($result['paid'] ?? false) && $subscriptionOrder->status === SubscriptionOrder::STATUS_PAID) {
                $customer = $subscriptionOrder->customer;

                $vars = [
                    'customer_name' => $customer?->name ?? 'مشتری',
                    'plan_name'     => $subscriptionOrder->plan_name,
                    'amount'        => number_format((int) $subscriptionOrder->final_amount),
                ];

                $this->smsManager->send('subscription_paid', $customer?->phone, $vars, $subscriptionOrder, once: true);
                $this->smsManager->notifyAdmin('admin_subscription_paid', $vars, $subscriptionOrder);
            }

            // سفارش API (callback_url بیرونی) → صفحه نتیجه + شمارش معکوس، سپس فروشگاه
            if ($subscriptionOrder->callback_url && !$this->isInternalCallback($subscriptionOrder->callback_url)) {
                return redirect()->route('payment.return.subscription', array_merge($request->query(), [
                    'order' => $subscriptionOrder->id,
                ]));
            }

            return redirect()
                ->route('subscription.result', $subscriptionOrder)
                ->with(
                    ($result['paid'] ?? false) ? 'success' : 'error',
                    $result['message'] ?? (($result['paid'] ?? false) ? 'پرداخت با موفقیت تأیید شد.' : 'پرداخت ناموفق بود.')
                );
        }

        // ---------- ۲) خرید محصول (پروژه‌ی قابل‌فروش — مهمان) ----------
        $productPurchase = ProductPurchase::where('transaction_id', $transactionId)->first();

        if ($productPurchase) {
            $result = $this->paymentService->verifyProductPayment($transactionId);
            $productPurchase->refresh();

            // 📱 پیامک مدیر: فروش موفق محصول
            if (($result['paid'] ?? false) && $productPurchase->isPaid()) {
                $vars = [
                    'product_name' => $productPurchase->product?->name ?? 'محصول',
                    'amount'       => number_format((int) $productPurchase->amount),
                    'gateway'      => $productPurchase->gateway ?? '-',
                ];

                $this->smsManager->notifyAdmin('admin_product_paid', $vars, $productPurchase);
            }

            return redirect()
                ->route('payment.result.product', $productPurchase)
                ->with(
                    ($result['paid'] ?? false) ? 'success' : 'error',
                    $result['message'] ?? (($result['paid'] ?? false) ? 'پرداخت با موفقیت تأیید شد.' : 'پرداخت ناموفق بود.')
                );
        }

        // ---------- ۳) خرید پکیج (مانند قبل) ----------
        $purchase = PackagePurchase::where('transaction_id', $transactionId)->first();

        if (!$purchase) {
            return redirect()
                ->route('shop.home')
                ->with('error', 'رکورد خرید یافت نشد.');
        }

        // تأیید پرداخت + صدور/تمدید لایسنس
        $result = $this->paymentService->verifyPayment($transactionId, renew: true);

        $purchase->refresh();

        // 📱 پیامک: خرید پکیج پرداخت شد + اطلاع مدیر
        if (($result['paid'] ?? false) && $purchase->isPaid()) {
            $vars = [
                'customer_name' => $purchase->customer?->name ?? 'مشتری',
                'package_name'  => $purchase->package?->name ?? 'پکیج',
                'amount'        => number_format((int) $purchase->amount),
                'license_key'   => (string) ($result['license_key'] ?? $purchase->license?->license_key ?? ''),
            ];

            $this->smsManager->send('purchase_paid', $purchase->customer?->phone, $vars, $purchase, once: true);
            $this->smsManager->notifyAdmin('admin_purchase_paid', $vars, $purchase);
        }

        // خرید فروشگاه (API): ابتدا صفحه‌ی نتیجه + شمارش معکوس، سپس بازگشت به فروشگاه
        if ($purchase->callback_url && !$this->isInternalCallback($purchase->callback_url)) {
            return redirect()->route('payment.return', array_merge($request->query(), [
                'purchase' => $purchase->id,
            ]));
        }

        return redirect()
            ->route('payment.result', $purchase)
            ->with(
                ($result['paid'] ?? false) ? 'success' : 'error',
                $result['message'] ?? (($result['paid'] ?? false) ? 'پرداخت با موفقیت تأیید شد.' : 'پرداخت ناموفق بود.')
            );
    }

    /**
     * آیا callback_url به یکی از مسیرهای کال‌بک خود پنل اشاره می‌کند؟
     */
    private function isInternalCallback(string $url): bool
    {
        // هاست + پورت (پورت را هم مقایسه می‌کنیم تا localhost:3001 با localhost:8000 یکی تلقی نشود)
        $given = [$this->hostWithPort($url), rtrim((string) parse_url($url, PHP_URL_PATH), '/')];

        foreach ([route('payment.callback'), route('api.packages.payment.callback')] as $panelUrl) {
            $panel = [$this->hostWithPort($panelUrl), rtrim((string) parse_url($panelUrl, PHP_URL_PATH), '/')];

            if ($given === $panel) {
                return true;
            }
        }

        return false;
    }

    /** هاست به‌همراه پورت (اگر وجود داشته باشد) برای مقایسه‌ی دقیق‌تر callback_url */
    private function hostWithPort(string $url): string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT);

        return $port ? $host . ':' . $port : $host;
    }
}
