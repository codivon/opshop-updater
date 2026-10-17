<?php

namespace Tests\Unit;

use App\Models\DiscountCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountCodeModelTest extends TestCase
{
    use RefreshDatabase;

    /* ---------------- calculateDiscount ---------------- */

    public function test_percent_discount_is_calculated_with_floor(): void
    {
        $code = new DiscountCode(['type' => DiscountCode::TYPE_PERCENT, 'value' => 25]);

        $this->assertSame(25000, $code->calculateDiscount(100000));
        $this->assertSame(2499, $code->calculateDiscount(9999)); // floor(2499.75) = 2499
    }

    public function test_percent_value_above_100_is_capped(): void
    {
        $code = new DiscountCode(['type' => DiscountCode::TYPE_PERCENT, 'value' => 150]);

        $this->assertSame(50000, $code->calculateDiscount(50000));
    }

    public function test_fixed_discount_is_capped_to_amount(): void
    {
        $code = new DiscountCode(['type' => DiscountCode::TYPE_FIXED, 'value' => 30000]);

        $this->assertSame(30000, $code->calculateDiscount(100000));
        $this->assertSame(20000, $code->calculateDiscount(20000)); // هرگز بیشتر از مبلغ نمی‌شود
        $this->assertSame(0, $code->calculateDiscount(0));
    }

    /* ---------------- isUsable / hasRemainingUses ---------------- */

    public function test_is_usable_for_active_code_with_capacity(): void
    {
        $code = DiscountCode::create([
            'code' => 'USABLE1', 'type' => 'percent', 'value' => 10,
            'max_uses' => 5, 'used_count' => 2, 'expires_at' => now()->addDays(3),
            'is_active' => true,
        ]);

        $this->assertTrue($code->isUsable());
        $this->assertSame(3, $code->remainingUses());
    }

    public function test_inactive_code_is_not_usable(): void
    {
        $code = DiscountCode::create([
            'code' => 'OFFCODE', 'type' => 'percent', 'value' => 10, 'is_active' => false,
        ]);

        $this->assertFalse($code->isUsable());
    }

    public function test_expired_code_is_not_usable(): void
    {
        $code = DiscountCode::create([
            'code' => 'OLDCODE', 'type' => 'percent', 'value' => 10,
            'expires_at' => now()->subHour(), 'is_active' => true,
        ]);

        $this->assertFalse($code->isUsable());
    }

    public function test_exhausted_code_is_not_usable(): void
    {
        $code = DiscountCode::create([
            'code' => 'FULLUSE', 'type' => 'percent', 'value' => 10,
            'max_uses' => 3, 'used_count' => 3, 'is_active' => true,
        ]);

        $this->assertFalse($code->hasRemainingUses());
        $this->assertFalse($code->isUsable());
        $this->assertSame(0, $code->remainingUses());
    }

    public function test_unlimited_uses_has_null_remaining(): void
    {
        $code = DiscountCode::create([
            'code' => 'NOFULL', 'type' => 'percent', 'value' => 10,
            'max_uses' => null, 'used_count' => 500, 'is_active' => true,
        ]);

        $this->assertTrue($code->hasRemainingUses());
        $this->assertNull($code->remainingUses());
    }

    /* ---------------- appliesTo ---------------- */

    public function test_applies_to_respects_scope_flags(): void
    {
        $code = new DiscountCode([
            'applies_packages' => true, 'applies_products' => false, 'applies_subscriptions' => false,
        ]);

        $this->assertTrue($code->appliesTo(DiscountCode::CONTEXT_PACKAGE));
        $this->assertFalse($code->appliesTo(DiscountCode::CONTEXT_PRODUCT));
        $this->assertFalse($code->appliesTo(DiscountCode::CONTEXT_SUBSCRIPTION));
    }

    /* ---------------- normalize ---------------- */

    public function test_normalize_uppercases_and_trims(): void
    {
        $this->assertSame('WELCOME10', DiscountCode::normalize('  welcome10 '));
        $this->assertSame('AB12', DiscountCode::normalize(' ab۱۲ ')); // ارقام فارسی
    }

    /* ---------------- recordUsage ---------------- */

    public function test_record_usage_increments_counter(): void
    {
        $code = DiscountCode::create([
            'code' => 'INCUSE', 'type' => 'percent', 'value' => 10,
            'max_uses' => 10, 'used_count' => 1, 'is_active' => true,
        ]);

        $code->recordUsage();

        $this->assertSame(2, $code->fresh()->used_count);
    }

    public function test_record_usage_never_exceeds_max_uses(): void
    {
        $code = DiscountCode::create([
            'code' => 'CAPUSE', 'type' => 'percent', 'value' => 10,
            'max_uses' => 2, 'used_count' => 2, 'is_active' => true,
        ]);

        $code->recordUsage();

        $this->assertSame(2, $code->fresh()->used_count); // ظرفیت پر → افزایش نمی‌یابد
    }

    /* ---------------- statusLabel ---------------- */

    public function test_status_labels(): void
    {
        $active = DiscountCode::create(['code' => 'LBLACT1', 'type' => 'percent', 'value' => 10, 'is_active' => true]);
        $this->assertSame(['فعال', 'success'], $active->statusLabel());

        $expired = DiscountCode::create(['code' => 'LBLEX01', 'type' => 'percent', 'value' => 10, 'is_active' => true, 'expires_at' => now()->subDay()]);
        $this->assertSame(['منقضی شده', 'danger'], $expired->statusLabel());

        $full = DiscountCode::create(['code' => 'LBLFUL1', 'type' => 'percent', 'value' => 10, 'is_active' => true, 'max_uses' => 1, 'used_count' => 1]);
        $this->assertSame(['تکمیل ظرفیت', 'warning'], $full->statusLabel());

        $off = DiscountCode::create(['code' => 'LBLOFF1', 'type' => 'percent', 'value' => 10, 'is_active' => false]);
        $this->assertSame(['غیرفعال', 'neutral'], $off->statusLabel());
    }
}
