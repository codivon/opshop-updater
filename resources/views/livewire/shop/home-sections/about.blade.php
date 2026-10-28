{{-- بخش «about» صفحهٔ اصلی — محتوا/سوییچ/ترتیب از پنل (Task 59) — لینک نمونه‌کارها به سایت برند (Task 62) --}}
        @if($ho['home_about_enabled'] && ($hs['home_about_title'] !== '' || $hs['home_about_body'] !== ''))
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6">
                <section id="about" class="scroll-mt-24">
                    <div class="relative overflow-hidden rounded-3xl bg-white p-6 ring-1 ring-zinc-200/80 dark:bg-zinc-900 dark:ring-zinc-700/60 sm:p-8">
                        <div class="pointer-events-none absolute -top-24 -end-24 size-64 rounded-full bg-brand-400/10 blur-3xl" aria-hidden="true"></div>

                        <div class="relative grid gap-6 lg:grid-cols-3">
                            <div>
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 text-xs font-bold text-brand-700 ring-1 ring-brand-200/70 dark:bg-brand-500/10 dark:text-brand-300 dark:ring-brand-400/20">
                                    <x-icon name="sparkles" class="size-3.5" />
                                    {{ $hs['home_hero_badge'] }}
                                </span>
                                <h2 class="mt-3 flex items-center gap-2 text-xl font-black text-zinc-900 dark:text-zinc-50">
                                    <x-icon name="users" class="size-5.5 text-brand-600" />
                                    {{ $hs['home_about_title'] }}
                                </h2>
                                <div class="mt-4 flex flex-wrap items-center gap-2">
                                    <a href="#contact" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-50 px-3.5 py-2 text-xs font-black text-brand-700 transition-colors hover:bg-brand-100 dark:bg-brand-500/10 dark:text-brand-300 dark:hover:bg-brand-500/20">
                                        <x-icon name="message-circle" class="size-3.5" />
                                        صحبت درباره ایده شما
                                    </a>
                                    {{-- «بیشتر / نمونه‌کارها» → سایت اصلی برند (تب جدید) — Task 62 --}}
                                    @if($hs['home_about_more_text'] !== '')
                                        @php($more = $hs['home_about_more_link'] ?: 'https://weblak.ir')
                                        <a href="{{ $more }}" @if(str_starts_with($more, 'http')) target="_blank" rel="noopener" @endif class="inline-flex items-center gap-1.5 rounded-xl bg-brand-50 px-3.5 py-2 text-xs font-black text-brand-700 transition-colors hover:bg-brand-100 dark:bg-brand-500/10 dark:text-brand-300 dark:hover:bg-brand-500/20">
                                            <x-icon name="globe" class="size-3.5" />
                                            {{ $hs['home_about_more_text'] }}
                                            <x-icon name="external-link" class="size-3 opacity-60" />
                                        </a>
                                    @endif
                                </div>
                            </div>
                            <div class="lg:col-span-2">
                                <p class="whitespace-pre-line text-sm leading-8 text-zinc-600 dark:text-zinc-300">
                                    {{ $hs['home_about_body'] }}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>
        </div>
        @endif
