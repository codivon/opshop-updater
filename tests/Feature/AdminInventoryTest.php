<?php

namespace Tests\Feature;

use App\Livewire\Inventory\Index as InventoryIndex;
use App\Models\PackageLicense;
use App\Models\Template;
use App\Models\TemplateLicense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

/**
 * نمای کامل فروشگاه (/admin/inventory) — همهٔ پکیج‌ها، محصولات و لایسنس‌ها
 * در یک صفحه با جزئیات کامل: تعداد نسخه‌ها، طرح‌های قیمت، وضعیت لایسنس‌ها و…
 *
 * - سه تب سمت سرور: پکیج‌ها / محصولات / لایسنس‌ها (با زیرزبانهٔ پکیج/قالب).
 * - ردیف پکیج/محصول باز‌شونده: فهرست نسخه‌ها (+ طرح‌های قیمت برای پکیج).
 * - تب لایسنس‌ها: پکیج → صفحهٔ اختصاصی؛ قالب → صفحهٔ لایسنس‌ها با تب قالب
 *   (چون admin.licenses.show فقط PackageLicense می‌بیند و جدول‌ها تداخل شناسه دارند).
 */
class AdminInventoryTest extends TestCase
{
    use MakesShopData, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'مدیر تست',
            'email' => 'admin@inventory.test',
            'password' => bcrypt('secret123'),
            'role' => 'admin',
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.inventory'))->assertRedirect(route('login'));
    }

    public function test_inventory_page_renders_for_admin(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.inventory'))
            ->assertOk()
            ->assertSee('نمای کامل فروشگاه')
            ->assertSee('پکیج‌ها')
            ->assertSee('محصولات')
            ->assertSee('لایسنس‌ها');
    }

    public function test_packages_tab_shows_counts_and_expandable_details(): void
    {
        $package = $this->makePackage(['name' => 'پکیج نمای کامل']);

        // سه نسخه: دو فعال + یک پیش‌نویس
        $this->makePackageVersion($package, ['version' => '1.0.0']);
        $this->makePackageVersion($package, ['version' => '1.1.0', 'type' => 'minor']);
        $this->makePackageVersion($package, ['version' => '0.9.0', 'status' => 'draft']);

        // دو طرح قیمت فعال: یکی اشتراکی، یکی یک‌بارمصرف
        $this->makePlan($package, ['name' => 'طرح سالانه', 'price' => 900000]);
        $this->makePlan($package, ['name' => 'طرح یک‌بارمصرف', 'price' => 500000, 'duration_months' => 0, 'is_one_time' => true]);

        Livewire::actingAs($this->admin)
            ->test(InventoryIndex::class)
            // خلاصهٔ ردیف: تعداد نسخه‌ها و طرح‌های قیمت (چیپ نام طرح‌ها هم ردیف بسته دیده می‌شود)
            ->assertSee('پکیج نمای کامل')
            ->assertSee('۳ نسخه')
            ->assertSee('۲ طرح قیمت')
            // نسخهٔ پیش‌نویس فقط داخل ردیف باز‌شونده دیده می‌شود
            ->assertDontSee('0.9.0')
            ->call('toggleExpand', $package->id)
            ->assertSee('1.0.0')
            ->assertSee('1.1.0')
            ->assertSee('0.9.0')
            ->assertSee('طرح سالانه')
            ->assertSee('طرح یک‌بارمصرف')
            // بستن ردیف → جزئیات نسخه‌ها مخفی
            ->call('toggleExpand', $package->id)
            ->assertDontSee('0.9.0');
    }

    public function test_packages_search_filters_by_name(): void
    {
        $this->makePackage(['name' => 'پکیج جستجو']);
        $this->makePackage(['name' => 'پکیج دیگر']);

        Livewire::actingAs($this->admin)
            ->test(InventoryIndex::class)
            ->set('search', 'جستجو')
            ->assertSee('پکیج جستجو')
            ->assertDontSee('پکیج دیگر');
    }

    public function test_products_tab_shows_versions_and_expandable_details(): void
    {
        $product = $this->makeProduct(['name' => 'محصول نمای کامل', 'price' => 80000, 'discount' => 20000]);
        $this->makeProductVersion($product, ['version' => '2.0.0']);
        $this->makeProductVersion($product, ['version' => '2.1.0', 'type' => 'minor']);

        Livewire::actingAs($this->admin)
            ->test(InventoryIndex::class)
            ->call('switchTab', 'products')
            ->assertSee('محصول نمای کامل')
            ->assertSee('۲ نسخه')
            // نسخهٔ قدیمی فقط داخل ردیف باز‌شونده دیده می‌شود
            ->assertDontSee('2.0.0')
            ->call('toggleExpand', $product->id)
            ->assertSee('2.0.0')
            ->assertSee('2.1.0');
    }

    public function test_licenses_tab_lists_package_licenses_and_links_to_show_page(): void
    {
        $package = $this->makePackage(['name' => 'پکیج لایسنس‌دار']);
        $customer = $this->makeCustomer(['name' => 'مشتری لایسنس']);

        $license = PackageLicense::create([
            'license_key' => 'INVTEST-KEY-0001',
            'package_id' => $package->id,
            'customer_id' => $customer->id,
            'status' => PackageLicense::STATUS_ACTIVE,
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(InventoryIndex::class)
            ->call('switchTab', 'licenses')
            ->assertSee('INVTEST-KEY-0001')
            ->assertSee('پکیج لایسنس‌دار')
            ->assertSee('مشتری لایسنس')
            // پیوند جزئیات باید مستقیم به صفحهٔ لایسنس باشد (PackageLicense)
            ->assertSeeHtml(route('admin.licenses.show', $license));
    }

    public function test_licenses_tab_template_type_links_to_licenses_page_not_show(): void
    {
        $template = Template::create([
            'name' => 'قالب نمای کامل',
            'slug' => 'tpl-inventory-'.uniqid(),
            'price' => 200000,
            'status' => Template::STATUS_ACTIVE,
        ]);

        $customer = $this->makeCustomer();

        $license = TemplateLicense::create([
            'license_key' => 'TPLINV-KEY-0001',
            'template_id' => $template->id,
            'customer_id' => $customer->id,
            'status' => TemplateLicense::STATUS_ACTIVE,
            'starts_at' => now(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(InventoryIndex::class)
            ->call('switchTab', 'licenses')
            ->call('switchLicenseType', 'template')
            ->assertSee('TPLINV-KEY-0001')
            ->assertSee('قالب نمای کامل')
            // ⚠️ لایسنس قالب نباید به admin.licenses.show برود (جدول دیگر = تداخل شناسه)
            ->assertSeeHtml('type=template')
            ->assertSeeHtml('search=TPLINV-KEY-0001');
    }
}
