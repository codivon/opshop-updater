<div class="mx-auto w-full max-w-md px-4 pt-12 sm:px-6">

    <div class="card overflow-hidden">
        {{-- header --}}
        <div class="shop-hero relative px-6 py-8 text-center">
            <div class="pointer-events-none absolute inset-0 opacity-[0.06]" aria-hidden="true"
                 style="background-image: linear-gradient(#fff 1px, transparent 1px), linear-gradient(90deg, #fff 1px, transparent 1px); background-size: 36px 36px;"></div>

            <div class="relative">
                <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/25 backdrop-blur">
                    <x-icon name="key-round" class="size-7 text-white" />
                </span>
                <h1 class="mt-4 text-xl font-black text-white">ورود مشتریان</h1>
                <p class="mt-1.5 text-xs leading-6 text-teal-50/85">
                    فقط با کد آپدیت وارد شوید — بدون رمز و بدون ثبت‌نام.
                </p>
            </div>
        </div>

        {{-- form --}}
        <form wire:submit="login" class="space-y-4 p-6">
            {{-- خلاصهٔ خطاها — هر خطایی (کد نامعتبر، قفل تلاش، کپچا…) اینجا هم دیده می‌شود --}}
            @if($errors->any())
                <div class="flex items-start gap-2.5 rounded-xl bg-rose-50 px-3.5 py-3 text-sm font-medium text-rose-700 ring-1 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20"
                     role="alert">
                    <x-icon name="alert-circle" class="mt-0.5 size-4.5 shrink-0" />
                    <ul class="space-y-1 leading-6">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <x-field label="کد آپدیت شما" for="login-code" error="updateCode" required hint="کد آپدیت را از پنل مشتری یا پیامک خرید خود دریافت کرده‌اید.">
                <x-input id="login-code" wire:model="updateCode" icon="key" placeholder="مثلاً: 1A2B3C4D5E6F" dir="ltr" class="text-start font-mono tracking-wider" autocomplete="off"
                         :class="$errors->has('updateCode') ? 'input-error' : ''" />
            </x-field>

            @if(\App\Support\Features::captcha('customer_login'))
                <x-captcha wireTarget="captcha" name="captcha" refreshWire="refreshCaptcha" />
            @endif

            {{-- هانی‌پات ضدربات — برای انسان نامرئی است --}}
            @if(\App\Support\Features::honeypot())
                <x-honeypot name="website" wireTarget="website" />
            @endif

            <x-btn type="submit" icon="log-in" :loading="true" class="w-full">
                ورود به حساب مشتری
            </x-btn>

            <p class="flex items-start gap-2 rounded-xl bg-zinc-50 p-3 text-[11px] leading-5 text-zinc-500 ring-1 ring-zinc-200/70 dark:bg-zinc-800/60 dark:text-zinc-400 dark:ring-zinc-700/60">
                <x-icon name="info" class="mt-0.5 size-4 shrink-0 text-brand-500" />
                کد آپدیت شناسه‌ی شما در این سیستم است؛ با آن می‌توانید پکیج‌های خریده، لایسنس‌ها و اشتراک فعال‌تان را در پروفایل ببینید.
            </p>
        </form>
    </div>

</div>
