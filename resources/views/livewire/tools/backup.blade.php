<div class="space-y-6" @if($this->active || $this->lastActiveId !== null) wire:poll.2s="tick" @endif>

    {{-- ============ Header ============ --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-black text-zinc-900 dark:text-zinc-50">پشتیبان‌گیری و بازیابی</h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                از همه اطلاعات — دیتابیس و فایل‌ها — یک فایل ZIP بگیرید و هر زمان همان فایل را بازیابی کنید تا همه‌چیز سر جای خودش برگردد.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <x-badge variant="neutral" icon="database">
                دیتابیس: <span dir="ltr" class="font-mono">{{ $this->stats['driver'] }}</span>
            </x-badge>
            <x-badge variant="neutral" icon="image">
                {{ fa_num($this->stats['files_count']) }} فایل رسانه‌ای
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
                        <span dir="ltr" class="font-mono opacity-80">{{ $message }}</span>
                    </div>
                @endforeach
                <p class="mt-1 text-[11px] leading-5 text-amber-700/80 dark:text-amber-300/60">
                    این جداول معمولاً میراث نسخه‌های قدیمی‌اند و کد فعلی استفاده‌شان نمی‌کند؛ برای رفع کامل، جدول خراب را در دیتابیس DROP و در صورت نیاز دوباره بسازید.
                </p>
            </div>
        </div>
    @endif

    {{-- ============ عملیات در جریان ============ --}}
    @if($active = $this->active)
        <div class="card flex flex-col gap-3 p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <x-icon name="loader" class="size-5 animate-spin text-brand-600 dark:text-brand-400" />
                    <div>
                        <p class="text-sm font-black text-zinc-900 dark:text-zinc-50">
                            {{ $active->isBackup() ? 'در حال ساخت نسخه پشتیبان' : 'در حال بازیابی اطلاعات' }}
                            — <span dir="ltr" class="font-mono">{{ $active->name }}</span>
                        </p>
                        <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $active->phase }}</p>
                    </div>
                </div>
                <span class="text-sm font-black text-brand-600 dark:text-brand-400">{{ fa_num($active->progress) }}٪</span>
            </div>

            <div class="h-2.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                <div class="h-full rounded-full bg-brand-500 transition-all duration-500"
                     style="width: {{ max(2, $active->progress) }}%"></div>
            </div>

            <p class="text-[11px] leading-5 text-zinc-400 dark:text-zinc-500">
                <x-icon name="shield-check" class="me-1 inline size-3.5" />
                عملیات تکه‌ای اجرا می‌شود و تایم‌اوت نمی‌گیرد. اگر صفحه را ببندید هم ادامه پیدا می‌کند
                (با کران‌جاب <span dir="ltr" class="font-mono">php artisan schedule:run</span> هر دقیقه)؛ با بازگشتن به همین صفحه، پیشرفت را می‌بینید.
            </p>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        {{-- ============================================================ --}}
        {{-- ۱) ساخت نسخه پشتیبان                                         --}}
        {{-- ============================================================ --}}
        <div class="card flex flex-col gap-4 p-5 sm:p-6">
            <div class="flex items-start gap-4">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400">
                    <x-icon name="archive" class="size-6" />
                </div>
                <div class="min-w-0">
                    <h3 class="text-base font-black text-zinc-900 dark:text-zinc-50">ساخت نسخه پشتیبان جدید</h3>
                    <p class="mt-1 text-sm leading-6 text-zinc-500 dark:text-zinc-400">
                        کل دیتابیس + تصاویر و فایل‌ها در یک فایل ZIP بایگانی می‌شود و در فهرست «فایل‌های پشتیبان» قابل دانلود و حذف است.
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
                <span class="inline-flex items-center gap-1.5 rounded-xl bg-zinc-100 px-3 py-1.5 text-xs font-bold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                    <x-icon name="image" class="size-3.5" />
                    {{ fa_num($this->stats['files_count']) }} فایل ({{ bytes_human($this->stats['files_size']) ?? '۰' }})
                </span>
            </div>

            {{-- فهرست جداول --}}
            <details class="group rounded-xl bg-zinc-50 ring-1 ring-zinc-200/70 dark:bg-zinc-800/50 dark:ring-zinc-700/60">
                <summary class="flex cursor-pointer list-none items-center gap-2 px-3 py-2.5 text-xs font-bold text-zinc-500 transition-colors hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">
                    <x-icon name="chevron-down" class="size-3.5 transition-transform group-open:rotate-180" />
                    فهرست جداول مشمول پشتیبان
                </summary>
                <div class="max-h-60 overflow-y-auto border-t border-zinc-200/70 px-3 py-3 dark:border-zinc-700/60">
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($this->stats['per_table'] as $table => $count)
                            <span dir="ltr" class="rounded-lg bg-white px-2 py-0.5 font-mono text-[10px] text-zinc-500 ring-1 ring-zinc-200/70 dark:bg-zinc-900 dark:text-zinc-400 dark:ring-zinc-700/60">
                                {{ $table }} · {{ fa_num($count) }}
                            </span>
                        @endforeach
                    </div>
                </div>
            </details>

            {{-- گزینه فایل‌ها --}}
            <label class="flex cursor-pointer items-center gap-3 rounded-xl bg-zinc-50 px-4 py-3 ring-1 ring-zinc-200/70 transition-colors hover:bg-zinc-100/70 dark:bg-zinc-800/50 dark:ring-zinc-700/60 dark:hover:bg-zinc-800">
                <input type="checkbox" class="size-4.5 accent-emerald-600" wire:model="includeFiles" />
                <span class="text-sm font-bold text-zinc-700 dark:text-zinc-200">
                    پشتیبان‌گیری از فایل‌ها و تصاویر
                    <span class="mt-0.5 block text-[11px] font-normal text-zinc-400 dark:text-zinc-500">
                        محتوای storage/app/public (تصاویر محصولات، پکیج‌ها، گالری‌ها و…) به ZIP اضافه می‌شود.
                    </span>
                </span>
            </label>

            <div class="mt-auto flex items-center justify-between gap-3 border-t border-zinc-100 pt-4 dark:border-zinc-700/50">
                <p class="text-[11px] leading-5 text-zinc-400 dark:text-zinc-500">
                    جدول‌های سیستمی (کش، سشن، صف‌ها) مشمول نمی‌شوند.
                </p>
                <x-btn icon="archive" wire:click="createBackup" :loading="true" :disabled="(bool) $this->active" class="shrink-0">
                    ساخت نسخه پشتیبان
                </x-btn>
            </div>
        </div>

        {{-- ============================================================ --}}
        {{-- ۲) بازیابی                                                   --}}
        {{-- ============================================================ --}}
        <div class="card flex flex-col gap-4 p-5 sm:p-6">
            <div class="flex items-start gap-4">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400">
                    <x-icon name="upload" class="size-6" />
                </div>
                <div class="min-w-0">
                    <h3 class="text-base font-black text-zinc-900 dark:text-zinc-50">بازیابی نسخه پشتیبان</h3>
                    <p class="mt-1 text-sm leading-6 text-zinc-500 dark:text-zinc-400">
                        فایل ZIP پشتیبان را آپلود کنید تا دیتابیس و فایل‌ها به‌کامل سر جایشان برگردند؛ در پایان گزارش کامل نمایش داده می‌شود.
                    </p>
                </div>
            </div>

            {{-- قانون بازیابی --}}
            <div class="flex items-start gap-2.5 rounded-xl bg-amber-50 p-3.5 text-xs leading-6 text-amber-800 ring-1 ring-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                <x-icon name="alert-triangle" class="mt-0.5 size-4 shrink-0" />
                <div>
                    <strong class="font-black">هیچ داده‌ای حذف یا بازنویسی نمی‌شود.</strong>
                    هر رکورد و فایل بررسی می‌شود؛ اگر <strong>از قبل وجود داشته باشد رد می‌شود</strong>
                    و فقط موارد جدید درج می‌گردند. رکوردها با شناسه اصلی درج می‌شوند تا روابط سالم بماند.
                </div>
            </div>

            {{-- انتخاب فایل --}}
            <div>
                <label class="mb-1.5 block text-xs font-bold text-zinc-600 dark:text-zinc-300" for="backup-file">
                    آپلود فایل پشتیبان (ZIP)
                </label>
                <input id="backup-file" type="file" accept=".zip,application/zip"
                       wire:model="backupFile"
                       class="input file:me-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-zinc-600 dark:file:bg-zinc-800 dark:file:text-zinc-300" />

                @error('backupFile')
                    <p class="mt-1.5 text-xs font-bold text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror

                <div class="mt-2 flex items-center gap-2 text-[11px] text-zinc-400 dark:text-zinc-500" wire:loading wire:target="backupFile">
                    <x-icon name="loader" class="size-3.5 animate-spin" />
                    در حال بارگذاری فایل…
                </div>

                <p class="mt-2 text-[11px] leading-5 text-zinc-400 dark:text-zinc-500">
                    فایل حجیم‌تر از ۵۱۲ مگابایت دارید؟ آن را از FTP/فایل‌منیجر در مسیر
                    <span dir="ltr" class="font-mono">storage/app/backups/incoming</span>
                    بگذارید؛ زیرا از همین فهرست قابل بازیابی است.
                </p>
            </div>

            {{-- فایل‌های موجود در incoming --}}
            @if(count($this->incoming) > 0)
                <div class="rounded-xl bg-zinc-50 ring-1 ring-zinc-200/70 dark:bg-zinc-800/50 dark:ring-zinc-700/60">
                    <p class="flex items-center gap-2 px-3 py-2.5 text-xs font-bold text-zinc-500 dark:text-zinc-400">
                        <x-icon name="folder" class="size-3.5" />
                        فایل‌های آمادهٔ بازیابی (incoming) — {{ fa_num(count($this->incoming)) }} فایل
                    </p>
                    <div class="max-h-52 divide-y divide-zinc-200/70 overflow-y-auto border-t border-zinc-200/70 dark:divide-zinc-700/60 dark:border-zinc-700/60">
                        @foreach($this->incoming as $file)
                            <div class="flex items-center justify-between gap-3 px-3 py-2.5">
                                <div class="flex min-w-0 flex-col">
                                    <span dir="ltr" class="truncate font-mono text-xs font-bold text-zinc-700 dark:text-zinc-200">{{ $file['name'] }}</span>
                                    <span class="text-[10px] text-zinc-400 dark:text-zinc-500">
                                        {{ bytes_human($file['size']) ?? '—' }} · {{ verta_date(date('Y-m-d H:i', $file['modified']), 'Y/m/d H:i') }}
                                    </span>
                                </div>
                                <div class="flex shrink-0 items-center gap-1.5">
                                    <button type="button" wire:click="requestRestore('{{ $file['name'] }}')"
                                            class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-2.5 py-1.5 text-[11px] font-bold text-emerald-700 ring-1 ring-emerald-500/20 transition-colors hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-400/20 dark:hover:bg-emerald-500/20">
                                        <x-icon name="upload" class="size-3" />
                                        بازیابی
                                    </button>
                                    <button type="button" wire:click="requestDelete('incoming:{{ $file['name'] }}')"
                                            class="inline-flex items-center gap-1 rounded-lg bg-rose-50 px-2.5 py-1.5 text-[11px] font-bold text-rose-600 ring-1 ring-rose-500/20 transition-colors hover:bg-rose-100 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20 dark:hover:bg-rose-500/20">
                                        <x-icon name="trash-2" class="size-3" />
                                        حذف
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="mt-auto flex items-center justify-between gap-3 border-t border-zinc-100 pt-4 dark:border-zinc-700/50">
                <p class="text-[11px] leading-5 text-zinc-400 dark:text-zinc-500">
                    فایل JSON قدیمی (نسخه ۱) هم پذیرفته می‌شود.
                </p>
                <x-btn icon="upload" wire:click="requestRestore('upload')" class="shrink-0" :disabled="! $backupFile">
                    بازیابی اطلاعات
                </x-btn>
            </div>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- ۳) گزارش آخرین بازیابی                                       --}}
    {{-- ============================================================ --}}
    @if($restore = $this->latestRestore)
        @php
            $report = $restore->report ?? [];
            $summary = $report['summary'] ?? [];
            $tablesReport = $report['tables'] ?? [];
            $skippedTables = $report['skipped_tables'] ?? [];
            $reportErrors = $report['errors'] ?? [];
        @endphp

        <div class="card flex flex-col gap-4 p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400">
                        <x-icon name="check-circle" class="size-5" />
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-zinc-900 dark:text-zinc-50">گزارش آخرین بازیابی</h3>
                        <p class="text-[11px] text-zinc-400 dark:text-zinc-500">
                            فایل: <span dir="ltr" class="font-mono">{{ $restore->original_filename }}</span>
                            · {{ verta_date($restore->created_at, 'Y/m/d H:i') }}
                            @if(($summary['format'] ?? null) === 1)
                                · (فرمت قدیمی نسخه ۱)
                            @endif
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-badge variant="success" icon="check-circle">
                        {{ fa_num($summary['restored_rows'] ?? 0) }} رکورد افزوده شد
                    </x-badge>
                    @if(($summary['fixed_values'] ?? 0) > 0)
                        <x-badge variant="warning" icon="settings">
                            {{ fa_num($summary['fixed_values'] ?? 0) }} مقدار ناسازگار اصلاح شد
                        </x-badge>
                    @endif
                    <x-badge variant="neutral" icon="clock">
                        {{ fa_num($summary['skipped_rows'] ?? 0) }} رکورد از قبل موجود (رد شد)
                    </x-badge>
                    @if(($summary['failed_rows'] ?? 0) > 0)
                        <x-badge variant="danger" icon="x-circle">
                            {{ fa_num($summary['failed_rows'] ?? 0) }} رکورد ناموفق
                        </x-badge>
                    @endif
                    <x-badge variant="success" icon="image">
                        {{ fa_num($summary['restored_files'] ?? 0) }} فایل بازیابی شد
                    </x-badge>
                    @if(($summary['skipped_files'] ?? 0) > 0)
                        <x-badge variant="neutral" icon="clock">
                            {{ fa_num($summary['skipped_files'] ?? 0) }} فایل تکراری (رد شد)
                        </x-badge>
                    @endif
                </div>
            </div>

            {{-- جزئیات جدول به جدول --}}
            @if(count($tablesReport) > 0)
                <div class="max-h-72 overflow-y-auto rounded-xl ring-1 ring-zinc-200/70 dark:ring-zinc-700/60">
                    <table class="w-full text-xs">
                        <thead class="sticky top-0 bg-zinc-50 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                            <tr>
                                <th class="px-3 py-2 text-start font-bold">جدول</th>
                                <th class="px-3 py-2 text-center font-bold">افزوده‌شده</th>
                                <th class="px-3 py-2 text-center font-bold">ردشده (موجود)</th>
                                <th class="px-3 py-2 text-center font-bold">اصلاح‌شده</th>
                                <th class="px-3 py-2 text-center font-bold">ناموفق</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach($tablesReport as $table => $row)
                                <tr class="text-zinc-600 dark:text-zinc-300">
                                    <td dir="ltr" class="px-3 py-2 text-start font-mono">{{ $table }}</td>
                                    <td class="px-3 py-2 text-center font-bold text-emerald-600 dark:text-emerald-400">{{ fa_num($row['restored'] ?? 0) }}</td>
                                    <td class="px-3 py-2 text-center text-zinc-400">{{ fa_num($row['skipped'] ?? 0) }}</td>
                                    <td class="px-3 py-2 text-center {{ ($row['fixed'] ?? 0) > 0 ? 'font-bold text-amber-600 dark:text-amber-400' : 'text-zinc-400' }}">{{ fa_num($row['fixed'] ?? 0) }}</td>
                                    <td class="px-3 py-2 text-center {{ ($row['failed'] ?? 0) > 0 ? 'font-bold text-rose-600 dark:text-rose-400' : 'text-zinc-400' }}">{{ fa_num($row['failed'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- جداول ردشده --}}
            @if(count($skippedTables) > 0)
                <div class="rounded-xl bg-amber-50 p-3.5 ring-1 ring-amber-500/20 dark:bg-amber-500/10 dark:ring-amber-400/20">
                    <p class="flex items-center gap-2 text-xs font-black text-amber-800 dark:text-amber-300">
                        <x-icon name="alert-triangle" class="size-3.5" />
                        {{ fa_num(count($skippedTables)) }} جدول از بازیابی رد شد:
                    </p>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @foreach($skippedTables as $table => $reason)
                            <span dir="ltr" class="rounded-lg bg-white px-2 py-0.5 font-mono text-[10px] text-amber-800 ring-1 ring-amber-500/20 dark:bg-amber-500/15 dark:text-amber-200 dark:ring-amber-400/20" title="{{ $reason }}">
                                {{ $table }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- خطاها --}}
            @if(count($reportErrors) > 0)
                <details class="rounded-xl bg-rose-50 ring-1 ring-rose-500/20 dark:bg-rose-500/10 dark:ring-rose-400/20">
                    <summary class="flex cursor-pointer list-none items-center gap-2 px-3.5 py-2.5 text-xs font-black text-rose-700 dark:text-rose-300">
                        <x-icon name="x-circle" class="size-3.5" />
                        جزئیات خطاها ({{ fa_num(count($reportErrors)) }} جدول)
                    </summary>
                    <div class="max-h-52 space-y-2 overflow-y-auto border-t border-rose-500/10 px-3.5 py-3">
                        @foreach($reportErrors as $table => $messages)
                            <div class="text-[11px] leading-5 text-rose-700 dark:text-rose-300/90">
                                <span dir="ltr" class="font-mono font-bold">{{ $table }}</span>:
                                @foreach((array) $messages as $message)
                                    <span dir="ltr" class="block font-mono opacity-80">{{ $message }}</span>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </details>
            @endif
        </div>
    @endif

    {{-- ============================================================ --}}
    {{-- ۴) فایل‌های پشتیبان                                          --}}
    {{-- ============================================================ --}}
    <div class="card flex flex-col gap-4 p-5 sm:p-6">
        <div class="flex items-center gap-3">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                <x-icon name="archive" class="size-5" />
            </div>
            <div>
                <h3 class="text-sm font-black text-zinc-900 dark:text-zinc-50">فایل‌های پشتیبان و عملیات اخیر</h3>
                <p class="text-[11px] text-zinc-400 dark:text-zinc-500">نسخه‌های ساخته‌شده را دانلود یا حذف کنید؛ گزارش عملیات بازیابی هم اینجاست.</p>
            </div>
        </div>

        @if(count($this->records) === 0)
            <p class="rounded-xl bg-zinc-50 px-4 py-8 text-center text-xs text-zinc-400 ring-1 ring-zinc-200/70 dark:bg-zinc-800/50 dark:text-zinc-500 dark:ring-zinc-700/60">
                هنوز نسخه پشتیبانی ساخته نشده است.
            </p>
        @else
            <div class="max-h-96 divide-y divide-zinc-100 overflow-y-auto rounded-xl ring-1 ring-zinc-200/70 dark:divide-zinc-800 dark:ring-zinc-700/60">
                @foreach($this->records as $record)
                    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg {{ $record->isBackup() ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400' : 'bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400' }}">
                                <x-icon :name="$record->isBackup() ? 'archive' : 'upload'" class="size-4" />
                            </span>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span dir="ltr" class="truncate font-mono text-xs font-bold text-zinc-800 dark:text-zinc-100">{{ $record->name }}{{ $record->isBackup() ? '.zip' : '' }}</span>
                                    <x-badge :variant="$record->statusVariant()" class="!px-2 !py-0.5 !text-[10px]">{{ $record->statusLabel() }}</x-badge>
                                </div>
                                <p class="mt-0.5 text-[10px] text-zinc-400 dark:text-zinc-500">
                                    {{ $record->typeLabel() }}
                                    @if($record->size > 0)
                                        · {{ bytes_human($record->size) }}
                                    @endif
                                    · {{ verta_date($record->created_at, 'Y/m/d H:i') }}
                                    @if($record->isFailed() && $record->error)
                                        · <span class="text-rose-500">{{ \Illuminate\Support\Str::limit($record->error, 90) }}</span>
                                    @endif
                                </p>
                                @if($record->isRunning() && $record->progress > 0)
                                    <div class="mt-1.5 h-1 w-40 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                        <div class="h-full rounded-full bg-brand-500 transition-all duration-500" style="width: {{ max(2, $record->progress) }}%"></div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-1.5">
                            @if($record->isBackup() && $record->isCompleted())
                                <button type="button" wire:click="download({{ $record->id }})"
                                        class="inline-flex items-center gap-1 rounded-lg bg-zinc-100 px-2.5 py-1.5 text-[11px] font-bold text-zinc-600 transition-colors hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700">
                                    <x-icon name="download" class="size-3" />
                                    دانلود
                                </button>
                            @endif
                            <button type="button" wire:click="requestDelete('backup:{{ $record->id }}')"
                                    class="inline-flex items-center gap-1 rounded-lg bg-rose-50 px-2.5 py-1.5 text-[11px] font-bold text-rose-600 ring-1 ring-rose-500/20 transition-colors hover:bg-rose-100 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20 dark:hover:bg-rose-500/20">
                                <x-icon name="trash-2" class="size-3" />
                                حذف
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ============ مودال تأیید بازیابی ============ --}}
    <x-modal wire:model="pendingRestore" title="بازیابی نسخه پشتیبان" size="md">
        <div class="space-y-4">
            <div class="flex items-start gap-3 rounded-xl bg-amber-50 p-3.5 text-sm leading-6 text-amber-800 ring-1 ring-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                <x-icon name="alert-triangle" class="mt-0.5 size-5 shrink-0" />
                <div>
                    @if($pendingRestore === 'upload')
                        بازیابی از فایل آپلودشده را تأیید می‌کنید؟ رکوردها و فایل‌های موجود
                        <strong class="font-black">رد</strong> و فقط موارد جدید
                        <strong class="font-black">درج</strong> می‌شوند.
                    @else
                        بازیابی از فایل <span dir="ltr" class="font-mono font-bold">{{ $pendingRestore }}</span> را تأیید می‌کنید؟
                        رکوردها و فایل‌های موجود <strong class="font-black">رد</strong>
                        و فقط موارد جدید <strong class="font-black">درج</strong> می‌شوند.
                    @endif
                </div>
            </div>

            <p class="text-xs leading-6 text-zinc-500 dark:text-zinc-400">
                این عملیات برای دیتابیس فعلی مخرب نیست؛ اما همیشه پیش از بازیابی، یک نسخه پشتیبان تازه از همین صفحه تهیه کنید.
                عملیات پس‌زمینه‌ای است و در پایان گزارش کامل نمایش داده می‌شود.
            </p>
        </div>

        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-btn variant="secondary" wire:click="$set('pendingRestore', null)">انصراف</x-btn>
                <x-btn icon="upload" wire:click="confirmRestore" :loading="true">
                    شروع بازیابی
                </x-btn>
            </div>
        </x-slot:footer>
    </x-modal>

    {{-- ============ مودال تأیید حذف ============ --}}
    <x-modal wire:model="pendingDelete" title="حذف فایل" size="sm">
        <div class="space-y-4">
            <div class="flex items-start gap-3 rounded-xl bg-rose-50 p-3.5 text-sm leading-6 text-rose-800 ring-1 ring-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300">
                <x-icon name="alert-triangle" class="mt-0.5 size-5 shrink-0" />
                <div>
                    حذف را تأیید می‌کنید؟ این کار فقط <strong class="font-black">فایل ZIP</strong> را حذف می‌کند
                    و هیچ داده‌ای از دیتابیس یا فایل‌های سایت تغییر نمی‌کند.
                </div>
            </div>
        </div>

        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-btn variant="secondary" wire:click="$set('pendingDelete', null)">انصراف</x-btn>
                <x-btn variant="danger" icon="trash-2" wire:click="confirmDelete" :loading="true">
                    حذف کن
                </x-btn>
            </div>
        </x-slot:footer>
    </x-modal>

</div>
