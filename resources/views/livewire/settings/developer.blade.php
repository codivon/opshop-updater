@php
    // رنگ نشان محیط — production سبز (سایت فعال)، بقیه هشداری
    $envBadges = [
        'production' => 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-400/20',
        'local'      => 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-400/20',
        'develop'    => 'bg-rose-100 text-rose-700 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20',
        'staging'    => 'bg-zinc-100 text-zinc-700 ring-zinc-500/20 dark:bg-zinc-500/10 dark:text-zinc-300 dark:ring-zinc-400/20',
    ];
@endphp
<div class="space-y-6">
    {{-- header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-black text-zinc-900 dark:text-zinc-50">تنظیمات برنامه‌نویس</h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">محیط برنامه، دیباگ، پاک‌سازی کش‌ها، بهینه‌سازی و اجرای مایگریشن‌های در انتظار — تغییرات مستقیماً روی سرور اعمال می‌شود.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.settings.index') }}" wire:navigate>
                <x-btn variant="secondary" icon="settings">تنظیمات عمومی</x-btn>
            </a>
            <a href="{{ route('admin.settings.security') }}" wire:navigate>
                <x-btn variant="secondary" icon="shield">امنیت و امکانات</x-btn>
            </a>
        </div>
    </div>

    {{-- ============ نوار وضعیت سیستم ============ --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-xl bg-white p-4 ring-1 ring-zinc-200/70 dark:bg-zinc-900 dark:ring-zinc-800">
            <p class="flex items-center gap-1.5 text-[11px] font-bold text-zinc-500 dark:text-zinc-400">
                <x-icon name="globe" class="size-3.5" /> محیط برنامه
            </p>
            <p class="mt-2">
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-black ring-1 {{ $envBadges[$environment] ?? $envBadges['staging'] }}" dir="ltr">{{ $environment }}</span>
            </p>
        </div>
        <div class="rounded-xl bg-white p-4 ring-1 ring-zinc-200/70 dark:bg-zinc-900 dark:ring-zinc-800">
            <p class="flex items-center gap-1.5 text-[11px] font-bold text-zinc-500 dark:text-zinc-400">
                <x-icon name="eye" class="size-3.5" /> حالت دیباگ
            </p>
            <p class="mt-2">
                @if($debug)
                    <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2.5 py-1 text-[11px] font-black text-rose-700 ring-1 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20">روشن</span>
                @else
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-black text-emerald-700 ring-1 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-400/20">خاموش</span>
                @endif
            </p>
        </div>
        <div class="rounded-xl bg-white p-4 ring-1 ring-zinc-200/70 dark:bg-zinc-900 dark:ring-zinc-800">
            <p class="flex items-center gap-1.5 text-[11px] font-bold text-zinc-500 dark:text-zinc-400">
                <x-icon name="server" class="size-3.5" /> نسخهٔ PHP
            </p>
            <p class="mt-2 font-mono text-sm font-bold text-zinc-800 dark:text-zinc-100" dir="ltr">{{ $phpVersion }}</p>
        </div>
        <div class="rounded-xl bg-white p-4 ring-1 ring-zinc-200/70 dark:bg-zinc-900 dark:ring-zinc-800">
            <p class="flex items-center gap-1.5 text-[11px] font-bold text-zinc-500 dark:text-zinc-400">
                <x-icon name="layers" class="size-3.5" /> نسخهٔ لاراول
            </p>
            <p class="mt-2 font-mono text-sm font-bold text-zinc-800 dark:text-zinc-100" dir="ltr">{{ $laravelVersion }}</p>
        </div>
    </div>

    {{-- ============ محیط برنامه (APP_ENV) ============ --}}
    <x-card title="محیط برنامه" subtitle="APP_ENV — این مقدار مستقیماً در فایل .env نوشته می‌شود و کش پیکربندی (در صورت وجود) همگام می‌شود.">
        <div class="space-y-4">
            @if(! $envWritable)
                <div class="flex items-center gap-3 rounded-xl bg-rose-50 p-4 text-rose-700 ring-1 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20">
                    <x-icon name="alert-circle" class="size-5 shrink-0" />
                    <p class="text-xs leading-6">فایل .env قابل نوشتن نیست؛ تغییر محیط از پنل کار نخواهد کرد. دسترسی نوشتن فایل را روی سرور بررسی کنید.</p>
                </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="محیط اجرای برنامه" for="developer_env" hint="production برای سایت واقعی — local/develop برای رفع اشکال روی سیستم خودتان.">
                    @php($envOptions = array_combine(\App\Livewire\Settings\Developer::ENVIRONMENTS, \App\Livewire\Settings\Developer::ENVIRONMENTS))
                    <x-select id="developer_env" wire:model="environment" :options="$envOptions" />
                </x-field>

                <div class="flex items-end">
                    <x-btn wire:click="saveEnvironment" icon="save" :loading="true" class="w-full sm:w-auto">ذخیره محیط</x-btn>
                </div>
            </div>

            <div class="flex items-center gap-3 rounded-xl bg-amber-50 p-4 text-amber-800 ring-1 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-400/20">
                <x-icon name="alert-triangle" class="size-5 shrink-0" />
                <p class="text-xs font-bold leading-6">برای سرعت و امنیت، سایت فعال باید روی محیط production باشد.</p>
            </div>
        </div>
    </x-card>

    {{-- ============ حالت توسعه‌ای (APP_DEBUG) ============ --}}
    <x-card title="فعال‌سازی حالت توسعه‌ای (Debug)" subtitle="APP_DEBUG — با فعال‌کردن، جزئیات کامل خطاها (Exception واقعی، فایل و خط) به‌جای صفحهٔ کلی «خطای سرور» نمایش داده می‌شود.">
        <div class="space-y-4">
            <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                <x-toggle wire:model="debug" label="نمایش جزئیات کامل خطاها (حالت توسعه‌ای)" id="app_debug_toggle" />
            </div>

            <x-btn wire:click="saveDebug" icon="save" :loading="true" variant="{{ $debug ? 'danger' : 'primary' }}">
                {{ $debug ? 'ذخیره و خاموش‌کردن دیباگ' : 'ذخیره و روشن‌کردن دیباگ' }}
            </x-btn>

            <div class="rounded-xl bg-amber-50 p-4 ring-1 ring-amber-600/20 dark:bg-amber-500/10 dark:ring-amber-400/20">
                <p class="text-xs font-bold leading-6 text-amber-700 dark:text-amber-400">فقط برای رفع اشکال استفاده کنید و پس از آن خاموشش کنید!</p>
                <p class="mt-1 text-xs leading-6 text-amber-700/90 dark:text-amber-400/90">نمایش جزئیات خطا برای امنیت سایت خطرناک است؛ پیام خطا می‌تواند ساختار پروژه، مسیرها و مقادیر پیکربندی را لو بدهد.</p>
            </div>
        </div>
    </x-card>

    {{-- ============ پاک‌سازی کش‌ها ============ --}}
    <x-card title="پاک‌سازی کش‌ها" subtitle="اگر بعد از تغییرات، ساییت قدیمی را نشان می‌دهد یا تنظیمات جدید اعمال نشده، کش‌های سرو را پاک کنید. این کار امن است و هیچ داده‌ای از دیتابیس حذف نمی‌شود.">
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <button type="button"
                        wire:click="clearCache('app')"
                        wire:loading.attr="disabled"
                        wire:target="clearCache('app')"
                        class="flex flex-col items-center gap-2 rounded-xl bg-zinc-50 px-4 py-5 ring-1 ring-zinc-200/60 transition-all duration-150 hover:-translate-y-0.5 hover:bg-zinc-100 hover:ring-zinc-300 disabled:opacity-50 dark:bg-zinc-800/40 dark:ring-zinc-700/60 dark:hover:bg-zinc-800 dark:hover:ring-zinc-600">
                    <x-icon name="database" class="size-6 text-zinc-500 dark:text-zinc-400" />
                    <span class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">کش برنامه</span>
                    <span class="font-mono text-[10px] text-zinc-400 dark:text-zinc-500" dir="ltr">cache:clear</span>
                </button>

                <button type="button"
                        wire:click="clearCache('config')"
                        wire:loading.attr="disabled"
                        wire:target="clearCache('config')"
                        class="flex flex-col items-center gap-2 rounded-xl bg-zinc-50 px-4 py-5 ring-1 ring-zinc-200/60 transition-all duration-150 hover:-translate-y-0.5 hover:bg-zinc-100 hover:ring-zinc-300 disabled:opacity-50 dark:bg-zinc-800/40 dark:ring-zinc-700/60 dark:hover:bg-zinc-800 dark:hover:ring-zinc-600">
                    <x-icon name="settings" class="size-6 text-zinc-500 dark:text-zinc-400" />
                    <span class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">کش پیکربندی</span>
                    <span class="font-mono text-[10px] text-zinc-400 dark:text-zinc-500" dir="ltr">config:clear</span>
                </button>

                <button type="button"
                        wire:click="clearCache('view')"
                        wire:loading.attr="disabled"
                        wire:target="clearCache('view')"
                        class="flex flex-col items-center gap-2 rounded-xl bg-zinc-50 px-4 py-5 ring-1 ring-zinc-200/60 transition-all duration-150 hover:-translate-y-0.5 hover:bg-zinc-100 hover:ring-zinc-300 disabled:opacity-50 dark:bg-zinc-800/40 dark:ring-zinc-700/60 dark:hover:bg-zinc-800 dark:hover:ring-zinc-600">
                    <x-icon name="eye" class="size-6 text-zinc-500 dark:text-zinc-400" />
                    <span class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">کش نماها</span>
                    <span class="font-mono text-[10px] text-zinc-400 dark:text-zinc-500" dir="ltr">view:clear</span>
                </button>

                <button type="button"
                        wire:click="clearCache('route')"
                        wire:loading.attr="disabled"
                        wire:target="clearCache('route')"
                        class="flex flex-col items-center gap-2 rounded-xl bg-zinc-50 px-4 py-5 ring-1 ring-zinc-200/60 transition-all duration-150 hover:-translate-y-0.5 hover:bg-zinc-100 hover:ring-zinc-300 disabled:opacity-50 dark:bg-zinc-800/40 dark:ring-zinc-700/60 dark:hover:bg-zinc-800 dark:hover:ring-zinc-600">
                    <x-icon name="globe" class="size-6 text-zinc-500 dark:text-zinc-400" />
                    <span class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">کش مسیرها</span>
                    <span class="font-mono text-[10px] text-zinc-400 dark:text-zinc-500" dir="ltr">route:clear</span>
                </button>
            </div>

            <button type="button"
                    wire:click="$set('confirmClearAll', true)"
                    class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-zinc-800 text-sm font-semibold text-white shadow-xs transition-all duration-150 hover:bg-zinc-700 active:scale-[0.99] dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white sm:w-auto">
                <x-icon name="refresh-cw" class="size-4.5" />
                پاک‌سازی همه‌ی کش‌ها
            </button>
        </div>
    </x-card>

    {{-- ============ بهینه‌سازی سرعت ============ --}}
    <x-card>
        <div class="rounded-xl bg-emerald-50 p-4 ring-1 ring-emerald-600/20 dark:bg-emerald-500/10 dark:ring-emerald-400/20">
            <div class="flex items-center gap-2">
                <x-icon name="zap" class="size-5 text-emerald-600 dark:text-emerald-400" />
                <p class="text-sm font-black text-emerald-800 dark:text-emerald-300">بهینه‌سازی سرعت سایت</p>
            </div>
            <p class="mt-2 text-xs leading-6 text-emerald-700 dark:text-emerald-400">
                کش پیکربندی، مسیرها و ویوها ساخته می‌شود — بزرگ‌ترین اثر را روی سرعت پاسخ‌دهی دارد. پس از هر استقرار یا تغییر تنظیمات، یک‌بار این دکمه را بزنید.
            </p>
            <div class="mt-3">
                <x-btn icon="zap" wire:click="$set('confirmOptimize', true)">اجرای بهینه‌سازی</x-btn>
            </div>
        </div>
    </x-card>

    {{-- ============ مایگریشن‌های در انتظار — فقط وقتی مایگریشن جدید ثبت‌نشده وجود دارد ============ --}}
    @if(count($pendingMigrations) > 0)
        <x-card title="مایگریشن‌های در انتظار" subtitle="این فایل‌های مایگریشن در پروژه هستند اما هنوز در جدول migrations دیتابیس ثبت نشده‌اند — یعنی جداول/ستون‌های جدید هنوز ساخته نشده‌اند.">
            <div class="space-y-4">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1.5 text-xs font-black text-amber-800 ring-1 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-400/20">
                        <x-icon name="database" class="size-3.5" />
                        {{ fa_num(count($pendingMigrations)) }} مایگریشن در انتظار اجرا
                    </span>
                    @if($runSeeders)
                        <span class="text-[11px] text-zinc-400 dark:text-zinc-500">سیدرها هم اجرا می‌شوند</span>
                    @endif
                </div>

                <ul class="max-h-96 divide-y divide-zinc-100 overflow-y-auto rounded-xl bg-zinc-50 ring-1 ring-zinc-200/60 dark:divide-zinc-800 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                    @foreach($pendingMigrations as $migration)
                        <li class="flex items-center gap-3 px-4 py-3">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                                <x-icon name="database" class="size-4" />
                            </span>
                            <span class="font-mono text-xs text-zinc-700 dark:text-zinc-300" dir="ltr">{{ $migration }}</span>
                        </li>
                    @endforeach
                </ul>

                <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                    <x-toggle wire:model="runSeeders" label="اجرای سیدرهای دیتابیس پس از مایگریشن (php artisan db:seed)" id="developer_run_seeders" />
                    <p class="mt-2 text-[11px] leading-5 text-zinc-500 dark:text-zinc-400">سیدرهای استاندارد لاراول idempotent هستند؛ اما اگر سیدر دادهٔ تکراری بسازد، این گزینه را خاموش کنید.</p>
                </div>

                <x-btn icon="play" wire:click="$set('confirmMigrate', true)">اجرای مایگریشن (php artisan migrate)</x-btn>
            </div>
        </x-card>
    @endif

    {{-- خروجی آخرین اجرای مایگریشن — پس از اجرا هم دیده می‌شود (وقتی بخش بالا مخفی شده) --}}
    @if($migrateOutput)
        <div class="overflow-hidden rounded-xl bg-zinc-950 ring-1 ring-zinc-800 dark:bg-black/60 dark:ring-zinc-800">
            <div class="flex items-center gap-2 border-b border-zinc-800 px-4 py-2.5">
                <x-icon name="terminal" class="size-4 text-zinc-400" />
                <p class="text-[11px] font-bold text-zinc-400">خروجی آخرین اجرای مایگریشن</p>
            </div>
            <pre class="max-h-64 overflow-auto p-4 text-left font-mono text-[11px] leading-5 text-emerald-300" dir="ltr">{{ $migrateOutput }}</pre>
        </div>
    @endif

    {{-- ============ مودال‌های تأیید ============ --}}

    {{-- تأیید اجرای مایگریشن --}}
    <x-modal wire:model="confirmMigrate" title="تأیید اجرای مایگریشن" size="sm">
        @if($confirmMigrate)
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-amber-50 p-4 text-amber-700 ring-1 ring-amber-600/10 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-400/20">
                    <x-icon name="alert-triangle" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">
                        {{ fa_num(count($pendingMigrations)) }} مایگریشن اجرا می‌شود و جداول/ستون‌های جدید ساخته می‌شوند{{ $runSeeders ? ' و سیدرهای دیتابیس هم اجرا می‌شوند' : '' }}. ادامه می‌دهید؟
                    </p>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <x-btn variant="secondary" wire:click="$set('confirmMigrate', false)">انصراف</x-btn>
                    <x-btn icon="play" wire:click="runMigrations" :loading="true">اجرا کن</x-btn>
                </div>
            </div>
        @endif
    </x-modal>

    {{-- پاک‌سازی همهٔ کش‌ها --}}
    <x-modal wire:model="confirmClearAll" title="پاک‌سازی همه‌ی کش‌ها" size="sm">
        @if($confirmClearAll)
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-amber-50 p-4 text-amber-700 ring-1 ring-amber-600/10 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-400/20">
                    <x-icon name="refresh-cw" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">کش برنامه، پیکربندی، مسیرها و ویوها پاک می‌شود. این عملیات امن است و داده‌ای حذف نمی‌شود؛ ادامه می‌دهید؟</p>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <x-btn variant="secondary" wire:click="$set('confirmClearAll', false)">انصراف</x-btn>
                    <x-btn icon="refresh-cw" wire:click="clearCache('all')" :loading="true">پاک‌سازی همه</x-btn>
                </div>
            </div>
        @endif
    </x-modal>

    {{-- بهینه‌سازی --}}
    <x-modal wire:model="confirmOptimize" title="بهینه‌سازی سیستم" size="sm">
        @if($confirmOptimize)
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-brand-50 p-4 text-brand-700 ring-1 ring-brand-600/10 dark:bg-brand-500/10 dark:text-brand-400 dark:ring-brand-400/20">
                    <x-icon name="zap" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">تنظیمات، مسیرها و ویوها کش می‌شوند تا سرعت برنامه بالا برود؛ ادامه می‌دهید؟</p>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <x-btn variant="secondary" wire:click="$set('confirmOptimize', false)">انصراف</x-btn>
                    <x-btn icon="zap" wire:click="optimize" :loading="true">بهینه‌سازی</x-btn>
                </div>
            </div>
        @endif
    </x-modal>
</div>
