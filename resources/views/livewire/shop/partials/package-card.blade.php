{{-- کارت پکیج فروشگاه — متغیرها: $package، $isCovered (اختیاری)، $showProject (اختیاری — نشان نام پروژه، صفحهٔ اصلی و فروشگاه) --}}
@php($isCovered = $isCovered ?? false)
@php($showProject = $showProject ?? false)
{{-- قیمت نمایشی = ارزان‌ترین «طرحِ پولی» فعال؛ طرح‌های رایگان (مثل «طرح آغازین») نباید
     کل پکیج را رایگان جلوه دهند — پکیج فقط وقتی رایگان است که نه طرح پولی داشته باشد و
     نه قیمت پیش‌فرضش بیش از صفر باشد (هم‌ساز با منطق صفحهٔ تک‌پکیج) --}}
@php($paidPrices = $package->activePricingPlans->filter(fn ($p) => $p->final_price > 0)->map->final_price)
@php($isFreePkg = $package->is_free || ($paidPrices->isEmpty() && (int) $package->default_price <= 0))
@php($minPrice = $paidPrices->isNotEmpty() ? $paidPrices->min() : $package->default_price)
<a href="{{ route('shop.package', $package->slug) }}" wire:key="pkg-{{ $package->id }}" wire:navigate
   class="group flex flex-col overflow-hidden rounded-2xl bg-white ring-1 ring-zinc-200/80 transition-all duration-200 hover:-translate-y-1 hover:shadow-card-lg dark:bg-zinc-900 dark:ring-zinc-700/60">

    {{-- thumbnail --}}
    <div class="relative aspect-video overflow-hidden bg-zinc-100 dark:bg-zinc-800">
        @if($package->thumbnail_url && $package->thumbnail_url !== asset('images/package-default.png'))
            <img src="{{ $package->thumbnail_url }}" alt="{{ $package->name }}"
                 class="size-full object-cover transition-transform duration-300 group-hover:scale-110" />
        @else
            <div class="shop-thumb flex size-full items-center justify-center transition-transform duration-300 group-hover:scale-110">
                <x-icon name="package" class="size-12 text-white/80" />
            </div>
        @endif

        {{-- نام پروژهٔ هدف پکیج (صفحهٔ اصلی — Task 71) --}}
        @if($showProject && $package->project)
            <span class="absolute top-2 start-2 inline-flex max-w-[70%] items-center gap-1 rounded-lg bg-white/90 px-2 py-1 text-[10px] font-black text-brand-700 shadow-sm backdrop-blur dark:bg-zinc-950/80 dark:text-brand-400"
                  title="مخصوص پروژهٔ {{ $package->project->name }}">
                <x-icon name="rocket" class="size-3 shrink-0" />
                <span class="truncate">{{ $package->project->name }}</span>
            </span>
        @endif

        {{-- version badge --}}
        @if($package->latestVersion)
            <span class="absolute bottom-2 start-2 inline-flex items-center gap-1 rounded-lg bg-zinc-950/70 px-2 py-1 font-mono text-[11px] font-bold text-white backdrop-blur" dir="ltr">
                v{{ $package->latestVersion->version }}
            </span>
        @endif

        {{-- free / subscription badges — «رایگان» فقط برای پکیج واقعاً رایگان --}}
        @if($isCovered || $isFreePkg)
            <div class="absolute bottom-2 end-2 flex flex-col items-end gap-1">
                @if($isFreePkg)
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500 px-2.5 py-1 text-[11px] font-black text-white shadow-sm">
                        <x-icon name="gift" class="size-3" /> رایگان
                    </span>
                @endif
                @if($isCovered)
                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-500 px-2.5 py-1 text-[11px] font-black text-white shadow-sm">
                        <x-icon name="crown" class="size-3" /> رایگان با اشتراک
                    </span>
                @endif
            </div>
        @endif
    </div>

    {{-- body --}}
    <div class="flex flex-1 flex-col gap-2.5 p-4">
        <div class="flex items-start justify-between gap-2">
            <h3 class="text-sm font-black leading-6 text-zinc-900 transition-colors group-hover:text-brand-600 dark:text-zinc-50">
                {{ $package->name }}
            </h3>
            @if($package->categoryModel)
                <span class="shrink-0 rounded-full bg-zinc-100 px-2.5 py-1 text-[10px] font-bold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                    {{ $package->categoryModel->name }}
                </span>
            @endif
        </div>

        <p class="line-clamp-2 flex-1 text-xs leading-6 text-zinc-500 dark:text-zinc-400">
            {{ \Illuminate\Support\Str::limit($package->short_description, 110) }}
        </p>

        <div class="mt-auto flex items-center justify-between border-t border-zinc-100 pt-3 dark:border-zinc-800">
            <span class="text-sm font-black tabular-nums text-zinc-900 dark:text-zinc-100">
                @if($isCovered)
                    <span class="inline-flex items-center gap-1 text-amber-600 dark:text-amber-400">
                        <x-icon name="crown" class="size-3.5" /> رایگان با اشتراک
                    </span>
                @elseif($isFreePkg)
                    <span class="text-emerald-600 dark:text-emerald-400">رایگان</span>
                @else
                    {{-- چند طرح پولی → «از» + ارزان‌ترین (ارزان‌ترین طرح پولی) --}}
                    @if($paidPrices->count() > 1)<span class="text-[10px] font-medium text-zinc-400">از </span>@endif{{ money($minPrice) }}
                @endif
            </span>
            <span class="inline-flex items-center gap-1 text-xs font-bold text-brand-600 transition-all group-hover:gap-2 dark:text-brand-400">
                مشاهده و خرید
                <x-icon name="arrow-left" class="size-3.5" />
            </span>
        </div>
    </div>
</a>
