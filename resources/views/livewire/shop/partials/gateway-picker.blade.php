{{-- ==================================================================
     انتخابگر درگاه پرداخت — کارت‌های رادیویی با لوگو و عنوان
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
         class="grid {{ $compact ? 'grid-cols-2 gap-2 min-[420px]:grid-cols-3' : 'grid-cols-2 gap-3 sm:grid-cols-3' }}">
        @foreach($gateways as $gateway)
            @php($logo = gateway_logo_url($gateway->key, $gateway->logo))
            @php($checked = (string) $gateway->key === (string) $gatewayKey)
            <label for="{{ $pickerId }}-{{ $gateway->key }}" wire:key="{{ $pickerId }}-{{ $gateway->key }}"
                   class="group relative flex cursor-pointer flex-col items-center {{ $compact ? 'gap-1.5 rounded-xl p-2 text-center transition-all duration-150 hover:-translate-y-0.5 hover:shadow-sm' : 'gap-2 rounded-2xl p-3 text-center transition-all duration-150 hover:-translate-y-0.5 hover:shadow-md' }}
                          {{ $checked
                              ? 'border-brand-500 ring-2 ring-brand-500/30 dark:border-brand-400 dark:ring-brand-400/20'
                              : 'border-zinc-200 hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600 dark:bg-zinc-800' }}">
                <input type="radio" id="{{ $pickerId }}-{{ $gateway->key }}"
                       wire:model.live="gatewayKey" value="{{ $gateway->key }}"
                       class="sr-only" role="radio" aria-checked="{{ $checked ? 'true' : 'false' }}" />

                {{-- لوگو --}}
                <span class="flex {{ $compact ? 'h-10 w-full' : 'h-12 w-[120px]' }} items-center justify-center overflow-hidden rounded-xl bg-zinc-50 p-1 ring-1 ring-zinc-200/70 dark:bg-zinc-900/60 dark:ring-zinc-700">
                    @if($logo)
                        <img src="{{ $logo }}" alt="{{ $gateway->name }}" loading="lazy"
                             class="max-h-full max-w-full object-contain" />
                    @else
                        <x-icon name="credit-card" class="size-6 text-zinc-300 dark:text-zinc-600" />
                    @endif
                </span>

                {{-- نام --}}
                <span class="flex items-center gap-1 {{ $compact ? 'text-[11px]' : 'text-xs' }} font-bold leading-4 text-zinc-700 dark:text-zinc-200">
                    <span class="truncate">{{ $gateway->name }}</span>
                    @if($gateway->key === 'local')
                        <span class="shrink-0 rounded-md bg-amber-100 px-1.5 py-0.5 text-[9px] font-black text-amber-700 dark:bg-amber-500/15 dark:text-amber-400">تست</span>
                    @endif
                </span>

                {{-- تیک انتخاب --}}
                <span class="absolute start-2 top-2 flex size-4 items-center justify-center rounded-full transition-opacity
                             {{ $checked ? 'bg-brand-600 opacity-100 dark:bg-brand-500' : 'bg-zinc-200 opacity-0 group-hover:opacity-40 dark:bg-zinc-700' }}">
                    <x-icon name="check" class="size-3 text-white" />
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
