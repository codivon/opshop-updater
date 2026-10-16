<div class="mx-auto w-full max-w-3xl px-4 py-10 sm:px-6">

    @php($state = match(true) {
        $order->admin_status === \App\Models\SubscriptionOrder::ADMIN_STATUS_APPROVED => 'approved',
        $order->admin_status === \App\Models\SubscriptionOrder::ADMIN_STATUS_REJECTED => 'rejected',
        $order->status === \App\Models\SubscriptionOrder::STATUS_PAID => 'paid_pending',
        $order->status === \App\Models\SubscriptionOrder::STATUS_FAILED => 'failed',
        default => 'pending',
    })

    {{-- ============ Status hero ============ --}}
    <section class="relative overflow-hidden rounded-3xl {{ $state === 'approved' ? 'shop-hero-success' : ($state === 'rejected' || $state === 'failed' ? 'shop-hero-fail' : 'shop-hero-pending') }}">
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div class="shop-hero-glow absolute -top-24 -start-24 size-72 rounded-full blur-3xl"></div>
        </div>

        <div class="relative flex flex-col items-center gap-4 px-6 py-12 text-center">
            {{-- icon --}}
            <div class="flex size-20 items-center justify-center rounded-full bg-white/15 ring-1 ring-white/25 backdrop-blur">
                @if($state === 'approved')
                    <x-icon name="badge-check" class="size-10 text-white" />
                @elseif($state === 'rejected' || $state === 'failed')
                    <x-icon name="x-circle" class="size-10 text-white" />
                @elseif($state === 'paid_pending')
                    <x-icon name="hourglass" class="size-10 animate-pulse text-white" />
                @else
                    <x-icon name="clock" class="size-10 text-white" />
                @endif
            </div>

            <h1 class="text-2xl font-black text-white sm:text-3xl">
                @if($state === 'approved')
                    اشتراک شما فعال شد 🎉
                @elseif($state === 'rejected')
                    درخواست شما رد شد
                @elseif($state === 'failed')
                    پرداخت ناموفق بود
                @elseif($state === 'paid_pending')
                    پرداخت موفق — در انتظار تأیید مدیر
                @else
                    در انتظار پرداخت
                @endif
            </h1>

            <p class="max-w-md text-sm leading-7 text-white/80">
                @if($state === 'approved')
                    اشتراک «{{ $order->plan_name }}» فعال است و دسترسی‌های رایگان پکیج‌های همراه، برای پروژه شما صادر شد.
                @elseif($state === 'rejected')
                    {{ $order->rejected_reason ?? 'درخواست اشتراک شما توسط مدیر رد شد.' }}
                @elseif($state === 'failed')
                    {{ $order->meta['fail_reason'] ?? 'متأسفانه پرداخت شما تأیید نشد؛ مبلغی از حساب شما کسر نشده است.' }}
                @elseif($state === 'paid_pending')
                    پرداخت شما با موفقیت ثبت شد. درخواست اشتراک «{{ $order->plan_name }}» در صف تأیید مدیر قرار دارد و پس از تأیید، فعال می‌شود.
                @else
                    پرداخت این سفارش هنوز از سمت درگاه تأیید نشده است.
                @endif
            </p>
        </div>
    </section>

    {{-- ============ Details card ============ --}}
    <section class="card mt-6 space-y-4 p-6">
        <h2 class="flex items-center gap-2 text-base font-black text-zinc-900 dark:text-zinc-50">
            <x-icon name="receipt" class="size-5 text-brand-600 dark:text-brand-400" />
            جزئیات سفارش #{{ fa_num($order->id) }}
        </h2>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="rounded-2xl bg-zinc-50 p-4 ring-1 ring-zinc-200/70 dark:bg-zinc-800/60 dark:ring-zinc-700/50">
                <p class="text-xs text-zinc-400">طرح اشتراک</p>
                <p class="mt-1 flex items-center gap-1.5 text-sm font-bold text-zinc-800 dark:text-zinc-100">
                    <x-icon name="crown" class="size-4 text-amber-500" />
                    {{ $order->plan_name }}
                </p>
            </div>
            <div class="rounded-2xl bg-zinc-50 p-4 ring-1 ring-zinc-200/70 dark:bg-zinc-800/60 dark:ring-zinc-700/50">
                <p class="text-xs text-zinc-400">مدت اعتبار</p>
                <p class="mt-1 text-sm font-bold text-zinc-800 dark:text-zinc-100">
                    @if($order->expires_at)
                        {{ fa_num(verta_date($order->starts_at)) }} تا {{ fa_num(verta_date($order->expires_at)) }}
                    @else
                        نامحدود
                    @endif
                </p>
            </div>
            <div class="rounded-2xl bg-zinc-50 p-4 ring-1 ring-zinc-200/70 dark:bg-zinc-800/60 dark:ring-zinc-700/50">
                <p class="text-xs text-zinc-400">مبلغ</p>
                <p class="mt-1 text-sm font-black tabular-nums text-zinc-800 dark:text-zinc-100">
                    @if($order->final_amount > 0)
                        {{ fa_num(money($order->final_amount)) }}
                    @else
                        <span class="text-emerald-600">{{ $order->discount_code_id ? 'رایگان با کد تخفیف' : 'رایگان' }}</span>
                    @endif
                </p>
                @if($order->discount > 0 && $order->final_amount > 0)
                    <p class="mt-1 text-[11px] text-zinc-400">
                        مبلغ قبل از تخفیف: <span class="line-through">{{ money($order->amount) }}</span>
                    </p>
                @endif
            </div>
            @if($order->discount > 0)
                <div class="rounded-2xl bg-emerald-50 p-4 ring-1 ring-emerald-600/15 dark:bg-emerald-500/10 dark:ring-emerald-400/20">
                    <p class="flex items-center gap-1.5 text-xs text-emerald-700/90 dark:text-emerald-400/90">
                        <x-icon name="ticket-percent" class="size-3.5" />
                        تخفیف اعمال‌شده
                    </p>
                    <p class="mt-1 flex flex-wrap items-center gap-2 text-sm">
                        @if($order->discountCode)
                            <span class="font-mono font-black text-emerald-800 dark:text-emerald-300" dir="ltr">{{ $order->discountCode->code }}</span>
                        @elseif(isset($order->meta['coupon']['code']))
                            <span class="font-mono font-black text-emerald-800 dark:text-emerald-300" dir="ltr">{{ $order->meta['coupon']['code'] }}</span>
                        @endif
                        <span class="text-xs font-bold text-emerald-700/80 dark:text-emerald-400/80">— {{ money($order->discount) }}</span>
                    </p>
                </div>
            @endif
            <div class="rounded-2xl bg-zinc-50 p-4 ring-1 ring-zinc-200/70 dark:bg-zinc-800/60 dark:ring-zinc-700/50">
                <p class="text-xs text-zinc-400">وضعیت</p>
                <p class="mt-1 text-sm font-bold text-zinc-800 dark:text-zinc-100">
                    {{ $order->status_label }} · {{ $order->admin_status_label }}
                </p>
            </div>
        </div>

        {{-- features --}}
        @if(!empty($order->meta['plan']['features']))
            <div class="rounded-2xl bg-zinc-50 p-4 ring-1 ring-zinc-200/70 dark:bg-zinc-800/60 dark:ring-zinc-700/50">
                <p class="text-xs font-bold text-zinc-500 dark:text-zinc-300">قابلیت‌های طرح</p>
                <ul class="mt-2 grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                    @foreach($order->meta['plan']['features'] as $feature)
                        <li class="flex items-center gap-1.5 text-xs text-zinc-600 dark:text-zinc-300">
                            <x-icon name="check-circle" class="size-3.5 text-emerald-500" />
                            {{ $feature }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- granted licenses (after approval) --}}
        @if($this->grantedLicenses->count())
            <div class="rounded-2xl bg-emerald-50 p-4 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:ring-emerald-400/20">
                <p class="text-xs font-bold text-emerald-700 dark:text-emerald-400">پکیج‌های رایگان فعال‌شده در پروژه شما</p>
                <ul class="mt-2.5 space-y-2.5">
                    @foreach($this->grantedLicenses as $license)
                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-white/70 p-3 ring-1 ring-emerald-100 dark:bg-zinc-800/70 dark:ring-emerald-400/10">
                            <span class="flex items-center gap-2 text-sm font-bold text-zinc-800 dark:text-zinc-100">
                                <x-icon name="package" class="size-4 text-brand-600 dark:text-brand-400" />
                                {{ $license->package?->name }}
                            </span>
                            <span class="flex items-center gap-3">
                                <span class="font-mono text-xs text-zinc-500 dark:text-zinc-400" dir="ltr">{{ $license->license_key }}</span>
                                <button x-data type="button"
                                        @click="navigator.clipboard.writeText('{{ $license->license_key }}'); $dispatch('toast', {message: 'کپی شد'})"
                                        class="rounded-lg p-1.5 text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-700"
                                        title="کپی کلید">
                                    <x-icon name="copy" class="size-3.5" />
                                </button>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- actions --}}
        <div class="flex flex-wrap items-center gap-2 border-t border-zinc-100 pt-4 dark:border-zinc-800">
            @if($state === 'pending' || $state === 'failed')
                <x-btn icon="refresh-cw" wire:click="recheck" :loading="true">بررسی مجدد وضعیت</x-btn>
            @endif
            <a href="{{ route('shop.subscriptions') }}" wire:navigate>
                <x-btn variant="secondary" icon="crown">بازگشت به طرح‌های اشتراک</x-btn>
            </a>
            <a href="{{ route('shop.home') }}" wire:navigate>
                <x-btn variant="ghost" icon="home">فروشگاه پکیج‌ها</x-btn>
            </a>
        </div>

        @if($state === 'paid_pending')
            <p class="rounded-xl bg-amber-50 p-3 text-xs leading-6 font-medium text-amber-700 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-400/20">
                نکته: تأیید مدیر معمولاً چند ساعت طول می‌کشد. این صفحه را می‌توانید بعداً باز کنید و وضعیت را با «بررسی مجدد» به‌روز کنید.
            </p>
        @endif
    </section>
</div>
