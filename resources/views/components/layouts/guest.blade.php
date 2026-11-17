{{--
    Guest layout for auth pages (login, register, ...).
    Used by Livewire page components:  #[Layout('components.layouts.guest')]
--}}
@props(['title' => null])

<!DOCTYPE html>
<html lang="fa" dir="rtl" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'ورود' }} · {{ config('app.name') }}</title>
    {{-- سئو: صفحات ورود/ثبت‌نام هرگز ایندکس نمی‌شوند (Task 82) --}}
    <meta name="robots" content="noindex, nofollow">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script>
        window.initDarkMode && window.initDarkMode();
    </script>
</head>

<body class="grid min-h-screen place-items-center overflow-hidden bg-zinc-950 antialiased">
    {{-- decorative background --}}
    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
        <div class="absolute -top-32 -start-32 size-96 rounded-full bg-brand-500/20 blur-3xl"></div>
        <div class="absolute -bottom-32 -end-32 size-96 rounded-full bg-teal-500/10 blur-3xl"></div>
        <div class="absolute top-1/3 end-1/4 size-64 rounded-full bg-brand-400/10 blur-3xl"></div>
    </div>

    <div class="relative z-10 mx-4 w-full max-w-md">
        {{-- brand --}}
        <div class="mb-8 flex flex-col items-center gap-3 animate-slide-up">
            <div class="flex size-14 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-400 to-brand-600 text-white shadow-xl shadow-brand-500/25">
                <x-icon name="rocket" class="size-7" />
            </div>
            <div class="text-center">
                <h1 class="text-xl font-black text-white">آپدیت‌سنتر</h1>
                <p class="mt-1 text-sm text-zinc-500">{{ $title ?? 'ورود به پنل مدیریت' }}</p>
            </div>
        </div>

        {{ $slot }}

        <p class="mt-8 text-center text-xs text-zinc-600">
            {{ config('app.name') }} · نسخه Livewire
        </p>
    </div>

</body>
</html>
