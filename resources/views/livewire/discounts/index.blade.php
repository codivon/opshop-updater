<div class="space-y-6">

    {{-- ============ Header ============ --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="flex items-center gap-2 text-xl font-black text-zinc-900 dark:text-zinc-50">
                <x-icon name="ticket-percent" class="size-5 text-brand-600" />
                کدهای تخفیف
            </h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                کدهای تخفیف با انقضا و محدودیت تعداد — قابل اعمال روی خرید پکیج‌ها، محصولات و طرح‌های اشتراک.
            </p>
        </div>
        <x-btn icon="plus" wire:click="create">کد تخفیف جدید</x-btn>
    </div>

    {{-- ============ Filters ============ --}}
    <div class="card p-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <x-input wire:model.live.debounce.400ms="search" type="search" icon="search" placeholder="جستجوی کد یا یادداشت…" />
            </div>
            <div class="sm:col-span-1">
                <x-select wire:model.live="statusFilter" :options="$this->statuses" />
            </div>
        </div>
    </div>

    {{-- ============ Table ============ --}}
    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 bg-zinc-50 text-start text-xs text-zinc-500 dark:border-zinc-700/60 dark:bg-zinc-800/60 dark:text-zinc-400">
                        <th class="px-4 py-3 text-start font-bold">کد</th>
                        <th class="px-4 py-3 text-start font-bold">مقدار تخفیف</th>
                        <th class="px-4 py-3 text-start font-bold">محدوده اعتبار</th>
                        <th class="px-4 py-3 text-start font-bold">استفاده</th>
                        <th class="px-4 py-3 text-start font-bold">انقضا</th>
                        <th class="px-4 py-3 text-start font-bold">وضعیت</th>
                        <th class="px-4 py-3 text-end font-bold">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->records as $code)
                        @php([$statusLabel, $statusVariant] = $code->statusLabel())
                        <tr wire:key="disc-{{ $code->id }}" class="border-b border-zinc-100 transition-colors last:border-0 hover:bg-zinc-50/70 dark:border-zinc-700/40 dark:hover:bg-zinc-800/40">
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-2">
                                    <span class="rounded-lg bg-zinc-100 px-2.5 py-1 font-mono text-xs font-black tracking-wider text-zinc-800 dark:bg-zinc-800 dark:text-zinc-100" dir="ltr">{{ $code->code }}</span>
                                    @if($code->notes)
                                        <span class="hidden max-w-40 truncate text-xs text-zinc-400 lg:inline" title="{{ $code->notes }}">{{ $code->notes }}</span>
                                    @endif
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @if($code->type === \App\Models\DiscountCode::TYPE_PERCENT)
                                    <x-badge variant="primary" icon="trending-down">{{ fa_num($code->value) }}٪</x-badge>
                                @else
                                    <x-badge variant="info" icon="banknote">{{ money($code->value, false) }} تومان</x-badge>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-1">
                                    @if($code->applies_packages)
                                        <x-badge variant="neutral" icon="package">پکیج</x-badge>
                                    @endif
                                    @if($code->applies_products)
                                        <x-badge variant="neutral" icon="rocket">محصول</x-badge>
                                    @endif
                                    @if($code->applies_subscriptions)
                                        <x-badge variant="neutral" icon="crown">اشتراک</x-badge>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="tabular-nums text-zinc-700 dark:text-zinc-300">
                                    {{ fa_num($code->used_count) }}
                                    <span class="text-zinc-400">/ {{ $code->max_uses ? fa_num($code->max_uses) : '∞' }}</span>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs tabular-nums text-zinc-600 dark:text-zinc-300">
                                @if($code->expires_at)
                                    {{ verta_date($code->expires_at, 'Y/m/d H:i') }}
                                @else
                                    <span class="text-zinc-400">بدون انقضا</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <button wire:click="toggleActive({{ $code->id }})" wire:loading.attr="disabled" wire:target="toggleActive"
                                        class="cursor-pointer" title="تغییر وضعیت">
                                    <x-badge variant="{{ $statusVariant }}">{{ $statusLabel }}</x-badge>
                                </button>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <button wire:click="edit({{ $code->id }})"
                                            class="rounded-lg p-2 text-zinc-500 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10"
                                            title="ویرایش" aria-label="ویرایش {{ $code->code }}">
                                        <x-icon name="pencil" class="size-4" />
                                    </button>
                                    <button wire:click="delete({{ $code->id }})"
                                            wire:confirm="آیا از حذف کد «{{ $code->code }}» مطمئن هستید؟ سابقه خریدهای قبلی حفظ می‌شود."
                                            class="rounded-lg p-2 text-zinc-500 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10"
                                            title="حذف" aria-label="حذف {{ $code->code }}">
                                        <x-icon name="trash" class="size-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10">
                                <x-empty icon="ticket-percent" title="کد تخفیفی یافت نشد" description="با دکمه «کد تخفیف جدید» اولین کد را بسازید." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>{{ $this->records->links() }}</div>

    {{-- ============ Modal ============ --}}
    <x-modal wire:model="showModal" :title="$editingId ? 'ویرایش کد تخفیف' : 'کد تخفیف جدید'">
        <form wire:submit="save" class="space-y-4">
            {{-- کد + تولید خودکار --}}
            <x-field label="کد تخفیف" for="form.code" required hint="فقط حروف انگلیسی، عدد، خط تیره و زیرخط">
                <div class="flex items-stretch gap-2">
                    <x-input id="form.code" wire:model="form.code" dir="ltr" class="flex-1 font-mono uppercase placeholder:normal-case text-start" placeholder="WELCOME10" />
                    <x-btn variant="secondary" type="button" wire:click="generateCode" icon="sparkles" class="shrink-0">تولید</x-btn>
                </div>
                @error('form.code') <p class="mt-1 text-xs font-bold text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
            </x-field>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-field label="نوع تخفیف" for="form.type" required>
                    <x-select id="form.type" wire:model.live="form.type" :options="[
                        \App\Models\DiscountCode::TYPE_PERCENT => 'درصدی (٪)',
                        \App\Models\DiscountCode::TYPE_FIXED   => 'مبلغ ثابت (تومان)',
                    ]" />
                </x-field>
                <x-field label="مقدار تخفیف" for="form.value" required hint="{{ $form['type'] === \App\Models\DiscountCode::TYPE_PERCENT ? 'عدد بین ۱ تا ۱۰۰' : 'مبلغ به تومان (حداقل ۱٬۰۰۰)' }}">
                    <x-input id="form.value" type="number" wire:model="form.value" min="1" />
                    @error('form.value') <p class="mt-1 text-xs font-bold text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </x-field>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-field label="حداکثر تعداد استفاده" for="form.max_uses" hint="خالی = بدون محدودیت">
                    <x-input id="form.max_uses" type="number" min="1" wire:model="form.max_uses" placeholder="بدون محدودیت" />
                </x-field>
                <x-field label="تاریخ انقضا" for="form.expires_at" hint="خالی = بدون انقضا">
                    <x-input id="form.expires_at" type="datetime-local" wire:model="form.expires_at" />
                    @error('form.expires_at') <p class="mt-1 text-xs font-bold text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </x-field>
            </div>

            {{-- محدوده اعتبار --}}
            <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/70 dark:bg-zinc-800/50 dark:ring-zinc-700/50">
                <p class="mb-3 text-xs font-bold text-zinc-600 dark:text-zinc-300">محدوده اعتبار کد (حداقل یکی)</p>
                <div class="space-y-2.5">
                    <x-toggle wire:model="form.applies_packages" label="خرید پکیج‌ها" />
                    <x-toggle wire:model="form.applies_products" label="خرید محصولات (پروژه‌ها)" />
                    <x-toggle wire:model="form.applies_subscriptions" label="خرید طرح‌های اشتراک" />
                </div>
                @error('form.applies_packages') <p class="mt-2 text-xs font-bold text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
            </div>

            <x-field label="یادداشت (داخلی)" for="form.notes">
                <x-textarea id="form.notes" wire:model="form.notes" rows="2" placeholder="اختیاری — مثلاً: کمپین نوروزی" />
            </x-field>

            <x-toggle wire:model="form.is_active" label="کد فعال باشد" />

            <div class="flex items-center justify-end gap-2 border-t border-zinc-100 pt-4 dark:border-zinc-700/50">
                <x-btn variant="secondary" type="button" wire:click="$set('showModal', false)">انصراف</x-btn>
                <x-btn icon="save" type="submit">ذخیره</x-btn>
            </div>
        </form>
    </x-modal>
</div>
