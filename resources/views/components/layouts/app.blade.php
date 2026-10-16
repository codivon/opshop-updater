{{--
    App shell – sidebar + topbar + content slot.
    Used by Livewire page components:  #[Layout('components.layouts.app')]
--}}
@props(['title' => null])

@php
    $nav = [
        [
            'label' => null,
            'items' => [
                ['route' => 'admin.dashboard', 'title' => 'داشبورد', 'icon' => 'layout-dashboard', 'match' => ['admin.dashboard']],
            ],
        ],
        [
            'label' => 'سیستم آپدیت',
            'items' => [
                ['route' => 'admin.projects.index', 'title' => 'پروژه‌ها', 'icon' => 'folder', 'match' => ['admin.projects*']],
                ['route' => 'admin.updates.index', 'title' => 'آپدیت‌ها', 'icon' => 'git-branch', 'match' => ['admin.updates*']],
                ['route' => 'admin.packages.index', 'title' => 'پکیج‌ها', 'icon' => 'package', 'match' => ['admin.packages.index', 'admin.packages.show']],
            ],
        ],
        [
            'label' => 'فروشگاه',
            'items' => [
                ['route' => 'admin.products.index', 'title' => 'محصولات (پروژه‌ها)', 'icon' => 'rocket', 'match' => ['admin.products*']],
                ['route' => 'admin.categories.index', 'title' => 'دسته‌بندی‌ها', 'icon' => 'tag', 'match' => ['admin.categories*']],
                ['route' => 'admin.comments.index', 'title' => 'دیدگاه‌ها', 'icon' => 'message-square', 'match' => ['admin.comments*'], 'badge' => \App\Models\Comment::pendingCount()],
            ],
        ],
        [
            'label' => 'فروش و مشتریان',
            'items' => [
                ['route' => 'admin.licenses.index', 'title' => 'لایسنس‌ها', 'icon' => 'key', 'match' => ['admin.licenses*']],
                ['route' => 'admin.purchases.index', 'title' => 'خریدها', 'icon' => 'receipt', 'match' => ['admin.purchases*']],
                ['route' => 'admin.customers.index', 'title' => 'مشتریان', 'icon' => 'users', 'match' => ['admin.customers*']],
                ['route' => 'admin.subscriptions.index', 'title' => 'اشتراک‌ها', 'icon' => 'ticket', 'match' => ['admin.subscriptions.index']],
                ['route' => 'admin.plans.index', 'title' => 'طرح‌های اشتراک', 'icon' => 'crown', 'match' => ['admin.plans.index']],
                ['route' => 'admin.subscriptions.orders', 'title' => 'درخواست‌های اشتراک', 'icon' => 'hourglass', 'match' => ['admin.subscriptions.orders'], 'badge' => \App\Services\SubscriptionService::pendingApprovalsCount()],
            ],
        ],
        [
            'label' => 'سیستم',
            'items' => [
                ['route' => 'admin.reports.index', 'title' => 'گزارش‌ها', 'icon' => 'bar-chart', 'match' => ['admin.reports*']],
                ['route' => 'admin.logs.index', 'title' => 'لاگ‌ها', 'icon' => 'terminal', 'match' => ['admin.logs*']],
                ['route' => 'admin.settings.index', 'title' => 'تنظیمات', 'icon' => 'settings', 'match' => ['admin.settings*']],
                ['route' => 'admin.users.index', 'title' => 'کاربران', 'icon' => 'shield-check', 'match' => ['admin.users*']],
            ],
        ],
        [
            'label' => 'اطلاع‌رسانی',
            'items' => [
                ['route' => 'admin.sms.templates', 'title' => 'قالب‌های پیامک', 'icon' => 'message-square', 'match' => ['admin.sms.templates']],
                ['route' => 'admin.sms.logs', 'title' => 'لاگ پیامک‌ها', 'icon' => 'send', 'match' => ['admin.sms.logs']],
            ],
        ],
    ];

    $pageTitle = $title ?? 'پنل مدیریت';
@endphp

<!DOCTYPE html>
<html lang="fa" dir="rtl" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }} · {{ config('app.name', 'پنل مدیریت') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script>
        // Apply theme before first paint to avoid a flash.
        window.initDarkMode && window.initDarkMode();
    </script>
</head>

<body class="min-h-screen antialiased" x-data="shell">
<div class="flex min-h-screen">

    {{-- ============ Sidebar ============ --}}
    <aside
        {{-- FIX (منوی موبایل وسط صفحه): در RTL سایدبار به لبه راست (start) چسبیده است؛
             translate-x فیزیکی است، پس حالت مخفی باید +100% (بیرون از لبه راست) باشد نه -100%
             که منو را وسط صفحه می‌آورد. transition باید شامل translate باشد (نه فقط transform). --}}
        :class="[
            collapsed ? 'lg:w-[76px]' : 'lg:w-64',
            mobileOpen ? 'translate-x-0' : 'translate-x-full lg:translate-x-0',
        ]"
        class="fixed inset-y-0 start-0 z-40 flex w-64 shrink-0 flex-col border-e border-white/5 bg-zinc-950 text-zinc-400 transition-[width,translate,transform] duration-200
               lg:sticky lg:top-0 lg:h-screen"
    >
        {{-- brand --}}
        <div class="flex h-16 shrink-0 items-center gap-3 border-b border-white/5 px-4">
            <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-brand-400 to-brand-600 text-white shadow-lg shadow-brand-500/20">
                <x-icon name="rocket" class="size-5" />
            </div>
            <div x-show="! collapsed" x-cloak class="min-w-0" x-transition.opacity.duration.150ms>
                <p class="truncate text-sm font-black text-white">آپدیت‌سنتر</p>
                <p class="truncate text-[11px] text-zinc-500">مدیریت پروژه و پکیج</p>
            </div>
        </div>

        {{-- nav --}}
        <nav class="flex-1 space-y-5 overflow-y-auto overflow-x-hidden px-3 py-4">
            @foreach($nav as $group)
                <div>
                    @if($group['label'])
                        <p x-show="! collapsed" x-cloak class="px-2.5 pb-2 text-[10px] font-bold tracking-widest text-zinc-600 uppercase">{{ $group['label'] }}</p>
                    @endif
                    <div class="space-y-1">
                        @foreach($group['items'] as $item)
                            @php($active = request()->routeIs(...$item['match']))
                            <a href="{{ route($item['route']) }}" wire:navigate
                               title="{{ $item['title'] }}"
                               @click="mobileOpen = false"
                               @class([
                                   'group relative flex items-center gap-3 rounded-xl px-2.5 py-2.5 text-sm font-medium transition-all duration-150',
                                   'bg-brand-500/10 text-brand-400' => $active,
                                   'hover:bg-white/5 hover:text-zinc-100' => ! $active,
                               ])
                               @if($active) aria-current="page" @endif>
                                @if($active)
                                    <span class="absolute end-0 top-1/2 h-5 w-1 -translate-y-1/2 rounded-full bg-brand-400"></span>
                                @endif
                                <x-icon :name="$item['icon']"
                                        class="size-5 shrink-0 transition-transform duration-150 group-hover:scale-110 {{ $active ? 'text-brand-400' : '' }}"/>
                                <span x-show="! collapsed" x-cloak class="truncate">{{ $item['title'] }}</span>
                                @if(($item['badge'] ?? 0) > 0)
                                    <span class="ms-auto flex size-5 shrink-0 items-center justify-center rounded-full bg-rose-500 text-[10px] font-black text-white"
                                          x-show="! collapsed">{{ fa_num($item['badge']) }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>

        {{-- footer: user --}}
        <div class="shrink-0 border-t border-white/5 p-3">
            <div class="flex items-center gap-3 rounded-xl p-2 transition-colors hover:bg-white/5">
                <x-avatar :name="auth()->user()?->name ?? 'مدیر'" size="sm" />
                <div x-show="! collapsed" x-cloak class="min-w-0 flex-1">
                    <p class="truncate text-xs font-bold text-zinc-100">{{ auth()->user()?->name ?? 'مدیر سیستم' }}</p>
                    <p class="truncate text-[10px] text-zinc-500">{{ auth()->user()?->email }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}" x-show="! collapsed" x-cloak>
                    @csrf
                    <button type="submit" class="rounded-lg p-2 text-zinc-500 transition-colors hover:bg-rose-500/10 hover:text-rose-400" title="خروج" aria-label="خروج از حساب">
                        <x-icon name="log-out" class="size-4.5" />
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- mobile backdrop --}}
    <div x-show="mobileOpen" x-cloak x-transition.opacity.duration.200ms @click="mobileOpen = false"
         class="fixed inset-0 z-30 bg-zinc-950/50 backdrop-blur-sm lg:hidden" aria-hidden="true"></div>

    {{-- ============ Main ============ --}}
    <div class="flex min-w-0 flex-1 flex-col">

        {{-- Topbar --}}
        <header class="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-2 border-b border-zinc-200/70 bg-white/80 px-4 backdrop-blur-md dark:border-zinc-800 dark:bg-zinc-950/80 sm:px-6">
            {{-- mobile menu --}}
            <button type="button" @click="mobileOpen = true"
                    class="rounded-xl p-2 text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-800 dark:hover:bg-zinc-800 dark:hover:text-zinc-100 lg:hidden"
                    aria-label="باز کردن منو">
                <x-icon name="menu" class="size-5" />
            </button>

            {{-- collapse (desktop) --}}
            <button type="button" @click="collapsed = ! collapsed"
                    class="hidden rounded-xl p-2 text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200 lg:block"
                    :title="collapsed ? 'باز کردن منو' : 'جمع کردن منو'">
                <x-icon name="arrow-up-down" class="size-4.5" />
            </button>

            <h1 class="truncate text-base font-bold text-zinc-800 dark:text-zinc-100 sm:text-lg">{{ $pageTitle }}</h1>

            <div class="flex-1"></div>

            {{-- dark mode --}}
            <button type="button" @click="window.toggleDarkMode()"
                    class="rounded-xl p-2 text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-amber-300"
                    aria-label="تغییر پوسته روشن/تاریک">
                <x-icon name="moon" class="size-5 hidden dark:block" />
                <x-icon name="sun" class="size-5 dark:hidden" />
            </button>

            {{-- user dropdown --}}
            <x-dropdown align="end" width="w-60">
                <x-slot:trigger>
                    <button type="button" class="flex items-center gap-2 rounded-xl p-1.5 transition-colors hover:bg-zinc-100 dark:hover:bg-zinc-800" aria-label="حساب کاربری">
                        <x-avatar :name="auth()->user()?->name ?? 'مدیر'" size="sm" />
                        <x-icon name="chevron-down" class="hidden size-4 text-zinc-400 sm:block" />
                    </button>
                </x-slot:trigger>
                <x-slot:menu>
                    <div class="border-b border-zinc-100 px-3 py-2.5 dark:border-zinc-700">
                        <p class="truncate text-sm font-bold text-zinc-800 dark:text-zinc-100">{{ auth()->user()?->name ?? 'مدیر سیستم' }}</p>
                        <p class="truncate text-xs text-zinc-500">{{ auth()->user()?->email }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="pt-1">
                        @csrf
                        <button type="submit" class="flex w-full cursor-pointer items-center gap-2.5 rounded-lg px-3 py-2 text-start text-sm font-medium text-rose-600 transition-colors hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10">
                            <x-icon name="log-out" class="size-4" />
                            خروج از حساب
                        </button>
                    </form>
                </x-slot:menu>
            </x-dropdown>
        </header>

        {{-- Content --}}
        <main class="flex-1">
            <div class="mx-auto w-full max-w-7xl p-4 fade-enter sm:p-6 lg:p-8">
                {{ $slot }}
            </div>
        </main>

        {{-- Footer --}}
        <footer class="mt-auto border-t border-zinc-200/70 py-4 text-center text-xs text-zinc-400 dark:border-zinc-800 dark:text-zinc-500">
            {{ config('app.name') }} · ساخته شده با <span class="text-rose-400">♥</span> — نسخه Livewire
        </footer>
    </div>
</div>

{{-- ============ Toasts ============ --}}
<div x-data x-cloak class="fixed bottom-4 start-4 z-[70] flex w-full max-w-sm flex-col gap-2" aria-live="polite">
    <template x-for="toast in $store.toasts.items" :key="toast.id">
        <div x-transition:enter.duration.200ms x-transition:leave.duration.150ms
             class="pointer-events-auto flex items-center gap-3 rounded-2xl bg-white p-3.5 pe-2 shadow-pop ring-1
                    ring-emerald-500/20 dark:bg-zinc-800 dark:ring-emerald-400/20">
            <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400">
                <x-icon name="check-circle" class="size-5" />
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
