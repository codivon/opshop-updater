{{--
    Lightweight SVG bar chart (no JS dependency).
    <x-chart :title="$chart['title']" :series="$chart['series']" color="brand" />
    series item: ['label' => ..., 'value' => int, 'secondary' => int (tooltip)]
--}}
@props(['title' => null, 'series' => [], 'color' => 'brand', 'height' => 160, 'showValues' => true, 'secondaryFormat' => 'money'])

@php
    $colors = [
        'brand' => ['bar' => '#10b981', 'barLight' => 'rgba(16,185,129,0.35)', 'text' => 'text-brand-600 dark:text-brand-400'],
        'teal'  => ['bar' => '#14b8a6', 'barLight' => 'rgba(20,184,166,0.35)', 'text' => 'text-teal-600 dark:text-teal-400'],
    ];
    $c = $colors[$color] ?? $colors['brand'];

    $max = max(1, collect($series)->max('value') ?? 1);
    // nice ceiling (multiples of 4)
    $step = max(1, ceil($max / 4));
    $max = $step * 4;

    $count = count($series) ?: 1;
    $slotW = 100 / $count;
    $barW = $slotW * 0.45;
    $gridLines = range(0, 4);
@endphp

<div class="flex h-full flex-col">
    @if($title)
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-sm font-bold text-zinc-800 dark:text-zinc-100">{{ $title }}</h3>
            @isset($legend)
                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $legend }}</div>
            @endisset
        </div>
    @endif

    <div class="relative w-full" style="aspect-ratio: {{ 320 }}/{{ $height }};">
        {{-- grid --}}
        <div class="absolute inset-0 flex flex-col justify-between" aria-hidden="true">
            @foreach($gridLines as $i)
                @php($y = $max - $step * $i)
                <div class="flex items-center gap-2">
                    <span class="w-8 shrink-0 text-[10px] tabular-nums text-zinc-400 dark:text-zinc-500">{{ fa_num($y) }}</span>
                    <div class="h-px flex-1 bg-zinc-200/80 dark:bg-zinc-700/60"></div>
                </div>
            @endforeach
        </div>

        {{-- bars --}}
        <div class="absolute inset-0 me-0 ps-10 pb-5">
            <div class="flex h-full items-end justify-around gap-1.5">
                @foreach($series as $item)
                    <div class="group relative flex h-full flex-1 flex-col items-center justify-end">
                        @if($showValues && ($item['value'] ?? 0) > 0)
                            <span class="absolute -top-4 text-[10px] font-bold tabular-nums {{ $c['text'] }} opacity-0 transition-opacity group-hover:opacity-100">{{ fa_num($item['value']) }}</span>
                        @endif
                        <div class="w-full max-w-10 rounded-t-lg transition-all duration-300 group-hover:opacity-80"
                             style="height: {{ max(2, ($item['value'] ?? 0) / $max * 100) }}%; background: linear-gradient(to top, {{ $c['barLight'] }}, {{ $c['bar'] }});"
                             title="{{ $item['label'] }}: {{ fa_num($item['value']) }}{{ isset($item['secondary']) ? ' / ' . ($secondaryFormat === 'money' ? money($item['secondary']) : fa_num($item['secondary'])) : '' }}">
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- labels --}}
        <div class="absolute inset-x-0 bottom-0 ps-10">
            <div class="flex justify-around gap-1.5">
                @foreach($series as $item)
                    <div class="flex-1 truncate text-center text-[10px] text-zinc-500 dark:text-zinc-400" title="{{ $item['label'] }}">{{ $item['label'] }}</div>
                @endforeach
            </div>
        </div>
    </div>
</div>
