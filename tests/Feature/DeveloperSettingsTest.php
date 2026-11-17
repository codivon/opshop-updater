<?php

namespace Tests\Feature;

use App\Livewire\Settings\Developer;
use App\Models\User;
use App\Support\EnvEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * تنظیمات برنامه‌نویس (/admin/settings/developer):
 *
 * - دسترسی فقط برای نقش admin (حتی با قفل نقش‌های خاموش)
 * - محیط برنامه و دیباگ → نوشتن واقعی در .env (در تست با فایل موقت)
 * - پاک‌سازی کش‌ها و بهینه‌سازی
 * - بخش مایگریشن‌های در انتظار: فقط وقتی مایگریشن جدیدی در جدول ثبت نشده باشد
 *   نمایش داده می‌شود و دکمهٔ اجرا (php artisan migrate) آن را با سیدرها اجرا می‌کند.
 */
class DeveloperSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'مدیر آزمون',
            'email' => 'admin@developer.test',
            'password' => 'secret123',
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    /* -------------------------------------------------------------- */
    /*  دسترسی و رندر */
    /* -------------------------------------------------------------- */

    public function test_page_renders_for_admin(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.settings.developer'))
            ->assertOk()
            ->assertSee('تنظیمات برنامه‌نویس')
            ->assertSee('محیط برنامه')
            ->assertSee('حالت توسعه‌ای')
            ->assertSee('پاک‌سازی کش‌ها')
            ->assertSee('بهینه‌سازی سرعت سایت')
            // نوار وضعیت سیستم
            ->assertSee('نسخهٔ PHP')
            ->assertSee('نسخهٔ لاراول');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.settings.developer'))->assertRedirect(route('login'));
    }

    public function test_operator_role_cannot_access(): void
    {
        $operator = User::create([
            'name' => 'اپراتور آزمون',
            'email' => 'operator@developer.test',
            'password' => 'secret123',
            'role' => 'operator',
            'status' => 'active',
        ]);

        $this->actingAs($operator)
            ->get(route('admin.settings.developer'))
            ->assertForbidden();
    }

    /* -------------------------------------------------------------- */
    /*  بخش مایگریشن‌ها */
    /* -------------------------------------------------------------- */

    public function test_migrations_section_is_hidden_when_everything_is_migrated(): void
    {
        // با RefreshDatabase همهٔ مایگریشن‌ها اجرا شده‌اند → بخش باید مخفی باشد
        // (زیرعنوان هدر صفحه عبارت کلی «مایگریشن‌های در انتظار» را دارد؛
        //  پس فقط متن‌های مخصوص خودِ بخش شرطی بررسی می‌شوند)
        $this->actingAs($this->admin)
            ->get(route('admin.settings.developer'))
            ->assertOk()
            ->assertDontSee('اجرای مایگریشن (php artisan migrate)')
            ->assertDontSee('مایگریشن در انتظار اجرا');
    }

    public function test_pending_migration_is_listed_and_migrate_action_runs_it_with_seeders(): void
    {
        $this->actingAs($this->admin);

        $file = database_path('migrations/2026_01_01_000000_create_developer_test_demo_table.php');

        file_put_contents($file, <<<'PHP'
            <?php

            use Illuminate\Database\Migrations\Migration;
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;

            return new class extends Migration
            {
                public function up(): void
                {
                    Schema::create('developer_test_demo', function (Blueprint $table) {
                        $table->id();
                        $table->string('title');
                        $table->timestamps();
                    });
                }

                public function down(): void
                {
                    Schema::dropIfExists('developer_test_demo');
                }
            };
            PHP);

        try {
            // فایل جدید باید در لیست «در انتظار» نمایش داده شود
            Livewire::test(Developer::class)
                ->assertSee('مایگریشن‌های در انتظار')
                ->assertSee('2026_01_01_000000_create_developer_test_demo_table');

            // اجرا با سیدرها
            Livewire::test(Developer::class)
                ->set('runSeeders', true)
                ->call('runMigrations')
                ->assertDispatched('toast');

            // جدول ساخته شد و در جدول migrations ثبت شد
            $this->assertTrue(Schema::hasTable('developer_test_demo'));
            $this->assertDatabaseHas('migrations', [
                'migration' => '2026_01_01_000000_create_developer_test_demo_table',
            ]);

            // سیدرها هم اجرا شدند (کاربر پیش‌فرض DatabaseSeeder)
            $this->assertDatabaseHas('users', ['email' => 'test@example.com']);

            // پس از اجرا، بخش باید مخفی شود
            $this->assertSame([], Livewire::test(Developer::class)->instance()->pendingMigrations);
        } finally {
            @unlink($file);
        }
    }

    /* -------------------------------------------------------------- */
    /*  محیط برنامه و دیباگ (.env — با فایل موقت) */
    /* -------------------------------------------------------------- */

    public function test_save_environment_writes_env_file_and_runtime_config(): void
    {
        $this->actingAs($this->admin);

        $path = $this->fakeEnv("APP_NAME=Test\nAPP_ENV=local\nAPP_DEBUG=true\n");
        $this->swapEnvEditor($path);

        Livewire::test(Developer::class)
            ->set('environment', 'production')
            ->call('saveEnvironment')
            ->assertDispatched('toast');

        $this->assertStringContainsString('APP_ENV=production', (string) file_get_contents($path));
        $this->assertSame('production', config('app.env'));

        @unlink($path);
    }

    public function test_save_environment_rejects_invalid_value(): void
    {
        $this->actingAs($this->admin);

        $path = $this->fakeEnv("APP_ENV=local\n");
        $this->swapEnvEditor($path);

        Livewire::test(Developer::class)
            ->set('environment', 'hacker-env')
            ->call('saveEnvironment')
            ->assertDispatched('toast');

        $this->assertStringContainsString('APP_ENV=local', (string) file_get_contents($path));

        @unlink($path);
    }

    public function test_save_debug_writes_env_file(): void
    {
        $this->actingAs($this->admin);

        $path = $this->fakeEnv("APP_ENV=local\nAPP_DEBUG=true\n");
        $this->swapEnvEditor($path);

        Livewire::test(Developer::class)
            ->set('debug', false)
            ->call('saveDebug')
            ->assertDispatched('toast');

        $this->assertStringContainsString('APP_DEBUG=false', (string) file_get_contents($path));
        $this->assertFalse(config('app.debug'));

        @unlink($path);
    }

    public function test_mount_reads_current_values_from_config(): void
    {
        $this->actingAs($this->admin);

        // mount از config می‌خواند (نه از فایل .env) — در تست APP_ENV=testing است
        $component = Livewire::test(Developer::class);

        $this->assertSame(config('app.env'), $component->get('environment'));
        $this->assertSame((bool) config('app.debug'), $component->get('debug'));
        $this->assertTrue($component->get('runSeeders'));
    }

    /* -------------------------------------------------------------- */
    /*  کش‌ها و بهینه‌سازی */
    /* -------------------------------------------------------------- */

    public function test_clear_cache_single_target_runs(): void
    {
        $this->actingAs($this->admin);

        Cache::put('developer-test-key', 'value', 60);

        Livewire::test(Developer::class)
            ->call('clearCache', 'view')
            ->assertDispatched('toast');
    }

    public function test_clear_cache_all_flushes_app_cache(): void
    {
        $this->actingAs($this->admin);

        Cache::put('developer-test-key', 'value', 60);

        Livewire::test(Developer::class)
            ->call('clearCache', 'all')
            ->assertDispatched('toast');

        $this->assertNull(Cache::get('developer-test-key'));
    }

    public function test_clear_cache_rejects_invalid_target(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(Developer::class)
            ->call('clearCache', 'bogus-target')
            ->assertDispatched('toast');
    }

    /* -------------------------------------------------------------- */
    /*  نشست ازدست‌رفته (Task 73) — مهمان به‌جای 403 به ورود برمی‌گردد */
    /* -------------------------------------------------------------- */

    public function test_guest_action_redirects_to_login_instead_of_403(): void
    {
        // شبیه‌سازی «تب باز + سشن مرده»: کامپوننت با کاربر لاگین‌کرده بالا
        // می‌آید، سپس سشن/کاربر از بین می‌رود و اکشن با مهمان اجرا می‌شود —
        // قبلاً این حالت 403 گمراه‌کننده می‌داد، حالا به ورود برمی‌گردد.
        $this->actingAs($this->admin);

        $component = Livewire::test(Developer::class);

        auth()->logout();

        $component->call('clearCache', 'all')
            ->assertRedirect(route('login'));

        // اکشن‌های دیگر هم همین رفتار را دارند (هر بار بوت تازه + خروج)
        foreach (['runMigrations', 'saveDebug', 'saveEnvironment', 'optimize'] as $action) {
            $this->actingAs($this->admin);

            $component = Livewire::test(Developer::class);
            auth()->logout();

            $component->call($action)
                ->assertRedirect(route('login'));
        }

        // خودِ رندر اولیهٔ مهمان هم 403 نیست — redirect است
        $this->actingAs($this->admin);
        auth()->logout();
        $this->get(route('admin.settings.developer'))
            ->assertRedirect(route('login'));
    }

    /** پاک‌سازی کش نباید احراز هویت کاربر جاری را بشکند (نجات سشن در runCacheCommand) */
    public function test_cache_clear_keeps_current_session_alive(): void
    {
        $this->actingAs($this->admin);

        // cache:clear — خطرناک‌ترین هدف برای سشن (استور مشترک)
        Livewire::test(Developer::class)
            ->call('clearCache', 'app')
            ->assertDispatched('toast')
            ->assertNoRedirect();

        // کاربر همچنان احراز هویت است (برخلاف حالت مهمان که redirect می‌گیرد)
        $this->assertAuthenticatedAs($this->admin);

        // اکشن «همهٔ کش‌ها» هم وضعیت لاگین را نمی‌شکند
        Livewire::test(Developer::class)
            ->call('clearCache', 'all')
            ->assertDispatched('toast');

        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_optimize_runs(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(Developer::class)
            ->call('optimize')
            ->assertDispatched('toast');
    }

    /* -------------------------------------------------------------- */
    /*  تب «لاگ‌های سیستم» — نمایشگر لاگ + کپی کامل رکورد (Task 86) */
    /* -------------------------------------------------------------- */

    public function test_logs_tab_renders_entries_from_laravel_log(): void
    {
        // فایل لاگ آزمون با دو رکورد شناخته‌شده
        $testLog = storage_path('logs/laravel.log');
        File::ensureDirectoryExists(dirname($testLog));
        File::put($testLog,
            "[2026-11-17 10:00:00] production.ERROR: خطای آزمونی خرید\r".
            '{"purchase_id":999,"error":"قابل تست"} '.
            "\n#0 /path/one.php(11): App\\Services\\PaymentService->createPayment()\n".
            "#1 /path/two.php(22): Shetabit\\Multipay\\Payment->purchase()\n\n".
            "[2026-11-17 10:05:00] production.INFO: پیام اطلاعاتی آزمونی\n\n"
        );

        try {
            $this->actingAs($this->admin)
                ->get(route('admin.settings.developer'))
                ->assertOk()
                ->assertSee('لاگ‌های سیستم')
                ->assertSee('laravel.log')
                ->assertSee('خطای آزمونی خرید')
                ->assertSee('پیام اطلاعاتی آزمونی')
                // کلید کپی کامل رکورد
                ->assertSee('کپی کامل رکورد')
                // استک‌تریس کامل در رکورد بازشو برای کپی
                ->assertSee('#0 /path/one.php(11)');
        } finally {
            File::delete($testLog);
        }
    }

    public function test_logs_tab_search_filters_entries(): void
    {
        $testLog = storage_path('logs/laravel.log');
        File::ensureDirectoryExists(dirname($testLog));
        File::put($testLog,
            "[2026-11-17 10:00:00] production.ERROR: خطای درگاه نوین‌پی\n\n".
            "[2026-11-17 10:05:00] production.ERROR: خطای کش پیکربندی\n\n"
        );

        try {
            $this->actingAs($this->admin);

            Livewire::withQueryParams([])
                ->test(Developer::class)
                ->set('logSearch', 'نوین‌پی')
                ->assertSee('خطای درگاه نوین‌پی')
                ->assertDontSee('خطای کش پیکربندی');

            // فیلتر سطح
            Livewire::test(Developer::class)
                ->set('logLevel', 'error')
                ->assertSee('خطای درگاه نوین‌پی')
                // «همه سطوح» در حالت سطح خالی — با فیلتر error فقط دو رکورد error هستند
                ->assertDontSee('پیام اطلاعاتی');
        } finally {
            File::delete($testLog);
        }
    }

    public function test_clear_log_action_empties_log_file(): void
    {
        $testLog = storage_path('logs/laravel.log');
        File::ensureDirectoryExists(dirname($testLog));
        File::put($testLog, "[2026-11-17 10:00:00] production.ERROR: رکورد برای پاک‌سازی\n\n");

        try {
            $this->actingAs($this->admin);

            Livewire::test(Developer::class)
                ->set('confirmClearLog', true)
                ->call('clearLog')
                ->assertDispatched('toast')
                ->assertSet('confirmClearLog', false);

            $this->assertSame('', File::get($testLog));
        } finally {
            File::delete($testLog);
        }
    }

    public function test_guest_cannot_clear_log(): void
    {
        $testLog = storage_path('logs/laravel.log');
        File::ensureDirectoryExists(dirname($testLog));
        File::put($testLog, "[2026-11-17 10:00:00] production.ERROR: رکورد محافظت‌شده\n\n");

        try {
            // الگوی «تب باز + سشن مرده»: بوت با ادمین، خروج، سپس اکشن — باید redirect بگیرد نه پاک‌سازی
            $this->actingAs($this->admin);

            $component = Livewire::test(Developer::class);
            auth()->logout();

            $component->call('clearLog')
                ->assertRedirect(route('login'));

            $this->assertStringContainsString('رکورد محافظت‌شده', File::get($testLog));
        } finally {
            File::delete($testLog);
        }
    }

    /* -------------------------------------------------------------- */
    /*  هلپرها */
    /* -------------------------------------------------------------- */

    /** ساخت فایل .env موقت با محتوای داده‌شده */
    private function fakeEnv(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'opshop-env-').'.env';
        file_put_contents($path, $content);

        return $path;
    }

    /** جایگزینی EnvEditor با نسخهٔ فایل موقت تا .env واقعی پروژه دست‌نخورده بماند */
    private function swapEnvEditor(string $path): void
    {
        $this->app->instance(EnvEditor::class, new EnvEditor($path));
    }
}
