<div class="space-y-6">
    @php
        $schema = \App\Livewire\Gateways::schema();
        $activeCount = count(array_filter($gateways, fn ($gw) => $gw['is_active']));
    @endphp

    {{-- header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-black text-zinc-900 dark:text-zinc-50">درگاه‌های پرداخت</h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">مدیریت و پیکربندی درگاه‌های پرداخت آنلاین سایت.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-badge variant="success" icon="check-circle">{{ fa_num($activeCount) }} از {{ fa_num(count($gateways)) }} درگاه فعال</x-badge>
            <a href="{{ route('admin.settings.index') }}" wire:navigate>
                <x-btn variant="secondary" icon="settings">تنظیمات</x-btn>
            </a>
        </div>
    </div>

    @if(count($gateways))
        {{-- filters / selection toolbar --}}
        <div class="card flex flex-wrap items-center justify-between gap-3 p-4">
            <label class="flex cursor-pointer select-none items-center gap-2.5 text-sm font-medium text-zinc-600 dark:text-zinc-300">
                <input type="checkbox" class="checkbox" aria-label="انتخاب همه درگاه‌ها"
                       @if($this->allPageSelected()) checked @endif
                       wire:click="toggleSelectAll" />
                انتخاب همه درگاه‌ها
            </label>
            <div class="relative sm:w-52">
                <x-icon name="arrow-up-down" class="pointer-events-none absolute start-3.5 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
                <select wire:model.live="sort" class="input ps-10" aria-label="ترتیب نمایش">
                    <option value="newest">جدیدترین</option>
                    <option value="oldest">قدیمی‌ترین</option>
                    <option value="id_desc">شناسه (نزولی)</option>
                    <option value="id_asc">شناسه (صعودی)</option>
                    <option value="ordering_asc">بر اساس ترتیب نمایش</option>
                    <option value="name_asc">بر اساس نام</option>
                </select>
            </div>
            <div wire:loading wire:target="sort" class="flex items-center gap-2 text-xs text-brand-600 dark:text-brand-400">
                <x-icon name="loader" class="size-4 animate-spin" />
                در حال مرتب‌سازی…
            </div>
        </div>

        {{-- bulk toolbar --}}
        @if($selectedIds)
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-rose-50 p-3 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:ring-rose-400/20">
                <div class="flex items-center gap-2 text-sm font-bold text-rose-700 dark:text-rose-400">
                    <x-icon name="check-square" class="size-4.5" />
                    {{ fa_num(count($selectedIds)) }} درگاه انتخاب شده است
                </div>
                <div class="flex items-center gap-2">
                    <x-btn variant="secondary" size="sm" wire:click="clearSelection">انصراف از انتخاب</x-btn>
                    <x-btn variant="danger" icon="trash" size="sm" wire:click="$set('confirmingBulkDelete', true)">حذف گروهی</x-btn>
                </div>
            </div>
        @endif
    @endif

    @if(count($gateways))
        <form wire:submit="save" class="space-y-6">
            <div class="grid items-start gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach($gateways as $index => $gw)
                    @php
                        $meta = $schema[$gw['key']] ?? ['label' => $gw['name'], 'fields' => []];
                        $nameError = $errors->has("gateways.{$index}.name") ? 'input-error' : '';
                        $orderingError = $errors->has("gateways.{$index}.ordering") ? 'input-error' : '';
                    @endphp

                    <div wire:key="gw-{{ $gw['id'] }}" class="card flex flex-col">
                        {{-- gateway header: logo + title --}}
                        <div class="flex items-center gap-3 border-b border-zinc-200/80 p-4 dark:border-zinc-800">
                            <input type="checkbox" class="checkbox shrink-0" aria-label="انتخاب این درگاه"
                                   wire:model.live="selectedIds" value="{{ $gw['id'] }}" />
                            {{-- logo (اختصاصی یا پیش‌فرض) --}}
                            @php($logoUrl = \App\Livewire\Gateways::logoUrl($gw))
                            <div class="h-11 w-[110px] shrink-0 overflow-hidden rounded-xl bg-white ring-1 ring-zinc-200/80 dark:ring-zinc-700/60">
                                @if($logoUrl)
                                    <img src="{{ $logoUrl }}" alt="لوگوی {{ $gw['name'] }}" class="h-full w-full object-contain" />
                                @else
                                    <div class="flex h-full w-full items-center justify-center text-zinc-400">
                                        <x-icon name="credit-card" class="size-5.5" />
                                    </div>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-bold text-zinc-800 dark:text-zinc-100">{{ $meta['label'] ?? $gw['name'] }}</p>
                                <div class="mt-1 flex flex-wrap items-center gap-2">
                                    <x-badge :variant="$gw['is_active'] ? 'success' : 'danger'">{{ $gw['is_active'] ? 'فعال' : 'غیرفعال' }}</x-badge>
                                    @if($gw['is_test'] ?? false)
                                        <x-badge variant="warning" icon="flask">درگاه تست</x-badge>
                                    @endif
                                    <span class="font-mono text-[11px] text-zinc-400" dir="ltr">{{ $gw['key'] }}</span>
                                </div>
                            </div>
                            <x-toggle wire:model.live="gateways.{{ $index }}.is_active" id="gw-active-{{ $gw['id'] }}" />
                            <button type="button" wire:click="$set('deleteId', {{ $gw['id'] }})"
                                    class="shrink-0 rounded-lg p-2 text-zinc-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
                                    title="حذف درگاه">
                                <x-icon name="trash" class="size-4.5" />
                            </button>
                        </div>

                        {{-- gateway body --}}
                        <div class="flex-1 space-y-4 p-4">
                            <div class="grid grid-cols-3 gap-3">
                                <div class="col-span-2">
                                    <x-field label="عنوان" for="gw-name-{{ $gw['id'] }}" required>
                                        <x-input id="gw-name-{{ $gw['id'] }}" wire:model="gateways.{{ $index }}.name" class="{{ $nameError }}" />
                                    </x-field>
                                </div>
                                <x-field label="ترتیب" for="gw-order-{{ $gw['id'] }}">
                                    <x-input id="gw-order-{{ $gw['id'] }}" wire:model="gateways.{{ $index }}.ordering" type="number" dir="ltr" class="{{ $orderingError }}" />
                                </x-field>
                            </div>

                            @foreach($meta['fields'] as $field)
                                @if(($field['type'] ?? 'text') === 'textarea')
                                    <x-field wire:key="gw-{{ $gw['id'] }}-f-{{ $field['name'] }}" :label="$field['label']" required>
                                        <x-textarea wire:model="gateways.{{ $index }}.configs.{{ $field['name'] }}" rows="3" dir="ltr" class="font-mono" />
                                    </x-field>
                                @else
                                    <x-field wire:key="gw-{{ $gw['id'] }}-f-{{ $field['name'] }}" :label="$field['label']" required>
                                        <x-input wire:model="gateways.{{ $index }}.configs.{{ $field['name'] }}" dir="ltr" class="font-mono" />
                                    </x-field>
                                @endif
                            @endforeach

                            {{-- لوگوی اختصاصی --}}
                            <div class="rounded-xl border border-dashed border-zinc-300 p-3 dark:border-zinc-700">
                                <p class="mb-2 text-xs font-bold text-zinc-600 dark:text-zinc-300">لوگوی اختصاصی (اختیاری)</p>
                                <div class="flex flex-wrap items-center gap-3">
                                    <input type="file" wire:model="logoUploads.{{ $gw['id'] }}" accept="image/*,.svg"
                                           class="block w-full text-xs text-zinc-500 file:me-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-zinc-600 hover:file:bg-zinc-200 dark:text-zinc-400 dark:file:bg-zinc-800 dark:file:text-zinc-300"
                                           aria-label="آپلود لوگوی درگاه {{ $gw['name'] }}" />
                                    <x-upload-progress target="logoUploads.{{ $gw['id'] }}" label="در حال آپلود لوگو" class="w-full" />
                                    @if($gw['logo'])
                                        <button type="button" wire:click="removeLogo({{ $gw['id'] }})"
                                                class="rounded-lg px-2.5 py-1.5 text-xs font-bold text-rose-600 transition-colors hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10">
                                            حذف لوگوی فعلی
                                        </button>
                                    @endif
                                    <div wire:loading wire:target="logoUploads.{{ $gw['id'] }}" class="flex items-center gap-1.5 text-xs text-brand-600 dark:text-brand-400">
                                        <x-icon name="loader" class="size-3.5 animate-spin" />
                                        در حال آپلود موقت…
                                    </div>
                                    @if(($logoUploads[$gw['id']] ?? null) && $logoUploads[$gw['id']]->isPreviewable())
                                        <div class="flex items-center gap-2">
                                            <img src="{{ $logoUploads[$gw['id']]->temporaryUrl() }}" alt="پیش‌نمایش" class="h-9 w-[90px] rounded-lg bg-white object-contain ring-1 ring-zinc-200 dark:ring-zinc-700" />
                                            <span class="text-[11px] text-zinc-400">پس از «ذخیره» اعمال می‌شود</span>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            @if($gw['is_test'] ?? false)
                                <div class="flex items-start gap-2 rounded-xl bg-amber-50 p-3 text-xs leading-6 text-amber-800 ring-1 ring-amber-600/10 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/20">
                                    <x-icon name="info" class="size-4 shrink-0" />
                                    <p>این درگاه فقط برای <b>تست جریان پرداخت</b> است و پول واقعی جابه‌جا نمی‌کند. با خاموش کردن کلید بالا، درگاه آزمایشی از لیست انتخاب مشتریان حذف می‌شود.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- save bar --}}
            <div class="card sticky bottom-4 flex flex-wrap items-center justify-between gap-3 p-4 sm:p-5">
                <p class="flex items-center gap-2 text-xs leading-5 text-zinc-500 dark:text-zinc-400">
                    <x-icon name="info" class="size-4 shrink-0" />
                    برای فعال نمودن هر یک از درگاه‌ها، پس از انتخاب درگاه اطلاعات مربوط به آن را پر کنید.
                </p>
                <x-btn type="submit" icon="save" :loading="true">ذخیره تغییرات</x-btn>
            </div>
        </form>
    @else
        <div class="card">
            <x-empty icon="credit-card" title="درگاهی یافت نشد" description="درگاه‌های پشتیبانی‌شده در فایل پیکربندی سیستم تعریف نشده‌اند." />
        </div>
    @endif

    {{-- single delete confirm --}}
    <x-modal wire:model="deleteId" title="حذف درگاه" size="sm">
        @if($deleteId)
            @php($target = \App\Models\Gateway::with('configs')->find($deleteId))
            @php($label = $target ? ($schema[$target->key]['label'] ?? $target->name) : null)
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-rose-50 p-4 text-rose-700 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20">
                    <x-icon name="trash" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">
                        درگاه «<strong>{{ $label }}</strong>» و تنظیمات پیکربندی آن برای همیشه حذف شود؟
                        این عمل قابل بازگشت نیست؛ درگاه‌های پشتیبانی‌شده پس از بارگذاری مجدد صفحه از پیکربندی سیستم دوباره ساخته می‌شوند.
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
    <x-modal wire:model="confirmingBulkDelete" title="حذف گروهی درگاه‌ها" size="sm">
        @if($confirmingBulkDelete)
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-rose-50 p-4 text-rose-700 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20">
                    <x-icon name="trash" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">
                        <strong>{{ fa_num(count($selectedIds)) }}</strong> درگاه انتخاب‌شده به‌همراه تنظیمات پیکربندی‌شان برای همیشه حذف شود؟
                        این عمل قابل بازگشت نیست؛ درگاه‌های پشتیبانی‌شده پس از بارگذاری مجدد صفحه از پیکربندی سیستم دوباره ساخته می‌شوند.
                    </p>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <x-btn variant="secondary" wire:click="clearSelection">انصراف</x-btn>
                    <x-btn variant="danger" icon="trash" wire:click="bulkDelete" :loading="true">حذف {{ fa_num(count($selectedIds)) }} درگاه</x-btn>
                </div>
            </div>
        @endif
    </x-modal>
</div>
