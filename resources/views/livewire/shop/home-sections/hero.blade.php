{{-- بخش «hero» صفحهٔ اصلی — محتوا/سوییچ/ترتیب از پنل (Task 59) — دکمهٔ سوم: سایت برند (Task 62) --}}
    @if($ho['home_hero_enabled'])
        <section class="shop-hero relative overflow-hidden">
            <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                <div class="shop-hero-glow absolute -top-32 -start-32 size-96 rounded-full blur-3xl"></div>
                <div class="shop-hero-glow absolute -bottom-40 -end-24 size-96 rounded-full opacity-60 blur-3xl"></div>
            </div>
            <div class="pointer-events-none absolute inset-0 opacity-[0.04]" aria-hidden="true"
                 style="background-image: linear-gradient(#fff 1px, transparent 1px), linear-gradient(90deg, #fff 1px, transparent 1px); background-size: 44px 44px;"></div>

            <div class="relative mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 sm:py-16">
                <div class="max-w-3xl animate-slide-up">
                    @if($hs['home_hero_badge'] !== '')
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-teal-100 ring-1 ring-white/20">
                            <x-icon name="sparkles" class="size-3.5" />
                            {{ $hs['home_hero_badge'] }}
                        </span>
                    @endif
                    <h1 class="shop-hero-title mt-4 text-3xl font-black leading-relaxed sm:text-4xl sm:leading-relaxed">
                        {{ $hs['home_hero_title'] }}
                    </h1>
                    @if($hs['home_hero_subtitle'] !== '')
                        <p class="mt-3 max-w-2xl text-sm leading-7 text-teal-50/80 sm:text-base">
                            {{ $hs['home_hero_subtitle'] }}
                        </p>
                    @endif

                    <div class="mt-6 flex flex-wrap items-center gap-2.5">
                        @if($hs['home_hero_cta_text'] !== '')
                            <a href="{{ $hs['home_hero_cta_link'] ?: '#shop-packages' }}" class="inline-flex items-center gap-1.5 rounded-xl bg-white px-4 py-2.5 text-sm font-black text-zinc-900 shadow-sm transition-all hover:shadow-md">
                                <x-icon name="package" class="size-4 text-brand-600" />
                                {{ $hs['home_hero_cta_text'] }}
                            </a>
                        @endif
                        @if($hs['home_hero_cta2_text'] !== '')
                            <a href="{{ $hs['home_hero_cta2_link'] ?: '#contact' }}" class="inline-flex items-center gap-1.5 rounded-xl bg-white/10 px-4 py-2.5 text-sm font-bold text-white ring-1 ring-white/25 backdrop-blur transition-colors hover:bg-white/20">
                                <x-icon name="message-circle" class="size-4" />
                                {{ $hs['home_hero_cta2_text'] }}
                            </a>
                        @endif
                        {{-- دکمهٔ سوم: بیشتر درباره من / نمونه‌کارها → سایت برند (تب جدید) — Task 62 --}}
                        @if($hs['home_hero_cta3_text'] !== '')
                            @php($cta3 = $hs['home_hero_cta3_link'] ?: 'https://weblak.ir')
                            <a href="{{ $cta3 }}" @if(str_starts_with($cta3, 'http')) target="_blank" rel="noopener" @endif class="inline-flex items-center gap-1.5 rounded-xl bg-white/10 px-4 py-2.5 text-sm font-bold text-white ring-1 ring-white/25 backdrop-blur transition-colors hover:bg-white/20">
                                <x-icon name="globe" class="size-4" />
                                {{ $hs['home_hero_cta3_text'] }}
                                <x-icon name="external-link" class="size-3.5 opacity-60" />
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    @endif
