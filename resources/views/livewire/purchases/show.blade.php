<div class="space-y-6">
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

    {{-- header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.purchases.index') }}" wire:navigate
               class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white text-zinc-500 ring-1 ring-zinc-200 transition-colors hover:text-brand-600 hover:ring-brand-300 dark:bg-zinc-800 dark:text-zinc-400 dark:ring-zinc-700 dark:hover:text-brand-400"
               title="بازگشت به خریدها">
                <x-icon name="arrow-right" class="size-5" />
            </a>
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-lg font-black text-zinc-900 dark:text-zinc-50">خرید #{{ fa_num($purchase->id) }}</h2>
                    <x-badge :variant="$variant">{{ $label }}</x-badge>
                </div>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">جزئیات پرداخت، مشتری و لایسنس صادرشده.</p>
            </div>
        </div>
        <x-btn variant="danger-soft" icon="trash" wire:click="$set('confirmingDelete', true)">حذف خرید</x-btn>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        {{-- payment info --}}
        <x-card title="اطلاعات پرداخت" class="xl:col-span-2">
            <div class="space-y-5">
                {{-- amount --}}
                <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-gradient-to-br from-brand-500 to-brand-600 p-5 text-white shadow-lg shadow-brand-500/20">
                    <div class="flex items-center gap-4">
                        <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-white/15 backdrop-blur">
                            <x-icon name="banknote" class="size-6" />
                        </div>
                        <div>
                            <p class="text-xs font-medium text-brand-50/90">مبلغ پرداخت</p>
                            <p class="mt-1 text-2xl font-black tabular-nums">{{ money($purchase->amount) }}</p>
                            @if($purchase->discount_amount > 0)
                                <p class="mt-1 text-xs text-brand-50/80">
                                    <span class="line-through">{{ money($purchase->original_amount ?? ($purchase->amount + $purchase->discount_amount)) }}</span>
                                    · کد تخفیف {{ $purchase->discountCode?->code ?? '—' }} ({{ money($purchase->discount_amount) }} تخفیف)
                                </p>
                            @endif
                        </div>
                    </div>
                    <x-badge :variant="$variant">{{ $label }}</x-badge>
                </div>

                {{-- fields --}}
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/80 dark:bg-zinc-800/60 dark:ring-zinc-700">
                        <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">شناسه تراکنش</p>
                        <div class="mt-1.5 flex items-center gap-1.5" dir="ltr">
                            <span class="truncate font-mono text-sm font-bold text-zinc-800 dark:text-zinc-100">{{ $purchase->transaction_id ?? '—' }}</span>
                            @if($purchase->transaction_id)
                                <button x-data type="button"
                                        @click="navigator.clipboard.writeText('{{ $purchase->transaction_id }}'); $dispatch('toast', {message: 'کپی شد'})"
                                        class="shrink-0 rounded-md p-1 text-zinc-300 transition-colors hover:bg-zinc-200 hover:text-zinc-500 dark:text-zinc-600 dark:hover:bg-zinc-700 dark:hover:text-zinc-400"
                                        title="کپی">
                                    <x-icon name="copy" class="size-3.5" />
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/80 dark:bg-zinc-800/60 dark:ring-zinc-700">
                        <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">درگاه پرداخت</p>
                        <div class="mt-1.5 flex items-center gap-1.5">
                            <x-icon name="monitor" class="size-4 text-zinc-400" />
                            <span class="font-mono text-sm font-bold uppercase text-zinc-800 dark:text-zinc-100" dir="ltr">{{ $purchase->gateway ?? '—' }}</span>
                        </div>
                    </div>

                    <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/80 dark:bg-zinc-800/60 dark:ring-zinc-700">
                        <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">تاریخ پرداخت</p>
                        <p class="mt-1.5 text-sm font-bold tabular-nums text-zinc-800 dark:text-zinc-100">{{ verta_date($purchase->paid_at, 'Y/m/d H:i') ?? 'پرداخت نشده' }}</p>
                        <p class="mt-0.5 text-xs text-zinc-400">ثبت: {{ verta_date($purchase->created_at, 'Y/m/d H:i') }}</p>
                    </div>

                    <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/80 dark:bg-zinc-800/60 dark:ring-zinc-700">
                        <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">آدرس بازگشت (Callback)</p>
                        <p class="mt-1.5 truncate font-mono text-xs text-zinc-600 dark:text-zinc-300" dir="ltr">{{ $purchase->callback_url ?? '—' }}</p>
                    </div>

                    @if($purchase->payment_url)
                        <div class="rounded-xl bg-brand-50 p-4 ring-1 ring-brand-600/15 sm:col-span-2 dark:bg-brand-500/10 dark:ring-brand-400/20">
                            <p class="text-xs font-medium text-brand-700 dark:text-brand-300">درگاه پرداخت (لینک فعال)</p>
                            <a href="{{ $purchase->payment_url }}" target="_blank" rel="noopener"
                               class="mt-1.5 flex items-center gap-1.5 truncate font-mono text-xs font-bold text-brand-700 transition-colors hover:underline dark:text-brand-300" dir="ltr">
                                {{ \Illuminate\Support\Str::limit($purchase->payment_url, 60) }}
                                <x-icon name="external-link" class="size-3.5 shrink-0" />
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </x-card>

        {{-- customer --}}
        <x-card title="مشتری" subtitle="خریدار این تراکنش">
            <div class="flex items-center gap-4">
                <x-avatar :name="$purchase->customer?->name ?? '؟'" size="lg" />
                <div class="min-w-0">
                    <a href="{{ route('admin.customers.index') }}?search={{ urlencode($purchase->customer?->name ?? '') }}" wire:navigate
                       class="block truncate text-base font-bold text-zinc-800 transition-colors hover:text-brand-600 dark:text-zinc-100 dark:hover:text-brand-400">
                        {{ $purchase->customer?->name ?? '—' }}
                    </a>
                    <a href="{{ route('admin.customers.index') }}?search={{ urlencode($purchase->customer?->email ?? '') }}" wire:navigate
                       class="mt-0.5 block truncate text-sm text-zinc-500 transition-colors hover:text-brand-600 dark:text-zinc-400" dir="ltr">
                        {{ $purchase->customer?->email ?? '—' }}
                    </a>
                </div>
            </div>
            @if($purchase->customer?->phone)
                <div class="mt-4 flex items-center justify-between rounded-xl bg-zinc-50 px-4 py-3 ring-1 ring-zinc-200/80 dark:bg-zinc-800/60 dark:ring-zinc-700">
                    <span class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                        <x-icon name="phone" class="size-4 text-zinc-400" />
                        شماره تماس
                    </span>
                    <span class="font-mono text-sm tabular-nums text-zinc-700 dark:text-zinc-200" dir="ltr">{{ $purchase->customer->phone }}</span>
                </div>
            @endif
        </x-card>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- package --}}
        <x-card title="پکیج و طرح" subtitle="محصول خریداری‌شده">
            <div class="space-y-4">
                <div class="flex items-center gap-4">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-500/10 text-brand-600 dark:text-brand-400">
                        <x-icon name="package" class="size-5.5" />
                    </div>
                    <div class="min-w-0">
                        @if($purchase->package)
                            <a href="{{ route('admin.packages.show', $purchase->package) }}" wire:navigate
                               class="block truncate text-base font-bold text-zinc-800 transition-colors hover:text-brand-600 dark:text-zinc-100 dark:hover:text-brand-400">
                                {{ $purchase->package->name }}
                            </a>
                            <p class="mt-0.5 font-mono text-xs text-zinc-400" dir="ltr">{{ $purchase->package->slug }}</p>
                        @else
                            <p class="text-base font-bold text-zinc-500">—</p>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/80 dark:bg-zinc-800/60 dark:ring-zinc-700">
                        <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">نسخه</p>
                        <div class="mt-1.5">
                            @if($purchase->version)
                                <x-badge variant="neutral" class="font-mono" dir="ltr">v{{ $purchase->version->version }}</x-badge>
                            @else
                                <span class="text-sm text-zinc-500">—</span>
                            @endif
                        </div>
                    </div>

                    <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/80 dark:bg-zinc-800/60 dark:ring-zinc-700">
                        <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">طرح قیمت‌گذاری</p>
                        @if($purchase->pricingPlan)
                            <div class="mt-1.5 flex items-center gap-1.5">
                                <x-icon name="tag" class="size-4 text-zinc-400" />
                                <span class="truncate text-sm font-bold text-zinc-800 dark:text-zinc-100">{{ $purchase->pricingPlan->name }}</span>
                            </div>
                            <p class="mt-1 text-xs text-zinc-400">{{ $purchase->pricingPlan->duration_label }}</p>
                        @else
                            <p class="mt-1.5 text-sm text-zinc-500">—</p>
                        @endif
                    </div>
                </div>

                @if($purchase->pricingPlan)
                    <div class="flex items-center justify-between rounded-xl bg-zinc-50 px-4 py-3 ring-1 ring-zinc-200/80 dark:bg-zinc-800/60 dark:ring-zinc-700">
                        <span class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                            <x-icon name="wallet" class="size-4 text-zinc-400" />
                            قیمت طرح
                        </span>
                        <span class="text-sm font-black tabular-nums text-zinc-800 dark:text-zinc-100">{{ money($purchase->pricingPlan->final_price) }}</span>
                    </div>
                @endif
            </div>
        </x-card>

        {{-- license --}}
        @if($purchase->license)
            <x-card title="لایسنس صادرشده" subtitle="کلید اعتبارسنجی این خرید">
                @php($licVariant = match($purchase->license->status) {
                    'active' => 'success',
                    'revoked' => 'danger',
                    'expired' => 'danger',
                    default => 'neutral',
                })
                @php($licLabel = match($purchase->license->status) {
                    'active' => 'فعال',
                    'revoked' => 'باطل شده',
                    'expired' => 'منقضی',
                    default => $purchase->license->status,
                })
                <div class="space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/80 dark:bg-zinc-800/60 dark:ring-zinc-700">
                        <div class="flex items-center gap-2" dir="ltr">
                            <x-icon name="key" class="size-4 text-zinc-400" />
                            <span class="font-mono text-sm font-bold text-zinc-800 dark:text-zinc-100">{{ $purchase->license->license_key }}</span>
                            <button x-data type="button"
                                    @click="navigator.clipboard.writeText('{{ $purchase->license->license_key }}'); $dispatch('toast', {message: 'کپی شد'})"
                                    class="shrink-0 rounded-md p-1 text-zinc-300 transition-colors hover:bg-zinc-200 hover:text-zinc-500 dark:text-zinc-600 dark:hover:bg-zinc-700 dark:hover:text-zinc-400"
                                    title="کپی">
                                <x-icon name="copy" class="size-3.5" />
                            </button>
                        </div>
                        <x-badge :variant="$licVariant">{{ $licLabel }}</x-badge>
                    </div>

                    <div class="flex items-center justify-between rounded-xl bg-zinc-50 px-4 py-3 ring-1 ring-zinc-200/80 dark:bg-zinc-800/60 dark:ring-zinc-700">
                        <span class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                            <x-icon name="calendar" class="size-4 text-zinc-400" />
                            تاریخ انقضا
                        </span>
                        <span class="text-sm font-bold tabular-nums text-zinc-800 dark:text-zinc-100">
                            {{ $purchase->license->expires_at ? verta_date($purchase->license->expires_at, 'Y/m/d') : 'نامحدود' }}
                        </span>
                    </div>

                    <div class="flex justify-end">
                        <a href="{{ route('admin.licenses.show', $purchase->license) }}" wire:navigate
                           class="inline-flex h-10 items-center gap-2 rounded-xl bg-brand-50 px-4 text-sm font-semibold text-brand-700 transition-all hover:bg-brand-100 dark:bg-brand-500/10 dark:text-brand-400 dark:hover:bg-brand-500/20">
                            مشاهده لایسنس
                            <x-icon name="chevron-left" class="size-4" />
                        </a>
                    </div>
                </div>
            </x-card>
        @endif
    </div>

    {{-- meta --}}
    @if($purchase->meta)
        <x-card title="اطلاعات اضافی (Meta)" subtitle="داده‌های خام دریافتی از درگاه">
            <pre class="overflow-auto rounded-xl bg-zinc-50 p-4 font-mono text-xs text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300" dir="ltr">{{ json_encode($purchase->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        </x-card>
    @endif

    {{-- delete confirm --}}
    <x-modal wire:model="confirmingDelete" title="حذف خرید" size="sm">
        @if($confirmingDelete)
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-rose-50 p-4 text-rose-700 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20">
                    <x-icon name="trash" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">
                        خرید #<strong>{{ fa_num($purchase->id) }}</strong> برای همیشه حذف شود؟
                        این عمل قابل بازگشت نیست؛ لایسنس صادرشده (در صورت وجود) باقی می‌ماند اما پیوندش با این خرید قطع می‌شود.
                    </p>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <x-btn variant="secondary" wire:click="$set('confirmingDelete', false)">انصراف</x-btn>
                    <x-btn variant="danger" icon="trash" wire:click="delete" :loading="true">حذف قطعی</x-btn>
                </div>
            </div>
        @endif
    </x-modal>
</div>
