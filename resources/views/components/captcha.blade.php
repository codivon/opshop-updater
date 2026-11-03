{{--
    کپچای خودمیزبان — «تصویر PNG» سؤال امنیتی (CaptchaImageRenderer) برای فرم‌های Livewire و کلاسیک.

    چرا تصویر؟ سؤال متنی در HTML با اسکریپت (regex + محاسبه) قابل حل است؛ تصویرِ دارای
    نویز/چرخش نیاز به OCR دارد. پاسخ فقط در سشن سرور است و هیچ‌جا در HTML نمی‌آید.
    تصویر و کادر آن قابل کلیک‌اند = «سؤال دیگر».

    استفاده در کامپوننت Livewire (با متد refreshCaptcha() روی همان کامپوننت):
        <x-captcha wireTarget="captcha" refreshWire="refreshCaptcha" />

    استفاده در فرم کلاسیک HTTP (ورود/ثبت‌نام مدیر):
        <x-captcha name="captcha" />
--}}
@props([
    'name'        => 'captcha', // نام فیلد در فرم کلاسیک + کلید خطا
    'wireTarget'  => null,      // اگر ست شود، input با wire:model به این پراپرتی بسته می‌شود
    'refreshWire' => null,      // متد عمومی کامپوننت Livewire برای تازه‌سازی سؤال (به‌جای لینک کامل)
    'label'       => 'سؤال امنیتی',
])

@php($version = \App\Services\CaptchaService::version())

<div {{ $attributes->merge(['class' => 'space-y-1.5']) }}>
    <div class="flex items-center justify-between gap-2 rounded-xl bg-zinc-50 px-3.5 py-2.5 ring-1 ring-zinc-200/70 dark:bg-zinc-800/60 dark:ring-zinc-700/60">
        <span class="flex min-w-0 items-center gap-2 text-sm">
            <x-icon name="shield-check" class="size-4.5 shrink-0 text-brand-600 dark:text-brand-400" />
            <span class="font-medium text-zinc-600 dark:text-zinc-300">{{ $label }}:</span>
        </span>

        {{-- تصویر سؤال — قابل کلیک برای سؤال دیگر؛ v= نسخهٔ سشن برای cache-busting --}}
        @if($refreshWire)
            <button type="button" wire:click="{{ $refreshWire }}" wire:loading.attr="disabled"
                    class="inline-flex shrink-0 cursor-pointer items-center rounded-lg bg-white p-1 ring-1 ring-zinc-200/80 transition-opacity hover:opacity-75 dark:bg-white dark:ring-zinc-300/40"
                    title="سؤال دیگر — برای خوانایی کلیک کنید">
                <img src="{{ route('captcha.image') }}?v={{ $version }}"
                     alt="تصویر سؤال امنیتی؛ دو عدد و عملگر را بخوانید"
                     width="120" height="38"
                     class="block h-9 w-auto select-none" draggable="false" />
            </button>
        @else
            <a href="{{ route('captcha.refresh') }}"
               class="inline-flex shrink-0 items-center rounded-lg bg-white p-1 ring-1 ring-zinc-200/80 transition-opacity hover:opacity-75 dark:bg-white dark:ring-zinc-300/40"
               title="سؤال دیگر — برای خوانایی کلیک کنید">
                <img src="{{ route('captcha.image') }}?v={{ $version }}"
                     alt="تصویر سؤال امنیتی؛ دو عدد و عملگر را بخوانید"
                     width="120" height="38"
                     class="block h-9 w-auto select-none" draggable="false" />
            </a>
        @endif

        @if($refreshWire)
            <button type="button" wire:click="{{ $refreshWire }}" wire:loading.attr="disabled"
                    class="inline-flex shrink-0 cursor-pointer items-center gap-1 rounded-lg px-2 py-1 text-[11px] font-bold text-zinc-500 transition-colors hover:bg-zinc-200/60 hover:text-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-700/60 dark:hover:text-zinc-200"
                    title="سؤال دیگر">
                <x-icon name="refresh-cw" class="size-3.5" />
                سؤال دیگر
            </button>
        @else
            <a href="{{ route('captcha.refresh') }}"
               class="inline-flex shrink-0 items-center gap-1 rounded-lg px-2 py-1 text-[11px] font-bold text-zinc-500 transition-colors hover:bg-zinc-200/60 hover:text-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-700/60 dark:hover:text-zinc-200"
               title="سؤال دیگر">
                <x-icon name="refresh-cw" class="size-3.5" />
                سؤال دیگر
            </a>
        @endif
    </div>

    @if($wireTarget)
        <x-input wire:model="{{ $wireTarget }}" inputmode="numeric" placeholder="پاسخ را بنویسید…"
                 autocomplete="off" :class="$errors->has($name) ? 'input-error' : ''" />
    @else
        <x-input name="{{ $name }}" inputmode="numeric" placeholder="پاسخ را بنویسید…"
                 autocomplete="off" required :class="$errors->has($name) ? 'input-error' : ''" />
    @endif

    @error($name)
        <p class="text-xs font-bold text-rose-600 dark:text-rose-400">{{ $message }}</p>
    @enderror
</div>
