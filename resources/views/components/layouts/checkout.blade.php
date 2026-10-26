{{--
    لایوت مینیمال صفحه تسویه (checkout) — بدون هدر/فوتر/ناوبری فروشگاه.
    فقط: تم روشن/تیره (دکمه شناور) + محتوا + توست‌ها.
    Used by Livewire page components:  #[Layout('components.layouts.checkout')]
--}}
@props(['title' => null])

@php
    $siteName = \App\Support\Features::siteName();
@endphp

<!DOCTYPE html>
<html lang="fa" dir="rtl" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'تسویه سفارش' }} · {{ $siteName }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script>
        // Apply theme before first paint to avoid a flash.
        window.initDarkMode && window.initDarkMode();
    </script>

    {{-- گرادیان‌های مشترک با فروشگاه (hero کارت‌های وضعیت) --}}
    <style>
        .shop-hero-success {
            background-image: linear-gradient(135deg, #059669 0%, #047857 45%, #064e3b 100%);
        }
        .shop-hero-fail {
            background-image: linear-gradient(135deg, #e11d48 0%, #be123c 45%, #4c0519 100%);
        }
        .shop-hero-pending {
            background-image: linear-gradient(135deg, #f59e0b 0%, #d97706 45%, #78350f 100%);
        }
        .shop-thumb {
            background-image: linear-gradient(135deg, #0d9488 0%, #10b981 55%, #047857 100%);
        }
    </style>
</head>

<body class="min-h-screen bg-zinc-50 antialiased dark:bg-zinc-950">

    {{-- ============ دکمه شناور تم روشن/تیره (تنها کنترل صفحه) ============ --}}
    <button type="button" x-data @click="window.toggleDarkMode()"
            class="fixed end-4 top-4 z-50 flex size-10 items-center justify-center rounded-full bg-white/85 text-zinc-500 shadow-lg shadow-zinc-900/5 ring-1 ring-zinc-200/80 backdrop-blur transition-colors hover:text-zinc-800 dark:bg-zinc-900/85 dark:text-zinc-400 dark:ring-zinc-700 dark:hover:text-amber-300"
            aria-label="تغییر پوسته روشن/تاریک" title="تغییر پوسته روشن/تاریک">
        <x-icon name="moon" class="hidden size-5 dark:block" />
        <x-icon name="sun" class="size-5 dark:hidden" />
    </button>

    {{-- ============ Content ============ --}}
    <main class="flex min-h-screen flex-col pb-6">
        {{ $slot }}
    </main>

    {{-- ============ Toasts (same system as shop/admin) ============ --}}
    <div x-data x-cloak class="fixed bottom-4 start-4 z-[70] flex w-full max-w-sm flex-col gap-2" aria-live="polite">
        <template x-for="toast in $store.toasts.items" :key="toast.id">
            <div x-transition:enter.duration.200ms x-transition:leave.duration.150ms
                 class="pointer-events-auto flex items-center gap-3 rounded-2xl bg-white p-3.5 pe-2 shadow-pop ring-1 dark:bg-zinc-800"
                 :class="toast.type === 'error'
                    ? 'ring-rose-500/20 dark:ring-rose-400/20'
                    : (toast.type === 'warning'
                        ? 'ring-amber-500/20 dark:ring-amber-400/20'
                        : 'ring-emerald-500/20 dark:ring-emerald-400/20')">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-xl"
                     :class="toast.type === 'error'
                        ? 'bg-rose-100 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400'
                        : (toast.type === 'warning'
                            ? 'bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400'
                            : 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400')">
                    <x-icon name="x-circle" class="size-5" x-show="toast.type === 'error'" />
                    <x-icon name="alert-triangle" class="size-5" x-show="toast.type === 'warning'" />
                    <x-icon name="check-circle" class="size-5" x-show="toast.type !== 'error' && toast.type !== 'warning'" />
                </div>
                <p class="flex-1 text-sm font-medium text-zinc-700 dark:text-zinc-200" x-text="toast.message"></p>
                <button type="button" @click="$store.toasts.dismiss(toast.id)" class="rounded-lg p-1.5 text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-700" aria-label="بستن">
                    <x-icon name="x" class="size-4" />
                </button>
            </div>
        </template>
    </div>

    {{-- Flash session -> toast bridge --}}
    @if(session('success') || session('error') || session('warning'))
        <div x-data x-init="$store.toasts.push(@js(session('success') ?? session('error') ?? session('warning')), @js(session('error') ? 'error' : (session('warning') ? 'warning' : 'success')))" class="hidden"></div>
    @endif

</body>
</html>
