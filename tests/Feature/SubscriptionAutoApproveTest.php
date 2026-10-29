<?php

namespace Tests\Feature;

use App\Livewire\Settings;
use App\Models\PackageLicense;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionOrder;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

/**
 * تأیید خودکار خریدهای اشتراک (پنل → تنظیمات → «تنظیمات اشتراک»).
 *
 *  - تنظیم خاموش (پیش‌فرض): رفتار قبلی — خرید رایگان/پرداخت‌شده در انتظار تأیید مدیر می‌ماند.
 *  - تنظیم روشن: خرید رایگان همان لحظه فعال می‌شود (اشتراک + لایسنس پکیج‌های همراه)؛
 *    سفارشِ پرداخت‌شدهٔ درگاه هم بلافاصله پس از تأیید پرداخت فعال می‌شود.
 */
class SubscriptionAutoApproveTest extends TestCase
{
    use RefreshDatabase, MakesShopData;

    protected SubscriptionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SubscriptionService::class);
    }

    /** روشن/خاموش کردن تنظیم (همان اثر ذخیرهٔ پنل: رکورد + پاک‌سازی کش) */
    private function setAutoApprove(bool $on): void
    {
        Setting::set('subscriptions_auto_approve', $on ? '1' : '0', 'string', 'subscriptions');
        Cache::forget('setting:subscriptions_auto_approve');
    }

    private function makeSubscriptionPlan(array $attrs = []): \App\Models\SubscriptionPlan
    {
        return \App\Models\SubscriptionPlan::create(array_merge([
            'name'            => 'طرح تست تأیید خودکار',
            'slug'            => 'sub-auto-' . uniqid(),
            'duration_months' => 1,
            'price'           => 0,
            'discount_price'  => 0,
            'is_one_time'     => false,
            'is_active'       => true,
            'sort_order'      => 0,
        ], $attrs));
    }

    /** سفارش اشتراک پولی بدون عبور از درگاه (فقط برای تست مسیر تأیید پرداخت) */
    private function makePaidOrder(\App\Models\SubscriptionPlan $plan, \App\Models\Customer $customer): SubscriptionOrder
    {
        $order = SubscriptionOrder::create([
            'subscription_plan_id' => $plan->id,
            'customer_id'          => $customer->id,
            'amount'               => 100000,
            'discount'             => 0,
            'final_amount'         => 100000,
            'transaction_id'       => 'trx-auto-' . uniqid(),
            'gateway'              => 'local',
            'status'               => SubscriptionOrder::STATUS_PENDING,
            'admin_status'         => SubscriptionOrder::ADMIN_STATUS_PENDING,
            'meta'                 => [
                'plan' => [
                    'name'            => $plan->name,
                    'duration_months' => 1,
                    'features'        => [],
                    'packages'        => [],
                ],
            ],
        ]);

        // شبیه‌سازی تأیید موفق درگاه (markAsPaid idempotent است)
        $order->markAsPaid('local');
        $order->refresh();

        return $order;
    }

    /* ------------------------------------------------------------------ */
    /*  تنظیم خاموش — رفتار قبلی                                           */
    /* ------------------------------------------------------------------ */

    public function test_free_purchase_stays_pending_when_setting_is_off(): void
    {
        $plan     = $this->makeSubscriptionPlan();
        $customer = $this->makeCustomer();

        $result = $this->service->purchase($customer, $plan, null, null);

        $this->assertTrue($result['is_free']);
        $this->assertArrayNotHasKey('auto_approved', $result);

        $order = $result['order'];
        $this->assertSame(SubscriptionOrder::ADMIN_STATUS_PENDING, $order->admin_status);
        $this->assertSame(0, Subscription::where('customer_id', $customer->id)->count());
        $this->assertSame(1, SubscriptionService::pendingApprovalsCount());
    }

    public function test_paid_order_stays_pending_after_verification_when_setting_is_off(): void
    {
        $plan     = $this->makeSubscriptionPlan(['price' => 100000]);
        $customer = $this->makeCustomer();

        $order = $this->makePaidOrder($plan, $customer);

        $verify = $this->service->verifyPayment($order->transaction_id);

        $this->assertTrue($verify['paid']);
        $this->assertArrayNotHasKey('auto_approved', $verify);
        $this->assertSame(SubscriptionOrder::ADMIN_STATUS_PENDING, $verify['order']->admin_status);
        $this->assertSame(0, Subscription::where('customer_id', $customer->id)->count());
    }

    /* ------------------------------------------------------------------ */
    /*  تنظیم روشن — تأیید خودکار                                          */
    /* ------------------------------------------------------------------ */

    public function test_free_purchase_is_auto_approved_with_subscription_and_licenses(): void
    {
        $this->setAutoApprove(true);

        $plan    = $this->makeSubscriptionPlan();
        $package = $this->makePackage(['name' => 'پکیج همراه']);
        $plan->packages()->attach($package->id, ['free_months' => 2]);
        $customer = $this->makeCustomer();

        $result = $this->service->purchase($customer, $plan, null, null);

        $this->assertTrue($result['is_free']);
        $this->assertTrue($result['auto_approved'] ?? false);

        $order = $result['order'];
        $this->assertSame(SubscriptionOrder::ADMIN_STATUS_APPROVED, $order->admin_status);
        $this->assertNotNull($order->subscription_id);

        // اشتراک فعال ساخته شد
        $subscription = Subscription::where('customer_id', $customer->id)->sole();
        $this->assertSame('active', $subscription->status);
        $this->assertSame($order->id, $subscription->subscription_order_id);

        // لایسنس پکیج همراه با همان مدت رایگان
        $license = PackageLicense::where('customer_id', $customer->id)->sole();
        $this->assertSame($package->id, $license->package_id);
        $this->assertSame(2, (int) $license->duration_months);

        // دیگر سفارشی در انتظار مدیر نیست
        $this->assertSame(0, SubscriptionService::pendingApprovalsCount());
    }

    public function test_paid_order_is_auto_approved_after_verification(): void
    {
        $this->setAutoApprove(true);

        $plan     = $this->makeSubscriptionPlan(['price' => 100000]);
        $customer = $this->makeCustomer();

        $order = $this->makePaidOrder($plan, $customer);

        $verify = $this->service->verifyPayment($order->transaction_id);

        $this->assertTrue($verify['paid']);
        $this->assertTrue($verify['auto_approved'] ?? false);
        $this->assertSame(SubscriptionOrder::ADMIN_STATUS_APPROVED, $verify['order']->admin_status);
        $this->assertNotNull($verify['order']->subscription_id);

        $subscription = Subscription::where('customer_id', $customer->id)->sole();
        $this->assertSame('active', $subscription->status);
        $this->assertSame($order->id, $subscription->subscription_order_id);
    }

    public function test_auto_approve_does_not_touch_rejected_orders(): void
    {
        $this->setAutoApprove(true);

        $plan     = $this->makeSubscriptionPlan(['price' => 100000]);
        $customer = $this->makeCustomer();

        $order = $this->makePaidOrder($plan, $customer);

        // مدیر پیش‌تر رد کرده → تأیید خودکار نباید آن را فعال کند
        $order->update([
            'admin_status'    => SubscriptionOrder::ADMIN_STATUS_REJECTED,
            'rejected_at'     => now(),
            'rejected_reason' => 'تست',
        ]);

        $verify = $this->service->verifyPayment($order->transaction_id);

        $this->assertTrue($verify['paid']);
        $this->assertArrayNotHasKey('auto_approved', $verify);
        $this->assertSame(SubscriptionOrder::ADMIN_STATUS_REJECTED, $verify['order']->admin_status);
        $this->assertSame(0, Subscription::where('customer_id', $customer->id)->count());
    }

    /* ------------------------------------------------------------------ */
    /*  صفحهٔ تنظیمات پنل                                                   */
    /* ------------------------------------------------------------------ */

    public function test_settings_page_saves_auto_approve_toggle(): void
    {
        Livewire::test(Settings\Index::class)
            ->assertSet('form.subscriptions_auto_approve', false)
            ->set('form.subscriptions_auto_approve', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('1', Setting::get('subscriptions_auto_approve'));
        $this->assertTrue(SubscriptionService::autoApproveEnabled());

        // خاموش‌کردن دوباره از پنل
        Livewire::test(Settings\Index::class)
            ->set('form.subscriptions_auto_approve', false)
            ->call('save')
            ->assertHasNoErrors();

        Cache::forget('setting:subscriptions_auto_approve');
        $this->assertSame('0', Setting::get('subscriptions_auto_approve'));
        $this->assertFalse(SubscriptionService::autoApproveEnabled());
    }

    public function test_settings_page_loads_saved_toggle_state(): void
    {
        $this->setAutoApprove(true);

        Livewire::test(Settings\Index::class)
            ->assertSet('form.subscriptions_auto_approve', true);
    }
}
