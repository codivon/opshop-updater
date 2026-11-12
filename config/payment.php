<?php

use App\Services\Payments\SamanCached;
use App\Services\Payments\SepRest;
use Shetabit\Multipay\Drivers\Aqayepardakht\Aqayepardakht;
use Shetabit\Multipay\Drivers\Asanpardakht\Asanpardakht;
use Shetabit\Multipay\Drivers\Atipay\Atipay;
use Shetabit\Multipay\Drivers\Azki\Azki;
use Shetabit\Multipay\Drivers\Behpardakht\Behpardakht;
use Shetabit\Multipay\Drivers\Digipay\Digipay;
use Shetabit\Multipay\Drivers\Etebarino\Etebarino;
use Shetabit\Multipay\Drivers\Fanavacard\Fanavacard;
use Shetabit\Multipay\Drivers\Idpay\Idpay;
use Shetabit\Multipay\Drivers\Irankish\Irankish;
use Shetabit\Multipay\Drivers\Local\Local;
use Shetabit\Multipay\Drivers\Nextpay\Nextpay;
use Shetabit\Multipay\Drivers\Novinopay\Novinopay;
use Shetabit\Multipay\Drivers\Omidpay\Omidpay;
use Shetabit\Multipay\Drivers\Parsian\Parsian;
use Shetabit\Multipay\Drivers\Pasargad\Pasargad;
use Shetabit\Multipay\Drivers\Payfa\Payfa;
use Shetabit\Multipay\Drivers\Payir\Payir;
use Shetabit\Multipay\Drivers\Paypal\Paypal;
use Shetabit\Multipay\Drivers\Payping\Payping;
use Shetabit\Multipay\Drivers\Paystar\Paystar;
use Shetabit\Multipay\Drivers\Poolam\Poolam;
use Shetabit\Multipay\Drivers\Rayanpay\Rayanpay;
use Shetabit\Multipay\Drivers\Sadad\Sadad;
use Shetabit\Multipay\Drivers\Sepehr\Sepehr;
use Shetabit\Multipay\Drivers\Sepordeh\Sepordeh;
use Shetabit\Multipay\Drivers\Sizpay\Sizpay;
use Shetabit\Multipay\Drivers\Toman\Toman;
use Shetabit\Multipay\Drivers\Vandar\Vandar;
use Shetabit\Multipay\Drivers\Walleta\Walleta;
use Shetabit\Multipay\Drivers\Yekpay\Yekpay;
use Shetabit\Multipay\Drivers\Zarinpal\Zarinpal;
use Shetabit\Multipay\Drivers\Zibal\Zibal;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Driver
    |--------------------------------------------------------------------------
    |
    | This value determines which of the following gateway to use.
    | You can switch to a different driver at runtime.
    |
    */
    'default' => 'zarinpal',

    /*
    |--------------------------------------------------------------------------
    | List of Drivers
    |--------------------------------------------------------------------------
    |
    | These are the list of drivers to use for this package.
    | You can change the name. Then you'll have to change
    | it in the map array too.
    |
    */
    'drivers' => [
        // درگاه آزمایشی — برای تست جریان پرداخت بدون درگاه واقعی
        // تنظیمات (title/description/...) از جدول gateways خوانده می‌شود (get_gateway_configs)
        'local' => [
            'title' => 'درگاه پرداخت آزمایشی',
            'description' => 'این درگاه فقط برای تست جریان پرداخت است — پول واقعی کم نمی‌شود',
            'orderLabel' => 'شماره سفارش',
            'amountLabel' => 'مبلغ',
            'payButton' => 'پرداخت (موفق)',
            'cancelButton' => 'لغو پرداخت',
            'callbackUrl' => 'http://yoursite.com/path/to',
        ],
        'fanavacard' => [
            'baseUri' => 'https://fcp.shaparak.ir',
            'apiPaymentUrl' => '_ipgw_//payment/',
            'apiPurchaseUrl' => 'ref-payment/RestServices/mts/generateTokenWithNoSign/',
            'apiVerificationUrl' => 'ref-payment/RestServices/mts/verifyMerchantTrans/',
            'apiReverseAmountUrl' => 'ref-payment/RestServices/mts/reverseMerchantTrans/',
            'username' => 'xxxxxxx',
            'password' => 'xxxxxxx',
            'callbackUrl' => 'http://yoursite.com/path/to',
            'currency' => 'T', // Can be R, T (Rial, Toman)
        ],
        'atipay' => [
            'atipayTokenUrl' => 'https://mipg.atipay.net/v1/get-token',
            'atipayRedirectGatewayUrl' => 'https://mipg.atipay.net/v1/redirect-to-gateway',
            'atipayVerifyUrl' => 'https://mipg.atipay.net/v1/verify-payment',
            'apikey' => '',
            'currency' => 'R', // Can be R, T (Rial, Toman)
            'callbackUrl' => 'http://yoursite.com/path/to',
            'description' => 'payment using Atipay',
        ],
        'asanpardakht' => [
            'apiPaymentUrl' => 'https://asan.shaparak.ir',
            'apiRestPaymentUrl' => 'https://ipgrest.asanpardakht.ir/v1/',
            'username' => '',
            'password' => '',
            'merchantConfigID' => '',
            'currency' => 'T', // Can be R, T (Rial, Toman)
            'callbackUrl' => 'http://yoursite.com/path/to',
            'description' => 'payment using asanpardakht',
        ],
        'novinopay' => [
            // درایور رسمی نوین‌پی (پورت از shetabit/multipay v3.6.0 در packages/shetabit)
            // درگاه آدرس صفحهٔ پرداخت (payment_url) را در پاسخ purchase برمی‌گرداند؛ URL اضافه لازم نیست
            'apiPurchaseUrl' => 'https://novinopay.com/merchant/invoices/add',
            'apiVerificationUrl' => 'https://novinopay.com/merchant/invoices/verify',
            'merchantId' => '',
            'callbackUrl' => 'http://yoursite.com/path/to',
            'description' => 'payment using novinopay',
            'currency' => 'T', // واحد مبلغ Invoice: R, T (Rial, Toman) — همیشه ریال به درگاه ارسال می‌شود
        ],
        'behpardakht' => [
            'apiPurchaseUrl' => 'https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl',
            'apiPaymentUrl' => 'https://bpm.shaparak.ir/pgwchannel/startpay.mellat',
            'apiVerificationUrl' => 'https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl',
            'terminalId' => '',
            'username' => '',
            'password' => '',
            'callbackUrl' => 'http://yoursite.com/path/to',
            'description' => 'payment using behpardakht',
            'currency' => 'T', // Can be R, T (Rial, Toman)
            'cumulativeDynamicPayStatus' => false,
        ],
        'digipay' => [
            'apiOauthUrl' => 'https://api.mydigipay.com/digipay/api/oauth/token',
            'apiPurchaseUrl' => 'https://api.mydigipay.com/digipay/api/businesses/ticket?type=0',
            'apiPaymentUrl' => 'https://api.mydigipay.com/digipay/api/purchases/ipg/pay/',
            'apiVerificationUrl' => 'https://api.mydigipay.com/digipay/api/purchases/verify/',
            'username' => 'username',
            'password' => 'password',
            'client_id' => '',
            'client_secret' => '',
            'callbackUrl' => 'http://yoursite.com/path/to',
            'currency' => 'R', // Can be R, T (Rial, Toman)
        ],
        'etebarino' => [
            'apiPurchaseUrl' => 'https://api.etebarino.com/public/merchant/request-payment',
            'apiPaymentUrl' => 'https://panel.etebarino.com/gateway/public/ipg',
            'apiVerificationUrl' => 'https://api.etebarino.com/public/merchant/verify-payment',
            'merchantId' => '',
            'terminalId' => '',
            'username' => '',
            'password' => '',
            'callbackUrl' => 'http://yoursite.com/path/to',
            'description' => 'payment using etebarino',
        ],
        'idpay' => [
            'apiPurchaseUrl' => 'https://api.idpay.ir/v1.1/payment',
            'apiPaymentUrl' => 'https://idpay.ir/p/ws/',
            'apiSandboxPaymentUrl' => 'https://idpay.ir/p/ws-sandbox/',
            'apiVerificationUrl' => 'https://api.idpay.ir/v1.1/payment/verify',
            'merchantId' => '',
            'callbackUrl' => 'http://yoursite.com/path/to',
            'description' => 'payment using idpay',
            'sandbox' => false, // set it to true for test environments
            'currency' => 'R', // Can be R, T (Rial, Toman)
        ],
        'irankish' => [
            'apiPurchaseUrl' => 'https://ikc.shaparak.ir/api/v3/tokenization/make',
            'apiPaymentUrl' => 'https://ikc.shaparak.ir/iuiv3/IPG/Index/',
            'apiVerificationUrl' => 'https://ikc.shaparak.ir/api/v3/confirmation/purchase',
            'callbackUrl' => 'http://yoursite.com/path/to',
            'description' => 'payment using irankish',
            'terminalId' => '',
            'password' => '',
            'acceptorId' => '',
            'pubKey' => '',
            'currency' => 'T', // Can be R, T (Rial, Toman)
        ],
        'nextpay' => [
            'apiPurchaseUrl' => 'https://nextpay.org/nx/gateway/token',
            'apiPaymentUrl' => 'https://nextpay.org/nx/gateway/payment/',
            'apiVerificationUrl' => 'https://nextpay.org/nx/gateway/verify',
            'merchantId' => '',
            'callbackUrl' => 'http://yoursite.com/path/to',
            'description' => 'payment using nextpay',
            'currency' => 'T', // Can be R, T (Rial, Toman)
        ],
        'omidpay' => [
            'apiGenerateTokenUrl' => 'https://ref.sayancard.ir/ref-payment/RestServices/mts/generateTokenWithNoSign/',
            'apiPaymentUrl' => 'https://say.shaparak.ir/_ipgw_/MainTemplate/payment/',
            'apiVerificationUrl' => 'https://ref.sayancard.ir/ref-payment/RestServices/mts/verifyMerchantTrans/',
            'username' => '',
            'merchantId' => '',
            'password' => '',
            'callbackUrl' => '',
            'description' => 'payment using omidpay',
            'currency' => 'T', // Can be R, T (Rial, Toman)
        ],
        'parsian' => [
            'apiPurchaseUrl' => 'https://pec.shaparak.ir/NewIPGServices/Sale/SaleService.asmx?wsdl',
            'apiPaymentUrl' => 'https://pec.shaparak.ir/NewIPG/',
            'apiVerificationUrl' => 'https://pec.shaparak.ir/NewIPGServices/Confirm/ConfirmService.asmx?wsdl',
            'merchantId' => '',
            'callbackUrl' => 'http://yoursite.com/path/to',
            'description' => 'payment using parsian',
            'currency' => 'T', // Can be R, T (Rial, Toman)
        ],
        'pasargad' => [
            // درگاه پاسارگاد — API نسخهٔ جدید REST (دروایور نصب‌شدهٔ shetabit)
            'baseUrl' => 'https://pep.shaparak.ir',
            'userName' => '',
            'password' => '',
            'terminalCode' => '',
            'callbackUrl' => 'http://yoursite.com/path/to',
            'description' => 'payment using pasargad',
            'currency' => 'T', // Can be R, T (Rial, Toman)
        ],
        'payir' => [
            'apiPurchaseUrl' => 'https://pay.ir/pg/send',
            'apiPaymentUrl' => 'https://pay.ir/pg/',
            'apiVerificationUrl' => 'https://pay.ir/pg/verify',
            'merchantId' => 'test', // set it to `test` for test environments
            'callbackUrl' => 'http://yoursite.com/path/to',
            'description' => 'payment using payir',
            'currency' => 'T', // Can be R, T (Rial, Toman)
        ],
        'paypal' => [
            /* normal api */
            'apiPurchaseUrl' => 'https://www.paypal.com/cgi-bin/webscr',
            'apiPaymentUrl' => 'https://www.zarinpal.com/pg/StartPay/',
            'apiVerificationUrl' => 'https://ir.zarinpal.com/pg/services/WebGate/wsdl',

            /* sandbox api */
            'sandboxApiPurchaseUrl' => 'https://www.sandbox.paypal.com/cgi-bin/webscr',
            'sandboxApiPaymentUrl' => 'https://sandbox.zarinpal.com/pg/StartPay/',
            'sandboxApiVerificationUrl' => 'https://sandbox.zarinpal.com/pg/services/WebGate/wsdl',

            'mode' => 'normal', // can be normal, sandbox
            'currency' => '',
            'id' => '', // Specify the email of the PayPal Business account
            'callbackUrl' => 'http://yoursite.com/path/to',
            'description' => 'payment using paypal',
            'currency' => 'T', // Can be R, T (Rial, Toman)
        ],
        'payping' => [
            'apiPurchaseUrl' => 'https://api.payping.ir/v2/pay/',
            'apiPaymentUrl' => 'https://api.payping.ir/v2/pay/gotoipg/',
            'apiVerificationUrl' => 'https://api.payping.ir/v2/pay/verify/',
            'merchantId' => '',
            'callbackUrl' => 'http://yoursite.com/path/to',
            'description' => 'payment using payping',
            'currency' => 'T', // Can be R, T (Rial, Toman)
        ],
        'paystar' => [
            'apiPurchaseUrl' => 'https://core.paystar.ir/api/pardakht/create/',
            'apiPaymentUrl' => 'https://core.paystar.ir/api/pardakht/payment/',
            'apiVerificationUrl' => 'https://core.paystar.ir/api/pardakht/verify/',
            'gatewayId' => '', // your gateway id
            'signKey' => '', // sign key of your gateway
            'callbackUrl' => 'http://yoursite.com/path/to',
            'description' => 'payment using paystar',
            'currency' => 'R', // Can be R, T (Rial, Toman)
        ],
        'poolam' => [
            'apiPurchaseUrl' => 'https://poolam.ir/invoice/request/',
            'apiPaymentUrl' => 'https://poolam.ir/invoice/pay/',
            'apiVerificationUrl' => 'https://poolam.ir/invoice/check/',
            'merchantId' => '',
            'callbackUrl' => 'http://yoursite.com/path/to',
            'description' => 'payment using poolam',
            'currency' => 'T', // Can be R, T (Rial, Toman)
        ],
        'sadad' => [
            'apiPaymentByMultiIdentityUrl' => 'https://sadad.shaparak.ir/VPG/api/v0/PaymentByMultiIdentityRequest',
            'apiPaymentByIdentityUrl' => 'https://sadad.shaparak.ir/api/v0/PaymentByIdentity/PaymentRequest',
            'apiPaymentUrl' => 'https://sadad.shaparak.ir/api/v0/Request/PaymentRequest',
            'apiPurchaseUrl' => 'https://sadad.shaparak.ir/Purchase',
            'apiVerificationUrl' => 'https://sadad.shaparak.ir/VPG/api/v0/Advice/Verify',
            'key' => '',
            'merchantId' => '',
            'terminalId' => '',
            'callbackUrl' => '',
            'currency' => 'T', // Can be R, T (Rial, Toman)
            'mode' => 'normal', // can be normal, PaymentByIdentity, PaymentByMultiIdentity,
            'PaymentIdentity' => '',
            'MultiIdentityRows' => [
                [
                    'IbanNumber' => '', // Sheba number (with IR)
                    'Amount' => 0,
                    'PaymentIdentity' => '',
                ],
            ],
            'description' => 'payment using sadad',
        ],
        'saman' => [
            'apiPurchaseUrl' => 'https://sep.shaparak.ir/Payments/InitPayment.asmx?WSDL',
            'apiPaymentUrl' => 'https://sep.shaparak.ir/payment.aspx',
            'apiVerificationUrl' => 'https://sep.shaparak.ir/payments/referencepayment.asmx?WSDL',
            'merchantId' => '',
            'password' => '', // رمز پذیرنده سامان (در صورتی که در پنل سامان فعال کرده باشید)
            'callbackUrl' => '',
            'description' => 'payment using saman',
            'currency' => 'T', // Can be R, T (Rial, Toman)
        ],
        'sep' => [
            // مُد v1 — REST جدید سپ (ترمینال‌های UUID)
            'apiV1Init' => 'https://sep.shaparak.ir/api/v1/payment/init',
            'apiV1Verify' => 'https://sep.shaparak.ir/api/v1/payment/verify',
            'apiV1PaymentPage' => 'https://sep.shaparak.ir/Payment',
            // مُد onlinepg — آسان‌پرداخت (ترمینال عددی)
            'apiGetToken' => 'https://sep.shaparak.ir/onlinepg/onlinepg',
            'apiPaymentUrl' => 'https://sep.shaparak.ir/OnlinePG/OnlinePG',
            'apiSendToken' => 'https://sep.shaparak.ir/OnlinePG/SendToken',
            'apiVerificationUrl' => 'https://sep.shaparak.ir/verifyTxnRandomSessionkey/ipg/VerifyTransaction',
            'mode' => '', // خالی = خودکار (UUID→v1، عددی→onlinepg) | v1 | onlinepg
            'terminalId' => '',
            'callbackUrl' => '',
            'description' => 'Saman Electronic Payment (SEP) — v1/onlinepg',
            'currency' => 'T', // Can be R, T (Rial, Toman)
        ],
        'sepehr' => [
            'apiGetToken' => 'https://sepehr.shaparak.ir:8081/V1/PeymentApi/GetToken',
            'apiPaymentUrl' => 'https://sepehr.shaparak.ir:8080/pay',
            'apiVerificationUrl' => 'https://sepehr.shaparak.ir:8081/V1/PeymentApi/Advice',
            'terminalId' => '',
            'callbackUrl' => '',
            'description' => 'payment using sepehr(saderat)',
            'currency' => 'T', // Can be R, T (Rial, Toman)
        ],
        'walleta' => [
            'apiPurchaseUrl' => 'https://cpg.walleta.ir/payment/request.json',
            'apiPaymentUrl' => 'https://cpg.walleta.ir/ticket/',
            'apiVerificationUrl' => 'https://cpg.walleta.ir/payment/verify.json',
            'merchantId' => '',
            'callbackUrl' => 'http://yoursite.com/path/to',
            'description' => 'payment using walleta',
            'currency' => 'T', // Can be R, T (Rial, Toman)
        ],
        'yekpay' => [
            'apiPurchaseUrl' => 'https://gate.yekpay.com/api/payment/server?wsdl',
            'apiPaymentUrl' => 'https://gate.yekpay.com/api/payment/start/',
            'apiVerificationUrl' => 'https://gate.yekpay.com/api/payment/server?wsdl',
            'fromCurrencyCode' => 978,
            'toCurrencyCode' => 364,
            'merchantId' => '',
            'callbackUrl' => 'http://yoursite.com/path/to',
            'description' => 'payment using yekpay',
        ],
        'zarinpal' => [
            /* normal api */
            'apiPurchaseUrl' => 'https://api.zarinpal.com/pg/v4/payment/request.json',
            'apiPaymentUrl' => 'https://www.zarinpal.com/pg/StartPay/',
            'apiVerificationUrl' => 'https://api.zarinpal.com/pg/v4/payment/verify.json',

            /* sandbox api */
            'sandboxApiPurchaseUrl' => 'https://sandbox.zarinpal.com/pg/services/WebGate/wsdl',
            'sandboxApiPaymentUrl' => 'https://sandbox.zarinpal.com/pg/StartPay/',
            'sandboxApiVerificationUrl' => 'https://sandbox.zarinpal.com/pg/services/WebGate/wsdl',

            /* zarinGate api */
            'zaringateApiPurchaseUrl' => 'https://ir.zarinpal.com/pg/services/WebGate/wsdl',
            'zaringateApiPaymentUrl' => 'https://www.zarinpal.com/pg/StartPay/:authority/ZarinGate',
            'zaringateApiVerificationUrl' => 'https://ir.zarinpal.com/pg/services/WebGate/wsdl',

            'mode' => 'normal', // can be normal, sandbox, zaringate
            'merchantId' => '',
            'callbackUrl' => 'http://yoursite.com/path/to',
            'description' => 'payment using zarinpal',
            'currency' => 'T',
        ],
        'zibal' => [
            /* normal api */
            'apiPurchaseUrl' => 'https://gateway.zibal.ir/v1/request',
            'apiPaymentUrl' => 'https://gateway.zibal.ir/start/',
            'apiVerificationUrl' => 'https://gateway.zibal.ir/v1/verify',

            'mode' => 'normal', // can be normal, direct

            'merchantId' => '',
            'callbackUrl' => 'http://yoursite.com/path/to',
            'description' => 'payment using zibal',
            'currency' => 'T', // Can be R, T (Rial, Toman)
        ],
        'sepordeh' => [
            'apiPurchaseUrl' => 'https://sepordeh.com/merchant/invoices/add',
            'apiPaymentUrl' => 'https://sepordeh.com/merchant/invoices/pay/id:',
            'apiDirectPaymentUrl' => 'https://sepordeh.com/merchant/invoices/pay/automatic:true/id:',
            'apiVerificationUrl' => 'https://sepordeh.com/merchant/invoices/verify',
            'mode' => 'normal', // can be normal, direct
            'merchantId' => '',
            'callbackUrl' => 'http://yoursite.com/path/to',
            'description' => 'payment using sepordeh',
            'currency' => 'T', // Can be R, T (Rial, Toman)
        ],
        'rayanpay' => [
            'apiPurchaseUrl' => 'https://bpm.shaparak.ir/pgwchannel/startpay.mellat',
            'apiTokenUrl' => 'https://pms.rayanpay.com/api/v1/auth/token/generate',
            'apiPayStart' => 'https://pms.rayanpay.com/api/v1/ipg/payment/start',
            'apiPayVerify' => 'https://pms.rayanpay.com/api/v1/ipg/payment/response/parse',
            'username' => '',
            'client_id' => '',
            'password' => '',
            'callbackUrl' => '',
            'currency' => 'R', // Can be R, T (Rial, Toman)
        ],
        'sizpay' => [
            'apiPurchaseUrl' => 'https://rt.sizpay.ir/KimiaIPGRouteService.asmx?WSDL',
            'apiPaymentUrl' => 'https://rt.sizpay.ir/Route/Payment',
            'apiVerificationUrl' => 'https://rt.sizpay.ir/KimiaIPGRouteService.asmx?WSDL',
            'merchantId' => '',
            'terminal' => '',
            'username' => '',
            'password' => '',
            'SignData' => '',
            'callbackUrl' => '',
            'currency' => 'R', // Can be R, T (Rial, Toman)
        ],
        'vandar' => [
            'apiPurchaseUrl' => 'https://ipg.vandar.io/api/v3/send',
            'apiPaymentUrl' => 'https://ipg.vandar.io/v3/',
            'apiVerificationUrl' => 'https://ipg.vandar.io/api/v3/verify',
            'callbackUrl' => '',
            'merchantId' => '',
            'description' => 'payment using Vandar',
            'currency' => 'T', // Can be R, T (Rial, Toman)
        ],
        'aqayepardakht' => [
            'apiPurchaseUrl' => 'https://panel.aqayepardakht.ir/api/v2/create',
            'apiPaymentUrl' => 'https://panel.aqayepardakht.ir/startpay/',
            'apiPaymentUrlSandbox' => 'https://panel.aqayepardakht.ir/startpay/sandbox/',
            'apiVerificationUrl' => 'https://panel.aqayepardakht.ir/api/v2/verify',
            'mode' => 'normal', // normal | sandbox
            'callbackUrl' => '',
            'pin' => '',
            'invoice_id' => '',
            'mobile' => '',
            'email' => '',
            'description' => 'payment using Aqayepardakht',
            'currency' => 'T', // Can be R, T (Rial, Toman)
        ],
        'azki' => [
            'apiPaymentUrl' => 'https://api.azkivam.com',
            'callbackUrl' => 'http://yoursite.com/path/to',
            'fallbackUrl' => 'http://yoursite.com/path/to',
            'merchantId' => '',
            'key' => '',
            'currency' => 'T', // Can be R, T (Rial, Toman)
            'description' => 'payment using azki',
        ],
        'payfa' => [
            'apiPurchaseUrl' => 'https://payment.payfa.com/v2/api/Transaction/Request',
            'apiPaymentUrl' => 'https://payment.payfa.ir/v2/api/Transaction/Pay/',
            'apiVerificationUrl' => 'https://payment.payfa.com/v2/api/Transaction/Verify/',
            'callbackUrl' => '',
            'apiKey' => '',
            'currency' => 'T', // Can be R, T (Rial, Toman)
        ],
        'toman' => [
            'base_url' => 'https://escrow-api.toman.ir/api/v1',
            'shop_slug' => '',
            'auth_code' => '',
            'data' => '',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Class Maps
    |--------------------------------------------------------------------------
    |
    | This is the array of Classes that maps to Drivers above.
    | You can create your own driver if you like and add the
    | config in the drivers array and the class to use for
    | here with the same name. You will have to extend
    | Shetabit\Multipay\Abstracts\Driver in your driver.
    |
    */
    'map' => [
        'local' => Local::class,
        // نوین‌پی — درایور اختصاصی بر پایهٔ درایور سپرده (ساختار API یکسان)
        'novinopay' => Novinopay::class,
        'fanavacard' => Fanavacard::class,
        'asanpardakht' => Asanpardakht::class,
        'atipay' => Atipay::class,
        'toman' => Toman::class,
        'behpardakht' => Behpardakht::class,
        'digipay' => Digipay::class,
        'etebarino' => Etebarino::class,
        'idpay' => Idpay::class,
        'irankish' => Irankish::class,
        'nextpay' => Nextpay::class,
        'omidpay' => Omidpay::class,
        'parsian' => Parsian::class,
        'pasargad' => Pasargad::class,
        'payir' => Payir::class,
        'paypal' => Paypal::class,
        'payping' => Payping::class,
        'paystar' => Paystar::class,
        'poolam' => Poolam::class,
        'sadad' => Sadad::class,
        // سامان با WSDL لوکال‌کش‌شده (رفع خطای Couldn't load WSDL در هاست اشتراکی)
        'saman' => SamanCached::class,
        // سامان SEP نسخه REST (بدون SOAP) — جایگزین مطمئن برای هاست‌های بدون SOAP/WSDL
        'sep' => SepRest::class,
        'sepehr' => Sepehr::class,
        'walleta' => Walleta::class,
        'yekpay' => Yekpay::class,
        'zarinpal' => Zarinpal::class,
        'zibal' => Zibal::class,
        'sepordeh' => Sepordeh::class,
        'rayanpay' => Rayanpay::class,
        'sizpay' => Sizpay::class,
        'vandar' => Vandar::class,
        'aqayepardakht' => Aqayepardakht::class,
        'azki' => Azki::class,
        'payfa' => Payfa::class,
    ],
];
