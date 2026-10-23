<x-layouts.guest title="کد تأیید دومرحله‌ای">
    <div class="card animate-slide-up bg-white/95 p-6 backdrop-blur dark:bg-zinc-900/95 sm:p-8">
        <div class="mb-6 flex flex-col items-center gap-3 text-center">
            <div class="flex size-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                <x-icon name="key-round" class="size-6" />
            </div>
            <div>
                <h2 class="text-base font-black text-zinc-900 dark:text-zinc-50">ورود دومرحله‌ای</h2>
                <p class="mt-1 text-sm leading-6 text-zinc-500 dark:text-zinc-400">
                    کد تأیید ۶ رقمی برای شما ارسال شد.
                </p>
                <p class="mt-0.5 text-xs text-zinc-400">
                    @if($channel === 'sms')
                        کد با پیامک به شمارهٔ مدیر ارسال می‌شود.
                    @elseif($channel === 'both')
                        کد با ایمیل {{ $maskedEmail }} و پیامک ارسال می‌شود.
                    @else
                        کد به ایمیل {{ $maskedEmail }} ارسال می‌شود.
                    @endif
                </p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.2fa.verify') }}" class="space-y-5">
            @csrf

            <x-field label="کد تأیید" for="code" required :error="'code'">
                <x-input id="code" name="code" inputmode="numeric" dir="ltr" autocomplete="one-time-code"
                         placeholder="------" maxlength="20" required autofocus
                         :class="$errors->has('code') ? 'input-error' : ''" />
            </x-field>

            @if(! $sent)
                <div class="flex items-center gap-2.5 rounded-xl bg-amber-50 px-3.5 py-3 text-sm font-medium text-amber-800 ring-1 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-400/20" role="alert">
                    <x-icon name="alert-triangle" class="size-4.5 shrink-0" />
                    ارسال خودکار کد ناموفق بود؛ کد پشتیبان در لاگ سرور (laravel.log) ثبت شده است.
                </div>
            @endif

            <x-btn type="submit" class="w-full" size="lg" icon="key-round">
                تأیید و ورود به پنل
            </x-btn>
        </form>

        <form method="POST" action="{{ route('admin.2fa.resend') }}" class="mt-4">
            @csrf
            <button type="submit" class="link mx-auto flex items-center gap-1.5 text-sm">
                <x-icon name="refresh-cw" class="size-4" />
                کد را دریافت نکردید؟ ارسال مجدد
            </button>
        </form>
    </div>
</x-layouts.guest>
