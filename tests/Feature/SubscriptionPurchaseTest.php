<?php

namespace Tests\Feature;

use App\Models\DiscountCode;
use App\Models\SubscriptionOrder;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

/**
 * ثبت سفارش اشتراک با کد تخفیف (SubscriptionService::purchase).
 * مسیر پرداخت واقعی (درگاه) در تست شبیه‌سازی نمی‌شود؛ خطای درگاه باید
 * سفارش یتیم نگذارد.
 */
class SubscriptionPurchaseTest extends TestCase
{
    use RefreshDatabase, MakesShopData;

    protected SubscriptionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SubscriptionService::class);
    }

    protected function makeSubscriptionPlan(array $attrs = []): \App\Models\SubscriptionPlan
    {
        return \App\Models\SubscriptionPlan::create(array_merge([
            'name'            => 'طرح اشتراک تست',
            'slug'            => 'sub-plan-' . uniqid(),
            'duration_months' => 1,
            'price'           => 100000,
            'discount_price'  => 0,
            'is_one_time'     => false,
            'is_active'       => true,
            'sort_order'      => 0,
        ], $attrs));
    }

    public function test_free_plan_creates_order_awaiting_admin_approval(): void
    {
        $plan     = $this->makeSubscriptionPlan(['price' => 0]);
        $customer = $this->makeCustomer();

        $result = $this->service->purchase($customer, $plan, null, null);

        $this->assertTrue($result['is_free']);

        $order = $result['order'];
        $this->assertTrue($order->isPaid());
        $this->assertSame(0, $order->final_amount);
        $this->assertNull($order->discount_code_id);
    }

    public function test_full_coupon_makes_subscription_free(): void
    {
        $plan     = $this->makeSubscriptionPlan(['price' => 50000]);
        $customer = $this->makeCustomer();

        $coupon = $this->makeCoupon([
            'code'                  => 'SUBFREE',
            'type'                  => DiscountCode::TYPE_PERCENT,
            'value'                 => 100,
            'applies_packages'      => false,
            'applies_products'      => false,
        ]);

        $result = $this->service->purchase($customer, $plan, null, null, $coupon);

        $this->assertTrue($result['is_free']);

        $order = $result['order'];

        $this->assertSame(0, $order->final_amount);
        $this->assertSame(50000, $order->discount); // تخفیف کوپن در گزارش حفظ می‌شود
        $this->assertSame($coupon->id, $order->discount_code_id);

        // snapshot کوپن در meta
        $this->assertSame('SUBFREE', $order->meta['coupon']['code'] ?? null);
        $this->assertSame(50000, $order->meta['coupon']['discount'] ?? null);

        $this->assertSame(1, $coupon->fresh()->used_count);
    }

    public function test_plan_discount_and_coupon_stack_correctly(): void
    {
        // طرح ۱۰۰٬۰۰۰ با تخفیف خودِ طرح ۲۰٬۰۰۰ → مبلغ پایه ۸۰٬۰۰۰
        // کوپن ثابت ۱۰٬۰۰۰ روی ۸۰٬۰۰۰ → نهایی ۷۰٬۰۰۰
        $plan     = $this->makeSubscriptionPlan(['price' => 100000, 'discount_price' => 20000]);
        $customer = $this->makeCustomer();
        $coupon   = $this->makeCoupon([
            'code' => 'FIX10SUB',
            'type' => DiscountCode::TYPE_FIXED,
            'value' => 10000,
        ]);

        // مسیر پرداختی → درگاه در تست موجود نیست → خطا، ولی مبلغ سفارش را قبل از حذف
        // نمی‌توان دید؛ پس منطق قیمت را از طریق کوپنِ کامل‌پوشش‌کننده تست می‌کنیم:
        // کوپن ۸۰٬۰۰۰ تومانی روی پایه ۸۰٬۰۰۰ → نهایی صفر → مسیر رایگان.
        $fullCoupon = $this->makeCoupon([
            'code' => 'FIX80SUB',
            'type' => DiscountCode::TYPE_FIXED,
            'value' => 80000,
        ]);

        $result = $this->service->purchase($customer, $plan, null, null, $fullCoupon);

        $this->assertTrue($result['is_free']);

        $order = $result['order'];
        $this->assertSame(0, $order->final_amount);
        // totalDiscount = تخفیف طرح (۲۰٬۰۰۰) + کوپن (۸۰٬۰۰۰)
        $this->assertSame(100000, $order->discount);
        // در مسیر رایگان، amount (مبلغ درگاه) صفر می‌شود و فقط discount نگه داشته می‌شود
        $this->assertSame(0, $order->amount);
    }

    public function test_gateway_failure_leaves_no_orphan_order(): void
    {
        $plan     = $this->makeSubscriptionPlan(['price' => 100000]);
        $customer = $this->makeCustomer();

        $this->expectException(\RuntimeException::class);

        try {
            // درگاهی فعال نیست → createSubscriptionPayment شکست می‌خورد
            $this->service->purchase($customer, $plan, 'zarinpal', null);
        } finally {
            $this->assertSame(0, SubscriptionOrder::count());
        }
    }

    public function test_subscription_order_mark_as_paid_is_idempotent(): void
    {
        $plan     = $this->makeSubscriptionPlan();
        $customer = $this->makeCustomer();
        $coupon   = $this->makeCoupon(['code' => 'SUBIDEM', 'max_uses' => 2]);

        $order = SubscriptionOrder::create([
            'subscription_plan_id' => $plan->id,
            'customer_id'          => $customer->id,
            'discount_code_id'     => $coupon->id,
            'amount'               => 100000,
            'discount'             => 0,
            'final_amount'         => 100000,
            'status'               => SubscriptionOrder::STATUS_PENDING,
        ]);

        $order->markAsPaid('zarinpal');
        $order->markAsPaid('zarinpal');

        $this->assertSame(1, $coupon->fresh()->used_count);
        $this->assertTrue($order->fresh()->isPaid());
    }

    public function test_inactive_plan_is_rejected(): void
    {
        $plan     = $this->makeSubscriptionPlan(['is_active' => false]);
        $customer = $this->makeCustomer();

        $this->expectException(\RuntimeException::class);

        $this->service->purchase($customer, $plan, null, null);
    }
}
