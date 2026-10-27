@php($user = auth()->user())
@php($errBag = $errors ?? new \Illuminate\Support\ViewErrorBag)
<div class="space-y-6">
    {{-- header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-black text-zinc-900 dark:text-zinc-50">پروفایل مدیر</h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">اطلاعات حساب و رمز عبور خودتان را همین‌جا مدیریت کنید.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.users.index') }}" wire:navigate>
                <x-btn variant="secondary" icon="users">مدیریت کاربران</x-btn>
            </a>
        </div>
    </div>

    {{-- identity strip --}}
    <div class="card flex flex-wrap items-center gap-4 p-5">
        <x-avatar :name="$user?->name ?? 'مدیر'" size="lg" />
        <div class="min-w-0 flex-1">
            <p class="truncate text-base font-black text-zinc-900 dark:text-zinc-50">{{ $user?->name ?? 'مدیر سیستم' }}</p>
            <p class="truncate text-sm text-zinc-500 dark:text-zinc-400" dir="ltr">{{ $user?->email }}</p>
        </div>
        <x-badge :variant="$user?->role === 'admin' ? 'success' : 'info'" icon="shield-check">
            {{ $user?->role === 'admin' ? 'مدیر' : 'اپراتور' }}
        </x-badge>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- ============ اطلاعات حساب ============ --}}
        <form wire:submit="saveProfile">
            <x-card title="اطلاعات حساب" subtitle="نام نمایشی و ایمیل ورود به پنل.">
                <div class="space-y-5">
                    <x-field label="نام نمایشی" for="profile-name" required error="name">
                        <x-input id="profile-name" wire:model="name" icon="user" placeholder="نام مدیر" :class="$errBag->has('name') ? 'input-error' : ''" />
                    </x-field>

                    <x-field label="ایمیل ورود" for="profile-email" required hint="با این ایمیل وارد پنل می‌شوید؛ باید یکتا باشد." error="email">
                        <x-input id="profile-email" wire:model="email" type="email" dir="ltr" icon="mail" placeholder="admin@panel.test" :class="$errBag->has('email') ? 'input-error' : ''" />
                    </x-field>

                    <div class="flex items-center justify-between gap-3 border-t border-zinc-200/70 pt-4 dark:border-zinc-800">
                        <p class="text-xs leading-5 text-zinc-500 dark:text-zinc-400">پس از تغییر ایمیل، دفعهٔ بعد با ایمیل تازه وارد شوید.</p>
                        <x-btn type="submit" icon="save">ذخیره اطلاعات</x-btn>
                    </div>
                </div>
            </x-card>
        </form>

        {{-- ============ تغییر رمز عبور ============ --}}
        <form wire:submit="savePassword">
            <x-card title="تغییر رمز عبور" subtitle="برای امنیت بیشتر، رمز فعلی را تأیید کنید.">
                <div class="space-y-5">
                    <x-field label="رمز عبور فعلی" for="profile-current-password" required error="current_password">
                        <x-input id="profile-current-password" wire:model="current_password" type="password" dir="ltr" icon="lock" autocomplete="current-password" placeholder="••••••••" :class="$errBag->has('current_password') ? 'input-error' : ''" />
                    </x-field>

                    <x-field label="رمز عبور جدید" for="profile-new-password" required hint="حداقل ۶ کاراکتر؛ ترکیب حروف و اعداد امن‌تر است." error="password">
                        <x-input id="profile-new-password" wire:model="password" type="password" dir="ltr" icon="key-round" autocomplete="new-password" placeholder="••••••••" :class="$errBag->has('password') ? 'input-error' : ''" />
                    </x-field>

                    <x-field label="تکرار رمز عبور جدید" for="profile-new-password-confirmation" required error="password">
                        <x-input id="profile-new-password-confirmation" wire:model="password_confirmation" type="password" dir="ltr" icon="key-round" autocomplete="new-password" placeholder="••••••••" :class="$errBag->has('password') ? 'input-error' : ''" />
                    </x-field>

                    <div class="flex items-center justify-between gap-3 border-t border-zinc-200/70 pt-4 dark:border-zinc-800">
                        <p class="text-xs leading-5 text-zinc-500 dark:text-zinc-400">جلسهٔ فعلی شما باز می‌ماند؛ ورود بعدی با رمز تازه.</p>
                        <x-btn type="submit" variant="secondary" icon="shield-check">تغییر رمز عبور</x-btn>
                    </div>
                </div>
            </x-card>
        </form>
    </div>
</div>
