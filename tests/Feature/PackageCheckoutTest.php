<?php

namespace Tests\Feature;

use App\Livewire\Shop\PackageCheckout;
use App\Models\DiscountCode;
use App\Models\Gateway;
use App\Models\PackageLicense;
use App\Models\PackagePurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

/**
 * صفحه تسویه خریدهای API (مرحله میانی پیش از درگاه):
 *  - پاسخ API خرید پولی → checkout_url (بدون تراکنش درگاه)
 *  - نمایش نام/تصویر پکیج + طرح + کد تخفیف + دکمه پرداخت/انصراف
 *  - اعمال کد تخفیف → ساخت تراکنش درگاه هنگام پرداخت (local)
 *  - انصراف → لغو خرید + بازگشت به callback_url فروشگاه
 *  - کد تخفیف ۱۰۰٪ → لایسنس فوری بدون درگاه
 */
class PackageCheckoutTest extends TestCase
{
    use RefreshDatabase, MakesShopData;

    /** هدرهای احراز API برای مشتری داده‌شده */
    private function apiHeaders(string $updateCode, string $websiteUrl): array
    {
        return [
            'Authorization' => 'Bearer ' . $updateCode,
            'X-Project-Url' => $websiteUrl,
            'Accept'        => 'application/json',
        ];
    }

    public function test_api_paid_purchase_returns_checkout_url_without_gateway_transaction(): void
    {
        $package  = $this->makePackage();
        $this->makePackageVersion($package);
        $plan     = $this->makePlan($package, ['price' => 350000]);
        $customer = $this->makeCustomer();

        $response = $this->postJson(
            '/api/v1/packages/' . $package->slug . '/purchase',
            [
                'pricing_plan_id' => $plan->id,
                'callback_url'    => 'https://example.com/pay/callback',
                'gateway'         => 'local',
            ],
            $this->apiHeaders($customer->update_code, $customer->website_url)
        );

        $response->assertOk()
            ->assertJsonStructure(['checkout_url', 'payment_url', 'amount', 'gateway', 'purchase_id'])
            ->assertJsonPath('transaction_id', null)
            ->assertJsonPath('amount', 350000);

        $json = $response->json();

        // payment_url و checkout_url هر دو به صفحه تسویه اشاره می‌کنند
        $this->assertSame($json['checkout_url'], $json['payment_url']);
        $this->assertStringContainsString('/checkout/package/', $json['checkout_url']);

        // رکورد خرید pending ساخته شده ولی تراکنش درگاه هنوز ایجاد نشده است
        $purchase = PackagePurchase::findOrFail($json['purchase_id']);
        $this->assertTrue($purchase->status === PackagePurchase::STATUS_PENDING);
        $this->assertNull($purchase->transaction_id);
        $this->assertNull($purchase->payment_url);
        $this->assertSame('local', $purchase->gateway);
        $this->assertSame(350000, (int) $purchase->amount);
        $this->assertSame(350000, (int) $purchase->original_amount);
    }

    public function test_checkout_page_displays_package_plan_and_discount_field(): void
    {
        $package  = $this->makePackage(['name' => 'پکیج فروشگاهی خاص']);
        $this->makePackageVersion($package);
        $plan     = $this->makePlan($package, ['price' => 350000, 'name' => 'طرح ویژه', 'duration_months' => 6]);
        $customer = $this->makeCustomer();

        $purchase = PackagePurchase::create([
            'package_id'      => $package->id,
            'version_id'      => $package->latestVersion()->first()->id,
            'pricing_plan_id' => $plan->id,
            'customer_id'     => $customer->id,
            'callback_url'    => 'https://example.com/pay/callback',
            'gateway'         => 'local',
            'original_amount' => 350000,
            'amount'          => 350000,
            'status'          => PackagePurchase::STATUS_PENDING,
        ]);

        $url = URL::temporarySignedRoute('shop.checkout', now()->addHour(), ['purchase' => $purchase->id]);

        $this->get($url)
            ->assertOk()
            ->assertSee('پکیج فروشگاهی خاص')
            ->assertSee('طرح ویژه')
            ->assertSee('کد تخفیف')
            ->assertSee('اگر کد تخفیف دارید')
            ->assertSee('انصراف از خرید')
            ->assertSee('مبلغ قابل پرداخت')
            ->assertSee('350,000');
    }

    public function test_invalid_signed_link_is_rejected(): void
    {
        $package  = $this->makePackage();
        $this->makePackageVersion($package);
        $plan     = $this->makePlan($package);
        $customer = $this->makeCustomer();

        $purchase = PackagePurchase::create([
            'package_id'      => $package->id,
            'version_id'      => $package->latestVersion()->first()->id,
            'pricing_plan_id' => $plan->id,
            'customer_id'     => $customer->id,
            'amount'          => 100000,
            'status'          => PackagePurchase::STATUS_PENDING,
        ]);

        // بدون امضا / با امضای دستکاری‌شده → 403
        $this->get('/checkout/package/' . $purchase->id)->assertStatus(403);

        $url = URL::temporarySignedRoute('shop.checkout', now()->addHour(), ['purchase' => $purchase->id]);
        $this->get($url . 'x')->assertStatus(403);
    }

    public function test_apply_discount_updates_preview_and_payable_amount(): void
    {
        $package  = $this->makePackage();
        $this->makePackageVersion($package);
        $plan     = $this->makePlan($package, ['price' => 100000]);
        $customer = $this->makeCustomer();
        $coupon   = $this->makeCoupon(['code' => 'CHK30', 'value' => 30, 'applies_products' => false]);

        $purchase = PackagePurchase::create([
            'package_id'      => $package->id,
            'version_id'      => $package->latestVersion()->first()->id,
            'pricing_plan_id' => $plan->id,
            'customer_id'     => $customer->id,
            'amount'          => 100000,
            'original_amount' => 100000,
            'status'          => PackagePurchase::STATUS_PENDING,
        ]);

        Livewire::test(PackageCheckout::class, ['purchase' => $purchase])
            ->assertSet('appliedCode', null)
            ->set('codeInput', 'chk30') // حروف کوچک → نرمال می‌شود
            ->call('applyDiscount')
            ->assertSet('appliedCode', 'CHK30')
            ->assertSet('codeDiscount', 30000)
            ->assertSet('codeMessageType', 'ok')
            // مبلغ قابل پرداخت ۷۰٫۰۰۰
            ->assertSee('70,000');

        // استفاده از کد هنوز ثبت نشده (فقط پیش‌نمایش است)
        $this->assertSame(0, $coupon->fresh()->used_count);
        $this->assertNull($purchase->fresh()->discount_code_id);
    }

    public function test_invalid_discount_code_is_rejected(): void
    {
        $package  = $this->makePackage();
        $this->makePackageVersion($package);
        $plan     = $this->makePlan($package);
        $customer = $this->makeCustomer();

        $purchase = PackagePurchase::create([
            'package_id'      => $package->id,
            'version_id'      => $package->latestVersion()->first()->id,
            'pricing_plan_id' => $plan->id,
            'customer_id'     => $customer->id,
            'amount'          => 100000,
            'status'          => PackagePurchase::STATUS_PENDING,
        ]);

        Livewire::test(PackageCheckout::class, ['purchase' => $purchase])
            ->set('codeInput', 'NOPE-404')
            ->call('applyDiscount')
            ->assertSet('appliedCode', null)
            ->assertSet('codeDiscount', 0)
            ->assertSet('codeMessageType', 'error');
    }

    public function test_pay_creates_gateway_transaction_and_redirects(): void
    {
        $package  = $this->makePackage();
        $this->makePackageVersion($package);
        $plan     = $this->makePlan($package, ['price' => 100000]);
        $customer = $this->makeCustomer();
        $coupon   = $this->makeCoupon(['code' => 'CHKPAY', 'value' => 10, 'applies_products' => false]);

        $purchase = PackagePurchase::create([
            'package_id'      => $package->id,
            'version_id'      => $package->latestVersion()->first()->id,
            'pricing_plan_id' => $plan->id,
            'customer_id'     => $customer->id,
            'callback_url'    => 'https://example.com/pay/callback',
            'gateway'         => 'local',
            'amount'          => 100000,
            'original_amount' => 100000,
            'status'          => PackagePurchase::STATUS_PENDING,
        ]);

        Livewire::test(PackageCheckout::class, ['purchase' => $purchase])
            ->set('codeInput', 'CHKPAY')
            ->call('applyDiscount')
            ->call('pay')
            ->assertRedirect();

        $fresh = $purchase->fresh();

        // تراکنش درگاه هنگام پرداخت ساخته شد؛ مبلغ پس از کد تخفیف مبنای شارژ است
        $this->assertNotNull($fresh->transaction_id);
        $this->assertNotNull($fresh->payment_url);
        $this->assertStringContainsString('/payment/form/', $fresh->payment_url);
        $this->assertSame(90000, (int) $fresh->amount);
        $this->assertSame(100000, (int) $fresh->original_amount);
        $this->assertSame(10000, (int) $fresh->discount_amount);
        $this->assertSame($coupon->id, $fresh->discount_code_id);
        $this->assertTrue($fresh->status === PackagePurchase::STATUS_PENDING); // هنوز در درگاه پرداخت نشده

        // شمارنده کد تخفیف فقط پس از تأیید پرداخت مصرف می‌شود
        $this->assertSame(0, $coupon->fresh()->used_count);
    }

    public function test_full_discount_issues_license_without_gateway(): void
    {
        $package  = $this->makePackage();
        $this->makePackageVersion($package);
        $plan     = $this->makePlan($package, ['price' => 100000]);
        $customer = $this->makeCustomer();
        $coupon   = $this->makeCoupon([
            'code'             => 'CHKFULL',
            'type'             => DiscountCode::TYPE_PERCENT,
            'value'            => 100,
            'applies_products' => false,
        ]);

        $purchase = PackagePurchase::create([
            'package_id'      => $package->id,
            'version_id'      => $package->latestVersion()->first()->id,
            'pricing_plan_id' => $plan->id,
            'customer_id'     => $customer->id,
            'gateway'         => 'local',
            'amount'          => 100000,
            'original_amount' => 100000,
            'status'          => PackagePurchase::STATUS_PENDING,
        ]);

        Livewire::test(PackageCheckout::class, ['purchase' => $purchase])
            ->set('codeInput', 'CHKFULL')
            ->call('applyDiscount')
            ->call('pay')
            ->assertRedirect();

        $fresh = $purchase->fresh();

        // بدون درگاه: پرداخت‌شده + لایسنس صادر شده
        $this->assertTrue($fresh->isPaid());
        $this->assertSame(0, (int) $fresh->amount);
        $this->assertSame(100000, (int) $fresh->discount_amount);
        $this->assertNull($fresh->transaction_id);

        $license = PackageLicense::query()->where('purchase_id', $purchase->id)->first();
        $this->assertNotNull($license);

        $this->assertSame(1, $coupon->fresh()->used_count);
    }

    public function test_cancel_marks_purchase_failed_and_returns_to_store(): void
    {
        $package  = $this->makePackage();
        $this->makePackageVersion($package);
        $plan     = $this->makePlan($package);
        $customer = $this->makeCustomer();

        $purchase = PackagePurchase::create([
            'package_id'      => $package->id,
            'version_id'      => $package->latestVersion()->first()->id,
            'pricing_plan_id' => $plan->id,
            'customer_id'     => $customer->id,
            'callback_url'    => 'https://example.com/pay/callback?order=12',
            'gateway'         => 'local',
            'amount'          => 100000,
            'status'          => PackagePurchase::STATUS_PENDING,
        ]);

        Livewire::test(PackageCheckout::class, ['purchase' => $purchase])
            ->call('cancel')
            ->assertRedirect();

        $fresh = $purchase->fresh();

        $this->assertTrue($fresh->status === PackagePurchase::STATUS_FAILED);
        $this->assertSame('انصراف کاربر از پرداخت', $fresh->meta['fail_reason'] ?? null);
        $this->assertNull(PackageLicense::query()->where('purchase_id', $purchase->id)->first());
    }

    public function test_paid_purchase_shows_paid_state(): void
    {
        $package  = $this->makePackage();
        $this->makePackageVersion($package);
        $plan     = $this->makePlan($package);
        $customer = $this->makeCustomer();

        $purchase = PackagePurchase::create([
            'package_id'      => $package->id,
            'version_id'      => $package->latestVersion()->first()->id,
            'pricing_plan_id' => $plan->id,
            'customer_id'     => $customer->id,
            'amount'          => 0,
            'status'          => PackagePurchase::STATUS_PAID,
            'paid_at'         => now(),
        ]);

        $url = URL::temporarySignedRoute('shop.checkout', now()->addHour(), ['purchase' => $purchase->id]);

        $this->get($url)
            ->assertOk()
            ->assertSee('این سفارش پرداخت شده است');
    }

    public function test_checkout_page_lists_active_gateways_with_default_selection(): void
    {
        // درگاه دومِ فعال + یک درگاه غیرفعال (نباید در لیست بیاید)
        Gateway::create(['name' => 'زیبال', 'key' => 'zibal', 'is_active' => true, 'ordering' => 10]);
        Gateway::create(['name' => 'غیرفعال', 'key' => 'off', 'is_active' => false, 'ordering' => 1]);

        $package  = $this->makePackage();
        $this->makePackageVersion($package);
        $plan     = $this->makePlan($package, ['price' => 100000]);
        $customer = $this->makeCustomer();

        $purchase = PackagePurchase::create([
            'package_id'      => $package->id,
            'version_id'      => $package->latestVersion()->first()->id,
            'pricing_plan_id' => $plan->id,
            'customer_id'     => $customer->id,
            'callback_url'    => 'https://example.com/pay/callback',
            'gateway'         => 'local',
            'amount'          => 100000,
            'original_amount' => 100000,
            'status'          => PackagePurchase::STATUS_PENDING,
        ]);

        $url = URL::temporarySignedRoute('shop.checkout', now()->addHour(), ['purchase' => $purchase->id]);

        $this->get($url)
            ->assertOk()
            ->assertSee('درگاه پرداخت را انتخاب کنید')
            ->assertSee('زیبال')
            ->assertSee('تست')
            ->assertDontSee('غیرفعال');

        Livewire::test(PackageCheckout::class, ['purchase' => $purchase])
            // پیش‌فرض = درگاه ثبت‌شدهٔ خرید
            ->assertSet('gatewayKey', 'local')
            // کاربر می‌تواند درگاه را عوض کند
            ->set('gatewayKey', 'zibal')
            ->assertSet('gatewayKey', 'zibal')
            // نام درگاه انتخابی در خروجی رندر می‌شود
            ->assertSee('زیبال');
    }

    public function test_pay_rejects_invalid_gateway_selection(): void
    {
        $package  = $this->makePackage();
        $this->makePackageVersion($package);
        $plan     = $this->makePlan($package, ['price' => 100000]);
        $customer = $this->makeCustomer();

        $purchase = PackagePurchase::create([
            'package_id'      => $package->id,
            'version_id'      => $package->latestVersion()->first()->id,
            'pricing_plan_id' => $plan->id,
            'customer_id'     => $customer->id,
            'gateway'         => 'local',
            'amount'          => 100000,
            'original_amount' => 100000,
            'status'          => PackagePurchase::STATUS_PENDING,
        ]);

        Livewire::test(PackageCheckout::class, ['purchase' => $purchase])
            ->set('gatewayKey', 'not-exists')
            ->call('pay')
            ->assertNoRedirect()
            ->assertSee('درگاه');

        $fresh = $purchase->fresh();

        // تراکنشی ساخته نشده و خرید هنوز pending است
        $this->assertNull($fresh->transaction_id);
        $this->assertNull($fresh->payment_url);
        $this->assertTrue($fresh->status === PackagePurchase::STATUS_PENDING);
    }

    public function test_pay_creates_transaction_with_selected_gateway(): void
    {
        Gateway::create(['name' => 'زیبال', 'key' => 'zibal', 'is_active' => true, 'ordering' => 10]);

        $package  = $this->makePackage();
        $this->makePackageVersion($package);
        $plan     = $this->makePlan($package, ['price' => 100000]);
        $customer = $this->makeCustomer();

        // خرید بدون درگاهِ ثبت‌شده (رکوردهای قدیمی) → پیش‌فرض اولین درگاه فعال
        $purchase = PackagePurchase::create([
            'package_id'      => $package->id,
            'version_id'      => $package->latestVersion()->first()->id,
            'pricing_plan_id' => $plan->id,
            'customer_id'     => $customer->id,
            'callback_url'    => 'https://example.com/pay/callback',
            'amount'          => 100000,
            'original_amount' => 100000,
            'status'          => PackagePurchase::STATUS_PENDING,
        ]);

        // بدون انتخاب کاربر → اولین درگاه فعال (زیبال با ordering=10 جلوتر از local=99)
        Livewire::test(PackageCheckout::class, ['purchase' => $purchase])
            ->assertSet('gatewayKey', 'zibal')
            // انتخاب صریح درگاه تست
            ->set('gatewayKey', 'local')
            ->call('pay')
            ->assertRedirect();

        $fresh = $purchase->fresh();

        $this->assertNotNull($fresh->transaction_id);
        $this->assertSame('local', $fresh->gateway);
        $this->assertStringContainsString('/payment/form/', (string) $fresh->payment_url);
    }
}
