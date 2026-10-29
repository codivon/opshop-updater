<div class="space-y-6">
    {{-- header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="flex items-center gap-2 text-lg font-black text-zinc-900 dark:text-zinc-50">
                <x-icon name="crown" class="size-5 text-brand-600 dark:text-brand-400" />
                طرح‌های اشتراک
            </h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">طرح‌هایی که مشتریان از فروشگاه خریداری می‌کنند؛ رایگان یا پولی، همراه پکیج‌های دسترسی رایگان و قابلیت‌ها.</p>
        </div>
        {{-- فرم در کامپوننت مجزاست (Plans\Form) — بدون رفت‌وبرگشت لیست باز می‌شود (رفع کندی) --}}
        <x-btn icon="plus" wire:click="$dispatch('plan-form-open')" wire:loading.attr="disabled" wire:target="showModal">طرح جدید</x-btn>
    </div>

    {{-- stats --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-stat icon="crown" :value="fa_num(\App\Models\SubscriptionPlan::count())" label="کل طرح‌ها" />
        <x-stat icon="check-circle" :value="fa_num(\App\Models\SubscriptionPlan::where('is_active', true)->count())" label="طرح‌های فعال" />
        <x-stat icon="banknote" :value="fa_num(\App\Models\SubscriptionPlan::where('price', 0)->count())" label="طرح‌های رایگان" />
    </div>

    {{-- filters --}}
    <div class="card flex flex-col gap-3 p-4 lg:flex-row lg:items-center">
        <div class="relative flex-1">
            <x-icon name="search" class="pointer-events-none absolute start-3.5 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
            <input type="search" wire:model.live.debounce.400ms="search" placeholder="جستجوی نام، شناسه یا توضیحات طرح…" class="input ps-10" />
        </div>
        <select wire:model.live="status" class="input lg:w-40">
            <option value="">همه وضعیت‌ها</option>
            <option value="active">فعال</option>
            <option value="inactive">غیرفعال</option>
        </select>
        <div class="relative lg:w-48">
            <x-icon name="arrow-up-down" class="pointer-events-none absolute start-3.5 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
            <select wire:model.live="sort" class="input ps-10" aria-label="ترتیب نمایش">
                <option value="newest">جدیدترین</option>
                <option value="oldest">قدیمی‌ترین</option>
                <option value="id_desc">شناسه (نزولی)</option>
                <option value="id_asc">شناسه (صعودی)</option>
                <option value="name_asc">بر اساس نام (الفبا)</option>
                <option value="name_desc">بر اساس نام (معکوس)</option>
                <option value="price_desc">گران‌ترین</option>
                <option value="price_asc">ارزان‌ترین</option>
                <option value="sort_order">ترتیب نمایش فرانت</option>
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
                {{ fa_num(count($selectedIds)) }} طرح انتخاب شده است
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
                            <th>طرح</th>
                            <th>مدت اعتبار</th>
                            <th>قیمت</th>
                            <th class="hidden lg:table-cell">خریدها</th>
                            <th>وضعیت</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->records as $plan)
                            <tr wire:key="plan-{{ $plan->id }}">
                                <td>
                                    <input type="checkbox" class="checkbox" aria-label="انتخاب این طرح"
                                           wire:model.live="selectedIds" value="{{ $plan->id }}" />
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <x-icon name="crown" class="size-4 shrink-0 text-amber-500" />
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-bold text-zinc-800 dark:text-zinc-100">{{ $plan->name }}</p>
                                            <p class="truncate text-xs text-zinc-400" dir="ltr">{{ $plan->slug }}</p>
                                        </div>
                                    </div>
                                    @if($plan->description)
                                        <p class="mt-1 max-w-xs truncate text-xs text-zinc-500 dark:text-zinc-400">{{ \Illuminate\Support\Str::limit($plan->description, 60) }}</p>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap">
                                    @if($plan->durations->where('is_active', true)->isNotEmpty())
                                        {{-- چندگزینه‌ای: چیپ هر مدت + قیمت نهایی آن --}}
                                        <div class="flex max-w-xs flex-wrap items-center gap-1">
                                            @foreach($plan->durations->filter(fn ($d) => $d->is_active)->take(4) as $duration)
                                                <span class="inline-flex items-center gap-1 rounded-lg bg-zinc-100 px-2 py-0.5 text-[11px] font-bold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300"
                                                      title="{{ fa_num(money($duration->final_price)) }} تومان">
                                                    {{ $duration->duration_label }}
                                                    <span class="tabular-nums text-zinc-400">{{ $duration->final_price <= 0 ? 'رایگان' : fa_num(money($duration->final_price)) }}</span>
                                                    {{-- پکیج‌های اختصاصی همین مدت (جای ستون حذف‌شدهٔ «پکیج‌های همراه» — Task 64) --}}
                                                    @if($duration->hasOwnPackages())
                                                        <span class="inline-flex items-center gap-0.5 rounded-md bg-emerald-100 px-1 py-0.5 text-[10px] font-black text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400"
                                                              title="پکیج‌های رایگان این مدت">
                                                            <x-icon name="package" class="size-3" />
                                                            {{ fa_num($duration->packages->count()) }}
                                                        </span>
                                                    @endif
                                                </span>
                                            @endforeach
                                            @if($plan->durations->where('is_active', true)->count() > 4)
                                                <span class="text-[11px] font-bold text-zinc-400">+{{ fa_num($plan->durations->where('is_active', true)->count() - 4) }}</span>
                                            @endif
                                            {{-- پکیج‌های پیش‌فرض طرح (فال‌بک مدت‌های بدون پیکربندی اختصاصی) --}}
                                            @if($plan->packages_count > 0)
                                                <span class="inline-flex items-center gap-1 text-[10px] font-medium text-emerald-600 dark:text-emerald-400"
                                                      title="پکیج‌های پیش‌فرض طرح — برای مدت‌هایی که پیکربندی اختصاصی ندارند">
                                                    <x-icon name="package" class="size-3" />
                                                    {{ fa_num($plan->packages_count) }} پکیج پیش‌فرض
                                                </span>
                                            @endif
                                        </div>
                                    @elseif((int) $plan->duration_months === 0)
                                        <span class="inline-flex items-center gap-1 text-xs font-bold text-brand-600 dark:text-brand-400">
                                            <x-icon name="infinity" class="size-3.5" />
                                            نامحدود
                                        </span>
                                    @else
                                        <span class="text-sm tabular-nums text-zinc-600 dark:text-zinc-300">{{ fa_num($plan->duration_months) }} ماه</span>
                                    @endif
                                    @if($plan->is_one_time)
                                        <div class="mt-1"><x-badge variant="warning">یک‌بارمصرف</x-badge></div>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap">
                                    @if($plan->final_price <= 0)
                                        <x-badge variant="success" icon="gift">رایگان</x-badge>
                                    @else
                                        <span class="text-sm font-bold tabular-nums text-zinc-800 dark:text-zinc-100">{{ fa_num(money($plan->final_price)) }}</span>
                                        @if($plan->discount_price)
                                            <p class="text-xs tabular-nums text-zinc-400 line-through">{{ fa_num(money($plan->price)) }}</p>
                                        @endif
                                    @endif
                                </td>
                                <td class="hidden whitespace-nowrap text-sm tabular-nums text-zinc-600 lg:table-cell dark:text-zinc-300">
                                    {{ fa_num($plan->paid_orders_count) }}
                                </td>
                                <td>
                                    @if($plan->features && count($plan->features))
                                        <span class="me-1 inline-flex items-center gap-1 text-xs text-zinc-400" title="{{ implode(' · ', $plan->features) }}">
                                            <x-icon name="list-checks" class="size-3.5" />
                                            {{ fa_num(count($plan->features)) }} قابلیت
                                        </span>
                                    @endif
                                    <x-badge variant="{{ $plan->is_active ? 'success' : 'neutral' }}" class="mt-1">
                                        {{ $plan->is_active ? 'فعال' : 'غیرفعال' }}
                                    </x-badge>
                                </td>
                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        <button type="button" wire:click="$dispatch('plan-form-open', { id: {{ $plan->id }} })"
                                                class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10 dark:hover:text-brand-400"
                                                title="ویرایش">
                                            <x-icon name="pencil" class="size-4.5" />
                                        </button>
                                        {{-- کپی کامل طرح: همهٔ مدت‌ها + قابلیت‌ها + پکیج‌های هر مدت (Task 65) --}}
                                        <button type="button" wire:click="copy({{ $plan->id }})"
                                                wire:loading.attr="disabled" wire:target="copy({{ $plan->id }})"
                                                class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-sky-50 hover:text-sky-600 dark:hover:bg-sky-500/10 dark:hover:text-sky-400"
                                                title="کپی طرح (با همهٔ مدت‌ها، قابلیت‌ها و پکیج‌ها)">
                                            <x-icon name="copy" class="size-4.5" />
                                        </button>
                                        <button type="button" wire:click="toggleActive({{ $plan->id }})"
                                                class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-amber-50 hover:text-amber-600 dark:hover:bg-amber-500/10 dark:hover:text-amber-400"
                                                title="{{ $plan->is_active ? 'غیرفعال کردن' : 'فعال کردن' }}">
                                            <x-icon name="{{ $plan->is_active ? 'x-circle' : 'check-circle' }}" class="size-4.5" />
                                        </button>
                                        <button type="button" wire:click="$set('deleteId', {{ $plan->id }})"
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
            <x-empty icon="crown" title="طرحی یافت نشد" description="اولین طرح اشتراک را بسازید تا در فروشگاه به مشتریان نمایش داده شود." />
        @endif
    </div>

    {{-- فرم افزودن/ویرایش (کامپوننت مجزا Plans\Form) — تعامل‌های داخل مودال فقط مودال را رندر می‌کنند، نه جدول (رفع کندی — Task 64) --}}
    <livewire:plans.form />

    {{-- delete confirm --}}
    <x-modal wire:model="deleteId" title="حذف طرح اشتراک" size="sm">
        @if($deleteId)
            @php($target = \App\Models\SubscriptionPlan::find($deleteId))
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-rose-50 p-4 text-rose-700 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20">
                    <x-icon name="trash" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">
                        طرح «<strong>{{ $target?->name }}</strong>» حذف شود؟
                        سفارش‌های ثبت‌شده حفظ می‌شوند اما به «طرح حذف‌شده» تبدیل می‌گردند.
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
    <x-modal wire:model="confirmingBulkDelete" title="حذف گروهی طرح‌ها" size="sm">
        @if($confirmingBulkDelete)
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-rose-50 p-4 text-rose-700 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20">
                    <x-icon name="trash" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">
                        <strong>{{ fa_num(count($selectedIds)) }}</strong> طرح انتخاب‌شده برای همیشه حذف شود؟
                    </p>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <x-btn variant="secondary" wire:click="clearSelection">انصراف</x-btn>
                    <x-btn variant="danger" icon="trash" wire:click="bulkDelete" :loading="true">حذف {{ fa_num(count($selectedIds)) }} طرح</x-btn>
                </div>
            </div>
        @endif
    </x-modal>
</div>
