<div class="space-y-6">

    {{-- ============ Header ============ --}}
    <div>
        <h1 class="flex items-center gap-2 text-xl font-black text-zinc-900 dark:text-zinc-50">
            <x-icon name="inbox" class="size-5 text-brand-600" />
            پیام‌های تماس
        </h1>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
            درخواست‌های «تماس با ما» صفحهٔ اصلی — قالب اختصاصی، پروژه اختصاصی، استارتاپ اختصاصی و مشاوره.
        </p>
    </div>

    {{-- ============ Filters ============ --}}
    <div class="card p-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
                <x-input wire:model.live.debounce.400ms="search" type="search" icon="search" placeholder="جستجوی نام، موبایل، ایمیل یا متن…" />
            </div>
            <div>
                <x-select wire:model.live="statusFilter" :options="\App\Models\ContactMessage::STATUS_LABELS" placeholder="همه وضعیت‌ها" />
            </div>
            <div>
                <x-select wire:model.live="typeFilter" :options="$this->types" placeholder="همه انواع درخواست" />
            </div>
        </div>
    </div>

    {{-- ============ List ============ --}}
    <div class="space-y-3">
        @forelse($this->records as $message)
            <div wire:key="msg-{{ $message->id }}"
                 class="card p-4 {{ $message->isNew() ? 'ring-2 ring-brand-200 dark:ring-brand-400/30' : '' }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <button type="button" wire:click="view({{ $message->id }})" class="flex min-w-0 flex-1 items-start gap-3 text-start">
                        <span class="relative">
                            <x-avatar :name="$message->name" />
                            @if($message->isNew())
                                <span class="absolute -top-1 -end-1 flex size-3 rounded-full bg-brand-500 ring-2 ring-white dark:ring-zinc-900" title="جدید"></span>
                            @endif
                        </span>
                        <span class="min-w-0">
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-bold text-zinc-900 dark:text-zinc-50">{{ $message->name }}</span>

                                @if($message->isNew())
                                    <x-badge variant="primary" icon="sparkles">جدید</x-badge>
                                @else
                                    <x-badge icon="check">خوانده‌شده</x-badge>
                                @endif

                                <span class="inline-flex items-center gap-1 rounded-lg bg-zinc-100 px-2 py-0.5 text-[11px] font-bold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                    <x-icon name="tag" class="size-3" />
                                    {{ $message->typeLabel() }}
                                </span>
                            </span>
                            <span class="mt-1.5 block line-clamp-2 whitespace-pre-line text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                                {{ $message->message }}
                            </span>
                            <span class="mt-1.5 block text-xs text-zinc-400">
                                {{ verta_date($message->created_at) }}
                                · <span dir="ltr">{{ $message->phone }}</span>
                                @if($message->email) · {{ $message->email }}@endif
                            </span>
                        </span>
                    </button>

                    <div class="flex items-center gap-1">
                        <button wire:click="toggleRead({{ $message->id }})"
                                class="rounded-lg p-2 text-zinc-500 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10"
                                title="{{ $message->isNew() ? 'خوانده‌شده کن' : 'به «جدید» برگردان' }}"
                                aria-label="تغییر وضعیت خواندن">
                            @if($message->isNew())
                                <x-icon name="check-check" class="size-4" />
                            @else
                                <x-icon name="mail" class="size-4" />
                            @endif
                        </button>
                        <button wire:click="delete({{ $message->id }})"
                                wire:confirm="حذف این پیام؟"
                                class="rounded-lg p-2 text-zinc-500 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10"
                                title="حذف" aria-label="حذف پیام">
                            <x-icon name="trash" class="size-4" />
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="card">
                <x-empty icon="inbox" title="پیامی یافت نشد" description="هنوز درخواستی ثبت نشده یا با این فیلترها پیامی پیدا نشد." />
            </div>
        @endforelse
    </div>

    <div>{{ $this->records->links() }}</div>

    {{-- ============ View Modal ============ --}}
    <x-modal wire:model="showViewModal" title="جزئیات درخواست">
        @php($viewing = $viewingId ? \App\Models\ContactMessage::find($viewingId) : null)
        @if($viewing)
            <div class="space-y-4">
                <div class="flex flex-wrap items-center gap-2">
                    <x-badge variant="primary" icon="tag">{{ $viewing->typeLabel() }}</x-badge>
                    @if($viewing->isNew())
                        <x-badge variant="warning" icon="sparkles">جدید</x-badge>
                    @endif
                    <span class="text-xs text-zinc-400">{{ verta_date($viewing->created_at) }}</span>
                </div>

                <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/70 dark:bg-zinc-800/60 dark:ring-zinc-700/50">
                    <p class="flex items-center gap-2 text-sm font-black text-zinc-800 dark:text-zinc-100">
                        <x-avatar :name="$viewing->name" size="sm" />
                        {{ $viewing->name }}
                    </p>
                    <div class="mt-3 grid gap-2 text-xs text-zinc-600 dark:text-zinc-300">
                        <p class="flex items-center gap-1.5">
                            <x-icon name="phone" class="size-3.5 shrink-0 text-brand-600 dark:text-brand-400" />
                            <span dir="ltr">{{ $viewing->phone }}</span>
                        </p>
                        @if($viewing->email)
                            <p class="flex items-center gap-1.5">
                                <x-icon name="mail" class="size-3.5 shrink-0 text-brand-600 dark:text-brand-400" />
                                <span dir="ltr">{{ $viewing->email }}</span>
                            </p>
                        @endif
                        @if($viewing->ip)
                            <p class="flex items-center gap-1.5 text-zinc-400">
                                <x-icon name="globe" class="size-3.5 shrink-0" />
                                <span dir="ltr">{{ $viewing->ip }}</span>
                            </p>
                        @endif
                    </div>
                </div>

                <div>
                    <p class="mb-1.5 text-xs font-black text-zinc-500 dark:text-zinc-400">توضیح درخواست</p>
                    <p class="whitespace-pre-line rounded-xl bg-brand-50/60 p-4 text-sm leading-7 text-zinc-700 ring-1 ring-brand-100 dark:bg-brand-500/10 dark:text-zinc-200 dark:ring-brand-400/20">
                        {{ $viewing->message }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @if($viewing->isNew())
                        <button type="button" wire:click="toggleRead({{ $viewing->id }})">
                            <x-btn variant="secondary" size="sm" icon="check-check">خوانده‌شده کن</x-btn>
                        </button>
                    @endif
                    <a href="tel:{{ $viewing->phone }}">
                        <x-btn size="sm" icon="phone">تماس</x-btn>
                    </a>
                </div>
            </div>
        @endif
    </x-modal>
</div>
