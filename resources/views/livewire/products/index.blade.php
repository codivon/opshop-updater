<div class="space-y-6">

    {{-- ============ Header ============ --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="flex items-center gap-2 text-xl font-black text-zinc-900 dark:text-zinc-50">
                <x-icon name="rocket" class="size-5 text-brand-600" />
                محصولات (پروژه‌های قابل‌فروش)
            </h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                فروش مستقیم پروژه‌ها به بازدیدکننده‌گان سایت — بدون کد آپدیت و بدون ثبت‌نام.
            </p>
        </div>
        <x-btn icon="plus" wire:click="create">محصول جدید</x-btn>
    </div>

    {{-- ============ Filters ============ --}}
    <div class="card p-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
            <div class="sm:col-span-2">
                <x-input wire:model.live.debounce.400ms="search" type="search" icon="search" placeholder="جستجوی نام یا نامک…" />
            </div>
            <div>
                <x-select wire:model.live="categoryFilter" :options="$this->categories" placeholder="همه دسته‌بندی‌ها" />
            </div>
            <div>
                <x-select wire:model.live="statusFilter" :options="[
                    \App\Models\Product::STATUS_ACTIVE   => 'فعال',
                    \App\Models\Product::STATUS_DRAFT    => 'پیش‌نویس',
                    \App\Models\Product::STATUS_ARCHIVED => 'آرشیو',
                ]" placeholder="همه وضعیت‌ها" />
            </div>
        </div>
    </div>

    {{-- ============ Grid ============ --}}
    @if($this->records->count())
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($this->records as $product)
                <div wire:key="product-{{ $product->id }}" class="card flex flex-col overflow-hidden p-0">
                    {{-- thumbnail --}}
                    <div class="relative aspect-video overflow-hidden bg-zinc-100 dark:bg-zinc-800">
                        <a href="{{ route('admin.products.show', $product) }}" wire:navigate class="block size-full">
                            <img src="{{ $product->thumbnail_url }}" alt="{{ $product->name }}" class="size-full object-cover"
                                 onerror="this.style.display='none';this.parentElement.classList.add('shop-thumb')">
                        </a>

                        @if($product->latestVersion)
                            <span class="absolute bottom-2 start-2 inline-flex items-center gap-1 rounded-lg bg-zinc-950/70 px-2 py-1 font-mono text-xs font-bold text-white backdrop-blur" dir="ltr">
                                v{{ $product->latestVersion->version }}
                            </span>
                        @endif

                        @if($product->is_free || $product->final_price <= 0)
                            <div class="absolute bottom-2 end-2">
                                <x-badge variant="success" icon="gift">رایگان</x-badge>
                            </div>
                        @elseif($product->discount > 0)
                            <div class="absolute bottom-2 end-2">
                                <x-badge variant="danger" icon="tag">{{ fa_num($product->discount) }}٪ تخفیف</x-badge>
                            </div>
                        @endif
                    </div>

                    {{-- body --}}
                    <div class="flex flex-1 flex-col gap-3 p-4">
                        <div class="flex items-start justify-between gap-2">
                            <a href="{{ route('admin.products.show', $product) }}" wire:navigate
                               class="text-base font-bold text-zinc-900 transition-colors hover:text-brand-600 dark:text-zinc-50">
                                {{ $product->name }}
                            </a>
                            @if($product->category)
                                <x-badge variant="neutral">{{ $product->category->name }}</x-badge>
                            @endif
                        </div>

                        @if($product->short_description)
                            <p class="line-clamp-2 text-sm leading-6 text-zinc-500 dark:text-zinc-400">
                                {{ \Illuminate\Support\Str::limit($product->short_description, 100) }}
                            </p>
                        @endif

                        {{-- stats row --}}
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                            <span class="inline-flex items-center gap-1"><x-icon name="layers" class="size-3.5" /> {{ fa_num($product->versions_count) }} نسخه</span>
                            <span class="inline-flex items-center gap-1"><x-icon name="receipt" class="size-3.5" /> {{ fa_num($product->purchases_count) }} فروش</span>
                            <span class="inline-flex items-center gap-1"><x-icon name="message-square" class="size-3.5" /> {{ fa_num($product->comments_count) }} دیدگاه</span>
                        </div>

                        {{-- footer --}}
                        <div class="mt-auto flex items-center justify-between border-t border-zinc-100 pt-3 dark:border-zinc-700/50">
                            <div class="flex items-center gap-2">
                                <button wire:click="toggleStatus({{ $product->id }})" wire:loading.attr="disabled" wire:target="toggleStatus" class="cursor-pointer">
                                    @if($product->status === \App\Models\Product::STATUS_ACTIVE)
                                        <x-badge variant="success" icon="check-circle">فعال</x-badge>
                                    @elseif($product->status === \App\Models\Product::STATUS_DRAFT)
                                        <x-badge variant="neutral">پیش‌نویس</x-badge>
                                    @else
                                        <x-badge variant="warning" icon="archive">آرشیو</x-badge>
                                    @endif
                                </button>
                            </div>

                            <div class="flex items-center gap-1">
                                @if(!$product->is_free && $product->final_price > 0)
                                    <span class="me-1 text-sm font-black tabular-nums text-zinc-900 dark:text-zinc-100">
                                        {{ money($product->final_price) }}
                                    </span>
                                @endif

                                <a href="{{ route('admin.products.show', $product) }}" wire:navigate
                                   class="rounded-lg p-2 text-zinc-500 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10"
                                   title="مدیریت" aria-label="مدیریت {{ $product->name }}">
                                    <x-icon name="settings" class="size-4" />
                                </a>
                                <button wire:click="edit({{ $product->id }})"
                                        class="rounded-lg p-2 text-zinc-500 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10"
                                        title="ویرایش" aria-label="ویرایش {{ $product->name }}">
                                    <x-icon name="pencil" class="size-4" />
                                </button>
                                <button wire:click="delete({{ $product->id }})"
                                        wire:confirm="آیا از حذف «{{ $product->name }}» مطمئن هستید؟"
                                        class="rounded-lg p-2 text-zinc-500 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10"
                                        title="حذف" aria-label="حذف {{ $product->name }}">
                                    <x-icon name="trash" class="size-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div>{{ $this->records->links() }}</div>
    @else
        <div class="card">
            <x-empty icon="rocket" title="محصولی یافت نشد" description="با دکمه «محصول جدید» اولین پروژه قابل‌فروش را بسازید." />
        </div>
    @endif

    {{-- ============ Modal (create/edit) ============ --}}
    <x-modal wire:model="showModal" :title="$editingId ? 'ویرایش محصول' : 'محصول جدید'" size="lg">
        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-field label="نام محصول" for="form.name" required>
                    <x-input id="form.name" wire:model="form.name" placeholder="مثلاً: اسکریپت فروشگاهی لاراول" />
                </x-field>
                <x-field label="نامک (slug)" for="form.slug" hint="خالی بگذارید تا خودکار ساخته شود">
                    <x-input id="form.slug" wire:model="form.slug" dir="ltr" class="text-start" placeholder="laravel-shop-script" />
                </x-field>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-field label="دسته‌بندی" for="form.category_id">
                    <x-select id="form.category_id" wire:model="form.category_id" :options="$this->categories" placeholder="بدون دسته" />
                </x-field>
                <x-field label="وضعیت" for="form.status">
                    <x-select id="form.status" wire:model="form.status" :options="[
                        \App\Models\Product::STATUS_ACTIVE   => 'فعال (نمایش در فروشگاه)',
                        \App\Models\Product::STATUS_DRAFT    => 'پیش‌نویس',
                        \App\Models\Product::STATUS_ARCHIVED => 'آرشیو',
                    ]" />
                </x-field>
            </div>

            <x-field label="توضیح کوتاه" for="form.short_description" hint="حداکثر ۱۰٬۰۰۰ کاراکتر — در کارت فروشگاه نمایش داده می‌شود">
                <x-textarea id="form.short_description" wire:model="form.short_description" rows="3" placeholder="خلاصه‌ای از محصول…" />
            </x-field>

            <x-field label="توضیحات کامل (HTML)" for="form.description">
                <x-ckeditor model="form.description" :value="$form['description'] ?? ''" />
            </x-field>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-field label="قیمت (تومان)" for="form.price">
                    <x-input id="form.price" type="number" min="0" wire:model="form.price" />
                </x-field>
                <x-field label="تخفیف (٪)" for="form.discount">
                    <x-input id="form.discount" type="number" min="0" max="100" wire:model="form.discount" />
                </x-field>
                <x-field label="ترتیب نمایش" for="form.sort_order">
                    <x-input id="form.sort_order" type="number" min="0" wire:model="form.sort_order" />
                </x-field>
            </div>

            @if(!$form['is_free'] && (int) $form['price'] > 0 && (int) $form['discount'] > 0)
                <div class="rounded-xl bg-emerald-50 px-4 py-2.5 text-sm font-bold text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/20">
                    قیمت نهایی: {{ money($form['price'] - round($form['price'] * $form['discount'] / 100)) }} تومان
                </div>
            @endif

            <div class="flex flex-col gap-4">
                <x-toggle wire:model="form.is_free" label="این محصول رایگان است" />

                {{-- thumbnail --}}
                <x-field label="تصویر شاخص">
                    <div wire:key="product-thumb-preview" wire:ignore x-data="filePreview()" class="space-y-2">
                        <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" wire:model="thumbnailFile" x-ref="input" class="input" />
                        <template x-for="(preview, i) in previews" :key="i">
                            <div class="flex items-center gap-3">
                                <img :src="preview.url" :alt="preview.name"
                                     class="size-20 shrink-0 rounded-xl bg-zinc-100 object-cover ring-1 ring-zinc-200 dark:bg-zinc-800 dark:ring-zinc-700" />
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-medium text-zinc-700 dark:text-zinc-200" x-text="preview.name"></p>
                                    <p class="mt-0.5 text-[11px] text-zinc-400">پیش‌نمایش تصویر انتخاب‌شده — با ذخیره جایگزین می‌شود</p>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div wire:loading wire:target="thumbnailFile" class="mt-2 flex items-center gap-2 text-xs text-brand-600 dark:text-brand-400">
                        <x-icon name="loader" class="size-4 animate-spin" />
                        در حال آپلود…
                    </div>
                    @error('thumbnailFile') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    @if($editingId && !$thumbnailFile)
                        <div class="mt-2">
                            <x-toggle wire:model="removeThumbnail" label="حذف تصویر فعلی" />
                        </div>
                    @endif
                </x-field>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-zinc-100 pt-4 dark:border-zinc-700/50">
                <x-btn variant="secondary" type="button" wire:click="$set('showModal', false)">انصراف</x-btn>
                <x-btn icon="save" type="submit">ذخیره</x-btn>
            </div>
        </form>
    </x-modal>
</div>
