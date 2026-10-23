<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PackagePurchase;
use App\Services\LicenseService;
use App\Services\PaymentService;
use App\Services\Sms\SmsManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ApiPaymentCallbackController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private LicenseService $licenseService,
        private SmsManager $smsManager
    ) {}

    /* ===================================================================
     *  GET /api/v1/payments/callback
     *  کال‌بک درگاه shetabit (کاربر از درگاه اینجا برمی‌گرده)
     *  Query: transaction_id (یا Authority برای زرین‌پال)
     *
     *  پس از تأیید پرداخت، کاربر به صفحه‌ی «نتیجه پرداخت» پنل
     *  (payment/return) هدایت می‌شود تا وضعیت پرداخت را ببیند و با
     *  دکمه/شمارش معکوس ۱۰ ثانیه‌ای به callback_url فروشگاه برگردد.
     * =================================================================== */
    public function callback(Request $request)
    {
        $transactionId = $request->input('transaction_id')
            ?? $request->input('transactionId') // درگاه آزمایشی local
            ?? $request->input('Authority')
            ?? $request->input('tracking_code')
            ?? $request->input('token');

        if (!$transactionId) {
            return redirect()->route('shop.home')
                ->with('error', 'اطلاعات تراکنش ناقص است.');
        }

        $purchase = PackagePurchase::where('transaction_id', $transactionId)->first();

        if (!$purchase) {
            return redirect()->route('shop.home')
                ->with('error', 'رکورد خرید یافت نشد.');
        }

        // تأیید پرداخت
        $result = $this->paymentService->verifyPayment($transactionId);

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
            $this->smsManager->notifyAdminSale('package', 'admin_purchase_paid', $vars, $purchase);
        }

        // صفحه‌ی نتیجه + شمارش معکوس برای بازگشت به فروشگاه
        if ($purchase->callback_url && !$this->isInternalCallback($purchase->callback_url)) {
            return redirect()->route('payment.return', array_merge($request->query(), [
                'purchase' => $purchase->id,
            ]));
        }

        // callback داخلی/ویترین پنل → صفحه‌ی نتیجه‌ی خود پنل
        return redirect()->route('payment.result', $purchase)
            ->with(($result['paid'] ?? false) ? 'success' : 'error',
                $result['message'] ?? (($result['paid'] ?? false) ? 'پرداخت با موفقیت تأیید شد.' : 'پرداخت ناموفق بود.'));
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
