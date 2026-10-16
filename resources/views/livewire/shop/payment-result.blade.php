<div class="space-y-6">

    {{-- ============ Hero ============ --}}
    @php($status = $purchase->status)
    @if($status === \App\Models\PackagePurchase::STATUS_PAID)
        <section class="shop-hero-success relative overflow-hidden">
            <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                <div class="shop-hero-glow absolute -top-24 -end-24 size-96 rounded-full opacity-50 blur-3xl"></div>
            </div>
            <div class="relative mx-auto flex w-full max-w-7xl flex-col items-center gap-4 px-4 py-10 text-center sm:px-6 sm:py-16">
                <span class="flex size-14 items-center justify-center rounded-2xl bg-white/15 text-white ring-1 ring-white/25">
                    <x-icon name="check-circle" class="size-8" />
                </span>
                <h1 class="text-2xl font-black text-white sm:text-3xl">پرداخت با موفقیت انجام شد</h1>
                <p class="text-sm leading-7 text-emerald-50/80">
                    از خرید شما متشکریم{{ $purchase->customer?->name ? '، ' . $purchase->customer->name : '' }}! لایسنس شما صادر شد و پکیج در پروژه شما فعال است.
                </p>
                @if($purchase->transaction_id)
                    <div class="mt-2 flex items-center gap-2 rounded-xl bg-white/10 px-4 py-2 ring-1 ring-white/20" dir="ltr">
                        <span class="font-mono text-sm font-bold tracking-wider text-white">{{ $purchase->transaction_id }}</span>
                        <button x-data type="button"
                                @click="navigator.clipboard.writeText('{{ $purchase->transaction_id }}'); $dispatch('toast', {message: 'کپی شد'})"
                                class="rounded-lg p-1.5 text-emerald-100 transition-colors hover:bg-white/15 hover:text-white"
                                title="کپی شناسه تراکنش">
                            <x-icon name="copy" class="size-4" />
                        </button>
                    </div>
                @endif
            </div>
        </section>
    @elseif($status === \App\Models\PackagePurchase::STATUS_FAILED)
        <section class="shop-hero-fail relative overflow-hidden">
            <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                <div class="shop-hero-glow absolute -top-24 -end-24 size-96 rounded-full opacity-30 blur-3xl"></div>
            </div>
            <div class="relative mx-auto flex w-full max-w-7xl flex-col items-center gap-4 px-4 py-10 text-center sm:px-6 sm:py-16">
                <span class="flex size-14 items-center justify-center rounded-2xl bg-white/15 text-white ring-1 ring-white/25">
                    <x-icon name="x-circle" class="size-8" />
                </span>
                <h1 class="text-2xl font-black text-white sm:text-3xl">پرداخت ناموفق</h1>
                <p class="max-w-xl text-sm leading-7 text-rose-50/80">
                    {{ $purchase->meta['fail_reason'] ?? 'متأسفانه پرداخت شما تأیید نشد. مبلغی از حساب شما کسر نشده است؛ می‌توانید دوباره تلاش کنید.' }}
                </p>
                @if($purchase->package)
                    <a href="{{ route('shop.package', $purchase->package->slug) }}" wire:navigate>
                        <x-btn variant="secondary" icon="refresh-cw">تلاش مجدد خرید</x-btn>
                    </a>
                @endif
            </div>
        </section>
    @else
        <section class="shop-hero-pending relative overflow-hidden">
            <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                <div class="shop-hero-glow absolute -top-24 -end-24 size-96 rounded-full opacity-30 blur-3xl"></div>
            </div>
            <div class="relative mx-auto flex w-full max-w-7xl flex-col items-center gap-4 px-4 py-10 text-center sm:px-6 sm:py-16">
                <span class="flex size-14 items-center justify-center rounded-2xl bg-white/15 text-white ring-1 ring-white/25">
                    <x-icon name="clock" class="size-8" />
                </span>
                <h1 class="text-2xl font-black text-white sm:text-3xl">در انتظار تأیید پرداخت</h1>
                <p class="max-w-xl text-sm leading-7 text-amber-50/80">
                    اگر پرداخت را در درگاه انجام داده‌اید و هنوز به این صفحه برمی‌گردید، چند لحظه بعد «بررسی مجدد» را بزنید.
                    نتیجه پرداخت به‌محض بازگشت از درگاه به‌روزرسانی می‌شود.
                </p>
                <x-btn variant="secondary" icon="refresh-cw" wire:click="recheck" :loading="true">بررسی مجدد</x-btn>
            </div>
        </section>
    @endif

    {{-- ============ Details ============ --}}
    <section class="mx-auto grid w-full max-w-7xl grid-cols-1 items-start gap-6 px-4 pb-12 sm:px-6 lg:grid-cols-3">

        {{-- LEFT --}}
        <div class="space-y-6 lg:col-span-2">

            {{-- payment summary --}}
            <x-card title="جزئیات پرداخت" subtitle="اطلاعات تراکنش ثبت‌شده برای این خرید">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/80 dark:bg-zinc-800/60 dark:ring-zinc-700">
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">مبلغ</p>
                        <p class="mt-1.5 text-sm font-black tabular-nums text-zinc-900 dark:text-zinc-100">
                            @if($purchase->amount <= 0)
                                <span class="text-emerald-600">{{ $purchase->discount_amount > 0 ? 'رایگان با کد تخفیف' : 'رایگان' }}</span>
                            @else
                                {{ money($purchase->amount) }}
                            @endif
                        </p>
                        @if($purchase->discount_amount > 0)
                            <p class="mt-1 text-[11px] text-zinc-400">
                                مبلغ قبل از تخفیف: <span class="line-through">{{ money($purchase->original_amount ?? ($purchase->amount + $purchase->discount_amount)) }}</span>
                            </p>
                        @endif
                    </div>

                    @if($purchase->discount_amount > 0)
                        <div class="rounded-xl bg-emerald-50 p-4 ring-1 ring-emerald-600/15 dark:bg-emerald-500/10 dark:ring-emerald-400/20">
                            <p class="flex items-center gap-1.5 text-xs text-emerald-700/90 dark:text-emerald-400/90">
                                <x-icon name="ticket-percent" class="size-3.5" />
                                کد تخفیف
                            </p>
                            <p class="mt-1.5 flex flex-wrap items-center gap-2">
                                <span class="font-mono font-black text-emerald-800 dark:text-emerald-300" dir="ltr">{{ $purchase->discountCode?->code }}</span>
                                <span class="text-xs font-bold text-emerald-700/80 dark:text-emerald-400/80">— {{ money($purchase->discount_amount) }} تخفیف</span>
                            </p>
                        </div>
                    @endif

                    <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/80 dark:bg-zinc-800/60 dark:ring-zinc-700">
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">درگاه پرداخت</p>
                        <div class="mt-1.5">
                            @if($purchase->gateway)
                                <x-badge variant="neutral" class="font-mono" dir="ltr">{{ strtoupper($purchase->gateway) }}</x-badge>
                            @else
                                <span class="text-sm text-zinc-500">—</span>
                            @endif
                        </div>
                    </div>

                    <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/80 dark:bg-zinc-800/60 dark:ring-zinc-700">
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">شناسه تراکنش</p>
                        <p class="mt-1.5 font-mono text-sm font-bold text-zinc-800 dark:text-zinc-100" dir="ltr">
                            {{ $purchase->transaction_id ?? '—' }}
                        </p>
                    </div>

                    <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/80 dark:bg-zinc-800/60 dark:ring-zinc-700">
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $purchase->isPaid() ? 'تاریخ پرداخت' : 'تاریخ ثبت' }}</p>
                        <p class="mt-1.5 text-sm font-bold tabular-nums text-zinc-800 dark:text-zinc-100">
                            {{ verta_date($purchase->isPaid() ? $purchase->paid_at : $purchase->created_at, 'Y/m/d H:i') ?? '—' }}
                        </p>
                    </div>

                    <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/80 dark:bg-zinc-800/60 dark:ring-zinc-700">
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">طرح</p>
                        <p class="mt-1.5 text-sm font-bold text-zinc-800 dark:text-zinc-100">
                            @if($purchase->pricingPlan)
                                {{ $purchase->pricingPlan->name }}
                                <span class="text-xs font-medium text-zinc-400">({{ $purchase->pricingPlan->duration_label }})</span>
                            @else
                                دریافت رایگان
                            @endif
                        </p>
                    </div>

                    <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/80 dark:bg-zinc-800/60 dark:ring-zinc-700">
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">وضعیت</p>
                        <div class="mt-1.5">
                            @php($variant = match($purchase->status) {
                                'paid' => 'success',
                                'pending' => 'warning',
                                'failed' => 'danger',
                                default => 'neutral',
                            })
                            @php($label = match($purchase->status) {
                                'paid' => 'پرداخت شده',
                                'pending' => 'در انتظار',
                                'failed' => 'ناموفق',
                                'refunded' => 'بازگشتی',
                                default => $purchase->status,
                            })
                            <x-badge :variant="$variant">{{ $label }}</x-badge>
                        </div>
                    </div>
                </div>
            </x-card>

            {{-- license card --}}
            @if($purchase->license)
                @php($license = $purchase->license)
                <x-card title="لایسنس شما" subtitle="این کلید را برای فعال‌سازی/به‌روزرسانی پکیج استفاده کنید">
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center gap-3 rounded-xl bg-brand-50 p-4 ring-1 ring-brand-600/10 dark:bg-brand-500/10 dark:ring-brand-400/20" dir="ltr">
                            <span class="min-w-0 flex-1 break-all font-mono text-base font-black tracking-wider text-brand-700 dark:text-brand-300">{{ $license->license_key }}</span>
                            <button x-data type="button"
                                    @click="navigator.clipboard.writeText('{{ $license->license_key }}'); $dispatch('toast', {message: 'کپی شد'})"
                                    class="flex shrink-0 items-center gap-1.5 rounded-lg bg-white px-2.5 py-1.5 text-xs font-medium text-zinc-500 ring-1 ring-zinc-200 transition-colors hover:text-brand-600 hover:ring-brand-300 dark:bg-zinc-800 dark:text-zinc-400 dark:ring-zinc-700 dark:hover:text-brand-400"
                                    title="کپی کلید لایسنس">
                                <x-icon name="copy" class="size-3.5" />
                                کپی
                            </button>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            @if($license->expires_at === null)
                                <x-badge variant="info" icon="clock">اعتبار نامحدود</x-badge>
                            @else
                                @php($remaining = (int) floor(now()->diffInDays($license->expires_at, false)))
                                @if($license->isExpired() || $remaining < 0)
                                    <x-badge variant="danger" icon="clock">منقضی شده</x-badge>
                                @elseif($remaining < 14)
                                    <x-badge variant="warning" icon="clock">{{ fa_num($remaining) }} روز باقی‌مانده</x-badge>
                                @else
                                    <x-badge variant="neutral" icon="clock">{{ fa_num($remaining) }} روز باقی‌مانده</x-badge>
                                @endif
                            @endif

                            @if($license->renewedFrom)
                                <x-badge variant="primary" icon="refresh-cw">تمدید شده از لایسنس قبلی</x-badge>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/80 dark:bg-zinc-800/60 dark:ring-zinc-700">
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">تاریخ انقضا</p>
                                <p class="mt-1.5 text-sm font-bold tabular-nums {{ $license->isExpired() ? 'text-rose-600' : 'text-zinc-800 dark:text-zinc-100' }}">
                                    {{ $license->expires_at ? verta_date($license->expires_at, 'Y/m/d') : 'نامحدود' }}
                                </p>
                            </div>
                            <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/80 dark:bg-zinc-800/60 dark:ring-zinc-700">
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">تاریخ صدور</p>
                                <p class="mt-1.5 text-sm font-bold tabular-nums text-zinc-800 dark:text-zinc-100">{{ verta_date($license->created_at, 'Y/m/d') }}</p>
                            </div>
                        </div>
                    </div>
                </x-card>
            @endif
        </div>

        {{-- RIGHT: package + download --}}
        <aside class="space-y-6 lg:sticky top-24">
            @if($purchase->package)
                <div class="card space-y-4 p-5 sm:p-6">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-black text-zinc-900 dark:text-zinc-50">پکیج خریداری‌شده</h3>
                        <span class="flex size-9 items-center justify-center rounded-xl bg-brand-500/10 text-brand-600 dark:text-brand-400">
                            <x-icon name="package" class="size-5" />
                        </span>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-xl">
                            @if($purchase->package->thumbnail_url && $purchase->package->thumbnail_url !== asset('images/package-default.png'))
                                <img src="{{ $purchase->package->thumbnail_url }}" alt="{{ $purchase->package->name }}" class="size-full object-cover" />
                            @else
                                <div class="shop-thumb flex size-full items-center justify-center">
                                    <x-icon name="package" class="size-6 text-white/80" />
                                </div>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold text-zinc-900 dark:text-zinc-50">{{ $purchase->package->name }}</p>
                            @if($purchase->version)
                                <x-badge variant="primary" class="mt-1 font-mono" dir="ltr">v{{ $purchase->version->version }}</x-badge>
                            @endif
                        </div>
                    </div>

                    @if($purchase->isPaid())
                        @php($renewed = (bool) $purchase->license?->renewedFrom)
                        <div class="flex items-start gap-3 rounded-xl bg-emerald-50 p-4 ring-1 ring-emerald-600/15 dark:bg-emerald-500/10 dark:ring-emerald-400/20">
                            <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 dark:bg-emerald-400/15 dark:text-emerald-400">
                                <x-icon name="refresh-cw" class="size-4" />
                            </span>
                            <p class="text-xs leading-6 font-medium text-emerald-800 dark:text-emerald-200">
                                پکیج «{{ $purchase->package->name }}» {{ $renewed ? 'در پروژه شما تمدید شد' : 'برای پروژه شما فعال شد' }} و می‌توانید از
                                <span class="font-black">صفحه پکیج‌ها</span>
                                آن را دانلود کنید.
                            </p>
                        </div>
                    @endif

                    <a href="{{ route('shop.package', $purchase->package->slug) }}" wire:navigate
                       class="flex items-center justify-center gap-1.5 rounded-xl px-3 py-2 text-xs font-bold text-brand-600 transition-colors hover:bg-brand-50 dark:text-brand-400 dark:hover:bg-brand-500/10">
                        <x-icon name="arrow-right" class="size-3.5" />
                        بازگشت به صفحه پکیج
                    </a>
                </div>
            @endif

            <div class="card space-y-2 p-5 text-xs leading-6 text-zinc-500 dark:text-zinc-400 sm:p-6">
                <p class="flex items-center gap-2 text-sm font-bold text-zinc-700 dark:text-zinc-200">
                    <x-icon name="info" class="size-4 shrink-0 text-teal-600" />
                    نکته
                </p>
                <p>
                    کلید لایسنس و «کد آپدیت» خود را نگه دارید؛ برای دریافت نسخه‌های بعدی همین پکیج
                    می‌توانید با همان کد آپدیت، مجدداً از پروژه خود خرید/تمدید کنید و از صفحه پکیج‌ها دانلود کنید.
                </p>
            </div>
        </aside>
    </section>
</div>
