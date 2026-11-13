<div class="space-y-6">
    {{-- header --}}
    <div>
        <h2 class="flex items-center gap-2 text-lg font-black text-zinc-900 dark:text-zinc-50">
            <x-icon name="layers" class="size-5 text-brand-600 dark:text-brand-400" />
            نمای کامل فروشگاه
        </h2>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">همهٔ پکیج‌ها، محصولات و لایسنس‌ها در یک نگاه — چند نسخه دارد؟ چند طرح قیمت دارد؟ لایسنس‌هایش چه وضعیتی دارند؟</p>
    </div>

    {{-- stats — آمار کلی مستقل از تب فعال --}}
    <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5">
        <x-stat icon="package" :value="fa_num($this->stats['packages'])" label="پکیج‌ها" />
        <x-stat icon="rocket" :value="fa_num($this->stats['products'])" label="محصولات" />
        <x-stat icon="git-branch" :value="fa_num($this->stats['versions'])" label="نسخه‌ها" />
        <x-stat icon="key" :value="fa_num($this->stats['licenses_active'])" label="لایسنس‌های فعال" />
        <x-stat icon="receipt" :value="fa_num($this->stats['paid_purchases'])" label="خرید پرداخت‌شده" />
    </div>

    {{-- tabs — سمت سرور (هر تب فقط کوئری خودش را اجرا می‌کند) --}}
    <div class="flex gap-1 overflow-x-auto rounded-xl bg-zinc-200/60 p-1 dark:bg-zinc-800/60" role="tablist" aria-label="بخش‌های نمای کامل فروشگاه">
        @foreach([
            'packages' => ['پکیج‌ها', 'package', $this->stats['packages']],
            'products' => ['محصولات', 'rocket', $this->stats['products']],
            'licenses' => ['لایسنس‌ها', 'key', \App\Models\PackageLicense::count() + \App\Models\TemplateLicense::count()],
        ] as $tabKey => [$label, $icon, $count])
            <button type="button" role="tab" wire:click="switchTab('{{ $tabKey }}')"
                    aria-selected="{{ $tab === $tabKey ? 'true' : 'false' }}"
                    @class([
                        'flex shrink-0 cursor-pointer items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-semibold transition-all',
                        'bg-white text-zinc-900 shadow-sm dark:bg-zinc-900 dark:text-zinc-100' => $tab === $tabKey,
                        'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' => $tab !== $tabKey,
                    ])>
                <x-icon :name="$icon" class="size-4" />
                {{ $label }}
                <span class="rounded-full bg-zinc-300/70 px-2 py-0.5 text-[11px] font-bold tabular-nums text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">{{ fa_num($count) }}</span>
            </button>
        @endforeach
    </div>

    {{-- filters --}}
    <div class="card flex flex-col gap-3 p-4 lg:flex-row lg:items-center">
        <div class="relative flex-1">
            <x-icon name="search" class="pointer-events-none absolute start-3.5 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
            <input type="search" wire:model.live.debounce.400ms="search"
                   placeholder="{{ $tab === 'packages' ? 'جستجوی نام یا شناسهٔ پکیج…' : ($tab === 'products' ? 'جستجوی نام یا شناسهٔ محصول…' : 'جستجوی کلید لایسنس، مشتری یا پکیج/قالب…') }}"
                   class="input ps-10" />
        </div>

        @if($tab === 'licenses')
            {{-- زیرزبانهٔ نوع لایسنس --}}
            <div class="flex rounded-xl bg-zinc-200/60 p-1 dark:bg-zinc-800/60" role="group" aria-label="نوع لایسنس">
                <button type="button" wire:click="switchLicenseType('package')"
                        @class([
                            'cursor-pointer rounded-lg px-3 py-1.5 text-xs font-bold transition-all',
                            'bg-white text-zinc-900 shadow-sm dark:bg-zinc-900 dark:text-zinc-100' => $license_type === 'package',
                            'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400' => $license_type !== 'package',
                        ])>
                    لایسنس پکیج‌ها
                </button>
                <button type="button" wire:click="switchLicenseType('template')"
                        @class([
                            'cursor-pointer rounded-lg px-3 py-1.5 text-xs font-bold transition-all',
                            'bg-white text-zinc-900 shadow-sm dark:bg-zinc-900 dark:text-zinc-100' => $license_type === 'template',
                            'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400' => $license_type !== 'template',
                        ])>
                    لایسنس قالب‌ها
                </button>
            </div>
            <select wire:model.live="status" class="input lg:w-40" aria-label="فیلتر وضعیت لایسنس">
                <option value="">همه وضعیت‌ها</option>
                <option value="active">فعال</option>
                <option value="expired">منقضی</option>
                <option value="revoked">باطل‌شده</option>
            </select>
        @else
            <select wire:model.live="status" class="input lg:w-40" aria-label="فیلتر وضعیت">
                <option value="">همه وضعیت‌ها</option>
                <option value="active">منتشر شده</option>
                <option value="draft">پیش‌نویس</option>
                <option value="archived">آرشیو شده</option>
            </select>
        @endif

        <div wire:loading wire:target="search, status, switchTab, switchLicenseType" class="flex items-center gap-2 text-xs text-brand-600 dark:text-brand-400">
            <x-icon name="loader" class="size-4 animate-spin" />
            در حال فیلتر…
        </div>
    </div>

    {{-- ================= پکیج‌ها ================= --}}
    @if($tab === 'packages')
        <div class="card overflow-hidden">
            @if($this->records->count())
                <div class="table-wrap ring-0">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>پکیج</th>
                                <th>وضعیت</th>
                                <th>نسخه‌ها</th>
                                <th>طرح‌های قیمت</th>
                                <th class="hidden lg:table-cell">لایسنس‌ها</th>
                                <th class="hidden xl:table-cell">فروش</th>
                                <th class="w-24">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->records as $package)
                                @php($expanded = $expandedId === $package->id)
                                <tr wire:key="pkg-{{ $package->id }}" @class(['bg-brand-50/40 dark:bg-brand-500/5' => $expanded])>
                                    <td>
                                        <div class="flex flex-col items-start gap-1">
                                            <span class="text-sm font-bold text-zinc-800 dark:text-zinc-100">{{ $package->name }}</span>
                                            <span class="font-mono text-[11px] text-zinc-400" dir="ltr">{{ $package->slug }}</span>
                                            <span class="inline-flex items-center gap-1 text-[11px] text-zinc-500 dark:text-zinc-400">
                                                <x-icon name="folder" class="size-3 shrink-0" />
                                                {{ $package->project?->name ?? 'بدون پروژه' }}
                                            </span>
                                            @if($package->categoryModel)
                                                <span class="inline-flex items-center gap-1 rounded-md bg-zinc-100 px-1.5 py-0.5 text-[10px] font-bold text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                                                    <x-icon name="tag" class="size-3" />
                                                    {{ $package->categoryModel->name }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        @php($statusMeta = match($package->status) {
                                            'active'   => ['success', 'منتشر شده'],
                                            'draft'    => ['warning', 'پیش‌نویس'],
                                            'archived' => ['neutral', 'آرشیو شده'],
                                            default    => ['neutral', $package->status],
                                        })
                                        <x-badge :variant="$statusMeta[0]">{{ $statusMeta[1] }}</x-badge>
                                    </td>
                                    <td>
                                        <div class="flex flex-col items-start gap-1">
                                            <span class="inline-flex items-center gap-1 text-sm font-bold tabular-nums text-zinc-800 dark:text-zinc-100">
                                                <x-icon name="git-branch" class="size-3.5 shrink-0 text-zinc-400" />
                                                {{ fa_num($package->versions_count) }} نسخه
                                            </span>
                                            @if($package->versions_count > 0)
                                                <span class="text-[11px] tabular-nums text-zinc-500 dark:text-zinc-400">
                                                    فعال <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ fa_num($package->active_versions_count) }}</span>
                                                    @if($package->draft_versions_count > 0) · پیش‌نویس {{ fa_num($package->draft_versions_count) }} @endif
                                                    @if($package->archived_versions_count > 0) · آرشیو {{ fa_num($package->archived_versions_count) }} @endif
                                                </span>
                                                @if($package->latestVersion)
                                                    <x-badge variant="neutral"><span class="font-mono" dir="ltr">v{{ $package->latestVersion->version }}</span></x-badge>
                                                @endif
                                            @else
                                                <span class="text-[11px] text-zinc-400">بدون نسخه</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex flex-col items-start gap-1.5">
                                            @if($package->active_plans_count > 0)
                                                <span class="inline-flex items-center gap-1 text-sm font-bold tabular-nums text-zinc-800 dark:text-zinc-100">
                                                    <x-icon name="banknote" class="size-3.5 shrink-0 text-zinc-400" />
                                                    {{ fa_num($package->active_plans_count) }} طرح قیمت
                                                    @if($package->pricing_plans_count > $package->active_plans_count)
                                                        <span class="text-[11px] font-medium text-zinc-400">(+{{ fa_num($package->pricing_plans_count - $package->active_plans_count) }} غیرفعال)</span>
                                                    @endif
                                                </span>
                                                @foreach($package->pricingPlans->where('is_active', true)->take(3) as $plan)
                                                    <span class="inline-flex max-w-full flex-wrap items-center gap-1 rounded-lg bg-zinc-100 px-2 py-1 text-[11px] font-bold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300"
                                                          title="{{ $plan->duration_label }} — {{ fa_num(money($plan->final_price)) }}">
                                                        {{ $plan->name }}
                                                        <span class="tabular-nums text-zinc-400">{{ $plan->final_price > 0 ? fa_num(money($plan->final_price, false)) : 'رایگان' }}</span>
                                                        @if($plan->is_one_time)
                                                            <span class="text-[10px] font-black text-amber-600 dark:text-amber-400">یک‌بارمصرف</span>
                                                        @endif
                                                    </span>
                                                @endforeach
                                                @if($package->active_plans_count > 3)
                                                    <span class="text-[11px] font-bold text-zinc-400">+{{ fa_num($package->active_plans_count - 3) }}</span>
                                                @endif
                                            @elseif($package->is_free)
                                                <x-badge variant="success" icon="gift">رایگان</x-badge>
                                            @elseif($package->default_price > 0)
                                                <span class="text-sm font-bold tabular-nums text-zinc-800 dark:text-zinc-100">{{ fa_num(money($package->default_price, false)) }}</span>
                                                <span class="text-[10px] text-zinc-400">قیمت پیش‌فرض (بدون طرح قیمت)</span>
                                            @else
                                                <span class="text-[11px] text-zinc-400">بدون قیمت</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="hidden lg:table-cell">
                                        <div class="flex flex-col items-start gap-1">
                                            <span class="inline-flex items-center gap-1 text-sm font-bold tabular-nums text-zinc-800 dark:text-zinc-100">
                                                <x-icon name="key" class="size-3.5 shrink-0 text-zinc-400" />
                                                {{ fa_num($package->licenses_count) }} لایسنس
                                            </span>
                                            @if($package->licenses_count > 0)
                                                <span class="text-[11px] tabular-nums text-zinc-500 dark:text-zinc-400">
                                                    فعال <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ fa_num($package->active_licenses_count) }}</span>
                                                    · منقضی {{ fa_num($package->expired_licenses_count) }}
                                                    · باطل {{ fa_num($package->revoked_licenses_count) }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="hidden xl:table-cell">
                                        <div class="flex flex-col items-start gap-1">
                                            <span class="inline-flex items-center gap-1 text-sm tabular-nums text-zinc-600 dark:text-zinc-300">
                                                <x-icon name="receipt" class="size-3.5 shrink-0 text-zinc-400" />
                                                {{ fa_num($package->paid_purchases_count) }} فروش
                                            </span>
                                            @if($package->paid_revenue_sum > 0)
                                                <span class="text-[11px] font-bold tabular-nums text-emerald-600 dark:text-emerald-400" title="درآمد پرداخت‌شده">
                                                    {{ fa_num(money($package->paid_revenue_sum, false)) }} تومان
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex flex-col items-end gap-1">
                                            <button type="button" wire:click="toggleExpand({{ $package->id }})"
                                                    class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10 dark:hover:text-brand-400"
                                                    title="{{ $expanded ? 'بستن جزئیات' : 'جزئیات کامل (فهرست نسخه‌ها و طرح‌های قیمت)' }}"
                                                    aria-expanded="{{ $expanded ? 'true' : 'false' }}">
                                                <x-icon name="{{ $expanded ? 'chevron-up' : 'chevron-down' }}" class="size-4.5" />
                                            </button>
                                            <a href="{{ route('admin.packages.show', $package) }}" wire:navigate
                                               class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                                               title="صفحهٔ کامل پکیج">
                                                <x-icon name="external-link" class="size-4.5" />
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                @if($expanded)
                                    <tr wire:key="pkg-exp-{{ $package->id }}">
                                        <td colspan="7" class="bg-zinc-50/70 p-0 dark:bg-zinc-900/50">
                                            <div class="grid grid-cols-1 gap-4 border-s-2 border-brand-400/60 p-4 xl:grid-cols-2">
                                                {{-- فهرست کامل نسخه‌ها --}}
                                                <div class="card p-4">
                                                    <p class="mb-3 flex items-center gap-2 text-sm font-black text-zinc-800 dark:text-zinc-100">
                                                        <x-icon name="git-branch" class="size-4 text-brand-500" />
                                                        نسخه‌های «{{ $package->name }}» ({{ fa_num($package->versions_count) }})
                                                    </p>
                                                    @if($package->versions->isNotEmpty())
                                                        <div class="max-h-64 overflow-y-auto">
                                                            <table class="table">
                                                                <thead>
                                                                    <tr>
                                                                        <th>نسخه</th>
                                                                        <th>نوع</th>
                                                                        <th>وضعیت</th>
                                                                        <th class="hidden sm:table-cell">انتشار</th>
                                                                        <th class="hidden sm:table-cell">حجم</th>
                                                                        <th>دانلود</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach($package->versions as $v)
                                                                        <tr wire:key="pkgv-{{ $v->id }}">
                                                                            <td>
                                                                                <span class="font-mono text-xs font-bold text-zinc-800 dark:text-zinc-100" dir="ltr">v{{ $v->version }}</span>
                                                                                @if($v->is_mandatory)
                                                                                    <span class="ms-1 rounded-md bg-rose-50 px-1 py-0.5 text-[10px] font-black text-rose-600 dark:bg-rose-500/10 dark:text-rose-400">اجباری</span>
                                                                                @endif
                                                                            </td>
                                                                            <td class="text-xs text-zinc-600 dark:text-zinc-300">{{ match($v->type) { 'major' => 'بزرگ', 'minor' => 'متوسط', 'patch' => 'ترمیمی', default => $v->type } }}</td>
                                                                            <td>
                                                                                <x-badge variant="{{ match($v->status) { 'active' => 'success', 'draft' => 'warning', default => 'neutral' } }}">
                                                                                    {{ match($v->status) { 'active' => 'فعال', 'draft' => 'پیش‌نویس', 'archived' => 'آرشیو', default => $v->status } }}
                                                                                </x-badge>
                                                                            </td>
                                                                            <td class="hidden text-xs tabular-nums text-zinc-500 sm:table-cell">{{ $v->release_date ? verta_date($v->release_date) : '—' }}</td>
                                                                            <td class="hidden text-xs tabular-nums text-zinc-500 sm:table-cell" dir="ltr">{{ $v->file_size ?: '—' }}</td>
                                                                            <td class="text-xs tabular-nums text-zinc-600 dark:text-zinc-300">{{ fa_num($v->downloads_count) }}</td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    @else
                                                        <p class="text-xs text-zinc-400">این پکیج هنوز نسخه‌ای ندارد.</p>
                                                    @endif
                                                </div>

                                                {{-- فهرست کامل طرح‌های قیمت --}}
                                                <div class="card p-4">
                                                    <p class="mb-3 flex items-center gap-2 text-sm font-black text-zinc-800 dark:text-zinc-100">
                                                        <x-icon name="banknote" class="size-4 text-brand-500" />
                                                        طرح‌های قیمت ({{ fa_num($package->pricingPlans->count()) }})
                                                    </p>
                                                    @if($package->pricingPlans->isNotEmpty())
                                                        <div class="max-h-64 overflow-y-auto">
                                                            <table class="table">
                                                                <thead>
                                                                    <tr>
                                                                        <th>طرح</th>
                                                                        <th>مدت</th>
                                                                        <th>قیمت نهایی</th>
                                                                        <th>نوع</th>
                                                                        <th>وضعیت</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach($package->pricingPlans as $plan)
                                                                        <tr wire:key="pkgp-{{ $plan->id }}">
                                                                            <td>
                                                                                <span class="text-xs font-bold text-zinc-800 dark:text-zinc-100">{{ $plan->name }}</span>
                                                                                @if($plan->has_discount)
                                                                                    <span class="ms-1 text-[10px] font-black text-emerald-600 dark:text-emerald-400">{{ fa_num($plan->discount_percent) }}٪ تخفیف</span>
                                                                                @endif
                                                                            </td>
                                                                            <td class="text-xs text-zinc-600 dark:text-zinc-300">{{ $plan->duration_label }}</td>
                                                                            <td>
                                                                                <span class="text-xs font-bold tabular-nums text-zinc-800 dark:text-zinc-100">{{ $plan->final_price > 0 ? fa_num(money($plan->final_price)) : 'رایگان' }}</span>
                                                                                @if($plan->has_discount)
                                                                                    <span class="block text-[10px] tabular-nums text-zinc-400 line-through">{{ fa_num(money($plan->price, false)) }}</span>
                                                                                @endif
                                                                            </td>
                                                                            <td class="text-xs text-zinc-600 dark:text-zinc-300">{{ $plan->is_one_time ? 'یک‌بارمصرف' : 'اشتراکی' }}</td>
                                                                            <td>
                                                                                <x-badge variant="{{ $plan->is_active ? 'success' : 'neutral' }}">{{ $plan->is_active ? 'فعال' : 'غیرفعال' }}</x-badge>
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    @else
                                                        <p class="text-xs text-zinc-400">این پکیج طرح قیمتی ندارد{{ $package->is_free ? ' — رایگان است' : ' (قیمت پیش‌فرض: '.fa_num(money($package->default_price)).')' }}.</p>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-zinc-100 px-4 py-3 dark:border-zinc-800">
                    {{ $this->records->links() }}
                </div>
            @else
                <x-empty icon="package" title="پکیجی یافت نشد" description="با فیلترها یا جستجو بازی کنید، یا از بخش «پکیج‌ها» اولین پکیج را بسازید." />
            @endif
        </div>
    @elseif($tab === 'products')
        {{-- ================= محصولات ================= --}}
        <div class="card overflow-hidden">
            @if($this->records->count())
                <div class="table-wrap ring-0">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>محصول</th>
                                <th>وضعیت</th>
                                <th>نسخه‌ها</th>
                                <th>قیمت</th>
                                <th class="hidden lg:table-cell">فروش</th>
                                <th class="hidden xl:table-cell">بازدید</th>
                                <th class="w-24">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->records as $product)
                                @php($expanded = $expandedId === $product->id)
                                <tr wire:key="prod-{{ $product->id }}" @class(['bg-brand-50/40 dark:bg-brand-500/5' => $expanded])>
                                    <td>
                                        <div class="flex flex-col items-start gap-1">
                                            <span class="text-sm font-bold text-zinc-800 dark:text-zinc-100">{{ $product->name }}</span>
                                            <span class="font-mono text-[11px] text-zinc-400" dir="ltr">{{ $product->slug }}</span>
                                            @if($product->category)
                                                <span class="inline-flex items-center gap-1 rounded-md bg-zinc-100 px-1.5 py-0.5 text-[10px] font-bold text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                                                    <x-icon name="tag" class="size-3" />
                                                    {{ $product->category->name }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <x-badge variant="{{ match($product->status) { 'active' => 'success', 'draft' => 'warning', default => 'neutral' } }}">
                                            {{ match($product->status) { 'active' => 'فعال', 'draft' => 'پیش‌نویس', 'archived' => 'آرشیو', default => $product->status } }}
                                        </x-badge>
                                    </td>
                                    <td>
                                        <div class="flex flex-col items-start gap-1">
                                            <span class="inline-flex items-center gap-1 text-sm font-bold tabular-nums text-zinc-800 dark:text-zinc-100">
                                                <x-icon name="git-branch" class="size-3.5 shrink-0 text-zinc-400" />
                                                {{ fa_num($product->versions_count) }} نسخه
                                            </span>
                                            @if($product->versions_count > 0)
                                                <span class="text-[11px] tabular-nums text-zinc-500 dark:text-zinc-400">
                                                    فعال <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ fa_num($product->active_versions_count) }}</span>
                                                    @if($product->draft_versions_count > 0) · پیش‌نویس {{ fa_num($product->draft_versions_count) }} @endif
                                                    @if($product->archived_versions_count > 0) · آرشیو {{ fa_num($product->archived_versions_count) }} @endif
                                                </span>
                                                @if($product->latestVersion)
                                                    <x-badge variant="neutral"><span class="font-mono" dir="ltr">v{{ $product->latestVersion->version }}</span></x-badge>
                                                @endif
                                            @else
                                                <span class="text-[11px] text-zinc-400">بدون نسخه</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex flex-col items-start gap-1">
                                            @if($product->is_effectively_free)
                                                <x-badge variant="success" icon="gift">رایگان</x-badge>
                                            @else
                                                <span class="text-sm font-bold tabular-nums text-zinc-800 dark:text-zinc-100">{{ fa_num(money($product->final_price, false)) }}</span>
                                                @if($product->discount > 0 && $product->discount < $product->price)
                                                    <span class="text-[11px] tabular-nums text-zinc-400 line-through">{{ fa_num(money($product->price, false)) }}</span>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                    <td class="hidden lg:table-cell">
                                        <div class="flex flex-col items-start gap-1">
                                            <span class="inline-flex items-center gap-1 text-sm tabular-nums text-zinc-600 dark:text-zinc-300">
                                                <x-icon name="receipt" class="size-3.5 shrink-0 text-zinc-400" />
                                                {{ fa_num($product->paid_purchases_count) }} فروش
                                            </span>
                                            @if($product->paid_revenue_sum > 0)
                                                <span class="text-[11px] font-bold tabular-nums text-emerald-600 dark:text-emerald-400" title="درآمد پرداخت‌شده">
                                                    {{ fa_num(money($product->paid_revenue_sum, false)) }} تومان
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="hidden xl:table-cell">
                                        <span class="inline-flex items-center gap-1 text-sm tabular-nums text-zinc-600 dark:text-zinc-300">
                                            <x-icon name="eye" class="size-3.5 shrink-0 text-zinc-400" />
                                            {{ fa_num($product->views_count) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="flex flex-col items-end gap-1">
                                            <button type="button" wire:click="toggleExpand({{ $product->id }})"
                                                    class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10 dark:hover:text-brand-400"
                                                    title="{{ $expanded ? 'بستن جزئیات' : 'جزئیات کامل (فهرست نسخه‌ها)' }}"
                                                    aria-expanded="{{ $expanded ? 'true' : 'false' }}">
                                                <x-icon name="{{ $expanded ? 'chevron-up' : 'chevron-down' }}" class="size-4.5" />
                                            </button>
                                            <a href="{{ route('admin.products.show', $product) }}" wire:navigate
                                               class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                                               title="صفحهٔ کامل محصول">
                                                <x-icon name="external-link" class="size-4.5" />
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                @if($expanded)
                                    <tr wire:key="prod-exp-{{ $product->id }}">
                                        <td colspan="7" class="bg-zinc-50/70 p-0 dark:bg-zinc-900/50">
                                            <div class="border-s-2 border-brand-400/60 p-4">
                                                <div class="card p-4">
                                                    <p class="mb-3 flex items-center gap-2 text-sm font-black text-zinc-800 dark:text-zinc-100">
                                                        <x-icon name="git-branch" class="size-4 text-brand-500" />
                                                        نسخه‌های «{{ $product->name }}» ({{ fa_num($product->versions_count) }})
                                                    </p>
                                                    @if($product->versions->sortByDesc('id')->isNotEmpty())
                                                        <div class="max-h-64 overflow-y-auto">
                                                            <table class="table">
                                                                <thead>
                                                                    <tr>
                                                                        <th>نسخه</th>
                                                                        <th>نوع</th>
                                                                        <th>وضعیت</th>
                                                                        <th class="hidden sm:table-cell">انتشار</th>
                                                                        <th class="hidden sm:table-cell">حجم</th>
                                                                        <th>دانلود</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach($product->versions->sortByDesc('id') as $v)
                                                                        <tr wire:key="prodv-{{ $v->id }}">
                                                                            <td>
                                                                                <span class="font-mono text-xs font-bold text-zinc-800 dark:text-zinc-100" dir="ltr">v{{ $v->version }}</span>
                                                                                @if($v->is_mandatory)
                                                                                    <span class="ms-1 rounded-md bg-rose-50 px-1 py-0.5 text-[10px] font-black text-rose-600 dark:bg-rose-500/10 dark:text-rose-400">اجباری</span>
                                                                                @endif
                                                                            </td>
                                                                            <td class="text-xs text-zinc-600 dark:text-zinc-300">{{ match($v->type) { 'major' => 'بزرگ', 'minor' => 'متوسط', 'patch' => 'ترمیمی', default => $v->type } }}</td>
                                                                            <td>
                                                                                <x-badge variant="{{ match($v->status) { 'active' => 'success', 'draft' => 'warning', default => 'neutral' } }}">
                                                                                    {{ match($v->status) { 'active' => 'فعال', 'draft' => 'پیش‌نویس', 'archived' => 'آرشیو', default => $v->status } }}
                                                                                </x-badge>
                                                                            </td>
                                                                            <td class="hidden text-xs tabular-nums text-zinc-500 sm:table-cell">{{ $v->release_date ? verta_date($v->release_date) : '—' }}</td>
                                                                            <td class="hidden text-xs tabular-nums text-zinc-500 sm:table-cell" dir="ltr">{{ $v->file_size_human }}</td>
                                                                            <td class="text-xs tabular-nums text-zinc-600 dark:text-zinc-300">{{ fa_num($v->downloads_count) }}</td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    @else
                                                        <p class="text-xs text-zinc-400">این محصول هنوز نسخه‌ای ندارد.</p>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-zinc-100 px-4 py-3 dark:border-zinc-800">
                    {{ $this->records->links() }}
                </div>
            @else
                <x-empty icon="rocket" title="محصولی یافت نشد" description="با فیلترها یا جستجو بازی کنید، یا از بخش «محصولات» اولین محصول را بسازید." />
            @endif
        </div>
    @else
        {{-- ================= لایسنس‌ها ================= --}}
        <div class="card overflow-hidden">
            @if($this->records->count())
                @if($license_type === 'package')
                    <div class="table-wrap ring-0">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>لایسنس</th>
                                    <th>پکیج</th>
                                    <th>مشتری</th>
                                    <th>وضعیت</th>
                                    <th class="hidden lg:table-cell">اعتبار</th>
                                    <th class="hidden xl:table-cell">تراکنش</th>
                                    <th class="w-16">عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($this->records as $license)
                                    <tr wire:key="lic-{{ $license->id }}">
                                        <td>
                                            <span class="font-mono text-xs font-bold text-zinc-800 dark:text-zinc-100" dir="ltr">{{ $license->license_key }}</span>
                                        </td>
                                        <td>
                                            <span class="inline-flex items-center gap-1 text-xs font-bold text-zinc-700 dark:text-zinc-200">
                                                <x-icon name="package" class="size-3.5 shrink-0 text-zinc-400" />
                                                {{ $license->package?->name ?? '—' }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="flex flex-col items-start gap-0.5">
                                                <span class="text-xs font-bold text-zinc-700 dark:text-zinc-200">{{ $license->customer?->name ?? '—' }}</span>
                                                @if($license->customer?->phone)
                                                    <span class="text-[11px] tabular-nums text-zinc-400" dir="ltr">{{ $license->customer->phone }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <x-badge variant="{{ match($license->status) { 'active' => 'success', 'expired' => 'warning', default => 'danger' } }}">
                                                {{ match($license->status) { 'active' => 'فعال', 'expired' => 'منقضی', 'revoked' => 'باطل‌شده', default => $license->status } }}
                                            </x-badge>
                                        </td>
                                        <td class="hidden lg:table-cell">
                                            <div class="flex flex-col items-start gap-0.5 text-[11px] tabular-nums text-zinc-500 dark:text-zinc-400">
                                                <span>از {{ $license->starts_at ? verta_date($license->starts_at) : '—' }}</span>
                                                <span>تا <span class="{{ $license->isExpired() ? 'font-bold text-rose-600 dark:text-rose-400' : '' }}">{{ $license->isUnlimited() ? 'نامحدود' : ($license->expires_at ? verta_date($license->expires_at) : '—') }}</span></span>
                                            </div>
                                        </td>
                                        <td class="hidden xl:table-cell">
                                            <span class="font-mono text-[11px] text-zinc-400" dir="ltr">{{ $license->purchase?->transaction_id ?? '—' }}</span>
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.licenses.show', $license) }}" wire:navigate
                                               class="inline-flex rounded-lg p-2 text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                                               title="صفحهٔ کامل لایسنس">
                                                <x-icon name="external-link" class="size-4.5" />
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="table-wrap ring-0">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>لایسنس</th>
                                    <th>قالب</th>
                                    <th>مشتری</th>
                                    <th>وضعیت</th>
                                    <th class="hidden lg:table-cell">اعتبار</th>
                                    <th class="hidden xl:table-cell">تراکنش</th>
                                    <th class="w-16">عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($this->records as $license)
                                    <tr wire:key="tlic-{{ $license->id }}">
                                        <td>
                                            <span class="font-mono text-xs font-bold text-zinc-800 dark:text-zinc-100" dir="ltr">{{ $license->license_key }}</span>
                                        </td>
                                        <td>
                                            <span class="inline-flex items-center gap-1 text-xs font-bold text-zinc-700 dark:text-zinc-200">
                                                <x-icon name="layout-template" class="size-3.5 shrink-0 text-zinc-400" />
                                                {{ $license->template?->name ?? '—' }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="flex flex-col items-start gap-0.5">
                                                <span class="text-xs font-bold text-zinc-700 dark:text-zinc-200">{{ $license->customer?->name ?? '—' }}</span>
                                                @if($license->customer?->phone)
                                                    <span class="text-[11px] tabular-nums text-zinc-400" dir="ltr">{{ $license->customer->phone }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <x-badge variant="{{ match($license->status) { 'active' => 'success', 'expired' => 'warning', default => 'danger' } }}">
                                                {{ match($license->status) { 'active' => 'فعال', 'expired' => 'منقضی', 'revoked' => 'باطل‌شده', default => $license->status } }}
                                            </x-badge>
                                        </td>
                                        <td class="hidden lg:table-cell">
                                            <div class="flex flex-col items-start gap-0.5 text-[11px] tabular-nums text-zinc-500 dark:text-zinc-400">
                                                <span>از {{ $license->starts_at ? verta_date($license->starts_at) : '—' }}</span>
                                                <span>تا {{ $license->expires_at ? verta_date($license->expires_at) : 'نامحدود' }}</span>
                                            </div>
                                        </td>
                                        <td class="hidden xl:table-cell">
                                            <span class="font-mono text-[11px] text-zinc-400" dir="ltr">{{ $license->purchase?->transaction_id ?? '—' }}</span>
                                        </td>
                                        <td>
                                            {{-- ⚠️ admin.licenses.show فقط PackageLicense می‌بیند (جدول دیگر = تداخل شناسه)؛
                                                 لایسنس قالب به صفحهٔ اختصاصی لایسنس‌ها با تب قالب و جستجوی همان کلید می‌رود --}}
                                            <a href="{{ route('admin.licenses.index', ['type' => 'template', 'search' => $license->license_key]) }}" wire:navigate
                                               class="inline-flex rounded-lg p-2 text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                                               title="مدیریت در صفحهٔ لایسنس‌ها (تب قالب‌ها)">
                                                <x-icon name="external-link" class="size-4.5" />
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                <div class="border-t border-zinc-100 px-4 py-3 dark:border-zinc-800">
                    {{ $this->records->links() }}
                </div>
            @else
                <x-empty icon="key" title="لایسنسی یافت نشد" description="لایسنس‌ها پس از اولین خرید در اینجا نمایش داده می‌شوند." />
            @endif
        </div>
    @endif
</div>
