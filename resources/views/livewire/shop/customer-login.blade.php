<div class="mx-auto w-full px-4 pt-10 sm:px-6">

    <div @class([
        'card mx-auto overflow-hidden',
        $tab === 'register' ? 'max-w-xl' : 'max-w-md',
    ])>

        {{-- header --}}
        <div class="shop-hero relative px-6 py-8 text-center">
            <div class="pointer-events-none absolute inset-0 opacity-[0.06]" aria-hidden="true"
                 style="background-image: linear-gradient(#fff 1px, transparent 1px), linear-gradient(90deg, #fff 1px, transparent 1px); background-size: 36px 36px;"></div>

            <div class="relative">
                <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/25 backdrop-blur">
                    @if($tab === 'register')
                        <x-icon name="user-plus" class="size-7 text-white" />
                    @else
                        <x-icon name="key-round" class="size-7 text-white" />
                    @endif
                </span>
                <h1 class="mt-4 text-xl font-black text-white">حساب مشتریان</h1>
                @if($tab === 'register')
                    <p class="mt-1.5 text-xs leading-6 text-teal-50/85">
                        در چند ثانیه حساب بسازید و کد آپدیت اختصاصی بگیرید.
                    </p>
                @else
                    <p class="mt-1.5 text-xs leading-6 text-teal-50/85">
                        با کد آپدیت وارد شوید — بدون رمز؛ حساب ندارید؟ همین‌جا ثبت‌نام کنید.
                    </p>
                @endif
            </div>
        </div>

        {{-- tabs + form --}}
        <div class="p-6 sm:p-8">

            {{-- سوییچ مدرن ورود / ثبت‌نام --}}
            <div class="grid grid-cols-2 gap-1 rounded-2xl bg-zinc-100 p-1 ring-1 ring-zinc-200/60 dark:bg-zinc-800/80 dark:ring-zinc-700/50"
                 role="tablist" aria-label="ورود یا ثبت‌نام مشتری">
                <button type="button" wire:click="switchTab('login')" wire:key="tab-login"
                        role="tab" aria-selected="{{ $tab === 'login' ? 'true' : 'false' }}"
                        @class([
                            'flex items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-sm font-bold transition-all duration-150',
                            'bg-white text-brand-700 shadow-sm ring-1 ring-zinc-200/60 dark:bg-zinc-900 dark:text-brand-400 dark:ring-zinc-700/60' => $tab === 'login',
                            'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' => $tab !== 'login',
                        ])>
                    <x-icon name="log-in" class="size-4.5" />
                    ورود مشتری
                </button>
                <button type="button" wire:click="switchTab('register')" wire:key="tab-register"
                        role="tab" aria-selected="{{ $tab === 'register' ? 'true' : 'false' }}"
                        @class([
                            'flex items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-sm font-bold transition-all duration-150',
                            'bg-white text-brand-700 shadow-sm ring-1 ring-zinc-200/60 dark:bg-zinc-900 dark:text-brand-400 dark:ring-zinc-700/60' => $tab === 'register',
                            'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' => $tab !== 'register',
                        ])>
                    <x-icon name="user-plus" class="size-4.5" />
                    ثبت‌نام جدید
                </button>
            </div>

            {{-- ==================== تب ورود ==================== --}}
            @if($tab === 'login')
                <form wire:submit="login" wire:key="form-login" class="mt-6 space-y-4">
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
                        <x-captcha spot="customer_login" wireTarget="captcha" name="captcha" refreshWire="refreshCaptcha" />
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
            @endif

            {{-- ==================== تب ثبت‌نام ==================== --}}
            @if($tab === 'register')
                <form wire:submit="register" wire:key="form-register" class="mt-6 space-y-5">

                    {{-- خلاصهٔ خطاها --}}
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

                    {{-- نام و نام خانوادگی --}}
                    <x-field label="نام و نام خانوادگی" for="reg-name" error="reg.name" required>
                        <x-input id="reg-name" wire:model="reg.name" icon="user" placeholder="مثلاً: علی محمدی" autocomplete="name"
                                 :class="$errors->has('reg.name') ? 'input-error' : ''" />
                    </x-field>

                    {{-- ایمیل + شماره تلفن --}}
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-field label="ایمیل" for="reg-email" error="reg.email" required>
                            <x-input id="reg-email" wire:model="reg.email" icon="mail" type="email" dir="ltr" placeholder="you@example.com" class="text-start" autocomplete="email"
                                     :class="$errors->has('reg.email') ? 'input-error' : ''" />
                        </x-field>

                        <x-field label="شماره تلفن (اختیاری)" for="reg-phone" error="reg.phone">
                            <x-input id="reg-phone" wire:model="reg.phone" icon="phone" dir="ltr" placeholder="0912 000 0000" class="text-start" inputmode="tel" autocomplete="tel"
                                     :class="$errors->has('reg.phone') ? 'input-error' : ''" />
                        </x-field>
                    </div>

                    {{-- آدرس سایت — خیلی مهم: پکیج‌ها و لایسنس‌ها روی همین دامنه کار می‌کنند --}}
                    <div class="rounded-2xl bg-brand-50/70 p-3.5 ring-1 ring-brand-500/20 dark:bg-brand-500/5 dark:ring-brand-400/20">
                        <div class="mb-1.5 flex items-center justify-between gap-2">
                            <label for="reg-website" class="flex items-center gap-1 text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                آدرس سایت
                                <span class="text-rose-500">*</span>
                            </label>
                            <span class="inline-flex items-center gap-1 rounded-lg bg-amber-100 px-2 py-0.5 text-[10px] font-black text-amber-700 ring-1 ring-amber-600/20 dark:bg-amber-500/15 dark:text-amber-400 dark:ring-amber-400/25"
                                  title="پکیج‌ها و لایسنس‌ها روی این دامنه فعال می‌شوند">
                                <x-icon name="alert-circle" class="size-3" />
                                خیلی مهم
                            </span>
                        </div>

                        <x-input id="reg-website" wire:model="reg.website_url" icon="globe" dir="ltr" placeholder="https://myshop.ir" class="text-start" inputmode="url" autocomplete="url"
                                 :class="$errors->has('reg.website_url') ? 'input-error' : ''" />

                        <p class="mt-1.5 flex items-start gap-1.5 text-xs leading-5 text-zinc-500 dark:text-zinc-400">
                            <x-icon name="shield-check" class="mt-0.5 size-3.5 shrink-0 text-brand-500" />
                            پکیج‌ها و لایسنس‌ها فقط روی همین دامنه فعال می‌شوند؛ آدرس را دقیق و کامل وارد کنید (بدون اسکیم هم قبول است).
                        </p>

                        @error('reg.website_url')
                            <p class="mt-1.5 flex items-center gap-1.5 text-xs font-medium text-rose-600 dark:text-rose-400">
                                <x-icon name="alert-circle" class="size-3.5 shrink-0" />
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- وضعیت حساب — پیش‌فرض فعال (نمایشی؛ تغییرش فقط از پنل مدیریت) --}}
                    <div class="rounded-xl bg-emerald-50 px-3.5 py-3 ring-1 ring-emerald-600/15 dark:bg-emerald-500/10 dark:ring-emerald-400/20">
                        <div class="flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2 text-sm font-medium text-emerald-800 dark:text-emerald-300">
                                <x-icon name="badge-check" class="size-4.5" />
                                وضعیت حساب
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-100 px-2.5 py-1 text-xs font-black text-emerald-700 ring-1 ring-emerald-600/20 dark:bg-emerald-500/20 dark:text-emerald-300 dark:ring-emerald-400/25">
                                <span class="size-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                                فعال
                            </span>
                        </div>
                        <p class="mt-1.5 text-[11px] leading-5 text-emerald-700/85 dark:text-emerald-400/85">
                            حساب شما بلافاصله پس از ثبت‌نام فعال می‌شود و می‌توانید پکیج یا اشتراک بخرید.
                        </p>
                    </div>

                    @if(\App\Support\Features::captcha('customer_login'))
                        <x-captcha spot="customer_login" wireTarget="captcha" name="captcha" refreshWire="refreshCaptcha" />
                    @endif

                    {{-- هانی‌پات ضدربات — برای انسان نامرئی است --}}
                    @if(\App\Support\Features::honeypot())
                        <x-honeypot name="website" wireTarget="website" />
                    @endif

                    <x-btn type="submit" icon="user-plus" :loading="true" size="lg" class="w-full">
                        ساخت حساب مشتری
                    </x-btn>

                    <p class="flex items-start gap-2 rounded-xl bg-zinc-50 p-3 text-[11px] leading-5 text-zinc-500 ring-1 ring-zinc-200/70 dark:bg-zinc-800/60 dark:text-zinc-400 dark:ring-zinc-700/60">
                        <x-icon name="key-round" class="mt-0.5 size-4 shrink-0 text-brand-500" />
                        پس از ثبت‌نام، «کد آپدیت» اختصاصی می‌گیرید؛ همان کلید ورود و نصب آپدیت‌ها در فروشگاه‌ساز شماست و در پروفایل قابل کپی است.
                    </p>
                </form>
            @endif

        </div>
    </div>

</div>
