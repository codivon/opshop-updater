<div class="space-y-8">

    {{-- ============ Breadcrumb ============ --}}
    <nav aria-label="مسیر" class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
        <a href="{{ route('shop.home') }}" wire:navigate class="transition-colors hover:text-brand-600 dark:hover:text-brand-400">فروشگاه</a>
        <x-icon name="chevron-left" class="size-3" />
        <span class="text-zinc-400">قالب‌ها</span>
        <x-icon name="chevron-left" class="size-3" />
        <span class="truncate font-bold text-zinc-700 dark:text-zinc-200">{{ $template->name }}</span>
    </nav>

    {{-- ============ Hero ============ --}}
    <section class="grid grid-cols-1 gap-6 lg:grid-cols-5">

        {{-- thumbnail --}}
        <div class="lg:col-span-3">
            <div class="card relative aspect-video overflow-hidden p-0">
                <img src="{{ $template->thumbnail_url }}" alt="{{ $template->name }}"
                     class="size-full object-cover"
                     onerror="this.style.display='none';this.parentElement.classList.add('shop-thumb')">
                @if(!$template->thumbnail)
                    <div class="absolute inset-0 flex items-center justify-center">
                        <x-icon name="layout-template" class="size-16 text-white/80" />
                    </div>
                @endif

                @if($template->latestVersion)
                    <span class="absolute bottom-3 start-3 inline-flex items-center gap-1 rounded-lg bg-zinc-950/70 px-2.5 py-1 font-mono text-xs font-bold text-white backdrop-blur" dir="ltr">
                        v{{ $template->latestVersion->version }}
                    </span>
                @endif
            </div>
        </div>

        {{-- info + buy box --}}
        <div class="space-y-4 lg:col-span-2">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    @if($template->category)
                        <a href="{{ route('shop.templates') }}?category={{ $template->category_id }}" wire:navigate
                           class="transition-transform hover:scale-105">
                            <x-badge variant="primary" icon="tag">{{ $template->category->name }}</x-badge>
                        </a>
                    @endif
                    @if($template->is_free || $template->final_price <= 0)
                        <x-badge variant="success" icon="gift">رایگان</x-badge>
                    @elseif($template->discount > 0)
                        <x-badge variant="danger" icon="tag">{{ fa_num($template->discount) }}٪ تخفیف</x-badge>
                    @endif
                </div>

                <h1 class="mt-3 text-2xl font-black leading-relaxed text-zinc-900 dark:text-zinc-50">
                    {{ $template->name }}
                </h1>

                {{-- meta row --}}
                <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-zinc-500 dark:text-zinc-400">
                    @if($template->rating)
                        <span class="inline-flex items-center gap-1 font-bold text-amber-500">
                            <x-icon name="star" class="size-4 fill-amber-400" />
                            {{ fa_num($template->rating) }}
                            <span class="font-normal text-zinc-400">({{ fa_num($template->approvedCommentsCount()) }} دیدگاه)</span>
                        </span>
                    @endif
                    <span class="inline-flex items-center gap-1"><x-icon name="receipt" class="size-3.5" /> {{ fa_num($template->sales_count) }} فروش</span>
                    <span class="inline-flex items-center gap-1"><x-icon name="eye" class="size-3.5" /> {{ fa_num($template->views_count) }} بازدید</span>
                    <span class="inline-flex items-center gap-1"><x-icon name="layers" class="size-3.5" /> {{ fa_num($template->versions()->count()) }} نسخه</span>
                </div>
            </div>

            @if($template->short_description)
                <p class="whitespace-pre-line rounded-2xl bg-white p-4 text-sm leading-7 text-zinc-600 ring-1 ring-zinc-200/80 dark:bg-zinc-900 dark:text-zinc-300 dark:ring-zinc-700/60">
                    {{ $template->short_description }}
                </p>
            @endif

            {{-- ============ Buy box ============ --}}
            <div class="card space-y-4 p-5 sm:p-6">

                @if($template->is_sold_exclusively)
                    {{-- ==== واگذارشده (اختصاصی) ==== --}}
                    <div class="flex flex-col items-center gap-3 rounded-2xl bg-violet-50 px-4 py-6 text-center ring-1 ring-violet-200 dark:bg-violet-500/10 dark:ring-violet-500/20">
                        <x-icon name="crown" class="size-8 text-violet-500" />
                        <p class="text-sm font-black text-violet-800 dark:text-violet-300">این قالب به‌صورت اختصاصی واگذار شده است</p>
                        <p class="text-xs leading-6 text-zinc-500 dark:text-zinc-400">مالکیت کامل این قالب به یک خریدار منتقل شده و دیگر قابل خرید نیست.</p>
                    </div>
                @else
                    {{-- ==== انتخاب نوع خرید (معمولی / اختصاصی) ==== --}}
                    @if($template->is_effectively_free)
                        <div class="flex items-end justify-between">
                            <div>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">قیمت قالب</p>
                                <span class="mt-1 inline-block text-2xl font-black text-emerald-600 dark:text-emerald-400">رایگان</span>
                            </div>
                            <span class="inline-flex items-center gap-1 rounded-xl bg-zinc-100 px-2.5 py-1 text-[11px] font-bold text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                                <x-icon name="shield-check" class="size-3.5" />
                                بدون نیاز به ثبت‌نام
                            </span>
                        </div>
                    @else
                        <p class="text-xs font-bold text-zinc-500 dark:text-zinc-400">نوع خرید را انتخاب کنید:</p>

                        <div class="grid grid-cols-1 gap-3" role="radiogroup" aria-label="نوع خرید">
                            {{-- خرید معمولی --}}
                            <button type="button" wire:click="$set('purchaseType', 'normal')"
                                    class="relative cursor-pointer rounded-2xl p-4 text-start transition-all"
                                    :class="purchaseType === 'normal'
                                        ? 'bg-brand-50 ring-2 ring-brand-500 dark:bg-brand-500/10 dark:ring-brand-400'
                                        : 'bg-white ring-1 ring-zinc-200 hover:ring-brand-300 dark:bg-zinc-900 dark:ring-zinc-700'"
                                    role="radio" :aria-checked="(purchaseType === 'normal').toString()">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex size-5 items-center justify-center rounded-full border-2 transition-colors"
                                              :class="purchaseType === 'normal' ? 'border-brand-500 bg-brand-500' : 'border-zinc-300 dark:border-zinc-600'">
                                            <span class="size-2 rounded-full bg-white transition-opacity" :class="purchaseType === 'normal' ? 'opacity-100' : 'opacity-0'"></span>
                                        </span>
                                        <p class="text-sm font-black text-zinc-900 dark:text-zinc-50">خرید معمولی</p>
                                    </div>
                                    <div class="text-end">
                                        @if($template->discount > 0)
                                            <span class="block text-[11px] text-zinc-400 line-through">{{ money($template->price) }}</span>
                                        @endif
                                        <span class="text-lg font-black tabular-nums text-brand-600 dark:text-brand-400">{{ money($template->final_price) }}</span>
                                    </div>
                                </div>
                                <p class="mt-1.5 ps-7 text-[11px] leading-5 text-zinc-500 dark:text-zinc-400">
                                    استفاده از قالب برای یک پروژه — دانلود تمام نسخه‌ها و آپدیت‌ها
                                </p>
                            </button>

                            {{-- خرید اختصاصی --}}
                            @if($template->has_exclusive_offer)
                                <button type="button" wire:click="$set('purchaseType', 'exclusive')"
                                        class="relative cursor-pointer rounded-2xl p-4 text-start transition-all"
                                        :class="purchaseType === 'exclusive'
                                            ? 'bg-violet-50 ring-2 ring-violet-500 dark:bg-violet-500/10 dark:ring-violet-400'
                                            : 'bg-white ring-1 ring-zinc-200 hover:ring-violet-300 dark:bg-zinc-900 dark:ring-zinc-700'"
                                        role="radio" :aria-checked="(purchaseType === 'exclusive').toString()">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex size-5 items-center justify-center rounded-full border-2 transition-colors"
                                                  :class="purchaseType === 'exclusive' ? 'border-violet-500 bg-violet-500' : 'border-zinc-300 dark:border-zinc-600'">
                                                <span class="size-2 rounded-full bg-white transition-opacity" :class="purchaseType === 'exclusive' ? 'opacity-100' : 'opacity-0'"></span>
                                            </span>
                                            <p class="flex items-center gap-1 text-sm font-black text-zinc-900 dark:text-zinc-50">
                                                <x-icon name="crown" class="size-4 text-violet-500" />
                                                خرید اختصاصی
                                            </p>
                                        </div>
                                        <div class="text-end">
                                            @if($template->exclusive_discount > 0)
                                                <span class="block text-[11px] text-zinc-400 line-through">{{ money($template->exclusive_price) }}</span>
                                            @endif
                                            <span class="text-lg font-black tabular-nums text-violet-600 dark:text-violet-400">{{ money($template->exclusive_final_price) }}</span>
                                        </div>
                                    </div>
                                    <p class="mt-1.5 ps-7 text-[11px] leading-5 text-zinc-500 dark:text-zinc-400">
                                        @if($template->exclusive_note)
                                            {{ $template->exclusive_note }}
                                        @else
                                            واگذاری کامل و انحصاری قالب — پس از خرید از فروشگاه برداشته می‌شود
                                        @endif
                                    </p>
                                </button>
                            @endif
                        </div>

                        @php($appliedDiscount = $this->appliedDiscount)
                        @php($baseAmount = (int) ($purchaseType === 'exclusive' ? $template->exclusive_final_price : $template->final_price))

                        {{-- نمایش قیمت با تخفیف کد --}}
                        @if($appliedDiscount)
                            <div class="flex items-center justify-between rounded-xl bg-emerald-50 px-4 py-3 text-sm dark:bg-emerald-500/10">
                                <span class="font-bold text-emerald-700 dark:text-emerald-400">مبلغ قابل پرداخت</span>
                                <span class="font-black tabular-nums text-emerald-700 dark:text-emerald-400">
                                    {{ money($appliedDiscount['final']) }}
                                    <span class="text-xs font-normal text-emerald-600/70">({{ money($appliedDiscount['discount'], false) }} تخفیف)</span>
                                </span>
                            </div>
                        @endif

                        {{-- کد تخفیف --}}
                        @include('livewire.shop.partials.discount-field', ['baseAmount' => $baseAmount])

                        @unless($appliedDiscount && $appliedDiscount['final'] <= 0)
                            @include('livewire.shop.partials.gateway-picker', ['gateways' => $this->gateways])
                        @endunless
                    @endif

                    <x-btn wire:click="buy" :loading="true" size="lg" class="w-full"
                           :icon="($template->is_effectively_free || ($this->appliedDiscount && $this->appliedDiscount['final'] <= 0)) ? 'download' : 'credit-card'">
                        @if($template->is_effectively_free)
                            دریافت رایگان و دانلود
                        @elseif($this->appliedDiscount && $this->appliedDiscount['final'] <= 0)
                            دریافت رایگان با کد تخفیف
                        @elseif($purchaseType === 'exclusive')
                            خرید اختصاصی و پرداخت
                        @else
                            خرید و دانلود فوری
                        @endif
                    </x-btn>

                    <p class="text-center text-[11px] leading-5 text-zinc-400">
                        پس از پرداخت، لایسنس خرید صادر و به صفحه دانلود هدایت می‌شوید.
                    </p>
                @endif
            </div>
        </div>
    </section>

    {{-- ============ Tabs: توضیحات / دیدگاه‌ها ============ --}}
    {{-- توضیحات و دیدگاه‌ها هرکدام در تب جدا + شمارنده دیدگاه‌های تأییدشده روی تب --}}
    @php($approvedCommentsCount = $template->approvedCommentsCount())
    <section class="card overflow-hidden" id="description" x-data="{ shopTab: 'description' }">
        <div class="flex items-center gap-1 overflow-x-auto border-b border-zinc-100 px-2 dark:border-zinc-800 sm:px-4" role="tablist" aria-label="توضیحات و دیدگاه‌ها">
            <button type="button" @click="shopTab = 'description'" role="tab"
                    :aria-selected="(shopTab === 'description').toString()"
                    class="-mb-px cursor-pointer whitespace-nowrap border-b-2 px-4 py-3.5 text-sm font-black transition-colors"
                    :class="shopTab === 'description'
                        ? 'border-brand-500 text-brand-600 dark:border-brand-400 dark:text-brand-400'
                        : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200'">
                <span class="inline-flex items-center gap-1.5">
                    <x-icon name="file-text" class="size-4" />
                    توضیحات قالب
                </span>
            </button>
            <button type="button" @click="shopTab = 'comments'" role="tab"
                    :aria-selected="(shopTab === 'comments').toString()"
                    class="-mb-px cursor-pointer whitespace-nowrap border-b-2 px-4 py-3.5 text-sm font-black transition-colors"
                    :class="shopTab === 'comments'
                        ? 'border-brand-500 text-brand-600 dark:border-brand-400 dark:text-brand-400'
                        : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200'">
                <span class="inline-flex items-center gap-1.5">
                    <x-icon name="message-square" class="size-4" />
                    دیدگاه‌ها
                    <span class="rounded-full px-2 py-0.5 text-[11px] tabular-nums transition-colors"
                          :class="shopTab === 'comments'
                              ? 'bg-brand-100 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400'
                              : 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400'">{{ fa_num($approvedCommentsCount) }}</span>
                </span>
            </button>
        </div>

        {{-- panel: توضیحات --}}
        <div x-show="shopTab === 'description'" role="tabpanel" class="p-5 sm:p-6">
            @if($template->description)
                <div class="rich-content text-sm leading-8 text-zinc-700 dark:text-zinc-300">
                    {!! $template->description !!}
                </div>
            @else
                <x-empty icon="file-text" title="توضیحاتی ثبت نشده" />
            @endif
        </div>

        {{-- panel: دیدگاه‌ها --}}
        <div x-show="shopTab === 'comments'" x-cloak role="tabpanel" class="p-5 sm:p-6">
            <livewire:shop.comment-section :model="$template" label="قالب" :key="'cs-template-'.$template->id" />
        </div>
    </section>

    {{-- ============ Gallery ============ --}}
    @if($template->activeImages->count())
        <section class="card p-5 sm:p-6" id="gallery">
            <h2 class="flex items-center gap-2 text-lg font-black text-zinc-900 dark:text-zinc-50">
                <x-icon name="image" class="size-5 text-brand-600" />
                گالری پیش‌نمایش
            </h2>

            <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2" x-data="{ lightbox: null }">
                @foreach($template->activeImages as $image)
                    <button type="button" @click="lightbox = '{{ $image->url }}'"
                            class="group relative overflow-hidden rounded-2xl bg-zinc-100 ring-1 ring-zinc-200/80 dark:bg-zinc-800 dark:ring-zinc-700/60"
                            wire:key="shp-timg-{{ $image->id }}" aria-label="بزرگ‌نمایی تصویر {{ $loop->iteration }}">
                        <img src="{{ $image->url }}" alt="{{ $image->original_name ?: 'پیش‌نمایش قالب ' . $loop->iteration }}"
                             class="aspect-video size-full object-cover transition-transform duration-300 group-hover:scale-105" loading="lazy" />
                        <span class="absolute inset-0 flex items-center justify-center bg-zinc-950/0 opacity-0 transition-all group-hover:bg-zinc-950/30 group-hover:opacity-100">
                            <x-icon name="zoom-in" class="size-6 text-white" />
                        </span>
                    </button>
                @endforeach

                {{-- Lightbox --}}
                <div x-show="lightbox" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center bg-zinc-950/90 p-6"
                     @click="lightbox = null" @keydown.escape.window="lightbox = null" role="dialog" aria-modal="true" aria-label="نمایش تصویر">
                    <img :src="lightbox" alt="پیش‌نمایش قالب" class="max-h-full max-w-full rounded-2xl object-contain shadow-2xl" />
                    <button type="button" class="absolute top-5 end-5 rounded-xl bg-white/10 p-2 text-white transition-colors hover:bg-white/20" aria-label="بستن">
                        <x-icon name="x" class="size-5" />
                    </button>
                </div>
            </div>
        </section>
    @endif

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
</div>
