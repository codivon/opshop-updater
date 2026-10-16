{{--
    Public storefront layout (فروشگاه عمومی).
    Used by Livewire page components:  #[Layout('components.layouts.shop')]
--}}
@props(['title' => null])

<!DOCTYPE html>
<html lang="fa" dir="rtl" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'فروشگاه پکیج‌ها' }} · آپدیت‌سنتر</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script>
        // Apply theme before first paint to avoid a flash.
        window.initDarkMode && window.initDarkMode();
    </script>

    {{--
        Storefront-only styles: گرادیان‌های اختصاصی فروشگاه.
        (تمام utilityهای Tailwind استفاده‌شده در این لایوت/صفحات فروشگاه در باندل CSS موجودند؛
        این بلوک فقط کلاس‌های سفارشی non-Tailwind را تعریف می‌کند.)
    --}}
    <style>
        /* ---------- hero gradients ---------- */
        .shop-hero {
            background-image: linear-gradient(135deg, #0d9488 0%, #0f766e 42%, #18181b 100%);
        }
        .shop-hero-success {
            background-image: linear-gradient(135deg, #059669 0%, #047857 45%, #064e3b 100%);
        }
        .shop-hero-fail {
            background-image: linear-gradient(135deg, #e11d48 0%, #be123c 45%, #4c0519 100%);
        }
        .shop-hero-pending {
            background-image: linear-gradient(135deg, #f59e0b 0%, #d97706 45%, #78350f 100%);
        }
        .shop-hero-glow {
            background: radial-gradient(closest-side, rgba(45, 212, 191, 0.35), transparent);
        }
        .shop-hero-title {
            background-image: linear-gradient(to bottom, #ffffff 30%, rgba(153, 246, 228, 0.85));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        /* ---------- gradient placeholder for package cards ---------- */
        .shop-thumb {
            background-image: linear-gradient(135deg, #0d9488 0%, #10b981 55%, #047857 100%);
        }
    </style>
</head>

<body class="flex min-h-screen flex-col bg-zinc-50 antialiased dark:bg-zinc-950">

    {{-- ============ Header (sticky) ============ --}}
    <header class="sticky top-0 z-40 border-b border-zinc-200/70 bg-white/80 backdrop-blur-md dark:border-zinc-800 dark:bg-zinc-950/80">
        <div class="mx-auto flex h-16 w-full max-w-7xl items-center gap-3 px-4 sm:px-6">
            {{-- brand --}}
            <a href="{{ route('shop.home') }}" wire:navigate class="flex items-center gap-2.5">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-brand-400 to-brand-600 text-white shadow-lg shadow-pop">
                    <x-icon name="rocket" class="size-5" />
                </span>
                <span class="hidden text-sm font-black text-zinc-900 sm:block dark:text-zinc-50">آپدیت‌سنتر</span>
            </a>

            <span class="hidden h-6 w-px bg-zinc-200 sm:block dark:bg-zinc-800" aria-hidden="true"></span>

            {{-- nav --}}
            <nav class="flex items-center gap-1">
                <a href="{{ route('shop.home') }}" wire:navigate
                   @class([
                       'flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium transition-colors',
                       'bg-brand-500/10 text-brand-600 dark:text-brand-400' => request()->routeIs('shop.home') || request()->routeIs('shop.package'),
                       'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100' => ! (request()->routeIs('shop.home') || request()->routeIs('shop.package')),
                   ])
                   @if(request()->routeIs('shop.home') || request()->routeIs('shop.package') || request()->routeIs('shop.product')) aria-current="page" @endif>
                    <x-icon name="package" class="size-4.5" />
                    فروشگاه
                </a>
                <a href="{{ route('shop.home') }}?tab=products" wire:navigate
                   @class([
                       'flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium transition-colors',
                       'bg-brand-500/10 text-brand-600 dark:text-brand-400' => request()->routeIs('shop.product'),
                       'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100' => ! request()->routeIs('shop.product'),
                   ])
                   @if(request()->routeIs('shop.product')) aria-current="page" @endif>
                    <x-icon name="rocket" class="size-4.5" />
                    پروژه‌ها
                </a>
                <a href="{{ route('shop.subscriptions') }}" wire:navigate
                   @class([
                       'flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium transition-colors',
                       'bg-brand-500/10 text-brand-600 dark:text-brand-400' => request()->routeIs('shop.subscriptions') || request()->routeIs('subscription.*'),
                       'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100' => ! (request()->routeIs('shop.subscriptions') || request()->routeIs('subscription.*')),
                   ])
                   @if(request()->routeIs('shop.subscriptions') || request()->routeIs('subscription.*')) aria-current="page" @endif>
                    <x-icon name="crown" class="size-4.5" />
                    طرح‌های اشتراک
                </a>
            </nav>

            <div class="flex-1"></div>

            {{-- FIX (دکمه تم صفحه اصلی کار نمی‌کرد): @click فقط داخل یک کامپوننت Alpine
                 (x-data) پردازش می‌شود؛ body فروشگاه x-data ندارد → دکمه مرده بود.
                 x-data روی هدر این مشکل را رفع می‌کند. --}}
            {{-- dark mode --}}
            <button type="button" x-data @click="window.toggleDarkMode()"
                    class="rounded-xl p-2 text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-amber-300"
                    aria-label="تغییر پوسته روشن/تاریک">
                <x-icon name="moon" class="hidden size-5 dark:block" />
                <x-icon name="sun" class="size-5 dark:hidden" />
            </button>

            {{-- admin login --}}
            <a href="{{ route('login') }}" wire:navigate
               class="flex items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-medium text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
               title="ورود به پنل مدیریت">
                <x-icon name="log-in" class="size-4" />
                <span class="hidden sm:block">ورود مدیر</span>
            </a>
        </div>
    </header>

    {{-- ============ Content ============ --}}
    <main class="flex-1">
        {{ $slot }}
    </main>

    {{-- ============ Footer ============ --}}
    <footer class="mt-auto border-t border-zinc-200/70 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="mx-auto flex w-full max-w-7xl flex-col items-center justify-between gap-3 px-4 py-6 text-center sm:flex-row sm:text-start sm:px-6">
            <div class="flex items-center gap-2.5">
                <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-brand-400 to-brand-600 text-white">
                    <x-icon name="rocket" class="size-4" />
                </span>
                <div>
                    <p class="text-xs font-black text-zinc-800 dark:text-zinc-100">آپدیت‌سنتر</p>
                    <p class="mt-0.5 text-xs leading-none text-zinc-400">مرکز توزیع پکیج و آپدیت پروژه‌ها</p>
                </div>
            </div>
            <p class="text-xs text-zinc-400">
                © {{ fa_num(verta_date(now(), 'Y')) }} آپدیت‌سنتر · تمامی حقوق محفوظ است.
            </p>
        </div>
    </footer>

    {{-- ============ Toasts (same system as admin; icon/color per type) ============ --}}
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
