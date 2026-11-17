<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\PackagePurchase;
use App\Models\SubscriptionOrder;
use App\Services\LicenseSigningKeyService;
use App\Services\PaymentService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;

/**
 * صفحه «نتیجه پرداخت» — مرحله میانی بین درگاه و فروشگاه:
 *
 * پس از بازگشت از درگاه و تأیید پرداخت، کاربر به این صفحه می‌آید؛ وضعیت پرداخت
 * (موفق / ناموفق / در انتظار) همراه با جزئیات تراکنش نمایش داده می‌شود و کاربر با
 * دکمه «بازگشت به فروشگاه» یا پس از شمارش معکوس ۱۰ ثانیه‌ای، به‌صورت خودکار به
 * callback_url ارسالی از سمت فروشگاه (هنگام ایجاد خرید) بازگردانده می‌شود.
 */
class PaymentReturnController extends Controller
{
    /** پارامترهای درگاه که هنگام بازگشت به فروشگاه حفظ (forward) می‌شوند */
    private const FORWARD_KEYS = [
        'transactionId', 'transaction_id', 'Authority',
        'Status', 'status', 'cancel', 'token', 'tracking_code',
    ];

    public function __construct(
        private PaymentService $paymentService,
        private SubscriptionService $subscriptionService
    ) {}

    /**
     * جعبهٔ «کلید عمومی راستی‌آزمایی لایسنس» برای صفحهٔ نتیجهٔ پرداخت — فقط در وضعیت پرداخت موفق.
     *
     * درخواست کاربر: بعد از خرید موفق، کلید عمومی به کاربر نمایش داده شود با یادآوریِ
     * «برای همهٔ پکیج‌ها و همهٔ مشتری‌ها مشترک است». کلید عمومی راز نیست و نمایشش بی‌خطر است؛
     * کلید خصوصی سرور (LicenseSigningKeyService) هرگز از این متد عبور نمی‌کند.
     * مقدار shop_env_value همان خطِ تک‌خطیِ آمادهٔ فروشگاه است (PACKAGE_LICENSE_PUBLIC_KEY="...")
     * که می‌توان آن را یک‌جا در فیلدِ هم‌نامِ پنل فروشگاه (تنظیمات برنامه‌نویس ← تب تنظیمات دیگر، کنار فیلد کد آپدیت)
     * یا .env فروشگاه چسباند.
     *
     * @return array{env_line:string, public_key:string, fingerprint:string}|null
     */
    private function licenseKeyBox(): ?array
    {
        try {
            $status = app(LicenseSigningKeyService::class)->status();
        } catch (\Throwable $e) {
            return null;
        }

        if (($status['state'] ?? '') !== 'ok' || empty($status['shop_env_value'])) {
            return null;
        }

        return [
            'env_line' => (string) $status['shop_env_value'],
            'public_key' => (string) ($status['public_key'] ?? ''),
            'fingerprint' => (string) ($status['fingerprint'] ?? ''),
        ];
    }

    public function show(Request $request, PackagePurchase $purchase, string $token)
    {
        // Task 85 — امنیت: صفحهٔ بازگشت پرداخت فقط با توکن محرمانهٔ خرید باز می‌شود
        abort_unless($purchase->hasValidAccessToken($token), 404);

        $purchase->load([
            'package:id,name,slug',
            'pricingPlan:id,name',
            'customer:id,name',
            'license:id,license_key,expires_at',
            'version:id,version',
        ]);

        // اگر خرید هنوز در انتظار است (مثلاً کال‌بک درگاه دیر رسیده)، یک‌بار تأیید را امتحان می‌کنیم
        if ($purchase->status === PackagePurchase::STATUS_PENDING && $purchase->transaction_id) {
            $this->paymentService->verifyPayment($purchase->transaction_id, renew: true);
            $purchase->refresh();
        }

        [$returnUrl, $returnHost, $isInternal] = $this->resolveReturnTarget($request, $purchase);

        return view('payment.return', [
            'purchase' => $purchase,
            'status' => $purchase->status, // paid | failed | pending
            'message' => $this->statusMessage($purchase),
            'gatewayName' => config("general.supported_gateways.{$purchase->gateway}") ?? $purchase->gateway,
            'licenseKeyBox' => $purchase->isPaid() ? $this->licenseKeyBox() : null,
            'returnUrl' => $returnUrl,
            'returnHost' => $returnHost,
            'isInternal' => $isInternal,
            'seconds' => 10,
        ]);
    }

    /**
     * مقصد نهایی بازگشت:
     *  - خرید API (callback_url بیرونی) → همان آدرس + پارامترهای وضعیت/تراکنش
     *  - خرید ویترین خود پنل یا callback داخلی → صفحه نتیجه خود پنل (بدون حلقه)
     *
     * @return array{0: string, 1: ?string, 2: bool} [url, host, isInternal]
     */
    private function resolveReturnTarget(Request $request, PackagePurchase $purchase): array
    {
        $callback = $purchase->callback_url;

        if (! $callback || $this->isInternalCallback($callback)) {
            $url = route('payment.result', ['purchase' => $purchase, 'token' => $purchase->access_token]);

            return [$url, null, true];
        }

        $status = match ($purchase->status) {
            PackagePurchase::STATUS_PAID => 'success',
            PackagePurchase::STATUS_FAILED => 'failed',
            default => 'pending',
        };

        $params = [
            'transaction_id' => $purchase->transaction_id,
            'status' => $status,
        ];

        if ($purchase->isPaid()) {
            $params['purchase_id'] = $purchase->id;
        } else {
            $params['error'] = $purchase->meta['fail_reason'] ?? 'پرداخت ناموفق بود.';
        }

        // پارامترهای اصلی درگاه (مثل transactionId یا cancel) به فروشگاه هم forward می‌شود
        foreach (self::FORWARD_KEYS as $key) {
            if ($request->filled($key) && ! array_key_exists($key, $params)) {
                $params[$key] = $request->input($key);
            }
        }

        $separator = str_contains($callback, '?') ? '&' : '?';

        return [
            $callback.$separator.http_build_query($params),
            parse_url($callback, PHP_URL_HOST) ?: $callback,
            false,
        ];
    }

    /**
     * نسخه‌ی اشتراک: صفحه نتیجه پرداختِ سفارش اشتراک (payment/return/subscription/{order}).
     * برای سفارش‌های API (callback_url بیرونی): وضعیت + شمارش معکوس + بازگشت به فروشگاه.
     */
    public function showSubscription(Request $request, SubscriptionOrder $order, string $token)
    {
        // Task 85 — امنیت: صفحهٔ بازگشت پرداخت اشتراک فقط با توکن محرمانهٔ سفارش باز می‌شود
        abort_unless($order->hasValidAccessToken($token), 404);

        $order->load([
            'plan:id,name,slug,duration_months',
            'customer:id,name',
            'subscription:id,status',
        ]);

        // اگر پرداخت هنوز pending است، یک‌بار تأیید را امتحان می‌کنیم
        if ($order->status === SubscriptionOrder::STATUS_PENDING && $order->transaction_id) {
            $this->subscriptionService->verifyPayment($order->transaction_id);
            $order->refresh();
        }

        [$returnUrl, $returnHost, $isInternal] = $this->resolveSubscriptionReturnTarget($request, $order);

        $months = (int) ($order->plan?->duration_months ?? $order->meta['plan']['duration_months'] ?? null);
        $planDuration = $months === null ? null : ($months === 0 ? 'نامحدود' : fa_num($months).' ماه');

        return view('payment.return-subscription', [
            'order' => $order,
            'status' => $order->status, // paid | failed | pending
            'message' => $this->subscriptionStatusMessage($order),
            'gatewayName' => config("general.supported_gateways.{$order->gateway}") ?? $order->gateway,
            'planDuration' => $planDuration,
            'packagesCount' => count($order->meta['plan']['packages'] ?? []),
            'licenseKeyBox' => $order->isPaid() ? $this->licenseKeyBox() : null,
            'returnUrl' => $returnUrl,
            'returnHost' => $returnHost,
            'isInternal' => $isInternal,
            'seconds' => 10,
        ]);
    }

    /**
     * مقصد نهایی بازگشت برای سفارش اشتراک (قرارداد مشابه خرید پکیج):
     *  - سفارش API (callback_url بیرونی) → همان آدرس + پارامترهای وضعیت/تراکنش
     *  - سفارش وب پنل یا callback داخلی → صفحه نتیجه اشتراک (بدون حلقه)
     *
     * @return array{0: string, 1: ?string, 2: bool} [url, host, isInternal]
     */
    private function resolveSubscriptionReturnTarget(Request $request, SubscriptionOrder $order): array
    {
        $callback = $order->callback_url;

        if (! $callback || $this->isInternalCallback($callback)) {
            $url = route('subscription.result', ['order' => $order, 'token' => $order->access_token]);

            return [$url, null, true];
        }

        $status = match ($order->status) {
            SubscriptionOrder::STATUS_PAID => 'success',
            SubscriptionOrder::STATUS_FAILED => 'failed',
            default => 'pending',
        };

        $params = [
            'transaction_id' => $order->transaction_id,
            'status' => $status,
        ];

        if ($order->isPaid()) {
            $params['order_id'] = $order->id;
            $params['admin_status'] = $order->admin_status;
            $params['subscription_id'] = $order->subscription_id;
        } else {
            $params['error'] = $order->meta['fail_reason'] ?? 'پرداخت ناموفق بود.';
        }

        foreach (self::FORWARD_KEYS as $key) {
            if ($request->filled($key) && ! array_key_exists($key, $params)) {
                $params[$key] = $request->input($key);
            }
        }

        $separator = str_contains($callback, '?') ? '&' : '?';

        return [
            $callback.$separator.http_build_query($params),
            parse_url($callback, PHP_URL_HOST) ?: $callback,
            false,
        ];
    }

    private function subscriptionStatusMessage(SubscriptionOrder $order): string
    {
        return match ($order->status) {
            SubscriptionOrder::STATUS_PAID => 'پرداخت شما تأیید شد؛ درخواست اشتراک ثبت شد و پس از تأیید مدیر، اشتراک و دسترسی‌های پکیج‌ها فعال می‌شود.',
            SubscriptionOrder::STATUS_FAILED => $order->meta['fail_reason'] ?? 'متأسفانه پرداخت شما تأیید نشد؛ مبلغی از حساب شما کسر نشده است.',
            default => 'پرداخت هنوز توسط درگاه تأیید نشده است. پس از بازگشت به فروشگاه، وضعیت تراکنش بررسی می‌شود.',
        };
    }

    /**
     * آیا callback_url به یکی از مسیرهای کال‌بک خود پنل اشاره می‌کند؟
     * (برای جلوگیری از حلقه‌ی بی‌نهایت، این موارد به صفحه‌ی نتیجه‌ی خود پنل هدایت می‌شوند)
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

    private function statusMessage(PackagePurchase $purchase): string
    {
        return match ($purchase->status) {
            PackagePurchase::STATUS_PAID => 'پرداخت شما با موفقیت تأیید شد و لایسنس پکیج صادر گردید.',
            PackagePurchase::STATUS_FAILED => $purchase->meta['fail_reason'] ?? 'متأسفانه پرداخت شما تأیید نشد؛ مبلغی از حساب شما کسر نشده است.',
            default => 'پرداخت هنوز توسط درگاه تأیید نشده است. پس از بازگشت به فروشگاه، وضعیت تراکنش بررسی می‌شود.',
        };
    }

    /** هاست به‌همراه پورت (اگر وجود داشته باشد) برای مقایسه‌ی دقیق‌تر callback_url */
    private function hostWithPort(string $url): string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT);

        return $port ? $host.':'.$port : $host;
    }
}
