{{-- ============================================================
     اسلایدر معرفی محصول (Task 71) — تصویر + متن، لمسی/سوایپ، RTL
     - مکانیزم: scroll-snap بومی مرورگر (سوایپ لمسی رایگان) + Alpine
       برای دات‌ها/فلش‌ها/پخش خودکار — scrollIntoView هم در LTR و RTL
       و هر مرورگری درست کار می‌کند (بدون محاسبهٔ دستی scrollLeft).
     - اسلایدها از App\Support\Showcase (دستی اولویت، وگرنه پیش‌فرض‌ها).
     - غیرفعال/جابه‌جایی از پنل: تب «بخش‌ها» صفحهٔ «صفحهٔ اصلی فروشگاه».
     متغیرها: $showcaseSlides، $ho
============================================================ --}}
@if($ho['home_showcase_enabled'] && count($showcaseSlides))
<section id="showcase" class="mx-auto w-full max-w-7xl px-4 sm:px-6" aria-label="ویترین محصولات">
    <div x-data="showcaseSlider({{ count($showcaseSlides) }})"
         wire:key="showcase-slider"
         @pointerenter="stop()" @pointerleave="play()">

        {{-- سربرگ بخش --}}
        <div class="mb-4 flex items-end justify-between gap-3">
            <div class="min-w-0">
                <p class="flex items-center gap-1.5 text-xs font-bold text-brand-600 dark:text-brand-400">
                    <x-icon name="sparkles" class="size-3.5" />
                    ویترین محصولات
                </p>
                <h2 class="mt-1 text-xl font-black text-zinc-900 dark:text-zinc-50 sm:text-2xl">
                    محصولات ویژهٔ ما را ببینید
                </h2>
            </div>

            {{-- فلش‌ها (دسکتاپ) --}}
            <div class="hidden shrink-0 items-center gap-2 sm:flex">
                <button type="button" @click="prev(); stop()"
                        class="rounded-xl bg-white p-2.5 text-zinc-600 shadow-sm ring-1 ring-zinc-200/70 transition-all hover:bg-brand-50 hover:text-brand-600 active:scale-95 dark:bg-zinc-900 dark:text-zinc-300 dark:ring-zinc-700/60 dark:hover:bg-brand-500/10 dark:hover:text-brand-400"
                        aria-label="اسلاید قبلی" x-show="count > 1">
                    <x-icon name="chevron-right" class="size-5" />
                </button>
                <button type="button" @click="next(); stop()"
                        class="rounded-xl bg-white p-2.5 text-zinc-600 shadow-sm ring-1 ring-zinc-200/70 transition-all hover:bg-brand-50 hover:text-brand-600 active:scale-95 dark:bg-zinc-900 dark:text-zinc-300 dark:ring-zinc-700/60 dark:hover:bg-brand-500/10 dark:hover:text-brand-400"
                        aria-label="اسلاید بعدی" x-show="count > 1">
                    <x-icon name="chevron-left" class="size-5" />
                </button>
            </div>
        </div>

        {{-- ترک اسلایدها — سوایپ لمسی با scroll-snap بومی --}}
        <div x-ref="track"
             @scroll.passive.debounce.80ms="sync()"
             class="flex snap-x snap-mandatory gap-4 overflow-x-auto rounded-3xl [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
             role="region" aria-roledescription="اسلایدر" aria-label="اسلایدهای ویترین محصولات">

            @foreach($showcaseSlides as $i => $slide)
                <article class="group relative w-full shrink-0 snap-center overflow-hidden rounded-3xl bg-zinc-900 ring-1 ring-zinc-200/70 min-h-[340px] sm:min-h-[300px] lg:min-h-[380px] dark:ring-zinc-700/60"
                         wire:key="slide-{{ $i }}">

                    {{-- تصویر پس‌زمینه / گرادیان برند در نبود تصویر --}}
                    @if($slide['image'])
                        <img src="{{ $slide['image'] }}" alt="{{ $slide['title'] }}" draggable="false"
                             class="absolute inset-0 size-full select-none object-cover transition-transform duration-500 group-hover:scale-[1.03]" />
                    @else
                        <div class="absolute inset-0 bg-gradient-to-bl from-brand-600 via-teal-700 to-zinc-900"></div>
                        <x-icon name="package" class="absolute -bottom-10 -end-10 size-56 text-white/10" />
                    @endif

                    {{-- لایهٔ خوانایی متن: موبایل از پایین، دسکتاپ از راست (RTL) --}}
                    <div class="absolute inset-0 bg-gradient-to-t from-zinc-950/90 via-zinc-950/45 to-transparent sm:bg-gradient-to-l sm:from-zinc-950/85 sm:via-zinc-950/45 sm:to-transparent"></div>

                    {{-- محتوای اسلاید --}}
                    <div class="relative flex size-full flex-col justify-end p-5 sm:justify-center sm:p-10 lg:p-14">
                        <div class="max-w-md lg:max-w-xl">
                            @if($slide['badge'])
                                <span class="mb-3 inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-[11px] font-black text-emerald-300 ring-1 ring-white/25 backdrop-blur">
                                    <x-icon name="badge-check" class="size-3.5" />
                                    {{ $slide['badge'] }}
                                </span>
                            @endif

                            <h3 class="text-xl font-black leading-9 text-white drop-shadow-sm sm:text-2xl lg:text-4xl lg:leading-[3rem]">
                                {{ $slide['title'] }}
                            </h3>

                            @if($slide['text'])
                                <p class="mt-2.5 line-clamp-3 text-[13px] leading-7 text-zinc-200/90 sm:text-sm lg:text-base lg:leading-8">
                                    {{ $slide['text'] }}
                                </p>
                            @endif

                            @if($slide['link'])
                                <a href="{{ $slide['link'] }}"
                                   @if(str_starts_with($slide['link'], 'http')) target="_blank" rel="noopener" @endif
                                   class="mt-4 inline-flex items-center gap-2 rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-black text-white shadow-lg shadow-brand-500/25 transition-all hover:bg-brand-400 hover:gap-3 active:scale-95">
                                    {{ $slide['link_text'] }}
                                    <x-icon name="arrow-left" class="size-4" />
                                </a>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        {{-- دات‌های ناوبری --}}
        @if(count($showcaseSlides) > 1)
            <div class="mt-4 flex items-center justify-center gap-2" role="tablist" aria-label="انتخاب اسلاید">
                @foreach($showcaseSlides as $i => $slide)
                    <button type="button" @click="go({{ $i }}); stop()"
                            :aria-current="current === {{ $i }}"
                            :class="current === {{ $i }} ? 'w-7 bg-brand-500' : 'w-2.5 bg-zinc-300 hover:bg-zinc-400 dark:bg-zinc-600 dark:hover:bg-zinc-500'"
                            class="h-2.5 cursor-pointer rounded-full transition-all duration-300"
                            aria-label="اسلاید {{ fa_num($i + 1) }}"></button>
                @endforeach
            </div>
        @endif
    </div>
</section>

@script
<script>
    Alpine.data('showcaseSlider', (count) => ({
        count,
        current: 0,
        timer: null,

        init() {
            this.sync();
            this.play();
        },

        /** اسلاید فعال از روی موقعیت اسکرول واقعی (سازگار با RTL همهٔ مرورگرها) */
        sync() {
            const track = this.$refs.track;
            if (!track || !track.children.length) return;

            const center = track.getBoundingClientRect().left + track.clientWidth / 2;
            let best = 0;
            let bestDist = Infinity;

            [...track.children].forEach((el, i) => {
                const r = el.getBoundingClientRect();
                const dist = Math.abs(r.left + r.width / 2 - center);
                if (dist < bestDist) { bestDist = dist; best = i; }
            });

            this.current = best;
        },

        go(i) {
            const track = this.$refs.track;
            if (!track || !track.children.length) return;
            track.children[Math.max(0, Math.min(i, this.count - 1))]
                .scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
        },

        next() { this.go(this.current + 1 >= this.count ? 0 : this.current + 1); },
        prev() { this.go(this.current - 1 < 0 ? this.count - 1 : this.current - 1); },

        /** پخش خودکار ملایم — با احترام به prefers-reduced-motion */
        play() {
            if (this.count < 2) return;
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            this.stop();
            this.timer = setInterval(() => this.next(), 6000);
        },

        stop() {
            if (this.timer) { clearInterval(this.timer); this.timer = null; }
        },
    }))
</script>
@endscript
@endif
