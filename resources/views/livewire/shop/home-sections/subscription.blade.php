{{-- بخش «اشتراک‌ها» — طرح‌های اشتراک ویژه (Task 60: وضعیت/بررسی اشتراک به بخش مستقل «اشتراک فعال» منتقل شد) --}}
        @if($ho['home_subscription_enabled'])
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6">
                <section id="subscription" class="scroll-mt-24 space-y-6">

                    {{-- ---------- طرح‌های اشتراک ویژه ---------- --}}
                    @if($this->featuredPlans->count())
                        <div>
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h2 class="flex items-center gap-2 text-lg font-black text-zinc-900 dark:text-zinc-50">
                                        <x-icon name="crown" class="size-5 text-amber-500" />
                                        طرح‌های اشتراک ویژه
                                    </h2>
                                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                        یک اشتراک بخرید، پکیج‌های منتخب را رایگان داشته باشید.
                                    </p>
                                </div>
                                <a href="{{ route('shop.subscriptions') }}" wire:navigate>
                                    <x-btn variant="secondary" size="sm" icon="arrow-left">مشاهده همه طرح‌ها</x-btn>
                                </a>
                            </div>

                            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-3">
                                @foreach($this->featuredPlans as $plan)
                                    <a href="{{ route('shop.subscriptions') }}" wire:key="plan-{{ $plan->id }}" wire:navigate
                                       class="group flex flex-col gap-3 rounded-2xl bg-white p-5 ring-1 ring-zinc-200/80 transition-all hover:-translate-y-1 hover:shadow-card-lg dark:bg-zinc-900 dark:ring-zinc-700/60">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="flex items-center gap-2 text-sm font-black text-zinc-900 dark:text-zinc-50">
                                                <x-icon name="crown" class="size-4 text-amber-500" />
                                                {{ $plan->name }}
                                            </span>
                                            @if($plan->final_price <= 0)
                                                <x-badge variant="success" icon="gift">رایگان</x-badge>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                            <x-icon name="calendar-clock" class="size-3.5" />
                                            {{ $plan->duration_label }}
                                            @if($plan->is_one_time)
                                                · یک‌بارمصرف
                                            @endif
                                        </div>
                                        @if($plan->packages->count())
                                            <div class="flex flex-wrap gap-1.5">
                                                @foreach($plan->packages->take(3) as $pkg)
                                                    <span class="rounded-lg bg-brand-50 px-2 py-0.5 text-[11px] font-bold text-brand-700 dark:bg-brand-500/10 dark:text-brand-400">
                                                        {{ \Illuminate\Support\Str::limit($pkg->name, 16) }}
                                                    </span>
                                                @endforeach
                                                @if($plan->packages->count() > 3)
                                                    <span class="rounded-lg bg-zinc-100 px-2 py-0.5 text-[11px] font-bold text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400">
                                                        +{{ fa_num($plan->packages->count() - 3) }}
                                                    </span>
                                                @endif
                                            </div>
                                        @endif
                                        <div class="mt-auto flex items-center justify-between border-t border-zinc-100 pt-3 dark:border-zinc-700/50">
                                            <span class="text-sm font-black tabular-nums text-zinc-900 dark:text-zinc-50">
                                                {{ $plan->final_price > 0 ? money($plan->final_price) : 'رایگان' }}
                                            </span>
                                            <span class="inline-flex items-center gap-1 text-xs font-bold text-brand-600 transition-all group-hover:gap-2 dark:text-brand-400">
                                                جزئیات طرح
                                                <x-icon name="arrow-left" class="size-3.5" />
                                            </span>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                </section>
        </div>
        @endif
