<div class="space-y-6">
    {{-- header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="flex items-center gap-2 text-lg font-black text-zinc-900 dark:text-zinc-50">
                <x-icon name="hourglass" class="size-5 text-brand-600 dark:text-brand-400" />
                درخواست‌های اشتراک
            </h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">سفارش‌های طرح‌های اشتراک؛ پس از پرداخت، با تأیید شما فعال می‌شوند.</p>
        </div>
        <a href="{{ route('admin.plans.index') }}" wire:navigate>
            <x-btn variant="secondary" icon="crown">مدیریت طرح‌ها</x-btn>
        </a>
    </div>

    {{-- stats --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
        @php($pendingCount = \App\Models\SubscriptionOrder::where('admin_status', 'pending')->where('status', 'paid')->count())
        <a href="{{ route('admin.subscriptions.orders') }}?admin_status=pending&status=paid" wire:navigate
           class="{{ $pendingCount > 0 ? 'ring-2 ring-amber-400/60' : '' }}">
            <x-stat icon="hourglass" :value="fa_num($pendingCount)" label="در انتظار تأیید شما" />
        </a>
        <x-stat icon="check-circle" :value="fa_num(\App\Models\SubscriptionOrder::where('admin_status', 'approved')->count())" label="تأیید شده" />
        <x-stat icon="x-circle" :value="fa_num(\App\Models\SubscriptionOrder::where('admin_status', 'rejected')->count())" label="رد شده" />
        <x-stat icon="receipt" :value="fa_num(\App\Models\SubscriptionOrder::where('status', 'paid')->count())" label="پرداخت‌شده" />
    </div>

    {{-- filters --}}
    <div class="card flex flex-col gap-3 p-4 xl:flex-row xl:items-center">
        <div class="relative flex-1">
            <x-icon name="search" class="pointer-events-none absolute start-3.5 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
            <input type="search" wire:model.live.debounce.400ms="search" placeholder="جستجوی مشتری، طرح یا شماره تراکنش…" class="input ps-10" />
        </div>
        <select wire:model.live="admin_status" class="input xl:w-44">
            <option value="">همه وضعیت‌های تأیید</option>
            <option value="pending">در انتظار تأیید مدیر</option>
            <option value="approved">تأیید شده</option>
            <option value="rejected">رد شده</option>
        </select>
        <select wire:model.live="status" class="input xl:w-36">
            <option value="">همه پرداخت‌ها</option>
            <option value="paid">پرداخت شده</option>
            <option value="pending">در انتظار پرداخت</option>
            <option value="failed">ناموفق</option>
        </select>
        <select wire:model.live="plan_id" class="input xl:w-40">
            <option value="">همه طرح‌ها</option>
            @foreach($this->plans as $plan)
                <option value="{{ $plan->id }}">{{ $plan->name }}</option>
            @endforeach
        </select>
        <div class="relative xl:w-48">
            <x-icon name="arrow-up-down" class="pointer-events-none absolute start-3.5 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
            <select wire:model.live="sort" class="input ps-10" aria-label="ترتیب نمایش">
                <option value="newest">جدیدترین</option>
                <option value="oldest">قدیمی‌ترین</option>
                <option value="id_desc">شناسه (نزولی)</option>
                <option value="id_asc">شناسه (صعودی)</option>
                <option value="amount_desc">بیشترین مبلغ</option>
                <option value="amount_asc">کمترین مبلغ</option>
                <option value="paid_newest">آخرین پرداخت</option>
                <option value="approved_newest">آخرین تأیید</option>
            </select>
        </div>
        <x-btn variant="ghost" size="sm" icon="refresh-cw" wire:click="resetFilters">پاک‌سازی فیلترها</x-btn>
        <div wire:loading wire:target="search, admin_status, status, plan_id, sort" class="flex items-center gap-2 text-xs text-brand-600 dark:text-brand-400">
            <x-icon name="loader" class="size-4 animate-spin" />
            در حال فیلتر…
        </div>
    </div>

    {{-- bulk toolbar --}}
    @if($selectedIds)
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-rose-50 p-3 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:ring-rose-400/20">
            <div class="flex items-center gap-2 text-sm font-bold text-rose-700 dark:text-rose-400">
                <x-icon name="check-square" class="size-4.5" />
                {{ fa_num(count($selectedIds)) }} سفارش انتخاب شده است
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
                            <th class="w-8">
                                <input type="checkbox" class="checkbox" aria-label="انتخاب همه"
                                       @if($this->allPageSelected()) checked @endif
                                       wire:click="toggleSelectAll" />
                            </th>
                            <th>مشتری</th>
                            <th>طرح</th>
                            <th>مبلغ</th>
                            <th class="hidden md:table-cell">پرداخت</th>
                            <th>تأیید مدیر</th>
                            <th class="hidden lg:table-cell">تاریخ</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->records as $order)
                            @php($canApprove = $order->isPaid() && $order->admin_status === \App\Models\SubscriptionOrder::ADMIN_STATUS_PENDING)
                            <tr wire:key="sub-order-{{ $order->id }}" class="{{ $canApprove ? 'bg-amber-50/50 dark:bg-amber-500/5' : '' }}">
                                <td>
                                    <input type="checkbox" class="checkbox" aria-label="انتخاب این سفارش"
                                           wire:model.live="selectedIds" value="{{ $order->id }}" />
                                </td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <x-avatar :name="$order->customer?->name ?? '؟'" size="sm" />
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-bold text-zinc-800 dark:text-zinc-100">{{ $order->customer?->name ?? '—' }}</p>
                                            <p class="truncate text-xs text-zinc-400">{{ $order->customer?->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <x-icon name="crown" class="size-4 shrink-0 text-amber-500" />
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ $order->plan_name }}</p>
                                            @if($order->plan)
                                                <p class="text-xs text-zinc-400">
                                                    {{ $order->plan->duration_label }}
                                                    @if(!empty($order->meta['plan']['packages']))
                                                        · {{ fa_num(count($order->meta['plan']['packages'])) }} پکیج رایگان
                                                    @endif
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap">
                                    @if($order->final_amount > 0)
                                        <span class="text-sm font-bold tabular-nums text-zinc-800 dark:text-zinc-100">{{ fa_num(money($order->final_amount)) }}</span>
                                        @if($order->discount > 0)
                                            <x-badge variant="success" icon="ticket-percent" class="mt-1">{{ money($order->discount, false) }}− تخفیف</x-badge>
                                        @endif
                                    @else
                                        <x-badge variant="success" icon="gift">رایگان</x-badge>
                                    @endif
                                    @if($order->gateway)
                                        <p class="mt-0.5 text-xs text-zinc-400">{{ config("general.supported_gateways.{$order->gateway}") ?? $order->gateway }}</p>
                                    @endif
                                </td>
                                <td class="hidden md:table-cell">
                                    @if($order->status === 'paid')
                                        <x-badge variant="success" icon="check-circle">پرداخت شده</x-badge>
                                    @elseif($order->status === 'failed')
                                        <x-badge variant="danger" icon="x-circle">ناموفق</x-badge>
                                    @else
                                        <div class="flex items-center gap-1">
                                            <x-badge variant="warning">در انتظار</x-badge>
                                            @if($order->transaction_id)
                                                <button type="button" wire:click="recheck({{ $order->id }})"
                                                        class="rounded-lg p-1.5 text-zinc-400 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10 dark:hover:text-brand-400"
                                                        title="بررسی مجدد پرداخت">
                                                    <x-icon name="refresh-cw" class="size-3.5" />
                                                </button>
                                            @endif
                                        </div>
                                    @endif
                                    @if($order->transaction_id)
                                        <p class="mt-0.5 font-mono text-xs text-zinc-400" dir="ltr">{{ \Illuminate\Support\Str::limit($order->transaction_id, 18) }}</p>
                                    @endif
                                </td>
                                <td>
                                    @if($order->admin_status === \App\Models\SubscriptionOrder::ADMIN_STATUS_APPROVED)
                                        <div class="space-y-1">
                                            <x-badge variant="success" icon="badge-check">تأیید شده</x-badge>
                                            @if($order->subscription)
                                                <p class="text-xs text-zinc-400">
                                                    @if($order->expires_at)
                                                        تا {{ fa_num(verta_date($order->expires_at)) }}
                                                    @else
                                                        نامحدود
                                                    @endif
                                                </p>
                                            @endif
                                        </div>
                                    @elseif($order->admin_status === \App\Models\SubscriptionOrder::ADMIN_STATUS_REJECTED)
                                        <x-badge variant="danger" icon="x-circle">رد شده</x-badge>
                                    @else
                                        <x-badge variant="{{ $order->isPaid() ? 'warning' : 'neutral' }}">
                                            {{ $order->isPaid() ? 'در انتظار تأیید' : 'پرداخت نشده' }}
                                        </x-badge>
                                    @endif
                                </td>
                                <td class="hidden whitespace-nowrap text-xs text-zinc-500 lg:table-cell dark:text-zinc-400">
                                    <p>{{ fa_num(verta_date($order->created_at)) }}</p>
                                    @if($order->approved_at)
                                        <p class="mt-0.5 text-brand-600 dark:text-brand-400">تأیید: {{ fa_num(verta_date($order->approved_at)) }}</p>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        @if($canApprove)
                                            <button type="button" wire:click="approve({{ $order->id }})"
                                                    class="rounded-lg p-2 text-emerald-500 transition-colors hover:bg-emerald-50 hover:text-emerald-600 dark:hover:bg-emerald-500/10"
                                                    title="تأیید و فعال‌سازی اشتراک">
                                                <x-icon name="check-circle" class="size-4.5" />
                                            </button>
                                            <button type="button" wire:click="$set('rejectId', {{ $order->id }})"
                                                    class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
                                                    title="رد درخواست">
                                                <x-icon name="x-circle" class="size-4.5" />
                                            </button>
                                        @endif
                                        @if($order->admin_status === \App\Models\SubscriptionOrder::ADMIN_STATUS_REJECTED && $order->rejected_reason)
                                            <button type="button" wire:click="$set('viewId', {{ $order->id }})"
                                                    class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10 dark:hover:text-brand-400"
                                                    title="مشاهده جزئیات">
                                                <x-icon name="eye" class="size-4.5" />
                                            </button>
                                        @endif
                                        <button type="button" wire:click="$set('deleteId', {{ $order->id }})"
                                                class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
                                                title="حذف">
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
            <x-empty icon="hourglass" title="سفارشی یافت نشد" description="سفارش‌های طرح اشتراک پس از خرید مشتریان اینجا ظاهر می‌شوند." />
        @endif
    </div>

    {{-- reject modal --}}
    <x-modal wire:model="rejectId" title="رد درخواست اشتراک" size="sm">
        @if($rejectId)
            @php($target = \App\Models\SubscriptionOrder::with('customer')->find($rejectId))
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-rose-50 p-4 text-rose-700 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20">
                    <x-icon name="x-circle" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">
                        درخواست «<strong>{{ $target?->plan_name }}</strong>» از <strong>{{ $target?->customer?->name }}</strong> رد شود؟
                    </p>
                </div>
                <x-field label="دلیل رد (برای مشتری نمایش داده می‌شود)">
                    <x-textarea wire:model="rejectReason" rows="2" placeholder="مثلاً اطلاعات پرداخت نامعتبر است…" />
                </x-field>
                <div class="flex items-center justify-end gap-2">
                    <x-btn variant="secondary" wire:click="$set('rejectId', null)">انصراف</x-btn>
                    <x-btn variant="danger" icon="x-circle" wire:click="reject" :loading="true">رد درخواست</x-btn>
                </div>
            </div>
        @endif
    </x-modal>

    {{-- view details modal (rejected reason / snapshot) --}}
    <x-modal wire:model="viewId" title="جزئیات سفارش" size="md">
        @if($viewId)
            @php($order = \App\Models\SubscriptionOrder::with(['customer', 'plan'])->find($viewId))
            @if($order)
                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-800/60">
                            <p class="text-xs text-zinc-400">مشتری</p>
                            <p class="mt-1 font-bold text-zinc-800 dark:text-zinc-100">{{ $order->customer?->name }}</p>
                        </div>
                        <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-800/60">
                            <p class="text-xs text-zinc-400">طرح</p>
                            <p class="mt-1 font-bold text-zinc-800 dark:text-zinc-100">{{ $order->plan_name }}</p>
                        </div>
                        <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-800/60">
                            <p class="text-xs text-zinc-400">مبلغ</p>
                            <p class="mt-1 font-bold tabular-nums text-zinc-800 dark:text-zinc-100">{{ fa_num(money($order->final_amount)) }}</p>
                        </div>
                        <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-800/60">
                            <p class="text-xs text-zinc-400">شماره تراکنش</p>
                            <p class="mt-1 font-mono text-xs text-zinc-800 dark:text-zinc-100" dir="ltr">{{ $order->transaction_id ?? '—' }}</p>
                        </div>
                    </div>
                    @if(!empty($order->meta['plan']['features']))
                        <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-800/60">
                            <p class="text-xs text-zinc-400">قابلیت‌های طرح</p>
                            <ul class="mt-1.5 space-y-1">
                                @foreach($order->meta['plan']['features'] as $feature)
                                    <li class="flex items-center gap-1.5 text-xs text-zinc-600 dark:text-zinc-300">
                                        <x-icon name="check-circle" class="size-3.5 text-brand-500" />
                                        {{ $feature }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if(!empty($order->meta['plan']['packages']))
                        <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-800/60">
                            <p class="text-xs text-zinc-400">پکیج‌های رایگان همراه</p>
                            <ul class="mt-1.5 space-y-1">
                                @foreach($order->meta['plan']['packages'] as $pkg)
                                    <li class="flex items-center justify-between gap-2 text-xs text-zinc-600 dark:text-zinc-300">
                                        <span class="flex items-center gap-1.5">
                                            <x-icon name="package" class="size-3.5 text-zinc-400" />
                                            {{ $pkg['name'] }}
                                        </span>
                                        <x-badge variant="info">{{ \App\Models\SubscriptionPlan::freeMonthsLabel((int) $pkg['free_months']) }}</x-badge>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if($order->rejected_reason)
                        <div class="rounded-xl bg-rose-50 p-3 text-sm text-rose-700 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20">
                            <p class="text-xs opacity-80">دلیل رد:</p>
                            <p class="mt-1 leading-6">{{ $order->rejected_reason }}</p>
                        </div>
                    @endif
                    <div class="flex justify-end">
                        <x-btn variant="secondary" wire:click="$set('viewId', null)">بستن</x-btn>
                    </div>
                </div>
            @endif
        @endif
    </x-modal>

    {{-- single delete confirm --}}
    <x-modal wire:model="deleteId" title="حذف سفارش اشتراک" size="sm">
        @if($deleteId)
            @php($target = \App\Models\SubscriptionOrder::find($deleteId))
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-rose-50 p-4 text-rose-700 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20">
                    <x-icon name="trash" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">
                        سفارش <strong>#{{ $target?->id }}</strong> ({{ $target?->plan_name }} — {{ $target?->customer?->name }}) حذف شود؟
                        @if($target?->subscription_id)
                            اشتراکِ صادرشده نیز حذف می‌شود.
                        @endif
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
    <x-modal wire:model="confirmingBulkDelete" title="حذف گروهی سفارش‌ها" size="sm">
        @if($confirmingBulkDelete)
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-rose-50 p-4 text-rose-700 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20">
                    <x-icon name="trash" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">
                        <strong>{{ fa_num(count($selectedIds)) }}</strong> سفارش انتخاب‌شده حذف شود؟
                        اشتراک‌های صادرشده از این سفارش‌ها نیز حذف می‌شوند.
                    </p>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <x-btn variant="secondary" wire:click="clearSelection">انصراف</x-btn>
                    <x-btn variant="danger" icon="trash" wire:click="bulkDelete" :loading="true">حذف {{ fa_num(count($selectedIds)) }} سفارش</x-btn>
                </div>
            </div>
        @endif
    </x-modal>
</div>
