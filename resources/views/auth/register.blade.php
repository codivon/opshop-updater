<x-layouts.guest title="ایجاد حساب کاربری">
    <div class="card animate-slide-up bg-white/95 p-6 backdrop-blur dark:bg-zinc-900/95 sm:p-8">
        <form method="POST" action="{{ route('register') }}" class="space-y-5">
            @csrf

            <x-field label="نام و نام خانوادگی" for="name" required>
                <x-input id="name" type="text" name="name" :value="old('name')" icon="user" placeholder="مثلاً علی رضایی" required autofocus
                         :class="$errors->has('name') ? 'input-error' : ''" />
            </x-field>

            <x-field label="ایمیل" for="email" required>
                <x-input id="email" type="email" name="email" :value="old('email')" icon="mail" placeholder="you@example.com" required
                         :class="$errors->has('email') ? 'input-error' : ''" />
            </x-field>

            <x-field label="گذرواژه" for="password" required>
                <x-input id="password" type="password" name="password" icon="lock" placeholder="••••••••" required autocomplete="new-password"
                         :class="$errors->has('password') ? 'input-error' : ''" />
            </x-field>

            <x-field label="تکرار گذرواژه" for="password-confirm" required>
                <x-input id="password-confirm" type="password" name="password_confirmation" icon="lock" placeholder="••••••••" required autocomplete="new-password" />
            </x-field>

            <x-captcha name="captcha" />

            {{-- هانی‌پات ضدربات — برای انسان نامرئی است --}}
            <x-honeypot name="website" />

            @if($errors->any())
                <div class="flex items-center gap-2.5 rounded-xl bg-rose-50 px-3.5 py-3 text-sm font-medium text-rose-700 ring-1 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20" role="alert">
                    <x-icon name="alert-circle" class="size-4.5 shrink-0" />
                    {{ $errors->first() }}
                </div>
            @endif

            <x-btn type="submit" class="w-full" size="lg" icon="user-plus">
                ایجاد حساب
            </x-btn>
        </form>

        <p class="mt-5 text-center text-sm text-zinc-500">
            قبلاً ثبت‌نام کرده‌اید؟
            <a href="{{ route('login') }}" class="link">وارد شوید</a>
        </p>
    </div>
</x-layouts.guest>
