<div class="space-y-6">

    {{-- ============ Header ============ --}}
    <div>
        <h1 class="flex items-center gap-2 text-xl font-black text-zinc-900 dark:text-zinc-50">
            <x-icon name="message-square" class="size-5 text-brand-600" />
            دیدگاه‌ها
        </h1>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
            مدیریت دیدگاه‌های ثبت‌شده روی صفحه محصولات و پکیج‌ها — دیدگاه‌ها پس از تأیید شما در فروشگاه نمایش داده می‌شوند.
        </p>
    </div>

    {{-- ============ Filters ============ --}}
    <div class="card p-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
                <x-input wire:model.live.debounce.400ms="search" type="search" icon="search" placeholder="جستجوی نام، ایمیل یا متن دیدگاه…" />
            </div>
            <div>
                <x-select wire:model.live="statusFilter" :options="\App\Models\Comment::STATUS_LABELS" placeholder="همه وضعیت‌ها" />
            </div>
            <div>
                <x-select wire:model.live="typeFilter" :options="$this->types" placeholder="همه انواع" />
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

                                @if($comment->status === \App\Models\Comment::STATUS_APPROVED)
                                    <x-badge variant="success" icon="check-circle">تأیید شده</x-badge>
                                @elseif($comment->status === \App\Models\Comment::STATUS_PENDING)
                                    <x-badge variant="warning" icon="clock">در انتظار</x-badge>
                                @else
                                    <x-badge variant="danger" icon="x-circle">رد شده</x-badge>
                                @endif

                                @php($commentable = $comment->commentable)
                                @if($commentable)
                                    @if($comment->commentable_type === \App\Models\Comment::TYPE_PRODUCT)
                                        <a href="{{ route('admin.products.show', $commentable) }}" wire:navigate
                                           class="inline-flex items-center gap-1 rounded-lg bg-zinc-100 px-2 py-0.5 text-[11px] font-bold text-zinc-600 transition-colors hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700">
                                            <x-icon name="rocket" class="size-3" />
                                            {{ $commentable->name }}
                                        </a>
                                    @else
                                        <a href="{{ route('admin.packages.show', $commentable) }}" wire:navigate
                                           class="inline-flex items-center gap-1 rounded-lg bg-zinc-100 px-2 py-0.5 text-[11px] font-bold text-zinc-600 transition-colors hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700">
                                            <x-icon name="package" class="size-3" />
                                            {{ $commentable->name }}
                                        </a>
                                    @endif
                                @endif
                            </div>
                            <p class="mt-1.5 whitespace-pre-line text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $comment->body }}</p>

                            {{-- پاسخ مدیر --}}
                            @if($comment->hasReply())
                                <div class="mt-2 flex items-start gap-2 rounded-xl bg-brand-50 p-3 ring-1 ring-brand-100 dark:bg-brand-500/10 dark:ring-brand-400/20" wire:key="cmreply-{{ $comment->id }}">
                                    <x-icon name="corner-up-left" class="mt-0.5 size-4 shrink-0 text-brand-600 dark:text-brand-400" />
                                    <div class="min-w-0">
                                        <p class="text-[11px] font-black text-brand-700 dark:text-brand-300">پاسخ شما @if($comment->replied_at)<span class="font-normal text-brand-600/70 dark:text-brand-400/70">· {{ verta_date($comment->replied_at) }}</span>@endif</p>
                                        <p class="mt-1 whitespace-pre-line text-xs leading-6 text-brand-800/90 dark:text-brand-200/80">{{ $comment->reply }}</p>
                                    </div>
                                </div>
                            @endif

                            <p class="mt-1.5 text-xs text-zinc-400">{{ verta_date($comment->created_at) }}@if($comment->email) · {{ $comment->email }}@endif</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-1">
                        <button wire:click="replyTo({{ $comment->id }})"
                                class="rounded-lg p-2 transition-colors {{ $comment->hasReply() ? 'text-brand-600 dark:text-brand-400' : 'text-zinc-500' }} hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10"
                                title="{{ $comment->hasReply() ? 'ویرایش پاسخ' : 'پاسخ مدیر' }}" aria-label="پاسخ مدیر به دیدگاه">
                            <x-icon name="corner-up-left" class="size-4" />
                        </button>
                        @if(!$comment->isApproved())
                            <button wire:click="approve({{ $comment->id }})"
                                    class="rounded-lg p-2 text-zinc-500 transition-colors hover:bg-emerald-50 hover:text-emerald-600 dark:hover:bg-emerald-500/10"
                                    title="تأیید" aria-label="تأیید دیدگاه">
                                <x-icon name="check" class="size-4" />
                            </button>
                        @endif
                        @if($comment->status !== \App\Models\Comment::STATUS_REJECTED)
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

    {{-- ============ Reply Modal ============ --}}
    <x-modal wire:model="showReplyModal" title="پاسخ مدیر">
        @php($replying = $replyingId ? \App\Models\Comment::find($replyingId) : null)
        @if($replying)
            <div class="mb-4 rounded-xl bg-zinc-50 p-3.5 ring-1 ring-zinc-200/70 dark:bg-zinc-800/60 dark:ring-zinc-700/50">
                <p class="flex flex-wrap items-center gap-2 text-xs font-bold text-zinc-700 dark:text-zinc-200">
                    {{ $replying->name }}
                    <span class="text-zinc-400">{{ verta_date($replying->created_at) }}</span>
                </p>
                <p class="mt-1.5 line-clamp-3 whitespace-pre-line text-xs leading-6 text-zinc-500 dark:text-zinc-400">{{ $replying->body }}</p>
            </div>
        @endif

        <form wire:submit="saveReply" class="space-y-4">
            <x-field label="متن پاسخ" for="replyText" required hint="این پاسخ زیر دیدگاهِ تأییدشده در فروشگاه نمایش داده می‌شود">
                <x-textarea id="replyText" wire:model="replyText" rows="4" placeholder="پاسخ خود را بنویسید…" />
                @error('replyText') <p class="mt-1 text-xs font-bold text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
            </x-field>

            <div class="flex items-center justify-between gap-2 border-t border-zinc-100 pt-4 dark:border-zinc-700/50">
                @if($replying?->hasReply())
                    <x-btn variant="danger-soft" type="button" icon="trash" wire:click="removeReply({{ $replying->id }})" wire:confirm="حذف پاسخ مدیر؟">حذف پاسخ</x-btn>
                @endif
                <div class="flex items-center gap-2">
                    <x-btn variant="secondary" type="button" wire:click="$set('showReplyModal', false)">انصراف</x-btn>
                    <x-btn icon="send" type="submit" :loading="true">ذخیره پاسخ</x-btn>
                </div>
            </div>
        </form>
    </x-modal>
</div>
