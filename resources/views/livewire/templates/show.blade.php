<div class="space-y-6">

    {{-- ============ Header ============ --}}
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="flex items-start gap-4">
            <a href="{{ route('admin.templates.index') }}" wire:navigate
               class="mt-1 rounded-xl p-2 text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-800 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
               aria-label="بازگشت به قالب‌ها">
                <x-icon name="arrow-right" class="size-5" />
            </a>
            @if($template->thumbnail)
                <img src="{{ $template->thumbnail_url }}" alt="{{ $template->name }}" class="size-14 rounded-2xl object-cover ring-1 ring-zinc-200 dark:ring-zinc-700" />
            @else
                <div class="flex size-14 items-center justify-center rounded-2xl bg-gradient-to-bl from-brand-100 to-teal-50 dark:from-zinc-800 dark:to-zinc-900">
                    <x-icon name="layout-template" class="size-6 text-brand-500" />
                </div>
            @endif

            <div>
                <h1 class="text-xl font-black text-zinc-900 dark:text-zinc-50">{{ $template->name }}</h1>
                <div class="mt-1.5 flex flex-wrap items-center gap-2">
                    @if($template->status === \App\Models\Template::STATUS_ACTIVE)
                        <x-badge variant="success" icon="check-circle">فعال</x-badge>
                    @elseif($template->status === \App\Models\Template::STATUS_DRAFT)
                        <x-badge variant="neutral">پیش‌نویس</x-badge>
                    @else
                        <x-badge variant="warning" icon="archive">آرشیو</x-badge>
                    @endif

                    @if($template->is_sold_exclusively)
                        <x-badge variant="violet" icon="crown">به‌صورت اختصاصی فروخته شد</x-badge>
                    @endif

                    @if($template->category)
                        <x-badge variant="primary" icon="tag">{{ $template->category->name }}</x-badge>
                    @endif

                    @if($template->is_free || $template->final_price <= 0)
                        <x-badge variant="success" icon="gift">رایگان</x-badge>
                    @else
                        <span class="text-sm font-black tabular-nums text-zinc-700 dark:text-zinc-200">{{ money($template->final_price) }}</span>
                        @if($template->discount > 0)
                            <span class="text-xs text-zinc-400 line-through">{{ money($template->price) }}</span>
                        @endif
                        @if($template->has_exclusive_offer)
                            <span class="rounded-lg bg-violet-50 px-2 py-0.5 text-xs font-black text-violet-700 dark:bg-violet-500/10 dark:text-violet-400">
                                اختصاصی: {{ money($template->exclusive_final_price) }}
                            </span>
                        @endif
                    @endif

                    <a href="{{ route('shop.template', $template->slug) }}" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-1 text-xs font-bold text-brand-600 hover:underline dark:text-brand-400">
                        <x-icon name="external-link" class="size-3.5" />
                        مشاهده در فروشگاه
                    </a>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <x-btn variant="secondary" icon="pencil" wire:click="openEditTemplate">ویرایش قالب</x-btn>
        </div>
    </div>

    {{-- ============ Tabs ============ --}}
    <x-tabs :tabs="[
        ['id' => 'info', 'label' => 'اطلاعات', 'icon' => 'info'],
        ['id' => 'versions', 'label' => 'نسخه‌ها', 'icon' => 'layers', 'count' => $template->versions()->count()],
        ['id' => 'gallery', 'label' => 'گالری', 'icon' => 'image', 'count' => $template->images()->count()],
        ['id' => 'purchases', 'label' => 'خریدها', 'icon' => 'receipt', 'count' => $template->purchases()->where('status', 'paid')->count()],
        ['id' => 'licenses', 'label' => 'لایسنس‌ها', 'icon' => 'key', 'count' => $template->licenses()->count()],
        ['id' => 'comments', 'label' => 'دیدگاه‌ها', 'icon' => 'message-square', 'count' => $template->comments()->count()],
    ]" :active="$tab" wire:key="template-tabs-{{ $template->id }}">

        {{-- ================= INFO ================= --}}
        <x-slot:info>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                {{-- آمار --}}
                <div class="card p-5">
                    <h2 class="mb-4 text-sm font-black text-zinc-900 dark:text-zinc-50">آمار</h2>
                    <div class="space-y-3 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-zinc-500 dark:text-zinc-400">فروش معمولی</span>
                            <span class="font-bold tabular-nums">{{ fa_num($this->stats['normal_sales']) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-zinc-500 dark:text-zinc-400">فروش اختصاصی</span>
                            <span class="font-bold tabular-nums">{{ fa_num($this->stats['exclusive_sales']) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-zinc-500 dark:text-zinc-400">درآمد کل</span>
                            <span class="font-bold tabular-nums">{{ money($this->stats['revenue']) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-zinc-500 dark:text-zinc-400">بازدید صفحه</span>
                            <span class="font-bold tabular-nums">{{ fa_num($template->views_count) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-zinc-500 dark:text-zinc-400">دانلود نسخه‌ها</span>
                            <span class="font-bold tabular-nums">{{ fa_num($template->versions()->sum('downloads_count')) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-zinc-500 dark:text-zinc-400">میانگین امتیاز</span>
                            <span class="font-bold tabular-nums">
                                @if($template->rating)
                                    {{ fa_num($template->rating) }} از ۵
                                @else
                                    —
                                @endif
                            </span>
                        </div>
                    </div>
                </div>

                {{-- مشخصات --}}
                <div class="card p-5 lg:col-span-2">
                    <h2 class="mb-4 text-sm font-black text-zinc-900 dark:text-zinc-50">مشخصات و قیمت‌گذاری</h2>
                    <dl class="space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-4">
                            <dt class="shrink-0 text-zinc-500 dark:text-zinc-400">نامک</dt>
                            <dd class="font-mono text-xs text-zinc-700 dark:text-zinc-300" dir="ltr">{{ $template->slug }}</dd>
                        </div>

                        <div class="rounded-xl bg-brand-50/60 p-3 ring-1 ring-brand-100 dark:bg-brand-500/5 dark:ring-brand-500/20">
                            <p class="mb-2 text-xs font-black text-brand-700 dark:text-brand-400">خرید معمولی</p>
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-zinc-500 dark:text-zinc-400">قیمت پایه</dt>
                                <dd class="font-bold tabular-nums">{{ $template->is_free ? 'رایگان' : money($template->price) }}</dd>
                            </div>
                            @if(!$template->is_free && $template->discount > 0)
                                <div class="mt-2 flex items-center justify-between gap-4">
                                    <dt class="text-zinc-500 dark:text-zinc-400">تخفیف</dt>
                                    <dd class="font-bold">{{ fa_num($template->discount) }}٪</dd>
                                </div>
                                <div class="mt-2 flex items-center justify-between gap-4">
                                    <dt class="text-zinc-500 dark:text-zinc-400">قیمت نهایی</dt>
                                    <dd class="font-black tabular-nums text-brand-600 dark:text-brand-400">{{ money($template->final_price) }}</dd>
                                </div>
                            @endif
                        </div>

                        @if($template->exclusive_price > 0 || $template->is_sold_exclusively)
                            <div class="rounded-xl bg-violet-50/60 p-3 ring-1 ring-violet-100 dark:bg-violet-500/5 dark:ring-violet-500/20">
                                <p class="mb-2 flex items-center gap-1 text-xs font-black text-violet-700 dark:text-violet-400">
                                    <x-icon name="crown" class="size-3.5" />
                                    خرید اختصاصی
                                    @if($template->is_sold_exclusively)
                                        <x-badge variant="violet">فروخته شد</x-badge>
                                    @endif
                                </p>
                                <div class="flex items-center justify-between gap-4">
                                    <dt class="text-zinc-500 dark:text-zinc-400">قیمت پایه</dt>
                                    <dd class="font-bold tabular-nums">{{ money($template->exclusive_price) }}</dd>
                                </div>
                                @if($template->exclusive_discount > 0)
                                    <div class="mt-2 flex items-center justify-between gap-4">
                                        <dt class="text-zinc-500 dark:text-zinc-400">تخفیف</dt>
                                        <dd class="font-bold">{{ fa_num($template->exclusive_discount) }}٪</dd>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between gap-4">
                                        <dt class="text-zinc-500 dark:text-zinc-400">قیمت نهایی</dt>
                                        <dd class="font-black tabular-nums text-violet-600 dark:text-violet-400">{{ money($template->exclusive_final_price) }}</dd>
                                    </div>
                                @endif
                                @if($template->exclusive_note)
                                    <p class="mt-2 border-t border-violet-100 pt-2 text-xs leading-6 text-zinc-600 dark:border-violet-500/20 dark:text-zinc-400">{{ $template->exclusive_note }}</p>
                                @endif
                                @if($template->is_sold_exclusively && $template->exclusivePurchase)
                                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                                        خریدار: {{ $template->exclusivePurchase->customer?->name ?? 'مهمان (خرید فروشگاه)' }}
                                    </p>
                                @endif
                            </div>
                        @endif

                        <div class="flex items-start justify-between gap-4">
                            <dt class="shrink-0 text-zinc-500 dark:text-zinc-400">توضیح کوتاه</dt>
                            <dd class="text-end leading-6">{{ \Illuminate\Support\Str::limit($template->short_description ?? '—', 200) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- کنترل واگذاری اختصاصی — قالب پس از خرید اختصاصی از فروشگاه/API حذف می‌شود --}}
            <div class="mt-4 card p-5" wire:key="exclusive-control-{{ $template->id }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="flex items-center gap-1.5 text-sm font-black text-zinc-900 dark:text-zinc-50">
                        <x-icon name="crown" class="size-4 text-violet-500" />
                        وضعیت واگذاری اختصاصی
                    </h2>
                    @if($template->is_sold_exclusively)
                        <x-badge variant="violet" icon="crown">فروخته شد (اختصاصی)</x-badge>
                    @else
                        <x-badge variant="success" icon="badge-check">عمومی — قابل خرید</x-badge>
                    @endif
                </div>

                @if($template->is_sold_exclusively)
                    <div class="mt-3 space-y-2 rounded-xl bg-violet-50/60 p-3 text-sm ring-1 ring-violet-100 dark:bg-violet-500/5 dark:ring-violet-500/20">
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-zinc-500 dark:text-zinc-400">واگذارشده در</span>
                            <span class="font-bold tabular-nums">{{ $template->sold_exclusively_at?->format('Y/m/d H:i') ?? '—' }}</span>
                        </div>
                        @if($template->exclusivePurchase)
                            <div class="flex items-center justify-between gap-4">
                                <span class="text-zinc-500 dark:text-zinc-400">خرید اختصاصی ثبت‌شده</span>
                                <span class="font-bold">
                                    {{ $template->exclusivePurchase->customer?->name ?? 'مهمان (خرید فروشگاه)' }}
                                    — {{ money($template->exclusivePurchase->amount) }}
                                </span>
                            </div>
                        @else
                            <p class="text-xs leading-6 text-zinc-500 dark:text-zinc-400">
                                این وضعیت به‌صورت دستی علامت خورده است (خرید اختصاصی پرداخت‌شده‌ای در سیستم ثبت نشده).
                            </p>
                        @endif
                    </div>
                @endif

                <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                    <x-input wire:model="buyerLabel" maxlength="190"
                             placeholder="برچسب نمایشی خریدار — مثلاً: واگذارشده به شرکت نمونه"
                             aria-label="برچسب خریدار اختصاصی" />
                    <button type="button" wire:click="saveBuyerLabel" wire:loading.attr="disabled"
                            class="inline-flex shrink-0 cursor-pointer items-center justify-center gap-1 rounded-xl bg-zinc-900 px-3.5 py-2.5 text-xs font-black text-white transition-opacity hover:opacity-90 dark:bg-zinc-100 dark:text-zinc-900">
                        ذخیره برچسب
                    </button>
                    @if($template->is_sold_exclusively)
                        <button type="button" wire:click="releaseExclusive"
                                wire:confirm="قالب دوباره عمومی شود و قابل خرید گردد؟"
                                class="inline-flex shrink-0 cursor-pointer items-center justify-center gap-1 rounded-xl bg-emerald-50 px-3.5 py-2.5 text-xs font-black text-emerald-700 ring-1 ring-emerald-200 transition-colors hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/30">
                            <x-icon name="shield-check" class="size-3.5" />
                            بازگشت به حالت عمومی
                        </button>
                    @else
                        <button type="button" wire:click="markExclusiveSold"
                                wire:confirm="قالب از فروشگاه و API حذف و به‌عنوان «فروخته‌شده اختصاصی» علامت بخورد؟"
                                class="inline-flex shrink-0 cursor-pointer items-center justify-center gap-1 rounded-xl bg-violet-50 px-3.5 py-2.5 text-xs font-black text-violet-700 ring-1 ring-violet-200 transition-colors hover:bg-violet-100 dark:bg-violet-500/10 dark:text-violet-400 dark:ring-violet-500/30">
                            <x-icon name="crown" class="size-3.5" />
                            علامت‌گذاری «فروخته‌شده اختصاصی»
                        </button>
                    @endif
                </div>
                <p class="mt-2 text-xs leading-6 text-zinc-500 dark:text-zinc-400">
                    پس از خرید اختصاصی، قالب از فروشگاه و API حذف می‌شود و برچسب خریدار (در صورت پر بودن) روی صفحهٔ قالب نمایش داده می‌شود؛ با «بازگشت به حالت عمومی» قالب دوباره قابل خرید می‌شود.
                </p>
            </div>

            <div class="mt-4 card p-5">
                <h2 class="mb-3 text-sm font-black text-zinc-900 dark:text-zinc-50">توضیحات کامل</h2>
                @if($template->description)
                    <div class="rich-content text-sm leading-7 text-zinc-700 dark:text-zinc-300">{!! $template->description !!}</div>
                @else
                    <x-empty icon="file-text" title="توضیحاتی ثبت نشده" description="با دکمه «ویرایش قالب» توضیحات را اضافه کنید." />
                @endif
            </div>
        </x-slot:info>

        {{-- ================= VERSIONS ================= --}}
        <x-slot:versions>
            <div class="mb-4 flex justify-end">
                <x-btn icon="plus" wire:click="createVersion">نسخه جدید</x-btn>
            </div>

            <div class="space-y-3">
                @forelse($this->versions as $version)
                    <div wire:key="tver-{{ $version->id }}" class="card p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-lg bg-zinc-950 px-2.5 py-1 font-mono text-xs font-bold text-white dark:bg-zinc-700" dir="ltr">v{{ $version->version }}</span>

                                <x-badge variant="neutral">{{ $version::TYPE_LABELS[$version->type] ?? $version->type }}</x-badge>

                                @if($version->status === \App\Models\TemplateVersion::STATUS_ACTIVE)
                                    <x-badge variant="success" icon="check-circle">فعال</x-badge>
                                @elseif($version->status === \App\Models\TemplateVersion::STATUS_DRAFT)
                                    <x-badge variant="neutral">پیش‌نویس</x-badge>
                                @else
                                    <x-badge variant="warning" icon="archive">آرشیو</x-badge>
                                @endif

                                @if($version->is_mandatory)
                                    <x-badge variant="danger" icon="alert-triangle">اجباری</x-badge>
                                @endif
                            </div>

                            <div class="flex items-center gap-1">
                                <button wire:click="editVersion({{ $version->id }})"
                                        class="rounded-lg p-2 text-zinc-500 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10"
                                        title="ویرایش" aria-label="ویرایش نسخه {{ $version->version }}">
                                    <x-icon name="pencil" class="size-4" />
                                </button>
                                <button wire:click="deleteVersion({{ $version->id }})"
                                        wire:confirm="حذف نسخه {{ $version->version }} و فایل آن؟"
                                        class="rounded-lg p-2 text-zinc-500 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10"
                                        title="حذف" aria-label="حذف نسخه {{ $version->version }}">
                                    <x-icon name="trash" class="size-4" />
                                </button>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                            <span class="inline-flex items-center gap-1"><x-icon name="calendar-clock" class="size-3.5" /> {{ $version->release_date ? verta_date($version->release_date) : '—' }}</span>
                            <span class="inline-flex items-center gap-1"><x-icon name="database" class="size-3.5" /> {{ $version->file_size_human }}</span>
                            <span class="inline-flex items-center gap-1"><x-icon name="download" class="size-3.5" /> {{ fa_num($version->downloads_count) }} دانلود</span>
                            @if($version->file_path)
                                <span class="inline-flex items-center gap-1 font-mono" dir="ltr" title="{{ $version->file_path }}"><x-icon name="shield-check" class="size-3.5" /> SHA256 ✓</span>
                            @else
                                <span class="inline-flex items-center gap-1 text-amber-600"><x-icon name="alert-circle" class="size-3.5" /> فایلی آپلود نشده</span>
                            @endif
                        </div>

                        @if($version->changelog)
                            <div class="mt-3 whitespace-pre-line border-t border-zinc-100 pt-3 text-sm leading-6 text-zinc-600 dark:border-zinc-700/50 dark:text-zinc-300">
                                {{ $version->changelog }}
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="card">
                        <x-empty icon="layers" title="هنوز نسخه‌ای ثبت نشده" description="با دکمه «نسخه جدید» اولین نسخه و فایل ZIP قالب را اضافه کنید." />
                    </div>
                @endforelse
            </div>
        </x-slot:versions>

        {{-- ================= GALLERY ================= --}}
        <x-slot:gallery>
            <div class="card p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <input type="file"
                           data-compress accept="image/jpeg,image/png,image/webp,image/gif" multiple wire:model="newImages" class="input w-full sm:flex-1" aria-label="انتخاب تصاویر گالری" />
                    <x-btn icon="upload" wire:click="uploadImages" wire:loading.attr="disabled" wire:target="uploadImages">
                        افزودن به گالری
                    </x-btn>
                </div>
                <x-upload-progress target="newImages" label="در حال آپلود تصاویر گالری" />
                @error('newImages.0') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">حداکثر ۱۰ تصویر در هر بار — JPG/PNG/WEBP تا ۳ مگابایت. ترتیب نمایش با فلش‌ها تغییر می‌کند.</p>
            </div>

            @if($this->images->count())
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach($this->images as $image)
                        <div wire:key="timg-{{ $image->id }}" class="group relative overflow-hidden rounded-2xl bg-zinc-100 ring-1 ring-zinc-200 dark:bg-zinc-800 dark:ring-zinc-700">
                            <img src="{{ $image->url }}" alt="{{ $image->original_name ?: 'گالری ' . $loop->iteration }}" class="aspect-video size-full object-cover" loading="lazy" />

                            <div class="absolute inset-x-0 bottom-0 flex items-center justify-between bg-zinc-950/70 px-2 py-1.5 opacity-0 backdrop-blur transition-opacity group-hover:opacity-100">
                                <div class="flex items-center gap-0.5">
                                    <button wire:click="moveImage({{ $image->id }}, 'up')" class="rounded-md p-1 text-white/80 transition-colors hover:text-white" title="انتقال به عقب" aria-label="انتقال به عقب">
                                        <x-icon name="chevron-right" class="size-4" />
                                    </button>
                                    <button wire:click="moveImage({{ $image->id }}, 'down')" class="rounded-md p-1 text-white/80 transition-colors hover:text-white" title="انتقال به جلو" aria-label="انتقال به جلو">
                                        <x-icon name="chevron-left" class="size-4" />
                                    </button>
                                </div>
                                <button wire:click="$set('deleteImageId', {{ $image->id }})" class="rounded-md p-1 text-rose-300 transition-colors hover:text-rose-200" title="حذف" aria-label="حذف تصویر">
                                    <x-icon name="trash" class="size-4" />
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="card">
                    <x-empty icon="image" title="گالری خالی است" description="تصاویر پیش‌نمایش قالب (اسکرین‌شات صفحات) را برای نمایش در فروشگاه اضافه کنید." />
                </div>
            @endif

            {{-- تأیید حذف تصویر --}}
            <x-modal wire:model="deleteImageId" title="حذف تصویر گالری" size="sm">
                <p class="text-sm leading-7 text-zinc-600 dark:text-zinc-300">آیا از حذف این تصویر از گالری مطمئن هستید؟ این عمل قابل بازگشت نیست.</p>
                <div class="mt-5 flex items-center justify-end gap-2">
                    <x-btn variant="secondary" wire:click="$set('deleteImageId', null)">انصراف</x-btn>
                    <x-btn variant="danger" icon="trash" wire:click="deleteImage">حذف تصویر</x-btn>
                </div>
            </x-modal>
        </x-slot:gallery>

        {{-- ================= PURCHASES ================= --}}
        <x-slot:purchases>
            {{-- خلاصه فروش قالب --}}
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                <x-stat label="فروش موفق" :value="fa_num($this->stats['normal_sales'] + $this->stats['exclusive_sales'])" icon="receipt" variant="primary" hint="خرید پرداخت‌شده" />
                <x-stat label="درآمد این قالب" :value="money($this->stats['revenue'])" icon="credit-card" variant="info" hint="مجموع مبالغ پرداختی" />
                <x-stat label="خرید معمولی" :value="fa_num($this->stats['normal_sales'])" icon="shopping-cart" variant="neutral" hint="عمومی" />
                <x-stat label="خرید اختصاصی" :value="fa_num($this->stats['exclusive_sales'])" icon="crown" variant="violet" hint="واگذاری کامل" />
                <x-stat label="لایسنس فعال" :value="fa_num($this->stats['licenses'])" icon="key" variant="success" hint="صادرشده" />
            </div>

            <div class="card mt-4 overflow-hidden">
                @if($this->purchases->count())
                    <div class="table-wrap ring-0">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>خریدار</th>
                                    <th>نوع</th>
                                    <th>نسخه</th>
                                    <th>مبلغ</th>
                                    <th class="hidden md:table-cell">درگاه</th>
                                    <th>وضعیت</th>
                                    <th class="hidden sm:table-cell">تاریخ</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($this->purchases as $purchase)
                                    <tr wire:key="tp-{{ $purchase->id }}">
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <x-avatar :name="$purchase->customer?->name ?? 'مهمان'" size="sm" />
                                                <div class="min-w-0">
                                                    <p class="truncate text-sm font-semibold text-zinc-800 dark:text-zinc-200">
                                                        {{ $purchase->customer?->name ?? 'مهمان (فروشگاه)' }}
                                                    </p>
                                                    @if($purchase->discount_code_id && $purchase->discountCode)
                                                        <p class="truncate font-mono text-[11px] text-emerald-600 dark:text-emerald-400" dir="ltr">{{ $purchase->discountCode->code }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            @if($purchase->purchase_type === \App\Models\TemplatePurchase::TYPE_EXCLUSIVE)
                                                <x-badge variant="violet" icon="crown">اختصاصی</x-badge>
                                            @else
                                                <x-badge variant="neutral">معمولی</x-badge>
                                            @endif
                                        </td>
                                        <td>
                                            @if($purchase->version)
                                                <span class="font-mono text-xs" dir="ltr">v{{ $purchase->version->version }}</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="font-semibold tabular-nums text-zinc-700 dark:text-zinc-200">
                                            {{ money($purchase->amount, false) }}
                                            @if($purchase->discount_amount > 0)
                                                <span class="block text-[10px] font-normal text-emerald-600 dark:text-emerald-400">−{{ money($purchase->discount_amount, false) }}</span>
                                            @endif
                                        </td>
                                        <td class="hidden text-sm text-zinc-500 md:table-cell">{{ $purchase->gateway ?? '—' }}</td>
                                        <td>
                                            @if($purchase->status === 'paid')
                                                <x-badge variant="success" icon="check-circle">پرداخت شده</x-badge>
                                            @elseif($purchase->status === 'pending')
                                                <x-badge variant="warning" icon="clock">در انتظار</x-badge>
                                            @elseif($purchase->status === 'refunded')
                                                <x-badge variant="neutral">بازپرداخت</x-badge>
                                            @else
                                                <x-badge variant="danger" icon="x-circle">ناموفق</x-badge>
                                            @endif
                                        </td>
                                        <td class="hidden text-sm text-zinc-500 sm:table-cell">{{ $purchase->paid_at ? verta_date($purchase->paid_at) : verta_date($purchase->created_at) }}</td>
                                        <td>
                                            @if($purchase->license)
                                                <span class="inline-flex items-center gap-1 rounded-lg bg-zinc-100 px-2 py-1 font-mono text-[10px] text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300" dir="ltr" title="لایسنس خرید">
                                                    <x-icon name="key" class="size-3" /> {{ $purchase->license->license_key }}
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-zinc-100 px-4 py-3 dark:border-zinc-800">
                        {{ $this->purchases->links() }}
                    </div>
                @else
                    <x-empty icon="receipt" title="هنوز خریدی ثبت نشده" description="خریدهای موفق قالب (فروشگاه و API) اینجا نمایش داده می‌شوند." />
                @endif
            </div>
        </x-slot:purchases>

        {{-- ================= LICENSES ================= --}}
        <x-slot:licenses>
            <div class="card overflow-hidden">
                @if($this->licenses->count())
                    <div class="table-wrap ring-0">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>کلید لایسنس</th>
                                    <th>خریدار</th>
                                    <th>نوع</th>
                                    <th>وضعیت</th>
                                    <th class="hidden md:table-cell">تاریخ صدور</th>
                                    <th class="hidden lg:table-cell">انقضا</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($this->licenses as $license)
                                    <tr wire:key="tl-{{ $license->id }}">
                                        <td>
                                            <span class="font-mono text-xs font-bold text-zinc-800 dark:text-zinc-200" dir="ltr">{{ $license->license_key }}</span>
                                        </td>
                                        <td class="text-sm text-zinc-600 dark:text-zinc-300">{{ $license->customer?->name ?? 'مهمان (فروشگاه)' }}</td>
                                        <td>
                                            @if($license->purchase_type === \App\Models\TemplatePurchase::TYPE_EXCLUSIVE)
                                                <x-badge variant="violet" icon="crown">اختصاصی</x-badge>
                                            @else
                                                <x-badge variant="neutral">معمولی</x-badge>
                                            @endif
                                        </td>
                                        <td>
                                            @if($license->status === 'active')
                                                <x-badge variant="success" icon="check-circle">فعال</x-badge>
                                            @elseif($license->status === 'expired')
                                                <x-badge variant="warning" icon="clock">منقضی</x-badge>
                                            @else
                                                <x-badge variant="danger" icon="x-circle">باطل شده</x-badge>
                                            @endif
                                        </td>
                                        <td class="hidden text-sm text-zinc-500 md:table-cell">{{ $license->starts_at ? verta_date($license->starts_at) : '—' }}</td>
                                        <td class="hidden text-sm text-zinc-500 lg:table-cell">{{ $license->isUnlimited() ? 'دائمی' : ($license->expires_at ? verta_date($license->expires_at) : '—') }}</td>
                                        <td>
                                            @if($license->status === 'active')
                                                <button wire:click="revokeLicense({{ $license->id }})"
                                                        wire:confirm="باطل کردن این لایسنس؟"
                                                        class="rounded-lg p-2 text-zinc-500 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10"
                                                        title="باطل کردن" aria-label="باطل کردن لایسنس">
                                                    <x-icon name="x-circle" class="size-4" />
                                                </button>
                                            @elseif($license->status === 'revoked')
                                                <button wire:click="restoreLicense({{ $license->id }})"
                                                        class="rounded-lg p-2 text-zinc-500 transition-colors hover:bg-emerald-50 hover:text-emerald-600 dark:hover:bg-emerald-500/10"
                                                        title="فعال‌سازی مجدد" aria-label="فعال‌سازی مجدد لایسنس">
                                                    <x-icon name="check" class="size-4" />
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-zinc-100 px-4 py-3 dark:border-zinc-800">
                        {{ $this->licenses->links() }}
                    </div>
                @else
                    <x-empty icon="key" title="لایسنسی صادر نشده" description="با اولین خرید موفق، لایسنس دائمی قالب به‌صورت خودکار صادر می‌شود." />
                @endif
            </div>
        </x-slot:licenses>

        {{-- ================= COMMENTS ================= --}}
        <x-slot:comments>
            <div class="space-y-3">
                @forelse($this->comments as $comment)
                    <div wire:key="pcm-{{ $comment->id }}" class="card p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex items-start gap-3">
                                <x-avatar :name="$comment->name" />
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-sm font-bold text-zinc-900 dark:text-zinc-50">{{ $comment->name }}</span>

                                        @if($comment->rating)
                                            <span class="inline-flex items-center gap-0.5 text-amber-500">
                                                @for($i = 1; $i <= 5; $i++)
                                                    <x-icon name="star" class="{{ $i <= $comment->rating ? 'size-3.5 fill-amber-400' : 'size-3.5 text-zinc-300 dark:text-zinc-600' }}" />
                                                @endfor
                                            </span>
                                        @endif

                                        @if($comment->status === \App\Models\Comment::STATUS_APPROVED)
                                            <x-badge variant="success" icon="check-circle">تأیید شده</x-badge>
                                        @elseif($comment->status === \App\Models\Comment::STATUS_PENDING)
                                            <x-badge variant="warning" icon="clock">در انتظار</x-badge>
                                        @else
                                            <x-badge variant="danger" icon="x-circle">رد شده</x-badge>
                                        @endif
                                    </div>
                                    <p class="mt-1.5 whitespace-pre-line text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $comment->body }}</p>
                                    <p class="mt-1.5 text-xs text-zinc-400">{{ verta_date($comment->created_at) }}@if($comment->email) · {{ $comment->email }}@endif</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-1">
                                @if(!$comment->isApproved())
                                    <button wire:click="approveComment({{ $comment->id }})"
                                            class="rounded-lg p-2 text-zinc-500 transition-colors hover:bg-emerald-50 hover:text-emerald-600 dark:hover:bg-emerald-500/10"
                                            title="تأیید" aria-label="تأیید دیدگاه">
                                        <x-icon name="check" class="size-4" />
                                    </button>
                                @endif
                                @if($comment->status !== \App\Models\Comment::STATUS_REJECTED)
                                    <button wire:click="rejectComment({{ $comment->id }})"
                                            class="rounded-lg p-2 text-zinc-500 transition-colors hover:bg-amber-50 hover:text-amber-600 dark:hover:bg-amber-500/10"
                                            title="رد" aria-label="رد دیدگاه">
                                        <x-icon name="x" class="size-4" />
                                    </button>
                                @endif
                                <button wire:click="deleteComment({{ $comment->id }})"
                                        wire:confirm="حذف این دیدگاه؟"
                                        class="rounded-lg p-2 text-zinc-500 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10"
                                        title="حذف" aria-label="حذف دیدگاه">
                                    <x-icon name="trash" class="size-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="card">
                        <x-empty icon="message-square" title="دیدگاهی ثبت نشده" description="دیدگاه‌های مشتریان پس از ثبت در این بخش نمایش داده می‌شوند." />
                    </div>
                @endforelse
            </div>

            @if($this->comments->hasPages())
                <div class="mt-4">{{ $this->comments->links() }}</div>
            @endif
        </x-slot:comments>
    </x-tabs>

    {{-- ============ Template Edit Modal ============ --}}
    <x-modal wire:model="showModal" title="ویرایش قالب" size="lg">
        <form wire:submit="saveTemplate" class="space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-field label="نام قالب" for="eform.name" required>
                    <x-input id="eform.name" wire:model="form.name" />
                </x-field>
                <x-field label="نامک (slug)" for="eform.slug" hint="خالی = خودکار">
                    <x-input id="eform.slug" wire:model="form.slug" dir="ltr" class="text-start" />
                </x-field>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-field label="دسته‌بندی" for="eform.category_id">
                    <x-select id="eform.category_id" wire:model="form.category_id" :options="\App\Models\Category::flatOptions(\App\Models\Category::TYPE_TEMPLATE)" placeholder="بدون دسته" />
                </x-field>
                <x-field label="وضعیت" for="eform.status">
                    <x-select id="eform.status" wire:model="form.status" :options="\App\Models\Template::STATUS_LABELS" />
                </x-field>
                <x-field label="ترتیب نمایش" for="eform.sort_order">
                    <x-input id="eform.sort_order" type="number" min="0" wire:model="form.sort_order" />
                </x-field>
            </div>

            <x-field label="توضیح کوتاه" for="eform.short_description">
                <x-textarea id="eform.short_description" wire:model="form.short_description" rows="3" />
            </x-field>

            <x-field label="توضیحات کامل (HTML)" for="eform.description">
                <x-ckeditor model="form.description" :value="$form['description'] ?? ''" />
            </x-field>

            {{-- قیمت‌گذاری دوگانه --}}
            <div class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-700/60">
                <p class="mb-3 flex items-center gap-1.5 text-sm font-black text-zinc-800 dark:text-zinc-100">
                    <x-icon name="wallet" class="size-4 text-brand-600" />
                    قیمت‌گذاری
                </p>
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div class="rounded-xl bg-brand-50/60 p-3 ring-1 ring-brand-100 dark:bg-brand-500/5 dark:ring-brand-500/20">
                        <p class="mb-2 text-xs font-black text-brand-700 dark:text-brand-400">۱) خرید معمولی</p>
                        <div class="grid grid-cols-2 gap-3">
                            <x-field label="قیمت (تومان)" for="eform.price">
                                <x-input id="eform.price" type="number" min="0" wire:model="form.price" />
                            </x-field>
                            <x-field label="تخفیف (٪)" for="eform.discount">
                                <x-input id="eform.discount" type="number" min="0" max="100" wire:model="form.discount" />
                            </x-field>
                        </div>
                    </div>
                    <div class="rounded-xl bg-violet-50/60 p-3 ring-1 ring-violet-100 dark:bg-violet-500/5 dark:ring-violet-500/20">
                        <p class="mb-2 flex items-center gap-1 text-xs font-black text-violet-700 dark:text-violet-400">
                            <x-icon name="crown" class="size-3.5" />
                            ۲) خرید اختصاصی
                        </p>
                        <div class="grid grid-cols-2 gap-3">
                            <x-field label="قیمت (تومان)" for="eform.exclusive_price" hint="صفر = غیرفعال">
                                <x-input id="eform.exclusive_price" type="number" min="0" wire:model="form.exclusive_price" />
                            </x-field>
                            <x-field label="تخفیف (٪)" for="eform.exclusive_discount">
                                <x-input id="eform.exclusive_discount" type="number" min="0" max="100" wire:model="form.exclusive_discount" />
                            </x-field>
                        </div>
                    </div>
                </div>
                <x-field class="mt-3" label="توضیح خرید اختصاصی" for="eform.exclusive_note">
                    <x-textarea id="eform.exclusive_note" wire:model="form.exclusive_note" rows="2" />
                </x-field>
            </div>

            <div class="flex items-center gap-6">
                <x-toggle wire:model="form.is_free" label="این قالب رایگان است" />
            </div>

            {{-- تصویر شاخص --}}
            <x-field label="تصویر شاخص">
                @if($template->thumbnail && !$thumbnailFile && !$removeThumbnail)
                    <div class="mb-2 flex items-center gap-3">
                        <img src="{{ $template->thumbnail_url }}" alt="تصویر فعلی قالب" class="size-16 rounded-xl object-cover ring-1 ring-zinc-200 dark:ring-zinc-700" />
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">تصویر فعلی — با انتخاب فایل جدید جایگزین می‌شود</p>
                    </div>
                @endif
                <div wire:key="show-thumb-preview-{{ $thumbNonce }}" x-data="filePreview()" class="space-y-2">
                    <input type="file"
                           data-compress accept="image/jpeg,image/png,image/webp,image/gif" wire:model="thumbnailFile" x-ref="input" class="input" />
                    <template x-for="(preview, i) in previews" :key="i">
                        <div class="flex items-center gap-3">
                            <img :src="preview.url" :alt="preview.name" class="size-16 shrink-0 rounded-xl object-cover ring-1 ring-zinc-200 dark:ring-zinc-700" />
                            <p class="truncate text-xs text-zinc-500" x-text="preview.name"></p>
                        </div>
                    </template>
                </div>
                <x-upload-progress target="thumbnailFile" label="در حال آپلود تصویر شاخص" />
                @error('thumbnailFile') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                <div class="mt-2">
                    <x-toggle wire:model="removeThumbnail" label="حذف تصویر فعلی" />
                </div>
            </x-field>

            {{-- گالری جدید --}}
            <x-field label="افزودن تصاویر به گالری" hint="اختیاری — به گالری موجود اضافه می‌شود">
                <input type="file"
                           data-compress accept="image/jpeg,image/png,image/webp,image/gif" multiple wire:model="newImages" class="input" aria-label="تصاویر جدید گالری" />
                <x-upload-progress target="newImages" label="در حال آپلود گالری" />
                @error('newImages.0') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </x-field>

            <div class="flex items-center justify-end gap-2 border-t border-zinc-100 pt-4 dark:border-zinc-700/50">
                <x-btn variant="secondary" type="button" wire:click="$set('showModal', false)">انصراف</x-btn>
                <x-btn icon="save" type="submit">ذخیره تغییرات</x-btn>
            </div>
        </form>
    </x-modal>

    {{-- ============ Version Modal ============ --}}
    <x-modal wire:model="showVersionModal" :title="$editingVersionId ? 'ویرایش نسخه' : 'نسخه جدید'" size="lg">
        <form wire:submit="saveVersion" class="space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-field label="شماره نسخه" for="versionForm.version" required>
                    <x-input id="versionForm.version" wire:model="versionForm.version" dir="ltr" class="text-start font-mono" placeholder="1.0.0" />
                </x-field>
                <x-field label="نوع" for="versionForm.type">
                    <x-select id="versionForm.type" wire:model="versionForm.type" :options="\App\Models\TemplateVersion::TYPE_LABELS" />
                </x-field>
                <x-field label="تاریخ انتشار" for="versionForm.release_date">
                    <x-input id="versionForm.release_date" type="date" wire:model="versionForm.release_date" />
                </x-field>
            </div>

            <x-field label="توضیح تغییرات (changelog)" for="versionForm.changelog">
                <x-textarea id="versionForm.changelog" wire:model="versionForm.changelog" rows="3" placeholder="خلاصه تغییرات این نسخه…" />
            </x-field>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-field label="چیزهای اضافه‌شده" for="versionForm.what_added">
                    <x-textarea id="versionForm.what_added" wire:model="versionForm.what_added" rows="3" />
                </x-field>
                <x-field label="چیزهای تغییر‌یافته" for="versionForm.what_changed">
                    <x-textarea id="versionForm.what_changed" wire:model="versionForm.what_changed" rows="3" />
                </x-field>
                <x-field label="باگ‌های رفع‌شده" for="versionForm.what_fixed">
                    <x-textarea id="versionForm.what_fixed" wire:model="versionForm.what_fixed" rows="3" />
                </x-field>
            </div>

            <x-field label="فایل ZIP قالب">
                <div wire:key="tver-file-{{ (int) $editingVersionId }}" x-data class="space-y-2">
                    <input type="file" accept=".zip,application/zip"
                           wire:model="versionFile"
                           data-chunk-upload
                           data-progress-name="versionFile"
                           data-chunk-props="versionChunkPath|versionChunkName|versionChunkSize"
                           data-chunk-max="512"
                           data-chunk-accept="zip"
                           class="input" />
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        @if($editingVersionId)
                            اگر فایل جدید انتخاب نکنید، فایل فعلی حفظ می‌شود.
                        @else
                            فایل ZIP این نسخه — پس از خرید، دانلود از همین فایل انجام می‌شود.
                        @endif
                    </p>
                    @if($versionChunkPath)
                        <p class="flex items-center gap-1.5 rounded-lg bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-700 ring-1 ring-emerald-600/10 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-400/20">
                            <x-icon name="check-circle" class="size-3.5" />
                            فایل <code class="font-mono" dir="ltr">{{ \Illuminate\Support\Str::limit((string) $versionChunkName, 40) }}</code>
                            ({{ bytes_human($versionChunkSize) ?? '—' }}) آماده ذخیره است.
                        </p>
                    @endif
                </div>
                <x-upload-progress target="versionFile" label="در حال آپلود فایل ZIP" />
                @error('versionFile') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </x-field>

            <div class="flex flex-wrap items-center gap-6">
                <x-toggle wire:model="versionForm.is_mandatory" label="آپدیت اجباری است" />
                <div class="w-40">
                    <x-select wire:model="versionForm.status" :options="[
                        \App\Models\TemplateVersion::STATUS_ACTIVE   => 'فعال',
                        \App\Models\TemplateVersion::STATUS_DRAFT    => 'پیش‌نویس',
                        \App\Models\TemplateVersion::STATUS_ARCHIVED => 'آرشیو',
                    ]" />
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-zinc-100 pt-4 dark:border-zinc-700/50">
                <x-btn variant="secondary" type="button" wire:click="$set('showVersionModal', false)">انصراف</x-btn>
                <x-btn icon="save" type="submit">ذخیره نسخه</x-btn>
            </div>
        </form>
    </x-modal>
</div>
