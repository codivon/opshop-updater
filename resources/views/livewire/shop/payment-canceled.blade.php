<div class="mx-auto w-full max-w-3xl px-4 pt-10 sm:px-6">

    {{-- ============ Hero ============ --}}
    <section class="shop-hero-fail relative overflow-hidden rounded-3xl">
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div class="shop-hero-glow absolute -top-24 -start-24 size-72 rounded-full blur-3xl"></div>
        </div>

        <div class="relative flex flex-col items-center gap-4 px-6 py-12 text-center">
            <div class="flex size-20 items-center justify-center rounded-full bg-white/15 ring-1 ring-white/25 backdrop-blur">
                <x-icon name="x-circle" class="size-10 text-white" />
            </div>

            <h1 class="text-2xl font-black text-white sm:text-3xl">پرداخت لغو شد یا ناتمام ماند</h1>

            <p class="max-w-md text-sm leading-7 text-white/80">
                پرداخت شما به پایان نرسید؛ بنابراین هیچ مبلغی از حساب شما کسر نشده است و سفارشی ثبت و تأیید نشده است.
                اگر از درگاه بازگشته‌اید و مطمئن نیستید، می‌توانید از پروفایل خود وضعیت سفارش‌ها را بررسی کنید.
            </p>
        </div>
    </section>

    {{-- ============ راهنما ============ --}}
    <section class="card mt-6 p-5 sm:p-6">
        <h2 class="flex items-center gap-2 text-base font-black text-zinc-900 dark:text-zinc-50">
            <x-icon name="info" class="size-5 text-brand-600 dark:text-brand-400" />
            چه اتفاقی افتاده است؟
        </h2>

        <ul class="mt-4 space-y-2.5 text-sm leading-7 text-zinc-600 dark:text-zinc-300">
            <li class="flex items-start gap-2.5">
                <x-icon name="check-circle" class="mt-1 size-4 shrink-0 text-brand-500" />
                درگاه پرداخت بدون تأیید تراکنش به سایت بازگشته است؛ یعنی پرداخت کامل نشده یا لغو شده است.
            </li>
            <li class="flex items-start gap-2.5">
                <x-icon name="check-circle" class="mt-1 size-4 shrink-0 text-brand-500" />
                هیچ اشتراک یا پکیجی برای شما فعال نشده و نگرانی دربارهٔ کسر پول وجود ندارد.
            </li>
            <li class="flex items-start gap-2.5">
                <x-icon name="check-circle" class="mt-1 size-4 shrink-0 text-brand-500" />
                می‌توانید هر زمان خرید را دوباره آغاز کنید؛ اگر مطمئن نیستید وضعیت سفارش‌ها را در پنل مشتری ببینید.
            </li>
        </ul>

        <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-zinc-100 pt-4 dark:border-zinc-800">
            <a href="{{ route('shop.subscriptions') }}" wire:navigate>
                <x-btn icon="crown">خرید طرح اشتراک</x-btn>
            </a>
            <a href="{{ route('shop.home') }}" wire:navigate>
                <x-btn variant="secondary" icon="package">فروشگاه پکیج‌ها</x-btn>
            </a>
            <a href="{{ route('customer.profile') }}" wire:navigate>
                <x-btn variant="ghost" icon="user">پروفایل من</x-btn>
            </a>
        </div>
    </section>
</div>
