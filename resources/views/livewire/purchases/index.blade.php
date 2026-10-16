<div class="space-y-6">
    {{-- header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-black text-zinc-900 dark:text-zinc-50">خریدها</h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">پرداخت‌های پکیج‌ها و وضعیت تراکنش‌ها.</p>
        </div>
    </div>

    {{-- summary cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-stat label="درآمد خریدهای موفق" :value="money($this->stats['revenue'])" icon="wallet" variant="primary" hint="جمع پرداخت‌های وضعیت موفق" />
        <x-stat label="در انتظار پرداخت" :value="fa_num($this->stats['pending'])" icon="clock" variant="warning" hint="تراکنش‌های ناتمام" />
        <x-stat label="خریدهای ناموفق" :value="fa_num($this->stats['failed'])" icon="x-circle" variant="danger" hint="پرداخت‌های شکست‌خورده" />
    </div>

    {{-- filters --}}
    <div class="card flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <x-icon name="search" class="pointer-events-none absolute start-3.5 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
            <input type="search" wire:model.live.debounce.400ms="search" placeholder="جستجوی شناسه تراکنش یا نام مشتری…" class="input ps-10" />
        </div>
        <select wire:model.live="status" class="input sm:w-44">
            <option value="">همه وضعیت‌ها</option>
            <option value="paid">موفق</option>
            <option value="pending">در انتظار</option>
            <option value="failed">ناموفق</option>
            <option value="refunded">بازگشتی</option>
        </select>
        <div class="relative sm:w-44">
            <x-icon name="arrow-up-down" class="pointer-events-none absolute start-3.5 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
            <select wire:model.live="sort" class="input ps-10" aria-label="ترتیب نمایش">
                <option value="newest">جدیدترین</option>
                <option value="oldest">قدیمی‌ترین</option>
                <option value="id_desc">شناسه (نزولی)</option>
                <option value="id_asc">شناسه (صعودی)</option>
                <option value="amount_desc">بیشترین مبلغ</option>
                <option value="amount_asc">کمترین مبلغ</option>
                <option value="paid_newest">بر اساس تاریخ پرداخت - جدیدترین</option>
                <option value="paid_oldest">بر اساس تاریخ پرداخت - قدیمی‌ترین</option>
            </select>
        </div>
        <div wire:loading wire:target="search, status, sort" class="flex items-center gap-2 text-xs text-brand-600 dark:text-brand-400">
            <x-icon name="loader" class="size-4 animate-spin" />
            در حال فیلتر…
        </div>
    </div>

    {{-- bulk toolbar --}}
    @if($selectedIds)
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-rose-50 p-3 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:ring-rose-400/20">
            <div class="flex items-center gap-2 text-sm font-bold text-rose-700 dark:text-rose-400">
                <x-icon name="check-square" class="size-4.5" />
                {{ fa_num(count($selectedIds)) }} خرید انتخاب شده است
            </div>
            <div class="flex items-center gap-2">
                <x-btn variant="secondary" size="sm" wire:click="clearSelection">انصراف از انتخاب</x-btn>
                <x-btn variant="danger" icon="trash" size="sm" wire:click="$set('confirmingBulkDelete', true)">حذف گروهی</x-btn>
            </div>
        </div>
    @endif

    {{-- table --}}
    <div class="card overflow-hidden">
        @if($this->records->count())
            <div class="table-wrap ring-0">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="w-10">
                                <input type="checkbox" class="checkbox" aria-label="انتخاب همه"
                                       @if($this->allPageSelected()) checked @endif
                                       wire:click="toggleSelectAll" />
                            </th>
                            <th>شناسه تراکنش</th>
                            <th>مشتری</th>
                            <th class="hidden md:table-cell">پکیج</th>
                            <th class="hidden lg:table-cell">نسخه</th>
                            <th>مبلغ</th>
                            <th class="hidden md:table-cell">درگاه</th>
                            <th>وضعیت</th>
                            <th class="hidden sm:table-cell">تاریخ پرداخت</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->records as $purchase)
                            <tr wire:key="purchase-{{ $purchase->id }}">
                                <td>
                                    <input type="checkbox" class="checkbox" aria-label="انتخاب این خرید"
                                           wire:model.live="selectedIds" value="{{ $purchase->id }}" />
                                </td>
                                <td>
                                    <a href="{{ route('admin.purchases.show', $purchase) }}" wire:navigate dir="ltr"
                                       class="font-mono text-xs font-bold text-brand-600 transition-colors hover:underline dark:text-brand-400">
                                        {{ $purchase->transaction_id }}
                                    </a>
                                </td>
                                <td>
                                    <a href="{{ route('admin.customers.index') }}?search={{ urlencode($purchase->customer?->name ?? '') }}" wire:navigate
                                       class="group flex items-center gap-3">
                                        <x-avatar :name="$purchase->customer?->name ?? '؟'" size="sm" />
                                        <span class="truncate text-sm font-medium text-zinc-700 transition-colors group-hover:text-brand-600 dark:text-zinc-200 dark:group-hover:text-brand-400">
                                            {{ $purchase->customer?->name ?? '—' }}
                                        </span>
                                    </a>
                                </td>
                                <td class="hidden md:table-cell">
                                    <div class="flex items-center gap-2">
                                        <x-icon name="package" class="size-4 shrink-0 text-zinc-400" />
                                        <span class="truncate text-sm text-zinc-600 dark:text-zinc-300">{{ $purchase->package?->name ?? '—' }}</span>
                                    </div>
                                </td>
                                <td class="hidden lg:table-cell">
                                    @if($purchase->version)
                                        <x-badge variant="neutral" class="font-mono" dir="ltr">v{{ $purchase->version->version }}</x-badge>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-sm font-black tabular-nums text-zinc-800 dark:text-zinc-100">
                                    {{ money($purchase->amount) }}
                                    @if($purchase->discount_amount > 0)
                                        <x-badge variant="success" icon="ticket-percent" class="mt-1 block w-fit">{{ money($purchase->discount_amount, false) }}− تخفیف</x-badge>
                                    @endif
                                </td>
                                <td class="hidden md:table-cell">
                                    <x-badge variant="neutral" class="font-mono uppercase">{{ $purchase->gateway ?? '—' }}</x-badge>
                                </td>
                                <td>
                                    @php($variant = match($purchase->status) {
                                        'paid' => 'success',
                                        'pending' => 'warning',
                                        'failed' => 'danger',
                                        'refunded' => 'neutral',
                                        default => 'neutral',
                                    })
                                    @php($label = match($purchase->status) {
                                        'paid' => 'موفق',
                                        'pending' => 'در انتظار',
                                        'failed' => 'ناموفق',
                                        'refunded' => 'بازگشتی',
                                        default => $purchase->status,
                                    })
                                    <x-badge :variant="$variant">{{ $label }}</x-badge>
                                </td>
                                <td class="hidden whitespace-nowrap text-xs tabular-nums text-zinc-500 dark:text-zinc-400 sm:table-cell">
                                    {{ $purchase->paid_at ? verta_date($purchase->paid_at, 'Y/m/d') : '—' }}
                                </td>
                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('admin.purchases.show', $purchase) }}" wire:navigate
                                           class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10 dark:hover:text-brand-400"
                                           title="مشاهده">
                                            <x-icon name="eye" class="size-4.5" />
                                        </a>
                                        <button type="button" wire:click="$set('deleteId', {{ $purchase->id }})"
                                                class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
                                                title="حذف خرید">
                                            <x-icon name="trash" class="size-4.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-zinc-100 px-4 py-3 dark:border-zinc-800">
                {{ $this->records->links() }}
            </div>
        @else
            <x-empty icon="receipt" title="خریدی یافت نشد" description="پرداخت‌های مشتریان از طریق API فروش اینجا نمایش داده می‌شوند یا فیلترها را تغییر دهید." />
        @endif
    </div>

    {{-- single delete confirm --}}
    <x-modal wire:model="deleteId" title="حذف خرید" size="sm">
        @if($deleteId)
            @php($target = \App\Models\PackagePurchase::find($deleteId))
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-rose-50 p-4 text-rose-700 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20">
                    <x-icon name="trash" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">
                        خرید #<strong>{{ fa_num($target?->id) }}</strong>
                        @if($target?->transaction_id)<span dir="ltr" class="font-mono">({{ $target->transaction_id }})</span>@endif
                        برای همیشه حذف شود؟
                        لایسنس صادرشده (در صورت وجود) باقی می‌ماند اما پیوندش با این خرید قطع می‌شود. این عمل قابل بازگشت نیست.
                    </p>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <x-btn variant="secondary" wire:click="$set('deleteId', null)">انصراف</x-btn>
                    <x-btn variant="danger" icon="trash" wire:click="delete" :loading="true">حذف قطعی</x-btn>
                </div>
            </div>
        @endif
    </x-modal>

    {{-- bulk delete confirm --}}
    <x-modal wire:model="confirmingBulkDelete" title="حذف گروهی خریدها" size="sm">
        @if($confirmingBulkDelete)
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-rose-50 p-4 text-rose-700 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20">
                    <x-icon name="trash" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">
                        <strong>{{ fa_num(count($selectedIds)) }}</strong> خرید انتخاب‌شده برای همیشه حذف شود؟
                        لایسنس‌های صادرشده باقی می‌مانند اما پیوندشان با این خریدها قطع می‌شود. این عمل قابل بازگشت نیست.
                    </p>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <x-btn variant="secondary" wire:click="clearSelection">انصراف</x-btn>
                    <x-btn variant="danger" icon="trash" wire:click="bulkDelete" :loading="true">حذف {{ fa_num(count($selectedIds)) }} خرید</x-btn>
                </div>
            </div>
        @endif
    </x-modal>
</div>
