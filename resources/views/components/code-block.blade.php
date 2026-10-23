{{--
    بلوک کد با برچسب و دکمهٔ کپی — برای مستندات API.
    <x-code-block label="نمونهٔ درخواست (cURL)" :code="$endpoint['request']" />
    <x-code-block label="پاسخ" :code="$endpoint['response']" collapsible />
--}}
@props([
    'label' => 'کد',
    'code' => '',
    'collapsible' => false, // محتوای بلند: با details باز/بسته می‌شود
])

<div x-data="{ copied: false }" {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl ring-1 ring-zinc-200 dark:ring-zinc-700/70']) }}>
    <div class="flex items-center justify-between gap-2 bg-zinc-100/90 px-3.5 py-2 dark:bg-zinc-800/90">
        <span class="flex items-center gap-1.5 text-[11px] font-bold text-zinc-500 dark:text-zinc-400">
            <x-icon name="terminal" class="size-3.5" />
            {{ $label }}
        </span>
        <button type="button"
                @click="navigator.clipboard.writeText($refs.code.innerText.trim()).then(() => { copied = true; $dispatch('toast', {message: 'کپی شد', type: 'success'}); setTimeout(() => copied = false, 1600) })"
                class="inline-flex cursor-pointer items-center gap-1 rounded-lg px-2 py-1 text-[11px] font-bold text-zinc-500 transition-colors hover:bg-zinc-200/70 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700/60 dark:hover:text-zinc-100">
            <x-icon name="copy" class="size-3.5" x-show="! copied" />
            <x-icon name="check" class="size-3.5" x-show="copied" x-cloak />
            <span x-text="copied ? 'کپی شد' : 'کپی'">کپی</span>
        </button>
    </div>

    @if($collapsible)
        <details>
            <summary class="cursor-pointer select-none bg-zinc-50 px-3.5 py-1.5 text-[11px] font-bold text-zinc-400 transition-colors hover:text-zinc-600 dark:bg-zinc-900/60 dark:text-zinc-500 dark:hover:text-zinc-300">
                نمایش محتوا
            </summary>
            <pre x-ref="code" dir="ltr"
                class="max-h-80 overflow-auto bg-zinc-50 p-4 text-left text-[12.5px] leading-6 text-zinc-800 dark:bg-zinc-900/80 dark:text-zinc-200"><code>{{ $code }}</code></pre>
        </details>
    @else
        <pre x-ref="code" dir="ltr"
            class="max-h-96 overflow-auto bg-zinc-50 p-4 text-left text-[12.5px] leading-6 text-zinc-800 dark:bg-zinc-900/80 dark:text-zinc-200"><code>{{ $code }}</code></pre>
    @endif
</div>
