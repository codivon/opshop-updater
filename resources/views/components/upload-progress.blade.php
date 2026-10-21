{{--
    نوار پیشرفت آپلود Livewire (فایل/تصویر) — نمایش درصد زنده.
    استفاده:
        <x-upload-progress target="thumbnailFile" label="در حال آپلود تصویر" />
    رویدادهای Livewire 3: upload-progress / upload-finished / upload-error (detail.name = نام پراپرتی)
--}}
@props(['target' => '', 'label' => 'در حال آپلود'])

<div x-data="{ uploading: false, progress: 0 }"
     x-on:upload-progress.window="if ($event.detail.name === @js($target)) { uploading = true; progress = Math.round($event.detail.progress); }"
     x-on:upload-finished.window="if ($event.detail.name === @js($target)) { progress = 100; setTimeout(() => { uploading = false; progress = 0; }, 600); }"
     x-on:upload-error.window="if ($event.detail.name === @js($target)) { uploading = false; progress = 0; }"
     x-show="uploading"
     x-cloak
     {{ $attributes->merge(['class' => 'mt-2']) }}
     role="status" aria-live="polite">
    <div class="flex items-center justify-between gap-2 text-xs font-bold text-brand-600 dark:text-brand-400">
        <span class="flex items-center gap-1.5">
            <x-icon name="loader" class="size-3.5 animate-spin" />
            {{ $label }}…
        </span>
        <span class="tabular-nums" x-text="progress + '٪'">۰٪</span>
    </div>
    <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-zinc-200/80 dark:bg-zinc-700/60">
        <div class="h-full rounded-full bg-gradient-to-r from-brand-500 to-brand-600 transition-[width] duration-200"
             x-bind:style="'width:' + progress + '%'"></div>
    </div>
</div>
