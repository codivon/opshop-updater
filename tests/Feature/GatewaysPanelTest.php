<?php

namespace Tests\Feature;

use App\Livewire\Gateways;
use App\Models\Gateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Shetabit\Multipay\Abstracts\Driver;
use Shetabit\Multipay\Constants\IranCurrency;
use Shetabit\Multipay\Drivers\Novinopay\Novinopay;
use Shetabit\Multipay\Drivers\Saman\Saman;
use Shetabit\Multipay\Exceptions\PreviouslyVerifiedException;
use Tests\TestCase;

/**
 * پنل درگاه‌های پرداخت (/admin/settings/gateways):
 * - همهٔ درگاه‌های پشتیبانی‌شده (۱۷ کلید) در صفحه ساخته و نمایش داده می‌شوند
 * - درگاه‌های جدید (novinopay / parsian / pasargad / asanpardakht) با فیلدهای پیکربندی درست
 * - نوین‌پی از درایور رسمی upstream shetabit/multipay v3.6.0 استفاده می‌کند (پکیج ارتقا یافته به v7)
 * - درایورهای idpay/payir/walleta از override لوکال (در upstream موجود نیستند/حذف شده‌اند)
 * - ذخیرهٔ فرم پنل مقادیر جدید را در gateway_configs می‌نویسد
 * - get_gateway_configs کلیدهای هر درگاه را به درایور پرداخت تزریق می‌کند
 * - فهرست عمودی (لیست زیر هم) — کارت‌های ردیفی به‌جای گرید چندستونه
 */
class GatewaysPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'مدیر آزمون',
            'email' => 'admin@gateways.test',
            'password' => 'secret123',
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    /* -------------------------------------------------------------- */
    /*  درگاه‌های جدید در پیکربندی و پنل */
    /* -------------------------------------------------------------- */

    public function test_new_gateways_are_supported_and_created_in_panel(): void
    {
        $supported = config('general.supported_gateways');

        foreach (['novinopay', 'parsian', 'pasargad', 'asanpardakht'] as $key) {
            $this->assertArrayHasKey($key, $supported);
        }

        // کلیدهای قدیمی حذف نشده باشند
        foreach (['zarinpal', 'payir', 'zibal', 'local', 'sadad', 'behpardakht', 'irankish', 'sepehr', 'payping'] as $key) {
            $this->assertArrayHasKey($key, $supported);
        }

        $this->actingAs($this->admin)->get(route('admin.settings.gateways'))->assertOk();

        Livewire::test(Gateways::class)
            ->assertSee('درگاه نوین پی')
            ->assertSee('درگاه بانک پارسیان')
            ->assertSee('درگاه بانک پاسارگاد')
            ->assertSee('درگاه آسان‌پرداخت');

        foreach ($supported as $key => $name) {
            $this->assertDatabaseHas('gateways', ['key' => $key]);
        }
    }

    public function test_new_gateways_have_config_fields_in_schema(): void
    {
        $schema = Gateways::schema();

        $this->assertSame(
            ['merchantId'],
            array_column($schema['novinopay']['fields'], 'name')
        );

        $this->assertSame(
            ['merchantId'],
            array_column($schema['parsian']['fields'], 'name')
        );

        $this->assertSame(
            ['terminalCode', 'userName', 'password'],
            array_column($schema['pasargad']['fields'], 'name')
        );

        $this->assertSame(
            ['username', 'password', 'merchantConfigID'],
            array_column($schema['asanpardakht']['fields'], 'name')
        );
    }

    public function test_gateway_configs_table_accepts_new_gateway_fields(): void
    {
        // ساختار پنل باید بتواند بدون خطا برای همهٔ درگاه‌ها مقدار ذخیره کند
        $gateway = Gateway::firstOrCreate(['key' => 'novinopay'], ['name' => 'نوین پی']);

        $gateway->configs()->create([
            'key' => 'merchantId', 'value' => 'NOVIN-123',
        ]);

        $this->assertSame('NOVIN-123', $gateway->config('merchantId'));
    }

    /* -------------------------------------------------------------- */
    /*  تزریق پیکربندی به درایورها */
    /* -------------------------------------------------------------- */

    public function test_get_gateway_configs_maps_new_gateway_keys(): void
    {
        $rows = [
            'novinopay' => ['merchantId' => 'N-1'],
            'parsian' => ['merchantId' => 'P-1'],
            'pasargad' => ['terminalCode' => '123456', 'userName' => 'user1', 'password' => 'pass1'],
            'asanpardakht' => ['username' => 'u2', 'password' => 'p2', 'merchantConfigID' => 'MC-9'],
        ];

        foreach ($rows as $key => $configs) {
            $gateway = Gateway::create(['key' => $key, 'name' => $key, 'is_active' => true]);

            foreach ($configs as $name => $value) {
                $gateway->configs()->create(['key' => $name, 'value' => $value]);
            }
        }

        $this->assertSame(
            ['merchantId' => 'N-1'],
            get_gateway_configs('novinopay')
        );
        $this->assertSame(
            ['merchantId' => 'P-1'],
            get_gateway_configs('parsian')
        );
        $this->assertSame(
            ['terminalCode' => '123456', 'userName' => 'user1', 'password' => 'pass1'],
            get_gateway_configs('pasargad')
        );
        $this->assertSame(
            ['username' => 'u2', 'password' => 'p2', 'merchantConfigID' => 'MC-9'],
            get_gateway_configs('asanpardakht')
        );

        // درگاه بدون پیکربندی = آرایهٔ خالی
        $this->assertSame([], get_gateway_configs('idpay'));
    }

    public function test_novinopay_driver_is_official_upstream_port(): void
    {
        $map = config('payment.map');

        $this->assertSame(Novinopay::class, $map['novinopay']);
        $this->assertTrue(class_exists($map['novinopay']));
        // درایور رسمی پورت‌شده از shetabit/multipay v3.6.0 — زیرکلاس Driver abstract نه هک سپرده
        $this->assertTrue(is_subclass_of($map['novinopay'], Driver::class));
        $this->assertNotSame(
            Shetabit\Multipay\Drivers\Sepordeh\Sepordeh::class,
            get_parent_class($map['novinopay'])
        );
        // استثنای تراکنشِ قبلاً تأییدشده از upstream موجود باشد
        $this->assertTrue(class_exists(PreviouslyVerifiedException::class));

        // درایورهای درخواست‌شدهٔ کاربر همگی در map موجود باشند
        foreach (['parsian', 'pasargad', 'asanpardakht', 'sadad', 'behpardakht', 'irankish', 'payping', 'sepehr'] as $key) {
            $this->assertArrayHasKey($key, $map, "درایور {$key} در map پرداخت نیست");
            $this->assertTrue(class_exists($map[$key]));
        }
    }

    public function test_upgraded_multipay_v3_drivers_resolve(): void
    {
        $map = config('payment.map');

        // درایورهای upstream نسخهٔ ۳٫۶ (قبلاً در نسخهٔ قدیم نبودند یا از vendor قدیمی می‌آمدند)
        foreach (['toman', 'saman', 'sep'] as $key) {
            $this->assertArrayHasKey($key, $map);
            $this->assertTrue(class_exists($map[$key]), "درایور {$key} پس از ارتقا به multipay v3.6.0 پیدا نشد");
        }

        // درایورهای override لوکال (در upstream v3.6.0 نیستند/حذف شده‌اند) — باید از packages/shetabit بیایند
        foreach (['idpay', 'payir', 'walleta'] as $key) {
            $this->assertTrue(class_exists($map[$key]), "درایور override {$key} پیدا نشد");
            $reflection = new \ReflectionClass($map[$key]);
            $this->assertStringContainsString('packages'.DIRECTORY_SEPARATOR.'shetabit', $reflection->getFileName(), "{$key} باید از override لوکال باشد");
        }

        // درایورهای سفارشی App با کلاس‌های typed نسخهٔ ۳ سازگارند
        $this->assertTrue(is_subclass_of($map['saman'], Saman::class));
        $this->assertTrue(is_subclass_of($map['sep'], Driver::class));
    }

    public function test_pasargad_payment_config_has_rest_base_url(): void
    {
        $driver = config('payment.drivers.pasargad');

        $this->assertSame('https://pep.shaparak.ir', $driver['baseUrl']);
        // درایورِ نصب‌شدهuserName/password/terminalCode می‌خواند — کلیدهای کهنه نباشند
        $this->assertArrayHasKey('userName', $driver);
        $this->assertArrayHasKey('password', $driver);
        $this->assertArrayHasKey('terminalCode', $driver);
        $this->assertArrayNotHasKey('certificate', $driver);
    }

    public function test_novinopay_payment_config_has_invoice_api_urls(): void
    {
        $driver = config('payment.drivers.novinopay');

        $this->assertSame('https://novinopay.com/merchant/invoices/add', $driver['apiPurchaseUrl']);
        $this->assertSame('https://novinopay.com/merchant/invoices/verify', $driver['apiVerificationUrl']);
        // v3.6.0: currency در زمان اجرا به enum IranCurrency تبدیل می‌شود (Toman → ضریب ۱۰)
        $this->assertInstanceOf(IranCurrency::class, $driver['currency']);
        $this->assertSame(10, $driver['currency']->ratio());
        // در فایل خام کانفیگ همچنان رشتهٔ R/T است (config:cache با var_export نمی‌تواند enum را serialize کند)
        $raw = require config_path('payment.php');
        $this->assertSame('T', $raw['drivers']['novinopay']['currency']);
        // کلیدهای الگوی سپردهٔ کهنه (دو مرحله‌ای) و mode دیگر نباشند — درایور رسمی payment_url را از پاسخ می‌گیرد
        $this->assertArrayNotHasKey('apiPaymentUrl', $driver);
        $this->assertArrayNotHasKey('apiDirectPaymentUrl', $driver);
        $this->assertArrayNotHasKey('mode', $driver);
    }

    /* -------------------------------------------------------------- */
    /*  ذخیرهٔ فرم پنل */
    /* -------------------------------------------------------------- */

    public function test_save_persists_new_gateway_configs_and_activation(): void
    {
        $this->actingAs($this->admin);

        $component = Livewire::test(Gateways::class);

        $gateways = $component->instance()->gateways;
        $this->assertNotEmpty($gateways);

        // فیلدهای نوین‌پی را پر و فعال می‌کنیم
        $payload = collect($gateways)
            ->map(fn (array $gw) => $gw['key'])
            ->flip();

        $this->assertTrue($payload->has('novinopay'));

        $index = $payload->get('novinopay');

        $component
            ->set("gateways.{$index}.name", 'درگاه نوین پی')
            ->set("gateways.{$index}.is_active", true)
            ->set("gateways.{$index}.configs.merchantId", 'MERCHANT-NOVIN-88')
            ->call('save')
            ->assertHasNoErrors();

        $model = Gateway::where('key', 'novinopay')->first();
        $this->assertTrue((bool) $model->is_active);
        $this->assertSame('MERCHANT-NOVIN-88', $model->config('merchantId'));

        // پیکربندی تزریق‌شده به درایور مطابق تنظیمات پنل است
        $this->assertSame(
            ['merchantId' => 'MERCHANT-NOVIN-88'],
            get_gateway_configs('novinopay')
        );
    }

    /* -------------------------------------------------------------- */
    /*  چیدمان فهرست عمودی */
    /* -------------------------------------------------------------- */

    public function test_gateways_page_renders_vertical_list_layout(): void
    {
        $this->actingAs($this->admin);

        $component = Livewire::test(Gateways::class);
        $component->assertSeeHtml('class="space-y-3"');
        // گرید قدیمی چندستونه نباید وجود داشته باشد
        $component->assertDontSeeHtml('xl:grid-cols-3');

        // هر درگاه یک ردیف تمام‌عرض با دکمهٔ بازشو است
        $count = count($component->instance()->gateways);
        $this->assertSame(count(config('general.supported_gateways')), $count);
    }

    public function test_gateway_logos_exist_for_new_gateways(): void
    {
        foreach (['novinopay', 'parsian', 'pasargad', 'asanpardakht'] as $key) {
            $path = public_path('uploads/gateways/logos/'.$key.'.svg');
            $this->assertFileExists($path, "لوگوی پیش‌فرض {$key} وجود ندارد");
            $this->assertStringContainsString('<svg', file_get_contents($path));
        }
    }

    public function test_logos_directory_schema_guard_still_works(): void
    {
        // ستون logo بعد از مایگریشن لوگو باید موجود باشد (گارد نصب‌های قدیمی)
        $this->assertTrue(Schema::hasColumn('gateways', 'logo'));
    }
}
