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
            <li class="font-bold text-zinc-700 dark:text-zinc-200">{{ $package->name }}</li>
        </ol>
    </nav>

    {{-- ============ Hero ============ --}}
    <section class="mx-auto w-full max-w-7xl px-4 sm:px-6">
        <div class="card overflow-hidden">
            <div class="flex flex-col gap-5 p-5 sm:p-6 sm:flex-row">
                {{-- thumbnail --}}
                <div class="relative aspect-video w-full overflow-hidden rounded-2xl sm:w-64 sm:shrink-0">
                    @if($package->thumbnail_url && $package->thumbnail_url !== asset('images/package-default.png'))
                        <img src="{{ $package->thumbnail_url }}" alt="{{ $package->name }}" class="size-full object-cover" />
                    @else
                        <div class="shop-thumb flex size-full items-center justify-center">
                            <x-icon name="package" class="size-14 text-white/80" />
                        </div>
                    @endif
                </div>

                {{-- summary --}}
                <div class="flex min-w-0 flex-1 flex-col gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                        @if($package->latestVersion)
                            <x-badge variant="primary" class="font-mono" dir="ltr">v{{ $package->latestVersion->version }}</x-badge>
                        @endif
                        @if($package->categoryModel)
                            <x-badge variant="neutral" icon="tag">{{ $package->categoryModel->name }}</x-badge>
                        @endif
                        @if($package->is_free)
                            <x-badge variant="success" icon="gift">رایگان</x-badge>
                        @endif
                    </div>

                    <h1 class="text-xl font-black text-zinc-900 sm:text-2xl dark:text-zinc-50">{{ $package->name }}</h1>

                    @if($package->short_description)
                        <p class="whitespace-pre-line text-sm leading-7 text-zinc-500 dark:text-zinc-400">{{ $package->short_description }}</p>
                    @endif

                    <div class="mt-auto flex flex-wrap items-end justify-between gap-3 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                        @php($prices = $this->plans->filter(fn ($p) => $p->final_price > 0)->map->final_price)
                        @if($package->is_free || ($prices->isNotEmpty() ? $prices->min() : $package->default_price) <= 0)
                            <div>
                                <p class="text-xs text-zinc-500">هزینه دریافت</p>
                                <p class="mt-1 text-lg font-black text-emerald-600">رایگان</p>
                            </div>
                        @else
                            @php($min = $prices->isNotEmpty() ? $prices->min() : $package->default_price)
                            @php($max = $prices->isNotEmpty() && $prices->count() > 1 ? $prices->max() : null)
                            <div>
                                <p class="text-xs text-zinc-500">{{ $max ? 'قیمت از' : 'قیمت' }}</p>
                                <p class="mt-1 text-lg font-black tabular-nums text-zinc-900 dark:text-zinc-50">
                                    {{ money($min) }}
                                    @if($max)
                                        <span class="text-sm font-medium text-zinc-400">تا {{ money($max, false) }}</span>
                                    @endif
                                </p>
                            </div>
                        @endif

                        <div class="flex items-center gap-4 text-xs text-zinc-400">
                            @if($this->versions->isNotEmpty())
                                <span class="inline-flex items-center gap-1.5">
                                    <x-icon name="layers" class="size-4" />
                                    {{ fa_num($this->versions->count()) }} نسخه اخیر
                                </span>
                            @endif
                            <span class="inline-flex items-center gap-1.5">
                                <x-icon name="download" class="size-4" />
                                {{ fa_num($package->downloads_count) }} دانلود
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- gallery --}}
            @if($this->images->isNotEmpty())
                <div class="border-t border-zinc-100 p-5 dark:border-zinc-800 sm:p-6">
                    <p class="mb-3 text-xs font-bold text-zinc-500 dark:text-zinc-400">تصاویر پکیج</p>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        @foreach($this->images as $image)
                            <img src="{{ $image->url }}" alt="{{ $image->alt ?: $package->name }}" wire:key="img-{{ $image->id }}"
                                 class="aspect-video w-full rounded-xl object-cover ring-1 ring-zinc-200 dark:ring-zinc-700" />
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </section>

    {{-- ============ Main grid ============ --}}
    <section class="mx-auto grid w-full max-w-7xl grid-cols-1 items-start gap-6 px-4 pb-12 sm:px-6 lg:grid-cols-3">

        {{-- LEFT --}}
        <div class="space-y-6 lg:col-span-2">

            {{-- description --}}
            <x-card title="توضیحات پکیج" subtitle="معرفی کامل امکانات و جزئیات">
                @if($package->description)
                    <div class="rich-content text-sm leading-7 text-zinc-600 dark:text-zinc-300">
                        {!! $package->description !!}
                    </div>
                @else
                    <p class="text-sm text-zinc-500">توضیحی برای این پکیج ثبت نشده است.</p>
                @endif
            </x-card>

            {{-- versions timeline --}}
            <x-card title="نسخه‌ها" subtitle="آخرین نسخه‌های منتشرشده">
                @if($this->versions->isNotEmpty())
                    <ol class="space-y-5">
                        @foreach($this->versions as $version)
                            <li class="relative flex gap-4" wire:key="ver-{{ $version->id }}">
                                {{-- rail --}}
                                <div class="flex flex-col items-center" aria-hidden="true">
                                    <span class="mt-1 flex size-3 shrink-0 rounded-full bg-brand-500 ring-4 ring-brand-500/15"></span>
                                    @if(!$loop->last)
                                        <span class="mt-1 w-0.5 flex-1 bg-zinc-200 dark:bg-zinc-800"></span>
                                    @endif
                                </div>

                                <div class="min-w-0 flex-1 pb-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <x-badge variant="primary" class="font-mono" dir="ltr">v{{ $version->version }}</x-badge>
                                        @if($version->release_date)
                                            <span class="text-xs tabular-nums text-zinc-400">{{ verta_date($version->release_date, 'Y/m/d') }}</span>
                                        @endif
                                        @if($version->is_mandatory)
                                            <x-badge variant="warning">اجباری</x-badge>
                                        @endif
                                    </div>
                                    @if($version->changelog)
                                        <p class="whitespace-pre-line mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $version->changelog }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @else
                    <x-empty icon="git-branch" title="نسخه‌ای منتشر نشده" />
                @endif
            </x-card>

            {{-- plans (selectable radio cards) --}}
            <x-card title="طرح‌های قیمت‌گذاری" subtitle="طرح موردنظر را انتخاب کنید">
                @if($this->plans->isNotEmpty())
                    <div class="grid grid-cols-1 gap-3">
                        @foreach($this->plans as $plan)
                            @php($selected = (string) $plan->id === $this->planId)
                            <button type="button" wire:click="$set('planId', '{{ $plan->id }}')"
                                    class="group relative flex flex-wrap items-center justify-between gap-3 rounded-2xl p-4 text-start ring-1 transition-all
                                           {{ $selected ? 'bg-brand-50/70 ring-2 ring-brand-500 dark:bg-brand-500/10' : 'bg-zinc-50 ring-zinc-200/80 hover:ring-brand-300 dark:bg-zinc-800/60 dark:ring-zinc-700 dark:hover:ring-brand-500/40' }}">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="flex size-5 shrink-0 items-center justify-center rounded-full ring-1 {{ $selected ? 'bg-brand-600 ring-brand-600' : 'bg-white ring-zinc-300 dark:bg-zinc-800 dark:ring-zinc-600' }}">
                                            @if($selected)
                                                <x-icon name="check" class="size-3 text-white" />
                                            @endif
                                        </span>
                                        <p class="text-sm font-bold text-zinc-800 dark:text-zinc-100">{{ $plan->name }}</p>
                                        <x-badge variant="info" icon="clock">{{ $plan->duration_label }}</x-badge>
                                        @if($plan->is_one_time)
                                            <x-badge variant="warning" icon="ticket">یک‌بار مصرف</x-badge>
                                        @endif
                                    </div>
                                    @if($plan->description)
                                        <p class="mt-1.5 text-xs leading-5 text-zinc-500 dark:text-zinc-400">{{ $plan->description }}</p>
                                    @endif
                                </div>
                                <div class="text-end">
                                    @if($plan->final_price <= 0)
                                        <p class="text-sm font-black text-emerald-600">رایگان</p>
                                    @else
                                        @if($plan->has_discount)
                                            <p class="text-xs tabular-nums text-zinc-400 line-through">{{ money($plan->price, false) }}</p>
                                        @endif
                                        <p class="text-sm font-black tabular-nums text-zinc-900 dark:text-zinc-50">{{ money($plan->final_price) }}</p>
                                    @endif
                                </div>
                            </button>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-zinc-500">
                        @if($package->is_free)
                            این پکیج رایگان است و طرح قیمت‌گذاری ندارد.
                        @else
                            طرح قیمت‌گذاری فعالی برای این پکیج ثبت نشده است.
                        @endif
                    </p>
                @endif
            </x-card>

            {{-- related subscription plans --}}
            @if($this->subscriptionPlansWithThisPackage->count())
                <div class="relative overflow-hidden rounded-3xl bg-gradient-to-bl from-brand-50 via-white to-teal-50 ring-1 ring-brand-100 dark:from-zinc-900 dark:via-zinc-900 dark:to-zinc-800 dark:ring-zinc-800">
                    <div class="shop-hero-glow pointer-events-none absolute -top-20 -end-20 size-64 rounded-full opacity-25 blur-3xl" aria-hidden="true"></div>
                    <div class="relative p-5 sm:p-6">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="flex items-center gap-2 text-sm font-black text-zinc-900 dark:text-zinc-50">
                                <x-icon name="crown" class="size-5 text-amber-500" />
                                این پکیج با اشتراک هم رایگان می‌شود
                            </h3>
                            <a href="{{ route('shop.subscriptions') }}" wire:navigate class="shrink-0 text-xs font-bold text-brand-600 hover:text-brand-500 dark:text-brand-400">
                                همه طرح‌ها ←
                            </a>
                        </div>
                        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                            @foreach($this->subscriptionPlansWithThisPackage as $plan)
                                @php($pivot = $plan->packages->first()?->pivot)
                                <a href="{{ route('shop.subscriptions') }}" wire:navigate
                                   class="group flex flex-col gap-2 rounded-2xl bg-white/80 p-4 ring-1 ring-zinc-200/70 backdrop-blur transition-all hover:-translate-y-0.5 hover:shadow-card dark:bg-zinc-800/70 dark:ring-zinc-700/50">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="flex items-center gap-1.5 text-xs font-black text-zinc-900 dark:text-zinc-50">
                                            <x-icon name="crown" class="size-3.5 text-amber-500" />
                                            {{ $plan->name }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $plan->duration_label }} اعتبار</p>
                                    @if($pivot)
                                        <span class="inline-flex w-fit items-center gap-1 rounded-lg bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400">
                                            <x-icon name="gift" class="size-3" />
                                            {{ \App\Models\SubscriptionPlan::freeMonthsLabel((int) $pivot->free_months) }}
                                        </span>
                                    @endif
                                    <span class="mt-auto pt-1 text-sm font-black tabular-nums text-zinc-900 dark:text-zinc-50">
                                        {{ $plan->final_price > 0 ? money($plan->final_price, false) : 'رایگان' }}
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- RIGHT: checkout --}}
        <aside class="lg:sticky top-24">
            @if($this->isCoveredBySubscription)
                {{-- جعبه اطلاع‌رسانی: پکیج با طرح اشتراک فعال رایگان است --}}
                <div class="mb-4 flex items-start gap-3 rounded-2xl bg-emerald-50 p-4 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:ring-emerald-400/25">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400">
                        <x-icon name="crown" class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-black text-emerald-800 dark:text-emerald-300">رایگان با اشتراک شما</p>
                        <p class="mt-1 text-xs leading-6 text-emerald-700/90 dark:text-emerald-300/80">
                            این پکیج در طرح اشتراک فعال شما ({{ $this->myActiveSubscription?->plan?->name }}) قرار دارد و دریافت آن برای شما رایگان است.
                            کافیست کد آپدیت خود را وارد و دکمه دریافت را بزنید.
                        </p>
                    </div>
                </div>
            @endif
            <div class="card space-y-5 p-5 sm:p-6">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-sm font-black text-zinc-900 dark:text-zinc-50">خرید پکیج</h3>
                    <span class="flex size-9 items-center justify-center rounded-xl bg-brand-500/10 text-brand-600 dark:text-brand-400">
                        <x-icon name="credit-card" class="size-5" />
                    </span>
                </div>

                {{-- selected plan summary --}}
                @php($selectedPlan = $this->planId !== '' ? $this->plans->firstWhere('id', (int) $this->planId) : null)
                @if($selectedPlan)
                    <div class="flex items-center justify-between rounded-xl bg-zinc-50 px-4 py-3 ring-1 ring-zinc-200/80 dark:bg-zinc-800/60 dark:ring-zinc-700">
                        <span class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                            <x-icon name="tag" class="size-4" />
                            طرح انتخاب‌شده
                        </span>
                        <span class="text-sm font-bold text-zinc-800 dark:text-zinc-100">{{ $selectedPlan->name }}</span>
                    </div>
                @endif
                <div class="flex items-center justify-between rounded-xl bg-zinc-50 px-4 py-3 ring-1 ring-zinc-200/80 dark:bg-zinc-800/60 dark:ring-zinc-700">
                    <span class="text-xs text-zinc-500 dark:text-zinc-400">مبلغ قابل پرداخت</span>
                    <span class="text-sm font-black tabular-nums text-zinc-900 dark:text-zinc-50">
                        @if($this->isCoveredBySubscription)
                            <span class="text-emerald-600 dark:text-emerald-400">رایگان با اشتراک شما</span>
                        @elseif($this->isFreeRoute)
                            <span class="text-emerald-600">رایگان</span>
                        @elseif($selectedPlan)
                            {{ money($selectedPlan->final_price) }}
                        @else
                            —
                        @endif
                    </span>
                </div>

                {{-- plan select (mobile-friendly fallback for changing plan from checkout) --}}
                @if($this->plans->isNotEmpty() && !$package->is_free && !$this->isCoveredBySubscription && $this->plans->count() > 1)
                    @php($planOptions = $this->plans->mapWithKeys(fn ($p) => [$p->id => $p->name . ' · ' . $p->duration_label . ' · ' . ($p->final_price <= 0 ? 'رایگان' : money($p->final_price, false))])->all())
                    <x-field label="تغییر طرح" for="planId">
                        <x-select id="planId" wire:model.live="planId" :options="$planOptions" />
                    </x-field>
                @endif

                {{-- gateway --}}
                @if(!$this->isFreeRoute && !$this->isCoveredBySubscription)
                    @if($this->gateways->isNotEmpty())
                        @include('livewire.shop.partials.gateway-picker', ['gateways' => $this->gateways])
                    @else
                        <div class="flex items-start gap-3 rounded-xl bg-amber-50 p-4 text-amber-700 ring-1 ring-amber-600/10 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-400/20">
                            <x-icon name="alert-triangle" class="mt-0.5 size-5 shrink-0" />
                            <p class="text-xs leading-5">درگاه پرداخت فعالی تنظیم نشده است. لطفاً بعداً مراجعه کنید یا با پشتیبانی تماس بگیرید.</p>
                        </div>
                    @endif
                @endif

                {{-- update code --}}
                @php($loggedInCustomer = auth('customer')->user())
                @if($loggedInCustomer)
                    <div class="flex items-start gap-3 rounded-xl bg-emerald-50 p-3.5 ring-1 ring-emerald-600/10 dark:bg-emerald-500/10 dark:ring-emerald-400/20">
                        <x-icon name="badge-check" class="mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                        <div class="min-w-0 text-xs leading-5">
                            <p class="font-bold text-emerald-700 dark:text-emerald-300">
                                خرید با حساب شما: {{ $loggedInCustomer->name ?: 'مشتری' }}
                            </p>
                            <p class="mt-0.5 text-emerald-700/80 dark:text-emerald-400/80">
                                کد آپدیت شما به‌صورت خودکار وارد شده است — <span class="font-mono" dir="ltr">{{ $loggedInCustomer->update_code }}</span>
                            </p>
                        </div>
                    </div>
                @else
                    <x-field label="کد آپدیت مشتری" for="updateCode" required hint="کد آپدیت در پنل مشتری شما موجود است. برای راحتی، ابتدا وارد شوید.">
                        <x-input id="updateCode" wire:model="updateCode" icon="key" placeholder="کد آپدیت مشتری…" dir="ltr" class="text-start font-mono" />
                    </x-field>
                @endif

                {{-- buy button --}}
                @php($buyDisabled = !$this->isFreeRoute && !$this->isCoveredBySubscription && $this->gateways->isEmpty())
                @php($covered = $this->isCoveredBySubscription)
                <x-btn wire:click="buy" :loading="true" icon="{{ $covered ? 'crown' : ($this->isFreeRoute ? 'download' : 'wallet') }}" class="w-full" :disabled="$buyDisabled">
                    {{ $covered ? 'دریافت رایگان با اشتراک' : ($this->isFreeRoute ? 'دریافت رایگان' : 'خرید و پرداخت') }}
                </x-btn>

                <p class="flex items-center justify-center gap-1.5 text-xs leading-4 text-zinc-400">
                    <x-icon name="shield-check" class="size-4 shrink-0 text-emerald-500" />
                    {{ $covered ? 'این دریافت از طریق اشتراک فعال شما و بدون پرداخت انجام می‌شود.' : 'پرداخت از طریق درگاه‌های بانکی امن انجام می‌شود.' }}
                </p>
            </div>
        </aside>
    </section>

    {{-- ============ Comments ============ --}}
    <section class="mx-auto w-full max-w-7xl px-4 sm:px-6">
        <livewire:shop.comment-section :model="$package" label="پکیج" :key="'cs-package-'.$package->id" />
    </section>
</div>
