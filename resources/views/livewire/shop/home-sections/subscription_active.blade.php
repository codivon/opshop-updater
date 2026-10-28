{{-- بخش «اشتراک فعال» — بنر وضعیت اشتراک کاربر / جعبهٔ بررسی کد (Task 60: جدا از طرح‌های اشتراک) --}}
        @if($ho['home_subscription_active_enabled'])
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6">
                <section id="subscription-active" class="scroll-mt-24 space-y-6">

                    {{-- ---------- وضعیت اشتراک من ---------- --}}
                    @php($mySubscription = $this->myActiveSubscription)
                    @if($mySubscription && $mySubscription->plan)
                        {{-- بنر موفق: مشتری (لاگین یا با کد) اشتراک فعالِ طرح‌محور دارد --}}
                        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-bl from-emerald-50 via-white to-teal-50 ring-1 ring-emerald-200 dark:from-zinc-900 dark:via-zinc-900 dark:to-emerald-950/40 dark:ring-emerald-500/25">
                            <div class="pointer-events-none absolute -top-20 -end-16 size-56 rounded-full bg-emerald-400/10 blur-3xl" aria-hidden="true"></div>

                            <div class="relative flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:p-6">
                                <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400">
                                    <x-icon name="badge-check" class="size-6" />
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                                        <h2 class="text-base font-black text-emerald-800 dark:text-emerald-300">اشتراک فعال دارید</h2>
                                        <x-badge variant="success" icon="crown">{{ $mySubscription->plan->name }}</x-badge>
                                        @if($mySubscription->expires_at)
                                            <span class="inline-flex items-center gap-1 text-xs tabular-nums text-zinc-500 dark:text-zinc-400">
                                                <x-icon name="calendar-clock" class="size-3.5" />
                                                اعتبار تا {{ verta_date($mySubscription->expires_at) }}
                                            </span>
                                        @endif
                                        {{-- منبع اشتراک: حساب لاگین‌شده یا کد بررسی‌شده --}}
                                        @if(auth('customer')->check())
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                                                <x-icon name="log-in" class="size-3" /> از حساب کاربری شما
                                            </span>
                                        @endif
                                    </div>
                                    <p class="mt-1.5 text-sm leading-6 text-emerald-700 dark:text-emerald-300/80">
                                        پکیج‌های این طرح برای شما رایگان‌اند؛ در فهرست پکیج‌ها با نشان «رایگان با اشتراک» مشخص شده‌اند.
                                    </p>
                                    @if($mySubscription->plan->packages->isNotEmpty())
                                        <div class="mt-3 flex flex-wrap items-center gap-1.5">
                                            <span class="inline-flex items-center gap-1 text-xs font-bold text-emerald-700 dark:text-emerald-400">
                                                <x-icon name="gift" class="size-3.5" />
                                                {{ fa_num($mySubscription->plan->packages->count()) }} پکیج رایگان:
                                            </span>
                                            @foreach($mySubscription->plan->packages as $pkg)
                                                <span class="rounded-lg bg-emerald-100/80 px-2 py-0.5 text-[11px] font-bold text-emerald-700 ring-1 ring-emerald-600/10 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-400/20">
                                                    {{ \Illuminate\Support\Str::limit($pkg->name, 18) }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                @if(!auth('customer')->check())
                                    <x-btn variant="ghost" size="sm" icon="x" wire:click="forgetMySubscription" :loading="true"
                                           class="shrink-0 self-start text-zinc-500 dark:text-zinc-400 sm:self-center">
                                        فراموشی
                                    </x-btn>
                                @endif
                            </div>
                        </div>
                    @else
                        {{-- جعبه بررسی اشتراک (مهمان‌ها) --}}
                        <div class="rounded-3xl bg-white p-5 ring-1 ring-zinc-200/80 dark:bg-zinc-900 dark:ring-zinc-700/60 sm:p-6">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                                <div class="flex min-w-0 flex-1 items-start gap-4">
                                    <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400">
                                        <x-icon name="crown" class="size-6" />
                                    </div>
                                    <div class="min-w-0">
                                        <h2 class="text-base font-black text-zinc-900 dark:text-zinc-50">اشتراک فعال دارید؟</h2>
                                        <p class="mt-1 text-sm leading-6 text-zinc-500 dark:text-zinc-400">
                                            اگر طرح اشتراک خریداری کرده‌اید، وارد حساب شوید یا کد آپدیت خود را بررسی کنید تا پکیج‌های رایگانِ طرح‌تان مشخص شوند.
                                        </p>
                                    </div>
                                </div>
                                <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
                                    <div class="sm:w-56">
                                        <x-input wire:model="updateCode" icon="key" placeholder="کد آپدیت خود را وارد کنید…" dir="ltr" class="text-start font-mono" />
                                    </div>
                                    <x-btn wire:click="checkMySubscription" icon="search" :loading="true" class="shrink-0">
                                        بررسی اشتراک من
                                    </x-btn>
                                    <a href="{{ route('shop.login', ['redirect' => url()->current()]) }}" wire:navigate>
                                        <x-btn variant="secondary" icon="log-in" class="w-full shrink-0 sm:w-auto">
                                            ورود مشتریان
                                        </x-btn>
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif

                </section>
        </div>
        @endif
