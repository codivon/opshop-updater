<div class="mx-auto w-full max-w-7xl space-y-6 px-4 pt-8 sm:px-6">

    {{-- ============ Header ============ --}}
    @php($customer = auth('customer')->user())
    <header class="card flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:p-6">
        <x-avatar :name="$customer?->name ?? 'مشتری'" size="lg" />

        <div class="min-w-0 flex-1">
            <h1 class="text-lg font-black text-zinc-900 dark:text-zinc-50">{{ $customer?->name }}</h1>
            <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                <span class="inline-flex items-center gap-1">
                    <x-icon name="key" class="size-3.5" />
                    کد آپدیت: <span class="font-mono font-bold text-zinc-700 dark:text-zinc-200" dir="ltr">{{ $customer?->update_code }}</span>
                </span>
                @if($this->mySubscription?->plan)
                    <span class="inline-flex items-center gap-1 text-amber-600 dark:text-amber-400">
                        <x-icon name="crown" class="size-3.5" />
                        طرح «{{ $this->mySubscription->plan->name }}» فعال
                    </span>
                @endif
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('shop.home') }}" wire:navigate>
                <x-btn variant="secondary" size="sm" icon="store">فروشگاه</x-btn>
            </a>
            <x-btn variant="ghost" size="sm" icon="log-out" wire:click="logout">خروج</x-btn>
        </div>
    </header>

    {{-- ============ Stats ============ --}}
    <section class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="card flex items-center gap-4 p-5">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-100 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                <x-icon name="package" class="size-5.5" />
            </span>
            <div>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">پکیج خریده‌شده</p>
                <p class="text-xl font-black tabular-nums text-zinc-900 dark:text-zinc-50">{{ fa_num($this->stats['purchases']) }}</p>
            </div>
        </div>
        <div class="card flex items-center gap-4 p-5">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400">
                <x-icon name="shield-check" class="size-5.5" />
            </span>
            <div>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">لایسنس فعال</p>
                <p class="text-xl font-black tabular-nums text-zinc-900 dark:text-zinc-50">{{ fa_num($this->stats['licenses']) }}</p>
            </div>
        </div>
        <div class="card flex items-center gap-4 p-5">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400">
                <x-icon name="wallet" class="size-5.5" />
            </span>
            <div>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">مجموع خرید</p>
                <p class="text-xl font-black tabular-nums text-zinc-900 dark:text-zinc-50">{{ money($this->stats['spent']) }} تومان</p>
            </div>
        </div>
    </section>

    {{-- ============ Tabs ============ --}}
    <div class="flex items-center justify-center">
        <div class="inline-flex rounded-2xl bg-zinc-200/70 p-1 dark:bg-zinc-800/70" role="tablist">
            <button type="button" wire:click="switchTab('purchases')" role="tab"
                    @class(['flex cursor-pointer items-center gap-2 rounded-xl px-4 py-2 text-sm font-black transition-all',
                        $tab === 'purchases' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-900 dark:text-zinc-50' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400'])
                    aria-current="{{ $tab === 'purchases' ? 'true' : 'false' }}">
                <x-icon name="package" class="size-4" /> پکیج‌های من
            </button>
            <button type="button" wire:click="switchTab('licenses')" role="tab"
                    @class(['flex cursor-pointer items-center gap-2 rounded-xl px-4 py-2 text-sm font-black transition-all',
                        $tab === 'licenses' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-900 dark:text-zinc-50' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400'])
                    aria-current="{{ $tab === 'licenses' ? 'true' : 'false' }}">
                <x-icon name="shield-check" class="size-4" /> لایسنس‌ها
            </button>
            <button type="button" wire:click="switchTab('info')" role="tab"
                    @class(['flex cursor-pointer items-center gap-2 rounded-xl px-4 py-2 text-sm font-black transition-all',
                        $tab === 'info' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-900 dark:text-zinc-50' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400'])
                    aria-current="{{ $tab === 'info' ? 'true' : 'false' }}">
                <x-icon name="user-pen" class="size-4" /> مشخصات
            </button>
        </div>
    </div>

    {{-- ==================== TAB: پکیج‌های من ==================== --}}
    @if($tab === 'purchases')
        @if($this->purchases->count())
            <div class="space-y-4">
                @foreach($this->purchases as $purchase)
                    @php($license = \App\Models\PackageLicense::query()
                        ->where('customer_id', $customer->id)
                        ->where('package_id', $purchase->package_id)
                        ->where('status', \App\Models\PackageLicense::STATUS_ACTIVE)
                        ->latest('id')->first())
                    <div wire:key="purchase-{{ $purchase->id }}" class="card flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:p-5">
                        {{-- thumbnail --}}
                        <div class="h-24 w-full shrink-0 overflow-hidden rounded-xl bg-zinc-100 sm:h-20 sm:w-32 dark:bg-zinc-800">
                            <img src="{{ $purchase->package->thumbnail_url }}" alt="{{ $purchase->package->name }}"
                                 class="size-full object-cover" onerror="this.style.display='none';this.parentElement.classList.add('shop-thumb')">
                        </div>

                        {{-- info --}}
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('shop.package', $purchase->package->slug) }}" wire:navigate
                                   class="text-sm font-black text-zinc-900 transition-colors hover:text-brand-600 dark:text-zinc-50">
                                    {{ $purchase->package->name }}
                                </a>
                                @if($license)
                                    <x-badge variant="success" icon="shield-check">لایسنس فعال</x-badge>
                                @else
                                    <x-badge variant="neutral" icon="receipt">خرید موفق</x-badge>
                                @endif
                            </div>
                            <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                                @if($purchase->version)
                                    <span class="font-mono" dir="ltr">v{{ $purchase->version->version }}</span>
                                @endif
                                @if($purchase->pricingPlan)
                                    <span class="inline-flex items-center gap-1"><x-icon name="layers" class="size-3.5" /> {{ $purchase->pricingPlan->name }}</span>
                                @endif
                                <span class="inline-flex items-center gap-1"><x-icon name="calendar" class="size-3.5" /> {{ verta_date($purchase->paid_at) }}</span>
                                @if($purchase->amount > 0)
                                    <span class="inline-flex items-center gap-1 tabular-nums">{{ money($purchase->amount) }} تومان</span>
                                @else
                                    <span class="text-emerald-600 dark:text-emerald-400">رایگان</span>
                                @endif
                            </div>
                            @if($license?->expires_at)
                                <p class="mt-1 inline-flex items-center gap-1 text-xs text-zinc-400">
                                    <x-icon name="calendar-clock" class="size-3.5" />
                                    اعتبار تا {{ verta_date($license->expires_at) }}
                                </p>
                            @endif
                        </div>

                        {{-- actions --}}
                        <div class="flex shrink-0 items-center gap-2">
                            <a href="{{ route('shop.package', $purchase->package->slug) }}" wire:navigate>
                                <x-btn variant="secondary" size="sm" icon="external-link">صفحه پکیج</x-btn>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- راهنمای نصب: مشتری پکیج را مستقیم دانلود نمی‌کند؛ با کد آپدیت در فروشگاه خودش نصب می‌کند --}}
            <div class="flex items-start gap-3 rounded-2xl bg-brand-50 p-4 ring-1 ring-brand-100 dark:bg-brand-500/10 dark:ring-brand-400/20">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-white text-brand-600 shadow-xs dark:bg-zinc-900 dark:text-brand-400">
                    <x-icon name="info" class="size-4.5" />
                </span>
                <p class="text-xs leading-6 text-brand-800/90 dark:text-brand-200/80">
                    دانلود مستقیم فایل پکیج‌ها ممکن نیست؛ برای نصب، در افزونه/فروشگاه ساز خود
                    <span class="font-black">کد آپدیت</span>
                    (<span class="font-mono font-bold" dir="ltr">{{ $customer->update_code }}</span>) را وارد کنید و
                    پکیج خریداری‌شده به‌صورت خودکار نصب و به‌روزرسانی می‌شود.
                </p>
            </div>

            <div>{{ $this->purchases->links() }}</div>
        @else
            <div class="card">
                <x-empty icon="package" title="هنوز پکیجی نخریده‌اید"
                         description="از فروشگاه پکیج موردنظرتان را تهیه کنید تا با کد آپدیت، در فروشگاه ساز شما قابل نصب باشد.">
                    <a href="{{ route('shop.home') }}" wire:navigate>
                        <x-btn variant="soft" size="sm" icon="store">رفتن به فروشگاه</x-btn>
                    </a>
                </x-empty>
            </div>
        @endif

    {{-- ==================== TAB: لایسنس‌ها ==================== --}}
    @elseif($tab === 'licenses')
        @if($this->licenses->count())
            <div class="card overflow-hidden p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 bg-zinc-50 text-xs text-zinc-500 dark:border-zinc-700/60 dark:bg-zinc-800/60 dark:text-zinc-400">
                                <th class="px-4 py-3 text-start font-bold">پکیج</th>
                                <th class="px-4 py-3 text-start font-bold">کلید لایسنس</th>
                                <th class="px-4 py-3 text-start font-bold">وضعیت</th>
                                <th class="px-4 py-3 text-start font-bold">اعتبار</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->licenses as $license)
                                <tr wire:key="lic-{{ $license->id }}" class="border-b border-zinc-100 last:border-0 dark:border-zinc-700/40">
                                    <td class="px-4 py-3 font-bold text-zinc-800 dark:text-zinc-100">{{ $license->package?->name }}</td>
                                    <td class="px-4 py-3 font-mono text-xs text-zinc-600 dark:text-zinc-300" dir="ltr">{{ $license->license_key }}</td>
                                    <td class="px-4 py-3">
                                        @if($license->status === \App\Models\PackageLicense::STATUS_ACTIVE)
                                            <x-badge variant="success" icon="check-circle">فعال</x-badge>
                                        @elseif($license->status === \App\Models\PackageLicense::STATUS_EXPIRED)
                                            <x-badge variant="warning" icon="clock">منقضی</x-badge>
                                        @else
                                            <x-badge variant="danger" icon="x-circle">لغو شده</x-badge>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 tabular-nums text-zinc-500">
                                        {{ $license->expires_at ? verta_date($license->expires_at) : 'نامحدود' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div>{{ $this->licenses->links() }}</div>
        @else
            <div class="card">
                <x-empty icon="shield-check" title="لایسنسی یافت نشد" description="پس از خرید پکیج، لایسنس آن در این بخش نمایش داده می‌شود." />
            </div>
        @endif

    {{-- ==================== TAB: مشخصات ==================== --}}
    @else
        <div class="card mx-auto w-full max-w-xl p-5 sm:p-6">
            <form wire:submit="saveInfo" class="space-y-4">
                <x-field label="نام و نام خانوادگی" for="pf-name" required>
                    <x-input id="pf-name" wire:model="form.name" placeholder="نام شما" />
                </x-field>

                <x-field label="ایمیل" for="pf-email">
                    <x-input id="pf-email" type="email" wire:model="form.email" placeholder="you@example.com" dir="ltr" class="text-start" />
                </x-field>

                <x-field label="شماره موبایل" for="pf-phone">
                    <x-input id="pf-phone" wire:model="form.phone" placeholder="۰۹۱۲…" dir="ltr" class="text-start" />
                </x-field>

                <x-field label="آدرس سایت" for="pf-site" hint="سایتی که پکیج‌ها را روی آن نصب کرده‌اید (اختیاری)">
                    <x-input id="pf-site" wire:model="form.website_url" placeholder="https://example.com" dir="ltr" class="text-start" />
                </x-field>

                <div class="flex items-center justify-end border-t border-zinc-100 pt-4 dark:border-zinc-700/50">
                    <x-btn type="submit" icon="save" :loading="true">ذخیره مشخصات</x-btn>
                </div>
            </form>
        </div>
    @endif

</div>
