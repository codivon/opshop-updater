<div class="space-y-6">
    {{-- header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-black text-zinc-900 dark:text-zinc-50">مشتریان</h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">مدیریت مشتریان فروشگاه‌ها و کدهای دریافت آپدیت آن‌ها.</p>
        </div>
        <x-btn icon="user-plus" wire:click="openCreate">مشتری جدید</x-btn>
    </div>

    {{-- filters --}}
    <div class="card flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <x-icon name="search" class="pointer-events-none absolute start-3.5 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
            <input type="search" wire:model.live.debounce.400ms="search" placeholder="جستجوی نام، ایمیل، تلفن، سایت یا کد آپدیت…" class="input ps-10" />
        </div>
        <select wire:model.live="status" class="input sm:w-44">
            <option value="">همه وضعیت‌ها</option>
            <option value="active">فعال</option>
            <option value="inactive">غیرفعال</option>
        </select>
        <div class="relative sm:w-44">
            <x-icon name="arrow-up-down" class="pointer-events-none absolute start-3.5 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
            <select wire:model.live="sort" class="input ps-10" aria-label="ترتیب نمایش">
                <option value="newest">جدیدترین</option>
                <option value="oldest">قدیمی‌ترین</option>
                <option value="id_desc">شناسه (نزولی)</option>
                <option value="id_asc">شناسه (صعودی)</option>
                <option value="name_asc">بر اساس نام (الفبا)</option>
                <option value="name_desc">بر اساس نام (معکوس)</option>
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
                {{ fa_num(count($selectedIds)) }} مشتری انتخاب شده است
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
                            <th class="hidden lg:table-cell">تلفن</th>
                            <th class="hidden xl:table-cell">سایت</th>
                            <th>کد آپدیت</th>
                            <th class="hidden md:table-cell">اشتراک‌ها</th>
                            <th class="hidden lg:table-cell">مجموع پرداخت</th>
                            <th>وضعیت</th>
                            <th class="hidden sm:table-cell">تاریخ عضویت</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->records as $customer)
                            <tr wire:key="customer-{{ $customer->id }}">
                                <td>
                                    <input type="checkbox" class="checkbox" aria-label="انتخاب این مشتری"
                                           wire:model.live="selectedIds" value="{{ $customer->id }}" />
                                </td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <x-avatar :name="$customer->name" />
                                        <div class="min-w-0">
                                            <a href="{{ route('admin.customers.show', $customer) }}" wire:navigate
                                               class="truncate font-semibold text-zinc-800 transition-colors hover:text-brand-600 dark:text-zinc-100 dark:hover:text-brand-400"
                                               title="مشاهدهٔ پروندهٔ مشتری">
                                                {{ $customer->name }}
                                            </a>
                                            <p class="truncate text-xs text-zinc-400" dir="ltr">{{ $customer->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="hidden tabular-nums text-zinc-600 dark:text-zinc-300 lg:table-cell" dir="ltr">
                                    {{ $customer->phone ? fa_num($customer->phone) : '—' }}
                                </td>
                                <td class="hidden xl:table-cell">
                                    @if($customer->website_url)
                                        <a href="{{ $customer->website_url }}" target="_blank" rel="noopener"
                                           class="flex items-center gap-1 truncate text-xs text-zinc-500 transition-colors hover:text-brand-500 dark:text-zinc-400" dir="ltr">
                                            {{ \Illuminate\Support\Str::limit($customer->website_url, 28) }}
                                            <x-icon name="external-link" class="size-3" />
                                        </a>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex items-center gap-1.5" dir="ltr">
                                        <span class="font-mono text-xs font-semibold tracking-wider text-zinc-700 dark:text-zinc-200">{{ $customer->update_code }}</span>
                                        <button type="button" x-data title="کپی کد آپدیت"
                                                @click="navigator.clipboard.writeText('{{ $customer->update_code }}'); $dispatch('toast', {message: 'کد کپی شد', type: 'success'})"
                                                class="rounded-lg p-1.5 text-zinc-400 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10 dark:hover:text-brand-400">
                                            <x-icon name="copy" class="size-3.5" />
                                        </button>
                                    </div>
                                </td>
                                <td class="hidden tabular-nums text-zinc-600 dark:text-zinc-300 md:table-cell">{{ fa_num($customer->subscriptions_count) }}</td>
                                <td class="hidden tabular-nums font-semibold text-zinc-700 dark:text-zinc-200 lg:table-cell">{{ money($customer->total_spent ?? 0) }}</td>
                                <td>
                                    @php($variant = $customer->status === 'active' ? 'success' : 'danger')
                                    <x-badge :variant="$variant">{{ $customer->status === 'active' ? 'فعال' : 'غیرفعال' }}</x-badge>
                                </td>
                                <td class="hidden text-sm text-zinc-500 sm:table-cell">{{ verta_date($customer->created_at) }}</td>
                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('admin.customers.show', $customer) }}" wire:navigate
                                           class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10 dark:hover:text-brand-400"
                                           title="پروندهٔ مشتری">
                                            <x-icon name="eye" class="size-4.5" />
                                        </a>
                                        <button type="button" wire:click="openEdit({{ $customer->id }})"
                                                class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10 dark:hover:text-brand-400"
                                                title="ویرایش">
                                            <x-icon name="pencil" class="size-4.5" />
                                        </button>
                                        <button type="button" wire:click="$set('deleteId', {{ $customer->id }})"
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
            <x-empty icon="users" title="مشتری‌ای یافت نشد" description="با ایجاد اولین مشتری شروع کنید یا فیلترها را تغییر دهید.">
                <x-btn variant="soft" icon="user-plus" wire:click="openCreate">ایجاد مشتری</x-btn>
            </x-empty>
        @endif
    </div>

    {{-- create / edit modal --}}
    <x-modal wire:model="showModal" :title="$editingId ? 'ویرایش مشتری' : 'مشتری جدید'" size="md">
        <form wire:submit="save" class="space-y-5">
            <x-field label="نام و نام خانوادگی" required>
                <x-input wire:model="form.name" placeholder="مثلاً علی رضایی" icon="user" :class="$errors->has('form.name') ? 'input-error' : ''" />
            </x-field>

            <x-field label="ایمیل" required>
                <x-input wire:model="form.email" type="email" placeholder="name@example.com" icon="mail" dir="ltr" :class="$errors->has('form.email') ? 'input-error' : ''" />
            </x-field>

            <x-field label="شماره تلفن">
                <x-input wire:model="form.phone" placeholder="09121234567" icon="phone" dir="ltr" :class="$errors->has('form.phone') ? 'input-error' : ''" />
            </x-field>

            <x-field label="آدرس سایت" required hint="آدرس کامل سایت مشتری؛ مشتری با کد آپدیت خود از این سایت آپدیت‌ها را دریافت می‌کند.">
                <x-input wire:model="form.website_url" placeholder="https://example.com" icon="globe" dir="ltr" :class="$errors->has('form.website_url') ? 'input-error' : ''" />
            </x-field>

            <x-field label="وضعیت">
                <x-select wire:model="form.status" :options="[
                    'active' => 'فعال',
                    'inactive' => 'غیرفعال',
                ]" />
            </x-field>

            @if($editingId)
                <x-field label="کد آپدیت" hint="این کد قابل ویرایش نیست؛ مشتری با آن از مسیر get-update/{code} آپدیت دریافت می‌کند.">
                    <div class="flex items-center gap-2">
                        <x-input :value="$updateCode" readonly dir="ltr" icon="key" class="font-mono tracking-wider" />
                        <button type="button" x-data title="کپی کد آپدیت"
                                @click="navigator.clipboard.writeText('{{ $updateCode }}'); $dispatch('toast', {message: 'کد کپی شد', type: 'success'})"
                                class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-zinc-100 text-zinc-500 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-brand-500/10 dark:hover:text-brand-400">
                            <x-icon name="copy" class="size-4.5" />
                        </button>
                    </div>
                </x-field>
            @else
                <div class="flex items-center gap-2.5 rounded-xl bg-zinc-50 p-3.5 text-xs leading-5 text-zinc-500 ring-1 ring-zinc-200/60 dark:bg-zinc-800/60 dark:text-zinc-400 dark:ring-zinc-700">
                    <x-icon name="key" class="size-4 shrink-0 text-zinc-400" />
                    <p>کد آپدیت به‌صورت خودکار تولید می‌شود و پس از ذخیره، در جدول مشتریان قابل مشاهده و کپی است.</p>
                </div>
            @endif

            <div class="flex items-center justify-end gap-2 pt-2">
                <x-btn variant="secondary" wire:click="$set('showModal', false)">انصراف</x-btn>
                <x-btn type="submit" icon="save" :loading="true">ذخیره</x-btn>
            </div>
        </form>
    </x-modal>

    {{-- delete confirm --}}
    <x-modal wire:model="deleteId" title="حذف مشتری" size="sm">
        @if($deleteId)
            @php($target = \App\Models\Customer::withCount('subscriptions')->find($deleteId))
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-rose-50 p-4 text-rose-700 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20">
                    <x-icon name="alert-triangle" class="size-6 shrink-0" />
                    <div class="text-sm leading-6">
                        <p>آیا از حذف مشتری «<strong>{{ $target?->name }}</strong>» مطمئن هستید؟ این عمل قابل بازگشت نیست.</p>
                        @if($target && $target->subscriptions_count > 0)
                            <p class="mt-1 font-semibold">توجه: {{ fa_num($target->subscriptions_count) }} اشتراک مرتبط با این مشتری نیز حذف خواهد شد.</p>
                        @endif
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <x-btn variant="secondary" wire:click="$set('deleteId', null)">انصراف</x-btn>
                    <x-btn variant="danger" icon="trash" wire:click="delete" :loading="true">حذف قطعی</x-btn>
                </div>
            </div>
        @endif
    </x-modal>

    {{-- bulk delete confirm --}}
    <x-modal wire:model="confirmingBulkDelete" title="حذف گروهی مشتریان" size="sm">
        @if($confirmingBulkDelete)
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-rose-50 p-4 text-rose-700 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20">
                    <x-icon name="trash" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">
                        <strong>{{ fa_num(count($selectedIds)) }}</strong> مشتری انتخاب‌شده برای همیشه حذف شود؟
                        این عمل قابل بازگشت نیست؛ اشتراک‌ها، لایسنس‌ها و خریدهای مرتبط با این مشتریان نیز حذف خواهند شد.
                    </p>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <x-btn variant="secondary" wire:click="clearSelection">انصراف</x-btn>
                    <x-btn variant="danger" icon="trash" wire:click="bulkDelete" :loading="true">حذف {{ fa_num(count($selectedIds)) }} مشتری</x-btn>
                </div>
            </div>
        @endif
    </x-modal>
</div>
