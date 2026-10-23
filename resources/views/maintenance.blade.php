{{--
    صفحهٔ حالت تعمیر و نگهداری (503) — مستقل از لایوت‌ها.
    پیام از پنل «امنیت و امکانات» قابل شخصی‌سازی است.
--}}
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>حالت تعمیر · {{ $siteName }}</title>
    <meta name="robots" content="noindex">
    @vite(['resources/css/app.css'])
</head>
<body class="grid min-h-screen place-items-center bg-zinc-50 p-6 antialiased dark:bg-zinc-950">
    <div class="text-center">
        <div class="mx-auto mb-6 flex size-16 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-400 to-brand-600 text-white shadow-lg shadow-pop">
            <x-icon name="wrench" class="size-8" />
        </div>
        <h1 class="text-2xl font-black text-zinc-900 dark:text-zinc-50">{{ $siteName }}</h1>
        <p class="mx-auto mt-3 max-w-md text-sm leading-6 text-zinc-500 dark:text-zinc-400">{{ $message }}</p>
        <p class="mt-8 text-xs text-zinc-400">به‌زودی برمی‌گردیم 🛠</p>
    </div>
</body>
</html>
