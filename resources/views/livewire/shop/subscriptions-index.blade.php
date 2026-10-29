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
                    @php($durOpt = $this->displayDurationFor($plan))
                    @php($durOpts = $plan->durationOptions())
                    @php($hasDurs = $durOpts->isNotEmpty())
                    @php($maxDisc = $hasDurs ? (int) $durOpts->max('discount_percent') : 0)
                    @php($allFree = $hasDurs && $durOpts->every(fn ($d) => $d->final_price <= 0))
                    {{-- جزئیات مؤثر هر مدت (قابلیت‌ها + پکیج‌ها) برای مودال — طرح تک‌مدتی هم یک ردیف دارد (Task 65) --}}
                    @php($cardDetails = $this->cardDetailsFor($plan))
                    @php($hasAnyDetails = collect($cardDetails)->contains(fn ($d) => count($d['features']) > 0 || count($d['packages']) > 0))
                    {{-- ایندکس مدتِ نمایشی در آرایهٔ جزئیات — کلیک روی نوار فشرده همین مدت را باز می‌کند --}}
                    @php($curIndex = $hasDurs
                        ? max(0, (int) $durOpts->search(fn ($d) => (int) $d->duration_months === (int) ($durOpt?->duration_months ?? -1)))
                        : 0)
                    <div class="card relative flex flex-col overflow-hidden transition-all hover:shadow-card-lg {{ $buyingSlug === $plan->slug ? 'ring-2 ring-brand-500' : '' }}"
                         wire:key="plan-{{ $plan->id }}"
                         @if($hasDurs || $hasAnyDetails) x-data="{ open: false, cur: 0, durs: JSON.parse(atob('{{ base64_encode((string) json_encode($cardDetails)) }}')), faNum(n) { return String(n).replace(/[0-9]/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]) } }" @endif>

                        {{-- ribbon --}}
                        @if($hasDurs && $allFree)
                            <span class="absolute end-4 top-4 z-10 inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-black text-emerald-700 ring-1 ring-emerald-300 dark:bg-emerald-500/15 dark:text-emerald-400 dark:ring-emerald-400/30">
                                <x-icon name="gift" class="size-3.5" />
                                رایگان
                            </span>
                        @elseif($hasDurs && $maxDisc > 0)
                            <span class="absolute end-4 top-4 z-10 inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-black text-amber-700 ring-1 ring-amber-300 dark:bg-amber-500/15 dark:text-amber-400 dark:ring-amber-400/30">
                                <x-icon name="tag" class="size-3.5" />
                                تا {{ fa_num($maxDisc) }}٪ تخفیف
                            </span>
                        @elseif(!$hasDurs && $plan->final_price <= 0)
                            <span class="absolute end-4 top-4 z-10 inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-black text-emerald-700 ring-1 ring-emerald-300 dark:bg-emerald-500/15 dark:text-emerald-400 dark:ring-emerald-400/30">
                                <x-icon name="gift" class="size-3.5" />
                                رایگان
                            </span>
                        @elseif(!$hasDurs && $plan->discount_price > 0 && $plan->price > $plan->final_price)
                            @php($legacyDisc = round(($plan->discount_price / max(1, $plan->price)) * 100))
                            <span class="absolute end-4 top-4 z-10 inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-black text-amber-700 ring-1 ring-amber-300 dark:bg-amber-500/15 dark:text-amber-400 dark:ring-amber-400/30">
                                <x-icon name="tag" class="size-3.5" />
                                {{ fa_num($legacyDisc) }}٪ تخفیف
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
                                        @unless($hasDurs)
                                            {{-- طرح‌های چندمدتی مدت‌ها را با تایل‌ها نشان می‌دهند؛ این نشان فقط برای تک‌مدتی است --}}
                                            <span class="inline-flex items-center gap-1 rounded-lg bg-brand-50 px-2 py-0.5 text-xs font-bold text-brand-700 dark:bg-brand-500/10 dark:text-brand-400"
                                                  title="مدت اعتبار طرح">
                                                <x-icon name="calendar-clock" class="size-3.5" />
                                                {{ $plan->duration_label }}
                                            </span>
                                        @endunless
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

                            {{-- قیمت — طرح‌های چندمدتی: تایل هر مدت با قیمت اصلی و تخفیف؛ تک‌مدتی: قیمت بزرگ --}}
                            @if($hasDurs)
                                <div class="rounded-2xl bg-zinc-50/80 p-3.5 ring-1 ring-zinc-200/70 dark:bg-zinc-800/60 dark:ring-zinc-700/50">
                                    <p class="mb-2.5 flex items-center gap-1.5 text-[11px] font-black text-zinc-500 dark:text-zinc-400">
                                        <x-icon name="calendar-clock" class="size-3.5 text-brand-600 dark:text-brand-400" />
                                        مدت‌های اعتبار و قیمت‌ها
                                    </p>
                                    <div class="grid grid-cols-2 gap-2" role="list" aria-label="گزینه‌های مدت اعتبار">
                                        @foreach($durOpts as $d)
                                            <div class="rounded-xl bg-white p-2.5 ring-1 ring-zinc-200/80 transition-shadow hover:shadow-sm dark:bg-zinc-800 dark:ring-zinc-700/60"
                                                 role="listitem"
                                                 title="هنگام خرید می‌توانید این مدت را انتخاب کنید">
                                                <div class="flex items-center justify-between gap-1">
                                                    <span class="text-xs font-black text-zinc-700 dark:text-zinc-200">{{ $d->duration_label }}</span>
                                                    @if($d->hasOwnFeatures() || $d->hasOwnPackages())
                                                        <span class="shrink-0 rounded-md bg-brand-100 px-1.5 py-0.5 text-[10px] font-black text-brand-700 dark:bg-brand-500/15 dark:text-brand-300" title="این مدت پیکربندی اختصاصی قابلیت‌ها/پکیج‌ها دارد">ویژه</span>
                                                    @endif
                                                    @if($d->discount_percent > 0)
                                                        <span class="shrink-0 rounded-md bg-amber-100 px-1.5 py-0.5 text-[10px] font-black text-amber-700 dark:bg-amber-500/15 dark:text-amber-400">{{ fa_num($d->discount_percent) }}٪</span>
                                                    @endif
                                                </div>
                                                <div class="mt-1 flex flex-wrap items-baseline gap-x-1.5 gap-y-0.5">
                                                    @if($d->final_price <= 0)
                                                        <span class="text-sm font-black text-emerald-600 dark:text-emerald-400">رایگان</span>
                                                    @else
                                                        <span class="text-base font-black tabular-nums text-zinc-900 dark:text-zinc-50">{{ money($d->final_price, false) }}</span>
                                                        @if($d->has_discount)
                                                            <span class="text-[11px] tabular-nums text-zinc-400 line-through">{{ money($d->price, false) }}</span>
                                                        @endif
                                                        <span class="text-[10px] text-zinc-400">تومان</span>
                                                    @endif
                                                </div>
                                                {{-- جزئیات همین مدت (قابلیت‌ها + پکیج‌های رایگان) — مودال آنی سمت مرورگر --}}
                                                <button type="button" @click="cur = {{ $loop->index }}; open = true"
                                                        class="mt-2 flex w-full cursor-pointer items-center justify-center gap-1 rounded-lg bg-zinc-50 px-2 py-1.5 text-[10px] font-bold text-zinc-500 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:bg-zinc-900/60 dark:text-zinc-400 dark:hover:bg-brand-500/15 dark:hover:text-brand-300"
                                                        aria-haspopup="dialog" title="دیدن قابلیت‌ها و پکیج‌های رایگان این مدت">
                                                    <x-icon name="eye" class="size-3.5" />
                                                    قابلیت‌ها و پکیج‌ها
                                                </button>
                                            </div>
                                        @endforeach
                                    </div>
                                    <p class="mt-2.5 flex items-center justify-center gap-1 text-center text-[10px] leading-4 text-zinc-400">
                                        <x-icon name="shopping-cart" class="size-3" />
                                        هنگام خرید، مدت اعتبار را انتخاب می‌کنید
                                    </p>
                                </div>
                            @else
                                <div class="flex items-end gap-2">
                                    @if($plan->final_price <= 0)
                                        <span class="text-2xl font-black text-emerald-600 dark:text-emerald-400">رایگان</span>
                                    @else
                                        <span class="text-2xl font-black tabular-nums text-zinc-900 dark:text-zinc-50">{{ money($plan->final_price) }}</span>
                                        @if($plan->discount_price > 0 && $plan->price > $plan->final_price)
                                            <span class="text-sm font-bold tabular-nums text-zinc-400 line-through">{{ money($plan->price) }}</span>
                                        @endif
                                        <span class="text-xs text-zinc-400">/ یک‌بار پرداخت</span>
                                    @endif
                                </div>
                            @endif

                            {{-- نوار فشردهٔ «قابلیت‌ها و پکیج‌های رایگان» (Task 65) — به‌جای لیست‌های بلند؛
                                 کلیک = مودال جزئیاتِ مدتِ نمایشی (آنی، سمت مرورگر) --}}
                            @if($hasAnyDetails)
                                @php($curDetail = $cardDetails[$curIndex] ?? ($cardDetails[0] ?? null))
                                <button type="button" @click="cur = {{ $curIndex }}; open = true"
                                        class="group relative flex w-full cursor-pointer items-center gap-3 overflow-hidden rounded-2xl bg-gradient-to-l from-brand-50 to-white p-3 text-start ring-1 ring-brand-100 transition-all hover:shadow-md hover:ring-brand-300 dark:from-brand-500/10 dark:to-zinc-800/40 dark:ring-brand-500/20 dark:hover:ring-brand-400/40"
                                        aria-haspopup="dialog">
                                    <span class="pointer-events-none absolute -end-6 -top-8 size-20 rounded-full bg-brand-100/60 blur-xl transition-opacity group-hover:opacity-100 dark:bg-brand-500/10" aria-hidden="true"></span>
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-brand-400 to-brand-600 text-white shadow-md shadow-brand-500/25">
                                        <x-icon name="gift" class="size-5" />
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-black text-zinc-800 dark:text-zinc-100">این طرح قابلیت‌ها و پکیج‌های رایگان دارد</span>
                                        <span class="mt-1 flex flex-wrap items-center gap-1.5">
                                            @if($curDetail && count($curDetail['features']))
                                                <span class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-1.5 py-0.5 text-[10px] font-black text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                                                    <x-icon name="list-checks" class="size-3" />
                                                    {{ fa_num(count($curDetail['features'])) }} قابلیت
                                                </span>
                                            @endif
                                            @if($curDetail && count($curDetail['packages']))
                                                <span class="inline-flex items-center gap-1 rounded-lg bg-brand-50 px-1.5 py-0.5 text-[10px] font-black text-brand-700 dark:bg-brand-500/10 dark:text-brand-400">
                                                    <x-icon name="package" class="size-3" />
                                                    {{ fa_num(count($curDetail['packages'])) }} پکیج رایگان
                                                </span>
                                            @endif
                                            <span class="text-[10px] font-bold text-zinc-400 group-hover:text-brand-600 dark:group-hover:text-brand-400">برای مشاهده کلیک کنید</span>
                                        </span>
                                    </span>
                                    <x-icon name="chevron-left" class="size-4 shrink-0 text-brand-400 transition-transform group-hover:-translate-x-0.5" />
                                </button>
                                @if($hasDurs)
                                    <p class="-mt-2 flex items-center justify-center gap-1 text-center text-[10px] leading-4 text-zinc-400">
                                        <x-icon name="info" class="size-3 shrink-0" />
                                        پیکربندی هر مدت می‌تواند متفاوت باشد — از دکمهٔ هر مدت هم می‌توانید ببینید
                                    </p>
                                @endif
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

                                {{-- انتخاب مدت اعتبار (طرح‌های چندمدتی) — پیش‌فرض: گزینهٔ اول --}}
                                @if($plan->hasDurations())
                                    <div role="group" aria-label="انتخاب مدت اعتبار">
                                        <label class="mb-1.5 flex items-center gap-1.5 text-xs font-bold text-zinc-700 dark:text-zinc-200">
                                            <x-icon name="calendar-clock" class="size-3.5 text-brand-600 dark:text-brand-400" />
                                            مدت اعتبار — انتخاب الزامی است (پیش‌فرض: گزینهٔ اول)
                                        </label>
                                        <div class="grid gap-2 sm:grid-cols-2">
                                            @foreach($plan->durationOptions() as $d)
                                                @php($isSel = ($durOpt?->duration_months ?? -1) === (int) $d->duration_months)
                                                <button type="button" wire:click="selectDuration({{ $plan->id }}, {{ $d->duration_months }})"
                                                        wire:key="cdur-{{ $plan->id }}-{{ $d->duration_months }}"
                                                        aria-pressed="{{ $isSel ? 'true' : 'false' }}"
                                                        @class([
                                                            'flex cursor-pointer items-center justify-between gap-2 rounded-xl px-3.5 py-2.5 text-start transition-all',
                                                            'bg-brand-600/5 ring-2 ring-brand-500' => $isSel,
                                                            'bg-white ring-1 ring-zinc-200 hover:ring-brand-300 dark:bg-zinc-800/60 dark:ring-zinc-700 dark:hover:ring-brand-400/40' => !$isSel,
                                                        ])>
                                                    <span>
                                                        <span @class([
                                                            'block text-sm font-black',
                                                            'text-brand-700 dark:text-brand-400' => $isSel,
                                                            'text-zinc-800 dark:text-zinc-100' => !$isSel,
                                                        ])>{{ $d->duration_label }}</span>
                                                        <span class="mt-0.5 block text-[11px] text-zinc-400">{{ $d->duration_months === 0 ? 'بدون انقضا' : 'اعتبار از زمان تأیید مدیر' }}</span>
                                                    </span>
                                                    <span class="text-end">
                                                        @if($d->final_price <= 0)
                                                            <span class="block text-sm font-black text-emerald-600">رایگان</span>
                                                        @else
                                                            @if($d->has_discount)
                                                                <span class="block text-[11px] tabular-nums text-zinc-400 line-through">{{ money($d->price, false) }}</span>
                                                            @endif
                                                            <span @class(['block text-sm font-black tabular-nums', 'text-brand-700 dark:text-brand-400' => $isSel, 'text-zinc-800 dark:text-zinc-100' => !$isSel])>{{ money($d->final_price, false) }}</span>
                                                        @endif
                                                    </span>
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

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

                                {{-- کد تخفیف (پایه: مدت انتخابی) --}}
                                @include('livewire.shop.partials.discount-field', ['baseAmount' => $this->effectiveBaseFor($plan)])

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

                        {{-- ============ مودال «جزئیات مدت» (Task 64 + 65) ============
                             کاملاً سمت مرورگر با Alpine — بدون رفت‌وبرگشت سرور، بازشدن آنی.
                             برای طرح‌های تک‌مدتی هم رندر می‌شود (cardDetailsFor یک ردیف می‌سازد).
                             داده‌ها از cardDetailsFor() (ارث‌بری رعایت شده) در x-data --}}
                        @if($hasDurs || $hasAnyDetails)
                        <div x-cloak x-show="open" class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
                             role="dialog" aria-modal="true" aria-label="جزئیات مدت اعتبار"
                             @keydown.escape.window="open = false">
                            {{-- backdrop --}}
                            <div x-show="open"
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                                 x-transition:leave="transition ease-in duration-150"
                                 x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                                 class="absolute inset-0 bg-zinc-950/60 backdrop-blur-sm"
                                 @click="open = false" aria-hidden="true"></div>

                            {{-- dialog --}}
                            <div x-show="open"
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95"
                                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                                 x-transition:leave="transition ease-in duration-150"
                                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                                 x-transition:leave-end="opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95"
                                 class="relative flex max-h-[88vh] w-full max-w-lg flex-col overflow-clip rounded-3xl bg-white shadow-pop ring-1 ring-zinc-900/5 dark:bg-zinc-900 dark:ring-white/10">

                                {{-- هدر گرادیانی برند --}}
                                <div class="relative overflow-hidden bg-gradient-to-br from-brand-500 via-brand-600 to-brand-800 px-5 py-4 text-white">
                                    <div class="pointer-events-none absolute -end-10 -top-14 size-40 rounded-full bg-white/10 blur-2xl" aria-hidden="true"></div>
                                    <div class="pointer-events-none absolute -bottom-16 -start-8 size-32 rounded-full bg-white/10 blur-2xl" aria-hidden="true"></div>
                                    <div class="relative flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="flex items-center gap-1.5 text-[11px] font-medium text-white/75">
                                                <x-icon name="crown" class="size-3.5 shrink-0" />
                                                <span class="truncate">{{ $plan->name }}</span>
                                            </p>
                                            <h3 class="mt-1 truncate text-lg font-black" x-text="durs[cur]?.label"></h3>
                                            <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                                <span class="inline-flex items-center gap-1 rounded-xl bg-white/15 px-2.5 py-1 text-xs font-black ring-1 ring-white/25"
                                                      x-text="durs[cur]?.price"></span>
                                                <template x-if="durs[cur]?.original">
                                                    <span class="text-[11px] tabular-nums text-white/60 line-through" x-text="durs[cur]?.original"></span>
                                                </template>
                                                <template x-if="durs[cur]?.percent > 0">
                                                    <span class="rounded-xl bg-amber-300/90 px-2 py-1 text-[10px] font-black text-amber-950" x-text="faNum(durs[cur]?.percent ?? 0) + '٪ تخفیف'"></span>
                                                </template>
                                                <template x-if="durs[cur]?.custom">
                                                    <span class="inline-flex items-center gap-1 rounded-xl bg-white/15 px-2 py-1 text-[10px] font-black ring-1 ring-white/25">
                                                        <x-icon name="sparkles" class="size-3" />
                                                        پیکربندی اختصاصی
                                                    </span>
                                                </template>
                                            </div>
                                        </div>
                                        <button type="button" @click="open = false"
                                                class="rounded-xl p-1.5 text-white/80 transition-colors hover:bg-white/15 hover:text-white"
                                                aria-label="بستن">
                                            <x-icon name="x" class="size-5" />
                                        </button>
                                    </div>
                                </div>

                                {{-- بدنه --}}
                                <div class="min-h-0 flex-1 overflow-y-auto">
                                    <template x-if="durs[cur]">
                                        <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                            {{-- قابلیت‌های همراه این مدت --}}
                                            <section class="px-5 py-4">
                                                <p class="flex items-center gap-1.5 text-xs font-black text-zinc-700 dark:text-zinc-200">
                                                    <x-icon name="list-checks" class="size-4 text-brand-600 dark:text-brand-400" />
                                                    قابلیت‌های همراه این مدت
                                                    <span class="rounded-full bg-zinc-100 px-1.5 py-0.5 text-[10px] font-bold text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400"
                                                          x-text="faNum(durs[cur].features.length) + ' مورد'"></span>
                                                </p>
                                                <ul class="mt-3 space-y-2" x-show="durs[cur].features.length">
                                                    <template x-for="(feature, fi) in durs[cur].features" :key="'f' + cur + '-' + fi">
                                                        <li class="flex items-start gap-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                                                            <x-icon name="check-circle" class="mt-0.5 size-4 shrink-0 text-emerald-500" />
                                                            <span x-text="feature"></span>
                                                        </li>
                                                    </template>
                                                </ul>
                                                <p class="mt-3 text-xs text-zinc-400" x-show="!durs[cur].features.length">قابلیتی برای این مدت ثبت نشده است.</p>
                                            </section>

                                            {{-- پکیج‌های رایگان این مدت --}}
                                            <section class="px-5 py-4">
                                                <p class="flex items-center gap-1.5 text-xs font-black text-zinc-700 dark:text-zinc-200">
                                                    <x-icon name="package" class="size-4 text-brand-600 dark:text-brand-400" />
                                                    پکیج‌های رایگان این مدت
                                                    <span class="rounded-full bg-zinc-100 px-1.5 py-0.5 text-[10px] font-bold text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400"
                                                          x-text="faNum(durs[cur].packages.length) + ' پکیج'"></span>
                                                </p>
                                                <ul class="mt-3 space-y-2" x-show="durs[cur].packages.length">
                                                    <template x-for="(pkg, pi) in durs[cur].packages" :key="'p' + cur + '-' + pi">
                                                        <li class="flex items-center justify-between gap-2 rounded-xl bg-zinc-50 px-3 py-2 ring-1 ring-zinc-200/70 dark:bg-zinc-800/60 dark:ring-zinc-700/50">
                                                            <span class="flex min-w-0 items-center gap-1.5 text-xs font-bold text-zinc-600 dark:text-zinc-300">
                                                                <x-icon name="download" class="size-3.5 shrink-0 text-zinc-400" />
                                                                <span class="truncate" x-text="pkg.name"></span>
                                                            </span>
                                                            <span class="shrink-0 rounded-lg bg-emerald-100 px-2 py-0.5 text-[10px] font-black text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400"
                                                                  x-text="pkg.free"></span>
                                                        </li>
                                                    </template>
                                                </ul>
                                                <p class="mt-3 text-xs text-zinc-400" x-show="!durs[cur].packages.length">پکیج رایگانی برای این مدت تعریف نشده است.</p>
                                            </section>

                                            {{-- یادآوری — برای طرح چندمدتی/تک‌مدتی متفاوت --}}
                                            @if($hasDurs)
                                            <p class="flex items-center justify-center gap-1.5 bg-zinc-50/60 px-5 py-3 text-center text-[10px] leading-5 text-zinc-400 dark:bg-zinc-800/40">
                                                <x-icon name="info" class="size-3.5 shrink-0" />
                                                پیکربندی هر مدت می‌تواند متفاوت باشد؛ هنگام خرید، دقیقاً پیکربندی همین مدت اعمال می‌شود.
                                            </p>
                                            @else
                                            <p class="flex items-center justify-center gap-1.5 bg-zinc-50/60 px-5 py-3 text-center text-[10px] leading-5 text-zinc-400 dark:bg-zinc-800/40">
                                                <x-icon name="info" class="size-3.5 shrink-0" />
                                                پس از خرید و تأیید مدیر، این قابلیت‌ها و پکیج‌های رایگان برای شما فعال می‌شود.
                                            </p>
                                            @endif
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
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
