{{-- بخش «why» صفحهٔ اصلی — محتوا/سوییچ/ترتیب از پنل (Task 59) --}}
        @if($ho['home_why_enabled'] && count($whyItems))
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6">
                <section aria-label="چرا ما">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="flex items-center gap-2 text-lg font-black text-zinc-900 dark:text-zinc-50">
                                <x-icon name="badge-check" class="size-5 text-brand-600" />
                                {{ $hs['home_why_title'] }}
                            </h2>
                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $hs['home_why_intro'] }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($whyItems as $i => $item)
                            <div wire:key="why-{{ $i }}"
                                 class="group rounded-2xl bg-white p-5 ring-1 ring-zinc-200/80 transition-all hover:-translate-y-1 hover:shadow-card-lg dark:bg-zinc-900 dark:ring-zinc-700/60">
                                <span class="flex size-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600 transition-colors group-hover:bg-brand-100 dark:bg-brand-500/10 dark:text-brand-400">
                                    <x-icon name="{{ $item['icon'] }}" class="size-5.5" />
                                </span>
                                <h3 class="mt-3.5 text-sm font-black text-zinc-900 dark:text-zinc-50">{{ $item['title'] }}</h3>
                                <p class="mt-1.5 text-xs leading-6 text-zinc-500 dark:text-zinc-400">{{ $item['text'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </section>
        </div>
        @endif
