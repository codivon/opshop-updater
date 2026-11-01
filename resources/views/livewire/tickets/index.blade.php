<div class="space-y-6">

    {{-- ============ Header ============ --}}
    <div>
        <h1 class="flex items-center gap-2 text-xl font-black text-zinc-900 dark:text-zinc-50">
            <x-icon name="message-circle" class="size-5 text-brand-600" />
            تیکت‌های پشتیبانی
        </h1>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
            گفتگوی پشتیبانی مشتریان — فقط مشتریِ دارای اشتراک فعال می‌تواند تیکت ثبت کند.
        </p>
    </div>

    {{-- ============ Stats ============ --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="card p-4">
            <p class="text-xs font-bold text-zinc-500 dark:text-zinc-400">کل تیکت‌ها</p>
            <p class="mt-1 text-xl font-black tabular-nums text-zinc-900 dark:text-zinc-50">{{ fa_num($this->stats['total']) }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs font-bold text-zinc-500 dark:text-zinc-400">در انتظار پاسخ</p>
            <p class="mt-1 text-xl font-black tabular-nums text-amber-600 dark:text-amber-400">{{ fa_num($this->stats['open']) }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs font-bold text-zinc-500 dark:text-zinc-400">پاسخ داده شده</p>
            <p class="mt-1 text-xl font-black tabular-nums text-emerald-600 dark:text-emerald-400">{{ fa_num($this->stats['answered']) }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs font-bold text-zinc-500 dark:text-zinc-400">بسته‌شده</p>
            <p class="mt-1 text-xl font-black tabular-nums text-zinc-400">{{ fa_num($this->stats['closed']) }}</p>
        </div>
    </div>

    {{-- ============ Filters ============ --}}
    <div class="card p-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
                <x-input wire:model.live.debounce.400ms="search" type="search" icon="search" placeholder="جستجوی موضوع، نام مشتری، کد آپدیت یا متن پیام…" />
            </div>
            <div>
                <x-select wire:model.live="statusFilter" :options="\App\Models\Ticket::STATUS_LABELS" placeholder="همه وضعیت‌ها" />
            </div>
        </div>
    </div>

    {{-- ============ List ============ --}}
    <div class="space-y-3">
        @forelse($this->records as $ticket)
            <div wire:key="ticket-{{ $ticket->id }}"
                 class="card p-4 {{ $ticket->isOpen() ? 'ring-2 ring-amber-200 dark:ring-amber-400/25' : '' }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <button type="button" wire:click="view({{ $ticket->id }})" class="flex min-w-0 flex-1 items-start gap-3 text-start">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-xl {{ $ticket->isClosed() ? 'bg-zinc-100 text-zinc-400 dark:bg-zinc-800' : ($ticket->isAnswered() ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400' : 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400') }}">
                            <x-icon name="{{ $ticket->isClosed() ? 'check-circle' : ($ticket->isAnswered() ? 'corner-up-left' : 'clock') }}" class="size-4.5" />
                        </span>
                        <span class="min-w-0">
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-black text-zinc-900 dark:text-zinc-50">{{ $ticket->subject }}</span>
                                @if($ticket->isOpen())
                                    <x-badge variant="warning" icon="clock">در انتظار پاسخ</x-badge>
                                @elseif($ticket->isAnswered())
                                    <x-badge variant="success" icon="corner-up-left">پاسخ داده شد</x-badge>
                                @else
                                    <x-badge variant="neutral" icon="archive">بسته‌شده</x-badge>
                                @endif
                            </span>
                            <span class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                                <span class="inline-flex items-center gap-1"><x-icon name="user" class="size-3.5" /> {{ $ticket->customer?->name ?? '—' }}</span>
                                <span class="inline-flex items-center gap-1 font-mono" dir="ltr">{{ $ticket->customer?->update_code }}</span>
                                <span class="inline-flex items-center gap-1"><x-icon name="calendar" class="size-3.5" /> {{ verta_date($ticket->created_at) }}</span>
                                <span class="inline-flex items-center gap-1"><x-icon name="message-circle" class="size-3.5" /> {{ fa_num($ticket->replies_count) }} پیام</span>
                            </span>
                        </span>
                    </button>

                    <div class="flex items-center gap-1">
                        <button wire:click="view({{ $ticket->id }})"
                                class="rounded-lg p-2 text-zinc-500 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10"
                                title="مشاهده گفتگو" aria-label="مشاهده گفتگو">
                            <x-icon name="eye" class="size-4" />
                        </button>
                        @if(!$ticket->isClosed())
                            <button wire:click="setStatus({{ $ticket->id }}, 'closed')"
                                    wire:confirm="این تیکت بسته شود؟"
                                    class="rounded-lg p-2 text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800"
                                    title="بستن تیکت" aria-label="بستن تیکت">
                                <x-icon name="archive" class="size-4" />
                            </button>
                        @else
                            <button wire:click="setStatus({{ $ticket->id }}, 'open')"
                                    class="rounded-lg p-2 text-zinc-500 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10"
                                    title="بازکردن دوباره" aria-label="بازکردن دوباره">
                                <x-icon name="refresh-cw" class="size-4" />
                            </button>
                        @endif
                        <button wire:click="delete({{ $ticket->id }})"
                                wire:confirm="حذف تیکت و همهٔ پیام‌های آن؟ این عمل قابل بازگشت نیست."
                                class="rounded-lg p-2 text-zinc-500 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10"
                                title="حذف" aria-label="حذف تیکت">
                            <x-icon name="trash" class="size-4" />
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="card">
                <x-empty icon="message-circle" title="تیکتی یافت نشد"
                         description="هنوز تیکتی ثبت نشده یا با این فیلترها چیزی پیدا نشد." />
            </div>
        @endforelse
    </div>

    <div>{{ $this->records->links() }}</div>

    {{-- ============ Conversation Modal ============ --}}
    <x-modal wire:model="showViewModal" title="گفتگوی تیکت" size="lg">
        @php($t = $this->viewing)
        @if($t)
            <div class="space-y-4" data-scroll-preserve="ticket-conversation">
                {{-- سربرگ تیکت + مشتری --}}
                <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/70 dark:bg-zinc-800/60 dark:ring-zinc-700/50">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-sm font-black text-zinc-900 dark:text-zinc-50">{{ $t->subject }}</p>
                        @if($t->isOpen())
                            <x-badge variant="warning" icon="clock">در انتظار پاسخ</x-badge>
                        @elseif($t->isAnswered())
                            <x-badge variant="success" icon="corner-up-left">پاسخ داده شد</x-badge>
                        @else
                            <x-badge variant="neutral" icon="archive">بسته‌شده</x-badge>
                        @endif
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                        <span class="inline-flex items-center gap-1.5">
                            <x-icon name="user" class="size-3.5 shrink-0 text-brand-600 dark:text-brand-400" />
                            {{ $t->customer?->name ?? '—' }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 font-mono" dir="ltr">
                            <x-icon name="key" class="size-3.5 shrink-0 text-brand-600 dark:text-brand-400" />
                            {{ $t->customer?->update_code }}
                        </span>
                        @if($t->customer?->phone)
                            <span class="inline-flex items-center gap-1.5">
                                <x-icon name="phone" class="size-3.5 shrink-0 text-brand-600 dark:text-brand-400" />
                                <span dir="ltr">{{ $t->customer->phone }}</span>
                            </span>
                        @endif
                        <span class="inline-flex items-center gap-1.5">
                            <x-icon name="calendar" class="size-3.5 shrink-0" />
                            {{ verta_date($t->created_at) }}
                        </span>
                    </div>
                </div>

                {{-- گفتگو --}}
                <div class="space-y-3">
                    @foreach($t->replies as $reply)
                        <div wire:key="adm-reply-{{ $reply->id }}"
                             class="flex flex-col gap-1.5 rounded-2xl p-4 ring-1 {{ $reply->isFromAdmin()
                                 ? 'bg-brand-50/80 ring-brand-100 dark:bg-brand-500/10 dark:ring-brand-400/20'
                                 : 'bg-white ring-zinc-200/70 dark:bg-zinc-900 dark:ring-zinc-700/60' }}">
                            <p class="flex items-center gap-2 text-xs font-black {{ $reply->isFromAdmin() ? 'text-brand-700 dark:text-brand-400' : 'text-zinc-600 dark:text-zinc-300' }}">
                                <x-icon name="{{ $reply->isFromAdmin() ? 'badge-check' : 'user' }}" class="size-3.5" />
                                {{ $reply->isFromAdmin() ? 'پشتیبانی (شما)' : ($t->customer?->name ?? 'مشتری') }}
                                <span class="font-medium text-zinc-400">{{ verta_date($reply->created_at) }}</span>
                            </p>
                            <p class="whitespace-pre-line text-sm leading-7 text-zinc-700 dark:text-zinc-200">{{ $reply->body }}</p>
                        </div>
                    @endforeach
                </div>

                {{-- فرم پاسخ مدیر --}}
                @if(!$t->isClosed())
                    <form wire:submit="reply" class="space-y-2 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                        <textarea wire:model="replyText" rows="3" placeholder="پاسخ پشتیبانی را بنویسید… — پس از ارسال، وضعیت تیکت «پاسخ داده شد» می‌شود."
                                  class="input {{ $errors->has('replyText') ? 'input-error' : '' }}"></textarea>
                        @error('replyText') <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-btn type="submit" icon="send" :loading="true">ارسال پاسخ</x-btn>
                            </div>
                            <x-btn type="button" variant="secondary" size="sm" icon="archive" wire:click="setStatus({{ $t->id }}, 'closed')">بستن تیکت</x-btn>
                        </div>
                    </form>
                @else
                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-zinc-100 p-3 text-xs text-zinc-500 dark:bg-zinc-800/60 dark:text-zinc-400">
                        <span>این تیکت بسته شده است — پاسخدادن ممکن نیست.</span>
                        <x-btn variant="secondary" size="sm" icon="refresh-cw" wire:click="setStatus({{ $t->id }}, 'open')">بازکردن دوباره</x-btn>
                    </div>
                @endif
            </div>
        @endif

        <x-slot:footer>
            <x-btn variant="secondary" wire:click="$set('showViewModal', false)">بستن</x-btn>
        </x-slot:footer>
    </x-modal>
</div>
