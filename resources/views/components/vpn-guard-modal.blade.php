{{--
    مودال «فیلترشکن (VPN) شناسایی شد» — هشدار مدرن آیکون‌دار روی صفحه‌های پرداخت.
    گارد IP پرداخت (IranIpGuard) سمت سرور انتقال به درگاه را بسته و این مودال را
    با $showVpnModal باز می‌کند؛ پیام دلایل در $vpnBlockReason می‌آید.

    استفاده در کامپوننت با trait WithPaymentGuard:
        <x-vpn-guard-modal wireModel="showVpnModal" retryMethod="pay" />
--}}
@props([
    'model' => 'showVpnModal',   // پراپرتی Livewire کنترل‌کننده
    'retryMethod' => null,       // متد تلاش دوباره (pay / buy) — خالی = بدون دکمهٔ تلاش
    'reasonProperty' => 'vpnBlockReason',
])

<x-modal wire:model="{{ $model }}" size="sm">
    <div class="px-2 pb-2 text-center">
        {{-- آیکون هشدار --}}
        <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-rose-50 ring-8 ring-rose-100/70 dark:bg-rose-500/10 dark:ring-rose-500/15">
            <span class="flex size-11 items-center justify-center rounded-full bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-400">
                <x-icon name="shield-off" class="size-6" />
            </span>
        </div>

        <h3 class="mt-5 text-base font-black text-zinc-900 dark:text-zinc-50 sm:text-lg">
            فیلترشکن (VPN) شناسایی شد
        </h3>

        <p class="mx-auto mt-3 max-w-sm text-sm leading-7 text-zinc-600 dark:text-zinc-300">
            {{ $this->{$reasonProperty} ?? 'پرداخت‌ها فقط از IP ایران پذیرفته می‌شود؛ لطفاً فیلترشکن خود را خاموش کنید و دوباره تلاش کنید.' }}
        </p>

        {{-- راهنمای گام‌به‌گام --}}
        <ul class="mx-auto mt-5 max-w-sm space-y-2 rounded-xl bg-zinc-50 p-4 text-start ring-1 ring-zinc-200/70 dark:bg-zinc-800/50 dark:ring-zinc-700/60" dir="rtl">
            <li class="flex items-center gap-2.5 text-xs leading-5 font-medium text-zinc-600 dark:text-zinc-300">
                <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-brand-100 text-[11px] font-black text-brand-700 dark:bg-brand-500/15 dark:text-brand-400">۱</span>
                فیلترشکن (VPN) خود را خاموش کنید
            </li>
            <li class="flex items-center gap-2.5 text-xs leading-5 font-medium text-zinc-600 dark:text-zinc-300">
                <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-brand-100 text-[11px] font-black text-brand-700 dark:bg-brand-500/15 dark:text-brand-400">۲</span>
                صفحه را دوباره بارگذاری کنید
            </li>
            <li class="flex items-center gap-2.5 text-xs leading-5 font-medium text-zinc-600 dark:text-zinc-300">
                <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-brand-100 text-[11px] font-black text-brand-700 dark:bg-brand-500/15 dark:text-brand-400">۳</span>
                دوباره روی دکمهٔ پرداخت بزنید
            </li>
        </ul>

        {{-- یادآوری امنیتی --}}
        <p class="mt-4 flex items-center justify-center gap-1.5 text-[11px] leading-5 text-zinc-400">
            <x-icon name="lock" class="size-3.5 shrink-0 text-emerald-500" />
            این محدودیت برای امنیت پرداخت و جلوگیری از رد شدن تراکنش در درگاه بانکی است.
        </p>
    </div>

    <x-slot:footer>
        <div class="flex flex-col gap-2 sm:flex-row-reverse">
            @if($retryMethod)
                <x-btn variant="primary" icon="rotate-cw" class="w-full sm:w-auto" wire:click="{{ $retryMethod }}" :loading="true">
                    تلاش دوباره
                </x-btn>
            @endif
            <x-btn variant="secondary" class="w-full sm:w-auto" wire:click="closeVpnModal">
                متوجه شدم
            </x-btn>
        </div>
    </x-slot:footer>
</x-modal>
