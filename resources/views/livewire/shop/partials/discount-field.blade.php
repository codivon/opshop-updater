{{--
    فیلد کد تخفیف (کوپن) — مشترک بین هر سه جریان خرید:
      - product-show   (خرید مهمان محصول)
      - package-show   (خرید پکیج با کد آپدیت)
      - subscriptions-index (خرید طرح اشتراک)

    قرارداد کامپوننت والد:
      - public string $discountCode  (ورودی کاربر)
      - public string $appliedCode   (کد اعمال‌شده)
      - #[Computed] appliedDiscount(): ?array  → resolve() سرویس تخفیف برای وضعیت فعلی
      - actions: applyDiscount() / removeDiscount()

    props:
      - baseAmount: مبلغ مبنا (۰ = نمایش داده نشود؛ مثلاً پکیج رایگان)
--}}
@if($baseAmount > 0)
    @if($this->appliedDiscount)
        {{-- ==== کد اعمال‌شده ==== --}}
        <div class="flex items-center justify-between gap-2 rounded-xl bg-emerald-50 px-3.5 py-3 ring-1 ring-emerald-600/15 dark:bg-emerald-500/10 dark:ring-emerald-400/20"
             wire:key="discount-applied">
            <div class="flex min-w-0 items-center gap-2.5">
                <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400">
                    <x-icon name="ticket-percent" class="size-4" />
                </span>
                <div class="min-w-0 text-xs leading-5">
                    <p class="font-black text-emerald-800 dark:text-emerald-300">
                        کد تخفیف: <span class="font-mono tracking-wider" dir="ltr">{{ $this->appliedDiscount['code']->code }}</span>
                    </p>
                    <p class="mt-0.5 text-emerald-700/80 dark:text-emerald-400/80">
                        {{ $this->appliedDiscount['code']->valueLabel() }}
                        <span class="mx-1">·</span>
                        {{ money($this->appliedDiscount['discount']) }} کمتر
                    </p>
                </div>
            </div>
            <button type="button" wire:click="removeDiscount" wire:loading.attr="disabled" wire:target="removeDiscount"
                    class="shrink-0 rounded-lg p-1.5 text-emerald-600 transition-colors hover:bg-rose-100 hover:text-rose-600 dark:text-emerald-400 dark:hover:bg-rose-500/15"
                    title="حذف کد تخفیف" aria-label="حذف کد تخفیف">
                <x-icon name="x" class="size-4" />
            </button>
        </div>
    @else
        {{-- ==== ورود کد ==== --}}
        <div wire:key="discount-input">
            <label for="discountCode" class="mb-1.5 flex items-center gap-1.5 text-xs font-bold text-zinc-700 dark:text-zinc-200">
                <x-icon name="ticket-percent" class="size-3.5 text-brand-600 dark:text-brand-400" />
                کد تخفیف <span class="font-normal text-zinc-400">(اختیاری)</span>
            </label>
            <div class="flex items-stretch gap-2">
                <input id="discountCode" type="text" wire:model="discountCode" dir="ltr"
                       placeholder="مثلاً WELCOME10" autocomplete="off" spellcheck="false"
                       class="input flex-1 font-mono uppercase placeholder:normal-case" />
                <x-btn variant="secondary" wire:click="applyDiscount" :loading="true" class="shrink-0">
                    اعمال
                </x-btn>
            </div>
            @error('discountCode') <p class="mt-1 text-xs font-bold text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
        </div>
    @endif
@endif
