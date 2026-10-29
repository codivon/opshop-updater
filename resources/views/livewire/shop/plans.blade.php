<div class="space-y-6">

    {{-- ============ Breadcrumb ============ --}}
    <nav class="mx-auto w-full max-w-7xl px-4 pt-6 sm:px-6" aria-label="مسیر">
        <ol class="flex items-center gap-1.5 text-xs text-zinc-500">
            <li>
                <a href="{{ route('shop.home') }}" wire:navigate class="inline-flex items-center gap-1 font-medium transition-colors hover:text-brand-600">
                    <x-icon name="home" class="size-3.5" />
                    فروشگاه
                </a>
            </li>
            <li aria-hidden="true"><x-icon name="chevron-left" class="size-3.5 text-zinc-300" /></li>
            <li class="font-bold text-zinc-700 dark:text-zinc-200">طرح‌های اشتراک</li>
        </ol>
    </nav>

    {{-- ============ Hero ============ --}}
    <section class="shop-hero relative overflow-hidden">
        {{-- decorative glows --}}
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div class="shop-hero-glow absolute -top-32 -start-32 size-96 rounded-full blur-3xl"></div>
            <div class="shop-hero-glow absolute -bottom-40 -end-24 size-96 rounded-full opacity-60 blur-3xl"></div>
        </div>

        <div class="relative mx-auto w-full max-w-7xl px-4 py-10 sm:px-6 sm:py-16">
            <div class="max-w-2xl animate-slide-up">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-teal-100 ring-1 ring-white/20">
                    <x-icon name="crown" class="size-3.5" />
                    یک خرید، دسترسی به چندین پکیج
                </span>
                <h1 class="shop-hero-title mt-4 text-3xl font-black leading-relaxed sm:text-4xl">
                    طرح‌های اشتراک
                </h1>
                <p class="mt-3 max-w-xl text-sm leading-7 text-teal-50/80 sm:text-base">
                    با خرید یک طرح، به مجموعه‌ای از پکیج‌ها با لایسنس اختصاصی دسترسی پیدا کنید — پس از پرداخت، فعال‌سازی توسط مدیر انجام می‌شود.
                </p>

                {{-- روند خرید: انتخاب → پرداخت → تأیید مدیر → لایسنس --}}
                <div class="mt-6 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1.5 text-xs font-medium text-teal-50 ring-1 ring-white/15">
                        <x-icon name="wallet" class="size-3.5" />
                        انتخاب طرح و پرداخت
                    </span>
                    <x-icon name="chevron-left" class="size-3.5 text-teal-200/50" aria-hidden="true" />
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1.5 text-xs font-medium text-teal-50 ring-1 ring-white/15">
                        <x-icon name="clock" class="size-3.5" />
                        در انتظار تأیید مدیر
                    </span>
                    <x-icon name="chevron-left" class="size-3.5 text-teal-200/50" aria-hidden="true" />
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1.5 text-xs font-medium text-teal-50 ring-1 ring-white/15">
                        <x-icon name="badge-check" class="size-3.5" />
                        صدور و فعال‌سازی لایسنس
                    </span>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ Stats ============ --}}
    <section class="mx-auto flex w-full max-w-7xl flex-wrap items-center justify-center gap-4 px-4 sm:justify-start sm:px-6">
        <x-stat label="طرح فعال" :value="fa_num($this->stats['plans'])" icon="crown" variant="primary" />
        <x-stat label="پکیج قابل دسترسی" :value="fa_num($this->stats['packages'])" icon="package" variant="info" />
        <x-stat label="طرح رایگان" :value="fa_num($this->stats['free'])" icon="gift" variant="warning" />
    </section>

    {{-- ============ Plans grid ============ --}}
    @if($this->plans->isNotEmpty())
        @php($bestValueId = $this->plans->filter(fn ($p) => $p->has_discount)->sortByDesc('discount_percent')->first()?->id)
        <section class="mx-auto w-full max-w-7xl px-4 pb-6 sm:px-6">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-black text-zinc-900 dark:text-zinc-50">مقایسه طرح‌ها</h2>
                    <p class="mt-0.5 text-xs text-zinc-500">
                        {{ fa_num($this->stats['plans']) }} طرح · {{ fa_num($this->stats['packages']) }} پکیج قابل دسترسی · فعال‌سازی پس از تأیید مدیر
                    </p>
                </div>
                <a href="{{ route('shop.home') }}" wire:navigate
                   class="inline-flex items-center gap-1 text-xs font-bold text-brand-600 transition-colors hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300">
                    مشاهده پکیج‌های فروشگاه
                    <x-icon name="arrow-left" class="size-3.5" />
                </a>
            </div>

            <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                @foreach($this->plans as $plan)
                    @php($isBestValue = $plan->id === $bestValueId)
                    @php($durationOption = $this->selectedDurationFor($plan))
                    @php($cardFinal = $durationOption?->final_price ?? $plan->final_price)
                    @php($cardPrice = $durationOption?->price ?? $plan->price)
                    @php($cardHasDiscount = $durationOption ? $durationOption->has_discount : $plan->has_discount)
                    @php($cardDiscountPercent = $durationOption ? $durationOption->discount_percent : $plan->discount_percent)
                    @php($cardDurationLabel = $durationOption?->duration_label ?? $plan->duration_label)
                    {{-- قابلیت‌ها/پکیج‌های مؤثر بر اساس مدت انتخابی (ارث‌بری از طرح در صورت نبود پیکربندی اختصاصی) — Task 63 --}}
                    @php($cardFeatures = $durationOption ? $durationOption->resolvedFeatures($plan->features ?? []) : ($plan->features ?? []))
                    @php($cardPackages = $durationOption?->resolvedPackages($plan->packages) ?? $plan->packages)
                    <article wire:key="plan-{{ $plan->id }}" @class([
                        'card relative flex flex-col overflow-hidden transition-all hover:shadow-card-lg',
                        'ring-2 ring-brand-500/60' => $isBestValue,
                    ])>

                        {{-- نوار گرادیانی طرح پیشنهادی --}}
                        @if($isBestValue)
                            <div class="h-1.5 bg-gradient-to-l from-amber-400 via-brand-400 to-teal-500" aria-hidden="true"></div>
                            <span class="absolute top-4 end-4 inline-flex items-center gap-1.5 rounded-full bg-gradient-to-l from-amber-400 to-amber-500 px-3 py-1 text-[11px] font-black text-amber-950 shadow-lg">
                                <x-icon name="star" class="size-3.5" />
                                پیشنهاد ویژه
                            </span>
                        @endif

                        {{-- ---- سربرگ: نام + بج‌ها ---- --}}
                        <div class="flex flex-col gap-4 p-5 sm:p-6" @class(['pe-16' => $isBestValue])>
                            <div class="space-y-2.5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-base font-black text-zinc-900 dark:text-zinc-50">{{ $plan->name }}</h3>
                                    @if($cardFinal <= 0)
                                        <x-badge variant="info" icon="gift">رایگان</x-badge>
                                    @endif
                                    @if($plan->is_one_time)
                                        <x-badge variant="warning" icon="ticket">یک‌بار مصرف</x-badge>
                                    @endif
                                    <x-badge variant="neutral" icon="clock" :title="$plan->hasDurations() ? 'مدت انتخاب‌شده — قابل تغییر' : 'مدت اعتبار طرح'">{{ $cardDurationLabel }}</x-badge>
                                </div>

                                @if($plan->description)
                                    <p class="text-xs leading-5 text-zinc-500 dark:text-zinc-400">{{ $plan->description }}</p>
                                @endif
                            </div>

                            {{-- ---- قیمت + انتخاب مدت اعتبار ---- --}}
                            <div class="rounded-2xl bg-zinc-50 p-4 ring-1 ring-zinc-200/80 dark:bg-zinc-800/60 dark:ring-zinc-700">
                                @if($cardFinal <= 0)
                                    <p class="text-2xl font-black text-emerald-600">رایگان</p>
                                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">بدون پرداخت — فقط تأیید مدیر</p>
                                @else
                                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                                        <span class="text-2xl font-black tabular-nums text-zinc-900 dark:text-zinc-50">{{ money($cardFinal, false) }}</span>
                                        <span class="text-xs font-medium text-zinc-400">تومان</span>
                                        @if($cardHasDiscount)
                                            <span class="text-sm tabular-nums text-zinc-400 line-through">{{ money($cardPrice, false) }}</span>
                                        @endif
                                    </div>
                                    @if($cardHasDiscount)
                                        <div class="mt-2">
                                            <x-badge variant="danger" icon="trending-down">{{ fa_num($cardDiscountPercent) }}٪ تخفیف</x-badge>
                                        </div>
                                    @endif
                                @endif

                                {{-- مدت‌های اعتبار طرح — انتخاب گزینه، قیمت کارت به‌روز می‌شود --}}
                                @if($plan->hasDurations())
                                    <div class="mt-3" role="group" aria-label="انتخاب مدت اعتبار">
                                        <p class="mb-1.5 text-[11px] font-bold text-zinc-500 dark:text-zinc-400">مدت اعتبار را انتخاب کنید:</p>
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach($plan->durationOptions() as $d)
                                                @php($isSelected = ($durationOption?->duration_months ?? -1) === (int) $d->duration_months)
                                                <button type="button" wire:click="selectDuration({{ $plan->id }}, {{ $d->duration_months }})"
                                                        wire:key="dur-{{ $plan->id }}-{{ $d->duration_months }}"
                                                        aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                                                        @class([
                                                            'inline-flex cursor-pointer items-center gap-1 rounded-xl px-3 py-1.5 text-xs font-bold transition-all',
                                                            'bg-brand-600 text-white shadow-sm' => $isSelected,
                                                            'bg-white text-zinc-600 ring-1 ring-zinc-200 hover:bg-brand-50 hover:text-brand-700 dark:bg-zinc-800 dark:text-zinc-300 dark:ring-zinc-700 dark:hover:bg-brand-500/10 dark:hover:text-brand-400' => !$isSelected,
                                                        ])>
                                                    {{ $d->duration_label }}
                                                    <span @class(['text-white/70' => $isSelected, 'text-zinc-400' => !$isSelected]) class="tabular-nums">{{ $d->final_price <= 0 ? 'رایگان' : money($d->final_price, false) }}</span>
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>

                            {{-- ---- قابلیت‌های طرح (مؤثر برای مدت انتخابی) ---- --}}
                            @if($cardFeatures)
                                <div>
                                    <p class="mb-2.5 text-xs font-bold text-zinc-500 dark:text-zinc-400">قابلیت‌های طرح</p>
                                    <ul class="space-y-2">
                                        @foreach($cardFeatures as $feature)
                                            <li class="flex items-start gap-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                                                <x-icon name="check" class="mt-1.5 size-4 shrink-0 text-emerald-500" />
                                                <span>{{ $feature }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>

                        {{-- ---- پکیج‌های این طرح ---- --}}
                        <div class="border-t border-zinc-100 p-5 dark:border-zinc-800 sm:px-6">
                            <div class="mb-3 flex items-center justify-between gap-2">
                                <p class="flex items-center gap-1.5 text-xs font-bold text-zinc-500 dark:text-zinc-400">
                                    <x-icon name="package" class="size-3.5" />
                                    پکیج‌های این طرح
                                </p>
                                <span class="text-xs tabular-nums text-zinc-400">{{ fa_num($cardPackages->count()) }} پکیج</span>
                            </div>

                            @php($expanded = in_array($plan->id, $this->expandedPlans, true))
                            @php($visiblePackages = $expanded || $cardPackages->count() <= 4 ? $cardPackages : $cardPackages->take(4))
                            <ul class="space-y-1.5">
                                @foreach($visiblePackages as $package)
                                    <li wire:key="pkg-{{ $plan->id }}-{{ $package->id }}">
                                        <a href="{{ route('shop.package', $package->slug) }}" wire:navigate
                                           class="group/row flex items-center justify-between gap-2 rounded-xl bg-zinc-50 px-3 py-2.5 ring-1 ring-zinc-200/80 transition-colors hover:bg-brand-50 hover:ring-brand-300 dark:bg-zinc-800/60 dark:ring-zinc-700 dark:hover:bg-brand-500/10 dark:hover:ring-brand-400/30">
                                            <span class="flex min-w-0 items-center gap-2">
                                                @if($package->latestVersion)
                                                    <span class="shrink-0 font-mono text-[10px] font-bold text-zinc-400" dir="ltr">v{{ $package->latestVersion->version }}</span>
                                                @endif
                                                <span class="truncate text-xs font-medium text-zinc-700 transition-colors group-hover/row:text-brand-600 dark:text-zinc-300 dark:group-hover/row:text-brand-400">
                                                    {{ $package->name }}
                                                </span>
                                            </span>

                                            {{-- مدت دسترسی رایگان این پکیج در مدت انتخابی (pivot؛ ۰ = نامحدود) --}}
                                            @php($freeMonths = (int) ($package->pivot->free_months ?? 1))
                                            @if($freeMonths === 0)
                                                <x-badge variant="info" icon="clock">نامحدود</x-badge>
                                            @else
                                                <x-badge variant="neutral" icon="clock">{{ \App\Models\SubscriptionPlan::freeMonthsLabel($freeMonths) }}</x-badge>
                                            @endif
                                        </a>
                                    </li>
                                @endforeach

                                @if(!$expanded && $cardPackages->count() > 4)
                                    <li wire:key="more-{{ $plan->id }}">
                                        <button type="button" wire:click="toggleExpand({{ $plan->id }})"
                                                class="flex w-full items-center justify-center gap-1.5 rounded-xl border border-dashed border-zinc-300 px-3 py-2.5 text-xs font-bold text-zinc-500 transition-colors hover:border-brand-400 hover:text-brand-600 dark:border-zinc-700 dark:text-zinc-400 dark:hover:border-brand-500/50 dark:hover:text-brand-400">
                                            <x-icon name="chevron-down" class="size-3.5" />
                                            +{{ fa_num($cardPackages->count() - 4) }} پکیج دیگر — نمایش همه
                                        </button>
                                    </li>
                                @elseif($expanded && $cardPackages->count() > 4)
                                    <li wire:key="less-{{ $plan->id }}">
                                        <button type="button" wire:click="toggleExpand({{ $plan->id }})"
                                                class="flex w-full items-center justify-center gap-1.5 rounded-xl border border-dashed border-zinc-300 px-3 py-2.5 text-xs font-bold text-zinc-500 transition-colors hover:border-brand-400 hover:text-brand-600 dark:border-zinc-700 dark:text-zinc-400 dark:hover:border-brand-500/50 dark:hover:text-brand-400">
                                            <x-icon name="chevron-up" class="size-3.5" />
                                            نمایش کمتر
                                        </button>
                                    </li>
                                @endif
                            </ul>
                        </div>

                        {{-- ---- CTA ---- --}}
                        <div class="mt-auto border-t border-zinc-100 p-5 dark:border-zinc-800 sm:px-6">
                            <x-btn wire:click="openCheckout({{ $plan->id }})" icon="{{ $plan->final_price <= 0 ? 'gift' : 'crown' }}" class="w-full">
                                انتخاب این طرح
                            </x-btn>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @else
        <section class="mx-auto w-full max-w-7xl px-4 pb-12 sm:px-6">
            <div class="card">
                <x-empty icon="crown" title="طرح اشتراکی فعالی موجود نیست" description="در حال حاضر طرح اشتراک فعالی برای فروشگاه ثبت نشده است؛ بعداً مراجعه کنید." />
            </div>
        </section>
    @endif

    {{-- ============ Footer note ============ --}}
    <section class="mx-auto w-full max-w-7xl px-4 pb-12 sm:px-6">
        <div class="card flex flex-col items-start gap-3 border-amber-200/60 bg-amber-50/60 p-5 ring-amber-500/10 dark:bg-amber-500/5 sm:flex-row dark:ring-amber-400/15">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400">
                <x-icon name="shield-check" class="size-5" />
            </span>
            <p class="text-sm leading-7 text-amber-800 dark:text-amber-200">
                پس از پرداخت، درخواست شما ثبت می‌شود و پس از تأیید مدیر، لایسنس پکیج‌های طرح صادر و فعال می‌شود.
            </p>
        </div>
    </section>

    {{-- ============ Checkout modal ============ --}}
    @php($checkoutPlan = $this->checkoutPlanId !== null ? $this->plans->firstWhere('id', $this->checkoutPlanId) : null)
    <x-modal wire:model="showCheckout" title="خرید طرح اشتراک" :subtitle="$checkoutPlan?->name">
        @if($checkoutPlan)
            @php($gatewayOptions = $this->gateways->mapWithKeys(fn ($g) => [$g->key => $g->name . ' (' . $g->key . ')'])->all())
            @php($checkoutDuration = $this->selectedDurationFor($checkoutPlan))
            @php($checkoutPackages = $checkoutDuration?->resolvedPackages($checkoutPlan->packages) ?? $checkoutPlan->packages)
            @php($checkoutFinal = $checkoutDuration?->final_price ?? $checkoutPlan->final_price)
            @php($checkoutPrice = $checkoutDuration?->price ?? $checkoutPlan->price)
            @php($checkoutHasDiscount = $checkoutDuration ? $checkoutDuration->has_discount : $checkoutPlan->has_discount)
            @php($checkoutDurationLabel = $checkoutDuration?->duration_label ?? $checkoutPlan->duration_label)
            @php($isFreePlan = $checkoutFinal <= 0)
            @php($buyDisabled = !$isFreePlan && $this->gateways->isEmpty())
            <div class="space-y-5">
                {{-- خلاصه طرح --}}
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/80 dark:bg-zinc-800/60 dark:ring-zinc-700">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-black text-zinc-900 dark:text-zinc-50">{{ $checkoutPlan->name }}</p>
                            <x-badge variant="neutral" icon="clock">{{ $checkoutDurationLabel }}</x-badge>
                            @if($checkoutPlan->is_one_time)
                                <x-badge variant="warning" icon="ticket">یک‌بار مصرف</x-badge>
                            @endif
                        </div>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            دسترسی به {{ fa_num($checkoutPackages->count()) }} پکیج با لایسنس اختصاصی
                        </p>
                    </div>
                    <div class="text-end">
                        @if($isFreePlan)
                            <p class="text-lg font-black text-emerald-600">رایگان</p>
                        @else
                            @if($checkoutHasDiscount)
                                <p class="text-xs tabular-nums text-zinc-400 line-through">{{ money($checkoutPrice, false) }}</p>
                            @endif
                            <p class="text-lg font-black tabular-nums text-zinc-900 dark:text-zinc-50">{{ money($checkoutFinal) }}</p>
                        @endif
                    </div>
                </div>

                {{-- انتخاب مدت اعتبار (طرح‌های چندمدتی) --}}
                @if($checkoutPlan->hasDurations())
                    <div role="group" aria-label="انتخاب مدت اعتبار">
                        <p class="mb-2 flex items-center gap-1.5 text-xs font-bold text-zinc-700 dark:text-zinc-200">
                            <x-icon name="clock" class="size-4 text-brand-500" />
                            مدت اعتبار — یکی را انتخاب کنید
                        </p>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach($checkoutPlan->durationOptions() as $d)
                                @php($isSelected = ($checkoutDuration?->duration_months ?? -1) === (int) $d->duration_months)
                                <button type="button" wire:click="selectDuration({{ $checkoutPlan->id }}, {{ $d->duration_months }})"
                                        wire:key="cdur-{{ $d->duration_months }}"
                                        aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                                        @class([
                                            'flex cursor-pointer items-center justify-between gap-2 rounded-xl px-3.5 py-2.5 text-start transition-all',
                                            'bg-brand-600/5 ring-2 ring-brand-500' => $isSelected,
                                            'bg-zinc-50 ring-1 ring-zinc-200 hover:ring-brand-300 dark:bg-zinc-800/60 dark:ring-zinc-700 dark:hover:ring-brand-400/40' => !$isSelected,
                                        ])>
                                    <span>
                                        <span @class([
                                            'block text-sm font-black',
                                            'text-brand-700 dark:text-brand-400' => $isSelected,
                                            'text-zinc-800 dark:text-zinc-100' => !$isSelected,
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
                                            <span @class(['block text-sm font-black tabular-nums', 'text-brand-700 dark:text-brand-400' => $isSelected, 'text-zinc-800 dark:text-zinc-100' => !$isSelected])>{{ money($d->final_price, false) }}</span>
                                        @endif
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- درگاه پرداخت (فقط طرح‌های پولی) --}}
                @if(!$isFreePlan)
                    @if($this->gateways->isNotEmpty())
                        <x-field label="درگاه پرداخت" for="gateway" required>
                            <x-select id="gateway" wire:model.live="gateway" :options="$gatewayOptions" />
                        </x-field>
                    @else
                        <div class="flex items-start gap-3 rounded-xl bg-amber-50 p-4 text-amber-700 ring-1 ring-amber-600/10 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-400/20">
                            <x-icon name="alert-triangle" class="mt-0.5 size-5 shrink-0" />
                            <p class="text-xs leading-5">درگاه پرداخت فعالی تنظیم نشده است. لطفاً بعداً مراجعه کنید یا با پشتیبانی تماس بگیرید.</p>
                        </div>
                    @endif
                @endif

                {{-- کد آپدیت --}}
                <x-field label="کد آپدیت مشتری" for="updateCode" required hint="کد آپدیت در پنل مشتری شما موجود است.">
                    <x-input id="updateCode" wire:model="updateCode" icon="key" placeholder="کد آپدیت مشتری…" dir="ltr" class="text-start font-mono" />
                </x-field>

                {{-- ثبت درخواست --}}
                <x-btn wire:click="buy({{ $checkoutPlan->id }})" :loading="true" :disabled="$buyDisabled"
                       icon="{{ $isFreePlan ? 'gift' : 'wallet' }}" class="w-full">
                    {{ $isFreePlan
                        ? 'ثبت درخواست رایگان'
                        : 'پرداخت و ثبت درخواست — ' . money($checkoutFinal) . ' تومان' }}
                </x-btn>

                <p class="flex items-center justify-center gap-1.5 text-center text-xs leading-5 text-zinc-400">
                    <x-icon name="{{ $isFreePlan ? 'check-circle' : 'shield-check' }}" class="size-4 shrink-0 {{ $isFreePlan ? 'text-emerald-500' : 'text-emerald-500' }}" />
                    {{ $isFreePlan
                        ? 'این گزینه رایگان است؛ درخواست شما مستقیماً در انتظار تأیید مدیر قرار می‌گیرد.'
                        : 'پرداخت از طریق درگاه‌های بانکی امن انجام می‌شود؛ فعال‌سازی پس از تأیید مدیر.' }}
                </p>
            </div>
        @endif
    </x-modal>
</div>
