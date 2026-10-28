{{-- بخش «testimonials» صفحهٔ اصلی — محتوا/سوییچ/ترتیب از پنل (Task 59) --}}
        @if($ho['home_testimonials_enabled'] && count($testimonials))
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6">
                <section aria-label="نظرات مشتریان">
                    <h2 class="flex items-center gap-2 text-lg font-black text-zinc-900 dark:text-zinc-50">
                        <x-icon name="quote" class="size-5 text-brand-600" />
                        {{ $hs['home_testimonials_title'] }}
                    </h2>

                    <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-3">
                        @foreach($testimonials as $i => $testimonial)
                            <figure wire:key="testi-{{ $i }}"
                                    class="flex flex-col gap-3 rounded-2xl bg-white p-5 ring-1 ring-zinc-200/80 transition-all hover:-translate-y-0.5 hover:shadow-card-lg dark:bg-zinc-900 dark:ring-zinc-700/60">
                                <span class="flex size-8 items-center justify-center rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400">
                                    <x-icon name="star" class="size-4 fill-current" />
                                </span>
                                <blockquote class="flex-1 text-xs leading-6 text-zinc-600 dark:text-zinc-300">
                                    {{ $testimonial['text'] }}
                                </blockquote>
                                <figcaption class="flex items-center gap-2 border-t border-zinc-100 pt-3 dark:border-zinc-700/50">
                                    <x-avatar :name="$testimonial['author']" size="sm" />
                                    <span class="truncate text-xs font-black text-zinc-700 dark:text-zinc-200">{{ $testimonial['author'] }}</span>
                                </figcaption>
                            </figure>
                        @endforeach
                    </div>
                </section>
        </div>
        @endif
