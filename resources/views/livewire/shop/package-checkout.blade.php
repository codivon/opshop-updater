{{--
    صفحه تسویه خریدهای API — مرحله میانی پیش از درگاه.
    نام/تصویر پکیج + طرح انتخابی + کد تخفیف + دکمه پرداخت/انصراف.
    ورود فقط با لینک امضادار (middleware('signed')).
--}}
<div class="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6 sm:py-10">

    @php($payable = $purchase->status === \App\Models\PackagePurchase::STATUS_PENDING)

    {{-- ================================================================ --}}
    {{--  وضعیت‌های پایانی (پرداخت‌شده / لغوشده)                          --}}
    {{-- ================================================================ --}}
    @if($purchase->status === \App\Models\PackagePurchase::STATUS_PAID)
        <section class="mx-auto max-w-2xl">
            <div class="card overflow-hidden p-0">
                <div class="shop-hero-success relative px-6 py-10 text-center">
                    <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-white/15 text-white ring-1 ring-white/25">
                        <x-icon name="check-circle" class="size-8" />
                    </span>
                    <h1 class="mt-4 text-xl font-black text-white sm:text-2xl">این سفارش پرداخت شده است</h1>
                    <p class="mt-2 text-sm leading-7 text-emerald-50/85">لایسنس پکیج «{{ $purchase->package?->name }}» صادر شد و می‌توانید جزئیات آن را مشاهده کنید.</p>
                </div>
                <div class="flex flex-col gap-3 p-5 sm:flex-row sm:justify-center sm:p-6">
                    <a href="{{ route('payment.result', $purchase) }}">
                        <x-btn variant="primary" icon="receipt">مشاهده لایسنس و جزئیات</x-btn>
                    </a>
                    @if($purchase->callback_url)
                        <a href="{{ $purchase->callback_url }}">
                            <x-btn variant="secondary" icon="log-out">بازگشت به فروشگاه</x-btn>
                        </a>
                    @endif
                </div>
            </div>
        </section>
    @elseif($purchase->status === \App\Models\PackagePurchase::STATUS_FAILED)
        <section class="mx-auto max-w-2xl">
            <div class="card overflow-hidden p-0">
                <div class="shop-hero-fail relative px-6 py-10 text-center">
                    <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-white/15 text-white ring-1 ring-white/25">
                        <x-icon name="x-circle" class="size-8" />
                    </span>
                    <h1 class="mt-4 text-xl font-black text-white sm:text-2xl">سفارش لغو شد</h1>
                    <p class="mt-2 text-sm leading-7 text-rose-50/85">{{ $purchase->meta['fail_reason'] ?? 'این سفارش لغو شده و قابل پرداخت نیست؛ برای خرید دوباره از فروشگاه اقدام کنید.' }}</p>
                </div>
                <div class="flex flex-col gap-3 p-5 sm:flex-row sm:justify-center sm:p-6">
                    @if($purchase->callback_url)
                        <a href="{{ $purchase->callback_url }}">
                            <x-btn variant="primary" icon="arrow-right">بازگشت به فروشگاه</x-btn>
                        </a>
                    @else
                        <a href="{{ route('shop.home') }}" wire:navigate>
                            <x-btn variant="primary" icon="home">بازگشت به فروشگاه</x-btn>
                        </a>
                    @endif
                </div>
            </div>
        </section>
    @else
        {{-- ================================================================ --}}
        {{--  تسویه عادی                                                       --}}
        {{-- ================================================================ --}}

        {{-- ---------- نوار مراحل ---------- --}}
        <nav aria-label="مراحل خرید" class="mb-8">
            <ol class="mx-auto flex max-w-xl items-center gap-2 sm:gap-3">
                <li class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400">
                    <span class="flex size-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400">
                        <x-icon name="check" class="size-4" />
                    </span>
                    <span class="hidden text-xs font-bold sm:block">انتخاب پکیج</span>
                </li>
                <li class="h-px flex-1 bg-gradient-to-l from-emerald-300 to-brand-300" aria-hidden="true"></li>
                <li class="flex items-center gap-2">
                    <span class="flex size-8 items-center justify-center rounded-full bg-gradient-to-br from-brand-500 to-brand-700 text-sm font-black text-white shadow-md shadow-pop">۲</span>
                    <span class="text-xs font-black text-brand-700 dark:text-brand-300 sm:text-sm">تأیید و پرداخت</span>
                </li>
                <li class="h-px flex-1 bg-zinc-200 dark:bg-zinc-700" aria-hidden="true"></li>
                <li class="flex items-center gap-2 text-zinc-400">
                    <span class="flex size-8 items-center justify-center rounded-full bg-zinc-100 text-sm font-black text-zinc-500 ring-1 ring-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:ring-zinc-700">۳</span>
                    <span class="hidden text-xs font-bold text-zinc-400 sm:block">فعال‌سازی لایسنس</span>
                </li>
            </ol>
        </nav>

        {{-- ---------- عنوان ---------- --}}
        <header class="mb-6 text-center">
            <h1 class="text-xl font-black text-zinc-900 sm:text-2xl dark:text-zinc-50">تسویه سفارش</h1>
            <p class="mt-1.5 text-sm text-zinc-500 dark:text-zinc-400">جزئیات سفارش خود را بررسی کنید؛ سپس پرداخت یا انصراف را انتخاب کنید.</p>
        </header>

        @if($pageError)
            <div class="mb-5 flex items-start gap-3 rounded-2xl bg-rose-50 p-4 ring-1 ring-rose-600/15 dark:bg-rose-500/10 dark:ring-rose-400/20" role="alert">
                <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-rose-100 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400">
                    <x-icon name="alert-triangle" class="size-4" />
                </span>
                <p class="text-sm leading-6 font-medium text-rose-800 dark:text-rose-200">{{ $pageError }}</p>
            </div>
        @endif

        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-5">

            {{-- ================= ستون اصلی (راست در RTL) ================= --}}
            <div class="space-y-5 lg:col-span-3">

                {{-- ---------- کارت پکیج ---------- --}}
                <section class="card p-5 sm:p-6" aria-label="پکیج انتخابی">
                    <div class="flex items-center gap-4">
                        <div class="size-20 shrink-0 overflow-hidden rounded-2xl ring-1 ring-zinc-200/80 sm:size-24 dark:ring-zinc-700">
                            @if($purchase->package?->thumbnail_url && $purchase->package->thumbnail_url !== asset('images/package-default.png'))
                                <img src="{{ $purchase->package->thumbnail_url }}" alt="تصویر پکیج {{ $purchase->package->name }}" class="size-full object-cover" />
                            @else
                                <div class="shop-thumb flex size-full items-center justify-center">
                                    <x-icon name="package" class="size-8 text-white/85" />
                                </div>
                            @endif
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="mb-1 flex items-center gap-1.5 text-[11px] font-bold tracking-wide text-brand-600 dark:text-brand-400">
                                <x-icon name="shopping-bag" class="size-3.5" />
                                پکیج انتخابی شما
                            </p>
                            <h2 class="truncate text-base font-black text-zinc-900 sm:text-lg dark:text-zinc-50">{{ $purchase->package?->name ?? 'پکیج' }}</h2>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                @if($purchase->version)
                                    <span class="inline-flex items-center gap-1 rounded-lg bg-zinc-100 px-2 py-0.5 font-mono text-[11px] font-bold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300" dir="ltr">
                                        <x-icon name="git-branch" class="size-3" />
                                        v{{ $purchase->version->version }}
                                    </span>
                                @endif
                                @if($purchase->customer?->name)
                                    <span class="inline-flex items-center gap-1 rounded-lg bg-brand-50 px-2 py-0.5 text-[11px] font-bold text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">
                                        <x-icon name="user" class="size-3" />
                                        {{ $purchase->customer->name }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </section>

                {{-- ---------- کارت طرح انتخابی ---------- --}}
                <section class="card p-5 sm:p-6" aria-label="طرح انتخابی">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="flex items-center gap-2 text-sm font-black text-zinc-800 dark:text-zinc-100">
                            <span class="flex size-8 items-center justify-center rounded-xl bg-brand-500/10 text-brand-600 dark:text-brand-400">
                                <x-icon name="layers" class="size-4" />
                            </span>
                            طرح انتخاب‌شده
                        </p>
                        @if($purchase->pricingPlan?->is_one_time)
                            <span class="rounded-lg bg-amber-50 px-2.5 py-1 text-[11px] font-bold text-amber-700 ring-1 ring-amber-600/15 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-400/20">
                                یک‌بار مصرف
                            </span>
                        @endif
                    </div>

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-zinc-50/80 p-4 ring-1 ring-zinc-200/70 dark:bg-zinc-800/60 dark:ring-zinc-700">
                        <div>
                            <p class="text-sm font-black text-zinc-900 dark:text-zinc-50">{{ $purchase->pricingPlan?->name ?? 'طرح' }}</p>
                            @if($purchase->pricingPlan)
                                <p class="mt-1 flex items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                                    <x-icon name="calendar" class="size-3.5" />
                                    اعتبار: {{ fa_num($purchase->pricingPlan->duration_label) }}
                                </p>
                            @endif
                        </div>
                        @if($purchase->pricingPlan)
                            <div class="text-end">
                                @if($purchase->pricingPlan->has_discount)
                                    <p class="text-xs text-zinc-400 line-through">{{ money($purchase->pricingPlan->price, false) }}</p>
                                    <p class="text-sm font-black tabular-nums text-zinc-900 dark:text-zinc-50">{{ money($purchase->pricingPlan->final_price) }}</p>
                                    <span class="mt-1 inline-block rounded-lg bg-emerald-50 px-1.5 py-0.5 text-[10px] font-black text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                                        {{ fa_num($purchase->pricingPlan->discount_percent) }}٪ تخفیف طرح
                                    </span>
                                @else
                                    <p class="text-sm font-black tabular-nums text-zinc-900 dark:text-zinc-50">{{ money($purchase->pricingPlan->final_price) }}</p>
                                @endif
                            </div>
                        @endif
                    </div>

                    @if($purchase->pricingPlan?->description)
                        <p class="mt-3 text-xs leading-6 text-zinc-500 dark:text-zinc-400">{{ $purchase->pricingPlan->description }}</p>
                    @endif
                </section>

                {{-- ---------- کارت کد تخفیف ---------- --}}
                <section class="card p-5 sm:p-6" aria-label="کد تخفیف">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="flex items-center gap-2 text-sm font-black text-zinc-800 dark:text-zinc-100">
                            <span class="flex size-8 items-center justify-center rounded-xl bg-amber-100/70 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400">
                                <x-icon name="ticket-percent" class="size-4" />
                            </span>
                            کد تخفیف
                        </p>
                        @if($appliedCode)
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 px-2.5 py-1 font-mono text-xs font-black text-emerald-700 ring-1 ring-emerald-600/15 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/20" dir="ltr">
                                <x-icon name="badge-check" class="size-3.5" />
                                {{ $appliedCode }}
                            </span>
                        @endif
                    </div>

                    @if($this->paymentStarted)
                        {{-- تراکنش درگاه شروع شده → کد دیگر قابل تغییر نیست --}}
                        <div class="mt-4 flex items-start gap-3 rounded-2xl bg-amber-50 p-4 ring-1 ring-amber-600/15 dark:bg-amber-500/10 dark:ring-amber-400/20">
                            <x-icon name="info" class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                            <p class="text-xs leading-6 font-medium text-amber-800 dark:text-amber-200">
                                تراکنش پرداخت شروع شده است؛ برای اعمال کد تخفیف جدید ابتدا از پرداخت انصراف دهید و سفارش جدیدی ثبت کنید.
                            </p>
                        </div>
                    @else
                        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">اگر کد تخفیف دارید، در کادر زیر وارد کنید:</p>

                        <form wire:submit="applyDiscount" class="mt-3 flex flex-col gap-2.5 sm:flex-row">
                            <div class="relative flex-1">
                                <span class="pointer-events-none absolute inset-y-0 start-3 flex items-center text-zinc-400" aria-hidden="true">
                                    <x-icon name="ticket-percent" class="size-4" />
                                </span>
                                <input
                                    type="text"
                                    wire:model="codeInput"
                                    placeholder="مثلاً OPShop1404"
                                    dir="ltr"
                                    class="w-full rounded-xl border-0 bg-white py-2.5 ps-10 pe-3 text-sm font-bold tracking-wider text-zinc-900 ring-1 ring-zinc-300 transition placeholder:font-medium placeholder:tracking-normal placeholder:text-zinc-400 focus:ring-2 focus:ring-brand-500 dark:bg-zinc-900 dark:text-zinc-100 dark:ring-zinc-700"
                                    autocomplete="off"
                                    aria-label="کد تخفیف"
                                />
                            </div>
                            <x-btn type="submit" variant="soft" icon="check" :loading="true" class="shrink-0">اعمال کد</x-btn>
                        </form>

                        @if($codeMessage)
                            <div class="mt-3 flex items-start justify-between gap-3 rounded-xl p-3 @if($codeMessageType === 'error') bg-rose-50 ring-1 ring-rose-600/15 dark:bg-rose-500/10 dark:ring-rose-400/20 @else bg-emerald-50 ring-1 ring-emerald-600/15 dark:bg-emerald-500/10 dark:ring-emerald-400/20 @endif" role="status">
                                <p class="flex items-start gap-2 text-xs leading-6 font-medium @if($codeMessageType === 'error') text-rose-700 dark:text-rose-300 @else text-emerald-700 dark:text-emerald-300 @endif">
                                    <x-icon name="{{ $codeMessageType === 'error' ? 'alert-triangle' : 'check-circle' }}" class="mt-0.5 size-4 shrink-0" />
                                    {{ $codeMessage }}
                                </p>
                                @if($codeMessageType === 'ok' && $appliedCode)
                                    <button type="button" wire:click="removeDiscount" wire:loading.attr="disabled"
                                            class="shrink-0 rounded-lg p-1.5 text-emerald-600/70 transition-colors hover:bg-emerald-100 hover:text-emerald-800 dark:text-emerald-400/70 dark:hover:bg-emerald-500/15"
                                            title="حذف کد تخفیف" aria-label="حذف کد تخفیف">
                                        <x-icon name="x" class="size-4" />
                                    </button>
                                @endif
                            </div>
                        @endif
                    @endif
                </section>
            </div>

            {{-- ================= ستون خلاصه سفارش (چپ — چسبان) ================= --}}
            <aside class="space-y-4 lg:sticky lg:top-24 lg:col-span-2" aria-label="خلاصه سفارش و پرداخت">
                <section class="card overflow-hidden p-0">
                    <div class="border-b border-zinc-200/80 bg-gradient-to-b from-brand-50/60 to-transparent px-5 py-4 dark:border-zinc-800 dark:from-brand-500/10">
                        <h3 class="flex items-center gap-2 text-sm font-black text-zinc-900 dark:text-zinc-50">
                            <x-icon name="receipt" class="size-4 text-brand-600 dark:text-brand-400" />
                            خلاصه سفارش
                        </h3>
                    </div>

                    <div class="space-y-3 px-5 py-4">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="text-zinc-500 dark:text-zinc-400">{{ $purchase->package?->name ?? 'پکیج' }}</span>
                            <span class="shrink-0 rounded-lg bg-zinc-100 px-2 py-0.5 text-[11px] font-bold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                {{ $purchase->pricingPlan?->name ?? '—' }}
                                @if($purchase->pricingPlan)
                                    · {{ fa_num($purchase->pricingPlan->duration_label) }}
                                @endif
                            </span>
                        </div>

                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="text-zinc-500 dark:text-zinc-400">مبلغ طرح</span>
                            <span class="shrink-0 tabular-nums">
                                @if($purchase->pricingPlan?->has_discount)
                                    <span class="me-1.5 text-xs text-zinc-400 line-through">{{ money($purchase->pricingPlan->price, false) }}</span>
                                    <span class="font-bold text-zinc-900 dark:text-zinc-50">{{ money($purchase->pricingPlan->final_price, false) }}</span>
                                @else
                                    <span class="font-bold text-zinc-900 dark:text-zinc-50">{{ money($this->baseAmount, false) }}</span>
                                @endif
                                <span class="text-[10px] text-zinc-400">تومان</span>
                            </span>
                        </div>

                        @if($codeDiscount > 0)
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
                                    <x-icon name="ticket-percent" class="size-3.5" />
                                    کد تخفیف
                                    <span class="font-mono text-[10px] font-black text-emerald-500/80" dir="ltr">{{ $appliedCode }}</span>
                                </span>
                                <span class="shrink-0 font-bold tabular-nums text-emerald-600 dark:text-emerald-400">− {{ money($codeDiscount, false) }} <span class="text-[10px] text-emerald-500/70">تومان</span></span>
                            </div>
                        @endif

                        <div class="border-t border-dashed border-zinc-200 pt-3 dark:border-zinc-700">
                            <div class="flex items-end justify-between gap-3">
                                <span class="text-sm font-bold text-zinc-700 dark:text-zinc-200">مبلغ قابل پرداخت</span>
                                <span class="text-end">
                                    @if($this->payableAmount <= 0)
                                        <span class="text-lg font-black text-emerald-600 dark:text-emerald-400">رایگان</span>
                                    @else
                                        <span class="text-xl font-black tabular-nums text-brand-700 dark:text-brand-300">{{ money($this->payableAmount, false) }}</span>
                                        <span class="text-[11px] font-bold text-zinc-400">تومان</span>
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2.5 border-t border-zinc-200/80 px-5 py-4 dark:border-zinc-800">
                        @if($this->paymentStarted)
                            {{-- تراکنش در جریان: ادامه پرداخت با همان لینک درگاه --}}
                            <div class="mb-3 flex items-start gap-2.5 rounded-xl bg-amber-50 p-3 ring-1 ring-amber-600/15 dark:bg-amber-500/10 dark:ring-amber-400/20">
                                <x-icon name="clock" class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                                <p class="text-xs leading-6 font-medium text-amber-800 dark:text-amber-200">تراکنش پرداخت در جریان است؛ برای ادامه به درگاه بروید.</p>
                            </div>
                            <x-btn variant="primary" size="lg" icon="credit-card" wire:click="pay" :loading="true" class="w-full">
                                ادامه پرداخت
                            </x-btn>
                        @elseif($this->payableAmount <= 0)
                            <x-btn variant="primary" size="lg" icon="sparkles" wire:click="pay" :loading="true" class="w-full">
                                دریافت و فعال‌سازی
                            </x-btn>
                        @else
                            <x-btn variant="primary" size="lg" icon="lock" wire:click="pay" :loading="true" class="w-full">
                                پرداخت {{ money($this->payableAmount, false) }} تومان
                            </x-btn>
                            <p class="text-center text-[11px] leading-5 text-zinc-400">با زدن دکمه پرداخت، به درگاه بانک منتقل می‌شوید.</p>
                        @endif

                        <x-btn variant="secondary" icon="x" wire:click="cancel" :loading="true" class="w-full">
                            انصراف از خرید
                        </x-btn>
                    </div>
                </section>

                {{-- ---------- تضمین‌ها ---------- --}}
                <section class="card space-y-3 p-5" aria-label="تضمین‌های پرداخت">
                    <div class="flex items-center gap-3">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                            <x-icon name="shield-check" class="size-4.5" />
                        </span>
                        <p class="text-xs leading-6 font-medium text-zinc-600 dark:text-zinc-300">پرداخت امن از طریق درگاه بانکی معتبر</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                            <x-icon name="refresh-cw" class="size-4.5" />
                        </span>
                        <p class="text-xs leading-6 font-medium text-zinc-600 dark:text-zinc-300">پس از پرداخت، به‌صورت خودکار به فروشگاه برمی‌گردید</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                            <x-icon name="badge-check" class="size-4.5" />
                        </span>
                        <p class="text-xs leading-6 font-medium text-zinc-600 dark:text-zinc-300">لایسنس پکیج بلافاصله پس از تأیید پرداخت فعال می‌شود</p>
                    </div>

                    @if($purchase->gateway)
                        <div class="flex items-center justify-between border-t border-zinc-100 pt-3 dark:border-zinc-800">
                            <span class="text-[11px] font-medium text-zinc-400">درگاه پرداخت</span>
                            <span class="rounded-lg bg-zinc-100 px-2 py-0.5 font-mono text-[10px] font-black tracking-wider text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400" dir="ltr">{{ strtoupper($purchase->gateway) }}</span>
                        </div>
                    @endif
                </section>
            </aside>
        </div>
    @endif
</div>
