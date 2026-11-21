<?php

namespace App\Services\Payments;

use DateTime;
use DateTimeZone;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Shetabit\Multipay\Contracts\ReceiptInterface;
use Shetabit\Multipay\Drivers\Sadad\Sadad as BaseSadad;
use Shetabit\Multipay\Exceptions\InvalidPaymentException;
use Shetabit\Multipay\Exceptions\PurchaseFailedException;
use Shetabit\Multipay\Invoice;
use Shetabit\Multipay\Request;
use Throwable;

/**
 * درایور رسمی سداد (بانک ملی) — دقیقاً مطابق نمونهٔ کد رسمی بانک (PHP-master.zip)
 *
 * چرا این درایور وجود دارد؟ (Task 97)
 *  ۱) برچسب‌های فرم مدیریت جابجا بود و خطای بانک عمومی «عملیات ناموفق بود» بود و
 *     کد واقعی (ResCode) در لاگ گم می‌شد → تشخیص ریشه‌یابی ناممکن بود.
 *  ۲) endpointهای config با نمونهٔ رسمی بانک فرق داشت (/vpg/api/v0/...).
 *  ۳) این درایور پاسخ خام بانک را لاگ می‌کند، کد خطا را به پیام اضافه می‌کند و
 *     پیلود را دقیقاً مطابق نمونهٔ رسمی می‌سازد (فیلدهای خالی مثل UserId خالی ارسال نمی‌شوند).
 *
 * نکتهٔ امنیتی: کلید ترمینال و توکن هرگز کامل در لاگ نوشته نمی‌شوند (فقط چند کاراکتر اول/آخر).
 */
class SadadOfficial extends BaseSadad
{
    /**
     * وضعیت آخرین پاسخ HTTP (برای پیام‌ها و لاگ).
     */
    protected int $lastHttpStatus = 0;

    public function __construct(Invoice $invoice, array|object $settings, ?Client $client = null)
    {
        parent::__construct($invoice, $settings);

        $this->client = $client ?? new Client([
            'timeout' => 30,
            'connect_timeout' => 15,
        ]);
    }

    /**
     * خرید: ساخت توکن پرداخت از ساداد.
     * برای مُدهای غیر normal (PaymentByIdentity / PaymentByMultiIdentity) همان پیاده‌سازی والد استفاده می‌شود.
     */
    public function purchase(): string|int|null
    {
        $mode = strtolower((string) ($this->settings->mode ?? 'normal'));

        if ($mode !== 'normal') {
            return parent::purchase();
        }

        $terminalId = (string) ($this->settings->terminalId ?? '');
        $merchantId = (string) ($this->settings->merchantId ?? '');
        $key = (string) ($this->settings->key ?? '');

        // ۱) گارد پیکربندی ناقص — پیام واضح به‌جای خطای عمومی بانک
        if ($terminalId === '' || $merchantId === '' || $key === '') {
            Log::error('Sadad purchase blocked: incomplete gateway credentials', [
                'has_terminal' => $terminalId !== '',
                'has_merchant' => $merchantId !== '',
                'has_key' => $key !== '',
            ]);

            throw new PurchaseFailedException(
                'اطلاعات درگاه سداد (بانک ملی) در پنل مدیریت تکمیل نیست — «شماره ترمینال»، «شماره پذیرنده» و «کلید ترمینال» را در تنظیمات درگاه‌ها وارد کنید.'
            );
        }

        if ($terminalId === $merchantId) {
            // علامت خطای برچسب‌جابجای فرم قدیمی: شماره پذیرنده در فیلد ترمینال هم ریخته شده
            Log::warning('Sadad config suspicious: TerminalId equals MerchantId (likely the old swapped-label form was used)');
        }

        // ۲) امضا دقیقاً مطابق نمونهٔ رسمی: encrypt_pkcs7("TerminalId;OrderId;Amount")
        $orderId = crc32($this->invoice->getUuid());
        $amount = $this->convertAmountToRial($this->invoice->getAmount());
        $signData = $this->encrypt_pkcs7("$terminalId;$orderId;$amount", $key);
        $iranTime = new DateTime('now', new DateTimeZone('Asia/Tehran'));

        // ۳) پیلود رسمی: ۷ فیلد اصلی نمونهٔ بانک؛ additionalData/UserId فقط با مقدار واقعی
        $data = [
            'TerminalId' => $terminalId,
            'MerchantId' => $merchantId,
            'Amount' => $amount,
            'OrderId' => $orderId,
            'LocalDateTime' => $iranTime->format('m/d/Y g:i:s a'),
            'ReturnUrl' => $this->settings->callbackUrl,
            'SignData' => $signData,
        ];

        $description = $this->invoice->getDetails()['description'] ?? ($this->settings->description ?? null);
        if (! empty($description)) {
            $data['additionalData'] = $description;
        }

        $mobile = $this->invoice->getDetails()['mobile'] ?? null;
        if (! empty($mobile)) {
            $data['UserId'] = $mobile;
        }

        $url = $this->settings->apiPaymentUrl;

        // ۴) درخواست + لاگ کامل پاسخ
        $rawBody = $this->postForPurchase($url, $data);

        $body = json_decode($rawBody);

        if (empty($body) || ! isset($body->ResCode)) {
            Log::error('Sadad purchase: non-JSON response', [
                'http_status' => $this->lastHttpStatus,
                'body' => mb_substr($rawBody, 0, 500),
                'terminal_id_tail' => mb_substr($terminalId, -4),
            ]);

            throw new PurchaseFailedException(
                "پاسخ نامعتبر از درگاه سداد (HTTP {$this->lastHttpStatus}) — جزئیات در laravel.log ثبت شد."
            );
        }

        $resCode = (int) $body->ResCode;

        if ($resCode !== 0) {
            $bankDescription = trim((string) ($body->Description ?? ''));
            $meaning = $this->translateStatus($resCode);

            Log::error('Sadad purchase rejected by bank', [
                'res_code' => $resCode,
                'bank_description' => $bankDescription,
                'meaning' => $meaning,
                'http_status' => $this->lastHttpStatus,
                'order_id' => $orderId,
                'amount_rial' => $amount,
                'terminal_id_tail' => mb_substr($terminalId, -4),
                'merchant_id_tail' => mb_substr($merchantId, -4),
            ]);

            throw new PurchaseFailedException(
                $this->rejectedMessage('خرید در درگاه سداد رد شد', $resCode, $bankDescription, $meaning)
            );
        }

        $token = $body->Token ?? null;

        if (empty($token)) {
            Log::error('Sadad purchase: ResCode=0 without Token', ['body' => mb_substr($rawBody, 0, 500)]);

            throw new PurchaseFailedException('درگاه سداد توکن پرداخت برنگرداند — لطفاً دوباره تلاش کنید.');
        }

        Log::info('Sadad purchase succeeded', [
            'order_id' => $orderId,
            'amount_rial' => $amount,
            'token_head' => mb_substr((string) $token, 0, 8).'…',
        ]);

        $this->invoice->transactionId($token);

        return $this->invoice->getTransactionId();
    }

    /**
     * تأیید پرداخت: همان پروتکل رسمی (Advice/Verify) + لاگ کامل + پیام خطای کد دار.
     */
    public function verify(): ReceiptInterface
    {
        $key = (string) ($this->settings->key ?? '');
        $token = (string) ($this->invoice->getTransactionId() ?? Request::input('token') ?? '');
        $callbackResCode = Request::input('ResCode');

        // اگر بانک در کال‌بک ResCode غیر صفر داده، اصلاً verify لازم نیست — پرداخت انجام نشده
        if ($callbackResCode !== null && (int) $callbackResCode !== 0) {
            $resCode = (int) $callbackResCode;
            $meaning = $this->translateStatus($resCode);

            Log::warning('Sadad callback reported failed payment', [
                'res_code' => $resCode,
                'meaning' => $meaning,
            ]);

            throw new InvalidPaymentException(
                $this->rejectedMessage('پرداخت در درگاه سداد ناتمام ماند', $resCode, '', $meaning),
                $resCode
            );
        }

        if ($token === '') {
            throw new InvalidPaymentException('توکن تراکنش سداد برای تأیید یافت نشد.');
        }

        $data = [
            'Token' => $token,
            'SignData' => $this->encrypt_pkcs7($token, $key),
        ];

        $url = $this->settings->apiVerificationUrl;
        $rawBody = $this->postForVerify($url, $data);

        $body = json_decode($rawBody);

        if (empty($body) || ! isset($body->ResCode)) {
            Log::error('Sadad verify: non-JSON response', [
                'http_status' => $this->lastHttpStatus,
                'body' => mb_substr($rawBody, 0, 500),
            ]);

            throw new InvalidPaymentException(
                "تأیید تراکنش در ساداد پاسخ نامعتبر گرفت (HTTP {$this->lastHttpStatus}) — از دکمهٔ «بررسی مجدد وضعیت» استفاده کنید."
            );
        }

        $resCode = (int) $body->ResCode;

        if ($resCode !== 0) {
            $bankDescription = trim((string) ($body->Description ?? ''));
            $meaning = $this->translateStatus($resCode);

            Log::error('Sadad verify rejected by bank', [
                'res_code' => $resCode,
                'bank_description' => $bankDescription,
                'meaning' => $meaning,
                'http_status' => $this->lastHttpStatus,
            ]);

            throw new InvalidPaymentException(
                $this->rejectedMessage('تأیید پرداخت سداد ناموفق بود', $resCode, $bankDescription, $meaning),
                $resCode
            );
        }

        $traceNo = (string) ($body->SystemTraceNo ?? '');
        $referenceNo = (string) ($body->RetrivalRefNo ?? $body->RetrievalRefNo ?? '');

        Log::info('Sadad verify succeeded', [
            'order_id' => (string) ($body->OrderId ?? ''),
            'trace_no' => $traceNo,
            'reference_no' => $referenceNo,
        ]);

        $receipt = $this->createReceipt($traceNo !== '' ? $traceNo : $token);
        $receipt->detail([
            'orderId' => (string) ($body->OrderId ?? ''),
            'traceNo' => $traceNo,
            'referenceNo' => $referenceNo,
            'description' => (string) ($body->Description ?? ''),
        ]);

        return $receipt;
    }

    /**
     * POST به endpoint خرید با لاگ خطای اتصال.
     */
    private function postForPurchase(string $url, array $data): string
    {
        return $this->postJson($url, $data, 'purchase');
    }

    /**
     * POST به endpoint تأیید با لاگ خطای اتصال.
     */
    private function postForVerify(string $url, array $data): string
    {
        return $this->postJson($url, $data, 'verify');
    }

    private function postJson(string $url, array $data, string $phase): string
    {
        $options = [
            'json' => $data,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => '',
            ],
            'http_errors' => false,
        ];

        // پروکسی شاپرک (برای سرورهای خارج از ایران — تنظیمات → پرداخت)
        $proxy = shaparak_proxy();

        if ($proxy) {
            $options['proxy'] = $proxy;
        }

        try {
            $response = $this->client->request('POST', $url, $options);
        } catch (Throwable $e) {
            Log::error("Sadad {$phase}: connection failure", [
                'url' => $url,
                'exception' => get_class($e),
                'message' => mb_substr($e->getMessage(), 0, 300),
            ]);

            throw new PurchaseFailedException(
                'اتصال به درگاه سداد (بانک ملی) برقرار نشد — '.$this->describeConnectionError($e)
            );
        }

        $this->lastHttpStatus = $response->getStatusCode();

        return $response->getBody()->getContents();
    }

    private function describeConnectionError(Throwable $e): string
    {
        $message = $e->getMessage();

        return match (true) {
            str_contains($message, 'cURL error 28') => 'مهلت اتصال تمام شد؛ سرور شما به sadad.shaparak.ir دسترسی ندارد (شاپرک به IPهای خارج از ایران پاسخ نمی‌دهد — در «تنظیمات → پرداخت» پروکسی ایرانی ثبت کنید).',
            str_contains($message, 'cURL error 7') => 'اتصال شبکه رد شد (فایروال یا دسترسی خروجی سرور).',
            str_contains($message, 'cURL error 60') => 'گواهی SSL بانک اعتبارسنجی نشد.',
            default => mb_substr($message, 0, 200),
        };
    }

    /**
     * پیام استاندارد رد شدن: کد بانک + معنی + توضیح بانک (اگر متفاوت باشد).
     */
    private function rejectedMessage(string $prefix, int $resCode, string $bankDescription, string $meaning): string
    {
        $message = "{$prefix} — کد خطا {$resCode}";

        if ($meaning !== '' && ! str_starts_with($meaning, 'خطای ناشناخته')) {
            $message .= ": {$meaning}";
        } elseif ($bankDescription !== '') {
            $message .= ": {$bankDescription}";
        } else {
            $message .= ': خطای ناشناخته از سمت بانک';
        }

        if ($bankDescription !== '' && $bankDescription !== $meaning) {
            $message .= " (توضیح بانک: {$bankDescription})";
        }

        return $message;
    }

    /**
     * جدول کد خطاهای سداد (کپی رسمی از درایور پکیج + fallback کد دار).
     */
    private function translateStatus(int|string|null $status): string
    {
        $translations = [
            '0' => 'تراکنش با موفقیت انجام شد',
            '3' => 'پذیرنده کارت فعال نیست؛ لطفاً با بخش امور پذیرندگان تماس حاصل فرمایید',
            '23' => 'پذیرنده کارت نا معتبر؛ لطفاً با بخش امور پذیرندگان تماس حاصل فرمایید',
            '58' => 'انجام تراکنش مربوطه توسط پایانهٔ انجام‌دهنده مجاز نمی‌باشد',
            '61' => 'مبلغ تراکنش از حد مجاز بالاتر است',
            '101' => 'مهلت ارسال تراکنش به پایان رسیده است',
            '1000' => 'ترتیب پارامترهای ارسالی اشتباه می‌باشد',
            '1001' => 'پارامترهای پرداخت اشتباه می‌باشد',
            '1002' => 'خطا در سیستم — تراکنش ناموفق',
            '1003' => 'IP پذیرنده اشتباه است (IP سرور را در پنل پذیرندهٔ سداد ثبت کنید)',
            '1004' => 'شماره پذیرنده اشتباه است',
            '1005' => 'خطای دسترسی؛ لطفاً بعداً تلاش فرمایید',
            '1006' => 'خطا در سیستم',
            '1011' => 'درخواست تکراری — شماره سفارش تکراری می‌باشد',
            '1012' => 'اطلاعات پذیرنده صحیح نیست؛ یکی از موارد تاریخ، زمان یا کلید تراکنش اشتباه است',
            '1015' => 'پاسخ خطای نامشخص از سمت مرکز',
            '1017' => 'مبلغ درخواستی از حد مجاز تعریف‌شده برای این پذیرنده بیشتر است',
            '1018' => 'اشکال در تاریخ و زمان سیستم؛ تاریخ و زمان سرور را با بانک هماهنگ کنید',
            '1019' => 'امکان پرداخت از طریق سیستم شتاب برای این پذیرنده ممکن نیست',
            '1020' => 'پذیرنده غیرفعال شده است',
            '1023' => 'آدرس بازگشت پذیرنده نامعتبر است',
            '1024' => 'مهر زمانی پذیرنده نامعتبر است',
            '1025' => 'امضای تراکنش نامعتبر است (کلید ترمینال یا ترتیب فیلدهای امضا اشتباه است)',
            '1026' => 'شماره سفارش تراکنش نامعتبر است',
            '1027' => 'شماره پذیرنده نامعتبر است',
            '1028' => 'شماره ترمینال پذیرنده نامعتبر است',
            '1029' => 'آدرس IP پرداخت در محدوده آدرس‌های معتبر اعلام‌شده توسط پذیرنده نیست',
            '1030' => 'آدرس Domain پرداخت در محدوده آدرس‌های معتبر اعلام‌شده توسط پذیرنده نیست',
            '1031' => 'مهلت زمانی شما جهت پرداخت به پایان رسیده است؛ لطفاً مجدداً سعی بفرمایید',
            '1032' => 'پرداخت با این کارت برای پذیرندهٔ موردنظر شما امکان‌پذیر نیست',
            '1033' => 'به علت مشکل در سایت پذیرنده، پرداخت برای این پذیرنده غیرفعال شده است',
            '1036' => 'اطلاعات اضافی ارسال نشده یا دارای اشکال است',
            '1037' => 'شماره پذیرنده یا شماره ترمینال پذیرنده صحیح نمی‌باشد',
            '1053' => 'درخواست معتبر از سمت پذیرنده صورت نگرفته است؛ لطفاً اطلاعات پذیرنده خود را چک کنید',
            '1055' => 'مقدار غیرمجاز در ورود اطلاعات',
            '1056' => 'سیستم موقتاً قطع می‌باشد؛ لطفاً بعداً تلاش فرمایید',
            '1058' => 'سرویس پرداخت اینترنتی خارج از سرویس می‌باشد؛ لطفاً بعداً سعی بفرمایید',
            '1061' => 'اشکال در تولید کد یکتا؛ لطفاً مرورگر خود را بسته و عملیات پرداخت را مجدداً انجام دهید',
            '1064' => 'لطفاً مجدداً سعی بفرمایید',
            '1065' => 'ارتباط ناموفق؛ لطفاً چند لحظهٔ دیگر مجدداً سعی کنید',
            '1066' => 'سیستم سرویس‌دهی پرداخت موقتاً غیرفعال شده است',
            '1068' => 'با عرض پوزش به علت بروزرسانی، سیستم موقتاً قطع می‌باشد',
            '1072' => 'خطا در پردازش پارامترهای اختیاری پذیرنده',
            '1101' => 'مبلغ تراکنش نامعتبر است',
            '1103' => 'توکن ارسالی نامعتبر است',
            '1104' => 'اطلاعات تسهیم صحیح نیست',
            '1105' => 'تراکنش بازگشت داده شده است (مهلت زمانی به پایان رسیده است)',
        ];

        $status = (string) $status;

        return $translations[$status]
            ?? "خطای ناشناخته از سمت سداد — کد {$status}";
    }
}
