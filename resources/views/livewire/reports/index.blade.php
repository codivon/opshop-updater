<div class="space-y-6">
    {{-- header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-black text-zinc-900 dark:text-zinc-50">گزارش‌ها</h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">تحلیل جامع مشتریان، آپدیت‌ها و فروش — به‌روز تا {{ verta_date(now(), 'l d F Y') }}</p>
        </div>
        <x-btn variant="secondary" icon="refresh-cw" wire:click="$refresh">به‌روزرسانی گزارش‌ها</x-btn>
    </div>

    @php($tabs = [
        ['id' => 'overview', 'label' => 'نمای کلی', 'icon' => 'bar-chart'],
        ['id' => 'customers', 'label' => 'مشتریان', 'icon' => 'users'],
        ['id' => 'updates', 'label' => 'آپدیت‌ها', 'icon' => 'git-branch'],
        ['id' => 'sales', 'label' => 'فروش', 'icon' => 'wallet'],
        ['id' => 'plans', 'label' => 'طرح‌های اشتراک', 'icon' => 'crown'],
    ])
    <x-tabs :tabs="$tabs">
        <x-slot:overview>
            <div class="space-y-6">
                {{-- stat cards --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($this->overviewStats as $stat)
                        <x-stat :label="$stat['label']" :value="fa_num($stat['value'])" :icon="$stat['icon']"
                                :variant="$stat['variant']" :hint="$stat['hint']" :href="$stat['href']" />
                    @endforeach
                </div>

                {{-- customers chart + subscription pills --}}
                <x-card padding="p-5">
                    <x-chart :title="$this->customerChart['title']" :series="$this->customerChart['series']" color="brand" :height="200" />

                    <div class="mt-6 flex flex-wrap items-center gap-2 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                        <span class="me-1 flex items-center gap-1.5 text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                            <x-icon name="ticket" class="size-3.5" />
                            وضعیت اشتراک‌ها:
                        </span>
                        <x-badge variant="success" icon="check-circle">فعال {{ fa_num($this->subscriptionBreakdown['active']) }}</x-badge>
                        <x-badge variant="danger" icon="alert-circle">منقضی شده {{ fa_num($this->subscriptionBreakdown['expired']) }}</x-badge>
                        <x-badge variant="neutral" icon="clock">غیرفعال {{ fa_num($this->subscriptionBreakdown['inactive']) }}</x-badge>
                    </div>
                </x-card>

                {{-- key metrics table --}}
                <x-card title="جدول شاخص‌های کلیدی" subtitle="خلاصه وضعیت کل سیستم">
                    <div class="table-wrap ring-0">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>شاخص</th>
                                    <th class="text-end">مقدار</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($this->summaryMetrics as $group)
                                    <tr wire:key="metric-group-{{ $loop->index }}">
                                        <td colspan="2" class="pt-5 pb-1.5">
                                            <span class="inline-flex items-center gap-2 rounded-lg bg-zinc-100 px-2.5 py-1.5 text-xs font-bold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                                <x-icon :name="$group['icon']" class="size-4 text-brand-600 dark:text-brand-400" />
                                                {{ $group['title'] }}
                                            </span>
                                        </td>
                                    </tr>
                                    @foreach($group['rows'] as $row)
                                        <tr wire:key="metric-{{ $loop->parent->index }}-{{ $loop->index }}">
                                            <td class="text-sm text-zinc-600 dark:text-zinc-300">{{ $row['label'] }}</td>
                                            <td class="text-end font-bold tabular-nums text-zinc-800 dark:text-zinc-100">{{ $row['value'] }}</td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            </div>
            </x-slot:overview>
        <x-slot:customers>
            <div class="space-y-6">
                {{-- date range filters --}}
                <div class="card flex flex-col gap-3 p-4 sm:flex-row sm:items-end">
                    <x-field label="از تاریخ عضویت" class="sm:w-44">
                        <x-input type="date" wire:model.live="from" dir="ltr" icon="calendar" />
                    </x-field>
                    <x-field label="تا تاریخ" class="sm:w-44">
                        <x-input type="date" wire:model.live="to" dir="ltr" icon="calendar" />
                    </x-field>
                    @if($from || $to)
                        <x-btn variant="ghost" icon="x" wire:click="resetFilters">حذف فیلترها</x-btn>
                    @endif
                    <div class="hidden flex-1 sm:block"></div>
                    <div class="flex items-center gap-2 text-xs text-zinc-400">
                        <x-icon name="calendar" class="size-3.5" />
                        بازه بر اساس تاریخ عضویت (میلادی)
                    </div>
                    <div wire:loading wire:target="from, to" class="flex items-center gap-2 text-xs text-brand-600 dark:text-brand-400">
                        <x-icon name="loader" class="size-4 animate-spin" />
                        در حال فیلتر…
                    </div>
                </div>

                {{-- customers table --}}
                <div class="card overflow-hidden">
                    @if($this->customersReport->count())
                        <div class="flex items-center justify-between border-b border-zinc-100 px-5 py-3 dark:border-zinc-800">
                            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">
                                <span class="font-bold tabular-nums text-zinc-700 dark:text-zinc-200">{{ fa_num($this->customersReport->total()) }}</span>
                                مشتری در بازه انتخابی
                            </p>
                            <x-icon name="users" class="size-4 text-zinc-300 dark:text-zinc-600" />
                        </div>
                        <div class="table-wrap ring-0">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>مشتری</th>
                                        <th>اشتراک‌ها</th>
                                        <th>مجموع پرداخت</th>
                                        <th class="hidden sm:table-cell">تاریخ عضویت</th>
                                        <th>وضعیت</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($this->customersReport as $customer)
                                        <tr wire:key="report-customer-{{ $customer->id }}">
                                            <td>
                                                <div class="flex items-center gap-3">
                                                    <x-avatar :name="$customer->name" size="sm" />
                                                    <div class="min-w-0">
                                                        <p class="truncate text-sm font-semibold text-zinc-800 dark:text-zinc-200">{{ $customer->name }}</p>
                                                        <p class="truncate text-xs text-zinc-500">{{ $customer->email }}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="tabular-nums text-zinc-600 dark:text-zinc-300">{{ fa_num($customer->subscriptions_count) }}</td>
                                            <td class="font-semibold tabular-nums text-zinc-700 dark:text-zinc-200">
                                                @if($customer->subscriptions_spent + $customer->purchases_spent > 0)
                                                    {{ money($customer->subscriptions_spent + $customer->purchases_spent, false) }}
                                                    <span class="text-[10px] font-normal text-zinc-400">تومان</span>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="hidden text-sm text-zinc-500 sm:table-cell">{{ verta_date($customer->created_at) }}</td>
                                            <td>
                                                <x-badge variant="{{ $customer->status === 'active' ? 'success' : 'danger' }}">
                                                    {{ $customer->status === 'active' ? 'فعال' : 'غیرفعال' }}
                                                </x-badge>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="border-t border-zinc-100 px-4 py-3 dark:border-zinc-800">
                            {{ $this->customersReport->links() }}
                        </div>
                    @else
                        <x-empty icon="users" title="مشتری‌ای در این بازه یافت نشد" description="بازه تاریخ را تغییر دهید یا فیلترها را حذف کنید.">
                            @if($from || $to)
                                <x-btn variant="soft" icon="x" wire:click="resetFilters">حذف فیلترها</x-btn>
                            @endif
                        </x-empty>
                    @endif
                </div>
            </div>
            </x-slot:customers>
        <x-slot:updates>
            <div class="space-y-6">
                {{-- filters --}}
                <div class="card flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                    <x-select wire:model.live="project" :options="$this->projects->pluck('name', 'id')" placeholder="همه پروژه‌ها" class="sm:w-48" />
                    <x-select wire:model.live="status" :options="\App\Models\Update::getStatuses()" placeholder="همه وضعیت‌ها" class="sm:w-44" />
                    @if($project || $status)
                        <x-btn variant="ghost" icon="x" wire:click="resetFilters">حذف فیلترها</x-btn>
                    @endif
                    <div class="hidden flex-1 sm:block"></div>
                    <div wire:loading wire:target="project, status" class="flex items-center gap-2 text-xs text-brand-600 dark:text-brand-400">
                        <x-icon name="loader" class="size-4 animate-spin" />
                        در حال فیلتر…
                    </div>
                </div>

                {{-- updates table --}}
                <div class="card overflow-hidden">
                    @if($this->updatesReport->count())
                        <div class="flex items-center justify-between border-b border-zinc-100 px-5 py-3 dark:border-zinc-800">
                            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">
                                <span class="font-bold tabular-nums text-zinc-700 dark:text-zinc-200">{{ fa_num($this->updatesReport->total()) }}</span>
                                آپدیت
                                @if($project)
                                    <span class="text-zinc-400">در پروژه «{{ $this->projects->firstWhere('id', (int) $project)?->name }}»</span>
                                @endif
                            </p>
                            <x-icon name="git-branch" class="size-4 text-zinc-300 dark:text-zinc-600" />
                        </div>
                        <div class="table-wrap ring-0">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>عنوان</th>
                                        <th class="hidden md:table-cell">پروژه</th>
                                        <th>نسخه</th>
                                        <th class="hidden lg:table-cell">نوع</th>
                                        <th>وضعیت</th>
                                        <th class="hidden sm:table-cell">تاریخ انتشار</th>
                                        <th class="hidden lg:table-cell">حجم فایل</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($this->updatesReport as $update)
                                        @php($statusVariant = match($update->status) {
                                            'active' => 'success',
                                            'draft' => 'warning',
                                            default => 'neutral',
                                        })
                                        @php($statusLabel = \App\Models\Update::getStatuses()[$update->status] ?? $update->status)
                                        @php($typeVariant = match($update->type) {
                                            'major' => 'primary',
                                            'minor' => 'info',
                                            default => 'neutral',
                                        })
                                        @php($typeLabel = match($update->type) {
                                            'major' => 'اصلی',
                                            'minor' => 'فرعی',
                                            default => 'اصلاحی',
                                        })
                                        <tr wire:key="report-update-{{ $update->id }}">
                                            <td>
                                                <div class="flex items-center gap-3">
                                                    <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                                                        <x-icon name="git-branch" class="size-4" />
                                                    </div>
                                                    <p class="max-w-56 truncate text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ $update->title }}</p>
                                                </div>
                                            </td>
                                            <td class="hidden text-sm text-zinc-600 dark:text-zinc-300 md:table-cell">{{ $update->project?->name ?? '—' }}</td>
                                            <td><x-badge variant="primary"><span dir="ltr" class="font-mono">{{ $update->version }}</span></x-badge></td>
                                            <td class="hidden lg:table-cell"><x-badge :variant="$typeVariant">{{ $typeLabel }}</x-badge></td>
                                            <td><x-badge :variant="$statusVariant">{{ $statusLabel }}</x-badge></td>
                                            <td class="hidden text-sm text-zinc-500 sm:table-cell">{{ verta_date($update->release_date) }}</td>
                                            <td class="hidden text-sm tabular-nums text-zinc-500 lg:table-cell" dir="ltr">{{ $update->file_size ?: '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="border-t border-zinc-100 px-4 py-3 dark:border-zinc-800">
                            {{ $this->updatesReport->links() }}
                        </div>
                    @else
                        <x-empty icon="git-branch" title="آپدیتی یافت نشد" description="فیلترهای پروژه یا وضعیت را تغییر دهید.">
                            @if($project || $status)
                                <x-btn variant="soft" icon="x" wire:click="resetFilters">حذف فیلترها</x-btn>
                            @endif
                        </x-empty>
                    @endif
                </div>
            </div>
            </x-slot:updates>
        <x-slot:sales>
            <div class="space-y-6">

                {{-- ================= فیلتر بازه زمانی ================= --}}
                <div class="card p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="me-1 flex items-center gap-1.5 text-xs font-bold text-zinc-500 dark:text-zinc-400">
                            <x-icon name="calendar" class="size-3.5" />
                            بازه زمانی:
                        </span>
                        <button type="button" wire:click="setPeriod('today')"
                                class="cursor-pointer rounded-full px-3.5 py-1.5 text-xs font-bold transition-colors @if($period === 'today') bg-brand-600 text-white shadow-sm @else bg-zinc-100 text-zinc-600 hover:bg-brand-50 hover:text-brand-600 dark:bg-zinc-800 dark:text-zinc-300 @endif">روزانه</button>
                        <button type="button" wire:click="setPeriod('week')"
                                class="cursor-pointer rounded-full px-3.5 py-1.5 text-xs font-bold transition-colors @if($period === 'week') bg-brand-600 text-white shadow-sm @else bg-zinc-100 text-zinc-600 hover:bg-brand-50 hover:text-brand-600 dark:bg-zinc-800 dark:text-zinc-300 @endif">هفتگی</button>
                        <button type="button" wire:click="setPeriod('month')"
                                class="cursor-pointer rounded-full px-3.5 py-1.5 text-xs font-bold transition-colors @if($period === 'month') bg-brand-600 text-white shadow-sm @else bg-zinc-100 text-zinc-600 hover:bg-brand-50 hover:text-brand-600 dark:bg-zinc-800 dark:text-zinc-300 @endif">ماهانه</button>
                        <button type="button" wire:click="setPeriod('year')"
                                class="cursor-pointer rounded-full px-3.5 py-1.5 text-xs font-bold transition-colors @if($period === 'year') bg-brand-600 text-white shadow-sm @else bg-zinc-100 text-zinc-600 hover:bg-brand-50 hover:text-brand-600 dark:bg-zinc-800 dark:text-zinc-300 @endif">سالانه</button>
                        <button type="button" wire:click="setPeriod('all')"
                                class="cursor-pointer rounded-full px-3.5 py-1.5 text-xs font-bold transition-colors @if($period === 'all') bg-brand-600 text-white shadow-sm @else bg-zinc-100 text-zinc-600 hover:bg-brand-50 hover:text-brand-600 dark:bg-zinc-800 dark:text-zinc-300 @endif">همه</button>
                        <button type="button" wire:click="setPeriod('custom')"
                                class="cursor-pointer rounded-full px-3.5 py-1.5 text-xs font-bold transition-colors @if($period === 'custom') bg-brand-600 text-white shadow-sm @else bg-zinc-100 text-zinc-600 hover:bg-brand-50 hover:text-brand-600 dark:bg-zinc-800 dark:text-zinc-300 @endif">بازه شمسی</button>

                        <div class="ms-auto"></div>

                        <x-btn variant="secondary" size="sm" icon="download" wire:click="exportSalesCsv">
                            خروجی CSV
                        </x-btn>
                    </div>

                    {{-- بازه دلخواه شمسی --}}
                    @if($period === 'custom')
                        <div class="mt-3 flex flex-wrap items-end gap-3 border-t border-zinc-100 pt-3 dark:border-zinc-800" wire:key="sales-custom-range">
                            <x-field label="از تاریخ (شمسی)" for="salesFrom" hint="مثلاً ۱۴۰۴/۰۱/۰۱">
                                <x-input id="salesFrom" wire:model.live.debounce.500ms="salesFrom" dir="ltr" class="w-40 text-center font-mono" placeholder="1404/01/01" />
                            </x-field>
                            <x-field label="تا تاریخ (شمسی)" for="salesTo" hint="مثلاً ۱۴۰۴/۰۱/۳۱">
                                <x-input id="salesTo" wire:model.live.debounce.500ms="salesTo" dir="ltr" class="w-40 text-center font-mono" placeholder="1404/12/29" />
                            </x-field>
                            <x-btn variant="ghost" size="sm" icon="x" wire:click="resetSalesFilters">حذف فیلتر</x-btn>
                            <p class="pb-2 text-[11px] text-zinc-400">تاریخ‌ها را می‌توانید شمسی (۱۴۰۴/۰۷/۰۱) یا میلادی (2025-09-23) وارد کنید.</p>
                        </div>
                    @endif
                </div>

                {{-- ================= KPI بازه ================= --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <x-stat label="درآمد بازه ({{ $this->salesStats['label'] }})" :value="money($this->salesStats['revenue'], false)" icon="wallet" variant="primary"
                            :hint="fa_num($this->salesStats['discount']) . ' تومان تخفیف داده‌شده'" />
                    <x-stat label="تعداد سفارش" :value="fa_num($this->salesStats['count'])" icon="receipt" variant="success"
                            :hint="fa_num($this->salesStats['discount_used']) . ' سفارش با کد تخفیف'" />
                    <x-stat label="میانگین هر سفارش" :value="money($this->salesStats['avg_order'], false)" icon="bar-chart" variant="info"
                            hint="تومان" />
                    @if($this->salesStats['growth'] !== null)
                        <x-stat label="رشد نسبت به دوره قبل" :value="($this->salesStats['growth'] >= 0 ? '+' : '') . fa_num($this->salesStats['growth']) . '٪'" icon="{{ $this->salesStats['growth'] >= 0 ? 'trending-up' : 'trending-down' }}"
                                :variant="$this->salesStats['growth'] >= 0 ? 'success' : 'danger'"
                                :hint="'دوره قبل: ' . money($this->salesStats['prev_revenue'] ?? 0, false) . ' تومان'" />
                    @else
                        <x-stat label="رشد نسبت به دوره قبل" value="—" icon="trending-up" variant="neutral" hint="برای «همه زمان‌ها» محاسبه نمی‌شود" />
                    @endif
                </div>

                {{-- ================= نمودار + خلاصه ================= --}}
                <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                    {{-- chart of the period --}}
                    <x-card class="xl:col-span-2" padding="p-5">
                        <x-chart :title="$this->salesStats['chart']['title']" :series="$this->salesStats['chart']['series']" color="teal" :height="200" />
                    </x-card>

                    {{-- تفکیک نوع فروش --}}
                    <x-card title="تفکیک فروش" :subtitle="$this->salesStats['label']">
                        <div class="space-y-3">
                            @foreach($this->salesStats['by_type'] as $type => $info)
                                <div class="flex items-center justify-between gap-3 rounded-xl bg-zinc-50 px-4 py-3 ring-1 ring-zinc-200/70 dark:bg-zinc-800/50 dark:ring-zinc-700/50">
                                    <div class="flex items-center gap-2.5">
                                        <span class="flex size-8 items-center justify-center rounded-lg bg-white ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-700">
                                            <x-icon name="{{ ['package' => 'package', 'product' => 'shopping-bag', 'template' => 'layout-template', 'subscription' => 'crown'][$type] }}" class="size-4 text-zinc-500" />
                                        </span>
                                        <p class="text-sm font-bold text-zinc-700 dark:text-zinc-200">{{ $info['label'] }}</p>
                                    </div>
                                    <div class="text-end">
                                        <p class="text-sm font-black tabular-nums text-zinc-900 dark:text-zinc-50">{{ money($info['revenue'], false) }}</p>
                                        <p class="text-[11px] text-zinc-400">{{ fa_num($info['count']) }} سفارش</p>
                                    </div>
                                </div>
                            @endforeach

                            <div class="flex items-center justify-between rounded-xl bg-gradient-to-br from-brand-500 to-brand-600 px-4 py-3 text-white shadow-md shadow-brand-500/20">
                                <p class="text-xs font-bold">درآمد کل بازه</p>
                                <p class="text-lg font-black tabular-nums">{{ money($this->salesStats['revenue'], false) }}</p>
                            </div>
                        </div>
                    </x-card>
                </div>

                {{-- ================= درگاه‌ها + کدهای تخفیف + درآمد کل همه‌زمان ================= --}}
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {{-- درگاه‌ها --}}
                    <x-card title="درگاه‌های پرداخت" :subtitle="$this->salesStats['label']">
                        @if(count($this->salesStats['by_gateway']))
                            <div class="space-y-2.5">
                                @foreach($this->salesStats['by_gateway'] as $gateway => $info)
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-2">
                                            <x-icon name="credit-card" class="size-4 text-zinc-400" />
                                            <span class="text-sm font-bold text-zinc-700 dark:text-zinc-200">{{ $gateway }}</span>
                                            <span class="text-[11px] text-zinc-400">({{ fa_num($info['count']) }})</span>
                                        </div>
                                        <span class="text-sm font-black tabular-nums text-brand-600 dark:text-brand-400">{{ money($info['revenue'], false) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <x-empty icon="credit-card" title="در این بازه پرداختی ثبت نشده" class="py-6" />
                        @endif
                    </x-card>

                    {{-- کدهای تخفیف --}}
                    <x-card title="کدهای تخفیف" :subtitle="$this->salesStats['label']">
                        <div class="space-y-3">
                            <div class="flex items-center justify-between rounded-xl bg-emerald-50 px-4 py-3 ring-1 ring-emerald-200/70 dark:bg-emerald-500/10 dark:ring-emerald-500/20">
                                <p class="text-xs font-bold text-emerald-700 dark:text-emerald-400">سفارش‌های دارای تخفیف</p>
                                <p class="font-black tabular-nums text-emerald-700 dark:text-emerald-400">{{ fa_num($this->salesStats['discount_used']) }}</p>
                            </div>
                            <div class="flex items-center justify-between rounded-xl bg-amber-50 px-4 py-3 ring-1 ring-amber-200/70 dark:bg-amber-500/10 dark:ring-amber-500/20">
                                <p class="text-xs font-bold text-amber-700 dark:text-amber-400">مجموع تخفیف داده‌شده</p>
                                <p class="font-black tabular-nums text-amber-700 dark:text-amber-400">{{ money($this->salesStats['discount'], false) }}</p>
                            </div>
                            <p class="text-[11px] leading-5 text-zinc-400">
                                کدهای تخفیف با همه بخش‌ها (پکیج، محصول، قالب، اشتراک) هماهنگ‌اند — اعمال و مصرف آن‌ها در همان لحظه تأیید پرداخت ثبت می‌شود.
                            </p>
                        </div>
                    </x-card>

                    {{-- درآمد کل (تمام زمان) --}}
                    <x-card title="درآمد کل (همه زمان‌ها)">
                        <div class="space-y-2.5">
                            @php($rev = $this->revenue)
                            <div class="flex items-center justify-between rounded-xl bg-zinc-50 px-4 py-2.5 dark:bg-zinc-800/50">
                                <span class="text-xs font-bold text-zinc-600 dark:text-zinc-300">اشتراک‌ها</span>
                                <span class="text-sm font-black tabular-nums">{{ money($rev['subscriptions'], false) }}</span>
                            </div>
                            <div class="flex items-center justify-between rounded-xl bg-zinc-50 px-4 py-2.5 dark:bg-zinc-800/50">
                                <span class="text-xs font-bold text-zinc-600 dark:text-zinc-300">پکیج‌ها</span>
                                <span class="text-sm font-black tabular-nums">{{ money($rev['packages'], false) }}</span>
                            </div>
                            <div class="flex items-center justify-between rounded-xl bg-zinc-50 px-4 py-2.5 dark:bg-zinc-800/50">
                                <span class="text-xs font-bold text-zinc-600 dark:text-zinc-300">محصولات</span>
                                <span class="text-sm font-black tabular-nums">{{ money($rev['products'], false) }}</span>
                            </div>
                            <div class="flex items-center justify-between rounded-xl bg-zinc-50 px-4 py-2.5 dark:bg-zinc-800/50">
                                <span class="text-xs font-bold text-zinc-600 dark:text-zinc-300">قالب‌ها</span>
                                <span class="text-sm font-black tabular-nums">{{ money($rev['templates'], false) }}</span>
                            </div>
                            <div class="flex items-center justify-between rounded-xl bg-zinc-900 px-4 py-3 text-white dark:bg-zinc-700">
                                <span class="text-xs font-bold">جمع کل</span>
                                <span class="text-base font-black tabular-nums">{{ money($rev['total'], false) }}</span>
                            </div>
                        </div>
                    </x-card>
                </div>

                {{-- ================= جدول فروش جزءبه‌جزء (همه بخش‌ها) ================= --}}
                <x-card title="فروش جزءبه‌جزء" :subtitle="'همه بخش‌ها — ' . $this->salesStats['label']">
                    @if($this->salesTable->count())
                        <div class="table-wrap ring-0">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>بخش</th>
                                        <th>آیتم</th>
                                        <th>خریدار</th>
                                        <th>مبلغ</th>
                                        <th class="hidden md:table-cell">تخفیف</th>
                                        <th class="hidden lg:table-cell">درگاه</th>
                                        <th class="hidden sm:table-cell">تاریخ</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($this->salesTable as $row)
                                        <tr wire:key="sale-{{ md5(json_encode([$row['type'], $row['title'], $row['paid_at']?->format('U'), $row['amount']])) }}-{{ $loop->index }}">
                                            <td>
                                                <x-badge variant="{{ ['package' => 'primary', 'product' => 'info', 'template' => 'violet', 'subscription' => 'warning'][$row['type']] }}">
                                                    {{ $row['type_label'] }}
                                                </x-badge>
                                            </td>
                                            <td>
                                                <div class="flex items-center gap-2">
                                                    <p class="max-w-44 truncate text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ $row['title'] }}</p>
                                                    @if($row['type'] === 'template' && ($row['purchase_type'] ?? '') === 'exclusive')
                                                        <x-badge variant="violet" icon="crown">اختصاصی</x-badge>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="text-sm text-zinc-600 dark:text-zinc-300">{{ $row['customer'] }}</td>
                                            <td class="font-black tabular-nums text-brand-600 dark:text-brand-400">
                                                {{ money($row['amount'], false) }}
                                                @if($row['discount'] > 0)
                                                    <span class="block text-[10px] font-normal text-zinc-400 line-through">{{ money($row['base_amount'], false) }}</span>
                                                @endif
                                            </td>
                                            <td class="hidden md:table-cell">
                                                @if($row['discount'] > 0)
                                                    <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400">−{{ money($row['discount'], false) }}</span>
                                                    @if($row['discount_code'])
                                                        <span class="block font-mono text-[10px] text-zinc-400" dir="ltr">{{ $row['discount_code'] }}</span>
                                                    @endif
                                                @else
                                                    <span class="text-zinc-300 dark:text-zinc-600">—</span>
                                                @endif
                                            </td>
                                            <td class="hidden text-sm text-zinc-500 lg:table-cell">{{ $row['gateway'] }}</td>
                                            <td class="hidden text-sm text-zinc-500 sm:table-cell">{{ $row['paid_at'] ? verta_date($row['paid_at'], 'Y/m/d H:i') : '—' }}</td>
                                            <td>
                                                @if($row['href'])
                                                    <a href="{{ $row['href'] }}" wire:navigate class="text-zinc-400 transition-colors hover:text-brand-600" aria-label="مشاهده">
                                                        <x-icon name="chevron-left" class="size-4" />
                                                    </a>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="border-t border-zinc-100 px-4 py-3 dark:border-zinc-800">
                            {{ $this->salesTable->links() }}
                        </div>
                    @else
                        <x-empty icon="wallet" title="در این بازه فروشی ثبت نشده" description="بازه زمانی را تغییر دهید یا «همه» را انتخاب کنید." />
                    @endif
                </x-card>

                {{-- ================= پرفروش‌ها (همه بخش‌ها) ================= --}}
                <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                    {{-- top packages --}}
                    <x-card title="پرفروش‌ترین پکیج‌ها">
                        @if($this->topPackages->count())
                            <div class="-my-2 divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach($this->topPackages as $package)
                                    <a href="{{ route('admin.packages.show', $package) }}" wire:navigate wire:key="top-pkg-{{ $package->id }}" class="group flex items-center gap-3 py-2.5">
                                        <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-[11px] font-black text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">{{ fa_num($loop->iteration) }}</span>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-semibold text-zinc-700 group-hover:text-brand-600 dark:text-zinc-200 dark:group-hover:text-brand-400">{{ $package->name }}</p>
                                            <p class="text-[11px] text-zinc-400">{{ fa_num($package->paid_purchases_count) }} فروش</p>
                                        </div>
                                        <span class="text-xs font-black tabular-nums text-brand-600 dark:text-brand-400">{{ money($package->paid_purchases_revenue, false) }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <x-empty icon="package" title="پکیجی ثبت نشده" class="py-6" />
                        @endif
                    </x-card>

                    {{-- top products --}}
                    <x-card title="پرفروش‌ترین محصولات">
                        @if($this->topProducts->count())
                            <div class="-my-2 divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach($this->topProducts as $product)
                                    <a href="{{ route('shop.product', $product->slug) }}" target="_blank" rel="noopener" wire:key="top-prd-{{ $product->id }}" class="group flex items-center gap-3 py-2.5">
                                        <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-[11px] font-black text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">{{ fa_num($loop->iteration) }}</span>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-semibold text-zinc-700 group-hover:text-brand-600 dark:text-zinc-200 dark:group-hover:text-brand-400">{{ $product->name }}</p>
                                            <p class="text-[11px] text-zinc-400">{{ fa_num($product->paid_purchases_count) }} فروش</p>
                                        </div>
                                        <span class="text-xs font-black tabular-nums text-brand-600 dark:text-brand-400">{{ money($product->paid_revenue, false) }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <x-empty icon="shopping-bag" title="محصولی ثبت نشده" class="py-6" />
                        @endif
                    </x-card>

                    {{-- top templates --}}
                    <x-card title="پرفروش‌ترین قالب‌ها">
                        @if($this->topTemplates->count())
                            <div class="-my-2 divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach($this->topTemplates as $template)
                                    <a href="{{ route('admin.templates.show', $template) }}" wire:navigate wire:key="top-tpl-{{ $template->id }}" class="group flex items-center gap-3 py-2.5">
                                        <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-[11px] font-black text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">{{ fa_num($loop->iteration) }}</span>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-semibold text-zinc-700 group-hover:text-brand-600 dark:text-zinc-200 dark:group-hover:text-brand-400">{{ $template->name }}</p>
                                            <p class="text-[11px] text-zinc-400">
                                                {{ fa_num($template->paid_purchases_count) }} فروش
                                                @if($template->is_sold_exclusively)
                                                    <span class="font-bold text-violet-600 dark:text-violet-400">· واگذارشده</span>
                                                @endif
                                            </p>
                                        </div>
                                        <span class="text-xs font-black tabular-nums text-brand-600 dark:text-brand-400">{{ money($template->paid_revenue, false) }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <x-empty icon="layout-template" title="قالبی ثبت نشده" class="py-6" />
                        @endif
                    </x-card>
                </div>

                {{-- ================= لایسنس‌ها در گزارش ================= --}}
                <x-card title="لایسنس‌های صادرشده" subtitle="پکیج‌ها + قالب‌ها — شامل گزارش‌ها">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        @foreach(['package' => 'لایسنس پکیج‌ها', 'template' => 'لایسنس قالب‌ها'] as $licType => $licLabel)
                            @php($lic = $this->licensesStats[$licType])
                            <div class="rounded-2xl bg-zinc-50 p-4 ring-1 ring-zinc-200/70 dark:bg-zinc-800/50 dark:ring-zinc-700/50">
                                <p class="mb-3 flex items-center gap-1.5 text-sm font-black text-zinc-800 dark:text-zinc-100">
                                    <x-icon name="key" class="size-4 text-brand-600" />
                                    {{ $licLabel }}
                                </p>
                                <div class="grid grid-cols-2 gap-2.5 text-center">
                                    <div class="rounded-xl bg-white px-3 py-2.5 ring-1 ring-zinc-200/70 dark:bg-zinc-900 dark:ring-zinc-700">
                                        <p class="text-lg font-black tabular-nums text-emerald-600 dark:text-emerald-400">{{ fa_num($lic['active']) }}</p>
                                        <p class="text-[11px] text-zinc-400">فعال</p>
                                    </div>
                                    <div class="rounded-xl bg-white px-3 py-2.5 ring-1 ring-zinc-200/70 dark:bg-zinc-900 dark:ring-zinc-700">
                                        <p class="text-lg font-black tabular-nums text-zinc-700 dark:text-zinc-200">{{ fa_num($lic['total']) }}</p>
                                        <p class="text-[11px] text-zinc-400">کل</p>
                                    </div>
                                    <div class="rounded-xl bg-white px-3 py-2.5 ring-1 ring-zinc-200/70 dark:bg-zinc-900 dark:ring-zinc-700">
                                        <p class="text-lg font-black tabular-nums text-amber-600 dark:text-amber-400">{{ fa_num($lic['expired']) }}</p>
                                        <p class="text-[11px] text-zinc-400">منقضی</p>
                                    </div>
                                    <div class="rounded-xl bg-white px-3 py-2.5 ring-1 ring-zinc-200/70 dark:bg-zinc-900 dark:ring-zinc-700">
                                        <p class="text-lg font-black tabular-nums text-rose-600 dark:text-rose-400">{{ fa_num($lic['revoked']) }}</p>
                                        <p class="text-[11px] text-zinc-400">باطل‌شده</p>
                                    </div>
                                </div>
                                @if($this->salesStats['label'] !== 'همه زمان‌ها')
                                    <p class="mt-3 text-center text-[11px] text-zinc-500 dark:text-zinc-400">
                                        در بازه «{{ $this->salesStats['label'] }}»: <span class="font-bold text-zinc-700 dark:text-zinc-200">{{ fa_num($lic['issued']) }}</span> لایسنس صادر شده
                                    </p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </x-card>
            </div>
        </x-slot:sales>
        <x-slot:plans>
            <div class="space-y-6">
                {{-- summary pills --}}
                <div class="card flex flex-wrap items-center gap-2 p-4">
                    <span class="me-1 flex items-center gap-1.5 text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                        <x-icon name="crown" class="size-3.5" />
                        وضعیت طرح‌ها:
                    </span>
                    <x-badge variant="success" icon="check-circle">طرح فعال {{ fa_num($this->plansStats['active_plans']) }}</x-badge>
                    <x-badge variant="warning" icon="hourglass">در انتظار تأیید {{ fa_num($this->plansStats['pending_orders']) }}</x-badge>
                    <x-badge variant="danger" icon="x-circle">ردشده {{ fa_num($this->plansStats['rejected_orders']) }}</x-badge>
                    <x-badge variant="primary" icon="wallet">درآمد {{ fa_num(money($this->plansStats['plan_revenue'], false)) }} تومان</x-badge>
                    <x-badge variant="neutral" icon="ticket">اشتراک فعال {{ fa_num($this->plansStats['active_plan_subscriptions']) }}</x-badge>
                </div>

                {{-- plans table --}}
                <div class="card overflow-hidden">
                    @if($this->plansReport->count())
                        <div class="flex items-center justify-between border-b border-zinc-100 px-5 py-3 dark:border-zinc-800">
                            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">
                                <span class="font-bold tabular-nums text-zinc-700 dark:text-zinc-200">{{ fa_num($this->plansReport->count()) }}</span>
                                طرح اشتراک — مرتب بر اساس درآمد
                            </p>
                            <x-icon name="crown" class="size-4 text-zinc-300 dark:text-zinc-600" />
                        </div>
                        <div class="table-wrap ring-0">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>طرح</th>
                                        <th class="hidden sm:table-cell">مدت</th>
                                        <th>قیمت</th>
                                        <th>سفارش‌ها</th>
                                        <th>درآمد</th>
                                        <th class="hidden md:table-cell">اشتراک فعال</th>
                                        <th class="hidden lg:table-cell">پکیج‌ها</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($this->plansReport as $plan)
                                        <tr wire:key="report-plan-{{ $plan->id }}">
                                            <td>
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ $plan->name }}</p>
                                                    @if($plan->is_free)
                                                        <x-badge variant="info" icon="gift">رایگان</x-badge>
                                                    @endif
                                                    @if($plan->is_one_time)
                                                        <x-badge variant="warning" icon="ticket">یک‌بارمصرف</x-badge>
                                                    @endif
                                                    @if(!$plan->is_active)
                                                        <x-badge variant="danger" icon="x-circle">غیرفعال</x-badge>
                                                    @endif
                                                </div>
                                                <p class="mt-0.5 hidden text-xs text-zinc-400 sm:block" dir="ltr">{{ $plan->slug }}</p>
                                            </td>
                                            <td class="hidden text-sm text-zinc-600 dark:text-zinc-300 sm:table-cell">{{ $plan->duration_label }}</td>
                                            <td class="text-sm">
                                                @if($plan->is_free)
                                                    <x-badge variant="info">رایگان</x-badge>
                                                @else
                                                    <span class="font-semibold tabular-nums text-zinc-700 dark:text-zinc-200">{{ fa_num(money($plan->final_price, false)) }}</span>
                                                    <span class="text-[10px] text-zinc-400">تومان</span>
                                                    @if($plan->discount_price)
                                                        <p class="text-[11px] tabular-nums text-zinc-400 line-through">{{ fa_num(money($plan->price, false)) }}</p>
                                                    @endif
                                                @endif
                                            </td>
                                            <td>
                                                @if($plan->orders_count)
                                                    <span class="text-sm font-bold tabular-nums text-zinc-700 dark:text-zinc-200">{{ fa_num($plan->orders_count) }}</span>
                                                    <div class="mt-1 flex flex-wrap items-center gap-1">
                                                        @if($plan->approved_orders_count)
                                                            <x-badge variant="success">تأیید {{ fa_num($plan->approved_orders_count) }}</x-badge>
                                                        @endif
                                                        @if($plan->pending_orders_count)
                                                            <x-badge variant="warning">در انتظار {{ fa_num($plan->pending_orders_count) }}</x-badge>
                                                        @endif
                                                        @if($plan->rejected_orders_count)
                                                            <x-badge variant="danger">رد {{ fa_num($plan->rejected_orders_count) }}</x-badge>
                                                        @endif
                                                    </div>
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
                                            <td class="hidden tabular-nums md:table-cell {{ $plan->active_subs_count ? 'text-sm font-bold text-zinc-700 dark:text-zinc-200' : 'text-sm text-zinc-400' }}">
                                                {{ $plan->active_subs_count ? fa_num($plan->active_subs_count) : '—' }}
                                            </td>
                                            <td class="hidden lg:table-cell">
                                                <p class="text-sm font-semibold tabular-nums text-zinc-600 dark:text-zinc-300">
                                                    {{ fa_num($plan->packages_count) }} <span class="text-xs font-normal text-zinc-400">پکیج</span>
                                                </p>
                                                @if($plan->packages->isNotEmpty())
                                                    <div class="mt-1 space-y-1">
                                                        @foreach($plan->packages->take(3) as $pkg)
                                                            <p class="max-w-48 truncate text-xs text-zinc-400 dark:text-zinc-500">— {{ $pkg->name }}</p>
                                                        @endforeach
                                                        @if($plan->packages->count() > 3)
                                                            <p class="text-[11px] text-zinc-400">+ {{ fa_num($plan->packages->count() - 3) }} پکیج دیگر</p>
                                                        @endif
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <x-empty icon="crown" title="طرحی ثبت نشده" description="پس از تعریف طرح‌های اشتراک در پنل، گزارش فروش آن‌ها اینجا نمایش داده می‌شود." />
                    @endif
                </div>
            </div>
        </x-slot:plans>
    </x-tabs>
</div>
