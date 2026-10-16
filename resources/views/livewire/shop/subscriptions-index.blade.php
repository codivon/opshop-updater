<div class="space-y-6">

    {{-- ============ Hero ============ --}}
    <section class="shop-hero relative overflow-hidden">
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div class="shop-hero-glow absolute -top-32 -start-32 size-96 rounded-full blur-3xl"></div>
            <div class="shop-hero-glow absolute -bottom-40 -end-24 size-96 rounded-full opacity-60 blur-3xl"></div>
        </div>

        <div class="relative mx-auto w-full max-w-7xl px-4 py-10 sm:px-6 sm:py-16">
            <div class="max-w-2xl animate-slide-up">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-teal-100 ring-1 ring-white/20">
                    <x-icon name="crown" class="size-3.5" />
                    اشتراک‌های ویژه
                </span>
                <h1 class="shop-hero-title mt-4 text-3xl font-black leading-relaxed sm:text-4xl">
                    طرح‌های اشتراک
                </h1>
                <p class="mt-3 max-w-xl text-sm leading-7 text-teal-50/80 sm:text-base">
                    یک اشتراک انتخاب کنید و به قابلیت‌های ویژه + پکیج‌های منتخب به‌صورت رایگان دسترسی داشته باشید.
                    پس از پرداخت، مدیر درخواست شما را تأیید می‌کند و اشتراک فعال می‌شود.
                </p>

                {{-- how it works --}}
                <div class="mt-6 grid max-w-xl grid-cols-1 gap-2 sm:grid-cols-3">
                    <div class="flex items-center gap-2 rounded-xl bg-white/10 px-3 py-2 text-xs font-medium text-teal-50 ring-1 ring-white/15">
                        <span class="flex size-6 shrink-0 items-center justify-center rounded-lg bg-white/15 text-[10px] font-black">۱</span>
                        کد آپدیت را وارد کنید
                    </div>
                    <div class="flex items-center gap-2 rounded-xl bg-white/10 px-3 py-2 text-xs font-medium text-teal-50 ring-1 ring-white/15">
                        <span class="flex size-6 shrink-0 items-center justify-center rounded-lg bg-white/15 text-[10px] font-black">۲</span>
                        پرداخت امن از درگاه
                    </div>
                    <div class="flex items-center gap-2 rounded-xl bg-white/10 px-3 py-2 text-xs font-medium text-teal-50 ring-1 ring-white/15">
                        <span class="flex size-6 shrink-0 items-center justify-center rounded-lg bg-white/15 text-[10px] font-black">۳</span>
                        تأیید مدیر و فعال‌سازی
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ Plans ============ --}}
    <section class="mx-auto w-full max-w-7xl px-4 pb-12 sm:px-6">
        <div class="mb-6 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-black text-zinc-900 dark:text-zinc-50">انتخاب طرح</h2>
                <p class="mt-0.5 text-xs text-zinc-500">
                    @if($this->plans->count())
                        {{ fa_num($this->plans->count()) }} طرح فعال
                    @endif
                </p>
            </div>
            <a href="{{ route('shop.home') }}" wire:navigate
               class="inline-flex items-center gap-1.5 text-sm font-bold text-brand-600 transition-colors hover:text-brand-500 dark:text-brand-400">
                <x-icon name="package" class="size-4" />
                خرید تکی پکیج‌ها
            </a>
        </div>

        @if($this->plans->count())
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
                @foreach($this->plans as $plan)
                    @php($myOrder = $this->myOrders->get($plan->id))
                    <div class="card relative flex flex-col overflow-hidden transition-all hover:shadow-card-lg {{ $buyingSlug === $plan->slug ? 'ring-2 ring-brand-500' : '' }}"
                         wire:key="plan-{{ $plan->id }}">

                        {{-- ribbon --}}
                        @if($plan->final_price <= 0)
                            <span class="absolute end-4 top-4 z-10 inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-black text-emerald-700 ring-1 ring-emerald-300 dark:bg-emerald-500/15 dark:text-emerald-400 dark:ring-emerald-400/30">
                                <x-icon name="gift" class="size-3.5" />
                                رایگان
                            </span>
                        @elseif($plan->discount_price)
                            <span class="absolute end-4 top-4 z-10 inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-black text-amber-700 ring-1 ring-amber-300 dark:bg-amber-500/15 dark:text-amber-400 dark:ring-amber-400/30">
                                <x-icon name="tag" class="size-3.5" />
                                {{ fa_num(round(($plan->discount_price / max(1, $plan->price)) * 100)) }}٪ تخفیف
                            </span>
                        @endif

                        <div class="flex flex-1 flex-col gap-4 p-6">
                            {{-- header --}}
                            <div class="flex items-start gap-3">
                                <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-400 to-brand-600 text-white shadow-lg shadow-pop">
                                    <x-icon name="crown" class="size-6" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <h3 class="truncate text-lg font-black text-zinc-900 dark:text-zinc-50">{{ $plan->name }}</h3>
                                    <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                        <span class="inline-flex items-center gap-1 rounded-lg bg-brand-50 px-2 py-0.5 text-xs font-bold text-brand-700 dark:bg-brand-500/10 dark:text-brand-400">
                                            <x-icon name="calendar-clock" class="size-3.5" />
                                            {{ $plan->duration_label }}
                                        </span>
                                        @if($plan->is_one_time)
                                            <span class="inline-flex items-center gap-1 rounded-lg bg-amber-50 px-2 py-0.5 text-xs font-bold text-amber-700 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-400/20">
                                                <x-icon name="zap" class="size-3.5" />
                                                یک‌بارمصرف
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            @if($plan->description)
                                <p class="text-sm leading-6 text-zinc-500 dark:text-zinc-400">{{ $plan->description }}</p>
                            @endif

                            {{-- price --}}
                            <div class="flex items-end gap-2">
                                @if($plan->final_price <= 0)
                                    <span class="text-2xl font-black text-emerald-600 dark:text-emerald-400">رایگان</span>
                                @else
                                    <span class="text-2xl font-black tabular-nums text-zinc-900 dark:text-zinc-50">{{ money($plan->final_price) }}</span>
                                    @if($plan->discount_price)
                                        <span class="text-sm font-bold tabular-nums text-zinc-400 line-through">{{ money($plan->price) }}</span>
                                    @endif
                                    <span class="text-xs text-zinc-400">/ یک‌بار پرداخت</span>
                                @endif
                            </div>

                            {{-- features --}}
                            @if($plan->features && count($plan->features))
                                <ul class="space-y-2 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                                    @foreach($plan->features as $feature)
                                        <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                                            <x-icon name="check-circle" class="mt-0.5 size-4 shrink-0 text-emerald-500" />
                                            {{ $feature }}
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            {{-- included packages --}}
                            @if($plan->packages->count())
                                <div class="rounded-2xl bg-zinc-50 p-4 ring-1 ring-zinc-200/70 dark:bg-zinc-800/60 dark:ring-zinc-700/50">
                                    <p class="flex items-center gap-1.5 text-xs font-bold text-zinc-700 dark:text-zinc-200">
                                        <x-icon name="package" class="size-4 text-brand-600 dark:text-brand-400" />
                                        پکیج‌های رایگان این طرح
                                    </p>
                                    <ul class="mt-2.5 space-y-2">
                                        @foreach($plan->packages as $pkg)
                                            <li class="flex items-center justify-between gap-2 text-xs">
                                                <span class="flex min-w-0 items-center gap-1.5 text-zinc-600 dark:text-zinc-300">
                                                    <x-icon name="download" class="size-3.5 shrink-0 text-zinc-400" />
                                                    <span class="truncate">{{ $pkg->name }}</span>
                                                </span>
                                                <span class="shrink-0 rounded-lg bg-emerald-100 px-2 py-0.5 font-bold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400">
                                                    {{ \App\Models\SubscriptionPlan::freeMonthsLabel((int) $pkg->pivot->free_months) }}
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            {{-- already purchased state --}}
                            @if($myOrder)
                                <div class="rounded-xl p-3 text-xs font-bold ring-1 {{ $myOrder->admin_status === 'approved' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-400/20' : ($myOrder->admin_status === 'rejected' ? 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20' : 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-400/20') }}">
                                    @if($myOrder->admin_status === 'approved')
                                        <span class="flex items-center gap-1.5"><x-icon name="badge-check" class="size-4" /> این طرح برای شما فعال شده است.</span>
                                    @elseif($myOrder->admin_status === 'rejected')
                                        <span class="flex items-center gap-1.5"><x-icon name="x-circle" class="size-4" /> درخواست قبلی رد شده: {{ \Illuminate\Support\Str::limit($myOrder->rejected_reason, 60) }}</span>
                                    @else
                                        <span class="flex items-center gap-1.5"><x-icon name="hourglass" class="size-4" /> درخواست شما در انتظار تأیید مدیر است.</span>
                                    @endif
                                </div>
                            @endif

                            <div class="mt-auto pt-2">
                                @if($myOrder && $myOrder->admin_status !== 'rejected')
                                    <x-btn variant="secondary" class="w-full" disabled>
                                        {{ $myOrder->admin_status === 'approved' ? 'فعال شده' : 'در انتظار تأیید مدیر' }}
                                    </x-btn>
                                @else
                                    <x-btn variant="primary" icon="shopping-cart" class="w-full" wire:click="setBuying('{{ $plan->slug }}')">
                                        {{ $buyingSlug === $plan->slug ? 'بستن فرم خرید' : 'خرید این طرح' }}
                                    </x-btn>
                                @endif
                            </div>
                        </div>

                        {{-- inline checkout --}}
                        @if($buyingSlug === $plan->slug)
                            @php($pw = $this->priceWithDiscount($plan))
                            @php($checkoutFree = $pw['final'] <= 0)
                            <form wire:submit="buy" class="space-y-3 border-t border-zinc-100 bg-brand-50/50 p-5 dark:border-zinc-800 dark:bg-brand-500/5">
                                <div>
                                    <label class="mb-1.5 flex items-center gap-1.5 text-xs font-bold text-zinc-700 dark:text-zinc-200">
                                        <x-icon name="key" class="size-3.5 text-brand-600 dark:text-brand-400" />
                                        کد آپدیت پروژه شما
                                    </label>
                                    <input type="text" wire:model="updateCode" placeholder="مثلاً 1F61…" dir="ltr"
                                           class="input font-mono {{ $errors->get('updateCode') ? 'input-error' : '' }}" />
                                    @error('updateCode') <p class="mt-1 text-xs font-bold text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                                </div>

                                @if(!$checkoutFree)
                                    <div>
                                        @include('livewire.shop.partials.gateway-picker', ['gateways' => $this->gateways])
                                    </div>
                                @endif

                                {{-- کد تخفیف --}}
                                @include('livewire.shop.partials.discount-field', ['baseAmount' => (int) $plan->final_price])

                                <div class="flex items-center justify-between gap-2 rounded-xl bg-white p-3 text-sm ring-1 ring-zinc-200/70 dark:bg-zinc-800 dark:ring-zinc-700/50">
                                    <span class="text-zinc-500 dark:text-zinc-400">مبلغ قابل پرداخت</span>
                                    <span class="text-end font-black tabular-nums text-zinc-900 dark:text-zinc-50">
                                        @if($checkoutFree)
                                            <span class="text-emerald-600">{{ $pw['discount'] > 0 ? 'رایگان با کد تخفیف' : 'رایگان' }}</span>
                                        @elseif($pw['discount'] > 0)
                                            <span class="block text-[11px] font-normal text-zinc-400 line-through">{{ money($pw['base']) }}</span>
                                            <span class="text-emerald-600">{{ money($pw['final']) }}</span>
                                        @else
                                            {{ money($pw['final']) }}
                                        @endif
                                    </span>
                                </div>

                                <x-btn variant="primary" icon="wallet" class="w-full" type="submit" :loading="true">
                                    {{ $checkoutFree ? 'ثبت درخواست رایگان' : 'ادامه و پرداخت' }}
                                </x-btn>
                                <p class="text-center text-[11px] leading-5 text-zinc-400">
                                    پس از پرداخت، درخواست شما برای تأیید مدیر ارسال می‌شود و اشتراک پس از تأیید فعال خواهد شد.
                                </p>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="card">
                <x-empty icon="crown" title="فعلاً طرح اشتراکی فعال نیست" description="به‌زودی طرح‌های اشتراک جدید اضافه می‌شود؛ فعلاً می‌توانید پکیج‌ها را تکی خریداری کنید." />
            </div>
        @endif
    </section>
</div>
