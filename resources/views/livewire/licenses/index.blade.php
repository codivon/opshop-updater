<div class="space-y-6">
    {{-- header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-black text-zinc-900 dark:text-zinc-50">لایسنس‌ها</h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">کلیدهای صدورشده برای پکیج‌ها و قالب‌ها به‌همراه وضعیت اعتبار و انقضا.</p>
        </div>

        <div class="flex items-center gap-2">
            {{-- تب نوع لایسنس --}}
            <div class="flex items-center gap-1 rounded-xl bg-zinc-100 p-1 dark:bg-zinc-800">
                <button type="button" wire:click="switchType('package')"
                        class="cursor-pointer rounded-lg px-3.5 py-1.5 text-xs font-bold transition-colors @if($type === 'package') bg-white text-zinc-900 shadow-sm dark:bg-zinc-700 dark:text-white @else text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 @endif">
                    لایسنس پکیج‌ها
                </button>
                <button type="button" wire:click="switchType('template')"
                        class="cursor-pointer rounded-lg px-3.5 py-1.5 text-xs font-bold transition-colors @if($type === 'template') bg-white text-zinc-900 shadow-sm dark:bg-zinc-700 dark:text-white @else text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 @endif">
                    لایسنس قالب‌ها
                </button>
            </div>

            @if($type === 'package')
                <x-btn variant="secondary" icon="refresh-cw" wire:click="expireOld" :loading="true">منقضی کردن لایسن‌های قدیمی</x-btn>
            @endif
        </div>
    </div>

    {{-- filters --}}
    <div class="card flex flex-col gap-3 p-4 lg:flex-row lg:items-center">
        <div class="relative flex-1">
            <x-icon name="search" class="pointer-events-none absolute start-3.5 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
            <input type="search" wire:model.live.debounce.400ms="search" placeholder="جستجوی کلید لایسنس یا نام مشتری…" class="input ps-10" />
        </div>
        <select wire:model.live="status" class="input lg:w-40">
            <option value="">همه وضعیت‌ها</option>
            <option value="active">فعال</option>
            <option value="revoked">باطل شده</option>
            <option value="expired">منقضی</option>
        </select>
        <select wire:model.live="package_id" class="input lg:w-44" @if($type !== 'package') disabled @endif>
            <option value="">همه پکیج‌ها</option>
            @foreach($this->packages as $package)
                <option value="{{ $package->id }}">{{ $package->name }}</option>
            @endforeach
        </select>
        <div class="relative lg:w-48">
            <x-icon name="arrow-up-down" class="pointer-events-none absolute start-3.5 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
            <select wire:model.live="sort" class="input ps-10" aria-label="ترتیب نمایش">
                <option value="newest">جدیدترین</option>
                <option value="oldest">قدیمی‌ترین</option>
                <option value="id_desc">شناسه (نزولی)</option>
                <option value="id_asc">شناسه (صعودی)</option>
                <option value="expires_soon">نزدیک‌ترین انقضا</option>
                <option value="expires_late">دورترین انقضا</option>
            </select>
        </div>
        <div wire:loading wire:target="search, status, package_id, sort" class="flex items-center gap-2 text-xs text-brand-600 dark:text-brand-400">
            <x-icon name="loader" class="size-4 animate-spin" />
            در حال فیلتر…
        </div>
    </div>

    {{-- bulk toolbar --}}
    @if($selectedIds)
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-rose-50/80 p-3 ring-1 ring-rose-200/70 dark:bg-rose-500/10 dark:ring-rose-400/20">
            <div class="flex items-center gap-2 text-sm font-bold text-rose-700 dark:text-rose-400">
                <x-icon name="check-square" class="size-4.5" />
                {{ fa_num(count($selectedIds)) }} لایسنس انتخاب شده است
            </div>
            <div class="flex items-center gap-2">
                <x-btn variant="secondary" size="sm" wire:click="clearSelection">انصراف از انتخاب</x-btn>
                <x-btn variant="danger" icon="trash" size="sm" wire:click="$set('confirmingBulkDelete', true)">حذف گروهی</x-btn>
            </div>
        </div>
    @endif

    {{-- table --}}
    @if($type === 'template')
        {{-- ==================== لایسنس قالب‌ها ==================== --}}
        <div class="card overflow-hidden">
            @if($this->templateRecords->count())
                <div class="table-wrap ring-0">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>کلید لایسنس</th>
                                <th>خریدار</th>
                                <th class="hidden md:table-cell">قالب</th>
                                <th class="hidden lg:table-cell">نوع خرید</th>
                                <th class="hidden lg:table-cell">صدور</th>
                                <th>وضعیت</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->templateRecords as $license)
                                <tr wire:key="tlicense-{{ $license->id }}">
                                    <td>
                                        <div class="flex items-center gap-1.5">
                                            <span dir="ltr" class="font-mono text-xs font-bold text-brand-600 dark:text-brand-400">{{ $license->license_key }}</span>
                                            <button x-data type="button"
                                                    @click="navigator.clipboard.writeText('{{ $license->license_key }}'); $dispatch('toast', {message: 'کپی شد'})"
                                                    class="rounded-md p-1 text-zinc-300 transition-colors hover:bg-zinc-100 hover:text-zinc-500 dark:text-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-400"
                                                    title="کپی کلید">
                                                <x-icon name="copy" class="size-3.5" />
                                            </button>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <x-avatar :name="$license->customer?->name ?? 'مهمان'" size="sm" />
                                            <span class="truncate text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ $license->customer?->name ?? 'مهمان (فروشگاه)' }}</span>
                                        </div>
                                    </td>
                                    <td class="hidden md:table-cell">
                                        <div class="flex items-center gap-2">
                                            <x-icon name="layout-template" class="size-4 shrink-0 text-zinc-400" />
                                            <span class="truncate text-sm text-zinc-600 dark:text-zinc-300">{{ $license->template?->name ?? '—' }}</span>
                                        </div>
                                    </td>
                                    <td class="hidden lg:table-cell">
                                        @if($license->purchase_type === \App\Models\TemplatePurchase::TYPE_EXCLUSIVE)
                                            <x-badge variant="violet" icon="crown">اختصاصی</x-badge>
                                        @else
                                            <x-badge variant="neutral">معمولی</x-badge>
                                        @endif
                                    </td>
                                    <td class="hidden whitespace-nowrap text-xs tabular-nums text-zinc-500 dark:text-zinc-400 lg:table-cell">{{ $license->starts_at ? verta_date($license->starts_at) : '—' }}</td>
                                    <td>
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
                                    </td>
                                    <td>
                                        <div class="flex items-center justify-end gap-1">
                                            @if($license->status === \App\Models\TemplateLicense::STATUS_ACTIVE)
                                                <button type="button" wire:click="$set('revokeId', {{ $license->id }})"
                                                        class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
                                                        title="ابطال لایسنس">
                                                    <x-icon name="x-circle" class="size-4.5" />
                                                </button>
                                            @else
                                                <button type="button" wire:click="activateTemplateLicense({{ $license->id }})"
                                                        class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-emerald-50 hover:text-emerald-600 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-400"
                                                        title="فعال‌سازی مجدد">
                                                    <x-icon name="check-circle" class="size-4.5" />
                                                </button>
                                            @endif
                                            <button type="button" wire:click="$set('deleteId', {{ $license->id }})"
                                                    class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
                                                    title="حذف لایسنس">
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
                    {{ $this->templateRecords->links() }}
                </div>
            @else
                <x-empty icon="key" title="لایسنس قالبی یافت نشد" description="با اولین خرید موفق قالب (فروشگاه یا API)، لایسنس دائمی به‌صورت خودکار صادر می‌شود." />
            @endif
        </div>
    @else
    {{-- ==================== لایسنس پکیج‌ها (موجود) ==================== --}}
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
                            <th>کلید لایسنس</th>
                            <th>مشتری</th>
                            <th class="hidden md:table-cell">پکیج</th>
                            <th class="hidden lg:table-cell">مدت</th>
                            <th class="hidden lg:table-cell">اعتبار</th>
                            <th>وضعیت</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->records as $license)
                            <tr wire:key="license-{{ $license->id }}">
                                <td>
                                    <input type="checkbox" class="checkbox" aria-label="انتخاب این لایسنس"
                                           wire:model.live="selectedIds" value="{{ $license->id }}" />
                                </td>
                                <td>
                                    <div class="flex items-center gap-1.5">
                                        <a href="{{ route('admin.licenses.show', $license) }}" wire:navigate dir="ltr"
                                           class="font-mono text-xs font-bold text-brand-600 transition-colors hover:underline dark:text-brand-400">
                                            {{ $license->license_key }}
                                        </a>
                                        <button x-data type="button"
                                                @click="navigator.clipboard.writeText('{{ $license->license_key }}'); $dispatch('toast', {message: 'کپی شد'})"
                                                class="rounded-md p-1 text-zinc-300 transition-colors hover:bg-zinc-100 hover:text-zinc-500 dark:text-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-400"
                                                title="کپی کلید">
                                            <x-icon name="copy" class="size-3.5" />
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <a href="{{ route('admin.customers.index') }}?search={{ urlencode($license->customer?->name ?? '') }}" wire:navigate
                                       class="group flex items-center gap-3">
                                        <x-avatar :name="$license->customer?->name ?? '؟'" size="sm" />
                                        <span class="truncate text-sm font-medium text-zinc-700 transition-colors group-hover:text-brand-600 dark:text-zinc-200 dark:group-hover:text-brand-400">
                                            {{ $license->customer?->name ?? '—' }}
                                        </span>
                                    </a>
                                </td>
                                <td class="hidden md:table-cell">
                                    <div class="flex items-center gap-2">
                                        <x-icon name="package" class="size-4 shrink-0 text-zinc-400" />
                                        <span class="truncate text-sm text-zinc-600 dark:text-zinc-300">{{ $license->package?->name ?? '—' }}</span>
                                    </div>
                                </td>
                                <td class="hidden whitespace-nowrap text-sm tabular-nums text-zinc-600 dark:text-zinc-300 lg:table-cell">
                                    @if($license->duration_months === 0)
                                        <x-badge variant="info">نامحدود</x-badge>
                                    @else
                                        {{ fa_num($license->duration_months) }} ماه
                                    @endif
                                </td>
                                <td class="hidden whitespace-nowrap text-xs tabular-nums text-zinc-500 dark:text-zinc-400 lg:table-cell">
                                    @if($license->expires_at)
                                        <span>{{ verta_date($license->starts_at) }}</span>
                                        <span class="mx-1 text-zinc-300 dark:text-zinc-600">تا</span>
                                        <span class="{{ $license->isExpired() ? 'font-bold text-rose-600 dark:text-rose-400' : '' }}">{{ verta_date($license->expires_at) }}</span>
                                    @else
                                        <x-badge variant="info">نامحدود</x-badge>
                                    @endif
                                </td>
                                <td>
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
                                </td>
                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('admin.licenses.show', $license) }}" wire:navigate
                                           class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10 dark:hover:text-brand-400"
                                           title="مشاهده">
                                            <x-icon name="eye" class="size-4.5" />
                                        </a>
                                        @if($license->status === \App\Models\PackageLicense::STATUS_ACTIVE)
                                            <button type="button" wire:click="$set('revokeId', {{ $license->id }})"
                                                    class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
                                                    title="ابطال لایسنس">
                                                <x-icon name="x-circle" class="size-4.5" />
                                            </button>
                                        @else
                                            <button type="button" wire:click="activate({{ $license->id }})"
                                                    class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-emerald-50 hover:text-emerald-600 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-400"
                                                    title="فعال‌سازی مجدد">
                                                <x-icon name="check-circle" class="size-4.5" />
                                            </button>
                                        @endif
                                        <button type="button" wire:click="$set('deleteId', {{ $license->id }})"
                                                class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
                                                title="حذف لایسنس">
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
            <x-empty icon="key" title="لایسنسی یافت نشد" description="کلیدهای صادرشده پس از اولین خرید پکیج اینجا نمایش داده می‌شوند یا فیلترها را تغییر دهید." />
        @endif
    </div>
    @endif

    {{-- revoke confirm --}}
    <x-modal wire:model="revokeId" title="ابطال لایسنس" size="sm">
        @if($revokeId)
            @if($type === 'template')
                @php($target = \App\Models\TemplateLicense::find($revokeId))
            @else
                @php($target = \App\Models\PackageLicense::find($revokeId))
            @endif
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-rose-50 p-4 text-rose-700 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20">
                    <x-icon name="alert-triangle" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">
                        کلید «<strong dir="ltr" class="font-mono">{{ $target?->license_key }}</strong>» باطل شود؟ مشتری دیگر نمی‌تواند از این لایسنس استفاده کند.
                    </p>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <x-btn variant="secondary" wire:click="$set('revokeId', null)">انصراف</x-btn>
                    <x-btn variant="danger" icon="x-circle" wire:click="{{ $type === 'template' ? 'revokeTemplateLicense' : 'revoke' }}" :loading="true">ابطال لایسنس</x-btn>
                </div>
            </div>
        @endif
    </x-modal>

    {{-- single delete confirm --}}
    <x-modal wire:model="deleteId" title="حذف لایسنس" size="sm">
        @if($deleteId)
            @if($type === 'template')
                @php($target = \App\Models\TemplateLicense::find($deleteId))
            @else
                @php($target = \App\Models\PackageLicense::find($deleteId))
            @endif
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-rose-50 p-4 text-rose-700 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20">
                    <x-icon name="trash" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">
                        لایسنس «<strong dir="ltr" class="font-mono">{{ $target?->license_key }}</strong>» برای همیشه حذف شود؟
                        این عمل قابل بازگشت نیست؛ سابقه‌ی خرید باقی می‌ماند اما دسترسی مشتری قطع می‌شود.
                    </p>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <x-btn variant="secondary" wire:click="$set('deleteId', null)">انصراف</x-btn>
                    <x-btn variant="danger" icon="trash" wire:click="{{ $type === 'template' ? 'deleteTemplateLicense' : 'delete' }}" :loading="true">حذف قطعی</x-btn>
                </div>
            </div>
        @endif
    </x-modal>

    {{-- bulk delete confirm --}}
    <x-modal wire:model="confirmingBulkDelete" title="حذف گروهی لایسنس‌ها" size="sm">
        @if($confirmingBulkDelete)
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-rose-50 p-4 text-rose-700 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20">
                    <x-icon name="trash" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">
                        <strong>{{ fa_num(count($selectedIds)) }}</strong> لایسنس انتخاب‌شده برای همیشه حذف شود؟
                        این عمل قابل بازگشت نیست.
                    </p>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <x-btn variant="secondary" wire:click="clearSelection">انصراف</x-btn>
                    <x-btn variant="danger" icon="trash" wire:click="bulkDelete" :loading="true">حذف {{ fa_num(count($selectedIds)) }} لایسنس</x-btn>
                </div>
            </div>
        @endif
    </x-modal>
</div>
