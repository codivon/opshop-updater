<div class="space-y-6" id="comments">

    {{-- ============ Header ============ --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="flex items-center gap-2 text-lg font-black text-zinc-900 dark:text-zinc-50">
            <x-icon name="message-square" class="size-5 text-brand-600" />
            دیدگاه‌ها
            @if($this->approvedCount)
                <span class="rounded-full bg-zinc-200/70 px-2.5 py-0.5 text-xs tabular-nums text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">{{ fa_num($this->approvedCount) }}</span>
            @endif
        </h2>

        @if($this->averageRating)
            <div class="flex items-center gap-2 rounded-xl bg-amber-50 px-3 py-1.5 ring-1 ring-amber-200/70 dark:bg-amber-500/10 dark:ring-amber-500/20">
                <x-icon name="star" class="size-4 fill-amber-400 text-amber-500" />
                <span class="text-sm font-black tabular-nums text-amber-700 dark:text-amber-300">{{ fa_num($this->averageRating) }}</span>
                <span class="text-xs text-amber-600/80 dark:text-amber-400/70">از ۵</span>
            </div>
        @endif
    </div>

    {{-- ============ List ============ --}}
    @if($this->approved->count())
        <div class="space-y-4">
            @foreach($this->approved as $comment)
                <div wire:key="shopcm-{{ $comment->id }}" class="card p-5">
                    <div class="flex items-start gap-4">
                        <x-avatar :name="$comment->name" />
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-bold text-zinc-900 dark:text-zinc-50">{{ $comment->name }}</span>

                                @if($comment->rating)
                                    <span class="inline-flex items-center gap-0.5 text-amber-500" aria-label="امتیاز {{ fa_num($comment->rating) }} از ۵">
                                        @for($i = 1; $i <= 5; $i++)
                                            <x-icon name="star" class="{{ $i <= $comment->rating ? 'size-3.5 fill-amber-400' : 'size-3.5 text-zinc-300 dark:text-zinc-600' }}" />
                                        @endfor
                                    </span>
                                @endif

                                <span class="text-xs text-zinc-400">{{ verta_date($comment->created_at) }}</span>
                            </div>
                            <p class="mt-2 whitespace-pre-line text-sm leading-7 text-zinc-600 dark:text-zinc-300">{{ $comment->body }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if($this->approved->hasPages())
            <div>{{ $this->approved->links() }}</div>
        @endif
    @else
        <div class="card">
            <x-empty icon="message-square" title="هنوز دیدگاهی ثبت نشده" description="اولین نفر باشید؛ دیدگاه خود را از فرم پایین ثبت کنید." />
        </div>
    @endif

    {{-- ============ Form ============ --}}
    <div class="card p-5 sm:p-6">
        <h3 class="text-base font-black text-zinc-900 dark:text-zinc-50">ثبت دیدگاه جدید</h3>
        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">دیدگاه شما پس از بررسی و تأیید مدیر منتشر می‌شود.</p>

        <form wire:submit="submit" class="mt-5 space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-field label="نام شما" for="cm-name" required>
                    <x-input id="cm-name" wire:model="name" placeholder="نام و نام خانوادگی" />
                </x-field>
                <x-field label="ایمیل (اختیاری)" for="cm-email">
                    <x-input id="cm-email" type="email" wire:model="email" placeholder="you@example.com" dir="ltr" class="text-start" />
                </x-field>
            </div>

            {{-- rating stars --}}
            <div>
                <span class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">امتیاز شما</span>
                <div class="flex items-center gap-1">
                    @for($i = 1; $i <= 5; $i++)
                        <button type="button" wire:click="setRating({{ $i }})"
                                class="cursor-pointer rounded-lg p-1 transition-transform hover:scale-110"
                                aria-label="{{ $i }} ستاره">
                            <x-icon name="star"
                                    class="{{ $i <= $rating ? 'size-6 fill-amber-400 text-amber-500' : 'size-6 text-zinc-300 transition-colors hover:text-amber-400 dark:text-zinc-600' }}" />
                        </button>
                    @endfor
                    @if($rating)
                        <span class="ms-2 text-xs font-bold text-zinc-500">{{ fa_num($rating) }} از ۵</span>
                    @endif
                </div>
            </div>

            <x-field label="متن دیدگاه" for="cm-body" required>
                <x-textarea id="cm-body" wire:model="body" rows="4" placeholder="تجربه‌تان از این {{ $label === 'محصول' ? 'پروژه' : 'پکیج' }} را بنویسید…" />
            </x-field>

            <div class="flex justify-end">
                <x-btn type="submit" icon="send" :loading="true">ارسال دیدگاه</x-btn>
            </div>
        </form>
    </div>
</div>
