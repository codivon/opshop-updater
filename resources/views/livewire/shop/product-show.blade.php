<div class="space-y-8">

    {{-- ============ Breadcrumb ============ --}}
    <nav aria-label="مسیر" class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
        <a href="{{ route('shop.home') }}" wire:navigate class="transition-colors hover:text-brand-600 dark:hover:text-brand-400">فروشگاه</a>
        <x-icon name="chevron-left" class="size-3" />
        <span class="text-zinc-400">پروژه‌ها</span>
        <x-icon name="chevron-left" class="size-3" />
        <span class="truncate font-bold text-zinc-700 dark:text-zinc-200">{{ $product->name }}</span>
    </nav>

    {{-- ============ Hero ============ --}}
    <section class="grid grid-cols-1 gap-6 lg:grid-cols-5">

        {{-- thumbnail --}}
        <div class="lg:col-span-3">
            <div class="card relative aspect-video overflow-hidden p-0">
                <img src="{{ $product->thumbnail_url }}" alt="{{ $product->name }}"
                     class="size-full object-cover"
                     onerror="this.style.display='none';this.parentElement.classList.add('shop-thumb')">
                @if(!$product->thumbnail)
                    <div class="absolute inset-0 flex items-center justify-center">
                        <x-icon name="rocket" class="size-16 text-white/80" />
                    </div>
                @endif

                @if($product->latestVersion)
                    <span class="absolute bottom-3 start-3 inline-flex items-center gap-1 rounded-lg bg-zinc-950/70 px-2.5 py-1 font-mono text-xs font-bold text-white backdrop-blur" dir="ltr">
                        v{{ $product->latestVersion->version }}
                    </span>
                @endif
            </div>
        </div>

        {{-- info + buy box --}}
        <div class="space-y-4 lg:col-span-2">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    @if($product->category)
                        <a href="{{ route('shop.home') }}?tab=products&productCategory={{ $product->category_id }}" wire:navigate
                           class="transition-transform hover:scale-105">
                            <x-badge variant="primary" icon="tag">{{ $product->category->name }}</x-badge>
                        </a>
                    @endif
                    @if($product->is_free || $product->final_price <= 0)
                        <x-badge variant="success" icon="gift">رایگان</x-badge>
                    @elseif($product->discount > 0)
                        <x-badge variant="danger" icon="tag">{{ fa_num($product->discount) }}٪ تخفیف</x-badge>
                    @endif
                </div>

                <h1 class="mt-3 text-2xl font-black leading-relaxed text-zinc-900 dark:text-zinc-50">
                    {{ $product->name }}
                </h1>

                {{-- meta row --}}
                <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-zinc-500 dark:text-zinc-400">
                    @if($product->rating)
                        <span class="inline-flex items-center gap-1 font-bold text-amber-500">
                            <x-icon name="star" class="size-4 fill-amber-400" />
                            {{ fa_num($product->rating) }}
                            <span class="font-normal text-zinc-400">({{ fa_num($product->approvedCommentsCount()) }} دیدگاه)</span>
                        </span>
                    @endif
                    <span class="inline-flex items-center gap-1"><x-icon name="receipt" class="size-3.5" /> {{ fa_num($product->sales_count) }} فروش</span>
                    <span class="inline-flex items-center gap-1"><x-icon name="eye" class="size-3.5" /> {{ fa_num($product->views_count) }} بازدید</span>
                    <span class="inline-flex items-center gap-1"><x-icon name="layers" class="size-3.5" /> {{ fa_num($product->versions()->count()) }} نسخه</span>
                </div>
            </div>

            @if($product->short_description)
                <p class="whitespace-pre-line rounded-2xl bg-white p-4 text-sm leading-7 text-zinc-600 ring-1 ring-zinc-200/80 dark:bg-zinc-900 dark:text-zinc-300 dark:ring-zinc-700/60">
                    {{ $product->short_description }}
                </p>
            @endif

            {{-- ============ Buy box ============ --}}
            <div class="card space-y-4 p-5 sm:p-6">
                <div class="flex items-end justify-between">
                    <div>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">قیمت پروژه</p>
                        <div class="mt-1 flex flex-wrap items-baseline gap-2">
                            @if($product->is_effectively_free)
                                <span class="text-2xl font-black text-emerald-600 dark:text-emerald-400">رایگان</span>
                            @elseif($this->appliedDiscount)
                                @php($applied = $this->appliedDiscount)
                                @if($product->discount > 0)
                                    <span class="text-sm text-zinc-400 line-through">{{ money($product->price) }}</span>
                                @endif
                                <span class="text-sm text-zinc-400 line-through">{{ money($product->final_price) }}</span>
                                @if($applied['final'] <= 0)
                                    <span class="text-2xl font-black text-emerald-600 dark:text-emerald-400">رایگان با کد تخفیف</span>
                                @else
                                    <span class="text-2xl font-black tabular-nums text-emerald-600 dark:text-emerald-400">{{ money($applied['final']) }}</span>
                                @endif
                            @else
                                @if($product->discount > 0)
                                    <span class="text-sm text-zinc-400 line-through">{{ money($product->price) }}</span>
                                @endif
                                <span class="text-2xl font-black tabular-nums text-zinc-900 dark:text-zinc-50">{{ money($product->final_price) }}</span>
                            @endif
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1 rounded-xl bg-zinc-100 px-2.5 py-1 text-[11px] font-bold text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                        <x-icon name="shield-check" class="size-3.5" />
                        بدون نیاز به ثبت‌نام
                    </span>
                </div>

                @php($appliedDiscount = $this->appliedDiscount)
                @php($productFree = $product->is_effectively_free || ($appliedDiscount !== null && $appliedDiscount['final'] <= 0))

                {{-- کد تخفیف --}}
                @include('livewire.shop.partials.discount-field', ['baseAmount' => (int) $product->final_price])

                @unless($productFree)
                    @include('livewire.shop.partials.gateway-picker', ['gateways' => $this->gateways])
                @endunless

                <x-btn wire:click="buy" :loading="true" size="lg" class="w-full"
                       :icon="$productFree ? 'download' : 'credit-card'">
                    @if($productFree)
                        {{ $appliedDiscount !== null ? 'دریافت رایگان با کد تخفیف' : 'دریافت رایگان و دانلود' }}
                    @else
                        خرید و دانلود فوری
                    @endif
                </x-btn>

                <p class="text-center text-[11px] leading-5 text-zinc-400">
                    پس از پرداخت، مستقیماً به صفحه دانلود هدایت می‌شوید.
                </p>
            </div>
        </div>
    </section>

    {{-- ============ Description ============ --}}
    <section class="card p-5 sm:p-6" id="description">
        <h2 class="flex items-center gap-2 text-lg font-black text-zinc-900 dark:text-zinc-50">
            <x-icon name="file-text" class="size-5 text-brand-600" />
            توضیحات پروژه
        </h2>
        @if($product->description)
            <div class="rich-content mt-4 text-sm leading-8 text-zinc-700 dark:text-zinc-300">
                {!! $product->description !!}
            </div>
        @else
            <div class="mt-4">
                <x-empty icon="file-text" title="توضیحاتی ثبت نشده" />
            </div>
        @endif
    </section>

    {{-- ============ Versions ============ --}}
    @if($this->versions->count())
        <section class="card p-5 sm:p-6" id="versions">
            <h2 class="flex items-center gap-2 text-lg font-black text-zinc-900 dark:text-zinc-50">
                <x-icon name="layers" class="size-5 text-brand-600" />
                نسخه‌ها و تغییرات
            </h2>

            <div class="mt-5 space-y-4">
                @foreach($this->versions as $version)
                    <div class="rounded-2xl bg-zinc-50 p-4 ring-1 ring-zinc-200/70 dark:bg-zinc-800/50 dark:ring-zinc-700/50" wire:key="shpver-{{ $version->id }}">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-lg bg-zinc-950 px-2.5 py-1 font-mono text-xs font-bold text-white dark:bg-zinc-700" dir="ltr">v{{ $version->version }}</span>
                                <x-badge variant="neutral">{{ $version::TYPE_LABELS[$version->type] ?? $version->type }}</x-badge>
                                @if($version->is_mandatory)
                                    <x-badge variant="danger" icon="alert-triangle">اجباری</x-badge>
                                @endif
                            </div>
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                                @if($version->release_date)
                                    <span class="inline-flex items-center gap-1"><x-icon name="calendar-clock" class="size-3.5" /> {{ verta_date($version->release_date) }}</span>
                                @endif
                                <span class="inline-flex items-center gap-1"><x-icon name="database" class="size-3.5" /> {{ $version->file_size_human }}</span>
                            </div>
                        </div>

                        @if($version->changelog)
                            <div class="mt-3 whitespace-pre-line text-sm leading-7 text-zinc-600 dark:text-zinc-300">{{ $version->changelog }}</div>
                        @endif

                        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                            @if($version->what_added)
                                <div class="rounded-xl bg-emerald-50/70 p-3 text-xs leading-6 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300">
                                    <p class="mb-1 font-black">✚ اضافه شد</p>
                                    <p class="whitespace-pre-line">{{ $version->what_added }}</p>
                                </div>
                            @endif
                            @if($version->what_changed)
                                <div class="rounded-xl bg-amber-50/70 p-3 text-xs leading-6 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
                                    <p class="mb-1 font-black">⟳ تغییر کرد</p>
                                    <p class="whitespace-pre-line">{{ $version->what_changed }}</p>
                                </div>
                            @endif
                            @if($version->what_fixed)
                                <div class="rounded-xl bg-brand-50/70 p-3 text-xs leading-6 text-brand-800 dark:bg-brand-500/10 dark:text-brand-300">
                                    <p class="mb-1 font-black">✓ رفع شد</p>
                                    <p class="whitespace-pre-line">{{ $version->what_fixed }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ============ Comments ============ --}}
    <section>
        <livewire:shop.comment-section :model="$product" label="محصول" :key="'cs-product-'.$product->id" />
    </section>
</div>
