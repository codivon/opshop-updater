<?php

namespace Tests\Feature;

use App\Livewire\Shop\PackageShow;
use App\Models\DiscountCode;
use App\Models\PackageLicense;
use App\Models\PackagePurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

/**
 * جریان خرید پکیج (مهمان با کد آپدیت) — مسیر رایگان + کوپن ۱۰۰٪ + صدور لایسنس.
 */
class PackagePurchaseTest extends TestCase
{
    use RefreshDatabase, MakesShopData;

    public function test_guest_with_valid_update_code_gets_free_package(): void
    {
        $package  = $this->makePackage(['is_free' => true]);
        $this->makePackageVersion($package); // خرید به نسخه فعال وابسته است
        $customer = $this->makeCustomer();

        Livewire::test(PackageShow::class, ['slug' => $package->slug])
            ->set('updateCode', $customer->update_code)
            ->call('buy')
            ->assertRedirect();

        $purchase = PackagePurchase::first();

        $this->assertNotNull($purchase);
        $this->assertTrue($purchase->isPaid());
        $this->assertSame(0, $purchase->amount);
        $this->assertSame($customer->id, $purchase->customer_id);

        // لایسنس نامحدود برای پکیج رایگانِ بدون طرح
        $license = PackageLicense::query()->where('purchase_id', $purchase->id)->first();
        $this->assertNotNull($license);
        $this->assertSame(PackageLicense::STATUS_ACTIVE, $license->status);
        $this->assertNull($license->expires_at);
    }

    public function test_invalid_update_code_blocks_purchase(): void
    {
        $package = $this->makePackage(['is_free' => true]);

        Livewire::test(PackageShow::class, ['slug' => $package->slug])
            ->set('updateCode', 'WRONG-CODE')
            ->call('buy');

        $this->assertSame(0, PackagePurchase::count());
        $this->assertSame(0, PackageLicense::count());
    }

    public function test_full_coupon_makes_paid_package_free_with_license(): void
    {
        $package  = $this->makePackage();
        $this->makePackageVersion($package);
        $plan     = $this->makePlan($package, ['price' => 100000]);
        $customer = $this->makeCustomer();

        $coupon = $this->makeCoupon([
            'code'             => 'PKFREE1',
            'type'             => DiscountCode::TYPE_PERCENT,
            'value'            => 100,
            'applies_products' => false,
        ]);

        Livewire::test(PackageShow::class, ['slug' => $package->slug])
            ->set('updateCode', $customer->update_code)
            ->assertSet('planId', (string) $plan->id) // پیش‌انتخاب اولین طرح
            ->set('discountCode', 'pkfree1')
            ->call('applyDiscount')
            ->assertSet('appliedCode', 'PKFREE1')
            ->call('buy')
            ->assertRedirect();

        $purchase = PackagePurchase::first();

        $this->assertNotNull($purchase);
        $this->assertTrue($purchase->isPaid());
        $this->assertSame(0, $purchase->amount);
        $this->assertSame(100000, (int) $purchase->original_amount);
        $this->assertSame(100000, $purchase->discount_amount);
        $this->assertSame($coupon->id, $purchase->discount_code_id);
        $this->assertSame($plan->id, $purchase->pricing_plan_id);

        // لایسنس بر اساس طرح صادر شده
        $license = PackageLicense::query()->where('purchase_id', $purchase->id)->first();
        $this->assertNotNull($license);
        $this->assertNotNull($license->expires_at); // ۱۲ ماه

        $this->assertSame(1, $coupon->fresh()->used_count);
    }

    public function test_package_mark_as_paid_is_idempotent(): void
    {
        $package = $this->makePackage();
        $plan    = $this->makePlan($package);
        $coupon  = $this->makeCoupon(['code' => 'PKGIDEM', 'max_uses' => 3]);
        $customer = $this->makeCustomer();

        $purchase = PackagePurchase::create([
            'package_id'       => $package->id,
            'pricing_plan_id'  => $plan->id,
            'customer_id'      => $customer->id,
            'discount_code_id' => $coupon->id,
            'amount'           => 80000,
            'original_amount'  => 100000,
            'discount_amount'  => 20000,
            'status'           => PackagePurchase::STATUS_PENDING,
        ]);

        $purchase->markAsPaid('zarinpal');
        $purchase->markAsPaid('zarinpal'); // کال‌بک تکراری

        $this->assertSame(1, $coupon->fresh()->used_count);
        $this->assertTrue($purchase->fresh()->isPaid());
    }
}
