{{-- بخش «categories» صفحهٔ اصلی — محتوا/سوییچ/ترتیب از پنل (Task 59) --}}
        @php($showCategories = $ho['home_categories_enabled'] && ($ho['home_packages_enabled'] || $ho['home_products_enabled']))
        @php($packageNodes = $showCategories ? $this->packageCategoryTree : collect())
        @php($productNodes = $showCategories ? $this->productCategoryTree : collect())
        @if($showCategories && ($packageNodes->count() || $productNodes->count()))
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6">
                <section aria-label="دسته‌بندی فروشگاه">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="flex items-center gap-2 text-lg font-black text-zinc-900 dark:text-zinc-50">
                                <x-icon name="folder-tree" class="size-5 text-brand-600" />
                                دسته‌بندی محصولات
                            </h2>
                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                دسته موردنظر را انتخاب کنید تا فهرست همان بخش، فیلترشده باز شود.
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 space-y-6">

                        {{-- ---------- پکیج‌ها (تمام‌عرض، ۴ ستون) ---------- --}}
                        @if($ho['home_packages_enabled'] && $packageNodes->count())
                            <div>
                                <h3 class="flex items-center gap-1.5 text-sm font-black text-zinc-700 dark:text-zinc-200">
                                    <x-icon name="package" class="size-4 text-brand-600" />
                                    پکیج‌ها
                                </h3>
                                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                    @foreach($packageNodes as $node)
                                        @php($root = $node['model'])
                                        <div class="group rounded-2xl bg-white p-4 ring-1 ring-zinc-200/80 transition-all hover:-translate-y-0.5 hover:shadow-card-lg dark:bg-zinc-900 dark:ring-zinc-700/60"
                                             wire:key="pkg-cat-{{ $root->id }}">
                                            <div class="flex items-center gap-3">
                                                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 transition-colors group-hover:bg-brand-100 dark:bg-brand-500/10 dark:text-brand-400">
                                                    <x-icon name="folder" class="size-5" />
                                                </span>
                                                <a href="{{ route('shop.packages', ['category' => $root->id]) }}" wire:navigate
                                                   class="flex min-w-0 flex-1 items-center justify-between gap-2 rounded-lg focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:outline-none">
                                                    <span class="truncate text-sm font-black text-zinc-900 dark:text-zinc-50">{{ $root->name }}</span>
                                                    <span class="inline-flex shrink-0 items-center rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-bold tabular-nums text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                                                        {{ fa_num($root->shop_count ?? 0) }} مورد
                                                    </span>
                                                </a>
                                            </div>
                                            @if($node['children']->count())
                                                <div class="mt-3 flex flex-wrap gap-1.5">
                                                    @foreach($node['children'] as $child)
                                                        <a href="{{ route('shop.packages', ['category' => $child->id]) }}" wire:navigate
                                                           class="inline-flex items-center gap-1 rounded-lg bg-zinc-50 px-2 py-1 text-[11px] font-bold text-zinc-600 ring-1 ring-zinc-200/70 transition-colors hover:bg-brand-50 hover:text-brand-700 dark:bg-zinc-800/60 dark:text-zinc-300 dark:ring-zinc-700/60 dark:hover:bg-brand-500/10 dark:hover:text-brand-400">
                                                            {{ $child->name }}
                                                            <span class="tabular-nums opacity-60">{{ fa_num($child->shop_count ?? 0) }}</span>
                                                        </a>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- ---------- پروژه‌های آماده (تمام‌عرض، ۴ ستون) ---------- --}}
                        @if($ho['home_products_enabled'] && $productNodes->count())
                            <div>
                                <h3 class="flex items-center gap-1.5 text-sm font-black text-zinc-700 dark:text-zinc-200">
                                    <x-icon name="rocket" class="size-4 text-brand-600" />
                                    پروژه‌های آماده
                                </h3>
                                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                    @foreach($productNodes as $node)
                                        @php($root = $node['model'])
                                        <div class="group rounded-2xl bg-white p-4 ring-1 ring-zinc-200/80 transition-all hover:-translate-y-0.5 hover:shadow-card-lg dark:bg-zinc-900 dark:ring-zinc-700/60"
                                             wire:key="prd-cat-{{ $root->id }}">
                                            <div class="flex items-center gap-3">
                                                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-teal-50 text-teal-600 transition-colors group-hover:bg-teal-100 dark:bg-teal-500/10 dark:text-teal-400">
                                                    <x-icon name="folder" class="size-5" />
                                                </span>
                                                <a href="{{ route('shop.products', ['category' => $root->id]) }}" wire:navigate
                                                   class="flex min-w-0 flex-1 items-center justify-between gap-2 rounded-lg focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:outline-none">
                                                    <span class="truncate text-sm font-black text-zinc-900 dark:text-zinc-50">{{ $root->name }}</span>
                                                    <span class="inline-flex shrink-0 items-center rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-bold tabular-nums text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                                                        {{ fa_num($root->shop_count ?? 0) }} مورد
                                                    </span>
                                                </a>
                                            </div>
                                            @if($node['children']->count())
                                                <div class="mt-3 flex flex-wrap gap-1.5">
                                                    @foreach($node['children'] as $child)
                                                        <a href="{{ route('shop.products', ['category' => $child->id]) }}" wire:navigate
                                                           class="inline-flex items-center gap-1 rounded-lg bg-zinc-50 px-2 py-1 text-[11px] font-bold text-zinc-600 ring-1 ring-zinc-200/70 transition-colors hover:bg-teal-50 hover:text-teal-700 dark:bg-zinc-800/60 dark:text-zinc-300 dark:ring-zinc-700/60 dark:hover:bg-teal-500/10 dark:hover:text-teal-400">
                                                            {{ $child->name }}
                                                            <span class="tabular-nums opacity-60">{{ fa_num($child->shop_count ?? 0) }}</span>
                                                        </a>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </section>
        </div>
        @endif
