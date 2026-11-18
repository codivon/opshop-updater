<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\PackagePurchase;
use App\Models\ProductPurchase;
use App\Models\SubscriptionOrder;
use App\Models\TemplatePurchase;
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
 * چهار نوع تراکنش از این مسیر عبور می‌کنند:
 *  ۱) خرید پکیج (PackagePurchase) — مانند قبل
 *  ۲) سفارش اشتراک (SubscriptionOrder) — پس از تأیید پرداخت، به صفحه نتیجه
 *     اشتراک می‌رود (پرداخت موفق + «در انتظار تأیید مدیر»).
 *  ۳) خرید محصول/پروژه‌ی قابل‌فروش (ProductPurchase) — مهمان؛ صفحه نتیجه محصول
 *     با دکمه دانلود.
 *  ۴) خرید قالب (TemplatePurchase) — مهمان یا API؛ صفحه نتیجه قالب با دکمه دانلود.
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
        // Task 92: سگمنت مسیر {reference} هم شناسهٔ تراکنش می‌تواند باشد (کال‌بک با شناسهٔ جاسازی‌شده)
        // Task 94: تطبیق «بی‌حساس به بزرگی/کوچکی حروف» — درگاه‌هایی مثل پارسیان پارامتر را با
        // کلید «Token» (حروف بزرگ) برمی‌گردانند و تطبیق حساسِ قبلی شناسه را نمی‌یافت؛ نتیجه:
        // مسیر redirectByReference با پیام «به پایان نرسید…» فعال می‌شد درحالی‌که پرداخت موفق بود
        // و صفحهٔ نتیجه سفارش را تأیید و فعال می‌کرد (هیروی سبز + توستِ متناقض — گزارش کاربر)
        $transactionId = $this->extractGatewayTransactionId($request);

        if (! $transactionId) {
            // درگاه کاملاً خالی برگشته (بدون شناسهٔ تراکنش و بدون شناسهٔ ارجاع مسیر)
            // قبلاً به صفحهٔ اصلی می‌رفت و کاربر هیچ بازخوردی نمی‌گرفت (گزارش Task 92)
            return redirect()->route('payment.canceled');
        }

        // ---------- ۱) سفارش اشتراک (طرح اشتراک) ----------
        $subscriptionOrder = SubscriptionOrder::where('transaction_id', $transactionId)->first();

        if ($subscriptionOrder) {
            $result = $this->subscriptionService->verifyPayment($transactionId);
            $subscriptionOrder->refresh();

            // 📱 پیامک: پرداخت اشتراک تأیید شد (در انتظار تأیید مدیر) + اطلاع مدیر (Task 94: متد مشترک)
            if (($result['paid'] ?? false) && $subscriptionOrder->status === SubscriptionOrder::STATUS_PAID) {
                $this->notifySubscriptionPaid($subscriptionOrder);
            }

            // سفارش API (callback_url بیرونی) → صفحه نتیجه + شمارش معکوس، سپس فروشگاه
            if ($subscriptionOrder->callback_url && ! $this->isInternalCallback($subscriptionOrder->callback_url)) {
                return redirect()->route('payment.return.subscription', array_merge($request->query(), [
                    'order' => $subscriptionOrder->id,
                    'token' => $subscriptionOrder->access_token,
                ]));
            }

            return redirect()
                ->route('subscription.result', ['order' => $subscriptionOrder, 'token' => $subscriptionOrder->access_token])
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

            // 📱 پیامک مدیر: فروش موفق محصول (Task 94: متد مشترک)
            if (($result['paid'] ?? false) && $productPurchase->isPaid()) {
                $this->notifyProductPaid($productPurchase);
            }

            return redirect()
                ->route('payment.result.product', ['purchase' => $productPurchase, 'token' => $productPurchase->access_token])
                ->with(
                    ($result['paid'] ?? false) ? 'success' : 'error',
                    $result['message'] ?? (($result['paid'] ?? false) ? 'پرداخت با موفقیت تأیید شد.' : 'پرداخت ناموفق بود.')
                );
        }

        // ---------- ۲-ب) خرید قالب (مهمان یا API) ----------
        $templatePurchase = TemplatePurchase::where('transaction_id', $transactionId)->first();

        if ($templatePurchase) {
            $result = $this->paymentService->verifyTemplatePayment($transactionId);
            $templatePurchase->refresh();

            // 📱 پیامک مدیر: فروش موفق قالب (Task 94: متد مشترک)
            if (($result['paid'] ?? false) && $templatePurchase->isPaid()) {
                $this->notifyTemplatePaid($templatePurchase);
            }

            return redirect()
                ->route('payment.result.template', ['purchase' => $templatePurchase, 'token' => $templatePurchase->access_token])
                ->with(
                    ($result['paid'] ?? false) ? 'success' : 'error',
                    $result['message'] ?? (($result['paid'] ?? false) ? 'پرداخت با موفقیت تأیید شد.' : 'پرداخت ناموفق بود.')
                );
        }

        // ---------- ۳) خرید پکیج (مانند قبل) ----------
        $purchase = PackagePurchase::where('transaction_id', $transactionId)->first();

        if (! $purchase) {
            // آخرین شناسه‌ای که باقی می‌ماند: reference مسیر (access_token)
            if (($reference = (string) $request->route('reference')) !== ''
                && ($redirect = $this->redirectByReference($reference, $request))) {
                return $redirect;
            }

            return redirect()->route('payment.canceled');
        }

        // تأیید پرداخت + صدور/تمدید لایسنس
        $result = $this->paymentService->verifyPayment($transactionId, renew: true);

        $purchase->refresh();

        // 📱 پیامک: خرید پکیج پرداخت شد + اطلاع مدیر (Task 94: متد مشترک)
        if (($result['paid'] ?? false) && $purchase->isPaid()) {
            $this->notifyPackagePaid($purchase, $result);
        }

        // خرید فروشگاه (API): ابتدا صفحه‌ی نتیجه + شمارش معکوس، سپس بازگشت به فروشگاه
        if ($purchase->callback_url && ! $this->isInternalCallback($purchase->callback_url)) {
            return redirect()->route('payment.return', array_merge($request->query(), [
                'purchase' => $purchase->id,
                'token' => $purchase->access_token,
            ]));
        }

        return redirect()
            ->route('payment.result', ['purchase' => $purchase, 'token' => $purchase->access_token])
            ->with(
                ($result['paid'] ?? false) ? 'success' : 'error',
                $result['message'] ?? (($result['paid'] ?? false) ? 'پرداخت با موفقیت تأیید شد.' : 'پرداخت ناموفق بود.')
            );
    }

    /**
     * Task 94 — استخراج شناسهٔ تراکنش از درخواست درگاه با تطبیق «بی‌حساس به بزرگی/کوچکی حروف»
     * کلیدهای رایج. بعضی درگاه‌ها (مثل پارسیان: Token، بعضی شاپرکی: RefID) کلید را با
     * حروف متفاوت می‌فرستند و تطبیق حساسِ قبلی آن‌ها را نمی‌یافت.
     * ترتیب اهمیت: شناسهٔ تراکنش ما > شناسه‌های مرجع درگاه > سگمنت مسیر (Task 92).
     */
    private function extractGatewayTransactionId(Request $request)
    {
        $normalized = collect($request->input())
            ->mapWithKeys(fn ($value, $key) => [strtolower((string) $key) => $value])
            ->filter(fn ($value) => filled($value));

        foreach (['transaction_id', 'transactionid', 'authority', 'tracking_code', 'token', 'refid', 'ref_id'] as $key) {
            if ($normalized->has($key)) {
                return $normalized->get($key);
            }
        }

        return $request->route('reference');
    }

    /**
     * Task 92 — شناسایی سفارش از روی reference مسیر کال‌بک (access_token) و هدایت به
     * صفحهٔ نتیجهٔ خودش. برای بازگشت‌های درگاه‌هایی است که شناسهٔ تراکنش
     * را در کال‌بک نمی‌فرستند.
     *
     * Task 94 — پیام دیگر نباید با واقعیت سفارش تناقض داشته باشد (گزارش کاربر:
     * «هیروی سبز اشتراک فعال شد» + توستِ «پرداخت به پایان نرسید» هم‌زمان دیده می‌شد):
     *  - سفارشِ پرداخت‌شده → پیام موفق/اطلاع‌رسانی (نه پیام خطای ناتمام!)
     *  - سفارشِ pending با تراکنش → یک‌بار تأیید از درگاه امتحان می‌شود (همان رفتار
     *    mount صفحهٔ نتیجه) و پیام بر اساس نتیجهٔ واقعی تنظیم + پیامک با once ارسال می‌شود
     *  - سفارشِ واقعاً ناتمام (بدون تراکنش یا failed) → همان پیام «به پایان نرسید…»
     *    (رفتار و تست‌های Task 92 حفظ می‌شود — سفارش بدون تراکنش وضعیتش ثابت می‌ماند)
     */
    private function redirectByReference(string $reference, Request $request): ?RedirectResponse
    {
        // سفارش اشتراک
        $subscriptionOrder = SubscriptionOrder::where('access_token', $reference)->first();

        if ($subscriptionOrder) {
            return $this->redirectSubscriptionByReference($subscriptionOrder);
        }

        // خرید پکیج
        $purchase = PackagePurchase::where('access_token', $reference)->first();

        if ($purchase) {
            return $this->redirectPackageByReference($purchase);
        }

        // خرید محصول
        $productPurchase = ProductPurchase::where('access_token', $reference)->first();

        if ($productPurchase) {
            return $this->redirectProductByReference($productPurchase);
        }

        // خرید قالب
        $templatePurchase = TemplatePurchase::where('access_token', $reference)->first();

        if ($templatePurchase) {
            return $this->redirectTemplateByReference($templatePurchase);
        }

        return null;
    }

    private function redirectSubscriptionByReference(SubscriptionOrder $order): RedirectResponse
    {
        $redirect = fn (string $type, string $message) => redirect()
            ->route('subscription.result', ['order' => $order, 'token' => $order->access_token])
            ->with($type, $message);

        if ($order->isPaid()) {
            // کال‌بک قبلی (مثلاً سرور-به-سرور) پرداخت را تأیید کرده و بازگشتِ مرورگر بدون شناسه است
            return $redirect('success', $order->admin_status === SubscriptionOrder::ADMIN_STATUS_APPROVED
                ? 'پرداخت این سفارش تأیید و اشتراک شما فعال شده است.'
                : 'پرداخت این سفارش با موفقیت تأیید شده است؛ در انتظار تأیید مدیر.');
        }

        if ($order->status === SubscriptionOrder::STATUS_PENDING && $order->transaction_id) {
            $result = $this->subscriptionService->verifyPayment($order->transaction_id);
            $order->refresh();

            if ($result['paid'] ?? false) {
                $this->notifySubscriptionPaid($order);

                return $redirect('success', $result['message'] ?? 'پرداخت با موفقیت تأیید شد.');
            }

            if ($order->status === SubscriptionOrder::STATUS_FAILED) {
                return $redirect('warning', 'تأیید این پرداخت از سمت درگاه ناموفق بود؛ اگر مبلغ کسر شده، حداکثر تا ۷۲ ساعت به‌صورت خودکار برمی‌گردد.');
            }
        }

        return $redirect('error', 'پرداخت این سفارش به پایان نرسید یا از سمت درگاه تأیید نشد.');
    }

    private function redirectPackageByReference(PackagePurchase $purchase): RedirectResponse
    {
        $redirect = fn (string $type, string $message) => redirect()
            ->route('payment.result', ['purchase' => $purchase, 'token' => $purchase->access_token])
            ->with($type, $message);

        if ($purchase->isPaid()) {
            return $redirect('success', 'این پرداخت قبلاً با موفقیت تأیید و لایسنس صادر شده است.');
        }

        if ($purchase->status === PackagePurchase::STATUS_PENDING && $purchase->transaction_id) {
            $result = $this->paymentService->verifyPayment($purchase->transaction_id, renew: true);
            $purchase->refresh();

            if ($result['paid'] ?? false) {
                $this->notifyPackagePaid($purchase, $result);

                return $redirect('success', $result['message'] ?? 'پرداخت با موفقیت تأیید شد.');
            }

            if ($purchase->status === PackagePurchase::STATUS_FAILED) {
                return $redirect('warning', 'تأیید این پرداخت از سمت درگاه ناموفق بود؛ اگر مبلغ کسر شده، حداکثر تا ۷۲ ساعت به‌صورت خودکار برمی‌گردد.');
            }
        }

        return $redirect('error', 'پرداخت این سفارش به پایان نرسید یا از سمت درگاه تأیید نشد.');
    }

    private function redirectProductByReference(ProductPurchase $purchase): RedirectResponse
    {
        $redirect = fn (string $type, string $message) => redirect()
            ->route('payment.result.product', ['purchase' => $purchase, 'token' => $purchase->access_token])
            ->with($type, $message);

        if ($purchase->isPaid()) {
            return $redirect('success', 'این پرداخت قبلاً با موفقیت تأیید شده است.');
        }

        if ($purchase->status === ProductPurchase::STATUS_PENDING && $purchase->transaction_id) {
            $result = $this->paymentService->verifyProductPayment($purchase->transaction_id);
            $purchase->refresh();

            if ($result['paid'] ?? false) {
                $this->notifyProductPaid($purchase);

                return $redirect('success', $result['message'] ?? 'پرداخت با موفقیت تأیید شد.');
            }

            if ($purchase->status === ProductPurchase::STATUS_FAILED) {
                return $redirect('warning', 'تأیید این پرداخت از سمت درگاه ناموفق بود؛ اگر مبلغ کسر شده، حداکثر تا ۷۲ ساعت به‌صورت خودکار برمی‌گردد.');
            }
        }

        return $redirect('error', 'پرداخت این سفارش به پایان نرسید یا از سمت درگاه تأیید نشد.');
    }

    private function redirectTemplateByReference(TemplatePurchase $purchase): RedirectResponse
    {
        $redirect = fn (string $type, string $message) => redirect()
            ->route('payment.result.template', ['purchase' => $purchase, 'token' => $purchase->access_token])
            ->with($type, $message);

        if ($purchase->isPaid()) {
            return $redirect('success', 'این پرداخت قبلاً با موفقیت تأیید شده است.');
        }

        if ($purchase->status === TemplatePurchase::STATUS_PENDING && $purchase->transaction_id) {
            $result = $this->paymentService->verifyTemplatePayment($purchase->transaction_id);
            $purchase->refresh();

            if ($result['paid'] ?? false) {
                $this->notifyTemplatePaid($purchase);

                return $redirect('success', $result['message'] ?? 'پرداخت با موفقیت تأیید شد.');
            }

            if ($purchase->status === TemplatePurchase::STATUS_FAILED) {
                return $redirect('warning', 'تأیید این پرداخت از سمت درگاه ناموفق بود؛ اگر مبلغ کسر شده، حداکثر تا ۷۲ ساعت به‌صورت خودکار برمی‌گردد.');
            }
        }

        return $redirect('error', 'پرداخت این سفارش به پایان نرسید یا از سمت درگاه تأیید نشد.');
    }

    /* ===================================================================
     *  Task 94 — پیامک‌های «پرداخت شد» به‌صورت متد مشترک:
     *  هم مسیر اصلی کال‌بک و هم مسیر بازگشتِ بدون شناسه (redirectByReference)
     *  همین‌ها را صدا می‌زنند؛ once: true تکرار را در هر دو مسیر منتفی می‌کند.
     * =================================================================== */

    private function notifySubscriptionPaid(SubscriptionOrder $order): void
    {
        $customer = $order->customer;

        $vars = [
            'customer_name' => $customer?->name ?? 'مشتری',
            'plan_name' => $order->plan_name,
            'amount' => number_format((int) $order->final_amount),
        ];

        $this->smsManager->send('subscription_paid', $customer?->phone, $vars, $order, once: true);
        $this->smsManager->notifyAdminSale('subscription', 'admin_subscription_paid', $vars, $order);
    }

    private function notifyPackagePaid(PackagePurchase $purchase, array $result): void
    {
        $vars = [
            'customer_name' => $purchase->customer?->name ?? 'مشتری',
            'package_name' => $purchase->package?->name ?? 'پکیج',
            'amount' => number_format((int) $purchase->amount),
            'license_key' => (string) ($result['license_key'] ?? $purchase->license?->license_key ?? ''),
        ];

        // Task 87 — خرید رزروشده → پیامک «رزرو شد» به‌جای پیامک لایسنس
        if ($purchase->isReserved()) {
            $this->smsManager->send('package_reserved', $purchase->customer?->phone, array_merge($vars, [
                'end_date' => $purchase->reserved_activate_at
                    ? (verta_date($purchase->reserved_activate_at, 'Y/m/d') ?? $purchase->reserved_activate_at->format('Y/m/d'))
                    : '',
            ]), $purchase, once: true);
        } else {
            $this->smsManager->send('purchase_paid', $purchase->customer?->phone, $vars, $purchase, once: true);
        }

        $this->smsManager->notifyAdminSale('package', 'admin_purchase_paid', $vars, $purchase);
    }

    private function notifyProductPaid(ProductPurchase $purchase): void
    {
        $vars = [
            'product_name' => $purchase->product?->name ?? 'محصول',
            'amount' => number_format((int) $purchase->amount),
            'gateway' => $purchase->gateway ?? '-',
        ];

        $this->smsManager->notifyAdminSale('product', 'admin_product_paid', $vars, $purchase);
    }

    private function notifyTemplatePaid(TemplatePurchase $purchase): void
    {
        $vars = [
            'template_name' => $purchase->template?->name ?? 'قالب',
            'amount' => number_format((int) $purchase->amount),
            'gateway' => $purchase->gateway ?? '-',
        ];

        $this->smsManager->notifyAdminSale('template', 'admin_template_paid', $vars, $purchase);
    }

    /**
     * آیا callback_url به یکی از مسیرهای کال‌بک خود پنل اشاره می‌کند؟
     * Task 92: مسیر با سگمنت شناسهٔ ارجاع (/payment/callback/{ref}) هم داخلی است.
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

        // پیشوند مسیر کال‌بک داخلی + هر هاستی غیر از هاست خود پنل، بیرونی است
        if (str_starts_with($given[1], '/payment/callback')) {
            $internalHosts = array_filter(array_unique(array_map('strtolower', [
                (string) request()->getHost(),
                (string) parse_url((string) route('payment.callback'), PHP_URL_HOST),
                (string) parse_url((string) config('app.url'), PHP_URL_HOST),
            ])));

            if ($given[0] === '' || in_array(strtolower($given[0]), $internalHosts, true)) {
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

        return $port ? $host.':'.$port : $host;
    }
}
