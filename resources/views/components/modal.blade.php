{{--
    Livewire modal – visibility entangled with a Livewire property.

    <x-modal wire:model="showModal" title="ایجاد پکیج" size="lg">
        ... form ...
        <x-slot:footer> ... </x-slot:footer>
    </x-modal>
--}}
@props(['model' => null, 'title' => null, 'subtitle' => null, 'size' => 'md'])

@php
    // Accept both model="prop" and wire:model="prop"
    $prop = $model ?? $attributes->get('wire:model') ?? $attributes->get('wire:model.live') ?? 'showModal';

    $sizes = [
        'sm' => 'max-w-md',
        'md' => 'max-w-lg',
        'lg' => 'max-w-2xl',
        'xl' => 'max-w-4xl',
        'full' => 'max-w-6xl',
    ];

    // Prevent prop passthrough on the wrapper
    $rootAttrs = $attributes->exceptProps(['model', 'wire:model', 'wire:model.live'])->filter(fn ($v, $k) => ! str_starts_with($k, 'wire:model'));
@endphp

<div
    x-data="{ open: $wire.entangle('{{ $prop }}') }"
    x-cloak
    x-show="open"
    x-effect="document.body.classList.toggle('overflow-hidden', open)"
    {{ $rootAttrs }}
    class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
    role="dialog"
    aria-modal="true"
    @keydown.escape.window="open = false"
>
    {{-- backdrop --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        class="absolute inset-0 bg-zinc-950/50 backdrop-blur-sm"
        @click="open = false"
        aria-hidden="true"
    ></div>

    {{-- dialog --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        class="relative flex w-full {{ $sizes[$size] ?? $sizes['md'] }} max-h-[90vh] flex-col overflow-clip rounded-2xl bg-white shadow-pop ring-1 ring-zinc-900/5 dark:bg-zinc-900 dark:ring-white/10"
    >
        @if($title || isset($header))
            <header class="flex items-start justify-between gap-4 border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
                <div class="min-w-0">
                    <h2 class="text-base font-bold text-zinc-900 dark:text-zinc-100">{{ $title }}</h2>
                    @if($subtitle)
                        <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $subtitle }}</p>
                    @endif
                    {{ $header ?? '' }}
                </div>
                <button type="button" @click="open = false"
                        class="rounded-lg p-1.5 text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                        aria-label="بستن">
                    <x-icon name="x" class="size-5" />
                </button>
            </header>
        @endif

        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5">
            {{ $slot }}
        </div>

        @isset($footer)
            <footer class="flex flex-wrap items-center justify-end gap-2 border-t border-zinc-100 bg-zinc-50/60 px-5 py-3.5 dark:border-zinc-800 dark:bg-zinc-800/40">
                {{ $footer }}
            </footer>
        @endisset
    </div>
</div>
