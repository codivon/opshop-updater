<div class="space-y-6">
    {{-- header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.licenses.index') }}" wire:navigate
               class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white text-zinc-500 ring-1 ring-zinc-200 transition-colors hover:text-brand-600 hover:ring-brand-300 dark:bg-zinc-800 dark:text-zinc-400 dark:ring-zinc-700 dark:hover:text-brand-400"
               title="بازگشت به لایسنس‌ها">
                <x-icon name="arrow-right" class="size-5" />
            </a>
            <div>
                <h2 class="text-lg font-black text-zinc-900 dark:text-zinc-50">جزئیات لایسنس</h2>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">اطلاعات کامل کلید، اعتبار و تاریخچه دانلود.</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            @if($license->status === \App\Models\PackageLicense::STATUS_ACTIVE)
                <x-btn variant="danger" icon="x-circle" wire:click="$set('confirmRevoke', true)">ابطال لایسنس</x-btn>
            @else
                <x-btn icon="check-circle" wire:click="activate" :loading="true">فعال‌سازی مجدد</x-btn>
            @endif
        </div>
    </div>

    {{-- hero --}}
    <div class="card overflow-hidden">
        <div class="flex flex-col gap-4 border-b border-zinc-100 bg-gradient-to-br from-brand-50/70 to-transparent p-5 sm:p-6 dark:border-zinc-800 dark:from-brand-500/5">
            <div class="flex flex-wrap items-center gap-3" dir="ltr">
                <span class="font-mono text-lg font-black tracking-wider text-brand-700 dark:text-brand-300 sm:text-xl">{{ $license->license_key }}</span>
                <button x-data type="button"
                        @click="navigator.clipboard.writeText('{{ $license->license_key }}'); $dispatch('toast', {message: 'کپی شد'})"
                        class="flex items-center gap-1.5 rounded-lg bg-white px-2.5 py-1.5 text-xs font-medium text-zinc-500 ring-1 ring-zinc-200 transition-colors hover:text-brand-600 hover:ring-brand-300 dark:bg-zinc-800 dark:text-zinc-400 dark:ring-zinc-700 dark:hover:text-brand-400"
                        title="کپی کلید لایسنس">
                    <x-icon name="copy" class="size-3.5" />
                    کپی
                </button>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @php($variant = match($license->status) {
                    'active' => 'success',
                    'revoked' => 'danger',
                    'expired' => 'danger',
                    default => 'neutral',
                })
                @php($label = match($license->status) {
                    'active' => 'فعال',
                    'revoked' => 'باطل شده',
                    'expired' => 'منقضی',
                    default => $license->status,
                })
                <x-badge :variant="$variant" :icon="$license->status === 'active' ? 'check-circle' : null">{{ $label }}</x-badge>

                @php($remaining = $license->expires_at ? (int) floor(now()->diffInDays($license->expires_at, false)) : null)
                @if($remaining === null)
                    <x-badge variant="info" icon="clock">نامحدود</x-badge>
                @elseif($license->isExpired() || $remaining < 0)
                    <x-badge variant="danger" icon="clock">منقضی شده</x-badge>
                @elseif($remaining < 14)
                    <x-badge variant="warning" icon="clock">{{ fa_num($remaining) }} روز باقی‌مانده</x-badge>
                @else
                    <x-badge variant="neutral" icon="clock">{{ fa_num($remaining) }} روز باقی‌مانده</x-badge>
                @endif
            </div>
        </div>

        {{-- detail cells --}}
        <div class="grid grid-cols-1 gap-3 p-5 sm:grid-cols-2 lg:grid-cols-4 sm:p-6">
            <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/80 dark:bg-zinc-800/60 dark:ring-zinc-700">
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">پکیج</p>
                @if($license->package)
                    <a href="{{ route('admin.packages.show', $license->package) }}" wire:navigate
                       class="mt-1.5 flex items-center gap-1.5 truncate text-sm font-bold text-zinc-800 transition-colors hover:text-brand-600 dark:text-zinc-100 dark:hover:text-brand-400">
                        <x-icon name="package" class="size-4 shrink-0 text-zinc-400" />
                        {{ $license->package->name }}
                    </a>
                @else
                    <p class="mt-1.5 text-sm text-zinc-500">—</p>
                @endif
            </div>

            <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/80 dark:bg-zinc-800/60 dark:ring-zinc-700">
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">مشتری</p>
                <a href="{{ $license->customer ? route('admin.customers.show', $license->customer) : route('admin.customers.index') }}" wire:navigate
                   class="mt-1.5 flex items-center gap-1.5 truncate text-sm font-bold text-zinc-800 transition-colors hover:text-brand-600 dark:text-zinc-100 dark:hover:text-brand-400">
                    <x-avatar :name="$license->customer?->name ?? '؟'" size="xs" />
                    {{ $license->customer?->name ?? '—' }}
                </a>
                <p class="mt-1 truncate text-xs text-zinc-400" dir="ltr">{{ $license->customer?->email }}</p>
            </div>

            <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/80 dark:bg-zinc-800/60 dark:ring-zinc-700">
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">مدت اعتبار</p>
                <p class="mt-1.5 text-sm font-bold tabular-nums text-zinc-800 dark:text-zinc-100">
                    @if($license->duration_months === 0)
                        نامحدود
                    @else
                        {{ fa_num($license->duration_months) }} ماه
                    @endif
                </p>
            </div>

            <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/80 dark:bg-zinc-800/60 dark:ring-zinc-700">
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">شروع اعتبار</p>
                <p class="mt-1.5 text-sm font-bold tabular-nums text-zinc-800 dark:text-zinc-100">{{ verta_date($license->starts_at, 'Y/m/d H:i') ?? '—' }}</p>
            </div>

            <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/80 dark:bg-zinc-800/60 dark:ring-zinc-700">
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">تاریخ انقضا</p>
                <p class="mt-1.5 text-sm font-bold tabular-nums {{ $license->isExpired() ? 'text-rose-600 dark:text-rose-400' : 'text-zinc-800 dark:text-zinc-100' }}">
                    {{ $license->expires_at ? verta_date($license->expires_at, 'Y/m/d H:i') : 'نامحدود' }}
                </p>
            </div>

            <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/80 dark:bg-zinc-800/60 dark:ring-zinc-700">
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">خرید مرتبط</p>
                @if($license->purchase)
                    <a href="{{ route('admin.purchases.show', $license->purchase) }}" wire:navigate
                       class="mt-1.5 flex items-center gap-1.5 truncate text-sm font-bold text-zinc-800 transition-colors hover:text-brand-600 dark:text-zinc-100 dark:hover:text-brand-400">
                        <x-icon name="receipt" class="size-4 shrink-0 text-zinc-400" />
                        #{{ fa_num($license->purchase->id) }} · {{ money($license->purchase->amount, false) }}
                    </a>
                @else
                    <p class="mt-1.5 text-sm text-zinc-500">— (تمدید دستی)</p>
                @endif
            </div>

            <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/80 dark:bg-zinc-800/60 dark:ring-zinc-700">
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">تمدید شده از</p>
                @if($license->renewedFrom)
                    <a href="{{ route('admin.licenses.show', $license->renewedFrom) }}" wire:navigate
                       class="mt-1.5 block truncate font-mono text-xs font-bold text-brand-600 transition-colors hover:underline dark:text-brand-400" dir="ltr">
                        {{ $license->renewedFrom->license_key }}
                    </a>
                @else
                    <p class="mt-1.5 text-sm text-zinc-500">—</p>
                @endif
            </div>

            <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/80 dark:bg-zinc-800/60 dark:ring-zinc-700">
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">تاریخ صدور</p>
                <p class="mt-1.5 text-sm font-bold tabular-nums text-zinc-800 dark:text-zinc-100">{{ verta_date($license->created_at, 'Y/m/d') }}</p>
            </div>
        </div>

        @if($license->notes)
            <div class="mx-5 mb-5 flex items-start gap-3 rounded-xl bg-amber-50 p-4 text-amber-800 ring-1 ring-amber-600/10 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/20 sm:mx-6">
                <x-icon name="info" class="mt-0.5 size-5 shrink-0" />
                <div class="min-w-0">
                    <p class="text-xs font-bold">یادداشت</p>
                    <p class="mt-1 text-sm leading-6">{{ $license->notes }}</p>
                </div>
            </div>
        @endif
    </div>

    {{-- purchase summary --}}
    @if($license->purchase)
        @php($purchase = $license->purchase)
        <x-card title="خرید مرتبط" subtitle="پرداختی که این لایسنس از آن صادر شده است">
            <div class="flex flex-wrap items-center justify-between gap-x-8 gap-y-4">
                <div class="flex items-center gap-3">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-500/10 text-brand-600 dark:text-brand-400">
                        <x-icon name="receipt" class="size-5.5" />
                    </div>
                    <div class="min-w-0">
                        <p class="font-mono text-sm font-bold text-zinc-800 dark:text-zinc-100" dir="ltr">{{ $purchase->transaction_id }}</p>
                        <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">شناسه تراکنش</p>
                    </div>
                </div>
                <div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">مبلغ</p>
                    <p class="mt-1 text-sm font-black tabular-nums text-zinc-800 dark:text-zinc-100">{{ money($purchase->amount) }}</p>
                </div>
                <div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">درگاه</p>
                    <div class="mt-1">
                        <x-badge variant="neutral" class="font-mono uppercase">{{ $purchase->gateway ?? '—' }}</x-badge>
                    </div>
                </div>
                <div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">تاریخ پرداخت</p>
                    <p class="mt-1 text-sm font-bold tabular-nums text-zinc-800 dark:text-zinc-100">{{ verta_date($purchase->paid_at, 'Y/m/d H:i') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">وضعیت</p>
                    <div class="mt-1">
                        @php($pVariant = match($purchase->status) {
                            'paid' => 'success',
                            'pending' => 'warning',
                            'failed' => 'danger',
                            default => 'neutral',
                        })
                        @php($pLabel = match($purchase->status) {
                            'paid' => 'موفق',
                            'pending' => 'در انتظار',
                            'failed' => 'ناموفق',
                            'refunded' => 'بازگشتی',
                            default => $purchase->status,
                        })
                        <x-badge :variant="$pVariant">{{ $pLabel }}</x-badge>
                    </div>
                </div>
                <a href="{{ route('admin.purchases.show', $purchase) }}" wire:navigate
                   class="inline-flex h-10 items-center gap-2 rounded-xl bg-white px-4 text-sm font-semibold text-zinc-700 shadow-xs ring-1 ring-zinc-300 transition-all hover:bg-zinc-50 hover:ring-zinc-400 dark:bg-zinc-800 dark:text-zinc-200 dark:ring-zinc-700 dark:hover:bg-zinc-700">
                    مشاهده خرید
                    <x-icon name="chevron-left" class="size-4" />
                </a>
            </div>
        </x-card>
    @endif

    {{-- download tokens --}}
    <x-card title="توکن‌های دانلود" subtitle="آخرین ۱۰ درخواست دریافت فایل با این لایسنس" padding="p-0">
        @if($this->downloadTokens->count())
            <div class="table-wrap ring-0">
                <table class="table">
                    <thead>
                        <tr>
                            <th>توکن</th>
                            <th class="hidden sm:table-cell">نسخه</th>
                            <th>انقضای توکن</th>
                            <th class="hidden md:table-cell">استفاده شده</th>
                            <th class="hidden lg:table-cell">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->downloadTokens as $token)
                            <tr wire:key="token-{{ $token->id }}">
                                <td>
                                    <span class="block max-w-40 truncate font-mono text-xs text-zinc-600 dark:text-zinc-300" dir="ltr" title="{{ $token->token }}">
                                        {{ \Illuminate\Support\Str::limit($token->token, 16) }}
                                    </span>
                                </td>
                                <td class="hidden sm:table-cell">
                                    @if($token->version)
                                        <x-badge variant="neutral" class="font-mono" dir="ltr">v{{ $token->version->version }}</x-badge>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-xs tabular-nums text-zinc-500 dark:text-zinc-400">
                                    {{ verta_date($token->expires_at, 'Y/m/d H:i') ?? '—' }}
                                </td>
                                <td class="hidden md:table-cell">
                                    @if($token->used_at)
                                        <span class="flex items-center gap-1.5 text-xs font-bold text-emerald-600 dark:text-emerald-400">
                                            <x-icon name="check" class="size-4" />
                                            {{ verta_date($token->used_at, 'Y/m/d H:i') }}
                                        </span>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </td>
                                <td class="hidden font-mono text-xs text-zinc-500 lg:table-cell" dir="ltr">
                                    {{ $token->ip_address ?: '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-empty icon="download" title="توکنی ثبت نشده" description="مشتری هنوز با این لایسنس فایلی دانلود نکرده است." class="py-10" />
        @endif
    </x-card>

    {{-- revoke confirm --}}
    <x-modal wire:model="confirmRevoke" title="ابطال لایسنس" size="sm">
        <div class="space-y-4">
            <div class="flex items-center gap-3 rounded-xl bg-rose-50 p-4 text-rose-700 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20">
                <x-icon name="alert-triangle" class="size-6 shrink-0" />
                <p class="text-sm leading-6">
                    کلید «<strong dir="ltr" class="font-mono">{{ $license->license_key }}</strong>» باطل شود؟ مشتری دیگر نمی‌تواند از این لایسنس استفاده کند.
                </p>
            </div>
            <div class="flex items-center justify-end gap-2">
                <x-btn variant="secondary" wire:click="$set('confirmRevoke', false)">انصراف</x-btn>
                <x-btn variant="danger" icon="x-circle" wire:click="revoke" :loading="true">ابطال لایسنس</x-btn>
            </div>
        </div>
    </x-modal>
</div>
