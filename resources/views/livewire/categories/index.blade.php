<div class="space-y-6">

    {{-- ============ Header ============ --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="flex items-center gap-2 text-xl font-black text-zinc-900 dark:text-zinc-50">
                <x-icon name="tag" class="size-5 text-brand-600" />
                دسته‌بندی‌ها
            </h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                دسته‌بندی مشترک فروشگاه برای محصولات (پروژه‌های قابل‌فروش) و پکیج‌ها.
            </p>
        </div>
        <x-btn icon="plus" wire:click="create">دسته‌بندی جدید</x-btn>
    </div>

    {{-- ============ Filters ============ --}}
    <div class="card p-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="sm:col-span-1">
                <x-input wire:model.live.debounce.400ms="search" type="search" icon="search" placeholder="جستجو…" />
            </div>
            <div class="sm:col-span-1">
                <x-select wire:model.live="typeFilter" :options="$this->types" placeholder="همه انواع" />
            </div>
        </div>
    </div>

    {{-- ============ Table ============ --}}
    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 bg-zinc-50 text-start text-xs text-zinc-500 dark:border-zinc-700/60 dark:bg-zinc-800/60 dark:text-zinc-400">
                        <th class="px-4 py-3 text-start font-bold">نام</th>
                        <th class="px-4 py-3 text-start font-bold">نوع</th>
                        <th class="px-4 py-3 text-start font-bold">استفاده</th>
                        <th class="px-4 py-3 text-start font-bold">ترتیب</th>
                        <th class="px-4 py-3 text-start font-bold">وضعیت</th>
                        <th class="px-4 py-3 text-end font-bold">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->records as $category)
                        <tr wire:key="cat-{{ $category->id }}" class="border-b border-zinc-100 transition-colors last:border-0 hover:bg-zinc-50/70 dark:border-zinc-700/40 dark:hover:bg-zinc-800/40">
                            <td class="px-4 py-3">
                                <div class="font-bold text-zinc-900 dark:text-zinc-50">{{ $category->name }}</div>
                                <div class="mt-0.5 font-mono text-xs text-zinc-400" dir="ltr">{{ $category->slug }}</div>
                            </td>
                            <td class="px-4 py-3">
                                @if($category->type === \App\Models\Category::TYPE_PRODUCT)
                                    <x-badge variant="primary" icon="rocket">محصول</x-badge>
                                @else
                                    <x-badge variant="neutral" icon="package">پکیج</x-badge>
                                @endif
                            </td>
                            <td class="px-4 py-3 tabular-nums text-zinc-600 dark:text-zinc-300">
                                @if($category->type === \App\Models\Category::TYPE_PRODUCT)
                                    {{ fa_num($category->products_count) }} محصول
                                @else
                                    {{ fa_num($category->packages_count) }} پکیج
                                @endif
                            </td>
                            <td class="px-4 py-3 tabular-nums text-zinc-500">{{ fa_num($category->sort_order) }}</td>
                            <td class="px-4 py-3">
                                <button wire:click="toggleActive({{ $category->id }})" wire:loading.attr="disabled" wire:target="toggleActive"
                                        class="cursor-pointer" title="تغییر وضعیت">
                                    @if($category->is_active)
                                        <x-badge variant="success" icon="check-circle">فعال</x-badge>
                                    @else
                                        <x-badge variant="neutral">غیرفعال</x-badge>
                                    @endif
                                </button>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <button wire:click="edit({{ $category->id }})"
                                            class="rounded-lg p-2 text-zinc-500 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10"
                                            title="ویرایش" aria-label="ویرایش {{ $category->name }}">
                                        <x-icon name="pencil" class="size-4" />
                                    </button>
                                    <button wire:click="delete({{ $category->id }})"
                                            wire:confirm="آیا از حذف «{{ $category->name }}» مطمئن هستید؟"
                                            class="rounded-lg p-2 text-zinc-500 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10"
                                            title="حذف" aria-label="حذف {{ $category->name }}">
                                        <x-icon name="trash" class="size-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10">
                                <x-empty icon="tag" title="دسته‌بندی‌ای یافت نشد" description="با دکمه «دسته‌بندی جدید» اولین دسته را بسازید." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>{{ $this->records->links() }}</div>

    {{-- ============ Modal ============ --}}
    <x-modal wire:model="showModal" :title="$editingId ? 'ویرایش دسته‌بندی' : 'دسته‌بندی جدید'">
        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-field label="نام دسته‌بندی" for="form.name" required>
                    <x-input id="form.name" wire:model="form.name" placeholder="مثلاً: اسکریپت" />
                </x-field>
                <x-field label="نامک (slug)" for="form.slug" hint="خالی بگذارید تا خودکار ساخته شود">
                    <x-input id="form.slug" wire:model="form.slug" dir="ltr" class="text-start" placeholder="script" />
                </x-field>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-field label="نوع" for="form.type" required>
                    <x-select id="form.type" wire:model="form.type" :options="$this->types" />
                </x-field>
                <x-field label="ترتیب نمایش" for="form.sort_order">
                    <x-input id="form.sort_order" type="number" min="0" wire:model="form.sort_order" />
                </x-field>
            </div>

            <x-field label="توضیح" for="form.description">
                <x-textarea id="form.description" wire:model="form.description" rows="2" placeholder="اختیاری…" />
            </x-field>

            <x-toggle wire:model="form.is_active" label="دسته‌بندی فعال باشد" />

            <div class="flex items-center justify-end gap-2 border-t border-zinc-100 pt-4 dark:border-zinc-700/50">
                <x-btn variant="secondary" type="button" wire:click="$set('showModal', false)">انصراف</x-btn>
                <x-btn icon="save" type="submit">ذخیره</x-btn>
            </div>
        </form>
    </x-modal>
</div>
