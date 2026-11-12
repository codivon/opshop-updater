{{-- ==================================================================
     انتخابگر درگاه پرداخت — فهرست عمودی ردیفی (مدرن)
     استفاده (همراه wire:model.live="gatewayKey"):
        @include('livewire.shop.partials.gateway-picker', [
            'gateways' => $this->gateways,
        ])
     حالت فشرده برای ستون‌های باریک (مثل کارت خلاصه سفارش تسویه):
        @include('livewire.shop.partials.gateway-picker', [
            'gateways' => $this->gateways,
            'compact'  => true,
        ])
     متغیر $gatewayKey باید در کامپوننت تعریف شده باشد.
================================================================== --}}
@php($pickerId = 'gwp-' . uniqid())
@php($compact = $compact ?? false)
<div>
    <p class="mb-2 block text-sm font-bold text-zinc-800 dark:text-zinc-100">
        درگاه پرداخت را انتخاب کنید
    </p>

    <div role="radiogroup" aria-label="انتخاب درگاه پرداخت"
         class="{{ $compact ? 'space-y-1.5' : 'space-y-2' }}">
        @foreach($gateways as $gateway)
            @php($logo = gateway_logo_url($gateway->key, $gateway->logo))
            @php($checked = (string) $gateway->key === (string) $gatewayKey)
            <label for="{{ $pickerId }}-{{ $gateway->key }}" wire:key="{{ $pickerId }}-{{ $gateway->key }}"
                   class="group relative flex cursor-pointer items-center {{ $compact ? 'gap-2.5 rounded-xl p-2' : 'gap-3 rounded-2xl p-3' }} transition-all duration-150
                          {{ $checked
                              ? 'border-brand-500 bg-brand-50/60 ring-2 ring-brand-500/25 dark:border-brand-400 dark:bg-brand-500/10 dark:ring-brand-400/20'
                              : 'border-zinc-200 hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:border-zinc-600 dark:bg-zinc-800 dark:hover:bg-zinc-700/60' }}">
                <input type="radio" id="{{ $pickerId }}-{{ $gateway->key }}"
                       wire:model.live="gatewayKey" value="{{ $gateway->key }}"
                       class="sr-only" role="radio" aria-checked="{{ $checked ? 'true' : 'false' }}" />

                {{-- رادیوی بصری --}}
                <span class="flex size-5 shrink-0 items-center justify-center rounded-full transition-colors
                             {{ $checked ? 'bg-brand-600 dark:bg-brand-500' : 'bg-white ring-2 ring-zinc-300 group-hover:ring-zinc-400 dark:bg-zinc-900 dark:ring-zinc-600' }}">
                    <x-icon name="check" class="{{ $compact ? 'size-3' : 'size-3.5' }} text-white transition-opacity {{ $checked ? 'opacity-100' : 'opacity-0' }}" />
                </span>

                {{-- لوگو --}}
                <span class="flex {{ $compact ? 'h-9 w-[76px]' : 'h-10 w-[96px]' }} shrink-0 items-center justify-center overflow-hidden rounded-lg bg-white p-1 ring-1 ring-zinc-200/70 dark:bg-zinc-900/60 dark:ring-zinc-700">
                    @if($logo)
                        <img src="{{ $logo }}" alt="{{ $gateway->name }}" loading="lazy"
                             class="max-h-full max-w-full object-contain" />
                    @else
                        <x-icon name="credit-card" class="size-5 text-zinc-300 dark:text-zinc-600" />
                    @endif
                </span>

                {{-- نام درگاه --}}
                <span class="flex min-w-0 flex-wrap items-center gap-1.5 {{ $compact ? 'text-xs' : 'text-sm' }} font-bold leading-5 text-zinc-700 dark:text-zinc-200">
                    <span class="truncate">{{ $gateway->name }}</span>
                    @if($gateway->key === 'local')
                        <span class="shrink-0 rounded-md bg-amber-100 px-1.5 py-0.5 text-[9px] font-black text-amber-700 dark:bg-amber-500/15 dark:text-amber-400">تست</span>
                    @endif
                </span>

                {{-- نشان انتخاب در سمت انتهایی --}}
                <span class="ms-auto flex size-6 shrink-0 items-center justify-center rounded-full transition-all duration-150
                             {{ $checked ? 'bg-brand-100 text-brand-700 dark:bg-brand-500/20 dark:text-brand-300' : 'text-transparent group-hover:text-zinc-300 dark:group-hover:text-zinc-600' }}">
                    <x-icon name="check" class="size-4" />
                </span>
            </label>
        @endforeach
    </div>

    @if($gateways->isEmpty())
        <div class="flex items-start gap-3 rounded-xl bg-amber-50 p-4 text-amber-700 ring-1 ring-amber-600/10 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-400/20">
            <x-icon name="alert-triangle" class="mt-0.5 size-5 shrink-0" />
            <p class="text-xs leading-5">درگاه پرداخت فعالی تنظیم نشده است. لطفاً بعداً مراجعه کنید یا با پشتیبانی تماس بگیرید.</p>
        </div>
    @endif
</div>
