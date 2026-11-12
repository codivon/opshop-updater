<?php

namespace App\Services\Payments;

use Shetabit\Multipay\Contracts\ReceiptInterface;
use Shetabit\Multipay\Drivers\Saman\Saman as BaseSaman;
use Shetabit\Multipay\Exceptions\InvalidPaymentException;
use Shetabit\Multipay\Receipt;
use Shetabit\Multipay\RedirectionForm;
use Shetabit\Multipay\Request;

/**
 * درایور سامان با WSDL لوکال‌کش‌شده
 *
 * رفع باگ: «SOAP-ERROR: Parsing WSDL: Couldn't load from
 * 'https://sep.shaparak.ir/Payments/InitPayment.asmx?WSDL'»
 *
 * WSDL با cURL دانلود و در storage/app/wsdl-cache کش می‌شود؛ SoapClient با
 * فایل محلی ساخته می‌شود (اکتشاف endpoint از WSDL همچنان به آدرس واقعی
 * sep.shaparak.ir اشاره دارد و فراخوانی SOAP روی سوکت مستقیم انجام می‌شود).
 * اگر cURL هم نتواند و کشی نباشد، همان URL ریموت پاس داده می‌شود تا رفتار
 * قدیمی حفظ شود (پیام خطای واضح‌تری هم به متن اضافه می‌شود).
 */
class SamanCached extends BaseSaman
{
    /**
     * ساخت SoapClient با WSDL لوکال + تنظیمات TLS/کش
     */
    private function soapClient(string $wsdlUrl): \SoapClient
    {
        $localWsdl = app(WsdlCache::class)->resolve($wsdlUrl, 'saman-'.md5($wsdlUrl));

        // کانتکست HTTP: TLS + پروکسی شاپرک (در صورت تنظیم)
        $httpContext = [];

        $proxy = shaparak_proxy();

        if ($proxy) {
            $httpContext['proxy'] = preg_match('#^(socks5?h?|tcp)://#i', $proxy)
                ? $proxy
                : preg_replace('#^(https?)://#i', 'tcp://', $proxy);
            $httpContext['request_fulluri'] = true;
        }

        try {
            return new \SoapClient($localWsdl, [
                'encoding' => 'UTF-8',
                'cache_wsdl' => WSDL_CACHE_DISK,   // کش دیسک (سرعت + مقاومت)
                'connection_timeout' => 25,
                'stream_context' => stream_context_create([
                    'http' => $httpContext,
                    'ssl' => [
                        'ciphers' => 'DEFAULT:!DH',
                        'verify_peer' => true,
                        'verify_host' => 2,
                        'SNI_enabled' => true,
                        'crypto_method' => STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
                    ],
                ]),
                'exceptions' => true,
                'trace' => false,
            ]);
        } catch (\SoapFault $e) {
            // اگر با فایل لوکال هم نشد → پیام راهنمای فارسی با ریشه خطا
            $hint = str_contains($e->getMessage(), 'failed to load external entity')
                ? ' (WSDL دانلود نشد؛ سرور شما به شاپرک دسترسی ندارد — شاپرک به IPهای خارج از ایران پاسخ نمی‌دهد؛ در «تنظیمات → پرداخت» پروکسی ایرانی ثبت کنید)'
                : '';

            throw new \SoapFault(
                $e->faultcode ?? 'WSDL',
                'خطای اتصال به درگاه سامان: '.$e->getMessage().$hint
            );
        }
    }

    public function purchase(): string|int|null
    {
        $data = [
            'MID' => $this->settings->merchantId,
            'ResNum' => $this->invoice->getUuid(),
            'Amount' => $this->invoice->getAmount() * 10, // convert to rial
            'CellNumber' => '',
        ];

        if (! empty($this->invoice->getDetails()['mobile'])) {
            $data['CellNumber'] = $this->invoice->getDetails()['mobile'];
        }

        $soap = $this->soapClient($this->settings->apiPurchaseUrl);

        $response = $soap->RequestToken($data['MID'], $data['ResNum'], $data['Amount'], $data['CellNumber']);

        $status = (int) $response;

        if ($status < 0) { // if something has done in a wrong way
            $this->purchaseFailed($response);
        }

        // set transaction id
        $this->invoice->transactionId($response);

        // return the transaction's id
        return $this->invoice->getTransactionId();
    }

    public function pay(): RedirectionForm
    {
        return parent::pay();
    }

    public function verify(): ReceiptInterface
    {
        $data = [
            'RefNum' => Request::input('RefNum'),
            'merchantId' => $this->settings->merchantId,
        ];

        $soap = $this->soapClient($this->settings->apiVerificationUrl);

        $status = (int) $soap->VerifyTransaction($data['RefNum'], $data['merchantId']);

        if ($status < 0) {
            $this->notVerified($status);
        }

        $receipt = new Receipt('saman', $data['RefNum']);
        $receipt->detail([
            'traceNo' => Request::input('TraceNo'),
            'referenceNo' => Request::input('RRN'),
            'transactionId' => Request::input('RefNum'),
            'cardNo' => Request::input('SecurePan'),
        ]);

        return $receipt;
    }

    /** معادلِ notVerified والد (در کلاس پایه private است) */
    private function notVerified($status)
    {
        $translations = [
            -1 => 'خطا در پردازش اطلاعات ارسالی (مشکل در یکی از ورودی‌ها و ناموفق بودن فراخوانی متد برگشت تراکنش)',
            -3 => 'ورودی‌ها حاوی کاراکترهای غیرمجاز می‌باشند.',
            -4 => 'کلمه عبور یا کد فروشنده اشتباه است (Merchant Authentication Failed)',
            -6 => 'سند قبال برگشت کامل یافته است. یا خارج از زمان ۳۰ دقیقه ارسال شده است.',
            -7 => 'رسید دیجیتالی تهی است.',
            -8 => 'طول ورودی‌ها بیشتر از حد مجاز است.',
            -9 => 'وجود کاراکترهای غیرمجاز در مبلغ برگشتی.',
            -10 => 'رسید دیجیتالی به صورت Base64 نیست (حاوی کاراکترهای غیرمجاز است)',
            -11 => 'طول ورودی‌ها کمتر از حد مجاز است.',
            -12 => 'مبلغ برگشتی منفی است.',
            -13 => 'مبلغ برگشتی برای برگشت جزئی بیش از مبلغ برگشت نخورده‌ی رسید دیجیتالی است.',
            -14 => 'چنین تراکنشی تعریف نشده است.',
            -15 => 'مبلغ برگشتی به صورت اعشاری داده شده است.',
            -16 => 'خطای داخلی سیستم',
            -17 => 'برگشت زدن جزئی تراکنش مجاز نمی‌باشد.',
            -18 => 'IP Address فروشنده نامعتبر است و یا رمز تابع بازگشتی (reverseTransaction) اشتباه است.',
        ];

        $message = $translations[$status] ?? ('خطای نامشخص در تأیید تراکنش سامان — کد: '.$status);

        throw new InvalidPaymentException($message);
    }
}
