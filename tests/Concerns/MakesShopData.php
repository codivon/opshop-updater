<?php

namespace Tests\Concerns;

use App\Models\Customer;
use App\Models\DiscountCode;
use App\Models\Package;
use App\Models\PackagePricingPlan;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\Project;
use Illuminate\Support\Facades\Storage;

/**
 * هلپرهای ساخت دیتای تست فروشگاه — برای همه تست‌های Feature خرید/تخفیف/دیدگاه.
 */
trait MakesShopData
{
    protected function makeProduct(array $attrs = []): Product
    {
        return Product::create(array_merge([
            'name'             => 'پروژه تست ' . uniqid(),
            'slug'             => 'test-product-' . uniqid(),
            'short_description'=> 'توضیح کوتاه',
            'description'      => '<p>توضیح کامل</p>',
            'price'            => 50000,
            'discount'         => 0,
            'is_free'          => false,
            'status'           => Product::STATUS_ACTIVE,
            'sort_order'       => 0,
        ], $attrs));
    }

    protected function makeProductVersion(Product $product, array $attrs = []): ProductVersion
    {
        $path = 'products/' . $product->slug . '/test-v1.zip';

        // فایل واقعی روی دیسک (در تست‌ها fake شده) تا hasFile() پاس شود
        Storage::disk('local')->put($path, 'fake-zip-content-' . uniqid());

        return $product->versions()->create(array_merge([
            'version'       => '1.0.0',
            'type'          => 'major',
            'changelog'     => 'انتشار اولیه',
            'file_path'     => $path,
            'file_size'     => 1024,
            'is_mandatory'  => false,
            'status'        => ProductVersion::STATUS_ACTIVE,
            'release_date'  => now(),
        ], $attrs));
    }

    protected function makePackage(array $attrs = []): Package
    {
        $project = Project::create([
            'name' => 'پروژه ' . uniqid(),
            'slug' => 'proj-' . uniqid(),
        ]);

        return Package::create(array_merge([
            'project_id'       => $project->id,
            'slug'             => 'test-package-' . uniqid(),
            'name'             => 'پکیج تست ' . uniqid(),
            'short_description'=> 'توضیح کوتاه',
            'description'      => '<p>توضیح کامل</p>',
            'is_free'          => false,
            'default_price'    => 100000,
            'status'           => Package::STATUS_ACTIVE,
            'sort_order'       => 0,
        ], $attrs));
    }

    protected function makePlan(Package $package, array $attrs = []): PackagePricingPlan
    {
        return $package->pricingPlans()->create(array_merge([
            'name'            => 'طرح تست',
            'duration_months' => 12,
            'price'           => 100000,
            'discount_price'  => 0,
            'is_one_time'     => false,
            'is_active'       => true,
            'sort_order'      => 0,
        ], $attrs));
    }

    /** نسخه فعال پکیج — بدون نیاز به فایل واقعی (خرید به فایل وابسته نیست) */
    protected function makePackageVersion(Package $package, array $attrs = []): \App\Models\PackageVersion
    {
        return $package->versions()->create(array_merge([
            'version'             => '1.0.0',
            'type'                => 'major',
            'changelog'           => 'انتشار اولیه',
            'what_added'          => '—',
            'what_changed'        => '—',
            'what_fixed'          => '—',
            'file_path'           => 'packages/' . $package->slug . '/v1.zip',
            'file_size'           => 1024,
            'min_php_version'     => '8.1',
            'min_laravel_version' => '10',
            'dependencies'        => [],
            'is_mandatory'        => false,
            'status'              => \App\Models\PackageVersion::STATUS_ACTIVE,
        ], $attrs));
    }

    protected function makeCustomer(array $attrs = []): Customer
    {
        return Customer::create(array_merge([
            'name'        => 'مشتری تست',
            'email'       => 'cust-' . uniqid() . '@example.com',
            'phone'       => '0912' . str_pad((string) random_int(0, 9999999), 7, '0'),
            'website_url' => 'https://example-' . uniqid() . '.com',
            'status'      => 'active',
        ], $attrs));
    }

    protected function makeCoupon(array $attrs = []): DiscountCode
    {
        return DiscountCode::create(array_merge([
            'code'                  => 'COUP' . strtoupper(substr(uniqid(), -5)),
            'type'                  => DiscountCode::TYPE_PERCENT,
            'value'                 => 20,
            'max_uses'              => null,
            'expires_at'            => null,
            'applies_packages'      => true,
            'applies_products'      => true,
            'applies_subscriptions' => true,
            'is_active'             => true,
        ], $attrs));
    }
}
