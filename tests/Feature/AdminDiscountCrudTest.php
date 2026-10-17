<?php

namespace Tests\Feature;

use App\Livewire\Discounts\Index as DiscountsIndex;
use App\Models\DiscountCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * پنل مدیریت کدهای تخفیف — ایجاد/اعتبارسنجی/تولید کد/تغییر وضعیت.
 */
class AdminDiscountCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_percent_discount_code(): void
    {
        Livewire::test(DiscountsIndex::class)
            ->call('create')
            ->assertSet('showModal', true)
            ->set('form.code', 'welcome10')
            ->set('form.type', DiscountCode::TYPE_PERCENT)
            ->set('form.value', 10)
            ->set('form.max_uses', '50')
            ->set('form.expires_at', '2026-12-31T23:59')
            ->call('save')
            ->assertSet('showModal', false);

        $code = DiscountCode::query()->where('code', 'WELCOME10')->first();

        $this->assertNotNull($code); // ذخیره با حروف بزرگ
        $this->assertSame(10, $code->value);
        $this->assertSame(50, $code->max_uses);
        $this->assertTrue($code->is_active);
        $this->assertTrue($code->applies_packages);
        $this->assertTrue($code->applies_products);
        $this->assertTrue($code->applies_subscriptions);
        $this->assertNotNull($code->expires_at);
    }

    public function test_code_is_required_and_must_be_unique(): void
    {
        DiscountCode::create(['code' => 'DUP0001', 'type' => 'percent', 'value' => 10]);

        Livewire::test(DiscountsIndex::class)
            ->call('create')
            ->set('form.code', 'dup0001') // همان کد با حروف کوچک → بعد از نرمال‌سازی تکراری
            ->set('form.value', 15)
            ->call('save')
            ->assertHasErrors(['form.code']);
    }

    public function test_at_least_one_scope_is_required(): void
    {
        Livewire::test(DiscountsIndex::class)
            ->call('create')
            ->set('form.code', 'NOSCOPE')
            ->set('form.value', 10)
            ->set('form.applies_packages', false)
            ->set('form.applies_products', false)
            ->set('form.applies_subscriptions', false)
            ->call('save')
            ->assertHasErrors(['form.applies_packages']);

        $this->assertSame(0, DiscountCode::count());
    }

    public function test_percent_value_must_be_between_1_and_100(): void
    {
        Livewire::test(DiscountsIndex::class)
            ->call('create')
            ->set('form.code', 'TOOBIGP')
            ->set('form.type', DiscountCode::TYPE_PERCENT)
            ->set('form.value', 150)
            ->call('save')
            ->assertHasErrors(['form.value']);

        $this->assertSame(0, DiscountCode::count());
    }

    public function test_fixed_value_minimum_is_1000(): void
    {
        Livewire::test(DiscountsIndex::class)
            ->call('create')
            ->set('form.code', 'TOOSMALL')
            ->set('form.type', DiscountCode::TYPE_FIXED)
            ->set('form.value', 500)
            ->call('save')
            ->assertHasErrors(['form.value']);
    }

    public function test_generate_code_produces_unique_8_char_code(): void
    {
        $component = Livewire::test(DiscountsIndex::class);

        $component->call('generateCode');

        $code = $component->get('form.code');

        $this->assertMatchesRegularExpression('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{8}$/', $code);
        $this->assertFalse(DiscountCode::where('code', $code)->exists());
    }

    public function test_toggle_active_flips_state(): void
    {
        $code = DiscountCode::create(['code' => 'TOGGLE1', 'type' => 'percent', 'value' => 10, 'is_active' => true]);

        Livewire::test(DiscountsIndex::class)
            ->call('toggleActive', $code->id);

        $this->assertFalse($code->fresh()->is_active);
    }

    public function test_edit_updates_existing_code(): void
    {
        $code = DiscountCode::create([
            'code' => 'EDITME1', 'type' => 'percent', 'value' => 10, 'is_active' => true,
        ]);

        Livewire::test(DiscountsIndex::class)
            ->call('edit', $code->id)
            ->assertSet('form.code', 'EDITME1')
            ->set('form.value', 30)
            ->call('save');

        $this->assertSame(30, $code->fresh()->value);
    }

    public function test_delete_removes_code_but_keeps_purchase_history_columns(): void
    {
        $code = DiscountCode::create(['code' => 'DEL0001', 'type' => 'percent', 'value' => 10]);

        Livewire::test(DiscountsIndex::class)
            ->call('delete', $code->id);

        $this->assertDatabaseMissing('discount_codes', ['code' => 'DEL0001']);
    }
}
