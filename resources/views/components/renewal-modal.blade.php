{{--
    مودال مدرن «محدودیت خرید مجدد» — هشدار آیکون‌دار و زیبا هنگام خرید تکراری پکیج/طرح.
    داده از خروجی RenewalService در کامپوننت با trait WithRenewalModal می‌آید:

        <x-renewal-modal />

    پراپرتی‌ها: $showRenewalModal (باز/بسته) + $renewalModalData:
      ['state' => blocked|reserved|unlimited|pending,
       'title' => ..., 'message' => ..., 'days' => ?int,
       'plan' => ?string, 'activate_at' => ?string]
--}}
@php
    $d = $this->renewalModalData ?? [];
    $state = $d['state'] ?? 'blocked';

    // پالت هر وضعیت — رنگ + آیکون
    $themes = [
        'blocked' => [
            'icon' => 'lock', 'variant' => 'warning',
            'wrap' => 'bg-amber-50 ring-8 ring-amber-100/70 dark:bg-amber-500/10 dark:ring-amber-500/15',
            'inner' => 'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400',
            'title' => 'text-amber-800 dark:text-amber-300',
            'chip' => 'bg-amber-100/90 text-amber-800 ring-amber-300/60 dark:bg-amber-500/15 dark:text-amber-200 dark:ring-amber-400/25',
        ],
        'reserved' => [
            'icon' => 'clock', 'variant' => 'primary',
            'wrap' => 'bg-violet-50 ring-8 ring-violet-100/70 dark:bg-violet-500/10 dark:ring-violet-500/15',
            'inner' => 'bg-violet-100 text-violet-600 dark:bg-violet-500/20 dark:text-violet-400',
            'title' => 'text-violet-800 dark:text-violet-300',
            'chip' => 'bg-violet-100/90 text-violet-800 ring-violet-300/60 dark:bg-violet-500/15 dark:text-violet-200 dark:ring-violet-400/25',
        ],
        'unlimited' => [
            'icon' => 'badge-check', 'variant' => 'info',
            'wrap' => 'bg-teal-50 ring-8 ring-teal-100/70 dark:bg-teal-500/10 dark:ring-teal-500/15',
            'inner' => 'bg-teal-100 text-teal-600 dark:bg-teal-500/20 dark:text-teal-400',
            'title' => 'text-teal-800 dark:text-teal-300',
            'chip' => 'bg-teal-100/90 text-teal-800 ring-teal-300/60 dark:bg-teal-500/15 dark:text-teal-200 dark:ring-teal-400/25',
        ],
        'pending' => [
            'icon' => 'hourglass', 'variant' => 'info',
            'wrap' => 'bg-sky-50 ring-8 ring-sky-100/70 dark:bg-sky-500/10 dark:ring-sky-500/15',
            'inner' => 'bg-sky-100 text-sky-600 dark:bg-sky-500/20 dark:text-sky-400',
            'title' => 'text-sky-800 dark:text-sky-300',
            'chip' => 'bg-sky-100/90 text-sky-800 ring-sky-300/60 dark:bg-sky-500/15 dark:text-sky-200 dark:ring-sky-400/25',
        ],
    ];
    $t = $themes[$state] ?? $themes['blocked'];
@endphp

<x-modal wire:model="showRenewalModal" size="sm">
    <div class="px-2 pb-2 text-center">
        {{-- آیکون بزرگ با هالهٔ رنگی --}}
        <div class="mx-auto flex size-16 items-center justify-center rounded-full {{ $t['wrap'] }}">
            <span class="flex size-11 items-center justify-center rounded-full {{ $t['inner'] }}">
                <x-icon name="{{ $t['icon'] }}" class="size-6" />
            </span>
        </div>

        <h3 class="mt-5 text-base font-black {{ $t['title'] }} sm:text-lg">
            {{ $d['title'] ?? 'امکان خرید مجدد فعلاً وجود ندارد' }}
        </h3>

        {{-- چیپ‌های اطلاعاتی: طرح / روزهای باقی‌مانده / تاریخ فعال‌سازی خودکار --}}
        @if(! empty($d))
            <div class="mt-4 flex flex-wrap items-center justify-center gap-1.5">
                @if(! empty($d['plan']))
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-bold ring-1 ring-inset {{ $t['chip'] }}">
                        <x-icon name="tag" class="size-3" />
                        {{ $d['plan'] }}
                    </span>
                @endif
                @if(($d['days'] ?? null) !== null)
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-bold tabular-nums ring-1 ring-inset {{ $t['chip'] }}">
                        <x-icon name="calendar-clock" class="size-3" />
                        {{ fa_num($d['days']) }} روز اعتبار باقی‌مانده
                    </span>
                @endif
                @if(! empty($d['activate_at']))
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-bold ring-1 ring-inset {{ $t['chip'] }}">
                        <x-icon name="sparkles" class="size-3" />
                        فعال‌سازی خودکار: {{ verta_date($d['activate_at']) }}
                    </span>
                @endif
            </div>
        @endif

        {{-- پیام اصلی --}}
        <p class="mx-auto mt-4 max-w-sm text-sm leading-7 text-zinc-600 dark:text-zinc-300">
            {{ $d['message'] ?? '' }}
        </p>

        @if($state === 'blocked')
            {{-- یادآوری پنجرهٔ ۵ روزه --}}
            <p class="mt-4 flex items-center justify-center gap-1.5 text-[11px] leading-5 text-zinc-400">
                <x-icon name="info" class="size-3.5 shrink-0" />
                از ۵ روز مانده به پایان اعتبار می‌توانید خرید بعدی را رزرو کنید تا پس از پایان دورهٔ فعلی خودکار فعال شود.
            </p>
        @elseif($state === 'reserved')
            <p class="mt-4 flex items-center justify-center gap-1.5 text-[11px] leading-5 text-zinc-400">
                <x-icon name="sparkles" class="size-3.5 shrink-0 text-violet-400" />
                پس از پایان دورهٔ فعلی، خرید رزروشدهٔ شما به‌صورت خودکار فعال و پیامک اطلاع‌رسانی ارسال می‌شود.
            </p>
        @endif
    </div>

    <x-slot:footer>
        <div class="flex flex-col gap-2 sm:flex-row-reverse">
            <x-btn variant="primary" icon="check" class="w-full sm:w-auto" wire:click="closeRenewalModal">
                متوجه شدم
            </x-btn>
            <x-btn variant="secondary" icon="crown" href="{{ route('shop.subscriptions') }}" class="w-full sm:w-auto">
                مشاهده اشتراک‌های من
            </x-btn>
        </div>
    </x-slot:footer>
</x-modal>
