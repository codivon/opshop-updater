<div class="space-y-6">

    {{-- ============ Header ============ --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="flex items-center gap-2 text-xl font-black text-zinc-900 dark:text-zinc-50">
                <x-icon name="layout-template" class="size-5 text-brand-600" />
                قالب‌ها
            </h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                فروش قالب‌های آماده با دو نوع قیمت‌گذاری (معمولی + اختصاصی) — بدون کد آپدیت و بدون ثبت‌نام.
            </p>
        </div>
        <x-btn icon="plus" wire:click="create">قالب جدید</x-btn>
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
                    \App\Models\Template::STATUS_ACTIVE   => 'فعال',
                    \App\Models\Template::STATUS_DRAFT    => 'پیش‌نویس',
                    \App\Models\Template::STATUS_ARCHIVED => 'آرشیو',
                ]" placeholder="همه وضعیت‌ها" />
            </div>
        </div>
    </div>

    {{-- ============ Grid ============ --}}
    @if($this->records->count())
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($this->records as $template)
                <div wire:key="template-{{ $template->id }}" class="card flex flex-col overflow-hidden p-0">
                    {{-- thumbnail --}}
                    <div class="relative aspect-video overflow-hidden bg-zinc-100 dark:bg-zinc-800">
                        <a href="{{ route('admin.templates.show', $template) }}" wire:navigate class="block size-full">
                            <img src="{{ $template->thumbnail_url }}" alt="{{ $template->name }}" class="size-full object-cover"
                                 onerror="this.style.display='none';this.parentElement.classList.add('shop-thumb')">
                        </a>

                        @if($template->is_sold_exclusively)
                            <div class="absolute top-2 end-2">
                                <x-badge variant="violet" icon="crown">فروخته شد (اختصاصی)</x-badge>
                            </div>
                        @elseif($template->is_free || $template->final_price <= 0)
                            <div class="absolute bottom-2 end-2">
                                <x-badge variant="success" icon="gift">رایگان</x-badge>
                            </div>
                        @elseif($template->discount > 0)
                            <div class="absolute bottom-2 end-2">
                                <x-badge variant="danger" icon="tag">{{ fa_num($template->discount) }}٪ تخفیف</x-badge>
                            </div>
                        @endif

                        @if($template->latestVersion)
                            <span class="absolute bottom-2 start-2 inline-flex items-center gap-1 rounded-lg bg-zinc-950/70 px-2 py-1 font-mono text-xs font-bold text-white backdrop-blur" dir="ltr">
                                v{{ $template->latestVersion->version }}
                            </span>
                        @endif
                    </div>

                    {{-- body --}}
                    <div class="flex flex-1 flex-col gap-3 p-4">
                        <div class="flex items-start justify-between gap-2">
                            <a href="{{ route('admin.templates.show', $template) }}" wire:navigate
                               class="text-base font-bold text-zinc-900 transition-colors hover:text-brand-600 dark:text-zinc-50">
                                {{ $template->name }}
                            </a>
                            @if($template->category)
                                <x-badge variant="neutral">{{ $template->category->name }}</x-badge>
                            @endif
                        </div>

                        @if($template->short_description)
                            <p class="line-clamp-2 text-sm leading-6 text-zinc-500 dark:text-zinc-400">
                                {{ \Illuminate\Support\Str::limit($template->short_description, 100) }}
                            </p>
                        @endif

                        {{-- stats row --}}
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                            <span class="inline-flex items-center gap-1"><x-icon name="layers" class="size-3.5" /> {{ fa_num($template->versions_count) }} نسخه</span>
                            <span class="inline-flex items-center gap-1"><x-icon name="receipt" class="size-3.5" /> {{ fa_num($template->purchases_count) }} فروش</span>
                            @if($template->paid_revenue_sum > 0)
                                <span class="inline-flex items-center gap-1 font-bold text-emerald-600 dark:text-emerald-400" title="درآمد این قالب"><x-icon name="credit-card" class="size-3.5" /> {{ money($template->paid_revenue_sum, false) }}</span>
                            @endif
                            <span class="inline-flex items-center gap-1"><x-icon name="message-square" class="size-3.5" /> {{ fa_num($template->comments_count) }} دیدگاه</span>
                        </div>

                        {{-- prices --}}
                        <div class="flex flex-wrap items-center gap-3 text-xs">
                            @if($template->is_free || $template->final_price <= 0)
                                <span class="font-bold text-emerald-600 dark:text-emerald-400">رایگان</span>
                            @else
                                <span class="rounded-lg bg-brand-50 px-2 py-1 font-bold text-brand-700 dark:bg-brand-500/10 dark:text-brand-400">
                                    معمولی: {{ money($template->final_price, false) }}
                                </span>
                            @endif

                            @if($template->has_exclusive_offer)
                                <span class="rounded-lg bg-violet-50 px-2 py-1 font-bold text-violet-700 dark:bg-violet-500/10 dark:text-violet-400">
                                    اختصاصی: {{ money($template->exclusive_final_price, false) }}
                                </span>
                            @endif
                        </div>

                        {{-- footer --}}
                        <div class="mt-auto flex items-center justify-between border-t border-zinc-100 pt-3 dark:border-zinc-700/50">
                            <div class="flex items-center gap-2">
                                <button wire:click="toggleStatus({{ $template->id }})" wire:loading.attr="disabled" wire:target="toggleStatus" class="cursor-pointer">
                                    @if($template->status === \App\Models\Template::STATUS_ACTIVE)
                                        <x-badge variant="success" icon="check-circle">فعال</x-badge>
                                    @elseif($template->status === \App\Models\Template::STATUS_DRAFT)
                                        <x-badge variant="neutral">پیش‌نویس</x-badge>
                                    @else
                                        <x-badge variant="warning" icon="archive">آرشیو</x-badge>
                                    @endif
                                </button>
                            </div>

                            <div class="flex items-center gap-1">
                                <a href="{{ route('admin.templates.show', $template) }}" wire:navigate
                                   class="rounded-lg p-2 text-zinc-500 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10"
                                   title="مدیریت" aria-label="مدیریت {{ $template->name }}">
                                    <x-icon name="settings" class="size-4" />
                                </a>
                                <button wire:click="edit({{ $template->id }})"
                                        class="rounded-lg p-2 text-zinc-500 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10"
                                        title="ویرایش" aria-label="ویرایش {{ $template->name }}">
                                    <x-icon name="pencil" class="size-4" />
                                </button>
                                <button wire:click="delete({{ $template->id }})"
                                        wire:confirm="آیا از حذف «{{ $template->name }}» مطمئن هستید؟"
                                        class="rounded-lg p-2 text-zinc-500 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10"
                                        title="حذف" aria-label="حذف {{ $template->name }}">
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
            <x-empty icon="rocket" title="قالبی یافت نشد" description="با دکمه «قالب جدید» اولین قالب را بسازید." />
        </div>
    @endif

    {{-- ============ Modal (create/edit) ============ --}}
    <x-modal wire:model="showModal" :title="$editingId ? 'ویرایش قالب' : 'قالب جدید'" size="lg">
        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-field label="نام قالب" for="form.name" required>
                    <x-input id="form.name" wire:model="form.name" placeholder="مثلاً: قالب فروشگاهی مدرن" />
                </x-field>
                <x-field label="نامک (slug)" for="form.slug" hint="خالی بگذارید تا خودکار ساخته شود">
                    <x-input id="form.slug" wire:model="form.slug" dir="ltr" class="text-start" placeholder="modern-shop-template" />
                </x-field>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-field label="دسته‌بندی" for="form.category_id">
                    <x-select id="form.category_id" wire:model="form.category_id" :options="$this->categories" placeholder="بدون دسته" />
                </x-field>
                <x-field label="وضعیت" for="form.status">
                    <x-select id="form.status" wire:model="form.status" :options="[
                        \App\Models\Template::STATUS_ACTIVE   => 'فعال (نمایش در فروشگاه)',
                        \App\Models\Template::STATUS_DRAFT    => 'پیش‌نویس',
                        \App\Models\Template::STATUS_ARCHIVED => 'آرشیو',
                    ]" />
                </x-field>
            </div>

            <x-field label="توضیح کوتاه" for="form.short_description" hint="حداکثر ۱۰٬۰۰۰ کاراکتر — در کارت فروشگاه نمایش داده می‌شود">
                <x-textarea id="form.short_description" wire:model="form.short_description" rows="3" placeholder="خلاصه‌ای از قالب…" />
            </x-field>

            <x-field label="توضیحات کامل (HTML)" for="form.description">
                <x-ckeditor model="form.description" :value="$form['description'] ?? ''" />
            </x-field>

            {{-- ================= قیمت‌گذاری دوگانه ================= --}}
            <div class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-700/60">
                <p class="mb-3 flex items-center gap-1.5 text-sm font-black text-zinc-800 dark:text-zinc-100">
                    <x-icon name="wallet" class="size-4 text-brand-600" />
                    قیمت‌گذاری
                </p>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    {{-- خرید معمولی --}}
                    <div class="rounded-xl bg-brand-50/60 p-3 ring-1 ring-brand-100 dark:bg-brand-500/5 dark:ring-brand-500/20">
                        <p class="mb-2 text-xs font-black text-brand-700 dark:text-brand-400">۱) خرید معمولی</p>
                        <div class="grid grid-cols-2 gap-3">
                            <x-field label="قیمت (تومان)" for="form.price">
                                <x-input id="form.price" type="number" min="0" wire:model="form.price" />
                                <x-money-preview target="form.price" />
                            </x-field>
                            <x-field label="تخفیف (٪)" for="form.discount">
                                <x-input id="form.discount" type="number" min="0" max="100" wire:model="form.discount" />
                            </x-field>
                        </div>
                        @if(!$form['is_free'] && (int) $form['price'] > 0 && (int) $form['discount'] > 0)
                            <div class="mt-2 rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                                قیمت نهایی معمولی: {{ money($form['price'] - round($form['price'] * $form['discount'] / 100)) }}
                            </div>
                        @endif
                    </div>

                    {{-- خرید اختصاصی --}}
                    <div class="rounded-xl bg-violet-50/60 p-3 ring-1 ring-violet-100 dark:bg-violet-500/5 dark:ring-violet-500/20">
                        <p class="mb-2 flex items-center gap-1 text-xs font-black text-violet-700 dark:text-violet-400">
                            <x-icon name="crown" class="size-3.5" />
                            ۲) خرید اختصاصی (واگذاری کامل)
                        </p>
                        <div class="grid grid-cols-2 gap-3">
                            <x-field label="قیمت (تومان)" for="form.exclusive_price" hint="صفر = بدون فروش اختصاصی">
                                <x-input id="form.exclusive_price" type="number" min="0" wire:model="form.exclusive_price" />
                                <x-money-preview target="form.exclusive_price" />
                            </x-field>
                            <x-field label="تخفیف (٪)" for="form.exclusive_discount">
                                <x-input id="form.exclusive_discount" type="number" min="0" max="100" wire:model="form.exclusive_discount" />
                            </x-field>
                        </div>
                        @if(!$form['is_free'] && (int) $form['exclusive_price'] > 0 && (int) $form['exclusive_discount'] > 0)
                            <div class="mt-2 rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                                قیمت نهایی اختصاصی: {{ money($form['exclusive_price'] - round($form['exclusive_price'] * $form['exclusive_discount'] / 100)) }}
                            </div>
                        @endif
                    </div>
                </div>

                <x-field class="mt-3" label="توضیح خرید اختصاصی (نمایش به خریدار)" for="form.exclusive_note" hint="مثلاً: برداشتن کامل قالب از فروشگاه + انتقال کامل حقوق">
                    <x-textarea id="form.exclusive_note" wire:model="form.exclusive_note" rows="2" placeholder="حقوق و شرایط خرید اختصاصی…" />
                </x-field>

                <p class="mt-2 flex items-start gap-1.5 text-[11px] leading-5 text-zinc-500 dark:text-zinc-400">
                    <x-icon name="info" class="mt-0.5 size-3.5 shrink-0 text-amber-500" />
                    پس از نخستین «خرید اختصاصی» موفق، قالب به‌صورت خودکار از فروشگاه و API برداشته می‌شود و دیگر قابل فروش نیست.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-field label="ترتیب نمایش" for="form.sort_order">
                    <x-input id="form.sort_order" type="number" min="0" wire:model="form.sort_order" />
                </x-field>
                <div class="flex items-end pb-1">
                    <x-toggle wire:model="form.is_free" label="این قالب رایگان است" />
                </div>
            </div>

            {{-- thumbnail --}}
            <x-field label="تصویر شاخص">
                <div wire:key="template-thumb-preview-{{ $thumbNonce }}" x-data="filePreview()" class="space-y-2">
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

                <x-upload-progress target="thumbnailFile" label="در حال آپلود تصویر شاخص" />

                @error('thumbnailFile') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                @if($editingId && !$thumbnailFile)
                    <div class="mt-2">
                        <x-toggle wire:model="removeThumbnail" label="حذف تصویر فعلی" />
                    </div>
                @endif
            </x-field>

            <div class="flex items-center justify-end gap-2 border-t border-zinc-100 pt-4 dark:border-zinc-700/50">
                <x-btn variant="secondary" type="button" wire:click="$set('showModal', false)">انصراف</x-btn>
                <x-btn icon="save" type="submit">ذخیره</x-btn>
            </div>
        </form>
    </x-modal>
</div>
