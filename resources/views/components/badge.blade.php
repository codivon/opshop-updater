{{--
    Status / meta badge.
    variants: success | warning | danger | info | neutral | primary
    <x-badge variant="success" icon="check">فعال</x-badge>
--}}
@props(['variant' => 'neutral', 'icon' => null])

@php
    $variants = [
        'success' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-400/20',
        'warning' => 'bg-amber-50 text-amber-700 ring-amber-600/25 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-400/25',
        'danger'  => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20',
        'info'    => 'bg-teal-50 text-teal-700 ring-teal-600/25 dark:bg-teal-500/10 dark:text-teal-400 dark:ring-teal-400/25',
        'primary' => 'bg-brand-50 text-brand-700 ring-brand-600/20 dark:bg-brand-500/10 dark:text-brand-400 dark:ring-brand-400/20',
        'violet'  => 'bg-violet-50 text-violet-700 ring-violet-600/20 dark:bg-violet-500/10 dark:text-violet-400 dark:ring-violet-400/20',
        'neutral' => 'bg-zinc-100 text-zinc-600 ring-zinc-500/20 dark:bg-zinc-700/40 dark:text-zinc-300 dark:ring-zinc-400/20',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset whitespace-nowrap '.$variants[$variant]]) }}>
    @if($icon)
        <x-icon :name="$icon" class="size-3" />
    @endif
    {{ $slot }}
</span>
