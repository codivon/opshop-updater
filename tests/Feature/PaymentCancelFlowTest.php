<?php

namespace Tests\Feature;

use App\Livewire\Shop\SubscriptionsIndex;
use App\Models\Gateway;
use App\Models\Subscription;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionPlan;
use App\Services\RenewalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

/**
 * Task 92 — جریان «پرداخت لغو شده / ناتمام»:
 *  ۱) کال‌بک بدون هیچ شناسه‌ای → صفحهٔ اختصاصی «پرداخت لغو شد» (نه صفحهٔ اصلی)
 *  ۲) کال‌بک با شناسهٔ ارجاع مسیر (access_token) → صفحهٔ نتیجهٔ همان سفارش
 *  ۳) سفارشِ بی‌پاسخِ ناتمام از درگاه نباید «در انتظار تأیید مدیر» نشان داده شود و
 *     خرید مجدد را قفل کند
 *  ۴) پیام محدودیت خرید مجددِ طرح، راهنمای «جایگزینی با تیکت» را هم دارد
 */
class PaymentCancelFlowTest extends TestCase
{
    use MakesShopData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gateway::firstOrCreate(['key' => 'local'], ['name' => 'درگاه آزمایشی', 'is_active' => true]);
    }

    protected function makePlan(array $attrs = []): SubscriptionPlan
    {
        return SubscriptionPlan::create(array_merge([
            'name' => 'طرح تست لغو پرداخت',
            'slug' => 'cancel-plan-'.uniqid(),
            'duration_months' => 1,
            'price' => 100000,
            'discount_price' => 0,
            'is_one_time' => false,
            'is_active' => true,
            'sort_order' => 0,
        ], $attrs));
    }

    /* -------------------------------------------------------------- */
    /*  ۱) کال‌بک بدون شناسه → صفحهٔ «پرداخت لغو شد» */
    /* -------------------------------------------------------------- */

    public function test_callback_without_any_identifier_redirects_to_canceled_page(): void
    {
        $this->get(route('payment.callback'))
            ->assertRedirect(route('payment.canceled'));

        $this->get(route('payment.canceled'))->assertOk()
            ->assertSee('پرداخت لغو شد یا ناتمام ماند')
            ->assertSee('هیچ مبلغی از حساب شما کسر نشده است');
    }

    /* -------------------------------------------------------------- */
    /*  ۲) شناسهٔ ارجاع مسیر (access_token) → صفحهٔ نتیجهٔ سفارش */
    /* -------------------------------------------------------------- */

    public function test_callback_with_reference_redirects_unfinished_subscription_order_to_result(): void
    {
        $customer = $this->makeCustomer();
        $plan = $this->makePlan();

        // سفارش ناتمام: کاربر در درگاه بازگشته و پرداخت تأیید نشده
        $order = SubscriptionOrder::create([
            'subscription_plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => 100000,
            'discount' => 0,
            'final_amount' => 100000,
            'gateway' => 'local',
            'status' => SubscriptionOrder::STATUS_PENDING,
            'admin_status' => SubscriptionOrder::ADMIN_STATUS_PENDING,
        ]);

        $this->assertNotSame('', (string) $order->access_token);

        $this->get(route('payment.callback', ['reference' => $order->access_token]))
            ->assertRedirect(route('subscription.result', ['order' => $order, 'token' => $order->access_token]));

        // سفارش همچنان ناتمام است (پرداختی تأیید نشده)
        $this->assertSame(SubscriptionOrder::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_callback_with_unknown_reference_shows_canceled_page(): void
    {
        $this->get(route('payment.callback', ['reference' => 'no-such-token']))
            ->assertRedirect(route('payment.canceled'));
    }

    public function test_callback_url_with_reference_segment_is_generated_for_orders(): void
    {
        $customer = $this->makeCustomer();
        $plan = $this->makePlan();

        $order = SubscriptionOrder::create([
            'subscription_plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => 100000,
            'discount' => 0,
            'final_amount' => 100000,
            'gateway' => 'local',
            'status' => SubscriptionOrder::STATUS_PENDING,
            'admin_status' => SubscriptionOrder::ADMIN_STATUS_PENDING,
        ]);

        // قرارداد Task 92: کال‌بک درگاه شناسهٔ ارجاع (access_token) را به‌صورت سگمنت مسیر دارد
        $callbackUrl = URL::route('payment.callback', ['reference' => $order->access_token]);

        $this->assertStringContainsString('/payment/callback/'.$order->access_token, $callbackUrl);

        // همان URL باید درخواست GET را به صفحهٔ نتیجهٔ سفارش هدایت کند
        $this->get($callbackUrl)
            ->assertRedirect(route('subscription.result', ['order' => $order, 'token' => $order->access_token]));
    }

    /* -------------------------------------------------------------- */
    /*  ۳) سفارش ناتمام از درگاه → خرید مجدد قفل نمی‌شود */
    /* -------------------------------------------------------------- */

    public function test_unpaid_pending_order_is_not_shown_as_awaiting_admin(): void
    {
        $customer = $this->makeCustomer();
        $plan = $this->makePlan();

        // سفارش بی‌پاسخ: کاربر از درگاه برگشته (pending + پرداخت‌نشده)
        SubscriptionOrder::create([
            'subscription_plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => 100000,
            'discount' => 0,
            'final_amount' => 100000,
            'gateway' => 'local',
            'status' => SubscriptionOrder::STATUS_PENDING,
            'admin_status' => SubscriptionOrder::ADMIN_STATUS_PENDING,
        ]);

        $component = Livewire::test(SubscriptionsIndex::class)
            ->set('updateCode', $customer->update_code);

        // بدون بج «در انتظار تأیید مدیر» و دکمهٔ خرید فعال است
        $this->assertStringNotContainsString('درخواست شما در انتظار تأیید مدیر است.', $component->html());
    }

    public function test_paid_pending_order_is_still_shown_as_awaiting_admin(): void
    {
        $customer = $this->makeCustomer();
        $plan = $this->makePlan();

        // سفارش پرداخت‌شدهٔ در انتظار مدیر — همان رفتار صحیح قبلی
        SubscriptionOrder::create([
            'subscription_plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => 100000,
            'discount' => 0,
            'final_amount' => 100000,
            'gateway' => 'local',
            'status' => SubscriptionOrder::STATUS_PAID,
            'admin_status' => SubscriptionOrder::ADMIN_STATUS_PENDING,
            'paid_at' => now(),
        ]);

        $component = Livewire::test(SubscriptionsIndex::class)
            ->set('updateCode', $customer->update_code);

        $this->assertStringContainsString('درخواست شما در انتظار تأیید مدیر است.', $component->html());
    }

    public function test_failed_order_does_not_block_repurchase(): void
    {
        $customer = $this->makeCustomer();
        $plan = $this->makePlan();

        SubscriptionOrder::create([
            'subscription_plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => 100000,
            'discount' => 0,
            'final_amount' => 100000,
            'gateway' => 'local',
            'status' => SubscriptionOrder::STATUS_FAILED,
            'admin_status' => SubscriptionOrder::ADMIN_STATUS_PENDING,
        ]);

        $component = Livewire::test(SubscriptionsIndex::class)
            ->set('updateCode', $customer->update_code);

        $this->assertStringNotContainsString('درخواست شما در انتظار تأیید مدیر است.', $component->html());
    }

    /* -------------------------------------------------------------- */
    /*  ۴) راهنمای «جایگزینی اشتراک با تیکت» در پیام محدودیت */
    /* -------------------------------------------------------------- */

    public function test_renewal_denied_messages_mention_ticket_replacement(): void
    {
        $customer = $this->makeCustomer();
        $plan = $this->makePlan();

        // اشتراک فعال با ۳۰ روز اعتبار → پنجرهٔ ۵ روزه بسته است
        Subscription::create([
            'customer_id' => $customer->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDays(2),
            'expires_at' => now()->addDays(30),
        ]);

        $evaluation = app(RenewalService::class)->evaluateSubscriptionRenewal($customer->id, $plan->id);

        $this->assertFalse($evaluation['allowed']);
        $this->assertStringContainsString('تیکت', $evaluation['message']);
        $this->assertStringContainsString('جایگزین', $evaluation['message']);
    }
}
