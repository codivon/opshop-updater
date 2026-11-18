<?php

namespace Tests\Feature;

use App\Models\Gateway;
use App\Models\PackagePurchase;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Shetabit\Multipay\Receipt;
use Shetabit\Payment\Facade\Payment;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

/**
 * Task 94 — پیام‌های کال‌بکِ بازگشت درگاه باید «هم‌راستا با واقعیت سفارش» باشند.
 *
 * گزارش کاربر: پرداختِ موفق در درگاه، هیروی سبز «اشتراک شما فعال شد» — ولی یک توستِ
 * متناقض از جنس «از سمت درگاه پاسخ موفق دریافت نشد…» که سریع محو می‌شد.
 *
 * ریشه:
 *  ۱) کلید پارامتر درگاه حساس به حروف بود (پارسیان «Token» با حروف بزرگ می‌فرستد
 *     و کد فقط «token» کوچک را می‌گرفت) → شناسه یافت نمی‌شد → مسیر redirectByReference
 *     با پیام «پرداخت این سفارش به پایان نرسید یا از سمت درگاه تأیید نشد.» فعال می‌شد.
 *  ۲) خودِ صفحهٔ نتیجه در mount سفارشِ pending را verify می‌کرد → سفارش پرداخت و
 *     فعال می‌شد (هیروی سبز) — درحالی‌که توستِ خطای قبلی نمایش داده می‌شد.
 *
 * فیکس: استخراج بی‌حساس به حروف + پیام‌رسانی redirectByReference بر اساس وضعیت واقعی
 * سفارش (پرداخت‌شده → پیام موفق؛ pending با تراکنش → تلاش تأیید؛ واقعاً ناتمام → پیام قبلی).
 */
class PaymentReferenceReturnTest extends TestCase
{
    use MakesShopData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gateway::firstOrCreate(['key' => 'local'], ['name' => 'درگاه آزمایشی', 'is_active' => true]);
    }

    private function makeOrder(array $attrs = []): SubscriptionOrder
    {
        $plan = SubscriptionPlan::create([
            'name' => 'طرح بازگشت درگاه',
            'slug' => 'ref-return-plan-'.uniqid(),
            'duration_months' => 1,
            'price' => 100000,
            'discount_price' => 0,
            'is_one_time' => false,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        return SubscriptionOrder::create(array_merge([
            'subscription_plan_id' => $plan->id,
            'customer_id' => $this->makeCustomer()->id,
            'amount' => 100000,
            'discount' => 0,
            'final_amount' => 100000,
            'gateway' => 'local',
            'status' => SubscriptionOrder::STATUS_PENDING,
            'admin_status' => SubscriptionOrder::ADMIN_STATUS_PENDING,
        ], $attrs));
    }

    /* -------------------------------------------------------------- */
    /*  استخراج شناسهٔ تراکنش — بی‌حساس به بزرگی/کوچکی حروف کلید */
    /* -------------------------------------------------------------- */

    public function test_callback_with_capitalized_token_param_finds_paid_order(): void
    {
        $order = $this->makeOrder([
            'transaction_id' => 'TRX-CAP-TOKEN',
            'status' => SubscriptionOrder::STATUS_PAID,
            'admin_status' => SubscriptionOrder::ADMIN_STATUS_APPROVED,
            'paid_at' => now(),
        ]);

        $response = $this->get(route('payment.callback', ['Token' => 'TRX-CAP-TOKEN']));

        $response->assertRedirect(route('subscription.result', ['order' => $order, 'token' => $order->access_token]));

        // پیام موفق — نه «به پایان نرسید»
        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('این تراکنش قبلاً تأیید شده است.')
            ->assertDontSee('به پایان نرسید');
    }

    public function test_callback_with_uppercase_transactionid_param_finds_order(): void
    {
        $order = $this->makeOrder([
            'transaction_id' => 'TRX-UPPER-CASE',
            'status' => SubscriptionOrder::STATUS_PAID,
            'admin_status' => SubscriptionOrder::ADMIN_STATUS_APPROVED,
            'paid_at' => now(),
        ]);

        $this->get(route('payment.callback', ['TransactionID' => 'TRX-UPPER-CASE']))
            ->assertRedirect(route('subscription.result', ['order' => $order, 'token' => $order->access_token]));
    }

    /* -------------------------------------------------------------- */
    /*  بازگشتِ بدون شناسه (redirectByReference) — پیام بر اساس واقعیت سفارش */
    /* -------------------------------------------------------------- */

    public function test_reference_return_for_paid_subscription_shows_success_message(): void
    {
        $order = $this->makeOrder([
            'transaction_id' => 'TRX-PAID-REF',
            'status' => SubscriptionOrder::STATUS_PAID,
            'admin_status' => SubscriptionOrder::ADMIN_STATUS_APPROVED,
            'paid_at' => now(),
        ]);

        $response = $this->get(route('payment.callback', ['reference' => $order->access_token]));

        $response->assertRedirect(route('subscription.result', ['order' => $order, 'token' => $order->access_token]));

        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('اشتراک شما فعال شد')
            ->assertDontSee('به پایان نرسید');
    }

    public function test_reference_return_for_pending_order_with_transaction_attempts_verification(): void
    {
        $order = $this->makeOrder(['transaction_id' => 'TRX-PENDING-VERIFY']);

        // تأیید درگاه موفق (کال‌بکِ اصلی ممکن است گم شده باشد — بازگشتِ مرورگر بدون شناسه)
        Payment::shouldReceive('via')->andReturnSelf();
        Payment::shouldReceive('config')->andReturnSelf();
        Payment::shouldReceive('amount')->andReturnSelf();
        Payment::shouldReceive('transactionId')->andReturnSelf();
        Payment::shouldReceive('verify')->andReturn(new Receipt('local', 'OK-REF'));

        $response = $this->get(route('payment.callback', ['reference' => $order->access_token]));

        $response->assertRedirect(route('subscription.result', ['order' => $order->fresh(), 'token' => $order->access_token]));

        // سفارش پرداخت شده است (همان رفتار mount صفحهٔ نتیجه)
        $this->assertSame(SubscriptionOrder::STATUS_PAID, $order->fresh()->status);
        $this->followRedirects($response)
            ->assertOk()
            ->assertDontSee('به پایان نرسید');
    }

    public function test_reference_return_for_unfinished_order_keeps_task92_behavior(): void
    {
        // سفارش بدون تراکنش — واقعاً ناتمام؛ وضعیت تغییر نمی‌کند و پیام قبلی می‌ماند
        $order = $this->makeOrder([]);

        $response = $this->get(route('payment.callback', ['reference' => $order->access_token]));

        $response->assertRedirect(route('subscription.result', ['order' => $order, 'token' => $order->access_token]));
        $this->assertSame(SubscriptionOrder::STATUS_PENDING, $order->fresh()->status);

        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('پرداخت این سفارش به پایان نرسید یا از سمت درگاه تأیید نشد.');
    }

    /* -------------------------------------------------------------- */
    /*  همین رفتار برای خرید پکیج */
    /* -------------------------------------------------------------- */

    public function test_reference_return_for_paid_package_shows_success_message(): void
    {
        $package = $this->makePackage();
        $this->makePackageVersion($package);
        $customer = $this->makeCustomer();

        $purchase = PackagePurchase::create([
            'package_id' => $package->id,
            'customer_id' => $customer->id,
            'amount' => $package->default_price ?? 100000,
            'status' => PackagePurchase::STATUS_PAID,
            'paid_at' => now(),
            'gateway' => 'local',
        ]);

        $response = $this->get(route('payment.callback', ['reference' => $purchase->access_token]));

        $response->assertRedirect(route('payment.result', ['purchase' => $purchase, 'token' => $purchase->access_token]));

        $this->followRedirects($response)
            ->assertOk()
            ->assertDontSee('به پایان نرسید');
    }
}
