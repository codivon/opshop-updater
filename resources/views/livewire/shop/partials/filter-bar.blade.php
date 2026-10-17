{{--
    نوار فیلتر مدرن فروشگاه (مشترک بین صفحه پکیج‌ها و پروژه‌ها).
    خواص موردنیاز در کامپوننت: search، category، priceFilter، sort
    + computed categories (با packages_count / products_count) و sortOptions.
    <x-shop.filter-bar /> داخل کامپوننت صدا زده می‌شود: @include با $type (package|product)
--}}
@php
    $type = $type ?? 'package';
    $countKey = $type === 'package' ? 'packages_count' : 'products_count';
    $placeholder = $type === 'package' ? 'جستجوی پکیج…' : 'جستجوی پروژه…';
    $hasActive = $search !== '' || $category !== '' || $priceFilter !== '' || $sort !== 'newest';
@endphp

<div class="rounded-2xl bg-white p-3 shadow-xs ring-1 ring-zinc-200/80 dark:bg-zinc-900 dark:ring-zinc-700/60 sm:p-4">

    {{-- ============ ردیف ۱: جستجو + قیمت + مرتب‌سازی ============ --}}
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center">

        {{-- search --}}
        <div class="relative flex-1">
            <x-icon name="search" class="pointer-events-none absolute start-3.5 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
            <input type="search"
                   wire:model.live.debounce.400ms="search"
                   placeholder="{{ $placeholder }}"
                   class="input ps-10 pe-9"
                   aria-label="جستجو" />
            @if($search !== '')
                <button type="button" wire:click="$set('search','')"
                        class="absolute end-2.5 top-1/2 -translate-y-1/2 rounded-lg p-1 text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-800"
                        aria-label="پاک کردن جستجو">
                    <x-icon name="x" class="size-3.5" />
                </button>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-3">

            {{-- فیلتر قیمت — سگمنت --}}
            <div class="inline-flex rounded-xl bg-zinc-100 p-1 dark:bg-zinc-800" role="group" aria-label="فیلتر قیمت">
                <button type="button" wire:click="$set('priceFilter','')"
                        @class(['cursor-pointer rounded-lg px-3 py-1.5 text-xs font-bold transition-all',
                            $priceFilter === '' ? 'bg-white text-zinc-900 shadow-xs dark:bg-zinc-700 dark:text-zinc-100' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'])
                        aria-pressed="{{ $priceFilter === '' ? 'true' : 'false' }}">
                    همه
                </button>
                <button type="button" wire:click="$set('priceFilter','free')"
                        @class(['cursor-pointer rounded-lg px-3 py-1.5 text-xs font-bold transition-all',
                            $priceFilter === 'free' ? 'bg-emerald-500 text-white shadow-xs' : 'text-zinc-500 hover:text-emerald-600 dark:hover:text-emerald-400'])
                        aria-pressed="{{ $priceFilter === 'free' ? 'true' : 'false' }}">
                    رایگان
                </button>
                <button type="button" wire:click="$set('priceFilter','paid')"
                        @class(['cursor-pointer rounded-lg px-3 py-1.5 text-xs font-bold transition-all',
                            $priceFilter === 'paid' ? 'bg-brand-600 text-white shadow-xs' : 'text-zinc-500 hover:text-brand-600 dark:hover:text-brand-400'])
                        aria-pressed="{{ $priceFilter === 'paid' ? 'true' : 'false' }}">
                    پولی
                </button>
            </div>

            {{-- مرتب‌سازی --}}
            <div class="relative">
                <x-icon name="arrow-up-down" class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
                <select wire:model.live="sort" class="input min-w-36 ps-9 pe-8" aria-label="مرتب‌سازی">
                    @foreach($this->sortOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- ============ ردیف ۲: چیپ دسته‌بندی‌ها ============ --}}
    @if($this->categories->count())
        <div class="mt-3 flex items-center gap-2 overflow-x-auto pb-1 [scrollbar-width:thin]" role="group" aria-label="دسته‌بندی‌ها">
            <button type="button" wire:click="$set('category','')"
                    @class(['flex shrink-0 cursor-pointer items-center gap-1.5 rounded-full px-4 py-2 text-xs font-bold transition-all',
                        $category === ''
                            ? 'bg-zinc-900 text-white shadow-sm dark:bg-zinc-100 dark:text-zinc-900'
                            : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700'])
                    aria-pressed="{{ $category === '' ? 'true' : 'false' }}">
                <x-icon name="layout-grid" class="size-3.5" />
                همه
            </button>

            @foreach($this->categories as $cat)
                @php($chipActive = (string) $category === (string) $cat->id)
                <button type="button" wire:click="$set('category','{{ $cat->id }}')"
                        @class(['flex shrink-0 cursor-pointer items-center gap-1.5 rounded-full px-4 py-2 text-xs font-bold transition-all',
                            $chipActive
                                ? 'bg-zinc-900 text-white shadow-sm dark:bg-zinc-100 dark:text-zinc-900'
                                : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700'])
                        aria-pressed="{{ $chipActive ? 'true' : 'false' }}">
                    @if(filled($cat->hierarchy_label ?? null) && $cat->hierarchy_label !== $cat->name)
                        {{-- زیردسته: نمایش مسیر والد + نام --}}
                        <span class="opacity-60">{{ \Illuminate\Support\Str::beforeLast($cat->hierarchy_label, ' › ') }}</span>
                        <x-icon name="corner-up-left" class="size-3 opacity-50" aria-hidden="true" />
                    @endif
                    {{ $cat->name }}
                    <span @class(['rounded-full px-1.5 text-[10px] tabular-nums',
                            $chipActive ? 'bg-white/20 text-white/80' : 'bg-zinc-200/80 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400'
                        ])>{{ fa_num($cat->shop_count ?? $cat->$countKey) }}</span>
                </button>
            @endforeach
        </div>
    @endif

    {{-- ============ ردیف ۳: شمارش نتایج + پاک‌سازی ============ --}}
    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-zinc-100 pt-3 dark:border-zinc-700/50">
        <p class="text-xs text-zinc-500 dark:text-zinc-400">
            {{ fa_num($this->records->total()) }} نتیجه
        </p>
        @if($hasActive)
            <button type="button" wire:click="clearFilters"
                    class="inline-flex cursor-pointer items-center gap-1 rounded-lg bg-rose-50 px-2.5 py-1 text-[11px] font-bold text-rose-600 transition-colors hover:bg-rose-100 dark:bg-rose-500/10 dark:text-rose-400 dark:hover:bg-rose-500/20">
                <x-icon name="x" class="size-3" />
                پاک‌سازی فیلترها
            </button>
        @endif
    </div>
</div>
