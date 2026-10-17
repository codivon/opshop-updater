<div class="space-y-6">

    {{-- ============ Header ============ --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-black text-zinc-900 dark:text-zinc-50">پشتیبان‌گیری و بازیابی</h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                از همه اطلاعات دیتابیس نسخه پشتیبان بگیرید و هر زمان همان فایل را آپلود کنید تا داده‌ها سر جای خودشان برگردند.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <x-badge variant="neutral" icon="database">
                دیتابیس: <span dir="ltr" class="font-mono">{{ $this->stats['driver'] }}</span>
            </x-badge>
        </div>
    </div>

    {{-- ============ هشدار جداول غیرقابل‌خواندن (مثلاً جدول خراب در لوکال) ============ --}}
    @if(! empty($this->stats['broken']))
        <div class="flex flex-col gap-2 rounded-2xl bg-amber-50 p-4 ring-1 ring-amber-500/20 dark:bg-amber-500/10 dark:ring-amber-400/20">
            <div class="flex items-start gap-2.5 text-sm font-black text-amber-800 dark:text-amber-300">
                <x-icon name="alert-triangle" class="mt-0.5 size-4.5 shrink-0" />
                {{ fa_num(count($this->stats['broken'])) }} جدول غیرقابل‌خواندن است و از پشتیبان‌گیری رد می‌شود:
            </div>
            <div class="flex flex-col gap-1.5 ps-7">
                @foreach($this->stats['broken'] as $table => $message)
                    <div class="text-xs leading-6 text-amber-700 dark:text-amber-300/80">
                        <span dir="ltr" class="rounded-lg bg-white px-2 py-0.5 font-mono font-bold text-amber-800 ring-1 ring-amber-500/20 dark:bg-amber-500/15 dark:text-amber-200">{{ $table }}</span>
                        <span dir="ltr" class="font-mono opacity-80">{{ \Illuminate\Support\Str::limit($message, 140) }}</span>
                    </div>
                @endforeach
                <p class="mt-1 text-[11px] leading-5 text-amber-700/80 dark:text-amber-300/60">
                    این جداول معمولاً میراث نسخه‌های قدیمی‌اند و کد فعلی استفاده‌شان نمی‌کند؛ برای رفع کامل، جدول خراب را در دیتابیس DROP و در صورت نیاز دوباره بسازید.
                </p>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        {{-- ============================================================ --}}
        {{-- ۱) دریافت نسخه پشتیبان                                       --}}
        {{-- ============================================================ --}}
        <div class="card flex flex-col gap-4 p-5 sm:p-6">
            <div class="flex items-start gap-4">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400">
                    <x-icon name="archive" class="size-6" />
                </div>
                <div class="min-w-0">
                    <h3 class="text-base font-black text-zinc-900 dark:text-zinc-50">دریافت نسخه پشتیبان</h3>
                    <p class="mt-1 text-sm leading-6 text-zinc-500 dark:text-zinc-400">
                        کل داده‌های جداول (کاربران، مشتریان، پکیج‌ها، محصولات، خریدها، لایسنس‌ها، اشتراک‌ها، کدهای تخفیف، دسته‌بندی‌ها، تنظیمات، پیامک‌ها و…) در یک فایل JSON دانلود می‌شود.
                    </p>
                </div>
            </div>

            {{-- آمار --}}
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-xl bg-zinc-100 px-3 py-1.5 text-xs font-bold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                    <x-icon name="database" class="size-3.5" />
                    {{ fa_num($this->stats['tables']) }} جدول
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-xl bg-zinc-100 px-3 py-1.5 text-xs font-bold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                    <x-icon name="list" class="size-3.5" />
                    {{ fa_num($this->stats['rows']) }} رکورد
                </span>
            </div>

            {{-- فهرست جداول --}}
            <details class="group rounded-xl bg-zinc-50 ring-1 ring-zinc-200/70 dark:bg-zinc-800/50 dark:ring-zinc-700/60">
                <summary class="flex cursor-pointer list-none items-center gap-2 px-3 py-2.5 text-xs font-bold text-zinc-500 transition-colors hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">
                    <x-icon name="chevron-down" class="size-3.5 transition-transform group-open:rotate-180" />
                    فهرست جداول مشمول پشتیبان
                </summary>
                <div class="flex flex-wrap gap-1.5 border-t border-zinc-200/70 px-3 py-3 dark:border-zinc-700/60">
                    @foreach($this->tableNames as $table)
                        <span dir="ltr" class="rounded-lg bg-white px-2 py-0.5 font-mono text-[10px] text-zinc-500 ring-1 ring-zinc-200/70 dark:bg-zinc-900 dark:text-zinc-400 dark:ring-zinc-700/60">{{ $table }}</span>
                    @endforeach
                </div>
            </details>

            <div class="mt-auto flex items-center justify-between gap-3 border-t border-zinc-100 pt-4 dark:border-zinc-700/50">
                <p class="text-[11px] leading-5 text-zinc-400 dark:text-zinc-500">
                    جدول‌های سیستمی (کش، سشن، صف‌ها) مشمول نمی‌شوند.
                </p>
                <x-btn icon="download" wire:click="download" :loading="true" class="shrink-0">
                    دریافت نسخه پشتیبان
                </x-btn>
            </div>
        </div>

        {{-- ============================================================ --}}
        {{-- ۲) بازیابی نسخه پشتیبان                                      --}}
        {{-- ============================================================ --}}
        <div class="card flex flex-col gap-4 p-5 sm:p-6">
            <div class="flex items-start gap-4">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400">
                    <x-icon name="upload" class="size-6" />
                </div>
                <div class="min-w-0">
                    <h3 class="text-base font-black text-zinc-900 dark:text-zinc-50">بازیابی نسخه پشتیبان</h3>
                    <p class="mt-1 text-sm leading-6 text-zinc-500 dark:text-zinc-400">
                        فایل پشتیبان را آپلود کنید تا همه اطلاعات به سر جای خودشان برگردند.
                    </p>
                </div>
            </div>

            {{-- قانون بازیابی --}}
            <div class="flex items-start gap-2.5 rounded-xl bg-amber-50 p-3.5 text-xs leading-6 text-amber-800 ring-1 ring-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                <x-icon name="alert-triangle" class="mt-0.5 size-4 shrink-0" />
                <div>
                    <strong class="font-black">هیچ داده‌ای حذف یا بازنویسی نمی‌شود.</strong>
                    هر رکورد بر اساس شناسه بررسی می‌شود؛
                    اگر <strong>از قبل وجود داشته باشد رد می‌شود</strong> و فقط رکوردهای جدید درج می‌گردند.
                    بنابراین بازیابی روی هر دیتابیسی امن است و خطر همپوشانی ندارد.
                </div>
            </div>

            {{-- انتخاب فایل --}}
            <div>
                <label class="mb-1.5 block text-xs font-bold text-zinc-600 dark:text-zinc-300" for="backup-file">
                    فایل پشتیبان (JSON)
                </label>
                <input id="backup-file" type="file" accept=".json,application/json"
                       wire:model="backupFile"
                       class="input file:me-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-zinc-600 dark:file:bg-zinc-800 dark:file:text-zinc-300" />

                @error('backupFile')
                    <p class="mt-1.5 text-xs font-bold text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror

                <div class="mt-2 flex items-center gap-2 text-[11px] text-zinc-400 dark:text-zinc-500" wire:loading wire:target="backupFile">
                    <x-icon name="loader" class="size-3.5 animate-spin" />
                    در حال بارگذاری فایل…
                </div>
            </div>

            <div class="mt-auto flex items-center justify-between gap-3 border-t border-zinc-100 pt-4 dark:border-zinc-700/50">
                <p class="text-[11px] leading-5 text-zinc-400 dark:text-zinc-500">
                    حداکثر حجم: ۵۰ مگابایت
                </p>
                <x-btn icon="upload" wire:click="$set('confirmRestore', true)" class="shrink-0" :disabled="! $backupFile">
                    بازیابی اطلاعات
                </x-btn>
            </div>
        </div>
    </div>

    {{-- ============ مودال تأیید بازیابی ============ --}}
    <x-modal wire:model="confirmRestore" title="بازیابی نسخه پشتیبان" size="md">
        <div class="space-y-4">
            <div class="flex items-start gap-3 rounded-xl bg-amber-50 p-3.5 text-sm leading-6 text-amber-800 ring-1 ring-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                <x-icon name="alert-triangle" class="mt-0.5 size-5 shrink-0" />
                <div>
                    بازیابی را تأیید می‌کنید؟ رکوردهای موجود
                    <strong class="font-black">رد</strong> و فقط رکوردهای جدید
                    <strong class="font-black">درج</strong> می‌شوند.
                </div>
            </div>

            <p class="text-xs leading-6 text-zinc-500 dark:text-zinc-400">
                این عملیات برای دیتابیس فعلی مخرب نیست؛ اما همیشه پیش از بازیابی، یک نسخه پشتیبان تازه از همین صفحه تهیه کنید.
            </p>
        </div>

        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-btn variant="secondary" wire:click="$set('confirmRestore', false)">انصراف</x-btn>
                <x-btn icon="upload" wire:click="restore" :loading="true">
                    شروع بازیابی
                </x-btn>
            </div>
        </x-slot:footer>
    </x-modal>

</div>
