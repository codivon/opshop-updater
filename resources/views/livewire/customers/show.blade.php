<div class="space-y-6">
    {{-- ============ هدر + بازگشت ============ --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.customers.index') }}" wire:navigate
               class="flex size-9 items-center justify-center rounded-xl bg-white text-zinc-500 ring-1 ring-zinc-200 transition-colors hover:text-brand-600 dark:bg-zinc-900 dark:text-zinc-400 dark:ring-zinc-800"
               aria-label="بازگشت به لیست مشتریان">
                <x-icon name="corner-up-left" class="size-4.5 rtl:rotate-180" />
            </a>
            <div>
                <h2 class="text-lg font-black text-zinc-900 dark:text-zinc-50">پروندهٔ مشتری</h2>
                <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">اطلاعات کامل، خریدها، لایسنس‌ها و اشتراک‌های {{ $customer->name }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <x-btn icon="plus" size="sm" wire:click="openSubscriptionCreate">اشتراک جدید</x-btn>
        </div>
    </div>

    {{-- ============ کارت هویت مشتری ============ --}}
    <div class="card p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-center gap-4">
                <x-avatar :name="$customer->name" class="size-14 text-lg" />
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-base font-black text-zinc-900 dark:text-zinc-50">{{ $customer->name }}</h3>
                        <x-badge :variant="$customer->status === 'active' ? 'success' : 'danger'" icon="{{ $customer->status === 'active' ? 'user-check' : 'user' }}">
                            {{ $customer->status === 'active' ? 'فعال' : 'غیرفعال' }}
                        </x-badge>
                        @if($this->stats['subs_reserved'] > 0)
                            <x-badge variant="violet" icon="clock">{{ fa_num($this->stats['subs_reserved']) }} رزروشده</x-badge>
                        @endif
                    </div>
                    <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                        <span class="flex items-center gap-1" dir="ltr"><x-icon name="mail" class="size-3.5" /> {{ $customer->email }}</span>
                        @if($customer->phone)
                            <span class="flex items-center gap-1 tabular-nums" dir="ltr"><x-icon name="phone" class="size-3.5" /> {{ fa_num($customer->phone) }}</span>
                        @endif
                        @if($customer->website_url)
                            <a href="{{ $customer->website_url }}" target="_blank" rel="noopener" class="flex items-center gap-1 transition-colors hover:text-brand-500" dir="ltr">
                                <x-icon name="globe" class="size-3.5" /> {{ \Illuminate\Support\Str::limit($customer->website_url, 34) }}
                                <x-icon name="external-link" class="size-3" />
                            </a>
                        @endif
                        <span class="flex items-center gap-1"><x-icon name="calendar" class="size-3.5" /> عضویت: {{ verta_date($customer->created_at) }}</span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2 rounded-xl bg-zinc-50 px-3 py-2 ring-1 ring-zinc-200/70 dark:bg-zinc-800/60 dark:ring-zinc-700">
                <div class="text-start">
                    <p class="text-[10px] font-medium text-zinc-400">کد آپدیت</p>
                    <p class="font-mono text-xs font-black tracking-wider text-zinc-700 dark:text-zinc-200" dir="ltr">{{ $customer->update_code }}</p>
                </div>
                <button type="button" x-data
                        @click="navigator.clipboard.writeText('{{ $customer->update_code }}'); $dispatch('toast', {message: 'کد آپدیت کپی شد', type: 'success'})"
                        class="rounded-lg p-1.5 text-zinc-400 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10 dark:hover:text-brand-400"
                        title="کپی کد آپدیت" aria-label="کپی کد آپدیت">
                    <x-icon name="copy" class="size-4" />
                </button>
            </div>
        </div>
    </div>

    {{-- ============ KPIها ============ --}}
    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        <x-stat label="مجموع پرداخت" :value="money($this->stats['total_revenue'])" icon="wallet" variant="primary" hint="{{ fa_num($this->stats['total_purchases']) }} خرید" />
        <x-stat label="درآمد پکیج‌ها" :value="money($this->stats['package_revenue'])" icon="package" variant="info" hint="{{ fa_num($this->stats['package_count']) }} خرید" />
        <x-stat label="درآمد قالب‌ها" :value="money($this->stats['template_revenue'])" icon="layout-template" variant="violet" hint="{{ fa_num($this->stats['template_count']) }} خرید" />
        <x-stat label="درآمد اشتراک‌ها" :value="money($this->stats['order_revenue'] + $this->stats['manual_revenue'])" icon="crown" variant="warning" hint="{{ fa_num($this->stats['order_count'] + $this->stats['manual_count']) }} سفارش" />
        <x-stat label="لایسنس‌های فعال" :value="fa_num($this->stats['licenses_active'] + $this->stats['template_licenses_active'])" icon="key" variant="success" hint="از {{ fa_num($this->stats['licenses_total'] + $this->stats['template_licenses_total']) }} لایسنس" />
        <x-stat label="اشتراک‌های فعال" :value="fa_num($this->stats['subs_active'])" icon="gem" variant="danger" hint="از {{ fa_num($this->stats['subs_total']) }} اشتراک" />
    </div>

    {{-- ============ نمودارها ============ --}}
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <div class="card p-4 sm:p-6">
            <x-chart :title="$this->spendByType['title']" :series="$this->spendByType['series']" color="brand" secondary-format="money" :height="180" />
        </div>
        <div class="card p-4 sm:p-6">
            <x-chart :title="$this->monthlyTrend['title']" :series="$this->monthlyTrend['series']" color="teal" secondary-format="count" :height="180" />
        </div>
    </div>

    {{-- ============ خریدهای پکیج ============ --}}
    <div class="card overflow-hidden">
        <div class="flex items-center justify-between gap-3 border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
            <div class="flex items-center gap-2.5">
                <div class="flex size-9 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                    <x-icon name="package" class="size-4.5" />
                </div>
                <div>
                    <h3 class="text-sm font-black text-zinc-900 dark:text-zinc-50">خریدهای پکیج</h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ fa_num($this->stats['package_count']) }} خرید پرداخت‌شده</p>
                </div>
            </div>
            <a href="{{ route('admin.purchases.index') }}" wire:navigate class="text-xs font-bold text-brand-600 transition-colors hover:text-brand-500 dark:text-brand-400">
                همهٔ خریدها ←
            </a>
        </div>
        @if($this->packagePurchases->isNotEmpty())
            <div class="max-h-96 overflow-y-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-zinc-50/95 backdrop-blur dark:bg-zinc-900/95">
                        <tr class="text-start text-xs text-zinc-500 dark:text-zinc-400">
                            <th class="px-5 py-2.5 text-start font-bold">پکیج</th>
                            <th class="px-3 py-2.5 text-start font-bold">طرح</th>
                            <th class="px-3 py-2.5 text-start font-bold">مبلغ</th>
                            <th class="hidden px-3 py-2.5 text-start font-bold md:table-cell">تاریخ پرداخت</th>
                            <th class="px-3 py-2.5 text-start font-bold">وضعیت</th>
                            <th class="px-5 py-2.5 text-start font-bold">لایسنس</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($this->packagePurchases as $purchase)
                            <tr wire:key="pp-{{ $purchase->id }}" class="transition-colors hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40">
                                <td class="px-5 py-3">
                                    <div class="min-w-0">
                                        <p class="truncate font-semibold text-zinc-800 dark:text-zinc-100">{{ $purchase->package?->name ?? 'پکیج حذف‌شده' }}</p>
                                        <p class="text-xs text-zinc-400">#{{ fa_num($purchase->id) }}</p>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-xs text-zinc-600 dark:text-zinc-300">
                                    {{ $purchase->pricingPlan?->name ?? '—' }}
                                </td>
                                <td class="px-3 py-3 tabular-nums font-semibold text-zinc-700 dark:text-zinc-200">{{ money($purchase->amount) }}</td>
                                <td class="hidden px-3 py-3 text-xs text-zinc-500 md:table-cell">{{ $purchase->paid_at ? verta_date($purchase->paid_at) : '—' }}</td>
                                <td class="px-3 py-3">
                                    @php([$label, $variant] = $this->purchaseStatusView($purchase->status))
                                    <x-badge :variant="$variant">{{ $label }}</x-badge>
                                    @if($purchase->isReservedPending())
                                        <x-badge variant="violet" icon="clock" class="mt-1">رزروشده</x-badge>
                                    @elseif($purchase->isReserved())
                                        <x-badge variant="violet" icon="check" class="mt-1">رزرو فعال‌شده</x-badge>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    @if($purchase->license)
                                        <a href="{{ route('admin.licenses.show', $purchase->license) }}" wire:navigate
                                           class="font-mono text-xs text-brand-600 transition-colors hover:text-brand-500 dark:text-brand-400" dir="ltr">
                                            {{ $purchase->license->license_key }}
                                        </a>
                                    @else
                                        <span class="text-xs text-zinc-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-empty icon="package" title="خرید پکیجی ثبت نشده" description="این مشتری هنوز هیچ پکیجی نخریده است." />
        @endif
    </div>

    {{-- ============ لایسنس‌های پکیج ============ --}}
    <div class="card overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
            <div class="flex items-center gap-2.5">
                <div class="flex size-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                    <x-icon name="key" class="size-4.5" />
                </div>
                <div>
                    <h3 class="text-sm font-black text-zinc-900 dark:text-zinc-50">لایسنس‌های پکیج</h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ fa_num($this->stats['licenses_active']) }} فعال از {{ fa_num($this->stats['licenses_total']) }} لایسنس</p>
                </div>
            </div>
            <x-btn variant="soft" size="sm" icon="plus" wire:click="openLicenseCreate">لایسنس جدید</x-btn>
        </div>
        @if($this->packageLicenses->isNotEmpty())
            <div class="max-h-96 overflow-y-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-zinc-50/95 backdrop-blur dark:bg-zinc-900/95">
                        <tr class="text-start text-xs text-zinc-500 dark:text-zinc-400">
                            <th class="px-5 py-2.5 text-start font-bold">کلید</th>
                            <th class="px-3 py-2.5 text-start font-bold">پکیج</th>
                            <th class="px-3 py-2.5 text-start font-bold">انضا</th>
                            <th class="hidden px-3 py-2.5 text-start font-bold lg:table-cell">تمدیدها</th>
                            <th class="px-3 py-2.5 text-start font-bold">وضعیت</th>
                            <th class="px-5 py-2.5 text-start font-bold">عملیات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($this->packageLicenses as $license)
                            @php([$label, $variant] = $this->licenseStatusView($license))
                            <tr wire:key="pl-{{ $license->id }}" class="transition-colors hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40">
                                <td class="px-5 py-3">
                                    <a href="{{ route('admin.licenses.show', $license) }}" wire:navigate
                                       class="font-mono text-xs font-bold text-brand-600 transition-colors hover:text-brand-500 dark:text-brand-400" dir="ltr">
                                        {{ $license->license_key }}
                                    </a>
                                </td>
                                <td class="px-3 py-3 text-xs font-semibold text-zinc-700 dark:text-zinc-200">{{ $license->package?->name ?? 'پکیج حذف‌شده' }}</td>
                                <td class="px-3 py-3">
                                    @if($license->expires_at)
                                        <p class="text-xs text-zinc-600 dark:text-zinc-300">{{ verta_date($license->expires_at) }}</p>
                                        <p class="text-[11px] tabular-nums {{ $license->expires_at->isPast() ? 'text-rose-500' : 'text-zinc-400' }}">
                                            {{ $license->expires_at->isPast() ? 'منقضی' : fa_num($license->days_remaining).' روز مانده' }}
                                        </p>
                                    @else
                                        <span class="text-xs text-emerald-600 dark:text-emerald-400">نامحدود</span>
                                    @endif
                                </td>
                                <td class="hidden px-3 py-3 text-xs tabular-nums text-zinc-500 lg:table-cell">{{ fa_num($license->renewals_count) }}</td>
                                <td class="px-3 py-3"><x-badge :variant="$variant">{{ $label }}</x-badge></td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-0.5" dir="ltr">
                                        @if($license->expires_at)
                                            <button type="button" wire:click="adjustLicenseExpiry({{ $license->id }}, 3)" wire:loading.attr="disabled"
                                                    class="rounded-lg p-1.5 text-zinc-400 transition-colors hover:bg-emerald-50 hover:text-emerald-600 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-400"
                                                    title="افزودن ۳ ماه" aria-label="افزودن ۳ ماه">
                                                <span class="text-[11px] font-black">+۳م</span>
                                            </button>
                                            <button type="button" wire:click="adjustLicenseExpiry({{ $license->id }}, -1)" wire:loading.attr="disabled"
                                                    class="rounded-lg p-1.5 text-zinc-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
                                                    title="کاهش ۱ ماه" aria-label="کاهش ۱ ماه">
                                                <span class="text-[11px] font-black">-۱م</span>
                                            </button>
                                        @endif
                                        <button type="button" wire:click="openLicenseEdit({{ $license->id }})"
                                                class="rounded-lg p-1.5 text-zinc-400 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10 dark:hover:text-brand-400"
                                                title="ویرایش (تاریخ انضا/وضعیت)" aria-label="ویرایش لایسنس">
                                            <x-icon name="pencil" class="size-4" />
                                        </button>
                                        <button type="button" wire:click="toggleLicense({{ $license->id }})"
                                                class="rounded-lg p-1.5 text-zinc-400 transition-colors hover:bg-amber-50 hover:text-amber-600 dark:hover:bg-amber-500/10 dark:hover:text-amber-400"
                                                title="{{ $license->status === 'revoked' ? 'فعال‌سازی دوباره' : 'ابطال لایسنس' }}" aria-label="تغییر وضعیت">
                                            <x-icon name="{{ $license->status === 'revoked' ? 'user-check' : 'shield-off' }}" class="size-4" />
                                        </button>
                                        <button type="button" wire:click="confirmDeleteLicense({{ $license->id }})"
                                                class="rounded-lg p-1.5 text-zinc-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
                                                title="حذف لایسنس" aria-label="حذف لایسنس">
                                            <x-icon name="trash" class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-empty icon="key" title="لایسنسی صادر نشده" description="با دکمهٔ «لایسنس جدید» می‌توانید برای این مشتری لایسنس دستی صادر کنید." />
        @endif
    </div>

    {{-- ============ قالب‌ها (خرید + لایسنس) ============ --}}
    <div class="card overflow-hidden">
        <div class="flex items-center justify-between gap-3 border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
            <div class="flex items-center gap-2.5">
                <div class="flex size-9 items-center justify-center rounded-xl bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400">
                    <x-icon name="layout-template" class="size-4.5" />
                </div>
                <div>
                    <h3 class="text-sm font-black text-zinc-900 dark:text-zinc-50">قالب‌ها</h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ fa_num($this->stats['template_count']) }} خرید · {{ fa_num($this->stats['template_licenses_total']) }} لایسنس</p>
                </div>
            </div>
        </div>
        @if($this->templatePurchases->isNotEmpty() || $this->templateLicenses->isNotEmpty())
            <div class="max-h-96 overflow-y-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-zinc-50/95 backdrop-blur dark:bg-zinc-900/95">
                        <tr class="text-start text-xs text-zinc-500 dark:text-zinc-400">
                            <th class="px-5 py-2.5 text-start font-bold">قالب</th>
                            <th class="px-3 py-2.5 text-start font-bold">نوع</th>
                            <th class="px-3 py-2.5 text-start font-bold">مبلغ</th>
                            <th class="hidden px-3 py-2.5 text-start font-bold md:table-cell">تاریخ</th>
                            <th class="px-3 py-2.5 text-start font-bold">لایسنس</th>
                            <th class="px-5 py-2.5 text-start font-bold">عملیات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($this->templatePurchases as $purchase)
                            @php([$label, $variant] = $this->purchaseStatusView($purchase->status))
                            <tr wire:key="tp-{{ $purchase->id }}" class="transition-colors hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40">
                                <td class="px-5 py-3 font-semibold text-zinc-800 dark:text-zinc-100">{{ $purchase->template?->name ?? 'قالب حذف‌شده' }}</td>
                                <td class="px-3 py-3"><x-badge :variant="$purchase->purchase_type === 'exclusive' ? 'warning' : 'neutral'">{{ $purchase->purchase_type === 'exclusive' ? 'اختصاصی' : 'معمولی' }}</x-badge></td>
                                <td class="px-3 py-3 tabular-nums font-semibold text-zinc-700 dark:text-zinc-200">{{ money($purchase->amount) }}</td>
                                <td class="hidden px-3 py-3 text-xs text-zinc-500 md:table-cell">{{ $purchase->paid_at ? verta_date($purchase->paid_at) : '—' }}</td>
                                <td class="px-3 py-3">
                                    @php([$lLabel, $lVariant] = match ($purchase->license?->status) {
                                        'active' => ['فعال', 'success'],
                                        'revoked' => ['ابطال', 'danger'],
                                        default => ['—', 'neutral'],
                                    })
                                    @if($purchase->license)
                                        <x-badge :variant="$lVariant">{{ $lLabel }}</x-badge>
                                    @else
                                        <span class="text-xs text-zinc-400">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-xs text-zinc-400">—</td>
                            </tr>
                        @endforeach
                        @foreach($this->templateLicenses as $tLicense)
                            <tr wire:key="tl-{{ $tLicense->id }}" class="transition-colors hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40">
                                <td class="px-5 py-3">
                                    <p class="font-semibold text-zinc-800 dark:text-zinc-100">{{ $tLicense->template?->name ?? 'قالب حذف‌شده' }}</p>
                                    <p class="font-mono text-[11px] text-zinc-400" dir="ltr">{{ $tLicense->license_key }}</p>
                                </td>
                                <td class="px-3 py-3"><x-badge variant="neutral">لایسنس</x-badge></td>
                                <td class="px-3 py-3 text-xs text-zinc-400">دائمی</td>
                                <td class="hidden px-3 py-3 text-xs text-zinc-500 md:table-cell">{{ $tLicense->starts_at ? verta_date($tLicense->starts_at) : '—' }}</td>
                                <td class="px-3 py-3">
                                    @php([$lLabel, $lVariant] = match ($tLicense->status) {
                                        'active' => ['فعال', 'success'],
                                        'revoked' => ['ابطال', 'danger'],
                                        default => ['منقضی', 'warning'],
                                    })
                                    <x-badge :variant="$lVariant">{{ $lLabel }}</x-badge>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-0.5" dir="ltr">
                                        <button type="button" wire:click="toggleTemplateLicense({{ $tLicense->id }})"
                                                class="rounded-lg p-1.5 text-zinc-400 transition-colors hover:bg-amber-50 hover:text-amber-600 dark:hover:bg-amber-500/10 dark:hover:text-amber-400"
                                                title="{{ $tLicense->status === 'revoked' ? 'فعال‌سازی دوباره' : 'ابطال' }}" aria-label="تغییر وضعیت لایسنس قالب">
                                            <x-icon name="{{ $tLicense->status === 'revoked' ? 'user-check' : 'shield-off' }}" class="size-4" />
                                        </button>
                                        <button type="button" wire:click="confirmDeleteTemplateLicense({{ $tLicense->id }})"
                                                class="rounded-lg p-1.5 text-zinc-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
                                                title="حذف" aria-label="حذف لایسنس قالب">
                                            <x-icon name="trash" class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-empty icon="layout-template" title="خرید قالبی ثبت نشده" description="خریدهای قالب به‌صورت مهمان هم ممکن است ثبت شده باشد و در اینجا به مشتری گره نمی‌خورد." />
        @endif
    </div>

    {{-- ============ اشتراک‌ها ============ --}}
    <div class="card overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
            <div class="flex items-center gap-2.5">
                <div class="flex size-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                    <x-icon name="crown" class="size-4.5" />
                </div>
                <div>
                    <h3 class="text-sm font-black text-zinc-900 dark:text-zinc-50">اشتراک‌ها</h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ fa_num($this->stats['subs_active']) }} فعال از {{ fa_num($this->stats['subs_total']) }} اشتراک</p>
                </div>
            </div>
            <x-btn variant="soft" size="sm" icon="plus" wire:click="openSubscriptionCreate">اشتراک جدید</x-btn>
        </div>
        @if($this->subscriptions->isNotEmpty())
            <div class="max-h-96 overflow-y-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-zinc-50/95 backdrop-blur dark:bg-zinc-900/95">
                        <tr class="text-start text-xs text-zinc-500 dark:text-zinc-400">
                            <th class="px-5 py-2.5 text-start font-bold">اشتراک</th>
                            <th class="px-3 py-2.5 text-start font-bold">مبلغ</th>
                            <th class="hidden px-3 py-2.5 text-start font-bold lg:table-cell">شروع</th>
                            <th class="px-3 py-2.5 text-start font-bold">انضا</th>
                            <th class="px-3 py-2.5 text-start font-bold">وضعیت</th>
                            <th class="px-5 py-2.5 text-start font-bold">عملیات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($this->subscriptions as $subscription)
                            <tr wire:key="sub-{{ $subscription->id }}" class="transition-colors hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40">
                                <td class="px-5 py-3">
                                    <div class="min-w-0">
                                        <p class="truncate font-semibold text-zinc-800 dark:text-zinc-100">
                                            {{ $subscription->plan?->name ?? ($subscription->project?->name ? 'اشتراک پروژهٔ '.$subscription->project->name : 'اشتراک دستی') }}
                                        </p>
                                        <p class="text-[11px] text-zinc-400">{{ $subscription->description ? \Illuminate\Support\Str::limit($subscription->description, 40) : '—' }}</p>
                                    </div>
                                </td>
                                <td class="px-3 py-3">
                                    <p class="tabular-nums text-xs font-semibold text-zinc-700 dark:text-zinc-200">{{ money((float) $subscription->final_amount) }}</p>
                                    <p class="text-[11px] {{ $subscription->payment_status === 'paid' ? 'text-emerald-600 dark:text-emerald-400' : ($subscription->payment_status === 'failed' ? 'text-rose-500' : 'text-amber-600 dark:text-amber-400') }}">
                                        {{ $subscription->payment_status === 'paid' ? 'پرداخت شده' : ($subscription->payment_status === 'failed' ? 'ناموفق' : 'در انتظار') }}
                                    </p>
                                </td>
                                <td class="hidden px-3 py-3 text-xs text-zinc-500 lg:table-cell">{{ $subscription->start_date ? verta_date($subscription->start_date) : '—' }}</td>
                                <td class="px-3 py-3">
                                    @if($subscription->isReserved())
                                        <p class="text-xs font-bold text-violet-600 dark:text-violet-400">پس از پایان دورهٔ فعلی</p>
                                        <p class="text-[11px] text-zinc-400">{{ $subscription->reserved_activate_at ? verta_date($subscription->reserved_activate_at) : '' }}</p>
                                    @elseif($subscription->expires_at)
                                        <p class="text-xs text-zinc-600 dark:text-zinc-300">{{ verta_date($subscription->expires_at) }}</p>
                                        <p class="text-[11px] tabular-nums {{ $subscription->expires_at->isPast() ? 'text-rose-500' : 'text-zinc-400' }}">
                                            {{ $subscription->expires_at->isPast() ? 'منقضی' : fa_num((int) now()->diffInDays($subscription->expires_at, false)).' روز مانده' }}
                                        </p>
                                    @else
                                        <span class="text-xs text-emerald-600 dark:text-emerald-400">نامحدود</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    @if($subscription->isReserved())
                                        <x-badge variant="violet" icon="clock">رزروشده</x-badge>
                                    @elseif($subscription->status === 'active' && !$subscription->isExpired())
                                        <x-badge variant="success">فعال</x-badge>
                                    @elseif($subscription->status === 'suspended')
                                        <x-badge variant="warning">معلق</x-badge>
                                    @else
                                        <x-badge variant="danger">منقضی</x-badge>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-0.5" dir="ltr">
                                        @unless($subscription->isReserved())
                                            <button type="button" wire:click="adjustSubscriptionExpiry({{ $subscription->id }}, 3)" wire:loading.attr="disabled"
                                                    class="rounded-lg p-1.5 text-zinc-400 transition-colors hover:bg-emerald-50 hover:text-emerald-600 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-400"
                                                    title="افزودن ۳ ماه" aria-label="افزودن ۳ ماه">
                                                <span class="text-[11px] font-black">+۳م</span>
                                            </button>
                                            <button type="button" wire:click="adjustSubscriptionExpiry({{ $subscription->id }}, -1)" wire:loading.attr="disabled"
                                                    class="rounded-lg p-1.5 text-zinc-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
                                                    title="کاهش ۱ ماه" aria-label="کاهش ۱ ماه">
                                                <span class="text-[11px] font-black">-۱م</span>
                                            </button>
                                            <button type="button" wire:click="openSubscriptionEdit({{ $subscription->id }})"
                                                    class="rounded-lg p-1.5 text-zinc-400 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10 dark:hover:text-brand-400"
                                                    title="ویرایش" aria-label="ویرایش اشتراک">
                                                <x-icon name="pencil" class="size-4" />
                                            </button>
                                        @endunless
                                        <button type="button" wire:click="confirmDeleteSubscription({{ $subscription->id }})"
                                                class="rounded-lg p-1.5 text-zinc-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
                                                title="حذف" aria-label="حذف اشتراک">
                                            <x-icon name="trash" class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-empty icon="crown" title="اشتراکی ثبت نشده" description="با دکمهٔ «اشتراک جدید» می‌توانید اشتراک دستی ثبت یا از طرح اشتراک صادر کنید." />
        @endif
    </div>

    {{-- ============ سفارش‌های طرح اشتراک ============ --}}
    <div class="card overflow-hidden">
        <div class="flex items-center justify-between gap-3 border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
            <div class="flex items-center gap-2.5">
                <div class="flex size-9 items-center justify-center rounded-xl bg-teal-50 text-teal-600 dark:bg-teal-500/10 dark:text-teal-400">
                    <x-icon name="receipt" class="size-4.5" />
                </div>
                <div>
                    <h3 class="text-sm font-black text-zinc-900 dark:text-zinc-50">سفارش‌های طرح اشتراک</h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ fa_num($this->stats['pending_orders']) }} سفارش در انتظار تأیید</p>
                </div>
            </div>
            <a href="{{ route('admin.subscriptions.orders') }}" wire:navigate class="text-xs font-bold text-brand-600 transition-colors hover:text-brand-500 dark:text-brand-400">
                مدیریت سفارش‌ها ←
            </a>
        </div>
        @if($this->subscriptionOrders->isNotEmpty())
            <div class="max-h-96 overflow-y-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-zinc-50/95 backdrop-blur dark:bg-zinc-900/95">
                        <tr class="text-start text-xs text-zinc-500 dark:text-zinc-400">
                            <th class="px-5 py-2.5 text-start font-bold">طرح</th>
                            <th class="px-3 py-2.5 text-start font-bold">مبلغ</th>
                            <th class="hidden px-3 py-2.5 text-start font-bold md:table-cell">تاریخ</th>
                            <th class="px-3 py-2.5 text-start font-bold">پرداخت</th>
                            <th class="px-3 py-2.5 text-start font-bold">تأیید مدیر</th>
                            <th class="px-5 py-2.5 text-start font-bold">عملیات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($this->subscriptionOrders as $order)
                            <tr wire:key="so-{{ $order->id }}" class="transition-colors hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40">
                                <td class="px-5 py-3">
                                    <p class="font-semibold text-zinc-800 dark:text-zinc-100">{{ $order->plan_name }}</p>
                                    <p class="text-[11px] text-zinc-400">{{ $order->duration_label }}</p>
                                </td>
                                <td class="px-3 py-3 tabular-nums font-semibold text-zinc-700 dark:text-zinc-200">{{ money($order->final_amount) }}</td>
                                <td class="hidden px-3 py-3 text-xs text-zinc-500 md:table-cell">{{ $order->paid_at ? verta_date($order->paid_at) : ($order->created_at ? verta_date($order->created_at) : '—') }}</td>
                                <td class="px-3 py-3">
                                    @php([$pLabel, $pVariant] = match ($order->status) {
                                        'paid' => ['پرداخت شده', 'success'],
                                        'failed' => ['ناموفق', 'danger'],
                                        default => ['در انتظار', 'warning'],
                                    })
                                    <x-badge :variant="$pVariant">{{ $pLabel }}</x-badge>
                                </td>
                                <td class="px-3 py-3">
                                    @php([$aLabel, $aVariant] = match ($order->admin_status) {
                                        'approved' => ['تأیید شده', 'success'],
                                        'rejected' => ['رد شده', 'danger'],
                                        default => ['در انتظار', 'warning'],
                                    })
                                    <x-badge :variant="$aVariant">{{ $aLabel }}</x-badge>
                                </td>
                                <td class="px-5 py-3">
                                    @if($order->status === 'paid' && $order->admin_status === 'pending')
                                        <div class="flex items-center gap-1">
                                            <x-btn variant="soft" size="sm" icon="check" wire:click="approveOrder({{ $order->id }})" :loading="true">تأیید</x-btn>
                                            <x-btn variant="danger-soft" size="sm" icon="x" wire:click="openRejectOrder({{ $order->id }})">رد</x-btn>
                                        </div>
                                    @elseif($order->subscription_id)
                                        <a href="{{ route('admin.subscriptions.index') }}" wire:navigate class="text-xs text-zinc-400 hover:text-brand-500">اشتراک صادر شد</a>
                                    @else
                                        <span class="text-xs text-zinc-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-empty icon="receipt" title="سفارشی ثبت نشده" description="این مشتری هنوز هیچ سفارش اشتراکی ثبت نکرده است." />
        @endif
    </div>

    {{-- ================================================================ --}}
    {{--  مودال‌ها                                                         --}}
    {{-- ================================================================ --}}

    {{-- مودال اشتراک: افزودن (دستی / از طرح) + ویرایش --}}
    <x-modal wire:model="showSubscriptionModal" :title="$subscriptionId ? 'ویرایش اشتراک' : 'اشتراک جدید'" size="lg">
        <div class="space-y-5">
            @if(! $subscriptionId)
                {{-- انتخاب نوع --}}
                <div class="flex items-center gap-2 rounded-2xl bg-zinc-100/70 p-1.5 dark:bg-zinc-800/60" role="tablist" aria-label="نوع اشتراک">
                    <button type="button" wire:click="setSubscriptionMode('manual')"
                            class="flex flex-1 items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-bold transition-all {{ $subscriptionMode === 'manual' ? 'bg-white text-brand-700 shadow-sm dark:bg-zinc-900 dark:text-brand-400' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400' }}"
                            role="tab" aria-selected="{{ $subscriptionMode === 'manual' ? 'true' : 'false' }}">
                        <x-icon name="pencil" class="size-4" /> ثبت دستی
                    </button>
                    <button type="button" wire:click="setSubscriptionMode('plan')"
                            class="flex flex-1 items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-bold transition-all {{ $subscriptionMode === 'plan' ? 'bg-white text-brand-700 shadow-sm dark:bg-zinc-900 dark:text-brand-400' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400' }}"
                            role="tab" aria-selected="{{ $subscriptionMode === 'plan' ? 'true' : 'false' }}">
                        <x-icon name="crown" class="size-4" /> صدور از طرح اشتراک
                    </button>
                </div>

                @if($subscriptionMode === 'plan')
                    <div class="space-y-5 rounded-2xl bg-amber-50/60 p-4 ring-1 ring-amber-200/60 dark:bg-amber-500/5 dark:ring-amber-400/15">
                        <x-field label="طرح اشتراک" required>
                            <x-select wire:model.live="subscriptionForm.subscription_plan_id" wire:change="$set('subscriptionDurationId', '0')"
                                      :options="$this->planOptions->pluck('name', 'id')->toArray()" placeholder="انتخاب طرح…"
                                      :class="$errors->has('subscriptionForm.subscription_plan_id') ? 'input-error' : ''" />
                        </x-field>
                        @if(count($this->durationOptions) > 0)
                            <x-field label="مدت اعتبار" hint="بر اساس مدت انتخابی، قیمت و پکیج‌های همراه طرح تعیین می‌شوند.">
                                <x-select wire:model="subscriptionDurationId" :options="$this->durationOptions" />
                            </x-field>
                        @endif
                        <label class="flex cursor-pointer items-center gap-2.5 text-sm text-zinc-700 dark:text-zinc-300">
                            <input type="checkbox" class="checkbox" wire:model="grantPlanPackages" />
                            صدور لایسنس پکیج‌های همراه طرح (مطابق جریان تأیید سفارش)
                        </label>
                    </div>
                @endif
            @endif

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-field label="مبلغ (تومان)" hint="در حالت طرح به‌صورت خودکار محاسبه می‌شود.">
                    <x-input wire:model="subscriptionForm.price" type="number" min="0" step="any" inputmode="numeric" placeholder="350000" icon="banknote" dir="ltr" :disabled="(! $subscriptionId) && $subscriptionMode === 'plan'" />
                </x-field>
                <x-field label="تخفیف (تومان)">
                    <x-input wire:model="subscriptionForm.discount" type="number" min="0" step="any" inputmode="numeric" placeholder="0" icon="tag" dir="ltr" :disabled="(! $subscriptionId) && $subscriptionMode === 'plan'" />
                </x-field>
                <x-field label="تاریخ شروع" required>
                    <x-input wire:model="subscriptionForm.start_date" type="date" icon="calendar" dir="ltr" :class="$errors->has('subscriptionForm.start_date') ? 'input-error' : ''" />
                </x-field>
                <x-field label="تاریخ پایان" required hint="خالی گذاشتن تاریخ پایان برای اشتراک نامحدود: از دکمه‌های «+۳م» بعداً تغییر دهید؛ اینجا تاریخ پایان الزامی است.">
                    <x-input wire:model="subscriptionForm.end_date" type="date" icon="calendar-clock" dir="ltr" :class="$errors->has('subscriptionForm.end_date') ? 'input-error' : ''" />
                </x-field>
                <x-field label="وضعیت پرداخت">
                    <x-select wire:model="subscriptionForm.payment_status" :options="[
                        'paid' => 'پرداخت شده',
                        'pending' => 'در انتظار پرداخت',
                        'failed' => 'ناموفق',
                    ]" />
                </x-field>
                <x-field label="وضعیت اشتراک">
                    <x-select wire:model="subscriptionForm.status" :options="[
                        'active' => 'فعال',
                        'expired' => 'منقضی',
                        'suspended' => 'معلق',
                    ]" />
                </x-field>
            </div>

            <x-field label="توضیحات">
                <x-textarea wire:model="subscriptionForm.description" rows="2" placeholder="مثلاً اشتراک سالانه حرفه‌ای با پشتیبانی اولویت‌دار…" />
            </x-field>

            <div class="flex items-center justify-end gap-2 pt-1">
                <x-btn variant="secondary" wire:click="$set('showSubscriptionModal', false)">انصراف</x-btn>
                <x-btn type="button" icon="save" wire:click="saveSubscription" :loading="true">
                    {{ $subscriptionId ? 'ذخیرهٔ تغییرات' : 'ثبت اشتراک' }}
                </x-btn>
            </div>
        </div>
    </x-modal>

    {{-- تأیید حذف اشتراک --}}
    <x-modal wire:model="deleteSubscriptionId" title="حذف اشتراک" size="sm">
        @if($deleteSubscriptionId)
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-rose-50 p-4 text-rose-700 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20">
                    <x-icon name="alert-triangle" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">آیا از حذف این اشتراک مطمئن هستید؟ این عمل قابل بازگشت نیست و اگر اشتراکِ طرح‌محور باشد، دسترسی‌های مبتنی بر آن از بین می‌رود.</p>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <x-btn variant="secondary" wire:click="$set('deleteSubscriptionId', null)">انصراف</x-btn>
                    <x-btn variant="danger" icon="trash" wire:click="deleteSubscription" :loading="true">حذف قطعی</x-btn>
                </div>
            </div>
        @endif
    </x-modal>

    {{-- مودال لایسنس: افزودن + ویرایش --}}
    <x-modal wire:model="showLicenseModal" :title="$licenseId ? 'ویرایش لایسنس' : 'صدور لایسنس جدید'" size="md">
        <div class="space-y-5">
            @if(! $licenseId)
                <x-field label="پکیج" required>
                    <x-select wire:model="licenseForm.package_id" :options="$this->packageOptions->pluck('name', 'id')->toArray()" placeholder="انتخاب پکیج…"
                              :class="$errors->has('licenseForm.package_id') ? 'input-error' : ''" />
                </x-field>
                <x-field label="مدت اعتبار (ماه)" hint="۰ = لایسنس نامحدود؛ برای ۱۲ ماه عدد ۱۲ را وارد کنید.">
                    <x-input wire:model="licenseForm.duration_months" type="number" min="0" max="120" inputmode="numeric" icon="calendar-clock" dir="ltr"
                             :class="$errors->has('licenseForm.duration_months') ? 'input-error' : ''" />
                </x-field>
            @else
                <x-field label="تاریخ انضا" hint="خالی = لایسنس نامحدود؛ با تغییر این تاریخ می‌توانید اعتبار را کم یا زیاد کنید.">
                    <x-input wire:model="licenseForm.expires_at" type="date" icon="calendar-clock" dir="ltr"
                             :class="$errors->has('licenseForm.expires_at') ? 'input-error' : ''" />
                </x-field>
            @endif
            <x-field label="وضعیت">
                <x-select wire:model="licenseForm.status" :options="[
                    'active' => 'فعال',
                    'expired' => 'منقضی',
                    'revoked' => 'ابطال شده',
                ]" />
            </x-field>
            <x-field label="یادداشت">
                <x-textarea wire:model="licenseForm.notes" rows="2" placeholder="یادداشت داخلی دربارهٔ این لایسنس…" />
            </x-field>
            <div class="flex items-center justify-end gap-2 pt-1">
                <x-btn variant="secondary" wire:click="$set('showLicenseModal', false)">انصراف</x-btn>
                <x-btn type="button" icon="save" wire:click="saveLicense" :loading="true">
                    {{ $licenseId ? 'ذخیرهٔ تغییرات' : 'صدور لایسنس' }}
                </x-btn>
            </div>
        </div>
    </x-modal>

    {{-- تأیید حذف لایسنس --}}
    <x-modal wire:model="deleteLicenseId" title="حذف لایسنس" size="sm">
        @if($deleteLicenseId)
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-rose-50 p-4 text-rose-700 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20">
                    <x-icon name="alert-triangle" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">حذف لایسنس غیرقابل بازگشت است؛ مشتری با این لایسنس دیگر نمی‌تواند آپدیت دریافت کند. مطمئن هستید؟</p>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <x-btn variant="secondary" wire:click="$set('deleteLicenseId', null)">انصراف</x-btn>
                    <x-btn variant="danger" icon="trash" wire:click="deleteLicense" :loading="true">حذف قطعی</x-btn>
                </div>
            </div>
        @endif
    </x-modal>

    {{-- تأیید حذف لایسنس قالب --}}
    <x-modal wire:model="deleteTemplateLicenseId" title="حذف لایسنس قالب" size="sm">
        @if($deleteTemplateLicenseId)
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-rose-50 p-4 text-rose-700 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20">
                    <x-icon name="alert-triangle" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">این لایسنس قالب (گواهی مالکیت) برای همیشه حذف شود؟</p>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <x-btn variant="secondary" wire:click="$set('deleteTemplateLicenseId', null)">انصراف</x-btn>
                    <x-btn variant="danger" icon="trash" wire:click="deleteTemplateLicense" :loading="true">حذف قطعی</x-btn>
                </div>
            </div>
        @endif
    </x-modal>

    {{-- مودال رد سفارش --}}
    <x-modal wire:model="showRejectModal" title="رد سفارش طرح" size="sm">
        <div class="space-y-4">
            <x-field label="دلیل رد (برای پیامک به مشتری)">
                <x-textarea wire:model="rejectReason" rows="3" placeholder="مثلاً اطلاعات پرداخت ناقص است…" />
            </x-field>
            <div class="flex items-center justify-end gap-2">
                <x-btn variant="secondary" wire:click="$set('showRejectModal', false)">انصراف</x-btn>
                <x-btn variant="danger" icon="x" wire:click="rejectOrder" :loading="true">رد سفارش</x-btn>
            </div>
        </div>
    </x-modal>
</div>
