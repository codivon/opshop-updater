<div class="mx-auto max-w-2xl space-y-6">

    {{-- ============ Status hero ============ --}}
    @if($purchase->isPaid())
        <section class="card relative overflow-hidden p-0">
            <div class="bg-gradient-to-bl from-emerald-500 to-teal-600 p-6 text-center text-white sm:p-8">
                <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-white/20 backdrop-blur">
                    <x-icon name="check-circle" class="size-9" />
                </div>
                <h1 class="mt-4 text-xl font-black">پرداخت با موفقیت انجام شد 🎉</h1>
                <p class="mt-2 text-sm leading-6 text-emerald-50/90">
                    پروژه «{{ $purchase->product?->name }}» برای شما فعال شد و همین حالا قابل دانلود است.
                </p>
            </div>

            <div class="space-y-4 p-5 sm:p-6">
                {{-- محصول --}}
                <div class="flex items-center gap-4">
                    @if($purchase->product?->thumbnail)
                        <img src="{{ $purchase->product->thumbnail_url }}" alt="{{ $purchase->product->name }}"
                             class="size-16 shrink-0 rounded-2xl object-cover ring-1 ring-zinc-200 dark:ring-zinc-700" />
                    @else
                        <div class="flex size-16 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-bl from-brand-100 to-teal-50 dark:from-zinc-800 dark:to-zinc-900">
                            <x-icon name="rocket" class="size-7 text-brand-500" />
                        </div>
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-base font-black text-zinc-900 dark:text-zinc-50">{{ $purchase->product?->name }}</p>
                        <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                            نسخه <span class="font-mono" dir="ltr">v{{ $purchase->version?->version ?? '-' }}</span>
                            @if($purchase->version?->file_size)
                                · {{ $purchase->version->file_size_human }}
                            @endif
                        </p>
                    </div>
                </div>

                {{-- دانلود --}}
                <div class="rounded-2xl bg-emerald-50 p-4 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:ring-emerald-500/20">
                    <div class="flex flex-col items-center gap-3">
                        <p class="text-xs leading-5 text-emerald-700 dark:text-emerald-300">
                            لینک دانلود یک‌بارمصرف است و ۱۵ دقیقه اعتبار دارد؛ در صورت نیاز می‌توانید دوباره لینک جدید بگیرید.
                        </p>
                        <x-btn wire:click="getDownload" :loading="true" size="lg" icon="download" class="w-full sm:w-auto">
                            دانلود فایل پروژه (ZIP)
                        </x-btn>
                    </div>
                </div>
            </div>
        </section>
    @elseif($purchase->status === \App\Models\ProductPurchase::STATUS_FAILED)
        <section class="card overflow-hidden p-0">
            <div class="bg-gradient-to-bl from-rose-500 to-rose-600 p-6 text-center text-white sm:p-8">
                <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-white/20 backdrop-blur">
                    <x-icon name="x-circle" class="size-9" />
                </div>
                <h1 class="mt-4 text-xl font-black">پرداخت ناموفق بود</h1>
                @if($purchase->meta['fail_reason'] ?? null)
                    <p class="mt-2 text-sm leading-6 text-rose-50/90">{{ $purchase->meta['fail_reason'] }}</p>
                @endif
            </div>
            <div class="flex flex-col gap-2 p-5 sm:flex-row sm:justify-center sm:p-6">
                <a href="{{ route('shop.product', $purchase->product?->slug) }}" wire:navigate>
                    <x-btn icon="refresh-cw" variant="primary">تلاش مجدد خرید</x-btn>
                </a>
                <a href="{{ route('shop.home') }}" wire:navigate>
                    <x-btn variant="secondary" icon="home">بازگشت به فروشگاه</x-btn>
                </a>
            </div>
        </section>
    @else
        {{-- pending --}}
        <section class="card overflow-hidden p-0">
            <div class="bg-gradient-to-bl from-amber-500 to-orange-600 p-6 text-center text-white sm:p-8">
                <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-white/20 backdrop-blur">
                    <x-icon name="clock" class="size-9" />
                </div>
                <h1 class="mt-4 text-xl font-black">در انتظار تأیید پرداخت</h1>
                <p class="mt-2 text-sm leading-6 text-amber-50/90">
                    اگر پرداخت انجام شده اما به این صفحه برگشته‌اید، چند لحظه صبر کنید و دکمه «بررسی مجدد» را بزنید.
                </p>
            </div>
            <div class="flex flex-col gap-2 p-5 sm:flex-row sm:justify-center sm:p-6">
                <x-btn wire:click="recheck" :loading="true" icon="refresh-cw">بررسی مجدد</x-btn>
                @if($purchase->payment_url)
                    <a href="{{ $purchase->payment_url }}" wire:navigate>
                        <x-btn variant="secondary" icon="credit-card">بازگشت به درگاه</x-btn>
                    </a>
                @endif
            </div>
        </section>
    @endif

    {{-- ============ Transaction details ============ --}}
    <section class="card p-5 sm:p-6">
        <h2 class="mb-4 text-sm font-black text-zinc-900 dark:text-zinc-50">جزئیات تراکنش</h2>
        <dl class="space-y-3 text-sm">
            <div class="flex items-center justify-between gap-4">
                <dt class="shrink-0 text-zinc-500 dark:text-zinc-400">شماره تراکنش</dt>
                <dd class="truncate font-mono text-xs text-zinc-700 dark:text-zinc-300" dir="ltr">{{ $purchase->transaction_id ?? '—' }}</dd>
            </div>
            <div class="flex items-center justify-between gap-4">
                <dt class="shrink-0 text-zinc-500 dark:text-zinc-400">مبلغ</dt>
                <dd class="font-bold tabular-nums">
                    @if($purchase->amount > 0)
                        {{ money($purchase->amount) }}
                    @else
                        <span class="text-emerald-600">{{ $purchase->discount_amount > 0 ? 'رایگان با کد تخفیف' : 'رایگان' }}</span>
                    @endif
                </dd>
            </div>
            @if($purchase->discount_amount > 0)
                <div class="flex items-center justify-between gap-4">
                    <dt class="shrink-0 flex items-center gap-1.5 text-zinc-500 dark:text-zinc-400">
                        <x-icon name="ticket-percent" class="size-3.5 text-emerald-500" />
                        کد تخفیف
                    </dt>
                    <dd class="font-bold">
                        <span class="font-mono text-emerald-700 dark:text-emerald-400" dir="ltr">{{ $purchase->discountCode?->code }}</span>
                        <span class="text-xs text-emerald-700/80 dark:text-emerald-400/80">— {{ money($purchase->discount_amount) }}</span>
                    </dd>
                </div>
            @endif
            @if($purchase->gateway)
                <div class="flex items-center justify-between gap-4">
                    <dt class="shrink-0 text-zinc-500 dark:text-zinc-400">درگاه</dt>
                    <dd class="font-bold">{{ $purchase->gateway }}</dd>
                </div>
            @endif
            <div class="flex items-center justify-between gap-4">
                <dt class="shrink-0 text-zinc-500 dark:text-zinc-400">وضعیت</dt>
                <dd>
                    @if($purchase->isPaid())
                        <x-badge variant="success" icon="check-circle">پرداخت شده</x-badge>
                    @elseif($purchase->status === \App\Models\ProductPurchase::STATUS_FAILED)
                        <x-badge variant="danger" icon="x-circle">ناموفق</x-badge>
                    @else
                        <x-badge variant="warning" icon="clock">در انتظار</x-badge>
                    @endif
                </dd>
            </div>
            @if($purchase->paid_at)
                <div class="flex items-center justify-between gap-4">
                    <dt class="shrink-0 text-zinc-500 dark:text-zinc-400">تاریخ پرداخت</dt>
                    <dd class="text-zinc-700 dark:text-zinc-300">{{ verta_date($purchase->paid_at) }}</dd>
                </div>
            @endif
        </dl>

        <div class="mt-5 border-t border-zinc-100 pt-4 dark:border-zinc-700/50">
            <p class="text-[11px] leading-5 text-zinc-400">
                💡 این صفحه را نشانه بگیرید؛ در صورت بستن صفحه، می‌توانید از فروشگاه (بخش پروژه‌ها → «خرید قبلی دارید؟») با شماره تراکنش دوباره به همین صفحه برگردید.
            </p>
        </div>
    </section>

    {{-- ============ Actions ============ --}}
    <div class="flex flex-wrap items-center justify-center gap-3">
        <a href="{{ route('shop.product', $purchase->product?->slug) }}" wire:navigate>
            <x-btn variant="secondary" icon="rocket">صفحه پروژه</x-btn>
        </a>
        <a href="{{ route('shop.home') }}" wire:navigate>
            <x-btn variant="ghost" icon="home">فروشگاه</x-btn>
        </a>
    </div>
</div>
