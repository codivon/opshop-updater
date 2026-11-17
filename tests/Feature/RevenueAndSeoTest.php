<?php

namespace Tests\Feature;

use App\Livewire\Revenue\Index;
use App\Models\Customer;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\Project;
use App\Models\Template;
use App\Models\TemplatePurchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Task 85 — رگرسیون: صفحهٔ درآمد + سئوی خودکار + توکن محرمانهٔ خرید.
 */
class RevenueAndSeoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Project $project;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'مدیر آزمون',
            'email' => 'admin@revenue.test',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->project = Project::create(['name' => 'پروژهٔ آزمون', 'slug' => 'test-project']);

        $this->customer = Customer::create([
            'name' => 'مشتری آزمون',
            'email' => 'cust@revenue.test',
            'website_url' => 'https://example.com',
            'update_code' => 'UPD-TEST-1234',
        ]);
    }

    /* -------------------------------------------------------------- */
    /*  صفحهٔ درآمد */
    /* -------------------------------------------------------------- */

    public function test_revenue_page_renders_for_admin(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(Index::class)
            ->assertOk()
            ->assertSee('درآمد')
            ->assertSee('تفکیک منابع درآمد');
    }

    public function test_revenue_page_counts_paid_purchases(): void
    {
        $package = Package::create([
            'name' => 'پکیج آزمون',
            'slug' => 'test-package',
            'project_id' => $this->project->id,
            'status' => 'active',
            'sort_order' => 0,
        ]);

        PackagePurchase::create([
            'package_id' => $package->id,
            'customer_id' => $this->customer->id,
            'amount' => 150000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        PackagePurchase::create([
            'package_id' => $package->id,
            'customer_id' => $this->customer->id,
            'amount' => 50000,
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin);

        Livewire::test(Index::class, ['period' => 'all'])
            ->assertOk()
            ->assertSee('150,000');
    }

    /* -------------------------------------------------------------- */
    /*  توکن محرمانهٔ خرید (C3/C4) */
    /* -------------------------------------------------------------- */

    public function test_payment_result_requires_valid_token(): void
    {
        $package = Package::create([
            'name' => 'پکیج توکن',
            'slug' => 'token-package',
            'project_id' => $this->project->id,
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $purchase = PackagePurchase::create([
            'package_id' => $package->id,
            'customer_id' => $this->customer->id,
            'amount' => 120000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->assertNotNull($purchase->fresh()->access_token);

        // بدون توکن → 404
        $this->get('/payment/result/'.$purchase->id)->assertNotFound();

        // توکن اشتباه → 404
        $this->get('/payment/result/'.$purchase->id.'/'.str_repeat('x', 64))->assertNotFound();

        // توکن درست → 200
        $this->get('/payment/result/'.$purchase->id.'/'.$purchase->access_token)->assertOk();
    }

    public function test_template_payment_result_requires_valid_token(): void
    {
        $template = Template::create([
            'name' => 'قالب توکن',
            'slug' => 'token-template',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $purchase = TemplatePurchase::create([
            'template_id' => $template->id,
            'amount' => 90000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->get('/payment/result/template/'.$purchase->id)->assertNotFound();

        $this->get('/payment/result/template/'.$purchase->id.'/'.$purchase->access_token)->assertOk();
    }

    /* -------------------------------------------------------------- */
    /*  فرمان سئو (seo:fill) */
    /* -------------------------------------------------------------- */

    public function test_seo_fill_command_fills_known_and_unknown_slugs(): void
    {
        $package = Package::create([
            'name' => 'پرداخت‌یار',
            'slug' => 'PayYar',
            'project_id' => $this->project->id,
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $unknown = Package::create([
            'name' => 'پکیج ناشناخته',
            'slug' => 'unknown-pkg',
            'project_id' => $this->project->id,
            'status' => 'active',
            'sort_order' => 0,
            'short_description' => 'توضیح کوتاه برای تست تولید خودکار متن سئو.',
        ]);

        $this->artisan('seo:fill')->assertSuccessful();

        $package->refresh();
        $unknown->refresh();

        // متن دقیق برای اسلاگ شناخته‌شده
        $this->assertSame('خرید پکیج پرداخت‌یار شاپ‌پرو | درگاه پرداخت چندگانه و اقساطی', $package->meta_title);

        // متن تولیدی برای اسلاگ ناشناخته
        $this->assertStringContainsString('پکیج ناشناخته', $unknown->meta_title);
        $this->assertNotEmpty($unknown->meta_description);

        // اجرای مجدد بدون --force → رکورد قبلاً پر است و دست نمی‌خورد
        $this->artisan('seo:fill')->assertSuccessful();
        $package->refresh();
        $this->assertSame('خرید پکیج پرداخت‌یار شاپ‌پرو | درگاه پرداخت چندگانه و اقساطی', $package->meta_title);
    }
}
