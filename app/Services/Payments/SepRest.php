<?php

namespace App\Services\Payments;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Shetabit\Multipay\Abstracts\Driver;
use Shetabit\Multipay\Contracts\ReceiptInterface;
use Shetabit\Multipay\Exceptions\InvalidPaymentException;
use Shetabit\Multipay\Exceptions\PurchaseFailedException;
use Shetabit\Multipay\Invoice;
use Shetabit\Multipay\Receipt;
use Shetabit\Multipay\RedirectionForm;
use Shetabit\Multipay\Request;

/**
 * درایور سامان SEP — REST (بدون SOAP) — نسخه ۲
 *
 * دو مُد دارد (خودکار بر اساس شکل TerminalId یا از تنظیمات):
 *
 *  ۱) v1 (پیش‌فرض برای ترمینال‌های جدید UUID):
 *     POST  {apiV1Init}     → { terminalId, amount(تومان), callbackUrl, orderId, userId }
 *       پاسخ: { status:"EN-00", token:"…" }
 *     ریدایرکت مرورگر: https://sep.shaparak.ir/Payment/{token}
 *     POST  {apiV1Verify}   → { token, terminalId } (یا refNum)
 *
 *  ۲) onlinepg (آسان‌پرداخت — ترمینال عددی قدیمی):
 *     POST  /onlinepg/onlinepg → { action:"token", TerminalId, Amount(ریال), ResNum, RedirectUrl }
 *       پاسخ: { status:1, token:"…" }
 *     ریدایرکت: https://sep.shaparak.ir/OnlinePG/SendToken?token=…
 *     POST  /verifyTxnRandomSessionkey/ipg/VerifyTransaction → { RefNum, TerminalNumber }
 *
 *  🌐 پشتیبانی از پروکسی (مهم):
 *  شاپرک به IPهای خارج از ایران پاسخ نمی‌دهد (TCP timeout). اگر سرور شما
 *  خارج از ایران است، در «تنظیمات → پرداخت» یک پروکسی داخلی (http/socks5)
 *  ثبت کنید؛ همه درخواست‌های شاپرک از آن عبور خواهند کرد.
 */
class SepRest extends Driver
{
    /** @var PendingRequest */
    private $client;

    public function __construct(Invoice $invoice, $settings)
    {
        $this->invoice($invoice);
        $this->settings = (object) $settings;

        $this->client = $this->buildClient();
    }

    /* ===================================================================
     *  ساخت HTTP client با تایم‌اوت مناسب + پروکسی شاپرک (در صورت تنظیم)
     * =================================================================== */
    private function buildClient()
    {
        $request = Http::asJson()
            ->acceptJson()
            ->timeout(40)
            ->connectTimeout(20);

        $proxy = shaparak_proxy();

        if ($proxy) {
            $request = $request->withOptions([
                'proxy' => [
                    'http' => $proxy,
                    'https' => $proxy,
                ],
            ]);
        }

        return $request;
    }

    /** مُد درایور: v1 | onlinepg (خودکار از شکل ترمینال) */
    private function mode(): string
    {
        $mode = strtolower(trim((string) ($this->settings->mode ?? '')));

        if (in_array($mode, ['v1', 'onlinepg'], true)) {
            return $mode;
        }

        $terminal = trim((string) $this->terminalId());

        // UUID (ترمینال‌های جدید سپ) → v1
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $terminal)) {
            return 'v1';
        }

        return 'onlinepg';
    }

    private function terminalId(): string
    {
        // terminalId یا merchantId (برای سازگاری با پیکربندی قبلی)
        return (string) ($this->settings->terminalId
            ?? $this->settings->merchantId
            ?? '');
    }

    /* ===================================================================
     *  Purchase — دریافت توکن
     * =================================================================== */
    public function purchase(): string|int|null
    {
        if ($this->mode() === 'v1') {
            return $this->purchaseV1();
        }

        return $this->purchaseOnlinePg();
    }

    /** مُد v1 — REST جدید سپ (ترمینال UUID) */
    private function purchaseV1()
    {
        $terminal = $this->terminalId();

        if ($terminal === '') {
            throw new PurchaseFailedException('شناسه ترمینال سپ (UUID) در تنظیمات درگاه ثبت نشده است.');
        }

        $payload = [
            'terminalId' => $terminal,
            'amount' => (int) $this->invoice->getAmount(),      // تومان
            'callbackUrl' => (string) $this->settings->callbackUrl,
            'orderId' => (string) $this->invoice->getUuid(),
            'userId' => (string) ($this->invoice->getDetails()['mobile'] ?? ''),
        ];

        try {
            $response = $this->client->post($this->settings->apiV1Init, $payload);
        } catch (\Throwable $e) {
            throw new PurchaseFailedException('ارتباط با درگاه سپ برقرار نشد: '.$this->connectionHint($e));
        }

        $json = (array) $response->json();

        $status = (string) ($json['status'] ?? $json['Status'] ?? '');
        $token = (string) ($json['token'] ?? $json['Token'] ?? '');

        $isSuccess = strtoupper($status) === 'EN-00'
            || (bool) ($json['StatusIsSuccessful'] ?? false)
            || ($token !== '' && in_array($status, ['1', 'OK'], true));

        if (! $isSuccess || $token === '') {
            $msg = $json['error'] ?? $json['Message'] ?? $json['errorDesc'] ?? null;

            throw new PurchaseFailedException(
                'دریافت توکن پرداخت سپ ناموفق بود'.($status !== '' ? " (وضعیت {$status})" : '').
                ($msg ? ': '.$msg : $this->httpError($response->status()))
            );
        }

        $this->invoice->transactionId($token);

        return $token;
    }

    /** مُد onlinepg — آسان‌پرداخت (ترمینال عددی) */
    private function purchaseOnlinePg()
    {
        $terminal = $this->terminalId();

        if ($terminal === '') {
            throw new PurchaseFailedException('کد پایانه/پذیرنده سامان در تنظیمات درگاه ثبت نشده است.');
        }

        $fields = [
            'action' => 'token',
            'TerminalId' => $terminal,
            'Amount' => $this->invoice->getAmount() * 10, // ریال
            'ResNum' => (string) $this->invoice->getUuid(),
            'RedirectUrl' => (string) $this->settings->callbackUrl,
            'CellNumber' => (string) ($this->invoice->getDetails()['mobile'] ?? ''),
        ];

        try {
            $response = $this->client->post($this->settings->apiGetToken, $fields);
        } catch (\Throwable $e) {
            throw new PurchaseFailedException('ارتباط با درگاه سامان برقرار نشد: '.$this->connectionHint($e));
        }

        $json = (array) $response->json();

        $status = $json['status'] ?? $json['Status'] ?? null;
        $token = (string) ($json['token'] ?? $json['Token'] ?? '');

        if ((int) $status !== 1 && $token === '') {
            $msg = $json['errorDesc'] ?? $json['Message'] ?? $json['errorCode'] ?? null;

            throw new PurchaseFailedException(
                'دریافت توکن پرداخت سامان ناموفق بود'.($msg !== null ? ': '.$msg : $this->httpError($response->status()))
            );
        }

        $this->invoice->transactionId($token);

        return $token;
    }

    /* ===================================================================
     *  Pay — ریدایرکت مرورگر به صفحه پرداخت
     * =================================================================== */
    public function pay(): RedirectionForm
    {
        $token = $this->invoice->getTransactionId();

        if ($this->mode() === 'v1') {
            // صفحه پرداخت جدید سپ: آدرس توکنی
            $url = rtrim((string) ($this->settings->apiV1PaymentPage
                ?? 'https://sep.shaparak.ir/Payment'), '/').'/'.$token;

            return $this->redirectWithForm($url, [], 'GET');
        }

        // onlinepg — فرم POST به OnlinePG (یا لینک SendToken)
        $url = (string) $this->settings->apiPaymentUrl; // https://sep.shaparak.ir/OnlinePG/OnlinePG

        // اگر آدرس SendToken تنظیم شده باشد → ریدایرکت ساده GET
        if (! empty($this->settings->apiSendToken)) {
            return $this->redirectWithForm(
                $this->settings->apiSendToken.'?token='.urlencode($token),
                [],
                'GET'
            );
        }

        return $this->redirectWithForm($url, [
            'Token' => $token,
            'RedirectUrl' => (string) $this->settings->callbackUrl,
        ], 'POST');
    }

    /* ===================================================================
     *  Verify — تأیید تراکنش
     * =================================================================== */
    public function verify(): ReceiptInterface
    {
        if ($this->mode() === 'v1') {
            return $this->verifyV1();
        }

        return $this->verifyOnlinePg();
    }

    private function verifyV1(): ReceiptInterface
    {
        $token = (string) ($this->invoice->getTransactionId() ?: Request::input('token') ?: Request::input('Token'));
        $refNum = (string) (Request::input('RefNum') ?: Request::input('refNum') ?: '');
        $orderId = (string) (Request::input('OrderId') ?: Request::input('orderId') ?: '');

        if ($token === '' && $refNum === '') {
            throw new InvalidPaymentException('توکن/شماره ارجاع تراکنش سپ یافت نشد.');
        }

        $payload = array_filter([
            'token' => $token,
            'refNum' => $refNum ?: null,
            'terminalId' => $this->terminalId(),
            'orderId' => $orderId ?: null,
        ]);

        try {
            $response = $this->client->post($this->settings->apiV1Verify, $payload);
        } catch (\Throwable $e) {
            throw new InvalidPaymentException('ارتباط با سرور تأیید سپ برقرار نشد: '.$this->connectionHint($e));
        }

        $json = (array) $response->json();
        $data = (array) ($json['data'] ?? $json); // برخی نسخه‌ها فیلدها را nested می‌فرستند

        $status = strtoupper((string) ($json['status'] ?? $data['status'] ?? ''));

        if ($status !== 'EN-00' && ! (bool) ($json['StatusIsSuccessful'] ?? $data['StatusIsSuccessful'] ?? false)) {
            $msg = $json['error'] ?? $data['error'] ?? $json['Message'] ?? $data['Message'] ?? 'تأیید تراکنش سپ ناموفق بود.';

            throw new InvalidPaymentException($msg.($status !== '' ? " ({$status})" : ''));
        }

        // مبلغ تأییدشده اگر برگشته باشد چک می‌شود (تومان)
        $verifiedAmount = (int) ($data['amount'] ?? $data['Amount'] ?? $data['affectiveAmount'] ?? 0);

        if ($verifiedAmount > 0 && $verifiedAmount !== (int) $this->invoice->getAmount()) {
            throw new InvalidPaymentException('مبلغ تراکنش تأییدشده با مبلغ فاکتور برابر نیست.');
        }

        $receipt = new Receipt('sep', $refNum ?: $token);
        $receipt->detail([
            'traceNo' => $data['traceNo'] ?? $data['TraceNo'] ?? Request::input('TraceNo'),
            'referenceNo' => $data['rrn'] ?? $data['RRN'] ?? Request::input('RRN'),
            'transactionId' => $refNum ?: $token,
            'cardNo' => $data['maskedPan'] ?? $data['SecurePan'] ?? Request::input('SecurePan'),
        ]);

        return $receipt;
    }

    private function verifyOnlinePg(): ReceiptInterface
    {
        $token = (string) ($this->invoice->getTransactionId() ?: Request::input('Token'));
        $refNum = (string) (Request::input('RefNum', ''));

        // اگر RefNum مستقیم از callback آمده → همان مسیر تأیید آسان‌پرداخت
        $refNum = $refNum ?: $token;

        if ($refNum === '') {
            throw new InvalidPaymentException('شماره ارجاع تراکنش سامان یافت نشد.');
        }

        try {
            $response = $this->client->post($this->settings->apiVerificationUrl, [
                'RefNum' => $refNum,
                'TerminalNumber' => $this->terminalId(),
            ]);
        } catch (\Throwable $e) {
            throw new InvalidPaymentException('ارتباط با سرور تأیید سامان برقرار نشد: '.$this->connectionHint($e));
        }

        $json = (array) $response->json();

        $resultCode = (int) ($json['ResultCode'] ?? $json['resultCode'] ?? -999);
        $data = (array) ($json['data'] ?? []);

        if ($resultCode !== 0) {
            throw new InvalidPaymentException(
                $this->onlinePgErrorText($resultCode, (string) ($json['errorDesc'] ?? $data['errorDesc'] ?? ''))
            );
        }

        // مبلغ (ریال) در پاسخ آسان‌پرداخت
        $verifiedAmount = (int) ($data['transactionAmount'] ?? $data['amount'] ?? $json['Amount'] ?? 0);

        if ($verifiedAmount > 0 && $verifiedAmount !== $this->invoice->getAmount() * 10) {
            throw new InvalidPaymentException('مبلغ تراکنش تأییدشده با مبلغ فاکتور برابر نیست.');
        }

        $receipt = new Receipt('sep', $refNum);
        $receipt->detail([
            'traceNo' => $data['traceNo'] ?? Request::input('TraceNo'),
            'referenceNo' => $data['RRN'] ?? Request::input('RRN'),
            'transactionId' => $refNum,
            'cardNo' => $data['maskedPan'] ?? $data['SecurePan'] ?? Request::input('SecurePan'),
        ]);

        return $receipt;
    }

    /* ------------------------------------------------------------------ */

    /** پیام راهنما برای خطای اتصال (احتمال محدودیت جغرافیایی شاپرک) */
    private function connectionHint(\Throwable $e): string
    {
        $msg = $e->getMessage();

        $hint = str_contains($msg, 'timed out') || str_contains($msg, 'timeout')
            ? ' — سرور شما به sep.shaparak.ir دسترسی ندارد (شاپرک به IPهای خارج از ایران پاسخ نمی‌دهد). '
              .'اگر هاست شما خارج از ایران است، در «تنظیمات → پرداخت» یک پروکسی داخلی (ایرانی) ثبت کنید.'
            : ' — دسترسی خروجی سرور به شاپرک را بررسی کنید.';

        return $msg.$hint;
    }

    private function httpError(int $status): string
    {
        return $status > 0 ? ' — خطای HTTP '.$status : '';
    }

    private function onlinePgErrorText(int $code, string $fallback): string
    {
        return match ($code) {
            -2 => 'تراکنش یافت نشد.',
            -6 => 'بیش از ۳۰ دقیقه از تراکنش گذشته است.',
            2 => 'درخواست تکراری (قبلاً تأیید شده).',
            5 => 'تراکنش برگشت خورده است.',
            -104 => 'پایانه غیرفعال است.',
            -105 => 'پایانه نامعتبر است.',
            -106 => 'آدرس IP سرور مجاز نیست.',
            -108 => 'شماره کارت / پایانه نامعتبر.',
            default => ($fallback !== '' ? $fallback : 'تأیید تراکنش ناموفق بود.')." (کد {$code})",
        };
    }
}
