<div class="pb-2">

    {{-- ==================== Hero band (بازطراحی مدرن — Task 82) ==================== --}}
    <section class="shop-hero relative overflow-hidden">
        {{-- عناصر تزئینی: هاله‌های نور + بافت نقطه‌ای --}}
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div class="absolute -top-28 start-1/5 size-80 rounded-full shop-hero-glow"></div>
            <div class="absolute -bottom-36 end-0 size-96 rounded-full bg-emerald-400/10 blur-3xl"></div>
            <div class="absolute inset-0 opacity-60 [background-image:radial-gradient(circle_at_1px_1px,rgba(255,255,255,0.09)_1px,transparent_0)] [background-size:22px_22px]"></div>
        </div>

        <div class="relative mx-auto w-full max-w-7xl px-4 py-14 sm:px-6 lg:px-8 sm:py-16">
            <div class="grid items-center gap-10 lg:grid-cols-[1fr_auto]">
                <div>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3.5 py-1.5 text-xs font-bold text-teal-100 ring-1 ring-white/20 backdrop-blur">
                        <x-icon name="rocket" class="size-3.5" />
                        فروش پروژه‌های آماده
                    </span>

                    <h1 class="shop-hero-title mt-4 text-3xl font-black leading-tight sm:text-4xl">
                        پروژه‌های آماده
                    </h1>

                    <p class="mt-3 max-w-xl text-sm leading-7 text-teal-50/80 sm:text-base">
                        پروژهٔ کامل و آمادهٔ راه‌اندازی را مستقیم بخرید؛ بدون ثبت‌نام، بدون واسطه — پس از پرداخت همان‌جا فایل را دانلود کنید و پروژه را بالا بیاورید.
                    </p>

                    <div class="mt-6 flex flex-wrap items-center gap-2.5">
                        <a href="{{ route('shop.packages') }}" wire:navigate
                           class="inline-flex items-center gap-1.5 rounded-xl bg-white/10 px-4 py-2.5 text-xs font-bold text-white ring-1 ring-white/20 backdrop-blur transition-all hover:-translate-y-0.5 hover:bg-white/20">
                            <x-icon name="package" class="size-4" />
                            پکیج‌ها
                        </a>
                        <a href="{{ route('shop.templates') }}" wire:navigate
                           class="inline-flex items-center gap-1.5 rounded-xl bg-white/10 px-4 py-2.5 text-xs font-bold text-white ring-1 ring-white/20 backdrop-blur transition-all hover:-translate-y-0.5 hover:bg-white/20">
                            <x-icon name="layout-template" class="size-4" />
                            قالب‌های آماده
                        </a>
                        <a href="{{ route('shop.subscriptions') }}" wire:navigate
                           class="inline-flex items-center gap-1.5 rounded-xl bg-amber-400/90 px-4 py-2.5 text-xs font-bold text-amber-950 shadow-lg shadow-amber-950/20 transition-all hover:-translate-y-0.5 hover:bg-amber-300">
                            <x-icon name="crown" class="size-4" />
                            طرح‌های اشتراک
                        </a>
                    </div>
                </div>

                {{-- کارت‌های آمار شیشه‌ای --}}
                <div class="grid w-full grid-cols-3 gap-3 lg:w-96" aria-label="آمار پروژه‌ها">
                    <div class="rounded-2xl bg-white/10 px-3 py-4 text-center ring-1 ring-white/15 backdrop-blur">
                        <p class="text-2xl font-black text-white sm:text-3xl">{{ fa_num($this->heroStats['total']) }}</p>
                        <p class="mt-1 text-[11px] font-bold text-teal-100/80">پروژهٔ فعال</p>
                    </div>
                    <div class="rounded-2xl bg-white/10 px-3 py-4 text-center ring-1 ring-white/15 backdrop-blur">
                        <p class="text-2xl font-black text-emerald-300 sm:text-3xl">{{ fa_num($this->heroStats['free']) }}</p>
                        <p class="mt-1 text-[11px] font-bold text-teal-100/80">رایگان دانلود کنید</p>
                    </div>
                    <div class="rounded-2xl bg-white/10 px-3 py-4 text-center ring-1 ring-white/15 backdrop-blur">
                        <p class="text-2xl font-black text-white sm:text-3xl">{{ fa_num($this->heroStats['categories']) }}</p>
                        <p class="mt-1 text-[11px] font-bold text-teal-100/80">دسته‌بندی</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ==================== Content ==================== --}}
    <div class="mx-auto w-full max-w-7xl space-y-6 px-4 pt-8 sm:px-6 lg:px-8">

        {{-- ============ Filter bar ============ --}}
        @include('livewire.shop.partials.filter-bar', ['type' => 'product'])

        {{-- ============ Grid ============ --}}
        @if($this->records->count())
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($this->records as $product)
                    @include('livewire.shop.partials.product-card', ['product' => $product])
                @endforeach
            </div>

            <div>{{ $this->records->links() }}</div>
        @else
            <div class="rounded-card bg-white p-6 shadow-card ring-1 ring-zinc-200/80 dark:bg-zinc-900 dark:ring-zinc-800">
                <x-empty icon="rocket" title="پروژه‌ای یافت نشد" description="با جستجوی دیگری تلاش کنید یا فیلترها را تغییر دهید." />
            </div>
        @endif

        {{-- ============ پیگیری خرید مهمان ============ --}}
        <div class="pt-2">
            <livewire:shop.purchase-track />
        </div>

    </div>
</div>
