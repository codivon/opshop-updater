<?php

namespace Tests\Feature;

use App\Livewire\Templates\Index as TemplatesIndex;
use App\Livewire\Templates\Show as TemplatesShow;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

/**
 * Task 94 — قالب‌ها به پروژه گره می‌خورند:
 *
 *  - فرم افزودن/ویرایش قالب در پنل، انتخاب پروژه را «اجباری» می‌گیرد (مثل فرم پکیج‌ها)
 *  - تک‌پروژه → از قبل انتخاب است؛ فیلتر لیست بر اساس پروژه کار می‌کند
 *  - ویرایش قالب از صفحهٔ جزئیات هم پروژه را به‌روز می‌کند
 *  - خرید API قالب فقط برای قالب‌های پروژه‌ای مجاز است که مشتری اشتراکِ فعال دارد
 */
class TemplateProjectTest extends TestCase
{
    use MakesShopData, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'مدیر قالب',
            'email' => 'admin-templates-'.uniqid().'@example.com',
            'password' => 'password123',
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    /* -------------------------------------------------------------- */
    /*  فرم پنل — انتخاب پروژه اجباری */
    /* -------------------------------------------------------------- */

    public function test_create_requires_project(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(TemplatesIndex::class)
            ->call('create')
            ->set('form.name', 'قالب بدون پروژه')
            ->set('form.status', Template::STATUS_ACTIVE)
            ->call('save')
            ->assertHasErrors(['form.project_id']);
    }

    public function test_single_project_is_preselected_in_create_form(): void
    {
        $project = Project::create(['name' => 'تک‌پروژه', 'slug' => 'single-tpl-'.uniqid(), 'status' => 'active']);

        $this->actingAs($this->admin);

        $component = Livewire::test(TemplatesIndex::class)
            ->call('create');

        $this->assertSame((string) $project->id, $component->get('form.project_id'));
    }

    public function test_template_is_created_with_selected_project(): void
    {
        $project = Project::create(['name' => 'پروژهٔ قالب', 'slug' => 'tpl-proj-'.uniqid(), 'status' => 'active']);

        $this->actingAs($this->admin);

        Livewire::test(TemplatesIndex::class)
            ->call('create')
            ->set('form.name', 'قالب فروشگاهی')
            ->set('form.project_id', (string) $project->id)
            ->set('form.status', Template::STATUS_ACTIVE)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $template = Template::where('name', 'قالب فروشگاهی')->first();
        $this->assertNotNull($template);
        $this->assertSame($project->id, $template->project_id);
        $this->assertSame('پروژهٔ قالب', $template->project?->name);
    }

    public function test_edit_form_loads_and_updates_project(): void
    {
        $project = Project::create(['name' => 'پروژهٔ اول', 'slug' => 'proj-a-'.uniqid(), 'status' => 'active']);
        $other = Project::create(['name' => 'پروژهٔ دوم', 'slug' => 'proj-b-'.uniqid(), 'status' => 'active']);

        $template = Template::create([
            'project_id' => $project->id,
            'name' => 'قالب موجود',
            'slug' => 'existing-'.uniqid(),
            'status' => Template::STATUS_ACTIVE,
        ]);

        $this->actingAs($this->admin);

        Livewire::test(TemplatesIndex::class)
            ->call('edit', $template->id)
            ->assertSet('form.project_id', (string) $project->id)
            ->set('form.project_id', (string) $other->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($other->id, $template->fresh()->project_id);
    }

    public function test_list_filter_by_project(): void
    {
        $project = Project::create(['name' => 'پروژهٔ فیلتر', 'slug' => 'proj-f-'.uniqid(), 'status' => 'active']);
        $other = Project::create(['name' => 'پروژهٔ دیگر', 'slug' => 'proj-g-'.uniqid(), 'status' => 'active']);

        $mine = Template::create(['project_id' => $project->id, 'name' => 'قالب من', 'slug' => 'mine-'.uniqid(), 'status' => Template::STATUS_ACTIVE]);
        Template::create(['project_id' => $other->id, 'name' => 'قالب دیگری', 'slug' => 'other-'.uniqid(), 'status' => Template::STATUS_ACTIVE]);

        $this->actingAs($this->admin);

        Livewire::test(TemplatesIndex::class)
            ->set('projectFilter', (string) $project->id)
            ->assertSee('قالب من')
            ->assertDontSee('قالب دیگری');
    }

    /* -------------------------------------------------------------- */
    /*  ویرایش از صفحهٔ جزئیات */
    /* -------------------------------------------------------------- */

    public function test_show_page_edit_updates_project(): void
    {
        $project = Project::create(['name' => 'پروژهٔ A', 'slug' => 'pa-'.uniqid(), 'status' => 'active']);
        $other = Project::create(['name' => 'پروژهٔ B', 'slug' => 'pb-'.uniqid(), 'status' => 'active']);

        $template = Template::create([
            'project_id' => $project->id,
            'name' => 'قالب جزئیات',
            'slug' => 'show-'.uniqid(),
            'status' => Template::STATUS_ACTIVE,
        ]);

        $this->actingAs($this->admin);

        Livewire::test(TemplatesShow::class, ['template' => $template])
            ->call('openEditTemplate')
            ->assertSet('form.project_id', (string) $project->id)
            ->set('form.project_id', (string) $other->id)
            ->call('saveTemplate')
            ->assertHasNoErrors();

        $this->assertSame($other->id, $template->fresh()->project_id);
    }

    public function test_show_page_edit_requires_project(): void
    {
        $template = Template::create([
            'name' => 'قالب بدون پروژهٔ قدیمی',
            'slug' => 'legacy-'.uniqid(),
            'status' => Template::STATUS_ACTIVE,
        ]);

        $this->actingAs($this->admin);

        Livewire::test(TemplatesShow::class, ['template' => $template])
            ->call('openEditTemplate')
            ->assertSet('form.project_id', '')
            ->call('saveTemplate')
            ->assertHasErrors(['form.project_id']);
    }

    /* -------------------------------------------------------------- */
    /*  خرید API قالب — گیت پروژه */
    /* -------------------------------------------------------------- */

    private function apiHeaders(Customer $customer): array
    {
        return [
            'Authorization' => 'Bearer '.$customer->update_code,
            'X-Project-Url' => $customer->website_url,
            'Accept' => 'application/json',
        ];
    }

    public function test_api_purchase_of_other_project_template_is_forbidden(): void
    {
        $project = Project::create(['name' => 'پروژهٔ خریدار', 'slug' => 'buyer-'.uniqid(), 'status' => 'active']);
        $other = Project::create(['name' => 'پروژهٔ دیگر', 'slug' => 'other-'.uniqid(), 'status' => 'active']);

        $template = Template::create([
            'project_id' => $other->id,
            'name' => 'قالب پروژهٔ دیگر',
            'slug' => 'other-tpl-'.uniqid(),
            'is_free' => true,
            'status' => Template::STATUS_ACTIVE,
        ]);

        $customer = $this->makeCustomer();
        Subscription::create([
            'customer_id' => $customer->id,
            'project_id' => $project->id, // اشتراک فقط برای پروژهٔ خودش
            'status' => 'active',
            'payment_status' => 'paid',
            'start_date' => now()->toDateString(),
            'expires_at' => now()->addMonth()->toDateString(),
            'price' => 0,
            'final_amount' => 0,
        ]);

        $this->postJson('/api/v1/templates/'.$template->slug.'/purchase', [], $this->apiHeaders($customer))
            ->assertStatus(403)
            ->assertJsonPath('error', 'دسترسی غیرمجاز؛ اشتراکِ فعالِ این پروژه را ندارید.');
    }

    public function test_api_purchase_of_own_project_template_works(): void
    {
        $project = Project::create(['name' => 'پروژهٔ خریدار', 'slug' => 'buyer-'.uniqid(), 'status' => 'active']);

        $template = Template::create([
            'project_id' => $project->id,
            'name' => 'قالب رایگان پروژه',
            'slug' => 'own-tpl-'.uniqid(),
            'is_free' => true,
            'status' => Template::STATUS_ACTIVE,
        ]);
        $template->versions()->create([
            'version' => '1.0.0',
            'type' => 'major',
            'file_path' => 'templates/'.$template->slug.'/v1.zip',
            'file_size' => 1024,
            'status' => TemplateVersion::STATUS_ACTIVE,
        ]);

        $customer = $this->makeCustomer();
        Subscription::create([
            'customer_id' => $customer->id,
            'project_id' => $project->id,
            'status' => 'active',
            'payment_status' => 'paid',
            'start_date' => now()->toDateString(),
            'expires_at' => now()->addMonth()->toDateString(),
            'price' => 0,
            'final_amount' => 0,
        ]);

        // لیست فقط قالب‌های همین پروژه را می‌بیند
        $this->getJson('/api/v1/templates', $this->apiHeaders($customer))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.slug', $template->slug)
            ->assertJsonPath('data.0.project.id', $project->id);

        // خرید رایگان قالبِ همان پروژه کار می‌کند
        $this->postJson('/api/v1/templates/'.$template->slug.'/purchase', [], $this->apiHeaders($customer))
            ->assertOk()
            ->assertJsonPath('is_free', true);

        $this->assertDatabaseHas('template_purchases', [
            'customer_id' => $customer->id,
            'template_id' => $template->id,
        ]);
    }
}
