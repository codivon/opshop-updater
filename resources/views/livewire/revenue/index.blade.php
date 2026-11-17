{{--
    Task 85 — صفحهٔ «درآمد» پنل مدیریت:
    درآمد کلی و جزئی همهٔ فروش‌ها به‌صورت تعداد و نمودار، با فیلتر بازهٔ زمانی.
--}}
<div class="space-y-6" x-data>

    {{-- ============ سربرگ + فیلتر بازه ============ --}}
    <div class="card p-4 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="flex items-center gap-2 text-xl font-black text-zinc-900 sm:text-2xl dark:text-zinc-50">
                    <x-icon name="wallet" class="size-6 text-brand-600" />
                    درآمد
                </h1>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                    گزارش کامل درآمد همهٔ فروش‌ها — پکیج، محصول، قالب و اشتراک · بازهٔ فعال:
                    <span class="font-bold text-brand-600 dark:text-brand-400">{{ $this->range[2] }}</span>
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-1.5">
                <button type="button" wire:click="setPeriod('today')"
                        class="cursor-pointer rounded-full px-3.5 py-1.5 text-xs font-bold transition-colors @if($period === 'today') bg-brand-600 text-white shadow-sm @else bg-zinc-100 text-zinc-600 hover:bg-brand-50 hover:text-brand-600 dark:bg-zinc-800 dark:text-zinc-300 @endif">امروز</button>
                <button type="button" wire:click="setPeriod('week')"
                        class="cursor-pointer rounded-full px-3.5 py-1.5 text-xs font-bold transition-colors @if($period === 'week') bg-brand-600 text-white shadow-sm @else bg-zinc-100 text-zinc-600 hover:bg-brand-50 hover:text-brand-600 dark:bg-zinc-800 dark:text-zinc-300 @endif">۷ روز</button>
                <button type="button" wire:click="setPeriod('month')"
                        class="cursor-pointer rounded-full px-3.5 py-1.5 text-xs font-bold transition-colors @if($period === 'month') bg-brand-600 text-white shadow-sm @else bg-zinc-100 text-zinc-600 hover:bg-brand-50 hover:text-brand-600 dark:bg-zinc-800 dark:text-zinc-300 @endif">این ماه</button>
                <button type="button" wire:click="setPeriod('year')"
                        class="cursor-pointer rounded-full px-3.5 py-1.5 text-xs font-bold transition-colors @if($period === 'year') bg-brand-600 text-white shadow-sm @else bg-zinc-100 text-zinc-600 hover:bg-brand-50 hover:text-brand-600 dark:bg-zinc-800 dark:text-zinc-300 @endif">یک سال</button>
                <button type="button" wire:click="setPeriod('all')"
                        class="cursor-pointer rounded-full px-3.5 py-1.5 text-xs font-bold transition-colors @if($period === 'all') bg-brand-600 text-white shadow-sm @else bg-zinc-100 text-zinc-600 hover:bg-brand-50 hover:text-brand-600 dark:bg-zinc-800 dark:text-zinc-300 @endif">همه</button>
                <button type="button" wire:click="setPeriod('custom')"
                        class="cursor-pointer rounded-full px-3.5 py-1.5 text-xs font-bold transition-colors @if($period === 'custom') bg-brand-600 text-white shadow-sm @else bg-zinc-100 text-zinc-600 hover:bg-brand-50 hover:text-brand-600 dark:bg-zinc-800 dark:text-zinc-300 @endif">بازهٔ شمسی</button>
            </div>
        </div>

        {{-- بازهٔ دلخواه شمسی --}}
        @if($period === 'custom')
            <div class="mt-4 flex flex-wrap items-end gap-3 border-t border-zinc-100 pt-4 dark:border-zinc-800" wire:key="revenue-custom-range">
                <x-field label="از تاریخ (شمسی)" for="revFrom" hint="مثلاً ۱۴۰۴/۰۱/۰۱">
                    <x-input id="revFrom" wire:model.live.debounce.500ms="from" dir="ltr" class="w-40 text-center font-mono" placeholder="1404/01/01" />
                </x-field>
                <x-field label="تا تاریخ (شمسی)" for="revTo" hint="مثلاً ۱۴۰۴/۰۹/۰۱">
                    <x-input id="revTo" wire:model.live.debounce.500ms="to" dir="ltr" class="w-40 text-center font-mono" placeholder="1404/09/01" />
                </x-field>
                <x-btn variant="secondary" size="sm" icon="refresh-cw" wire:click="resetFilters">بازنشانی</x-btn>
            </div>
        @endif

        {{-- KPI ها --}}
        <div class="mt-4 grid grid-cols-2 gap-3 border-t border-zinc-100 pt-4 sm:grid-cols-3 lg:grid-cols-6 dark:border-zinc-800">
            <x-stat label="درآمد بازه" :value="money($this->kpis['total_revenue'], false)" icon="wallet" variant="primary" hint="تومان" />
            <x-stat label="فروش موفق" :value="fa_num($this->kpis['total_count'])" icon="shopping-bag" variant="info" hint="تراکنش پرداخت‌شده" />
            <x-stat label="میانگین هر فروش" :value="money($this->kpis['avg_order'], false)" icon="calculator" variant="violet" hint="تومان" />
            <x-stat label="درآمد امروز" :value="money($this->kpis['today'], false)" icon="sun" variant="warning" hint="تومان" />
            <x-stat label="درآمد این ماه" :value="money($this->kpis['month'], false)" icon="calendar" variant="primary" hint="تومان" />
            <x-stat label="تخفیف اعمال‌شده" :value="money($this->kpis['total_discount'], false)" icon="percent" variant="danger" hint="تومان" />
        </div>
    </div>

    {{-- ============ نمودارها ============ --}}
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <div class="card p-4 sm:p-6">
            <x-chart :title="$this->monthlyChart['title']" :series="$this->monthlyChart['series']" color="brand" secondary-format="count" :height="200" />
        </div>
        <div class="card p-4 sm:p-6">
            <x-chart :title="$this->dailyChart['title']" :series="$this->dailyChart['series']" color="teal" secondary-format="count" :height="200" />
        </div>
    </div>

    {{-- ============ تفکیک منابع درآمد ============ --}}
    <div class="card overflow-hidden">
        <div class="flex items-center justify-between p-4 sm:px-6">
            <h2 class="flex items-center gap-2 text-sm font-black text-zinc-800 dark:text-zinc-100">
                <x-icon name="pie-chart" class="size-4 text-brand-600" />
                تفکیک منابع درآمد
            </h2>
            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $this->range[2] }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-sm">
                <thead>
                    <tr class="border-y border-zinc-100 bg-zinc-50/60 text-xs text-zinc-500 dark:border-zinc-800 dark:bg-zinc-900/40 dark:text-zinc-400">
                        <th class="px-4 py-2.5 text-start font-bold sm:px-6">منبع</th>
                        <th class="px-4 py-2.5 text-center font-bold">فروش موفق</th>
                        <th class="px-4 py-2.5 text-center font-bold">در انتظار</th>
                        <th class="px-4 py-2.5 text-center font-bold">ناموفق</th>
                        <th class="px-4 py-2.5 text-center font-bold">میانگین فروش</th>
                        <th class="px-4 py-2.5 text-center font-bold">تخفیف</th>
                        <th class="px-4 py-2.5 text-center font-bold">سهم</th>
                        <th class="px-4 py-2.5 text-end font-bold sm:px-6">درآمد (تومان)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach($this->breakdown as $row)
                        <tr class="transition-colors hover:bg-brand-50/40 dark:hover:bg-brand-500/5">
                            <td class="px-4 py-3 font-bold text-zinc-800 sm:px-6 dark:text-zinc-100">{{ $row['label'] }}</td>
                            <td class="px-4 py-3 text-center tabular-nums">{{ fa_num($row['count']) }}</td>
                            <td class="px-4 py-3 text-center tabular-nums text-amber-600 dark:text-amber-400">{{ fa_num($row['pending']) }}</td>
                            <td class="px-4 py-3 text-center tabular-nums text-rose-600 dark:text-rose-400">{{ fa_num($row['failed']) }}</td>
                            <td class="px-4 py-3 text-center tabular-nums text-zinc-500 dark:text-zinc-400">{{ money($row['avg'], false) }}</td>
                            <td class="px-4 py-3 text-center tabular-nums text-zinc-500 dark:text-zinc-400">{{ money($row['discount'], false) }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="h-1.5 w-14 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                        <span class="block h-full rounded-full bg-brand-500" style="width: {{ $row['share'] }}%"></span>
                                    </span>
                                    <span class="text-xs tabular-nums text-zinc-500 dark:text-zinc-400">٪{{ fa_num($row['share']) }}</span>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-end font-black tabular-nums text-brand-600 sm:px-6 dark:text-brand-400">{{ money($row['revenue'], false) }}</td>
                        </tr>
                    @endforeach
                    @php($totalRevenue = array_sum(array_column($this->breakdown, 'revenue')))
                    <tr class="border-t border-zinc-200 bg-zinc-50/80 font-black dark:border-zinc-700 dark:bg-zinc-900/60">
                        <td class="px-4 py-3 sm:px-6">جمع کل</td>
                        <td class="px-4 py-3 text-center tabular-nums">{{ fa_num(array_sum(array_column($this->breakdown, 'count'))) }}</td>
                        <td class="px-4 py-3 text-center tabular-nums">{{ fa_num(array_sum(array_column($this->breakdown, 'pending'))) }}</td>
                        <td class="px-4 py-3 text-center tabular-nums">{{ fa_num(array_sum(array_column($this->breakdown, 'failed'))) }}</td>
                        <td colspan="3"></td>
                        <td class="px-4 py-3 text-end tabular-nums sm:px-6">{{ money($totalRevenue, false) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- ============ پرفروش‌ترین‌ها + درگاه‌ها ============ --}}
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="flex items-center justify-between p-4 sm:px-6">
                <h2 class="flex items-center gap-2 text-sm font-black text-zinc-800 dark:text-zinc-100">
                    <x-icon name="trophy" class="size-4 text-amber-500" />
                    پرفروش‌ترین‌ها
                </h2>
                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $this->range[2] }}</span>
            </div>
            <div class="max-h-96 overflow-y-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-zinc-50/95 backdrop-blur dark:bg-zinc-900/95">
                        <tr class="border-y border-zinc-100 text-xs text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
                            <th class="px-4 py-2.5 text-start font-bold sm:px-6">آیتم</th>
                            <th class="px-4 py-2.5 text-center font-bold">نوع</th>
                            <th class="px-4 py-2.5 text-center font-bold">تعداد</th>
                            <th class="px-4 py-2.5 text-end font-bold sm:px-6">درآمد (تومان)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse($this->topSellers as $item)
                            <tr class="transition-colors hover:bg-brand-50/40 dark:hover:bg-brand-500/5">
                                <td class="max-w-56 truncate px-4 py-3 font-bold text-zinc-800 sm:px-6 dark:text-zinc-100" title="{{ $item['name'] }}">{{ $item['name'] }}</td>
                                <td class="px-4 py-3 text-center">
                                    <x-badge variant="neutral">{{ $item['type'] }}</x-badge>
                                </td>
                                <td class="px-4 py-3 text-center tabular-nums">{{ fa_num($item['count']) }}</td>
                                <td class="px-4 py-3 text-end font-bold tabular-nums sm:px-6">{{ money($item['revenue'], false) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-8"><x-empty icon="shopping-bag" title="فروشی در این بازه ثبت نشده" description="با انتخاب بازهٔ دیگر یا پس از اولین فروش، آمار این‌جا نمایش داده می‌شود." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="flex items-center justify-between p-4 sm:px-6">
                <h2 class="flex items-center gap-2 text-sm font-black text-zinc-800 dark:text-zinc-100">
                    <x-icon name="credit-card" class="size-4 text-brand-600" />
                    درگاه‌های پرداخت
                </h2>
                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $this->range[2] }}</span>
            </div>
            <div class="max-h-96 overflow-y-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-zinc-50/95 backdrop-blur dark:bg-zinc-900/95">
                        <tr class="border-y border-zinc-100 text-xs text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
                            <th class="px-4 py-2.5 text-start font-bold sm:px-6">درگاه</th>
                            <th class="px-4 py-2.5 text-center font-bold">تعداد</th>
                            <th class="px-4 py-2.5 text-center font-bold">سهم</th>
                            <th class="px-4 py-2.5 text-end font-bold sm:px-6">درآمد (تومان)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse($this->gateways as $gw)
                            <tr class="transition-colors hover:bg-brand-50/40 dark:hover:bg-brand-500/5">
                                <td class="px-4 py-3 font-bold text-zinc-800 sm:px-6 dark:text-zinc-100" dir="ltr">{{ $gw['name'] }}</td>
                                <td class="px-4 py-3 text-center tabular-nums">{{ fa_num($gw['count']) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="h-1.5 w-14 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                            <span class="block h-full rounded-full bg-teal-500" style="width: {{ $gw['share'] }}%"></span>
                                        </span>
                                        <span class="text-xs tabular-nums text-zinc-500 dark:text-zinc-400">٪{{ fa_num($gw['share']) }}</span>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-end font-bold tabular-nums sm:px-6">{{ money($gw['revenue'], false) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-8"><x-empty icon="credit-card" title="پرداختی در این بازه ثبت نشده" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
