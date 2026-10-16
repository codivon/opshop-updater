<?php

namespace Tests\Feature;

use App\Models\DiscountCode;
use App\Services\DiscountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

class DiscountServiceTest extends TestCase
{
    use RefreshDatabase, MakesShopData;

    protected DiscountService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(DiscountService::class);
    }

    public function test_empty_code_means_no_discount_but_ok(): void
    {
        $result = $this->service->resolve(null, DiscountCode::CONTEXT_PRODUCT, 100000);

        $this->assertTrue($result['ok']);
        $this->assertNull($result['code']);
        $this->assertSame(0, $result['discount']);
        $this->assertSame(100000, $result['final']);
    }

    public function test_unknown_code_fails(): void
    {
        $result = $this->service->resolve('NOPE404', DiscountCode::CONTEXT_PRODUCT, 100000);

        $this->assertFalse($result['ok']);
        $this->assertNull($result['code']);
    }

    public function test_inactive_code_fails(): void
    {
        $this->makeCoupon(['code' => 'INACTIV', 'is_active' => false]);

        $result = $this->service->resolve('INACTIV', DiscountCode::CONTEXT_PRODUCT, 100000);

        $this->assertFalse($result['ok']);
    }

    public function test_expired_code_fails(): void
    {
        $this->makeCoupon(['code' => 'OLDONE1', 'expires_at' => now()->subDay()]);

        $result = $this->service->resolve('OLDONE1', DiscountCode::CONTEXT_PRODUCT, 100000);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('منقضی', $result['message']);
    }

    public function test_exhausted_code_fails(): void
    {
        $this->makeCoupon(['code' => 'ALLUSED', 'max_uses' => 3, 'used_count' => 3]);

        $result = $this->service->resolve('ALLUSED', DiscountCode::CONTEXT_PRODUCT, 100000);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('ظرفیت', $result['message']);
    }

    public function test_wrong_scope_fails(): void
    {
        // کد فقط برای پکیج → استفاده در محصول باید رد شود
        $this->makeCoupon([
            'code'             => 'PKGONLY',
            'applies_products' => false,
        ]);

        $result = $this->service->resolve('PKGONLY', DiscountCode::CONTEXT_PRODUCT, 100000);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('قابل استفاده نیست', $result['message']);
    }

    public function test_valid_percent_code_returns_discount_and_final(): void
    {
        $this->makeCoupon(['code' => 'PCT20XX', 'type' => DiscountCode::TYPE_PERCENT, 'value' => 20]);

        $result = $this->service->resolve(' pct20xx ', DiscountCode::CONTEXT_PRODUCT, 100000); // نرمال‌سازی حروف کوچک

        $this->assertTrue($result['ok']);
        $this->assertSame('PCT20XX', $result['code']->code);
        $this->assertSame(20000, $result['discount']);
        $this->assertSame(80000, $result['final']);
    }

    public function test_valid_fixed_code_is_capped_to_amount(): void
    {
        $this->makeCoupon(['code' => 'FIX9000', 'type' => DiscountCode::TYPE_FIXED, 'value' => 90000]);

        $result = $this->service->resolve('FIX9000', DiscountCode::CONTEXT_SUBSCRIPTION, 50000);

        $this->assertTrue($result['ok']);
        $this->assertSame(50000, $result['discount']);
        $this->assertSame(0, $result['final']);
    }

    public function test_zero_amount_with_code_fails(): void
    {
        $this->makeCoupon(['code' => 'ONFREE0']);

        $result = $this->service->resolve('ONFREE0', DiscountCode::CONTEXT_PACKAGE, 0);

        $this->assertFalse($result['ok']);
    }

    public function test_exhausted_after_usage_is_reflected(): void
    {
        $code = $this->makeCoupon(['code' => 'USEONCE', 'max_uses' => 1]);

        $first = $this->service->resolve('USEONCE', DiscountCode::CONTEXT_PRODUCT, 100000);
        $this->assertTrue($first['ok']);

        $code->recordUsage();

        $second = $this->service->resolve('USEONCE', DiscountCode::CONTEXT_PRODUCT, 100000);
        $this->assertFalse($second['ok']);
    }
}
