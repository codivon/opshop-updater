<div class="space-y-10">

    {{-- ============ Hero ============ --}}
    <section class="shop-hero relative overflow-hidden">
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div class="shop-hero-glow absolute -top-32 -start-32 size-96 rounded-full blur-3xl"></div>
            <div class="shop-hero-glow absolute -bottom-40 -end-24 size-96 rounded-full opacity-60 blur-3xl"></div>
        </div>
        <div class="pointer-events-none absolute inset-0 opacity-[0.04]" aria-hidden="true"
             style="background-image: linear-gradient(#fff 1px, transparent 1px), linear-gradient(90deg, #fff 1px, transparent 1px); background-size: 44px 44px;"></div>

        <div class="relative mx-auto w-full max-w-7xl px-4 py-10 sm:px-6 sm:py-14">
            <div class="max-w-2xl animate-slide-up">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-teal-100 ring-1 ring-white/20">
                    <x-icon name="sparkles" class="size-3.5" />
                    مرکز توزیع پروژه، پکیج و آپدیت
                </span>
                <h1 class="shop-hero-title mt-4 text-3xl font-black leading-relaxed sm:text-4xl">
                    فروشگاه آپدیت‌سنتر
                </h1>
                <p class="mt-3 max-w-xl text-sm leading-7 text-teal-50/80 sm:text-base">
                    اشتراک ویژه بگیرید، پکیج‌ها را با کد آپدیت فعال کنید یا پروژه‌های آماده را مستقیماً خرید و دانلود کنید.
                </p>

                <div class="mt-5 flex flex-wrap items-center gap-2.5">
                    <a href="#subscription" class="inline-flex items-center gap-1.5 rounded-xl bg-white px-4 py-2.5 text-sm font-black text-zinc-900 shadow-sm transition-all hover:shadow-md">
                        <x-icon name="crown" class="size-4 text-amber-500" />
                        اشتراک‌ها
                    </a>
                    <a href="{{ route('shop.packages') }}" wire:navigate class="inline-flex items-center gap-1.5 rounded-xl bg-white/10 px-4 py-2.5 text-sm font-bold text-white ring-1 ring-white/25 backdrop-blur transition-colors hover:bg-white/20">
                        <x-icon name="package" class="size-4" />
                        پکیج‌ها
                    </a>
                    <a href="{{ route('shop.products') }}" wire:navigate class="inline-flex items-center gap-1.5 rounded-xl bg-white/10 px-4 py-2.5 text-sm font-bold text-white ring-1 ring-white/25 backdrop-blur transition-colors hover:bg-white/20">
                        <x-icon name="rocket" class="size-4" />
                        پروژه‌های آماده
                    </a>
                </div>
            </div>
        </div>
    </section>

    <div class="mx-auto w-full max-w-7xl space-y-10 px-4 sm:px-6">

        {{-- ================================================================ --}}
        {{-- ۱) اشتراک — وضعیت من + طرح‌های ویژه                                --}}
        {{-- ================================================================ --}}
        <section id="subscription" class="scroll-mt-20 space-y-6">

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

        {{-- ================================================================ --}}
        {{-- ۲) آمار                                                            --}}
        {{-- ================================================================ --}}
        <section class="flex flex-wrap items-center justify-center gap-4 sm:justify-start">
            <x-stat label="پروژه قابل‌فروش" :value="fa_num($this->stats['products'])" icon="rocket" variant="primary" />
            <x-stat label="پکیج فعال" :value="fa_num($this->stats['packages'])" icon="package" variant="info" />
            <x-stat label="نسخه فعال" :value="fa_num($this->stats['versions'])" icon="layers" variant="neutral" />
            <x-stat label="طرح اشتراک فعال" :value="fa_num($this->stats['plans'])" icon="crown" variant="warning" />
            <x-stat label="خرید موفق" :value="fa_num($this->stats['customers'])" icon="check-circle" variant="neutral" />
        </section>

        {{-- ================================================================ --}}
        {{-- ۳) پکیج‌ها (پیش‌نمایش)                                             --}}
        {{-- ================================================================ --}}
        <section>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="flex items-center gap-2 text-lg font-black text-zinc-900 dark:text-zinc-50">
                        <x-icon name="package" class="size-5 text-brand-600" />
                        پکیج‌های فروشگاه
                    </h2>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ fa_num($this->stats['packages']) }} پکیج فعال — خرید و فعال‌سازی با کد آپدیت.
                    </p>
                </div>
                <a href="{{ route('shop.packages') }}" wire:navigate>
                    <x-btn variant="secondary" size="sm" icon="arrow-left">
                        مشاهده همه + فیلترها
                    </x-btn>
                </a>
            </div>

            @if($this->packages->count())
                <div class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($this->packages as $package)
                        @include('livewire.shop.partials.package-card', ['package' => $package, 'isCovered' => in_array($package->id, $this->coveredPackageIds)])
                    @endforeach
                </div>
            @else
                <div class="card mt-5">
                    <x-empty icon="package" title="هنوز پکیجی منتشر نشده" description="به‌زودی پکیج‌های جدید اضافه می‌شوند." />
                </div>
            @endif
        </section>

        {{-- ================================================================ --}}
        {{-- ۴) پروژه‌های آماده (پیش‌نمایش)                                     --}}
        {{-- ================================================================ --}}
        <section>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="flex items-center gap-2 text-lg font-black text-zinc-900 dark:text-zinc-50">
                        <x-icon name="rocket" class="size-5 text-brand-600" />
                        پروژه‌های آماده
                    </h2>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ fa_num($this->stats['products']) }} پروژه — خرید بدون ثبت‌نام؛ دانلود فوری پس از پرداخت.
                    </p>
                </div>
                <a href="{{ route('shop.products') }}" wire:navigate>
                    <x-btn variant="secondary" size="sm" icon="arrow-left">
                        مشاهده همه + فیلترها
                    </x-btn>
                </a>
            </div>

            @if($this->products->count())
                <div class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($this->products as $product)
                        @include('livewire.shop.partials.product-card', ['product' => $product])
                    @endforeach
                </div>
            @else
                <div class="card mt-5">
                    <x-empty icon="rocket" title="هنوز پروژه‌ای منتشر نشده" description="به‌زودی پروژه‌های جدید اضافه می‌شوند." />
                </div>
            @endif
        </section>

        {{-- ================================================================ --}}
        {{-- ۵) پیگیری خرید مهمان                                               --}}
        {{-- ================================================================ --}}
        <section>
            <livewire:shop.purchase-track />
        </section>

    </div>
</div>
