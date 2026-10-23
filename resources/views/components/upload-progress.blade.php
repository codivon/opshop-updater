{{--
    نوار پیشرفت آپلود Livewire (فایل/تصویر) — نمایش درصد زنده.
    استفاده:
        <x-upload-progress target="thumbnailFile" label="در حال آپلود تصویر" />
        <x-upload-progress target="form.file" label="در حال آپلود فایل آپدیت" />
    رویدادهای واقعی Livewire 3 (روی خود input ارسال و تا window حباب می‌شوند):
        livewire-upload-start    { id, property }
        livewire-upload-progress { progress }   ← بدون نام property
        livewire-upload-finish   { id, property }
        livewire-upload-error    { id, property }
    target دقیقاً همان عبارت wire:model است (مثل «versionFile» یا «form.file»).
--}}
@props(['target' => '', 'label' => 'در حال آپلود'])

<div x-data="{ uploading: false, progress: 0 }"
     x-on:livewire-upload-start.window="if ($event.detail && $event.detail.property === @js($target)) { uploading = true; progress = 0; }"
     x-on:livewire-upload-progress.window="if (uploading && $event.detail && typeof $event.detail.progress !== 'undefined') { progress = Math.max(1, Math.round($event.detail.progress)); }"
     x-on:livewire-upload-finish.window="if ($event.detail && $event.detail.property === @js($target)) { progress = 100; setTimeout(() => { uploading = false; progress = 0; }, 700); }"
     x-on:livewire-upload-error.window="if ($event.detail && $event.detail.property === @js($target)) { uploading = false; progress = 0; }"
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
        {{-- نکته: از bg-gradient-to-l استفاده شده (نه to-r) چون CSS کامیت‌شده بدون build رشد
             می‌کند و فقط کلاس‌های موجود در بیلد قبلی رندر می‌شوند؛ to-r در بیلد نیست و
             نوار کاملاً نامرئی می‌شد. در RTL جهت پر شدن از راست است → رنگ اول سمت راست. --}}
        <div class="h-full rounded-full bg-gradient-to-l from-brand-500 to-brand-600 transition-all duration-200"
             x-bind:style="'width:' + progress + '%'"></div>
    </div>
</div>
