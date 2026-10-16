{{-- کارت پروژه‌ی قابل‌فروش — متغیرها: $product --}}
<a href="{{ route('shop.product', $product->slug) }}" wire:key="product-{{ $product->id }}" wire:navigate
   class="group flex flex-col overflow-hidden rounded-2xl bg-white ring-1 ring-zinc-200/80 transition-all duration-200 hover:-translate-y-1 hover:shadow-card-lg dark:bg-zinc-900 dark:ring-zinc-700/60">

    {{-- thumbnail --}}
    <div class="relative aspect-video overflow-hidden bg-zinc-100 dark:bg-zinc-800">
        <img src="{{ $product->thumbnail_url }}" alt="{{ $product->name }}"
             class="size-full object-cover transition-transform duration-300 group-hover:scale-110"
             onerror="this.style.display='none';this.parentElement.classList.add('shop-thumb')">
        @if(!$product->thumbnail)
            <div class="absolute inset-0 flex items-center justify-center">
                <x-icon name="rocket" class="size-12 text-white/80" />
            </div>
        @endif

        @if($product->latestVersion)
            <span class="absolute bottom-2 start-2 inline-flex items-center gap-1 rounded-lg bg-zinc-950/70 px-2 py-1 font-mono text-[11px] font-bold text-white backdrop-blur" dir="ltr">
                v{{ $product->latestVersion->version }}
            </span>
        @endif

        @if($product->is_effectively_free)
            <span class="absolute bottom-2 end-2 inline-flex items-center gap-1 rounded-full bg-emerald-500 px-2.5 py-1 text-[11px] font-black text-white shadow-sm">
                <x-icon name="gift" class="size-3" /> رایگان
            </span>
        @elseif($product->discount > 0)
            <span class="absolute bottom-2 end-2 inline-flex items-center gap-1 rounded-full bg-rose-500 px-2.5 py-1 text-[11px] font-black text-white shadow-sm">
                <x-icon name="tag" class="size-3" /> {{ fa_num($product->discount) }}٪ تخفیف
            </span>
        @endif
    </div>

    {{-- body --}}
    <div class="flex flex-1 flex-col gap-2.5 p-4">
        <div class="flex items-start justify-between gap-2">
            <h3 class="text-sm font-black leading-6 text-zinc-900 transition-colors group-hover:text-brand-600 dark:text-zinc-50">
                {{ $product->name }}
            </h3>
            @if($product->category)
                <span class="shrink-0 rounded-full bg-zinc-100 px-2.5 py-1 text-[10px] font-bold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                    {{ $product->category->name }}
                </span>
            @endif
        </div>

        <p class="line-clamp-2 flex-1 text-xs leading-6 text-zinc-500 dark:text-zinc-400">
            {{ \Illuminate\Support\Str::limit($product->short_description, 110) }}
        </p>

        {{-- meta --}}
        <div class="flex flex-wrap items-center gap-x-3.5 gap-y-1 text-[11px] text-zinc-500 dark:text-zinc-400">
            @if($product->rating)
                <span class="inline-flex items-center gap-1 text-amber-500">
                    <x-icon name="star" class="size-3.5 fill-amber-400" />
                    {{ fa_num($product->rating) }}
                </span>
            @endif
            <span class="inline-flex items-center gap-1">
                <x-icon name="receipt" class="size-3.5" />
                {{ fa_num($product->sales_count) }} فروش
            </span>
            <span class="inline-flex items-center gap-1">
                <x-icon name="message-square" class="size-3.5" />
                {{ fa_num($product->comments_count) }} دیدگاه
            </span>
        </div>

        <div class="mt-auto flex items-center justify-between border-t border-zinc-100 pt-3 dark:border-zinc-800">
            <span class="text-sm font-black tabular-nums text-zinc-900 dark:text-zinc-100">
                @if($product->is_effectively_free)
                    <span class="text-emerald-600 dark:text-emerald-400">رایگان</span>
                @else
                    @if($product->discount > 0)
                        <span class="me-1 text-xs font-normal text-zinc-400 line-through">{{ money($product->price) }}</span>
                    @endif
                    {{ money($product->final_price) }}
                @endif
            </span>
            <span class="inline-flex items-center gap-1 text-xs font-bold text-brand-600 transition-all group-hover:gap-2 dark:text-brand-400">
                مشاهده و خرید
                <x-icon name="arrow-left" class="size-3.5" />
            </span>
        </div>
    </div>
</a>
