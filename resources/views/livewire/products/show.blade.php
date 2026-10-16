<div class="space-y-6">

    {{-- ============ Header ============ --}}
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="flex items-start gap-4">
            <a href="{{ route('admin.products.index') }}" wire:navigate
               class="mt-1 rounded-xl p-2 text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-800 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
               aria-label="بازگشت به محصولات">
                <x-icon name="arrow-right" class="size-5" />
            </a>
            @if($product->thumbnail)
                <img src="{{ $product->thumbnail_url }}" alt="{{ $product->name }}" class="size-14 rounded-2xl object-cover ring-1 ring-zinc-200 dark:ring-zinc-700" />
            @else
                <div class="flex size-14 items-center justify-center rounded-2xl bg-gradient-to-bl from-brand-100 to-teal-50 dark:from-zinc-800 dark:to-zinc-900">
                    <x-icon name="rocket" class="size-6 text-brand-500" />
                </div>
            @endif

            <div>
                <h1 class="text-xl font-black text-zinc-900 dark:text-zinc-50">{{ $product->name }}</h1>
                <div class="mt-1.5 flex flex-wrap items-center gap-2">
                    @if($product->status === \App\Models\Product::STATUS_ACTIVE)
                        <x-badge variant="success" icon="check-circle">فعال</x-badge>
                    @elseif($product->status === \App\Models\Product::STATUS_DRAFT)
                        <x-badge variant="neutral">پیش‌نویس</x-badge>
                    @else
                        <x-badge variant="warning" icon="archive">آرشیو</x-badge>
                    @endif

                    @if($product->category)
                        <x-badge variant="primary" icon="tag">{{ $product->category->name }}</x-badge>
                    @endif

                    @if($product->is_free || $product->final_price <= 0)
                        <x-badge variant="success" icon="gift">رایگان</x-badge>
                    @else
                        <span class="text-sm font-black tabular-nums text-zinc-700 dark:text-zinc-200">{{ money($product->final_price) }}</span>
                        @if($product->discount > 0)
                            <span class="text-xs text-zinc-400 line-through">{{ money($product->price) }}</span>
                        @endif
                    @endif

                    <a href="{{ route('shop.product', $product->slug) }}" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-1 text-xs font-bold text-brand-600 hover:underline dark:text-brand-400">
                        <x-icon name="external-link" class="size-3.5" />
                        مشاهده در فروشگاه
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ Tabs ============ --}}
    <x-tabs :tabs="[
        ['id' => 'info', 'label' => 'اطلاعات', 'icon' => 'info'],
        ['id' => 'versions', 'label' => 'نسخه‌ها', 'icon' => 'layers', 'count' => $product->versions()->count()],
        ['id' => 'comments', 'label' => 'دیدگاه‌ها', 'icon' => 'message-square', 'count' => $product->comments()->count()],
    ]" :active="$tab" wire:key="product-tabs-{{ $product->id }}">

        {{-- ================= INFO ================= --}}
        <x-slot:info>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                {{-- آمار --}}
                <div class="card p-5">
                    <h2 class="mb-4 text-sm font-black text-zinc-900 dark:text-zinc-50">آمار</h2>
                    <div class="space-y-3 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-zinc-500 dark:text-zinc-400">فروش موفق</span>
                            <span class="font-bold tabular-nums">{{ fa_num($product->purchases()->where('status', 'paid')->count()) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-zinc-500 dark:text-zinc-400">درآمد کل</span>
                            <span class="font-bold tabular-nums">{{ money($product->purchases()->where('status', 'paid')->sum('amount')) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-zinc-500 dark:text-zinc-400">بازدید صفحه</span>
                            <span class="font-bold tabular-nums">{{ fa_num($product->views_count) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-zinc-500 dark:text-zinc-400">دانلود نسخه‌ها</span>
                            <span class="font-bold tabular-nums">{{ fa_num($product->versions()->sum('downloads_count')) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-zinc-500 dark:text-zinc-400">میانگین امتیاز</span>
                            <span class="font-bold tabular-nums">
                                @if($product->rating)
                                    {{ fa_num($product->rating) }} از ۵
                                @else
                                    —
                                @endif
                            </span>
                        </div>
                    </div>
                </div>

                {{-- مشخصات --}}
                <div class="card p-5 lg:col-span-2">
                    <h2 class="mb-4 text-sm font-black text-zinc-900 dark:text-zinc-50">مشخصات</h2>
                    <dl class="space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-4">
                            <dt class="shrink-0 text-zinc-500 dark:text-zinc-400">نامک</dt>
                            <dd class="font-mono text-xs text-zinc-700 dark:text-zinc-300" dir="ltr">{{ $product->slug }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="shrink-0 text-zinc-500 dark:text-zinc-400">قیمت پایه</dt>
                            <dd class="font-bold tabular-nums">{{ $product->is_free ? 'رایگان' : money($product->price) }}</dd>
                        </div>
                        @if(!$product->is_free && $product->discount > 0)
                            <div class="flex items-center justify-between gap-4">
                                <dt class="shrink-0 text-zinc-500 dark:text-zinc-400">تخفیف</dt>
                                <dd class="font-bold">{{ fa_num($product->discount) }}٪</dd>
                            </div>
                        @endif
                        <div class="flex items-start justify-between gap-4">
                            <dt class="shrink-0 text-zinc-500 dark:text-zinc-400">توضیح کوتاه</dt>
                            <dd class="text-end leading-6">{{ \Illuminate\Support\Str::limit($product->short_description ?? '—', 200) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="mt-4 card p-5">
                <h2 class="mb-3 text-sm font-black text-zinc-900 dark:text-zinc-50">توضیحات کامل</h2>
                @if($product->description)
                    <div class="rich-content text-sm leading-7 text-zinc-700 dark:text-zinc-300">{!! $product->description !!}</div>
                @else
                    <x-empty icon="file-text" title="توضیحاتی ثبت نشده" description="از صفحه فهرست محصولات، دکمه ویرایش را بزنید." />
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
                    <div wire:key="pver-{{ $version->id }}" class="card p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-lg bg-zinc-950 px-2.5 py-1 font-mono text-xs font-bold text-white dark:bg-zinc-700" dir="ltr">v{{ $version->version }}</span>

                                <x-badge variant="neutral">{{ $version::TYPE_LABELS[$version->type] ?? $version->type }}</x-badge>

                                @if($version->status === \App\Models\ProductVersion::STATUS_ACTIVE)
                                    <x-badge variant="success" icon="check-circle">فعال</x-badge>
                                @elseif($version->status === \App\Models\ProductVersion::STATUS_DRAFT)
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
                        <x-empty icon="layers" title="هنوز نسخه‌ای ثبت نشده" description="با دکمه «نسخه جدید» اولین نسخه و فایل ZIP محصول را اضافه کنید." />
                    </div>
                @endforelse
            </div>
        </x-slot:versions>

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

    {{-- ============ Version Modal ============ --}}
    <x-modal wire:model="showVersionModal" :title="$editingVersionId ? 'ویرایش نسخه' : 'نسخه جدید'" size="lg">
        <form wire:submit="saveVersion" class="space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-field label="شماره نسخه" for="versionForm.version" required>
                    <x-input id="versionForm.version" wire:model="versionForm.version" dir="ltr" class="text-start font-mono" placeholder="1.0.0" />
                </x-field>
                <x-field label="نوع" for="versionForm.type">
                    <x-select id="versionForm.type" wire:model="versionForm.type" :options="\App\Models\ProductVersion::TYPE_LABELS" />
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

            <x-field label="فایل ZIP محصول">
                <div wire:key="pver-file-{{ (int) $editingVersionId }}" wire:ignore x-data class="space-y-2">
                    <input type="file" accept=".zip,application/zip" wire:model="versionFile" class="input" />
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        @if($editingVersionId)
                            اگر فایل جدید انتخاب نکنید، فایل فعلی حفظ می‌شود.
                        @else
                            فایل ZIP این نسخه — پس از خرید، دانلود از همین فایل انجام می‌شود.
                        @endif
                    </p>
                </div>
                <div wire:loading wire:target="versionFile" class="mt-2 flex items-center gap-2 text-xs text-brand-600 dark:text-brand-400">
                    <x-icon name="loader" class="size-4 animate-spin" />
                    در حال آپلود فایل…
                </div>
                @error('versionFile') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </x-field>

            <div class="flex flex-wrap items-center gap-6">
                <x-toggle wire:model="versionForm.is_mandatory" label="آپدیت اجباری است" />
                <div class="w-40">
                    <x-select wire:model="versionForm.status" :options="[
                        \App\Models\ProductVersion::STATUS_ACTIVE   => 'فعال',
                        \App\Models\ProductVersion::STATUS_DRAFT    => 'پیش‌نویس',
                        \App\Models\ProductVersion::STATUS_ARCHIVED => 'آرشیو',
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
