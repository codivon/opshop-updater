<x-layouts.guest title="ورود به پنل مدیریت">
    <div class="card animate-slide-up bg-white/95 p-6 backdrop-blur dark:bg-zinc-900/95 sm:p-8">
        <form method="POST" action="{{ route('login') }}" class="space-y-5">
            @csrf

            <x-field label="ایمیل" for="email" required :error="'email'">
                <x-input id="email" type="email" name="email" :value="old('email')" icon="mail" placeholder="admin@example.com"
                         required autofocus autocomplete="email" :class="$errors->has('email') ? 'input-error' : ''" />
            </x-field>

            <x-field label="گذرواژه" for="password" required :error="'password'">
                <x-input id="password" type="password" name="password" icon="lock" placeholder="••••••••"
                         required autocomplete="current-password" :class="$errors->has('password') ? 'input-error' : ''" />
            </x-field>

            <x-captcha name="captcha" />

            {{-- هانی‌پات ضدربات — برای انسان نامرئی است --}}
            <x-honeypot name="website" />

            <label class="flex cursor-pointer items-center gap-2.5 text-sm text-zinc-600 dark:text-zinc-400">
                <input type="checkbox" name="remember" value="1" class="checkbox" {{ old('remember') ? 'checked' : '' }}>
                مرا به خاطر بسپار
            </label>

            @if($errors->any())
                <div class="flex items-center gap-2.5 rounded-xl bg-rose-50 px-3.5 py-3 text-sm font-medium text-rose-700 ring-1 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20" role="alert">
                    <x-icon name="alert-circle" class="size-4.5 shrink-0" />
                    {{ $errors->first() }}
                </div>
            @endif

            <x-btn type="submit" class="w-full" size="lg" icon="log-in">
                ورود به پنل
            </x-btn>
        </form>

        @if(\Illuminate\Support\Facades\Route::has('password.request'))
            <p class="mt-5 text-center text-sm">
                <a href="{{ route('password.request') }}" class="link">گذرواژه‌ام را فراموش کرده‌ام</a>
            </p>
        @endif
    </div>
</x-layouts.guest>
