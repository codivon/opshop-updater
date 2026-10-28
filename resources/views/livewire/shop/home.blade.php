<div class="space-y-10">

    {{-- ============ Hero (معرفی — محتوا از پنل «صفحهٔ اصلی فروشگاه») ============ --}}
    @if($ho['home_hero_enabled'])
        <section class="shop-hero relative overflow-hidden">
            <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                <div class="shop-hero-glow absolute -top-32 -start-32 size-96 rounded-full blur-3xl"></div>
                <div class="shop-hero-glow absolute -bottom-40 -end-24 size-96 rounded-full opacity-60 blur-3xl"></div>
            </div>
            <div class="pointer-events-none absolute inset-0 opacity-[0.04]" aria-hidden="true"
                 style="background-image: linear-gradient(#fff 1px, transparent 1px), linear-gradient(90deg, #fff 1px, transparent 1px); background-size: 44px 44px;"></div>

            <div class="relative mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 sm:py-16">
                <div class="max-w-3xl animate-slide-up">
                    @if($hs['home_hero_badge'] !== '')
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-teal-100 ring-1 ring-white/20">
                            <x-icon name="sparkles" class="size-3.5" />
                            {{ $hs['home_hero_badge'] }}
                        </span>
                    @endif
                    <h1 class="shop-hero-title mt-4 text-3xl font-black leading-relaxed sm:text-4xl sm:leading-relaxed">
                        {{ $hs['home_hero_title'] }}
                    </h1>
                    @if($hs['home_hero_subtitle'] !== '')
                        <p class="mt-3 max-w-2xl text-sm leading-7 text-teal-50/80 sm:text-base">
                            {{ $hs['home_hero_subtitle'] }}
                        </p>
                    @endif

                    <div class="mt-6 flex flex-wrap items-center gap-2.5">
                        @if($hs['home_hero_cta_text'] !== '')
                            <a href="{{ $hs['home_hero_cta_link'] ?: '#shop-packages' }}" class="inline-flex items-center gap-1.5 rounded-xl bg-white px-4 py-2.5 text-sm font-black text-zinc-900 shadow-sm transition-all hover:shadow-md">
                                <x-icon name="package" class="size-4 text-brand-600" />
                                {{ $hs['home_hero_cta_text'] }}
                            </a>
                        @endif
                        @if($hs['home_hero_cta2_text'] !== '')
                            <a href="{{ $hs['home_hero_cta2_link'] ?: '#contact' }}" class="inline-flex items-center gap-1.5 rounded-xl bg-white/10 px-4 py-2.5 text-sm font-bold text-white ring-1 ring-white/25 backdrop-blur transition-colors hover:bg-white/20">
                                <x-icon name="message-circle" class="size-4" />
                                {{ $hs['home_hero_cta2_text'] }}
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    @endif

    <div class="mx-auto w-full max-w-7xl space-y-10 px-4 sm:px-6">

        {{-- ================================================================ --}}
        {{-- ۱) آمار اعتماد (از پنل قابل ویرایش)                                --}}
        {{-- ================================================================ --}}
        @if($ho['home_stats_enabled'] && count($trustStats))
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
        @endif

        {{-- ================================================================ --}}
        {{-- ۲) معرفی (درباره ما)                                               --}}
        {{-- ================================================================ --}}
        @if($ho['home_about_enabled'] && ($hs['home_about_title'] !== '' || $hs['home_about_body'] !== ''))
            <section id="about" class="scroll-mt-24">
                <div class="relative overflow-hidden rounded-3xl bg-white p-6 ring-1 ring-zinc-200/80 dark:bg-zinc-900 dark:ring-zinc-700/60 sm:p-8">
                    <div class="pointer-events-none absolute -top-24 -end-24 size-64 rounded-full bg-brand-400/10 blur-3xl" aria-hidden="true"></div>

                    <div class="relative grid gap-6 lg:grid-cols-3">
                        <div>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 text-xs font-bold text-brand-700 ring-1 ring-brand-200/70 dark:bg-brand-500/10 dark:text-brand-300 dark:ring-brand-400/20">
                                <x-icon name="sparkles" class="size-3.5" />
                                {{ $hs['home_hero_badge'] }}
                            </span>
                            <h2 class="mt-3 flex items-center gap-2 text-xl font-black text-zinc-900 dark:text-zinc-50">
                                <x-icon name="users" class="size-5.5 text-brand-600" />
                                {{ $hs['home_about_title'] }}
                            </h2>
                            <a href="#contact" class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-brand-50 px-3.5 py-2 text-xs font-black text-brand-700 transition-colors hover:bg-brand-100 dark:bg-brand-500/10 dark:text-brand-300 dark:hover:bg-brand-500/20">
                                <x-icon name="message-circle" class="size-3.5" />
                                صحبت درباره ایده شما
                            </a>
                        </div>
                        <div class="lg:col-span-2">
                            <p class="whitespace-pre-line text-sm leading-8 text-zinc-600 dark:text-zinc-300">
                                {{ $hs['home_about_body'] }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        {{-- ================================================================ --}}
        {{-- ۳) چرا ما (اعتمادسازی)                                             --}}
        {{-- ================================================================ --}}
        @if($ho['home_why_enabled'] && count($whyItems))
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
        @endif

        {{-- ================================================================ --}}
        {{-- ۴) نظرات مشتریان                                                   --}}
        {{-- ================================================================ --}}
        @if($ho['home_testimonials_enabled'] && count($testimonials))
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
        @endif

        {{-- ================================================================ --}}
        {{-- ۵) محصولات — دسته‌بندی‌ها + پکیج‌ها + پروژه‌ها + قالب‌ها            --}}
        {{-- ================================================================ --}}
        @php($showCategories = $ho['home_categories_enabled'] && ($ho['home_packages_enabled'] || $ho['home_products_enabled']))
        @php($packageNodes = $showCategories ? $this->packageCategoryTree : collect())
        @php($productNodes = $showCategories ? $this->productCategoryTree : collect())
        @if($showCategories && ($packageNodes->count() || $productNodes->count()))
            <section aria-label="دسته‌بندی فروشگاه">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="flex items-center gap-2 text-lg font-black text-zinc-900 dark:text-zinc-50">
                            <x-icon name="folder-tree" class="size-5 text-brand-600" />
                            دسته‌بندی محصولات
                        </h2>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            دسته موردنظر را انتخاب کنید تا فهرست همان بخش، فیلترشده باز شود.
                        </p>
                    </div>
                </div>

                <div class="mt-5 space-y-6">

                    {{-- ---------- پکیج‌ها (تمام‌عرض، ۴ ستون) ---------- --}}
                    @if($ho['home_packages_enabled'] && $packageNodes->count())
                        <div>
                            <h3 class="flex items-center gap-1.5 text-sm font-black text-zinc-700 dark:text-zinc-200">
                                <x-icon name="package" class="size-4 text-brand-600" />
                                پکیج‌ها
                            </h3>
                            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                @foreach($packageNodes as $node)
                                    @php($root = $node['model'])
                                    <div class="group rounded-2xl bg-white p-4 ring-1 ring-zinc-200/80 transition-all hover:-translate-y-0.5 hover:shadow-card-lg dark:bg-zinc-900 dark:ring-zinc-700/60"
                                         wire:key="pkg-cat-{{ $root->id }}">
                                        <div class="flex items-center gap-3">
                                            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 transition-colors group-hover:bg-brand-100 dark:bg-brand-500/10 dark:text-brand-400">
                                                <x-icon name="folder" class="size-5" />
                                            </span>
                                            <a href="{{ route('shop.packages', ['category' => $root->id]) }}" wire:navigate
                                               class="flex min-w-0 flex-1 items-center justify-between gap-2 rounded-lg focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:outline-none">
                                                <span class="truncate text-sm font-black text-zinc-900 dark:text-zinc-50">{{ $root->name }}</span>
                                                <span class="inline-flex shrink-0 items-center rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-bold tabular-nums text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                                                    {{ fa_num($root->shop_count ?? 0) }} مورد
                                                </span>
                                            </a>
                                        </div>
                                        @if($node['children']->count())
                                            <div class="mt-3 flex flex-wrap gap-1.5">
                                                @foreach($node['children'] as $child)
                                                    <a href="{{ route('shop.packages', ['category' => $child->id]) }}" wire:navigate
                                                       class="inline-flex items-center gap-1 rounded-lg bg-zinc-50 px-2 py-1 text-[11px] font-bold text-zinc-600 ring-1 ring-zinc-200/70 transition-colors hover:bg-brand-50 hover:text-brand-700 dark:bg-zinc-800/60 dark:text-zinc-300 dark:ring-zinc-700/60 dark:hover:bg-brand-500/10 dark:hover:text-brand-400">
                                                        {{ $child->name }}
                                                        <span class="tabular-nums opacity-60">{{ fa_num($child->shop_count ?? 0) }}</span>
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- ---------- پروژه‌های آماده (تمام‌عرض، ۴ ستون) ---------- --}}
                    @if($ho['home_products_enabled'] && $productNodes->count())
                        <div>
                            <h3 class="flex items-center gap-1.5 text-sm font-black text-zinc-700 dark:text-zinc-200">
                                <x-icon name="rocket" class="size-4 text-brand-600" />
                                پروژه‌های آماده
                            </h3>
                            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                @foreach($productNodes as $node)
                                    @php($root = $node['model'])
                                    <div class="group rounded-2xl bg-white p-4 ring-1 ring-zinc-200/80 transition-all hover:-translate-y-0.5 hover:shadow-card-lg dark:bg-zinc-900 dark:ring-zinc-700/60"
                                         wire:key="prd-cat-{{ $root->id }}">
                                        <div class="flex items-center gap-3">
                                            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-teal-50 text-teal-600 transition-colors group-hover:bg-teal-100 dark:bg-teal-500/10 dark:text-teal-400">
                                                <x-icon name="folder" class="size-5" />
                                            </span>
                                            <a href="{{ route('shop.products', ['category' => $root->id]) }}" wire:navigate
                                               class="flex min-w-0 flex-1 items-center justify-between gap-2 rounded-lg focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:outline-none">
                                                <span class="truncate text-sm font-black text-zinc-900 dark:text-zinc-50">{{ $root->name }}</span>
                                                <span class="inline-flex shrink-0 items-center rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-bold tabular-nums text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                                                    {{ fa_num($root->shop_count ?? 0) }} مورد
                                                </span>
                                            </a>
                                        </div>
                                        @if($node['children']->count())
                                            <div class="mt-3 flex flex-wrap gap-1.5">
                                                @foreach($node['children'] as $child)
                                                    <a href="{{ route('shop.products', ['category' => $child->id]) }}" wire:navigate
                                                       class="inline-flex items-center gap-1 rounded-lg bg-zinc-50 px-2 py-1 text-[11px] font-bold text-zinc-600 ring-1 ring-zinc-200/70 transition-colors hover:bg-teal-50 hover:text-teal-700 dark:bg-zinc-800/60 dark:text-zinc-300 dark:ring-zinc-700/60 dark:hover:bg-teal-500/10 dark:hover:text-teal-400">
                                                        {{ $child->name }}
                                                        <span class="tabular-nums opacity-60">{{ fa_num($child->shop_count ?? 0) }}</span>
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </section>
        @endif

        {{-- ================================================================ --}}
        {{-- ۵-الف) پکیج‌ها (پیش‌نمایش)                                          --}}
        {{-- ================================================================ --}}
        @if($ho['home_packages_enabled'])
            <section id="shop-packages" class="scroll-mt-24">
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
        @endif

        {{-- ================================================================ --}}
        {{-- ۵-ب) پروژه‌های آماده (پیش‌نمایش)                                    --}}
        {{-- ================================================================ --}}
        @if($ho['home_products_enabled'])
            <section id="shop-products" class="scroll-mt-24">
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
        @endif

        {{-- ================================================================ --}}
        {{-- ۵-ج) قالب‌های آماده (پیش‌نمایش)                                     --}}
        {{-- ================================================================ --}}
        @if($ho['home_templates_enabled'])
            <section id="shop-templates" class="scroll-mt-24">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="flex items-center gap-2 text-lg font-black text-zinc-900 dark:text-zinc-50">
                            <x-icon name="layout-template" class="size-5 text-brand-600" />
                            قالب‌های آماده
                        </h2>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ fa_num($this->stats['templates']) }} قالب — خرید بدون ثبت‌نام؛ دانلود فوری پس از پرداخت.
                        </p>
                    </div>
                    <a href="{{ route('shop.templates') }}" wire:navigate>
                        <x-btn variant="secondary" size="sm" icon="arrow-left">
                            مشاهده همه + فیلترها
                        </x-btn>
                    </a>
                </div>

                @if($this->templates->count())
                    <div class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($this->templates as $template)
                            @include('livewire.shop.partials.template-card', ['template' => $template])
                        @endforeach
                    </div>
                @else
                    <div class="card mt-5">
                        <x-empty icon="layout-template" title="هنوز قالبی منتشر نشده" description="به‌زودی قالب‌های جدید اضافه می‌شوند." />
                    </div>
                @endif
            </section>
        @endif

        {{-- ================================================================ --}}
        {{-- ۶) اشتراک — وضعیت من + طرح‌های ویژه                                --}}
        {{-- ================================================================ --}}
        @if($ho['home_subscription_enabled'])
            <section id="subscription" class="scroll-mt-24 space-y-6">

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
        @endif

        {{-- ================================================================ --}}
        {{-- ۷) پیگیری خرید مهمان                                               --}}
        {{-- ================================================================ --}}
        <section>
            <livewire:shop.purchase-track />
        </section>

        {{-- ================================================================ --}}
        {{-- ۸) تماس با ما (توضیح کامل درخواست‌ها + فرم)                        --}}
        {{-- ================================================================ --}}
        @if($ho['home_contact_enabled'])
            <section id="contact" class="scroll-mt-24 space-y-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="max-w-2xl">
                        <h2 class="flex items-center gap-2 text-lg font-black text-zinc-900 dark:text-zinc-50">
                            <x-icon name="message-circle" class="size-5 text-brand-600" />
                            {{ $hs['home_contact_title'] }}
                        </h2>
                        <p class="mt-1.5 text-sm leading-7 text-zinc-500 dark:text-zinc-400">
                            {{ $hs['home_contact_intro'] }}
                        </p>
                    </div>
                </div>

                {{-- ---------- سه مسیر درخواست ---------- --}}
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    @foreach([
                        ['icon' => 'layout-template', 'title' => 'قالب اختصاصی', 'desc' => $hs['contact_type_template_desc']],
                        ['icon' => 'rocket',          'title' => 'پروژه اختصاصی', 'desc' => $hs['contact_type_project_desc']],
                        ['icon' => 'lightbulb',       'title' => 'استارتاپ اختصاصی', 'desc' => $hs['contact_type_startup_desc']],
                    ] as $ti => $type)
                        @if(trim($type['desc']) !== '')
                            <div wire:key="ctype-{{ $ti }}"
                                 class="group relative overflow-hidden rounded-2xl bg-white p-5 ring-1 ring-zinc-200/80 transition-all hover:-translate-y-0.5 hover:shadow-card-lg dark:bg-zinc-900 dark:ring-zinc-700/60">
                                <span class="flex size-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600 transition-colors group-hover:bg-brand-100 dark:bg-brand-500/10 dark:text-brand-400">
                                    <x-icon name="{{ $type['icon'] }}" class="size-5.5" />
                                </span>
                                <h3 class="mt-3.5 text-sm font-black text-zinc-900 dark:text-zinc-50">{{ $type['title'] }}</h3>
                                <p class="mt-1.5 text-xs leading-6 text-zinc-500 dark:text-zinc-400">{{ $type['desc'] }}</p>
                                <span class="mt-3 inline-flex items-center gap-1 text-[11px] font-black text-brand-600 dark:text-brand-400">
                                    <x-icon name="send" class="size-3" />
                                    درخواست از فرم پایین
                                </span>
                            </div>
                        @endif
                    @endforeach
                </div>

                {{-- ---------- فرم + راه‌های تماس ---------- --}}
                <div class="grid gap-6 lg:grid-cols-5">
                    <div class="lg:col-span-3">
                        @if($ho['contact_form_enabled'])
                            <livewire:shop.contact-form />
                        @endif
                    </div>

                    <div class="space-y-4 lg:col-span-2">
                        {{-- راه‌های ارتباطی --}}
                        <div class="card p-5 sm:p-6">
                            <h3 class="text-base font-black text-zinc-900 dark:text-zinc-50">راه‌های ارتباطی</h3>
                            <div class="mt-4 space-y-2.5">
                                @if($hs['contact_phone'] !== '')
                                    <a href="tel:{{ $hs['contact_phone'] }}" class="flex items-center gap-3 rounded-xl bg-zinc-50 p-3.5 ring-1 ring-zinc-200/60 transition-colors hover:bg-brand-50 hover:ring-brand-200 dark:bg-zinc-800/40 dark:ring-zinc-700/60 dark:hover:bg-brand-500/10 dark:hover:ring-brand-400/20">
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white text-brand-600 shadow-xs dark:bg-zinc-900 dark:text-brand-400">
                                            <x-icon name="phone" class="size-4.5" />
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block text-[10px] text-zinc-400">تلفن همراه</span>
                                            <span class="block text-sm font-black tabular-nums text-zinc-800 dark:text-zinc-100" dir="ltr">{{ fa_num($hs['contact_phone']) }}</span>
                                        </span>
                                    </a>
                                @endif
                                @if($hs['contact_email'] !== '')
                                    <a href="mailto:{{ $hs['contact_email'] }}" class="flex items-center gap-3 rounded-xl bg-zinc-50 p-3.5 ring-1 ring-zinc-200/60 transition-colors hover:bg-brand-50 hover:ring-brand-200 dark:bg-zinc-800/40 dark:ring-zinc-700/60 dark:hover:bg-brand-500/10 dark:hover:ring-brand-400/20">
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white text-brand-600 shadow-xs dark:bg-zinc-900 dark:text-brand-400">
                                            <x-icon name="mail" class="size-4.5" />
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block text-[10px] text-zinc-400">ایمیل</span>
                                            <span class="block truncate text-sm font-black text-zinc-800 dark:text-zinc-100" dir="ltr">{{ $hs['contact_email'] }}</span>
                                        </span>
                                    </a>
                                @endif
                                @if(preg_match('#^https?://#', $hs['contact_telegram']))
                                    <a href="{{ $hs['contact_telegram'] }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-3 rounded-xl bg-zinc-50 p-3.5 ring-1 ring-zinc-200/60 transition-colors hover:bg-brand-50 hover:ring-brand-200 dark:bg-zinc-800/40 dark:ring-zinc-700/60 dark:hover:bg-brand-500/10 dark:hover:ring-brand-400/20">
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white text-brand-600 shadow-xs dark:bg-zinc-900 dark:text-brand-400">
                                            <x-icon name="send" class="size-4.5" />
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block text-[10px] text-zinc-400">تلگرام</span>
                                            <span class="block truncate text-sm font-black text-zinc-800 dark:text-zinc-100" dir="ltr">{{ $hs['contact_telegram'] }}</span>
                                        </span>
                                    </a>
                                @endif
                                @if($hs['contact_address'] !== '')
                                    <div class="flex items-center gap-3 rounded-xl bg-zinc-50 p-3.5 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white text-brand-600 shadow-xs dark:bg-zinc-900 dark:text-brand-400">
                                            <x-icon name="map-pin" class="size-4.5" />
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block text-[10px] text-zinc-400">آدرس</span>
                                            <span class="block text-sm font-black text-zinc-800 dark:text-zinc-100">{{ $hs['contact_address'] }}</span>
                                        </span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- تضمین‌های اعتماد --}}
                        <div class="card p-5 sm:p-6">
                            <h3 class="text-base font-black text-zinc-900 dark:text-zinc-50">با خیال راحت شروع کنید</h3>
                            <ul class="mt-4 space-y-3 text-xs leading-6 text-zinc-600 dark:text-zinc-300">
                                <li class="flex items-start gap-2">
                                    <x-icon name="badge-check" class="mt-0.5 size-4 shrink-0 text-brand-600 dark:text-brand-400" />
                                    مشاوره اولیه رایگان است؛ بدون هیچ تعهدی ایده‌تان را بپرسید.
                                </li>
                                <li class="flex items-start gap-2">
                                    <x-icon name="shield-check" class="mt-0.5 size-4 shrink-0 text-brand-600 dark:text-brand-400" />
                                    برآورد هزینه و زمان تحویل قبل از شروع کار، شفاف و کتبی اعلام می‌شود.
                                </li>
                                <li class="flex items-start gap-2">
                                    <x-icon name="heart-handshake" class="mt-0.5 size-4 shrink-0 text-brand-600 dark:text-brand-400" />
                                    بعد از تحویل هم تنها نیستید؛ پشتیبانی و توسعه ادامه دارد.
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>
        @endif

    </div>
</div>
