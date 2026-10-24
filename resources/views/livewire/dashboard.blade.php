<div class="space-y-6">
    {{-- welcome --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-black text-zinc-900 dark:text-zinc-50">سلام {{ auth()->user()?->name }}، خوش برگشتی 👋</h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">نمای کلی سیستم امروز — {{ verta_date(now(), 'l d F Y') }}</p>
        </div>
        <x-btn href="{{ route('admin.packages.index') }}" icon="package" variant="secondary">
            مشاهده پکیج‌ها
        </x-btn>
    </div>

    {{-- stats --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
        @foreach($this->stats as $stat)
            <x-stat :label="$stat['label']" :value="fa_num($stat['value'])" :icon="$stat['icon']"
                    :variant="$stat['variant']" :hint="$stat['hint']" :href="$stat['href']" />
        @endforeach
    </div>

    {{-- revenue + charts --}}
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-card class="xl:col-span-2" padding="p-5">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <x-chart :title="$this->customerChart['title']" :series="$this->customerChart['series']" color="brand" />
                <x-chart :title="$this->purchaseChart['title']" :series="$this->purchaseChart['series']" color="teal" />
                <x-chart :title="$this->templateSalesChart['title']" :series="$this->templateSalesChart['series']" color="brand" class="lg:col-span-2" />
            </div>

            {{-- updates status pills --}}
            <div class="mt-6 flex flex-wrap items-center gap-2 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                <span class="me-1 flex items-center gap-1.5 text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                    <x-icon name="git-branch" class="size-3.5" />
                    وضعیت آپدیت‌ها:
                </span>
                <x-badge variant="success" icon="check-circle">فعال {{ fa_num($this->updatesBreakdown['active']) }}</x-badge>
                <x-badge variant="warning" icon="pencil">پیش‌نویس {{ fa_num($this->updatesBreakdown['draft']) }}</x-badge>
                <x-badge variant="neutral" icon="archive">بایگانی {{ fa_num($this->updatesBreakdown['archived']) }}</x-badge>
            </div>
        </x-card>

        {{-- revenue card --}}
        <x-card title="درآمد و فروش">
            <div class="space-y-4">
                <div class="rounded-2xl bg-gradient-to-br from-brand-500 to-brand-600 p-5 text-white shadow-lg shadow-brand-500/20">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-medium text-brand-50/90">درآمد کل</p>
                        <x-icon name="wallet" class="size-5 text-brand-50/90" />
                    </div>
                    <p class="mt-2 text-2xl font-black tabular-nums">{{ money($this->revenue['total']) }}</p>
                    <div class="mt-4 grid grid-cols-2 gap-3 text-xs">
                        <div class="rounded-xl bg-white/10 px-3 py-2.5 backdrop-blur">
                            <p class="text-brand-50/80">اشتراک‌ها</p>
                            <p class="mt-0.5 font-bold tabular-nums">{{ money($this->revenue['subscriptions'], false) }}</p>
                        </div>
                        <div class="rounded-xl bg-white/10 px-3 py-2.5 backdrop-blur">
                            <p class="text-brand-50/80">پکیج‌ها</p>
                            <p class="mt-0.5 font-bold tabular-nums">{{ money($this->revenue['packages'], false) }}</p>
                        </div>
                        <div class="rounded-xl bg-white/10 px-3 py-2.5 backdrop-blur">
                            <p class="text-brand-50/80">محصولات</p>
                            <p class="mt-0.5 font-bold tabular-nums">{{ money($this->revenue['products'], false) }}</p>
                        </div>
                        <div class="rounded-xl bg-white/10 px-3 py-2.5 backdrop-blur">
                            <p class="text-brand-50/80">قالب‌ها</p>
                            <p class="mt-0.5 font-bold tabular-nums">{{ money($this->revenue['templates'], false) }}</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/80 dark:bg-zinc-800/60 dark:ring-zinc-700">
                        <div class="flex items-center gap-2 text-zinc-500 dark:text-zinc-400">
                            <x-icon name="check-circle" class="size-4 text-emerald-500" />
                            <p class="text-xs font-medium">خرید موفق</p>
                        </div>
                        <p class="mt-1.5 text-lg font-black tabular-nums text-zinc-900 dark:text-zinc-50">{{ fa_num($this->revenue['paid_purchases']) }}</p>
                    </div>
                    <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/80 dark:bg-zinc-800/60 dark:ring-zinc-700">
                        <div class="flex items-center gap-2 text-zinc-500 dark:text-zinc-400">
                            <x-icon name="clock" class="size-4 text-amber-500" />
                            <p class="text-xs font-medium">در انتظار پرداخت</p>
                        </div>
                        <p class="mt-1.5 text-lg font-black tabular-nums text-zinc-900 dark:text-zinc-50">{{ fa_num($this->revenue['pending_purchases']) }}</p>
                    </div>
                </div>

                <div class="flex items-center justify-between rounded-xl bg-zinc-50 px-4 py-3 ring-1 ring-zinc-200/80 dark:bg-zinc-800/60 dark:ring-zinc-700">
                    <div class="flex items-center gap-2.5">
                        <x-icon name="download" class="size-4.5 text-brand-600 dark:text-brand-400" />
                        <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">مجموع دانلودها</p>
                    </div>
                    <p class="font-black tabular-nums text-zinc-900 dark:text-zinc-50">{{ fa_num($this->revenue['downloads']) }}</p>
                </div>
            </div>
        </x-card>
    </div>

    {{-- recent + top --}}
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        {{-- recent purchases --}}
        <x-card title="آخرین خریدها" subtitle="خریدهای موفق پکیج‌ها">
            <div class="-my-2 divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse($this->recentPurchases as $purchase)
                    <div class="flex items-center gap-3 py-2.5">
                        <x-icon name="package" class="size-4 shrink-0 text-zinc-400" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-zinc-700 dark:text-zinc-200">{{ $purchase->package?->name ?? '—' }}</p>
                            <p class="truncate text-xs text-zinc-500">{{ $purchase->customer?->name }} · {{ verta_date($purchase->paid_at) }}</p>
                        </div>
                        <span class="shrink-0 text-xs font-bold tabular-nums text-brand-600 dark:text-brand-400">{{ money($purchase->amount, false) }}</span>
                    </div>
                @empty
                    <x-empty icon="receipt" title="هنوز خریدی ثبت نشده" description="خریدهای مشتریان از طریق API اینجا نمایش داده می‌شوند." class="py-8" />
                @endforelse
            </div>
        </x-card>

        {{-- top packages --}}
        <x-card title="پکیج‌های پرفروش">
            <div class="-my-2 divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse($this->topPackages as $package)
                    <a href="{{ route('admin.packages.show', $package) }}" wire:navigate class="group flex items-center gap-3 py-2.5">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-zinc-100 text-xs font-black text-zinc-500 group-hover:bg-brand-50 group-hover:text-brand-600 dark:bg-zinc-800 dark:text-zinc-400 dark:group-hover:bg-brand-500/10 dark:group-hover:text-brand-400">
                            {{ fa_num($loop->iteration) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-zinc-700 group-hover:text-brand-600 dark:text-zinc-200 dark:group-hover:text-brand-400">{{ $package->name }}</p>
                            <p class="flex items-center gap-2 text-xs text-zinc-500">
                                {{ fa_num($package->paid_purchases_count) }} فروش
                                @if($package->paid_revenue_sum > 0)
                                    <span class="font-bold text-emerald-600 dark:text-emerald-400">· {{ money($package->paid_revenue_sum, false) }}</span>
                                @endif
                                <span class="text-zinc-300 dark:text-zinc-600">·</span>
                                {{ fa_num($package->downloads_count) }} دانلود
                            </p>
                        </div>
                        <x-icon name="chevron-left" class="size-4 shrink-0 text-zinc-300 transition-transform group-hover:-translate-x-0.5 group-hover:text-brand-500" />
                    </a>
                @empty
                    <x-empty icon="package" title="پکیجی ثبت نشده" class="py-8" />
                @endforelse
            </div>
        </x-card>
    </div>

    {{-- license health: در حال اتمام / منقضی‌شده --}}
    <div class="space-y-4">
        {{-- section header --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="flex items-center gap-2.5 text-base font-black text-zinc-900 dark:text-zinc-50">
                <span class="flex size-9 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400">
                    <x-icon name="key" class="size-4.5" />
                </span>
                وضعیت لایسنس‌ها
            </h3>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.licenses.index', ['type' => 'package', 'sort' => 'expires_soon']) }}" wire:navigate
                   class="inline-flex items-center gap-1.5 rounded-full bg-amber-500/10 px-3 py-1.5 text-xs font-bold tabular-nums text-amber-600 transition-colors hover:bg-amber-500/20 dark:bg-amber-500/15 dark:text-amber-400 dark:hover:bg-amber-500/25">
                    <x-icon name="clock" class="size-3.5" />
                    {{ fa_num($this->licenseExpiryCounts['expiring']) }} در حال اتمام
                </a>
                <a href="{{ route('admin.licenses.index', ['type' => 'package', 'status' => 'expired']) }}" wire:navigate
                   class="inline-flex items-center gap-1.5 rounded-full bg-rose-500/10 px-3 py-1.5 text-xs font-bold tabular-nums text-rose-600 transition-colors hover:bg-rose-500/20 dark:bg-rose-500/15 dark:text-rose-400 dark:hover:bg-rose-500/25">
                    <x-icon name="alert-triangle" class="size-3.5" />
                    {{ fa_num($this->licenseExpiryCounts['expired']) }} منقضی‌شده
                </a>
                <a href="{{ route('admin.licenses.index') }}" wire:navigate
                   class="inline-flex items-center gap-1.5 rounded-lg bg-zinc-100 px-3 py-1.5 text-xs font-semibold text-zinc-600 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-brand-500/10 dark:hover:text-brand-400">
                    <x-icon name="key" class="size-3.5" />
                    مدیریت لایسنس‌ها
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            {{-- expiring licenses --}}
            <x-card title="در حال اتمام"
                    :subtitle="fa_num($this->licenseExpiryDays) . ' روز آینده · ' . fa_num($this->licenseExpiryCounts['expiring']) . ' لایسنس'">
                <div class="-my-2 divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse($this->expiringLicenses as $license)
                        @php($chip = $this->daysLeftChip($license->expires_at))
                        <a href="{{ route('admin.licenses.show', $license) }}" wire:navigate
                           class="group flex items-center gap-3 py-2.5">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600 transition-colors group-hover:bg-amber-100 dark:bg-amber-500/10 dark:text-amber-400 dark:group-hover:bg-amber-500/20">
                                <x-icon name="clock" class="size-4.5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-zinc-700 transition-colors group-hover:text-amber-600 dark:text-zinc-200 dark:group-hover:text-amber-400">{{ $license->package?->name ?? '—' }}</p>
                                <p class="truncate text-xs text-zinc-500">{{ $license->customer?->name ?? 'بدون مشتری' }} · انقضا: {{ verta_date($license->expires_at) }}</p>
                            </div>
                            <x-badge :variant="$chip['tone']">{{ $chip['label'] }}</x-badge>
                        </a>
                    @empty
                        <x-empty icon="check-circle" title="همه چیز مرتب است"
                                 :description="'هیچ لایسنسی در ' . fa_num($this->licenseExpiryDays) . ' روز آینده منقضی نمی‌شود.'"
                                 class="py-8" />
                    @endforelse
                </div>
                @if($this->licenseExpiryCounts['expiring'] > count($this->expiringLicenses))
                    <a href="{{ route('admin.licenses.index', ['type' => 'package', 'sort' => 'expires_soon']) }}" wire:navigate
                       class="mt-3 flex items-center justify-center gap-1.5 rounded-xl bg-zinc-50 py-2.5 text-xs font-semibold text-zinc-600 transition-colors hover:bg-amber-50 hover:text-amber-600 dark:bg-zinc-800/60 dark:text-zinc-300 dark:hover:bg-amber-500/10 dark:hover:text-amber-400">
                        مشاهده همهٔ لایسنس‌های در حال اتمام
                        <x-icon name="chevron-left" class="size-3.5" />
                    </a>
                @endif
            </x-card>

            {{-- expired licenses --}}
            <x-card title="منقضی‌شده"
                    :subtitle="fa_num($this->licenseExpiryCounts['expired']) . ' لایسنس گذشته از تاریخ انقضا'">
                <div class="-my-2 divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse($this->expiredLicenses as $license)
                        @php($chip = $this->daysAgoChip($license->expires_at))
                        <a href="{{ route('admin.licenses.show', $license) }}" wire:navigate
                           class="group flex items-center gap-3 py-2.5">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600 transition-colors group-hover:bg-rose-100 dark:bg-rose-500/10 dark:text-rose-400 dark:group-hover:bg-rose-500/20">
                                <x-icon name="alert-triangle" class="size-4.5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-zinc-700 transition-colors group-hover:text-rose-600 dark:text-zinc-200 dark:group-hover:text-rose-400">{{ $license->package?->name ?? '—' }}</p>
                                <p class="truncate text-xs text-zinc-500">{{ $license->customer?->name ?? 'بدون مشتری' }} · انقضا: {{ verta_date($license->expires_at) }}</p>
                            </div>
                            <x-badge :variant="$chip['tone']">{{ $chip['label'] }}</x-badge>
                        </a>
                    @empty
                        <x-empty icon="check-circle" title="هیچ لایسنسی منقضی نشده"
                                 description="لایسنس‌های گذشته از تاریخ انقضا اینجا نمایش داده می‌شوند."
                                 class="py-8" />
                    @endforelse
                </div>
                @if($this->licenseExpiryCounts['expired'] > count($this->expiredLicenses))
                    <a href="{{ route('admin.licenses.index', ['type' => 'package', 'status' => 'expired']) }}" wire:navigate
                       class="mt-3 flex items-center justify-center gap-1.5 rounded-xl bg-zinc-50 py-2.5 text-xs font-semibold text-zinc-600 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:bg-zinc-800/60 dark:text-zinc-300 dark:hover:bg-rose-500/10 dark:hover:text-rose-400">
                        مشاهده همهٔ لایسنس‌های منقضی‌شده
                        <x-icon name="chevron-left" class="size-3.5" />
                    </a>
                @endif
            </x-card>
        </div>
    </div>

    {{-- subscription health: در حال اتمام / منقضی‌شده --}}
    <div class="space-y-4">
        {{-- section header --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="flex items-center gap-2.5 text-base font-black text-zinc-900 dark:text-zinc-50">
                <span class="flex size-9 items-center justify-center rounded-xl bg-violet-500/10 text-violet-600 dark:bg-violet-500/15 dark:text-violet-400">
                    <x-icon name="ticket" class="size-4.5" />
                </span>
                وضعیت اشتراک‌ها
            </h3>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.subscriptions.index', ['sort' => 'expires_soon']) }}" wire:navigate
                   class="inline-flex items-center gap-1.5 rounded-full bg-amber-500/10 px-3 py-1.5 text-xs font-bold tabular-nums text-amber-600 transition-colors hover:bg-amber-500/20 dark:bg-amber-500/15 dark:text-amber-400 dark:hover:bg-amber-500/25">
                    <x-icon name="clock" class="size-3.5" />
                    {{ fa_num($this->subscriptionExpiryCounts['expiring']) }} در حال اتمام
                </a>
                <a href="{{ route('admin.subscriptions.index', ['status' => 'expired']) }}" wire:navigate
                   class="inline-flex items-center gap-1.5 rounded-full bg-rose-500/10 px-3 py-1.5 text-xs font-bold tabular-nums text-rose-600 transition-colors hover:bg-rose-500/20 dark:bg-rose-500/15 dark:text-rose-400 dark:hover:bg-rose-500/25">
                    <x-icon name="alert-triangle" class="size-3.5" />
                    {{ fa_num($this->subscriptionExpiryCounts['expired']) }} منقضی‌شده
                </a>
                <a href="{{ route('admin.subscriptions.index') }}" wire:navigate
                   class="inline-flex items-center gap-1.5 rounded-lg bg-zinc-100 px-3 py-1.5 text-xs font-semibold text-zinc-600 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-brand-500/10 dark:hover:text-brand-400">
                    <x-icon name="ticket" class="size-3.5" />
                    مدیریت اشتراک‌ها
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            {{-- expiring subscriptions --}}
            <x-card title="در حال اتمام"
                    :subtitle="fa_num($this->subscriptionExpiryDays) . ' روز آینده · ' . fa_num($this->subscriptionExpiryCounts['expiring']) . ' اشتراک'">
                <div class="-my-2 divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse($this->expiringSubscriptions as $sub)
                        @php($chip = $this->daysLeftChip($sub->effective_expires_at))
                        <a href="{{ $sub->customer_id ? route('admin.subscriptions.index', ['customer_id' => $sub->customer_id]) : route('admin.subscriptions.index') }}" wire:navigate
                           class="group flex items-center gap-3 py-2.5">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600 transition-colors group-hover:bg-amber-100 dark:bg-amber-500/10 dark:text-amber-400 dark:group-hover:bg-amber-500/20">
                                <x-icon name="clock" class="size-4.5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-zinc-700 transition-colors group-hover:text-amber-600 dark:text-zinc-200 dark:group-hover:text-amber-400">{{ $sub->customer?->name ?? 'بدون مشتری' }}</p>
                                <p class="truncate text-xs text-zinc-500">{{ $sub->plan?->name ?? $sub->project?->name ?? '—' }} · انقضا: {{ verta_date($sub->effective_expires_at) }}</p>
                            </div>
                            <x-badge :variant="$chip['tone']">{{ $chip['label'] }}</x-badge>
                        </a>
                    @empty
                        <x-empty icon="check-circle" title="همه چیز مرتب است"
                                 :description="'هیچ اشتراکی در ' . fa_num($this->subscriptionExpiryDays) . ' روز آینده منقضی نمی‌شود.'"
                                 class="py-8" />
                    @endforelse
                </div>
                @if($this->subscriptionExpiryCounts['expiring'] > count($this->expiringSubscriptions))
                    <a href="{{ route('admin.subscriptions.index', ['sort' => 'expires_soon']) }}" wire:navigate
                       class="mt-3 flex items-center justify-center gap-1.5 rounded-xl bg-zinc-50 py-2.5 text-xs font-semibold text-zinc-600 transition-colors hover:bg-amber-50 hover:text-amber-600 dark:bg-zinc-800/60 dark:text-zinc-300 dark:hover:bg-amber-500/10 dark:hover:text-amber-400">
                        مشاهده همهٔ اشتراک‌های در حال اتمام
                        <x-icon name="chevron-left" class="size-3.5" />
                    </a>
                @endif
            </x-card>

            {{-- expired subscriptions --}}
            <x-card title="منقضی‌شده"
                    :subtitle="fa_num($this->subscriptionExpiryCounts['expired']) . ' اشتراک گذشته از تاریخ انقضا'">
                <div class="-my-2 divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse($this->expiredSubscriptions as $sub)
                        @php($chip = $this->daysAgoChip($sub->effective_expires_at))
                        <a href="{{ $sub->customer_id ? route('admin.subscriptions.index', ['customer_id' => $sub->customer_id]) : route('admin.subscriptions.index') }}" wire:navigate
                           class="group flex items-center gap-3 py-2.5">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600 transition-colors group-hover:bg-rose-100 dark:bg-rose-500/10 dark:text-rose-400 dark:group-hover:bg-rose-500/20">
                                <x-icon name="alert-triangle" class="size-4.5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-zinc-700 transition-colors group-hover:text-rose-600 dark:text-zinc-200 dark:group-hover:text-rose-400">{{ $sub->customer?->name ?? 'بدون مشتری' }}</p>
                                <p class="truncate text-xs text-zinc-500">{{ $sub->plan?->name ?? $sub->project?->name ?? '—' }} · انقضا: {{ verta_date($sub->effective_expires_at) }}</p>
                            </div>
                            <x-badge :variant="$chip['tone']">{{ $chip['label'] }}</x-badge>
                        </a>
                    @empty
                        <x-empty icon="check-circle" title="هیچ اشتراکی منقضی نشده"
                                 description="اشتراک‌های گذشته از تاریخ انقضا اینجا نمایش داده می‌شوند."
                                 class="py-8" />
                    @endforelse
                </div>
                @if($this->subscriptionExpiryCounts['expired'] > count($this->expiredSubscriptions))
                    <a href="{{ route('admin.subscriptions.index', ['status' => 'expired']) }}" wire:navigate
                       class="mt-3 flex items-center justify-center gap-1.5 rounded-xl bg-zinc-50 py-2.5 text-xs font-semibold text-zinc-600 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:bg-zinc-800/60 dark:text-zinc-300 dark:hover:bg-rose-500/10 dark:hover:text-rose-400">
                        مشاهده همهٔ اشتراک‌های منقضی‌شده
                        <x-icon name="chevron-left" class="size-3.5" />
                    </a>
                @endif
            </x-card>
        </div>
    </div>

    {{-- templates section (فروش قالب‌ها) --}}
    <div class="space-y-4">
        {{-- section header --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="flex items-center gap-2.5 text-base font-black text-zinc-900 dark:text-zinc-50">
                <span class="flex size-9 items-center justify-center rounded-xl bg-violet-500/10 text-violet-600 dark:bg-violet-500/15 dark:text-violet-400">
                    <x-icon name="layout-template" class="size-4.5" />
                </span>
                فروش قالب‌ها
            </h3>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.templates.index') }}" wire:navigate
                   class="inline-flex items-center gap-1.5 rounded-lg bg-zinc-100 px-3 py-1.5 text-xs font-semibold text-zinc-600 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-brand-500/10 dark:hover:text-brand-400">
                    <x-icon name="layout-template" class="size-3.5" />
                    مدیریت قالب‌ها
                </a>
                <a href="{{ route('admin.reports.index') }}" wire:navigate
                   class="inline-flex items-center gap-1.5 rounded-lg bg-zinc-100 px-3 py-1.5 text-xs font-semibold text-zinc-600 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-brand-500/10 dark:hover:text-brand-400">
                    <x-icon name="bar-chart" class="size-3.5" />
                    گزارش فروش
                </a>
            </div>
        </div>

        {{-- template mini stats --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat label="فروش کل قالب‌ها" :value="fa_num($this->templateStats['sales'])" icon="receipt" variant="violet"
                    :hint="fa_num($this->templateStats['exclusive_sales']) . ' خرید اختصاصی'" :href="route('admin.reports.index')" />
            <x-stat label="درآمد قالب‌ها" :value="money($this->templateStats['revenue'], false)" icon="wallet" variant="success"
                    hint="تومان — خریدهای پرداخت‌شده" :href="route('admin.reports.index')" />
            <x-stat label="قالب‌های قابل فروش" :value="fa_num($this->templateStats['active_templates'])" icon="layout-template" variant="primary"
                    hint="فعال و واگذارنشده" :href="route('admin.templates.index')" />
            <x-stat label="پرفروش‌ترین قالب" :value="$this->topTemplates->first()?->name ?? '—'" icon="crown" variant="warning"
                    :hint="$this->topTemplates->first() ? fa_num($this->topTemplates->first()->paid_purchases_count) . ' فروش' : 'هنوز فروشی ثبت نشده'" :href="route('admin.templates.index')" />
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            {{-- recent template sales --}}
            <x-card title="آخرین فروش قالب‌ها" subtitle="خریدهای موفق فروشگاه و API">
                <div class="-my-2 divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse($this->recentTemplateSales as $sale)
                        <div class="flex items-center gap-3 py-2.5">
                            <x-icon name="layout-template" class="size-4 shrink-0 text-zinc-400" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-zinc-700 dark:text-zinc-200">{{ $sale->template?->name ?? '—' }}</p>
                                <p class="truncate text-xs text-zinc-500">
                                    {{ $sale->customer?->name ?? 'مهمان (فروشگاه)' }} · {{ verta_date($sale->paid_at) }}
                                    @if($sale->purchase_type === \App\Models\TemplatePurchase::TYPE_EXCLUSIVE)
                                        <span class="font-bold text-violet-600 dark:text-violet-400">· اختصاصی</span>
                                    @endif
                                </p>
                            </div>
                            <span class="shrink-0 text-xs font-bold tabular-nums text-brand-600 dark:text-brand-400">{{ money($sale->amount, false) }}</span>
                        </div>
                    @empty
                        <x-empty icon="layout-template" title="هنوز قالبی فروخته نشده" description="فروش قالب‌ها از فروشگاه و API اینجا نمایش داده می‌شود." class="py-8" />
                    @endforelse
                </div>
            </x-card>

            {{-- top templates --}}
            <x-card title="پرفروش‌ترین قالب‌ها">
                <div class="-my-2 divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse($this->topTemplates as $template)
                        <a href="{{ route('admin.templates.show', $template) }}" wire:navigate class="group flex items-center gap-3 py-2.5">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-zinc-100 text-xs font-black text-zinc-500 group-hover:bg-brand-50 group-hover:text-brand-600 dark:bg-zinc-800 dark:text-zinc-400 dark:group-hover:bg-brand-500/10 dark:group-hover:text-brand-400">
                                {{ fa_num($loop->iteration) }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-zinc-700 group-hover:text-brand-600 dark:text-zinc-200 dark:group-hover:text-brand-400">{{ $template->name }}</p>
                                <p class="flex items-center gap-2 text-xs text-zinc-500">
                                    {{ fa_num($template->paid_purchases_count) }} فروش موفق
                                    <span class="text-zinc-300 dark:text-zinc-600">·</span>
                                    {{ money($template->paid_revenue, false) }} تومان
                                </p>
                            </div>
                            @if($template->is_sold_exclusively)
                                <x-badge variant="violet" icon="crown">اختصاصی</x-badge>
                            @endif
                            <x-icon name="chevron-left" class="size-4 shrink-0 text-zinc-300 transition-transform group-hover:-translate-x-0.5 group-hover:text-brand-500" />
                        </a>
                    @empty
                        <x-empty icon="layout-template" title="قالبی ثبت نشده" class="py-8" />
                    @endforelse
                </div>
            </x-card>
        </div>
    </div>

    {{-- subscription plans --}}
    <div class="space-y-4">
        {{-- section header --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="flex items-center gap-2.5 text-base font-black text-zinc-900 dark:text-zinc-50">
                <span class="flex size-9 items-center justify-center rounded-xl bg-brand-500/10 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                    <x-icon name="crown" class="size-4.5" />
                </span>
                طرح‌های اشتراک
            </h3>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.plans.index') }}" wire:navigate
                   class="inline-flex items-center gap-1.5 rounded-lg bg-zinc-100 px-3 py-1.5 text-xs font-semibold text-zinc-600 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-brand-500/10 dark:hover:text-brand-400">
                    <x-icon name="crown" class="size-3.5" />
                    مدیریت طرح‌ها
                </a>
                <a href="{{ route('admin.subscriptions.orders') }}" wire:navigate
                   class="inline-flex items-center gap-1.5 rounded-lg bg-zinc-100 px-3 py-1.5 text-xs font-semibold text-zinc-600 transition-colors hover:bg-amber-50 hover:text-amber-600 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-amber-500/10 dark:hover:text-amber-400">
                    <x-icon name="badge-check" class="size-3.5" />
                    درخواست‌ها
                    @if($this->planStats['pending_orders'] > 0)
                        <span class="rounded-full bg-amber-500/15 px-1.5 py-0.5 text-[11px] font-bold tabular-nums text-amber-600 dark:text-amber-400">{{ fa_num($this->planStats['pending_orders']) }}</span>
                    @endif
                </a>
            </div>
        </div>

        {{-- plan mini stats --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat label="سفارش‌های تأییدشده" :value="fa_num($this->planStats['approved_orders'])" icon="check-circle" variant="success"
                    :hint="fa_num($this->planStats['total_orders']) . ' سفارش ثبت‌شده'" href="{{ route('admin.subscriptions.orders') }}" />
            <x-stat label="در انتظار تأیید" :value="fa_num($this->planStats['pending_orders'])" icon="hourglass" variant="warning"
                    :hint="fa_num($this->planStats['rejected_orders']) . ' سفارش ردشده'" href="{{ route('admin.subscriptions.orders') }}" />
            <x-stat label="درآمد طرح‌ها" :value="fa_num(money($this->planStats['plan_revenue'], false))" icon="wallet" variant="primary"
                    hint="تومان · سفارش‌های پرداخت‌شده" />
            <x-stat label="اشتراک فعال طرح‌ها" :value="fa_num($this->planStats['active_plan_subscriptions'])" icon="ticket" variant="neutral"
                    :hint="fa_num($this->planStats['active_plans']) . ' طرح فعال'" href="{{ route('admin.plans.index') }}" />
        </div>

        {{-- top plans table --}}
        <div class="card overflow-hidden">
            @if(count($this->topPlans))
                <div class="flex items-center justify-between border-b border-zinc-100 px-5 py-3 dark:border-zinc-800">
                    <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">
                        <span class="font-bold tabular-nums text-zinc-700 dark:text-zinc-200">{{ fa_num(count($this->topPlans)) }}</span>
                        طرح برتر بر اساس تعداد سفارش
                    </p>
                    <x-icon name="crown" class="size-4 text-zinc-300 dark:text-zinc-600" />
                </div>
                <div class="table-wrap ring-0">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>طرح</th>
                                <th class="hidden sm:table-cell">مدت</th>
                                <th>سفارش‌ها</th>
                                <th>درآمد</th>
                                <th class="hidden md:table-cell">پکیج‌ها</th>
                                <th class="hidden lg:table-cell">اشتراک فعال</th>
                                <th>وضعیت</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->topPlans as $plan)
                                <tr wire:key="dash-plan-{{ $plan->id }}">
                                    <td>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ $plan->name }}</p>
                                            @if($plan->is_free)
                                                <x-badge variant="info" icon="gift">رایگان</x-badge>
                                            @endif
                                            @if($plan->is_one_time)
                                                <x-badge variant="warning" icon="ticket">یک‌بارمصرف</x-badge>
                                            @endif
                                        </div>
                                        <p class="mt-0.5 hidden text-xs text-zinc-400 sm:block" dir="ltr">{{ $plan->slug }}</p>
                                    </td>
                                    <td class="hidden text-sm text-zinc-600 dark:text-zinc-300 sm:table-cell">{{ $plan->duration_label }}</td>
                                    <td>
                                        @if($plan->orders_count)
                                            <span class="text-sm font-bold tabular-nums text-zinc-700 dark:text-zinc-200">{{ fa_num($plan->orders_count) }}</span>
                                            <span class="text-xs text-zinc-400">سفارش</span>
                                        @else
                                            <span class="text-sm text-zinc-400">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($plan->revenue)
                                            <span class="text-sm font-bold tabular-nums text-brand-600 dark:text-brand-400">{{ fa_num(money($plan->revenue, false)) }}</span>
                                            <span class="text-[10px] text-zinc-400">تومان</span>
                                        @else
                                            <span class="text-sm text-zinc-400">—</span>
                                        @endif
                                    </td>
                                    <td class="hidden md:table-cell">
                                        <span class="text-sm font-semibold tabular-nums text-zinc-600 dark:text-zinc-300">{{ fa_num($plan->packages_count) }}</span>
                                        <span class="text-xs text-zinc-400">پکیج</span>
                                    </td>
                                    <td class="hidden tabular-nums lg:table-cell {{ $plan->active_subs_count ? 'text-sm font-bold text-zinc-700 dark:text-zinc-200' : 'text-sm text-zinc-400' }}">
                                        {{ $plan->active_subs_count ? fa_num($plan->active_subs_count) : '—' }}
                                    </td>
                                    <td>
                                        @if($plan->is_active)
                                            <x-badge variant="success" icon="check-circle">فعال</x-badge>
                                        @else
                                            <x-badge variant="danger" icon="x-circle">غیرفعال</x-badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-empty icon="crown" title="طرحی ثبت نشده" description="پس از تعریف طرح‌های اشتراک، آمار فروش آن‌ها اینجا نمایش داده می‌شود." />
            @endif
        </div>
    </div>

    {{-- recent customers + recent updates --}}
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <x-card title="آخرین مشتریان ثبت‌شده">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>مشتری</th>
                            <th>وضعیت</th>
                            <th class="hidden sm:table-cell">تلفن</th>
                            <th>تاریخ عضویت</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->recentCustomers as $customer)
                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <x-avatar :name="$customer->name" size="sm" />
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-zinc-800 dark:text-zinc-200">{{ $customer->name }}</p>
                                            <p class="truncate text-xs text-zinc-500">{{ $customer->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <x-badge variant="{{ $customer->status === 'active' ? 'success' : 'danger' }}">
                                        {{ $customer->status === 'active' ? 'فعال' : 'غیرفعال' }}
                                    </x-badge>
                                </td>
                                <td class="hidden text-sm tabular-nums text-zinc-600 dark:text-zinc-400 sm:table-cell" dir="ltr">{{ $customer->phone ?: '—' }}</td>
                                <td class="text-sm text-zinc-500">{{ verta_date($customer->created_at) }}</td>
                                <td>
                                    <a href="{{ route('admin.customers.index') }}?search={{ urlencode($customer->name) }}" wire:navigate
                                       class="text-zinc-400 transition-colors hover:text-brand-600" aria-label="مشاهده">
                                        <x-icon name="chevron-left" class="size-4" />
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-empty icon="users" title="مشتری‌ای ثبت نشده" class="py-8" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        {{-- recent updates --}}
        <x-card title="آخرین آپدیت‌ها" subtitle="آخرین نسخه‌های فعال پروژه‌ها">
            <div class="-my-2 divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse($this->recentUpdates as $update)
                    @php($statusVariant = match($update->status) {
                        'active' => 'success',
                        'draft' => 'warning',
                        default => 'neutral',
                    })
                    @php($statusLabel = \App\Models\Update::getStatuses()[$update->status] ?? $update->status)
                    <div class="flex items-center gap-3 py-2.5">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-brand-500/10 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                            <x-icon name="git-branch" class="size-4.5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-zinc-800 dark:text-zinc-200">{{ $update->title }}</p>
                            <p class="truncate text-xs text-zinc-500">{{ $update->project?->name ?? '—' }} · {{ verta_date($update->release_date) }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1.5">
                            <x-badge variant="primary"><span dir="ltr" class="font-mono">{{ $update->version }}</span></x-badge>
                            <x-badge :variant="$statusVariant">{{ $statusLabel }}</x-badge>
                        </div>
                    </div>
                @empty
                    <x-empty icon="git-branch" title="هنوز آپدیتی منتشر نشده" description="آپدیت‌های فعال پروژه‌ها اینجا نمایش داده می‌شوند." class="py-8" />
                @endforelse
            </div>
            @if($this->recentUpdates->isNotEmpty())
                <a href="{{ route('admin.updates.index') }}" wire:navigate
                   class="mt-3 flex items-center justify-center gap-1.5 rounded-xl bg-zinc-50 py-2.5 text-xs font-semibold text-zinc-600 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:bg-zinc-800/60 dark:text-zinc-300 dark:hover:bg-brand-500/10 dark:hover:text-brand-400">
                    مشاهده همه آپدیت‌ها
                    <x-icon name="chevron-left" class="size-3.5" />
                </a>
            @endif
        </x-card>
    </div>
</div>
