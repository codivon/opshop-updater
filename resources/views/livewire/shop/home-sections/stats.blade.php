{{-- بخش «stats» صفحهٔ اصلی — محتوا/سوییچ/ترتیب از پنل (Task 59) --}}
        @if($ho['home_stats_enabled'] && count($trustStats))
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6">
                <section aria-label="آمار و اعتماد" class="-mt-2">
                    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        @foreach($trustStats as $i => $stat)
                            <div wire:key="trust-stat-{{ $i }}"
                                 class="flex flex-col items-center gap-1 rounded-2xl bg-white p-5 text-center ring-1 ring-zinc-200/80 transition-all hover:-translate-y-0.5 hover:shadow-card-lg dark:bg-zinc-900 dark:ring-zinc-700/60">
                                <span class="bg-gradient-to-b from-brand-600 to-emerald-500 bg-clip-text text-2xl font-black tabular-nums text-transparent sm:text-3xl">{{ $stat['value'] }}</span>
                                <span class="text-xs font-bold text-zinc-500 dark:text-zinc-400">{{ $stat['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>
        </div>
        @endif
