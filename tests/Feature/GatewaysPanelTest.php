<?php

namespace Tests\Feature;

use App\Livewire\Gateways;
use App\Models\Gateway;
use App\Models\User;
use App\Services\Payments\Novinopay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Shetabit\Multipay\Drivers\Sepordeh\Sepordeh;
use Tests\TestCase;

/**
 * پنل درگاه‌های پرداخت (/admin/settings/gateways):
 * - همهٔ درگاه‌های پشتیبانی‌شده (۱۷ کلید) در صفحه ساخته و نمایش داده می‌شوند
 * - درگاه‌های جدید (novinopay / parsian / pasargad / asanpardakht) با فیلدهای پیکربندی درست
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
            ['merchantId', 'mode'],
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

        $gateway->configs()->createMany([
            ['key' => 'merchantId', 'value' => 'NOVIN-123'],
            ['key' => 'mode', 'value' => 'direct'],
        ]);

        $this->assertSame('NOVIN-123', $gateway->config('merchantId'));
        $this->assertSame('direct', $gateway->config('mode'));
    }

    /* -------------------------------------------------------------- */
    /*  تزریق پیکربندی به درایورها */
    /* -------------------------------------------------------------- */

    public function test_get_gateway_configs_maps_new_gateway_keys(): void
    {
        $rows = [
            'novinopay' => ['merchantId' => 'N-1', 'mode' => 'direct'],
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
            ['merchantId' => 'N-1', 'mode' => 'direct'],
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

    public function test_novinopay_driver_is_mapped_and_extends_sepordeh(): void
    {
        $map = config('payment.map');

        $this->assertSame(Novinopay::class, $map['novinopay']);
        $this->assertTrue(class_exists($map['novinopay']));
        $this->assertTrue(is_subclass_of($map['novinopay'], Sepordeh::class));

        // درایورهای درخواست‌شدهٔ کاربر همگی در map موجود باشند
        foreach (['parsian', 'pasargad', 'asanpardakht', 'sadad', 'behpardakht', 'irankish', 'payping', 'sepehr'] as $key) {
            $this->assertArrayHasKey($key, $map, "درایور {$key} در map پرداخت نیست");
            $this->assertTrue(class_exists($map[$key]));
        }
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
        $this->assertSame('https://novinopay.com/merchant/invoices/pay/id:', $driver['apiPaymentUrl']);
        $this->assertSame('https://novinopay.com/merchant/invoices/verify', $driver['apiVerificationUrl']);
        $this->assertSame('T', $driver['currency']);
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
            ->set("gateways.{$index}.configs.mode", 'direct')
            ->call('save')
            ->assertHasNoErrors();

        $model = Gateway::where('key', 'novinopay')->first();
        $this->assertTrue((bool) $model->is_active);
        $this->assertSame('MERCHANT-NOVIN-88', $model->config('merchantId'));
        $this->assertSame('direct', $model->config('mode'));

        // پیکربندی تزریق‌شده به درایور مطابق تنظیمات پنل است
        $this->assertSame(
            ['merchantId' => 'MERCHANT-NOVIN-88', 'mode' => 'direct'],
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
