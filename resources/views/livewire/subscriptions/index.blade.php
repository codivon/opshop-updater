@php
    // گزینه‌های فرم: در حالت ایجاد فقط مشتریان/پروژه‌های فعال (مطابق کنترلر قدیمی)
    $customerOptions = $this->customers
        ->when(! $editingId, fn ($c) => $c->where('status', 'active'))
        ->mapWithKeys(fn ($c) => [$c->id => $c->name])
        ->all();
    $projectOptions = $this->projects
        ->when(! $editingId, fn ($p) => $p->where('status', 'active'))
        ->mapWithKeys(fn ($p) => [$p->id => $p->name])
        ->all();

    $statusLabels = ['active' => 'فعال', 'expired' => 'منقضی شده', 'suspended' => 'تعلیق شده'];
    $statusVariants = ['active' => 'success', 'expired' => 'danger', 'suspended' => 'danger'];
    $paymentLabels = ['paid' => 'پرداخت شده', 'pending' => 'در انتظار پرداخت', 'failed' => 'ناموفق'];
    $paymentVariants = ['paid' => 'success', 'pending' => 'warning', 'failed' => 'danger'];
@endphp

<div class="space-y-6">
    {{-- header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-black text-zinc-900 dark:text-zinc-50">اشتراک‌ها</h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">مدیریت اشتراک‌های پروژه‌ها، تمدید و وضعیت پرداخت مشتریان.</p>
        </div>
        <x-btn icon="plus" wire:click="openCreate">اشتراک جدید</x-btn>
    </div>

    {{-- filters --}}
    <div class="card flex flex-col gap-3 p-4 xl:flex-row xl:items-center">
        <div class="relative flex-1">
            <x-icon name="search" class="pointer-events-none absolute start-3.5 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
            <input type="search" wire:model.live.debounce.400ms="search" placeholder="جستجوی مشتری، پروژه یا توضیحات…" class="input ps-10" />
        </div>
        <select wire:model.live="status" class="input xl:w-44">
            <option value="">همه وضعیت‌ها</option>
            <option value="active">فعال</option>
            <option value="expired">منقضی شده</option>
            <option value="suspended">تعلیق شده</option>
        </select>
        <select wire:model.live="customer_id" class="input xl:w-48">
            <option value="">همه مشتریان</option>
            @foreach($this->customers as $c)
                <option value="{{ $c->id }}">{{ $c->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="project_id" class="input xl:w-48">
            <option value="">همه پروژه‌ها</option>
            @foreach($this->projects as $p)
                <option value="{{ $p->id }}">{{ $p->name }}</option>
            @endforeach
        </select>
        <div class="relative xl:w-44">
            <x-icon name="arrow-up-down" class="pointer-events-none absolute start-3.5 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
            <select wire:model.live="sort" class="input ps-10" aria-label="ترتیب نمایش">
                <option value="newest">جدیدترین</option>
                <option value="oldest">قدیمی‌ترین</option>
                <option value="id_desc">شناسه (نزولی)</option>
                <option value="id_asc">شناسه (صعودی)</option>
                <option value="expires_soon">نزدیک‌ترین انقضا</option>
                <option value="expires_late">دورترین انقضا</option>
                <option value="newest_start">بر اساس تاریخ شروع (جدید)</option>
                <option value="oldest_start">بر اساس تاریخ شروع (قدیمی)</option>
            </select>
        </div>
        <div wire:loading wire:target="search, status, customer_id, project_id, sort" class="flex items-center gap-2 text-xs text-brand-600 dark:text-brand-400">
            <x-icon name="loader" class="size-4 animate-spin" />
            در حال فیلتر…
        </div>
    </div>

    {{-- bulk toolbar --}}
    @if($selectedIds)
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-rose-50 p-3 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:ring-rose-400/20">
            <div class="flex items-center gap-2 text-sm font-bold text-rose-700 dark:text-rose-400">
                <x-icon name="check-square" class="size-4.5" />
                {{ fa_num(count($selectedIds)) }} اشتراک انتخاب شده است
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
                            <th class="hidden md:table-cell">پروژه</th>
                            <th>مبلغ</th>
                            <th class="hidden lg:table-cell">دوره</th>
                            <th class="hidden sm:table-cell">پرداخت</th>
                            <th>وضعیت</th>
                            <th>باقی‌مانده</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->records as $sub)
                            @php
                                $expiry = $sub->expires_at ?: $sub->end_date;
                                $remaining = $expiry ? (int) floor(now()->diffInDays($expiry)) : null;
                            @endphp
                            <tr wire:key="subscription-{{ $sub->id }}">
                                <td>
                                    <input type="checkbox" class="checkbox" aria-label="انتخاب این اشتراک"
                                           wire:model.live="selectedIds" value="{{ $sub->id }}" />
                                </td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <x-avatar :name="$sub->customer?->name ?? '؟'" />
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-zinc-800 dark:text-zinc-100">{{ $sub->customer?->name ?? '—' }}</p>
                                            <p class="truncate text-xs text-zinc-400" dir="ltr">{{ $sub->customer?->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="hidden md:table-cell">
                                    <div class="flex items-center gap-2">
                                        <x-icon name="folder" class="size-4 shrink-0 text-zinc-400" />
                                        <span class="truncate font-medium text-zinc-700 dark:text-zinc-200">{{ $sub->project?->name ?? '—' }}</span>
                                    </div>
                                </td>
                                <td>
                                    @if((float) $sub->discount > 0)
                                        <p class="text-xs font-medium tabular-nums text-amber-600 line-through dark:text-amber-400" dir="ltr">{{ money($sub->price, false) }}</p>
                                    @endif
                                    <p class="font-semibold tabular-nums text-zinc-800 dark:text-zinc-100">{{ money($sub->final_amount) }}</p>
                                    @if((float) $sub->discount > 0)
                                        <p class="text-[11px] tabular-nums text-amber-600 dark:text-amber-400">تخفیف {{ money($sub->discount, false) }}</p>
                                    @endif
                                </td>
                                <td class="hidden lg:table-cell">
                                    <div class="flex items-center gap-1.5 text-sm tabular-nums text-zinc-600 dark:text-zinc-300">
                                        <span>{{ verta_date($sub->start_date) }}</span>
                                        <x-icon name="arrow-left" class="size-3.5 shrink-0 text-zinc-400" />
                                        <span>{{ verta_date($sub->end_date ?: $sub->expires_at) }}</span>
                                    </div>
                                    @if($sub->start_date)
                                        <p class="mt-0.5 text-xs text-zinc-400">{{ verta_from_now($sub->start_date) }}</p>
                                    @endif
                                </td>
                                <td class="hidden sm:table-cell">
                                    <x-badge :variant="$paymentVariants[$sub->payment_status] ?? 'neutral'">{{ $paymentLabels[$sub->payment_status] ?? $sub->payment_status }}</x-badge>
                                </td>
                                <td>
                                    <x-badge :variant="$statusVariants[$sub->status] ?? 'neutral'">{{ $statusLabels[$sub->status] ?? $sub->status }}</x-badge>
                                </td>
                                <td>
                                    @if($remaining === null)
                                        <x-badge variant="neutral">نامحدود</x-badge>
                                    @elseif($remaining < 0)
                                        <x-badge variant="danger" icon="alert-circle">منقضی شده</x-badge>
                                    @elseif($remaining < 14)
                                        <x-badge variant="warning" icon="clock">{{ fa_num($remaining) }} روز باقی‌مانده</x-badge>
                                    @else
                                        <x-badge variant="neutral" icon="clock">{{ fa_num($remaining) }} روز</x-badge>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        <button type="button" wire:click="openEdit({{ $sub->id }})"
                                                class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10 dark:hover:text-brand-400"
                                                title="ویرایش">
                                            <x-icon name="pencil" class="size-4.5" />
                                        </button>
                                        <button type="button" wire:click="openExtend({{ $sub->id }})"
                                                class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10 dark:hover:text-brand-400"
                                                title="تمدید">
                                            <x-icon name="refresh-cw" class="size-4.5" />
                                        </button>
                                        <button type="button" wire:click="$set('deleteId', {{ $sub->id }})"
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
            <x-empty icon="credit-card" title="اشتراکی یافت نشد" description="با ثبت اولین اشتراک شروع کنید یا فیلترها را تغییر دهید.">
                <x-btn variant="soft" icon="plus" wire:click="openCreate">ثبت اشتراک</x-btn>
            </x-empty>
        @endif
    </div>

    {{-- create / edit modal --}}
    <x-modal wire:model="showModal" :title="$editingId ? 'ویرایش اشتراک' : 'اشتراک جدید'" subtitle="مبلغ نهایی به‌صورت خودکار از قیمت منهای تخفیف محاسبه می‌شود." size="lg">
        <form wire:submit="save" class="space-y-5">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-field label="مشتری" required>
                    <x-select wire:model="form.customer_id" :options="$customerOptions" placeholder="انتخاب مشتری…" :class="$errors->has('form.customer_id') ? 'input-error' : ''" />
                </x-field>

                <x-field label="پروژه" required>
                    <x-select wire:model="form.project_id" :options="$projectOptions" placeholder="انتخاب پروژه…" :class="$errors->has('form.project_id') ? 'input-error' : ''" />
                </x-field>

                <x-field label="قیمت (تومان)" required>
                    <x-input id="form.price" wire:model="form.price" type="number" min="0" step="any" inputmode="numeric" placeholder="350000" icon="banknote" dir="ltr" :class="$errors->has('form.price') ? 'input-error' : ''" />
                    <x-money-preview target="form.price" />
                </x-field>

                <x-field label="تخفیف (تومان)">
                    <x-input id="form.discount" wire:model="form.discount" type="number" min="0" step="any" inputmode="numeric" placeholder="0" icon="tag" dir="ltr" :class="$errors->has('form.discount') ? 'input-error' : ''" />
                    <x-money-preview target="form.discount" suffix="تومان تخفیف" />
                </x-field>

                <x-field label="تاریخ شروع" required>
                    <x-input wire:model="form.start_date" type="date" icon="calendar" dir="ltr" :class="$errors->has('form.start_date') ? 'input-error' : ''" />
                </x-field>

                <x-field label="تاریخ پایان" required>
                    <x-input wire:model="form.end_date" type="date" icon="calendar" dir="ltr" :class="$errors->has('form.end_date') ? 'input-error' : ''" />
                </x-field>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-field label="وضعیت پرداخت" required>
                    <x-select wire:model="form.payment_status" :options="[
                        'pending' => 'در انتظار پرداخت',
                        'paid' => 'پرداخت شده',
                        'failed' => 'ناموفق',
                    ]" :class="$errors->has('form.payment_status') ? 'input-error' : ''" />
                </x-field>

                <x-field label="وضعیت اشتراک" required>
                    <x-select wire:model="form.status" :options="[
                        'active' => 'فعال',
                        'expired' => 'منقضی شده',
                        'suspended' => 'تعلیق شده',
                    ]" :class="$errors->has('form.status') ? 'input-error' : ''" />
                </x-field>
            </div>

            <x-field label="توضیحات">
                <x-textarea wire:model="form.description" rows="3" placeholder="مثلاً اشتراک سالانه حرفه‌ای با پشتیبانی اولویت‌دار…" :class="$errors->has('form.description') ? 'input-error' : ''" />
            </x-field>

            <div class="flex items-center justify-end gap-2 pt-2">
                <x-btn variant="secondary" wire:click="$set('showModal', false)">انصراف</x-btn>
                <x-btn type="submit" icon="save" :loading="true">ذخیره</x-btn>
            </div>
        </form>
    </x-modal>

    {{-- extend modal --}}
    <x-modal wire:model="extendId" title="تمدید اشتراک" subtitle="با تمدید، تاریخ انقضا به اندازه مدت انتخابی افزایش می‌یابد و اشتراک فعال می‌شود." size="sm">
        @if($extendId)
            @php($target = \App\Models\Subscription::with(['customer:id,name', 'project:id,name'])->find($extendId))
            <form wire:submit="extend" class="space-y-5">
                <div class="space-y-3 rounded-xl bg-brand-50/70 p-4 ring-1 ring-brand-600/10 dark:bg-brand-500/10 dark:ring-brand-400/20">
                    <div class="flex items-center gap-3">
                        <x-avatar :name="$target?->customer?->name ?? '؟'" size="sm" />
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold text-zinc-800 dark:text-zinc-100">{{ $target?->customer?->name ?? '—' }}</p>
                            <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $target?->project?->name ?? '—' }}</p>
                        </div>
                    </div>
                    <dl class="grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <dt class="text-zinc-400 dark:text-zinc-500">تاریخ انقضای فعلی</dt>
                            <dd class="mt-0.5 font-semibold tabular-nums text-zinc-700 dark:text-zinc-200">
                                @if($target?->expires_at)
                                    {{ verta_date($target->expires_at) }}
                                    <span class="font-normal text-zinc-400">({{ verta_from_now($target->expires_at) }})</span>
                                @else
                                    نامحدود
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-zinc-400 dark:text-zinc-500">وضعیت فعلی</dt>
                            <dd class="mt-0.5 font-semibold text-zinc-700 dark:text-zinc-200">{{ $statusLabels[$target?->status] ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <x-field label="مدت تمدید (ماه)" required>
                    <x-input wire:model="extendMonths" type="number" min="1" max="36" inputmode="numeric" icon="calendar" dir="ltr" :class="$errors->has('extendMonths') ? 'input-error' : ''" />
                </x-field>

                <div class="flex items-center justify-end gap-2 pt-1">
                    <x-btn variant="secondary" wire:click="$set('extendId', null)">انصراف</x-btn>
                    <x-btn type="submit" icon="refresh-cw" :loading="true">تمدید اشتراک</x-btn>
                </div>
            </form>
        @endif
    </x-modal>

    {{-- delete confirm --}}
    <x-modal wire:model="deleteId" title="حذف اشتراک" size="sm">
        @if($deleteId)
            @php($target = \App\Models\Subscription::with(['customer:id,name', 'project:id,name'])->find($deleteId))
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-rose-50 p-4 text-rose-700 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20">
                    <x-icon name="alert-triangle" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">آیا از حذف اشتراک «<strong>{{ $target?->customer?->name }} — {{ $target?->project?->name }}</strong>» مطمئن هستید؟ این عمل قابل بازگشت نیست.</p>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <x-btn variant="secondary" wire:click="$set('deleteId', null)">انصراف</x-btn>
                    <x-btn variant="danger" icon="trash" wire:click="delete" :loading="true">حذف قطعی</x-btn>
                </div>
            </div>
        @endif
    </x-modal>

    {{-- bulk delete confirm --}}
    <x-modal wire:model="confirmingBulkDelete" title="حذف گروهی اشتراک‌ها" size="sm">
        @if($confirmingBulkDelete)
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-rose-50 p-4 text-rose-700 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20">
                    <x-icon name="trash" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">
                        <strong>{{ fa_num(count($selectedIds)) }}</strong> اشتراک انتخاب‌شده برای همیشه حذف شود؟
                        این عمل قابل بازگشت نیست.
                    </p>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <x-btn variant="secondary" wire:click="clearSelection">انصراف</x-btn>
                    <x-btn variant="danger" icon="trash" wire:click="bulkDelete" :loading="true">حذف {{ fa_num(count($selectedIds)) }} اشتراک</x-btn>
                </div>
            </div>
        @endif
    </x-modal>
</div>
