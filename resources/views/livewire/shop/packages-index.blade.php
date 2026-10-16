<div class="mx-auto w-full max-w-7xl space-y-6 px-4 pt-8 sm:px-6">

    {{-- ============ Page header ============ --}}
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 text-xs font-bold text-brand-700 ring-1 ring-brand-100 dark:bg-brand-500/10 dark:text-brand-300 dark:ring-brand-500/20">
                <x-icon name="package" class="size-3.5" />
                فروشگاه پکیج‌ها
            </span>
            <h1 class="mt-3 text-2xl font-black text-zinc-900 dark:text-zinc-50">پکیج‌های فروشگاه</h1>
            <p class="mt-1.5 text-sm leading-6 text-zinc-500 dark:text-zinc-400">
                پکیج‌ها با «کد آپدیت» پنل مشتری خریداری و فعال می‌شوند؛ دارندگان اشتراک، پکیج‌های طرح خود را رایگان می‌گیرند.
            </p>
        </div>

        <a href="{{ route('shop.subscriptions') }}" wire:navigate
           class="inline-flex items-center gap-1.5 rounded-xl bg-amber-50 px-3.5 py-2 text-xs font-bold text-amber-700 ring-1 ring-amber-200/70 transition-colors hover:bg-amber-100 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20">
            <x-icon name="crown" class="size-4" />
            طرح‌های اشتراک ویژه
        </a>
    </header>

    {{-- ============ Filter bar ============ --}}
    @include('livewire.shop.partials.filter-bar', ['type' => 'package'])

    {{-- ============ Grid ============ --}}
    @if($this->records->count())
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($this->records as $package)
                @include('livewire.shop.partials.package-card', ['package' => $package, 'isCovered' => in_array($package->id, $this->coveredPackageIds)])
            @endforeach
        </div>

        <div>{{ $this->records->links() }}</div>
    @else
        <div class="card">
            <x-empty icon="package" title="پکیجی یافت نشد" description="با جستجوی دیگری تلاش کنید یا فیلترها را تغییر دهید." />
        </div>
    @endif

</div>
