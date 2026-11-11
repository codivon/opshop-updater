<?php

namespace App\Livewire\Settings;

use App\Livewire\Concerns\WithToasts;
use App\Support\EnvEditor;
use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * تنظیمات برنامه‌نویس (/admin/settings/developer):
 *
 * - محیط برنامه (APP_ENV) و حالت توسعه‌ای (APP_DEBUG) → مستقیماً در .env نوشته می‌شود
 * - پاک‌سازی کش‌ها (برنامه/پیکربندی/مسیرها/نماها + همه)
 * - بهینه‌سازی سرعت (config/route/view:cache)
 * - مایگریشن‌های در انتظار: این بخش فقط وقتی نمایش داده می‌شود که مایگریشن جدیدی
 *   در database/migrations باشد که هنوز در جدول migrations دیتابیس ثبت نشده است؛
 *   دکمهٔ اجرا (php artisan migrate) جداول/ستون‌های جدید را می‌سازد و در صورت
 *   انتخاب مدیر، سیدرهای دیتابیس (db:seed) هم اجرا می‌شوند.
 *
 * امنیت: علاوه بر میان‌افزار پنل، همهٔ اکشن‌های این صفحه فقط برای نقش admin
 * پاسخ داده می‌شوند (حتی وقتی قفل نقش‌ها در «امنیت و امکانات» خاموش است).
 */
#[Layout('components.layouts.app')]
#[Title('تنظیمات برنامه‌نویس')]
class Developer extends Component
{
    use WithToasts;

    /** مقادیر مجاز APP_ENV (مطابق عرف لاراول — production تنها مقدار «زنده» است) */
    public const ENVIRONMENTS = ['production', 'local', 'develop', 'staging'];

    /** اکشن پاک‌سازی هر کش */
    private const CACHE_TARGETS = [
        'app' => ['command' => 'cache:clear',  'label' => 'کش برنامه'],
        'config' => ['command' => 'config:clear', 'label' => 'کش پیکربندی'],
        'route' => ['command' => 'route:clear',  'label' => 'کش مسیرها'],
        'view' => ['command' => 'view:clear',   'label' => 'کش نماها'],
    ];

    /** محیط برنامه — مقدار فعلی هنگام mount از config خوانده می‌شود */
    public string $environment = 'production';

    /** حالت توسعه‌ای (APP_DEBUG) */
    public bool $debug = false;

    /** پس از مایگریشن، سیدرهای دیتابیس هم اجرا شوند؟ */
    public bool $runSeeders = true;

    /** مودال تأیید اجرای مایگریشن / پاک‌سازی همهٔ کش‌ها / بهینه‌سازی */
    public bool $confirmMigrate = false;

    public bool $confirmClearAll = false;

    public bool $confirmOptimize = false;

    /** خروجی آخرین اجرای مایگریشن (برای نمایش در کد-باکس) */
    public ?string $migrateOutput = null;

    public function mount(): void
    {
        $this->authorizeDeveloper();

        $this->environment = (string) config('app.env');
        $this->debug = (bool) config('app.debug');
    }

    public function render()
    {
        $this->authorizeDeveloper();

        $editor = app(EnvEditor::class);

        return view('livewire.settings.developer', [
            'pendingMigrations' => $this->pendingMigrations,
            'envWritable' => $editor->writable(),
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => app()->version(),
            'dbDriver' => (string) config('database.default'),
            'configCached' => is_file(base_path('bootstrap/cache/config.php')),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  محیط برنامه (APP_ENV) */
    /* ------------------------------------------------------------------ */

    public function saveEnvironment(): void
    {
        $this->authorizeDeveloper();

        if (! in_array($this->environment, self::ENVIRONMENTS, true)) {
            $this->toast('مقدار محیط معتبر نیست.', 'error');

            return;
        }

        try {
            app(EnvEditor::class)->write('APP_ENV', $this->environment);
        } catch (\Throwable $e) {
            $this->toast('نوشتن در .env ناموفق بود: '.$e->getMessage(), 'error');

            return;
        }

        // اعمال فوری در همین درخواست + همگام‌سازی کش پیکربندی (اگر ساخته شده باشد)
        config(['app.env' => $this->environment]);
        $this->refreshConfigCache();

        if ($this->environment === 'production') {
            $this->toast('محیط برنامه روی production تنظیم شد — برای سرعت، کش‌های سرعت‌دهنده را اجرا کنید.');
        } else {
            $this->toast('محیط برنامه به «'.$this->environment.'» تغییر کرد و در .env ذخیره شد.');
        }
    }

    /* ------------------------------------------------------------------ */
    /*  حالت توسعه‌ای (APP_DEBUG) */
    /* ------------------------------------------------------------------ */

    public function saveDebug(): void
    {
        $this->authorizeDeveloper();

        try {
            app(EnvEditor::class)->write('APP_DEBUG', $this->debug ? 'true' : 'false');
        } catch (\Throwable $e) {
            $this->toast('نوشتن در .env ناموفق بود: '.$e->getMessage(), 'error');

            return;
        }

        config(['app.debug' => $this->debug]);
        $this->refreshConfigCache();

        if ($this->debug) {
            // نمایش جزئیات خطا روی سایت فعال خطرناک است — هشدار جدی
            $this->toast('حالت توسعه‌ای روشن شد. برای امنیت سایت پس از رفع اشکال آن را خاموش کنید!', 'warning');
        } else {
            $this->toast('حالت توسعه‌ای خاموش شد — جزئیات خطا دیگر نمایش داده نمی‌شود.');
        }
    }

    /* ------------------------------------------------------------------ */
    /*  پاک‌سازی کش‌ها */
    /* ------------------------------------------------------------------ */

    /** wire:click="clearCache('app' | 'config' | 'route' | 'view' | 'all')" */
    public function clearCache(string $what = 'all'): void
    {
        $this->authorizeDeveloper();
        $this->confirmClearAll = false;

        try {
            if ($what === 'all') {
                foreach (self::CACHE_TARGETS as $target) {
                    Artisan::call($target['command']);
                }

                $this->toast('همهٔ کش‌ها پاک شدند.');

                return;
            }

            if (! isset(self::CACHE_TARGETS[$what])) {
                $this->toast('هدف پاک‌سازی نامعتبر است.', 'error');

                return;
            }

            Artisan::call(self::CACHE_TARGETS[$what]['command']);
            $this->toast(self::CACHE_TARGETS[$what]['label'].' با موفقیت پاک شد.');
        } catch (\Throwable $e) {
            $this->toast('خطا در پاک‌سازی کش: '.$e->getMessage(), 'error');
        }
    }

    /* ------------------------------------------------------------------ */
    /*  بهینه‌سازی سرعت */
    /* ------------------------------------------------------------------ */

    public function optimize(): void
    {
        $this->authorizeDeveloper();
        $this->confirmOptimize = false;

        // نکته: در محیط تست، config:cache مقادیر phpunit را در کش واقعی پروژه
        // می‌نویسد؛ بنابراین این مرحله فقط خارج از تست اجرا می‌شود.
        $commands = app()->environment('testing')
            ? ['view:cache']
            : ['config:cache', 'route:cache', 'view:cache'];

        $failed = [];

        foreach ($commands as $command) {
            try {
                Artisan::call($command);
            } catch (\Throwable) {
                $failed[] = $command;
            }
        }

        if ($failed !== []) {
            $this->toast('بهینه‌سازی انجام شد اما این مراحل ناموفق بود: '.implode('، ', $failed), 'warning');
        } else {
            $this->toast('بهینه‌سازی انجام شد — پیکربندی، مسیرها و ویوها کش شدند.');
        }
    }

    /* ------------------------------------------------------------------ */
    /*  مایگریشن‌های در انتظار (فقط وقتی مایگریشن جدید وجود دارد) */
    /* ------------------------------------------------------------------ */

    /** ویژگی محاسبه‌شدهٔ لیووایر: $this->pendingMigrations */
    public function getPendingMigrationsProperty(): array
    {
        try {
            $migrator = app('migrator');
            $repository = $migrator->getRepository();

            // اگر جدول migrations هنوز ساخته نشده باشد، همهٔ فایل‌ها «در انتظار» هستند
            $ran = $repository->repositoryExists()
                ? collect($repository->getRan())
                : collect();

            $files = collect($migrator->getMigrationFiles(database_path('migrations')));

            return $files->keys()
                ->reject(fn (string $name) => $ran->contains($name))
                ->values()
                ->all();
        } catch (\Throwable) {
            // دیتابیس در دسترس نیست — بخش مایگریشن‌ها را مخفی کن
            return [];
        }
    }

    /** دکمهٔ «php artisan migrate» (+ سیدرها در صورت انتخاب) */
    public function runMigrations(): void
    {
        $this->authorizeDeveloper();
        $this->confirmMigrate = false;

        if ($this->pendingMigrations === []) {
            $this->toast('مایگریشن در انتظاری وجود ندارد.', 'warning');

            return;
        }

        try {
            $exitCode = Artisan::call('migrate', ['--force' => true]);
            $output = trim(Artisan::output());

            $seedFailed = false;

            if ($this->runSeeders) {
                try {
                    Artisan::call('db:seed', ['--force' => true]);
                    $seedOutput = trim(Artisan::output());

                    if ($seedOutput !== '') {
                        $output .= PHP_EOL.PHP_EOL.'--- db:seed ---'.PHP_EOL.$seedOutput;
                    }
                } catch (\Throwable) {
                    $seedFailed = true;
                }
            }

            // کش درون‌درخواستی پراپرتی محاسبه‌شده را خنثی کن تا لیست «در انتظار»
            // بعد از اجرا دوباره (تازه) محاسبه شود و بخش بلافاصله مخفی شود.
            unset($this->pendingMigrations);

            $this->migrateOutput = $output;

            if ($exitCode !== 0) {
                $this->toast('اجرای مایگریشن با خطا متوقف شد — خروجی را بررسی کنید.', 'error');

                return;
            }

            if ($seedFailed) {
                $this->toast('مایگریشن‌ها اجرا شدند اما اجرای سیدرها ناموفق بود — خروجی را بررسی کنید.', 'warning');

                return;
            }

            $this->toast('مایگریشن‌ها اجرا شدند'.($this->runSeeders ? ' و سیدرهای دیتابیس هم اجرا شدند' : '').'.');
        } catch (\Throwable $e) {
            $this->migrateOutput = $e->getMessage();
            $this->toast('اجرای مایگریشن ناموفق بود: '.$e->getMessage(), 'error');
        }
    }

    /* ------------------------------------------------------------------ */
    /*  داخلی */
    /* ------------------------------------------------------------------ */

    /** محافظت اضافی: این صفحه/اکشن‌ها فقط برای نقش admin (حتی با قفل نقش‌های خاموش) */
    protected function authorizeDeveloper(): void
    {
        abort_unless((bool) auth()->user()?->isAdmin(), 403, 'تنظیمات برنامه‌نویس فقط برای نقش «مدیر» مجاز است.');
    }

    /**
     * پس از تغییر .env، اگر پیکربندی قبلاً کش شده باشد باید بازسازی شود
     * وگرنه تغییرات در درخواست‌های بعدی دیده نمی‌شود.
     */
    private function refreshConfigCache(): void
    {
        if (app()->environment('testing')) {
            return; // کش‌کردن مقادیر phpunit در فایل واقعی پروژه ممنوع
        }

        try {
            $cached = is_file(base_path('bootstrap/cache/config.php'));

            Artisan::call('config:clear');

            if ($cached) {
                Artisan::call('config:cache');
            }
        } catch (\Throwable) {
            // کش پیکربندی حیاتی نیست — خطا نادیده گرفته می‌شود
        }
    }
}
