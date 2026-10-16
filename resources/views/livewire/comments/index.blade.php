<div class="space-y-6">

    {{-- ============ Header ============ --}}
    <div>
        <h1 class="flex items-center gap-2 text-xl font-black text-zinc-900 dark:text-zinc-50">
            <x-icon name="message-square" class="size-5 text-brand-600" />
            دیدگاه‌های محصولات
        </h1>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
            مدیریت دیدگاه‌های ثبت‌شده روی صفحه محصولات — دیدگاه‌ها پس از تأیید شما در فروشگاه نمایش داده می‌شوند.
        </p>
    </div>

    {{-- ============ Filters ============ --}}
    <div class="card p-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
            <div class="sm:col-span-2">
                <x-input wire:model.live.debounce.400ms="search" type="search" icon="search" placeholder="جستجوی نام، ایمیل یا متن دیدگاه…" />
            </div>
            <div>
                <x-select wire:model.live="statusFilter" :options="\App\Models\ProductComment::STATUS_LABELS" placeholder="همه وضعیت‌ها" />
            </div>
            <div>
                <x-select wire:model.live="productFilter" :options="$this->products" placeholder="همه محصولات" />
            </div>
        </div>
    </div>

    {{-- ============ List ============ --}}
    <div class="space-y-3">
        @forelse($this->records as $comment)
            <div wire:key="cm-{{ $comment->id }}" class="card p-4">
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

                                @if($comment->status === \App\Models\ProductComment::STATUS_APPROVED)
                                    <x-badge variant="success" icon="check-circle">تأیید شده</x-badge>
                                @elseif($comment->status === \App\Models\ProductComment::STATUS_PENDING)
                                    <x-badge variant="warning" icon="clock">در انتظار</x-badge>
                                @else
                                    <x-badge variant="danger" icon="x-circle">رد شده</x-badge>
                                @endif

                                @if($comment->product)
                                    <a href="{{ route('admin.products.show', $comment->product) }}" wire:navigate
                                       class="inline-flex items-center gap-1 rounded-lg bg-zinc-100 px-2 py-0.5 text-[11px] font-bold text-zinc-600 transition-colors hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700">
                                        <x-icon name="rocket" class="size-3" />
                                        {{ $comment->product->name }}
                                    </a>
                                @endif
                            </div>
                            <p class="mt-1.5 whitespace-pre-line text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $comment->body }}</p>
                            <p class="mt-1.5 text-xs text-zinc-400">{{ verta_date($comment->created_at) }}@if($comment->email) · {{ $comment->email }}@endif</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-1">
                        @if(!$comment->isApproved())
                            <button wire:click="approve({{ $comment->id }})"
                                    class="rounded-lg p-2 text-zinc-500 transition-colors hover:bg-emerald-50 hover:text-emerald-600 dark:hover:bg-emerald-500/10"
                                    title="تأیید" aria-label="تأیید دیدگاه">
                                <x-icon name="check" class="size-4" />
                            </button>
                        @endif
                        @if($comment->status !== \App\Models\ProductComment::STATUS_REJECTED)
                            <button wire:click="reject({{ $comment->id }})"
                                    class="rounded-lg p-2 text-zinc-500 transition-colors hover:bg-amber-50 hover:text-amber-600 dark:hover:bg-amber-500/10"
                                    title="رد" aria-label="رد دیدگاه">
                                <x-icon name="x" class="size-4" />
                            </button>
                        @endif
                        <button wire:click="delete({{ $comment->id }})"
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
                <x-empty icon="message-square" title="دیدگاهی یافت نشد" description="هیچ دیدگاهی با این فیلترها ثبت نشده است." />
            </div>
        @endforelse
    </div>

    <div>{{ $this->records->links() }}</div>
</div>
