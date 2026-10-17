<?php

namespace Tests\Feature;

use App\Livewire\Shop\ProductShow;
use App\Models\DiscountCode;
use App\Models\ProductPurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

/**
 * جریان خرید محصول (مهمان) — مسیرهای رایگان/کوپن + راستی‌آزمایی سمت سرور.
 * (مسیر درگاه پرداخت واقعی در تست شبیه‌سازی نمی‌شود؛ فقط منطق قیمت/تخفیف/رایگان)
 */
class ProductPurchaseTest extends TestCase
{
    use RefreshDatabase, MakesShopData;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_guest_can_buy_free_product_without_any_input(): void
    {
        $product = $this->makeProduct(['is_free' => true, 'price' => 0]);
        $this->makeProductVersion($product);

        Livewire::test(ProductShow::class, ['slug' => $product->slug])
            ->call('buy')
            ->assertRedirect(route('payment.result.product', ProductPurchase::first()));

        $purchase = ProductPurchase::first();

        $this->assertNotNull($purchase);
        $this->assertTrue($purchase->isPaid());
        $this->assertSame(0, $purchase->amount);
        $this->assertNull($purchase->discount_code_id);

        // شمارنده فروش محصول
        $this->assertSame(1, $product->fresh()->sales_count);
    }

    public function test_typed_but_unapplied_code_is_ignored_on_free_product(): void
    {
        // باگ رفع‌شده: کدِ تایپ‌شده نیمه‌کاره نباید جلوی دریافت رایگان را بگیرد
        $product = $this->makeProduct(['is_free' => true, 'price' => 0]);
        $this->makeProductVersion($product);

        Livewire::test(ProductShow::class, ['slug' => $product->slug])
            ->set('discountCode', 'LEFTOVER')
            ->call('buy')
            ->assertRedirect();

        $this->assertSame(1, ProductPurchase::count());
        $this->assertSame(0, ProductPurchase::first()->amount);
    }

    public function test_full_discount_code_makes_paid_product_free(): void
    {
        $product = $this->makeProduct(['price' => 50000]);
        $this->makeProductVersion($product);

        $coupon = $this->makeCoupon([
            'code'             => 'FULL100',
            'type'             => DiscountCode::TYPE_PERCENT,
            'value'            => 100,
            'max_uses'         => 5,
            'applies_packages' => false,
        ]);

        Livewire::test(ProductShow::class, ['slug' => $product->slug])
            ->set('discountCode', 'full100')
            ->call('applyDiscount')
            ->assertSet('appliedCode', 'FULL100')
            ->call('buy')
            ->assertRedirect();

        $purchase = ProductPurchase::first();

        $this->assertNotNull($purchase);
        $this->assertTrue($purchase->isPaid());
        $this->assertSame(0, $purchase->amount);                       // مبلغ نهایی صفر
        $this->assertSame(50000, (int) $purchase->original_amount);    // مبلغ قبل از کوپن
        $this->assertSame(50000, $purchase->discount_amount);
        $this->assertSame($coupon->id, $purchase->discount_code_id);

        // شمارنده کوپن دقیقاً یک‌بار مصرف شده
        $this->assertSame(1, $coupon->fresh()->used_count);
        // فروش محصول ثبت شده
        $this->assertSame(1, $product->fresh()->sales_count);
    }

    public function test_invalid_coupon_blocks_purchase_before_any_record(): void
    {
        $product = $this->makeProduct(['price' => 50000]);
        $this->makeProductVersion($product);

        $this->makeCoupon(['code' => 'EXPIRD1', 'expires_at' => now()->subDay()]);

        Livewire::test(ProductShow::class, ['slug' => $product->slug])
            ->set('discountCode', 'EXPIRD1')
            ->call('applyDiscount')
            ->assertSet('appliedCode', '')
            ->call('buy');

        // هیچ رکورد خریدی نباید ساخته شده باشد
        $this->assertSame(0, ProductPurchase::count());
    }

    public function test_wrong_scope_coupon_is_rejected_for_product(): void
    {
        $product = $this->makeProduct(['price' => 50000]);
        $this->makeProductVersion($product);

        $this->makeCoupon(['code' => 'PKGNOPR', 'applies_products' => false]);

        Livewire::test(ProductShow::class, ['slug' => $product->slug])
            ->set('discountCode', 'PKGNOPR')
            ->call('applyDiscount')
            ->assertSet('appliedCode', '');

        $this->assertSame(0, ProductPurchase::count());
    }

    public function test_mark_as_paid_is_idempotent_for_coupon_counter(): void
    {
        $product  = $this->makeProduct(['price' => 50000]);
        $coupon   = $this->makeCoupon(['code' => 'IDEM001', 'max_uses' => 5]);

        $purchase = ProductPurchase::create([
            'product_id'       => $product->id,
            'discount_code_id' => $coupon->id,
            'original_amount'  => 50000,
            'amount'           => 40000, // بعد از ۲۰٪ تخفیف
            'discount_amount'  => 10000,
            'status'           => ProductPurchase::STATUS_PENDING,
        ]);

        // شبیه‌سازی کال‌بک تکراری درگاه
        $purchase->markAsPaid('zarinpal');
        $purchase->refresh();
        $purchase->markAsPaid('zarinpal');

        $this->assertSame(1, $coupon->fresh()->used_count);
        $this->assertSame(1, $product->fresh()->sales_count);
        $this->assertTrue($purchase->fresh()->isPaid());
    }
}
