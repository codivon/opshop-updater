<?php

namespace Tests\Feature;

use App\Services\Payments\SadadOfficial;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Shetabit\Multipay\Constants\IranCurrency;
use Shetabit\Multipay\Exceptions\InvalidPaymentException;
use Shetabit\Multipay\Exceptions\PurchaseFailedException;
use Shetabit\Multipay\Invoice;
use Tests\TestCase;

/**
 * Task 97 — درایور رسمی سداد (بانک ملی)
 *
 * سناریوها: پیلود دقیقاً مطابق نمونهٔ رسمی بانک (PHP-master.zip) + امضای صحیح
 * 3DES/PKCS7 روی «TerminalId;OrderId;Amount» + پیام خطای کد دار (ResCode واقعی
 * دیگر گم نمی‌شود) + لاگ کامل پاسخ بانک + جریان verify.
 */
class SadadDriverTest extends TestCase
{
    private const TEST_KEY = '4iyqmhtQ1ThBYnBDnQB6+NwNZlesJd6p'; // فرمت واقعی کلید ترمینال (base64 24 بایتی)

    private function settings(array $overrides = []): array
    {
        return array_merge([
            'key' => self::TEST_KEY,
            'merchantId' => '000000140352535',
            'terminalId' => '24121342',
            'callbackUrl' => 'https://shop.example.com/payment/callback/ref123',
            'currency' => IranCurrency::TOMAN,
            'mode' => 'normal',
            'apiPaymentUrl' => 'https://sadad.test/vpg/api/v0/Request/PaymentRequest',
            'apiVerificationUrl' => 'https://sadad.test/vpg/api/v0/Advice/Verify',
            'apiPurchaseUrl' => 'https://sadad.test/VPG/Purchase',
            'description' => 'payment using sadad',
        ], $overrides);
    }

    private function driver(array $settings, MockHandler $handler): SadadOfficial
    {
        return new SadadOfficial(
            new Invoice,
            $settings,
            new Client(['handler' => HandlerStack::create($handler)])
        );
    }

    /** رمزگشایی SignData با الگوریتم رسمی بانک — برای مقایسه با رشتهٔ امضاشده */
    private function decryptSign(string $signData): string|false
    {
        return openssl_decrypt(
            base64_decode($signData),
            'DES-EDE3',
            base64_decode(self::TEST_KEY),
            OPENSSL_RAW_DATA
        );
    }

    public function test_config_uses_official_driver_and_urls(): void
    {
        // map باید به درایور رسمیِ پروژه اشاره کند
        $this->assertSame(SadadOfficial::class, config('payment.map.sadad'));

        // مقادیر «پیش‌فرضِ کد» را از خودِ فایل پیکربندی می‌سنجیم (envهای شبیه‌ساز
        // سندباکس روی config رانتایم اثر می‌گذارند و نباید نتیجهٔ تست را عوض کنند)
        $source = file_get_contents(config_path('payment.php'));

        $this->assertStringContainsString(
            "'apiPaymentUrl' => env('SADAD_API_PAYMENT_URL', 'https://sadad.shaparak.ir/vpg/api/v0/Request/PaymentRequest')",
            $source
        );
        $this->assertStringContainsString(
            "'apiVerificationUrl' => env('SADAD_API_VERIFICATION_URL', 'https://sadad.shaparak.ir/vpg/api/v0/Advice/Verify')",
            $source
        );
        $this->assertStringContainsString(
            "'apiPurchaseUrl' => env('SADAD_REDIRECT_URL', 'https://sadad.shaparak.ir/VPG/Purchase')",
            $source
        );
    }

    public function test_purchase_sends_official_payload_with_correct_sign(): void
    {
        $handler = new MockHandler([
            new Response(200, [], json_encode(['ResCode' => '0', 'Token' => 'TOKEN-ABC-123', 'Description' => ''])),
        ]);

        $invoice = new Invoice;
        $invoice->amount(5000); // تومان → باید 50000 ریال امضا و ارسال شود

        $driver = new SadadOfficial($invoice, $this->settings(), new Client(['handler' => HandlerStack::create($handler)]));

        $transactionId = $driver->purchase();

        $this->assertSame('TOKEN-ABC-123', $transactionId);

        $request = $handler->getLastRequest();
        $payload = json_decode($request->getBody()->getContents(), true);

        // فیلدهای اصلی نمونهٔ رسمی بانک
        $this->assertSame('24121342', $payload['TerminalId']);
        $this->assertSame('000000140352535', $payload['MerchantId']);
        $this->assertSame(50000, $payload['Amount'], 'مبلغ باید ریال باشد (تومان × ۱۰)');
        $this->assertSame('https://shop.example.com/payment/callback/ref123', $payload['ReturnUrl']);
        $this->assertMatchesRegularExpression(
            '/^\d{2}\/\d{2}\/\d{4} \d{1,2}:\d{2}:\d{2} (am|pm)$/',
            $payload['LocalDateTime'],
            'فرمت LocalDateTime باید مطابق نمونهٔ رسمی باشد'
        );
        $this->assertArrayHasKey('SignData', $payload);
        $this->assertArrayHasKey('additionalData', $payload);

        // امضا باید «TerminalId;OrderId;Amount» رمز‌شده با کلید ترمینال باشد
        $this->assertSame("24121342;{$payload['OrderId']};50000", $this->decryptSign($payload['SignData']));

        // UserId خالی ارسال نمی‌شود (فقط وقتی موبایل واقعی هست)
        $this->assertArrayNotHasKey('UserId', $payload);
    }

    public function test_purchase_includes_user_id_only_when_mobile_present(): void
    {
        $handler = new MockHandler([
            new Response(200, [], json_encode(['ResCode' => 0, 'Token' => 'T2'])),
        ]);

        $invoice = new Invoice;
        $invoice->amount(1000);
        $invoice->detail('mobile', '09121234567');

        $driver = new SadadOfficial($invoice, $this->settings(), new Client(['handler' => HandlerStack::create($handler)]));
        $driver->purchase();

        $payload = json_decode($handler->getLastRequest()->getBody()->getContents(), true);

        $this->assertSame('09121234567', $payload['UserId']);
    }

    public function test_purchase_rejection_includes_res_code_and_meaning(): void
    {
        Log::shouldReceive('error')->once()->withArgs(function (string $channel, array $ctx) {
            return str_contains($channel, 'Sadad')
                && ($ctx['res_code'] ?? null) === 1004
                && ($ctx['terminal_id_tail'] ?? null) === '1342';
        });

        $handler = new MockHandler([
            new Response(200, [], json_encode(['ResCode' => 1004, 'Description' => 'شماره پذيرنده اشتباه است'])),
        ]);

        $invoice = new Invoice;
        $invoice->amount(1000);

        $driver = new SadadOfficial($invoice, $this->settings(), new Client(['handler' => HandlerStack::create($handler)]));

        try {
            $driver->purchase();
            $this->fail('PurchaseFailedException expected');
        } catch (PurchaseFailedException $e) {
            // کد واقعی بانک دیگر گم نمی‌شود — در پیام و لاگ هر دو هست
            $this->assertStringContainsString('1004', $e->getMessage());
            $this->assertStringContainsString('شماره پذیرنده', $e->getMessage());
        }
    }

    public function test_purchase_non_json_response_reports_http_status(): void
    {
        Log::shouldReceive('error')->once();

        $handler = new MockHandler([
            new Response(503, [], '<html>Service Unavailable</html>'),
        ]);

        $invoice = new Invoice;
        $invoice->amount(1000);

        $driver = new SadadOfficial($invoice, $this->settings(), new Client(['handler' => HandlerStack::create($handler)]));

        try {
            $driver->purchase();
            $this->fail('PurchaseFailedException expected');
        } catch (PurchaseFailedException $e) {
            $this->assertStringContainsString('503', $e->getMessage());
        }
    }

    public function test_purchase_missing_credentials_clear_message(): void
    {
        Log::shouldReceive('error')->once();

        $invoice = new Invoice;
        $invoice->amount(1000);

        $driver = new SadadOfficial($invoice, $this->settings(['key' => '', 'merchantId' => '']), $this->mockClient());

        try {
            $driver->purchase();
            $this->fail('PurchaseFailedException expected');
        } catch (PurchaseFailedException $e) {
            $this->assertStringContainsString('تکمیل نیست', $e->getMessage());
            $this->assertStringContainsString('سداد', $e->getMessage());
        }
    }

    public function test_verify_success_builds_receipt(): void
    {
        Log::shouldReceive('info')->once();

        $handler = new MockHandler([
            new Response(200, [], json_encode([
                'ResCode' => 0,
                'Description' => 'موفق',
                'SystemTraceNo' => '123456',
                'RetrivalRefNo' => '789012',
                'OrderId' => '111222333',
            ])),
        ]);

        $invoice = new Invoice;
        $invoice->amount(5000);
        $invoice->transactionId('TOKEN-VERIFY-1');

        $driver = new SadadOfficial($invoice, $this->settings(), new Client(['handler' => HandlerStack::create($handler)]));

        $receipt = $driver->verify();

        $this->assertSame('123456', $receipt->getReferenceId());
        $this->assertSame('789012', $receipt->getDetail('referenceNo'));
        $this->assertSame('111222333', $receipt->getDetail('orderId'));

        // امضای verify باید روی توکن باشد (الگوی رسمی: encrypt_pkcs7(Token))
        $verifyPayload = json_decode($handler->getLastRequest()->getBody()->getContents(), true);
        $this->assertSame('TOKEN-VERIFY-1', $verifyPayload['Token']);
        $this->assertSame('TOKEN-VERIFY-1', $this->decryptSign($verifyPayload['SignData']));
    }

    public function test_verify_callback_failure_uses_res_code(): void
    {
        Log::shouldReceive('warning')->once()->withArgs(function (string $channel, array $ctx) {
            return str_contains($channel, 'Sadad') && ($ctx['res_code'] ?? null) === 1011;
        });

        // کال‌بک ساداد: ResCode غیر صفر در درخواست جاری
        // (نکته: shetabit/payment متد Request::input را با request() لاراول جایگزین می‌کند)
        $this->app->instance('request', Request::create('/payment/callback', 'POST', ['ResCode' => '1011']));

        $invoice = new Invoice;
        $invoice->amount(1000);
        $invoice->transactionId('TOKEN-X');

        $driver = new SadadOfficial($invoice, $this->settings(), $this->mockClient());

        try {
            $driver->verify();
            $this->fail('InvalidPaymentException expected');
        } catch (InvalidPaymentException $e) {
            $this->assertStringContainsString('1011', $e->getMessage());
            $this->assertStringContainsString('تکراری', $e->getMessage());
        }
    }

    public function test_verify_rejected_by_bank_message(): void
    {
        Log::shouldReceive('error')->once();

        $handler = new MockHandler([
            new Response(200, [], json_encode(['ResCode' => 1103, 'Description' => 'توکن ارسالی نامعتبر است'])),
        ]);

        $invoice = new Invoice;
        $invoice->amount(1000);
        $invoice->transactionId('EXPIRED-TOKEN');

        $driver = new SadadOfficial($invoice, $this->settings(), new Client(['handler' => HandlerStack::create($handler)]));

        try {
            $driver->verify();
            $this->fail('InvalidPaymentException expected');
        } catch (InvalidPaymentException $e) {
            $this->assertStringContainsString('1103', $e->getMessage());
            $this->assertStringContainsString('توکن', $e->getMessage());
        }
    }

    public function test_verify_non_json_reports_http_status(): void
    {
        Log::shouldReceive('error')->once();

        $handler = new MockHandler([
            new Response(500, [], 'Internal Server Error'),
        ]);

        $invoice = new Invoice;
        $invoice->amount(1000);
        $invoice->transactionId('TOKEN-Y');

        $driver = new SadadOfficial($invoice, $this->settings(), new Client(['handler' => HandlerStack::create($handler)]));

        try {
            $driver->verify();
            $this->fail('InvalidPaymentException expected');
        } catch (InvalidPaymentException $e) {
            $this->assertStringContainsString('500', $e->getMessage());
        }
    }

    private function mockClient(): Client
    {
        // کلاینت که هرگز نباید صدا بزند (مسیرها قبل از HTTP گارد می‌شوند)
        return new Client(['handler' => HandlerStack::create(new MockHandler([
            new \RuntimeException('HTTP should not be reached'),
        ]))]);
    }
}
