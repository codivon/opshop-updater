<div class="mx-auto w-full max-w-5xl px-4 pt-6 sm:px-6 sm:pt-10">
    {{-- header --}}
    <div class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h1 class="flex items-center gap-2.5 text-xl font-black text-zinc-900 sm:text-2xl dark:text-zinc-50">
                <span class="flex size-10 items-center justify-center rounded-xl bg-brand-500/10 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                    <x-icon name="history" class="size-5.5" />
                </span>
                {{ $title }}
            </h1>
            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                آخرین نسخه‌ها و تغییرات پکیج‌ها، پروژه‌ها و قالب‌ها.
            </p>
        </div>

        {{-- kind filter --}}
        <div class="flex flex-wrap gap-1 rounded-xl bg-zinc-200/60 p-1 dark:bg-zinc-800/60" role="group" aria-label="فیلتر نوع">
            @foreach([
                'all'      => 'همه',
                'package'  => 'پکیج‌ها',
                'product'  => 'پروژه‌ها',
                'template' => 'قالب‌ها',
            ] as $value => $label)
                <button type="button" wire:click="filterKind('{{ $value }}')"
                        @class([
                            'cursor-pointer rounded-lg px-3 py-1.5 text-xs font-bold transition-all',
                            'bg-white text-zinc-900 shadow-sm dark:bg-zinc-900 dark:text-zinc-100' => $kind === $value,
                            'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' => $kind !== $value,
                        ])
                        aria-pressed="{{ $kind === $value ? 'true' : 'false' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- timeline --}}
    <div class="mt-8 space-y-4">
        @forelse($this->items as $item)
            <article class="card p-5 sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2.5">
                        @if($item['url'])
                            <a href="{{ $item['url'] }}" wire:navigate
                               class="text-sm font-black text-zinc-900 transition-colors hover:text-brand-600 dark:text-zinc-50 dark:hover:text-brand-400">
                                {{ $item['label'] }}
                            </a>
                        @else
                            <span class="text-sm font-black text-zinc-900 dark:text-zinc-50">{{ $item['label'] }}</span>
                        @endif

                        <span class="rounded-lg bg-brand-500/10 px-2 py-0.5 font-mono text-xs font-bold text-brand-700 dark:bg-brand-500/15 dark:text-brand-300" dir="ltr">
                            v{{ $item['version'] }}
                        </span>

                        @if($item['type'] === 'major')
                            <span class="rounded-lg bg-rose-500/10 px-2 py-0.5 text-[11px] font-bold text-rose-700 dark:bg-rose-500/15 dark:text-rose-400">نسخهٔ بزرگ</span>
                        @elseif($item['type'] === 'minor')
                            <span class="rounded-lg bg-amber-500/10 px-2 py-0.5 text-[11px] font-bold text-amber-700 dark:bg-amber-500/15 dark:text-amber-400">نسخهٔ متوسط</span>
                        @elseif($item['type'] === 'patch')
                            <span class="rounded-lg bg-emerald-500/10 px-2 py-0.5 text-[11px] font-bold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400">ترمیم</span>
                        @endif

                        <span class="text-[11px] font-medium text-zinc-400">
                            @if($item['kind'] === 'package') پکیج
                            @elseif($item['kind'] === 'product') پروژه
                            @else قالب @endif
                        </span>
                    </div>

                    @if($item['date'])
                        <span class="flex items-center gap-1.5 text-xs text-zinc-400">
                            <x-icon name="calendar" class="size-3.5" />
                            {{ fa_num(verta_date($item['date'], 'Y/m/d')) }}
                        </span>
                    @endif
                </div>

                <div class="mt-4 space-y-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    @if($item['changelog'] !== '')
                        <p class="whitespace-pre-line">{{ $item['changelog'] }}</p>
                    @endif

                    @if($item['added'] !== '')
                        <div class="rounded-xl bg-emerald-500/5 p-3.5 ring-1 ring-emerald-500/15 dark:bg-emerald-500/5 dark:ring-emerald-500/20">
                            <p class="mb-1 flex items-center gap-1.5 text-xs font-black text-emerald-700 dark:text-emerald-400">
                                <x-icon name="plus" class="size-3.5" /> افزوده شد
                            </p>
                            <p class="whitespace-pre-line">{{ $item['added'] }}</p>
                        </div>
                    @endif

                    @if($item['changed'] !== '')
                        <div class="rounded-xl bg-amber-500/5 p-3.5 ring-1 ring-amber-500/15 dark:bg-amber-500/5 dark:ring-amber-500/20">
                            <p class="mb-1 flex items-center gap-1.5 text-xs font-black text-amber-700 dark:text-amber-400">
                                <x-icon name="refresh-cw" class="size-3.5" /> تغییر کرد
                            </p>
                            <p class="whitespace-pre-line">{{ $item['changed'] }}</p>
                        </div>
                    @endif

                    @if($item['fixed'] !== '')
                        <div class="rounded-xl bg-sky-500/5 p-3.5 ring-1 ring-sky-500/15 dark:bg-sky-500/5 dark:ring-sky-500/20">
                            <p class="mb-1 flex items-center gap-1.5 text-xs font-black text-sky-700 dark:text-sky-400">
                                <x-icon name="check-circle" class="size-3.5" /> رفع شد
                            </p>
                            <p class="whitespace-pre-line">{{ $item['fixed'] }}</p>
                        </div>
                    @endif

                    @if($item['changelog'] === '' && $item['added'] === '' && $item['changed'] === '' && $item['fixed'] === '')
                        <p class="text-zinc-400">توضیحی برای این نسخه ثبت نشده است.</p>
                    @endif
                </div>
            </article>
        @empty
            <div class="card flex flex-col items-center gap-3 p-10 text-center">
                <span class="flex size-12 items-center justify-center rounded-2xl bg-zinc-100 text-zinc-400 dark:bg-zinc-800">
                    <x-icon name="history" class="size-6" />
                </span>
                <p class="text-sm font-bold text-zinc-700 dark:text-zinc-200">هنوز نسخه‌ای ثبت نشده است</p>
                <p class="max-w-sm text-xs leading-6 text-zinc-400">پس از انتشار نسخه‌های جدید پکیج‌ها و پروژه‌ها، تغییرات آن‌ها به‌صورت خودکار اینجا نمایش داده می‌شود.</p>
            </div>
        @endforelse
    </div>

    {{-- pagination --}}
    @if(method_exists($this->items, 'hasPages') && $this->items->hasPages())
        <div class="mt-6">
            {{ $this->items->onEachSide(1)->links() }}
        </div>
    @endif
</div>
