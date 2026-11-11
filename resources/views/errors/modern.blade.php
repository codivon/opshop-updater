{{--
    لایوت مدرن صفحات خطا (Task 73) — 403/404/500/503/419/429
    ✅ کاملاً مستقل: بدون @vite، بدون کامپوننت Blade، بدون کوئری دیتابیس —
       حتی وقتی اپ آسیب‌دیده (مثلاً کش پیکربندی خراب) هم رندر می‌شود.
    ✅ RTL فارسی + فونت وزیرمتن از public/fonts (fallback: فونت سیستمی)
    ✅ حالت تاریک خودکار با prefers-color-scheme
    ✅ انیمیشن ورود ملایم با احترام به prefers-reduced-motion

    استفاده:
        @include('errors.modern', [
            'code' => '۴۰۴',
            'icon' => 'compass',        // compass | lock | crash | wrench | clock | shield
            'title' => 'صفحه پیدا نشد',
            'message' => 'توضیح فارسی…',
            'tone' => 'brand',          // brand | rose | amber | sky
        ])
--}}
@php($code ??= 'خطا')
@php($icon ??= 'compass')
@php($title ??= 'خطایی رخ داد')
@php($message ??= 'لطفاً دوباره تلاش کنید.')
@php($tone ??= 'brand')
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $code }} · {{ $title }}</title>
    <meta name="robots" content="noindex">
    <style>
        @font-face {
            font-family: "Vazirmatn";
            src: url("/fonts/Vazirmatn%5Bwght%5D.woff2") format("woff2-variations"),
                 url("/fonts/Vazirmatn-Regular.woff2") format("woff2");
            font-weight: 100 900;
            font-display: swap;
        }

        :root {
            --bg: #fafafa;
            --card: #ffffff;
            --text: #18181b;
            --muted: #71717a;
            --ring: rgba(24, 24, 27, .08);
            --btn2-bg: #ffffff;
            --btn2-text: #3f3f46;
            color-scheme: light;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #09090b;
                --card: #101013;
                --text: #fafafa;
                --muted: #a1a1aa;
                --ring: rgba(250, 250, 250, .1);
                --btn2-bg: rgba(255, 255, 255, .04);
                --btn2-text: #d4d4d8;
                color-scheme: dark;
            }
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        html, body { height: 100%; }

        body {
            font-family: "Vazirmatn", "Segoe UI", Tahoma, ui-sans-serif, system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100svh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* ---------- حباب‌های تزئینی پس‌زمینه ---------- */
        .blob {
            position: fixed;
            border-radius: 9999px;
            filter: blur(90px);
            opacity: .16;
            pointer-events: none;
            z-index: 0;
        }

        .tone-brand  .blob-1 { background: #10b981; }
        .tone-rose   .blob-1 { background: #f43f5e; }
        .tone-amber  .blob-1 { background: #f59e0b; }
        .tone-sky    .blob-1 { background: #0ea5e9; }
        .tone-brand .blob-2, .tone-sky .blob-2 { background: #14b8a6; }
        .tone-rose .blob-2 { background: #a855f7; }
        .tone-amber .blob-2 { background: #ef4444; }

        .blob-1 { inline-size: 420px; block-size: 420px; top: -120px; inset-inline-start: -100px; }
        .blob-2 { inline-size: 360px; block-size: 360px; bottom: -120px; inset-inline-end: -80px; opacity: .12; }

        /* ---------- کارت ---------- */
        .card {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 480px;
            background: var(--card);
            border: 1px solid var(--ring);
            border-radius: 28px;
            padding: 44px 32px 36px;
            text-align: center;
            box-shadow: 0 24px 70px -30px rgba(0, 0, 0, .25);
            animation: rise .5s cubic-bezier(.2, .7, .3, 1) both;
        }

        @keyframes rise {
            from { opacity: 0; transform: translateY(18px) scale(.98); }
            to   { opacity: 1; transform: none; }
        }

        @media (prefers-reduced-motion: reduce) {
            .card { animation: none; }
        }

        /* ---------- نشان کد خطا ---------- */
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 800;
            padding: 6px 14px;
            border-radius: 9999px;
            border: 1px solid var(--ring);
            color: var(--muted);
            margin-bottom: 18px;
        }

        .pill .dot {
            inline-size: 7px; block-size: 7px; border-radius: 9999px;
        }

        .tone-brand .pill .dot { background: #10b981; }
        .tone-rose  .pill .dot { background: #f43f5e; }
        .tone-amber .pill .dot { background: #f59e0b; }
        .tone-sky   .pill .dot { background: #0ea5e9; }

        /* ---------- کد بزرگ ---------- */
        .code {
            font-size: 96px;
            font-weight: 900;
            line-height: 1;
            letter-spacing: -.02em;
            margin-bottom: 6px;
        }

        .tone-brand .code { background: linear-gradient(135deg, #059669, #2dd4bf); }
        .tone-rose  .code { background: linear-gradient(135deg, #e11d48, #a855f7); }
        .tone-amber .code { background: linear-gradient(135deg, #d97706, #f43f5e); }
        .tone-sky   .code { background: linear-gradient(135deg, #0284c7, #14b8a6); }

        .tone-brand .code, .tone-rose .code, .tone-amber .code, .tone-sky .code {
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        @media (max-width: 480px) { .code { font-size: 72px; } }

        /* ---------- آیکون ---------- */
        .icon-wrap {
            inline-size: 64px; block-size: 64px;
            margin: 0 auto 18px;
            border-radius: 20px;
            display: flex; align-items: center; justify-content: center;
        }

        .tone-brand .icon-wrap { background: rgba(16, 185, 129, .12); color: #059669; }
        .tone-rose  .icon-wrap { background: rgba(244, 63, 94, .12);  color: #e11d48; }
        .tone-amber .icon-wrap { background: rgba(245, 158, 11, .14); color: #d97706; }
        .tone-sky   .icon-wrap { background: rgba(14, 165, 233, .12); color: #0284c7; }

        @media (prefers-color-scheme: dark) {
            .tone-brand .icon-wrap { color: #34d399; }
            .tone-rose  .icon-wrap { color: #fb7185; }
            .tone-amber .icon-wrap { color: #fbbf24; }
            .tone-sky   .icon-wrap { color: #38bdf8; }
        }

        .icon-wrap svg { inline-size: 30px; block-size: 30px; }

        /* ---------- متن ---------- */
        h1 { font-size: 22px; font-weight: 900; margin-bottom: 10px; }

        p.desc {
            font-size: 14px;
            line-height: 2;
            color: var(--muted);
            margin-bottom: 28px;
            overflow-wrap: anywhere;
        }

        /* ---------- دکمه‌ها ---------- */
        .actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 22px;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 800;
            font-family: inherit;
            text-decoration: none;
            cursor: pointer;
            transition: transform .15s ease, opacity .15s ease, background .15s ease;
        }

        .btn:active { transform: scale(.96); }

        .tone-brand .btn-primary { background: linear-gradient(135deg, #059669, #10b981); color: #fff; }
        .tone-rose  .btn-primary { background: linear-gradient(135deg, #e11d48, #f43f5e); color: #fff; }
        .tone-amber .btn-primary { background: linear-gradient(135deg, #d97706, #f59e0b); color: #fff; }
        .tone-sky   .btn-primary { background: linear-gradient(135deg, #0284c7, #0ea5e9); color: #fff; }

        .btn-primary:hover { opacity: .9; }

        .btn-secondary {
            background: var(--btn2-bg);
            color: var(--btn2-text);
            border: 1px solid var(--ring);
        }

        .btn-secondary:hover { opacity: .75; }

        footer {
            margin-top: 26px;
            font-size: 11.5px;
            color: var(--muted);
        }
    </style>
</head>
<body class="tone-{{ $tone }}">

    <div class="blob blob-1" aria-hidden="true"></div>
    <div class="blob blob-2" aria-hidden="true"></div>

    <main class="card" role="main">
        <span class="pill"><span class="dot"></span> خطای {{ $code }}</span>

        <div class="icon-wrap" aria-hidden="true">
            @if($icon === 'lock')
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            @elseif($icon === 'crash')
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="8" x="2" y="2" rx="2" ry="2"/><rect width="20" height="8" x="2" y="14" rx="2" ry="2"/><line x1="6" x2="6.01" y1="6" y2="6"/><line x1="6" x2="6.01" y1="18" y2="18"/></svg>
            @elseif($icon === 'wrench')
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
            @elseif($icon === 'clock')
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            @elseif($icon === 'shield')
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1 1 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></svg>
            @else
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
            @endif
        </div>

        <div class="code">{{ $code }}</div>
        <h1>{{ $title }}</h1>
        <p class="desc">{{ $message }}</p>

        <div class="actions">
            <a href="/" class="btn btn-primary">
                بازگشت به صفحهٔ اصلی
            </a>
            <a href="/login" class="btn btn-secondary">
                ورود به پنل
            </a>
        </div>

        <footer>آپدیت‌سنتر · سامانه به‌روزرسانی و فروش محصولات وب</footer>
    </main>
</body>
</html>
