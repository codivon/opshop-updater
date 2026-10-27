<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Package;
use App\Models\PackageDownloadToken;
use App\Models\PackageLicense;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\Template;
use App\Models\TemplateDownloadToken;
use App\Models\TemplatePurchase;
use App\Models\TemplateVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

/**
 * گیت اشتراک در API (درخواست کاربر):
 *
 *  - لیست قالب‌ها: بدون اشتراکِ فعال/پرداخت‌شده/منقضی‌نشده → لیست خالی
 *    (همان رفتار لیست پکیج‌ها که مرجع درست است)
 *  - نمایش تک‌قالب → ۴۰۳ «دسترسی غیرمجاز.» (مثل نمایش تک‌پکیج)
 *  - دانلود قالب (bySlug / token / download-url) → فقط با اشتراک فعال
 *  - دانلود پکیج (bySlug / token / download-url) → فقط با اشتراکِ فعالِ
 *    پوشش‌دهندهٔ پروژهٔ پکیج (حتی با لایسنس فعال یا توکن معتبر)
 */
class ApiSubscriptionGateTest extends TestCase
{
    use RefreshDatabase, MakesShopData;

    /* -------------------------------------------------------------- */
    /*  ابزارهای ساخت داده                                             */
    /* -------------------------------------------------------------- */

    private function apiHeaders(Customer $customer): array
    {
        return [
            'Authorization' => 'Bearer ' . $customer->update_code,
            'X-Project-Url' => $customer->website_url,
            'Accept'        => 'application/json',
        ];
    }

    private function makeTemplate(array $attrs = []): Template
    {
        return Template::create(array_merge([
            'name'              => 'قالب تست ' . uniqid(),
            'slug'              => 'test-template-' . uniqid(),
            'short_description' => 'توضیح کوتاه',
            'description'       => '<p>توضیح کامل</p>',
            'price'             => 500000,
            'is_free'           => false,
            'status'            => Template::STATUS_ACTIVE,
            'sort_order'        => 0,
        ], $attrs));
    }

    private function makeTemplateVersion(Template $template, array $attrs = []): TemplateVersion
    {
        return $template->versions()->create(array_merge([
            'version'   => '1.0.0',
            'type'      => 'major',
            'changelog' => 'انتشار اولیه',
            'file_path' => 'templates/' . $template->slug . '/v' . uniqid() . '.zip',
            'file_size' => 1024,
            'status'    => TemplateVersion::STATUS_ACTIVE,
        ], $attrs));
    }

    private function makePaidTemplatePurchase(Customer $customer, Template $template, TemplateVersion $version): TemplatePurchase
    {
        return TemplatePurchase::create([
            'template_id'   => $template->id,
            'version_id'    => $version->id,
            'customer_id'   => $customer->id,
            'purchase_type' => Template::PURCHASE_NORMAL,
            'amount'        => 500000,
            'status'        => TemplatePurchase::STATUS_PAID,
            'paid_at'       => now(),
        ]);
    }

    private function makeSubscription(Customer $customer, int $projectId, array $attrs = []): Subscription
    {
        return Subscription::create(array_merge([
            'customer_id'    => $customer->id,
            'project_id'     => $projectId,
            'status'         => 'active',
            'payment_status' => 'paid',
            'start_date'     => now()->toDateString(),
            'expires_at'     => now()->addMonth()->toDateString(),
            'price'          => 0,
            'discount'       => 0,
            'final_amount'   => 0,
        ], $attrs));
    }

    private function makeActiveLicense(Customer $customer, Package $package): PackageLicense
    {
        return PackageLicense::create([
            'license_key' => 'LIC-' . strtoupper(uniqid()),
            'package_id'  => $package->id,
            'customer_id' => $customer->id,
            'status'      => PackageLicense::STATUS_ACTIVE,
            'starts_at'   => now(),
            'expires_at'  => now()->addYear(),
        ]);
    }

    private function seedRealVersionFile(string $filePath): void
    {
        Storage::fake('local');
        Storage::disk('local')->put($filePath, "FAKE-ZIP-CONTENT");
    }

    /* -------------------------------------------------------------- */
    /*  لیست قالب‌ها — مثل لیست پکیج‌ها                                 */
    /* -------------------------------------------------------------- */

    public function test_templates_list_is_empty_without_any_subscription(): void
    {
        $this->makeTemplate();
        $customer = $this->makeCustomer();

        $this->getJson('/api/v1/templates', $this->apiHeaders($customer))
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');
    }

    public function test_templates_list_is_empty_with_expired_subscription(): void
    {
        $this->makeTemplate();
        $customer = $this->makeCustomer();
        $project  = Project::create(['name' => 'پ', 'slug' => 'p-' . uniqid()]);

        $this->makeSubscription($customer, $project->id, ['expires_at' => now()->subDay()->toDateString()]);

        $this->getJson('/api/v1/templates', $this->apiHeaders($customer))
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');
    }

    public function test_templates_list_is_empty_with_unpaid_subscription(): void
    {
        $this->makeTemplate();
        $customer = $this->makeCustomer();
        $project  = Project::create(['name' => 'پ', 'slug' => 'p-' . uniqid()]);

        $this->makeSubscription($customer, $project->id, ['payment_status' => 'pending']);

        $this->getJson('/api/v1/templates', $this->apiHeaders($customer))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_templates_list_returns_templates_with_active_subscription(): void
    {
        $template = $this->makeTemplate();
        $customer = $this->makeCustomer();
        $project  = Project::create(['name' => 'پ', 'slug' => 'p-' . uniqid()]);

        $this->makeSubscription($customer, $project->id);

        $this->getJson('/api/v1/templates', $this->apiHeaders($customer))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.slug', $template->slug);
    }

    /* -------------------------------------------------------------- */
    /*  نمایش تک‌قالب — مثل نمایش تک‌پکیج                              */
    /* -------------------------------------------------------------- */

    public function test_template_show_is_forbidden_without_active_subscription(): void
    {
        $template = $this->makeTemplate();
        $customer = $this->makeCustomer();

        $this->getJson('/api/v1/templates/' . $template->slug, $this->apiHeaders($customer))
            ->assertStatus(403)
            ->assertJsonPath('error', 'دسترسی غیرمجاز.');
    }

    public function test_template_show_works_with_active_subscription(): void
    {
        $template = $this->makeTemplate();
        $this->makeTemplateVersion($template);
        $customer = $this->makeCustomer();
        $project  = Project::create(['name' => 'پ', 'slug' => 'p-' . uniqid()]);

        $this->makeSubscription($customer, $project->id);

        $this->getJson('/api/v1/templates/' . $template->slug, $this->apiHeaders($customer))
            ->assertOk()
            ->assertJsonPath('data.slug', $template->slug);
    }

    /* -------------------------------------------------------------- */
    /*  دانلود قالب                                                    */
    /* -------------------------------------------------------------- */

    public function test_template_download_by_slug_requires_active_subscription(): void
    {
        $template = $this->makeTemplate();
        $version  = $this->makeTemplateVersion($template);
        $this->seedRealVersionFile($version->file_path);

        $customer = $this->makeCustomer();
        $this->makePaidTemplatePurchase($customer, $template, $version);

        // خرید پرداخت‌شده هست ولی اشتراک فعال نیست → ممنوع
        $this->getJson('/api/v1/templates/' . $template->slug . '/download', $this->apiHeaders($customer))
            ->assertStatus(403)
            ->assertJsonPath('error', 'اشتراک فعالی وجود ندارد؛ دانلود قالب فقط با اشتراک فعال امکان‌پذیر است.');

        // با اشتراک فعال → دانلود موفق
        $project = Project::create(['name' => 'پ', 'slug' => 'p-' . uniqid()]);
        $this->makeSubscription($customer, $project->id);

        $this->get('/api/v1/templates/' . $template->slug . '/download', $this->apiHeaders($customer))
            ->assertOk()
            ->assertHeader('X-Template-Version', '1.0.0');
    }

    public function test_template_download_url_issue_requires_active_subscription(): void
    {
        $template = $this->makeTemplate();
        $version  = $this->makeTemplateVersion($template);
        $this->seedRealVersionFile($version->file_path);

        $customer = $this->makeCustomer();
        $this->makePaidTemplatePurchase($customer, $template, $version);

        $this->postJson('/api/v1/templates/' . $template->slug . '/download-url', [], $this->apiHeaders($customer))
            ->assertStatus(403)
            ->assertJsonPath('error', 'اشتراک فعالی وجود ندارد؛ دانلود قالب فقط با اشتراک فعال امکان‌پذیر است.');

        $project = Project::create(['name' => 'پ', 'slug' => 'p-' . uniqid()]);
        $this->makeSubscription($customer, $project->id);

        $this->postJson('/api/v1/templates/' . $template->slug . '/download-url', [], $this->apiHeaders($customer))
            ->assertOk()
            ->assertJsonStructure(['download_url', 'expires_in_seconds']);
    }

    public function test_template_token_download_requires_active_subscription(): void
    {
        $template = $this->makeTemplate();
        $version  = $this->makeTemplateVersion($template);
        $this->seedRealVersionFile($version->file_path);

        $customer = $this->makeCustomer();
        $this->makePaidTemplatePurchase($customer, $template, $version);

        // توکن یک‌بارمصرفِ معتبر (حالت لینک مستقیم مرورگر — بدون هدرهای احراز)
        $token = TemplateDownloadToken::create([
            'token'               => str_repeat('a', 64),
            'template_id'         => $template->id,
            'template_version_id' => $version->id,
            'customer_id'         => $customer->id,
            'expires_at'          => now()->addMinutes(15),
        ]);

        // حتی با توکن معتبر، بدون اشتراک فعال → ممنوع
        $this->get('/api/v1/templates/download/' . $token->token)
            ->assertStatus(403)
            ->assertJsonPath('error', 'اشتراک فعالی وجود ندارد؛ دانلود قالب فقط با اشتراک فعال امکان‌پذیر است.');

        // توکن مصرف نشده باشد (تست قبلی فقط ۴۰۳ داد، consume نشده)
        $project = Project::create(['name' => 'پ', 'slug' => 'p-' . uniqid()]);
        $this->makeSubscription($customer, $project->id);

        $this->get('/api/v1/templates/download/' . $token->token)
            ->assertOk()
            ->assertHeader('X-Template-Version', '1.0.0');
    }

    /* -------------------------------------------------------------- */
    /*  نمایش و دانلود پکیج — گیت اشتراک پروژه‌ای                       */
    /* -------------------------------------------------------------- */

    public function test_packages_list_is_empty_without_subscription(): void
    {
        $this->makePackage();
        $customer = $this->makeCustomer();

        $this->getJson('/api/v1/packages', $this->apiHeaders($customer))
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');
    }

    public function test_package_show_is_forbidden_without_project_subscription(): void
    {
        $package  = $this->makePackage();
        $this->makePackageVersion($package);
        $customer = $this->makeCustomer();

        $this->getJson('/api/v1/packages/' . $package->slug, $this->apiHeaders($customer))
            ->assertStatus(403)
            ->assertJsonPath('error', 'دسترسی غیرمجاز.');
    }

    public function test_package_download_by_slug_requires_active_project_subscription(): void
    {
        $package  = $this->makePackage();
        $version  = $this->makePackageVersion($package);
        $this->seedRealVersionFile($version->file_path);

        $customer = $this->makeCustomer();
        $this->makeActiveLicense($customer, $package);

        // لایسنس فعال هست ولی اشتراک پروژه فعال نیست → ممنوع
        $this->getJson('/api/v1/packages/' . $package->slug . '/download', $this->apiHeaders($customer))
            ->assertStatus(403)
            ->assertJsonPath('error', 'اشتراک فعالی وجود ندارد؛ دانلود پکیج فقط با اشتراک فعال امکان‌پذیر است.');

        // اشتراک پروژهٔ دیگری → هنوز ممنوع (گیت پروژه‌محور است)
        $otherProject = Project::create(['name' => 'دیگر', 'slug' => 'o-' . uniqid()]);
        $this->makeSubscription($customer, $otherProject->id);

        $this->getJson('/api/v1/packages/' . $package->slug . '/download', $this->apiHeaders($customer))
            ->assertStatus(403);

        // اشتراک پروژهٔ خود پکیج → دانلود موفق
        $this->makeSubscription($customer, $package->project_id);

        $this->get('/api/v1/packages/' . $package->slug . '/download', $this->apiHeaders($customer))
            ->assertOk()
            ->assertHeader('X-Package-Version', '1.0.0');
    }

    public function test_package_download_url_issue_requires_active_project_subscription(): void
    {
        $package  = $this->makePackage();
        $version  = $this->makePackageVersion($package);
        $this->seedRealVersionFile($version->file_path);

        $customer = $this->makeCustomer();
        $this->makeActiveLicense($customer, $package);

        $this->postJson('/api/v1/packages/' . $package->slug . '/download-url', [], $this->apiHeaders($customer))
            ->assertStatus(403)
            ->assertJsonPath('error', 'اشتراک فعالی وجود ندارد؛ دانلود پکیج فقط با اشتراک فعال امکان‌پذیر است.');

        $this->makeSubscription($customer, $package->project_id);

        $this->postJson('/api/v1/packages/' . $package->slug . '/download-url', [], $this->apiHeaders($customer))
            ->assertOk()
            ->assertJsonStructure(['download_url', 'expires_in_seconds']);
    }

    public function test_package_token_download_requires_active_project_subscription(): void
    {
        $package  = $this->makePackage();
        $version  = $this->makePackageVersion($package);
        $this->seedRealVersionFile($version->file_path);

        $customer = $this->makeCustomer();
        $this->makeActiveLicense($customer, $package);

        $token = PackageDownloadToken::create([
            'token'       => PackageDownloadToken::generate(),
            'license_id'  => $this->makeActiveLicense($customer, $package)->id,
            'version_id'  => $version->id,
            'customer_id' => $customer->id,
            'expires_at'  => now()->addMinutes(15),
        ]);

        // حتی با توکن معتبر، بدون اشتراک پروژه → ممنوع
        $this->get('/api/v1/packages/download/' . $token->token)
            ->assertStatus(403)
            ->assertJsonPath('error', 'اشتراک فعالی وجود ندارد؛ دانلود پکیج فقط با اشتراک فعال امکان‌پذیر است.');

        $this->makeSubscription($customer, $package->project_id);

        $this->get('/api/v1/packages/download/' . $token->token)
            ->assertOk()
            ->assertHeader('X-Package-Version', '1.0.0');
    }
}
