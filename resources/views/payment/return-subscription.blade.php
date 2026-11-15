<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0a0f1c">
    <title>نتیجه خرید اشتراک | {{ config('app.name') }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --bg: #0a0f1c;
            --card: rgba(255, 255, 255, .045);
            --card-border: rgba(255, 255, 255, .09);
            --text: #f4f6fb;
            --muted: #9aa4b8;
            --chip: rgba(255, 255, 255, .055);
            --chip-border: rgba(255, 255, 255, .08);
            --ring-bg: rgba(255, 255, 255, .1);
        }

        /* ---- رنگ‌های وضعیت ---- */
        body.st-paid    { --accent: #10b981; --accent-2: #34d399; --accent-deep: #059669; --glow: rgba(16, 185, 129, .16); --glow-2: rgba(16, 185, 129, .07); }
        body.st-failed  { --accent: #f43f5e; --accent-2: #fb7185; --accent-deep: #e11d48; --glow: rgba(244, 63, 94, .14);  --glow-2: rgba(244, 63, 94, .06); }
        body.st-pending { --accent: #f59e0b; --accent-2: #fbbf24; --accent-deep: #d97706; --glow: rgba(245, 158, 11, .13); --glow-2: rgba(245, 158, 11, .05); }

        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            background:
                radial-gradient(900px 520px at 85% -5%, var(--glow), transparent 65%),
                radial-gradient(700px 480px at 0% 105%, var(--glow-2), transparent 60%),
                var(--bg);
            font-family: "Vazirmatn", "Segoe UI", Tahoma, Arial, sans-serif;
            color: var(--text);
            padding: 24px 16px 20px;
            overflow-x: hidden;
        }

        /* ---- هاله‌های پس‌زمینه ---- */
        .bg-glow {
            position: fixed; border-radius: 50%;
            pointer-events: none; z-index: 0;
            filter: blur(90px);
        }
        .bg-glow-1 { width: 340px; height: 340px; top: -120px; inset-inline-end: -100px; background: var(--glow); animation: float 11s ease-in-out infinite; }
        .bg-glow-2 { width: 260px; height: 260px; bottom: -90px; inset-inline-start: -80px; background: var(--glow-2); animation: float 13s ease-in-out infinite reverse; }
        @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(26px); } }

        /* ---- کارت ---- */
        /* margin:auto فقط وقتی فضا باشد وسط قرار می‌دهد؛ در صفحه‌های کوتاه،
           صفحه از بالا شروع و به‌طور طبیعی اسکرول می‌شود (دکمه هرگز زیر صفحه گم نمی‌شود). */
        .wrap { position: relative; z-index: 1; width: 100%; max-width: 430px; display: flex; flex-direction: column; margin-block: auto; }
        .card {
            background: linear-gradient(180deg, rgba(255,255,255,.065), rgba(255,255,255,.028));
            border: 1px solid var(--card-border);
            border-radius: 24px;
            padding: 28px 26px 24px;
            backdrop-filter: blur(22px);
            -webkit-backdrop-filter: blur(22px);
            box-shadow: 0 30px 80px rgba(0, 0, 0, .5);
            text-align: center;
            animation: card-in .55s cubic-bezier(.22, .9, .34, 1) both;
        }
        @keyframes card-in { from { opacity: 0; transform: translateY(16px) scale(.985); } to { opacity: 1; transform: none; } }

        /* ---- آیکن وضعیت ---- */
        .icon-wrap {
            position: relative;
            width: 92px; height: 92px;
            margin: 0 auto 14px;
            display: flex; align-items: center; justify-content: center;
        }
        .icon-wrap::before {
            content: ""; position: absolute; inset: -14px;
            border-radius: 50%;
            background: radial-gradient(circle, var(--glow), transparent 70%);
        }
        .pulse {
            position: absolute; inset: 0;
            border-radius: 50%;
            border: 1.5px solid var(--accent);
            opacity: 0;
            animation: pulse 2.4s ease-out infinite;
        }
        .pulse.p2 { animation-delay: 1.2s; }
        @keyframes pulse { 0% { transform: scale(.72); opacity: .55; } 100% { transform: scale(1.35); opacity: 0; } }
        .icon-svg { width: 92px; height: 92px; position: relative; }
        .ic-ring { fill: none; stroke: var(--accent); stroke-width: 3.5; stroke-linecap: round; }
        .ic-shape { fill: none; stroke: var(--accent-2); stroke-width: 5.5; stroke-linecap: round; stroke-linejoin: round; }
        .ic-bg { fill: rgba(255,255,255,.03); }
        .st-paid .ic-ring    { stroke-dasharray: 226; stroke-dashoffset: 226; animation: draw .6s ease-out .12s forwards; }
        .st-paid .ic-shape   { stroke-dasharray: 60;  stroke-dashoffset: 60;  animation: draw .4s ease-out .55s forwards; }
        .st-failed .ic-ring  { stroke-dasharray: 226; stroke-dashoffset: 226; animation: draw .6s ease-out .12s forwards; }
        .st-failed .ic-shape { stroke-dasharray: 46;  stroke-dashoffset: 46;  animation: draw .3s ease-out .55s forwards; }
        .st-pending .ic-ring { stroke-dasharray: 5 7; animation: spin 9s linear infinite; }
        .st-pending .ic-shape { stroke-dasharray: 34; stroke-dashoffset: 34; animation: draw .45s ease-out .4s forwards; }
        @keyframes draw { to { stroke-dashoffset: 0; } }
        @keyframes spin { to { transform: rotate(360deg); } }
        .st-pending .ic-ring { transform-origin: 50% 50%; }
        .st-failed .icon-wrap { animation: shake .5s ease-in-out .7s; }
        @keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-5px); } 75% { transform: translateX(5px); } }

        /* ---- تیتر و پیام ---- */
        h1 { font-size: 21px; font-weight: 800; letter-spacing: -.2px; }
        .msg {
            margin: 9px auto 0; max-width: 330px;
            font-size: 13px; line-height: 1.9; color: var(--muted);
        }

        /* ---- جزئیات ---- */
        .details {
            margin-top: 18px;
            display: grid; gap: 7px;
            text-align: start;
        }
        .row {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            background: var(--chip);
            border: 1px solid var(--chip-border);
            border-radius: 14px;
            padding: 10px 14px;
            font-size: 13px;
        }
        .row .k { color: var(--muted); flex-shrink: 0; }
        .row .v { font-weight: 700; text-align: end; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .row .v.mono { direction: ltr; font-family: ui-monospace, "Cascadia Mono", Consolas, monospace; font-size: 12.5px; letter-spacing: .4px; display: inline-flex; align-items: center; gap: 8px; }
        .row.amount { background: linear-gradient(135deg, var(--glow), rgba(255,255,255,.03)); border-color: color-mix(in srgb, var(--accent) 25%, transparent); }
        .row.amount .k, .row.amount .v { color: var(--accent-2); }
        .row.amount .v { font-size: 15px; }

        /* ---- کلید عمومی راستی‌آزمایی (پرداخت موفق) ---- */
        .keybox {
            margin-top: 16px; text-align: start;
            background: var(--chip);
            border: 1px solid var(--chip-border);
            border-radius: 16px;
            padding: 13px 14px;
            animation: card-in .55s cubic-bezier(.22, .9, .34, 1) both;
            animation-delay: .12s;
        }
        .kb-head { display: flex; align-items: center; gap: 7px; font-size: 12.5px; font-weight: 800; }
        .kb-head svg { width: 15px; height: 15px; color: var(--accent-2); flex-shrink: 0; }
        .kb-badge {
            margin-inline-start: auto;
            font-size: 9.5px; font-weight: 700;
            color: var(--accent-2);
            background: color-mix(in srgb, var(--accent) 13%, transparent);
            border: 1px solid color-mix(in srgb, var(--accent) 30%, transparent);
            padding: 2px 8px; border-radius: 999px;
            white-space: nowrap;
        }
        .kb-note { margin-top: 7px; font-size: 11px; line-height: 1.85; color: var(--muted); }
        .kb-note b { color: var(--text); font-weight: 700; }
        .kb-note.kb-how { margin-top: 5px; }
        .kb-value {
            margin-top: 9px;
            display: flex; align-items: stretch; gap: 8px;
            background: rgba(0,0,0,.28);
            border: 1px solid var(--chip-border);
            border-radius: 12px;
            padding: 9px 11px;
        }
        .kb-text {
            flex: 1; min-width: 0; max-height: 96px; overflow-y: auto;
            direction: ltr; text-align: left;
            font-family: ui-monospace, "Cascadia Mono", Consolas, monospace;
            font-size: 10px; line-height: 1.7;
            color: var(--text);
            word-break: break-all;
            user-select: all;
        }
        .kb-text::-webkit-scrollbar { width: 6px; }
        .kb-text::-webkit-scrollbar-thumb { background: rgba(255,255,255,.16); border-radius: 3px; }
        .kb-value .copy-btn { align-self: center; width: 30px; height: 30px; }
        .kb-value .copy-btn svg { width: 14px; height: 14px; }
        .kb-fp {
            margin-top: 8px;
            display: flex; align-items: center; justify-content: space-between; gap: 8px;
            font-size: 10.5px; color: var(--muted);
        }
        .kb-fp b {
            color: var(--accent-2);
            font-family: ui-monospace, Consolas, monospace;
            direction: ltr; font-weight: 600; letter-spacing: .5px;
        }

        /* ---- دکمه کپی ---- */
        .copy-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 26px; height: 26px; flex-shrink: 0;
            border: 1px solid var(--chip-border);
            border-radius: 8px;
            background: rgba(255,255,255,.04);
            color: var(--muted);
            cursor: pointer;
            transition: all .18s ease;
        }
        .copy-btn:hover { color: var(--text); background: rgba(255,255,255,.1); }
        .copy-btn svg { width: 13px; height: 13px; }
        .copy-btn .ic-check { display: none; }
        .copy-btn.copied { color: var(--accent-2); border-color: color-mix(in srgb, var(--accent) 40%, transparent); }
        .copy-btn.copied .ic-copy { display: none; }
        .copy-btn.copied .ic-check { display: block; }

        /* ---- شمارش معکوس ---- */
        .countdown { margin-top: 18px; display: flex; flex-direction: column; align-items: center; gap: 9px; }
        .count-ring-wrap { position: relative; width: 78px; height: 78px; }
        .count-ring { width: 78px; height: 78px; transform: rotate(-90deg); }
        .cr-bg { fill: none; stroke: var(--ring-bg); stroke-width: 5; }
        .cr-fg {
            fill: none; stroke: var(--accent); stroke-width: 5; stroke-linecap: round;
            transition: stroke-dashoffset 1s linear;
            filter: drop-shadow(0 0 6px var(--glow));
        }
        .count-num {
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
            font-size: 26px; font-weight: 800;
            color: var(--text);
        }
        .count-label { font-size: 12px; color: var(--muted); line-height: 1.9; }
        .count-label b { color: var(--accent-2); font-size: 13px; }
        .countdown.dim { opacity: .45; transition: opacity .3s; }
        .countdown.dim .cr-fg { filter: none; stroke: var(--ring-bg); }

        /* ---- دکمه بازگشت ---- */
        .btn-return {
            margin-top: 18px;
            display: flex; align-items: center; justify-content: center; gap: 10px;
            width: 100%; min-height: 52px;
            border-radius: 15px;
            background: linear-gradient(135deg, var(--accent), var(--accent-deep));
            color: #fff; text-decoration: none;
            font-family: inherit;
            font-size: 15px; font-weight: 800;
            box-shadow: 0 12px 34px -10px var(--glow), inset 0 1px 0 rgba(255,255,255,.22);
            transition: transform .18s ease, box-shadow .18s ease, filter .18s ease;
        }
        .btn-return:hover { transform: translateY(-2px); box-shadow: 0 18px 40px -10px var(--glow), inset 0 1px 0 rgba(255,255,255,.22); filter: brightness(1.05); }
        .btn-return:active { transform: translateY(0) scale(.985); }
        .btn-return svg { width: 19px; height: 19px; flex-shrink: 0; }
        .btn-return .host {
            direction: ltr;
            font-size: 11.5px; font-weight: 600;
            background: rgba(0,0,0,.22);
            padding: 3px 9px; border-radius: 999px;
            max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }

        .link-stop {
            margin: 10px auto 0; display: block;
            background: none; border: none; cursor: pointer;
            font-family: inherit;
            font-size: 11.5px; color: var(--muted);
            text-decoration: none;
            border-bottom: 1px dashed transparent;
            padding: 2px 4px;
            transition: color .15s ease;
        }
        .link-stop:hover { color: var(--text); }
        .link-stop.stopped { color: var(--accent-2); cursor: default; }

        /* ---- فوتر ---- */
        .foot {
            position: relative; z-index: 1;
            margin-top: 14px;
            display: flex; align-items: center; gap: 6px;
            font-size: 11px; color: rgba(154, 164, 184, .75);
        }
        .foot .dot { width: 4px; height: 4px; border-radius: 50%; background: var(--accent); opacity: .7; }

        @media (max-width: 400px) {
            .card { padding: 24px 16px 18px; border-radius: 20px; }
            .icon-wrap, .icon-svg { width: 78px; height: 78px; }
            h1 { font-size: 19px; }
            .row { padding: 9px 12px; font-size: 12.5px; }
        }

        @media (prefers-reduced-motion: reduce) {
            .card, .ic-ring, .ic-shape, .pulse, .bg-glow, .st-failed .icon-wrap { animation: none !important; }
            .ic-ring, .ic-shape { stroke-dashoffset: 0 !important; }
            .cr-fg { transition: none; }
        }
    </style>
</head>
<body class="st-{{ $status }}">

<div class="bg-glow bg-glow-1" aria-hidden="true"></div>
<div class="bg-glow bg-glow-2" aria-hidden="true"></div>

<main class="wrap">
    <article class="card">

        {{-- ============ آیکن وضعیت ============ --}}
        <div class="icon-wrap" aria-hidden="true">
            <span class="pulse p1"></span>
            <span class="pulse p2"></span>
            @if ($status === \App\Models\SubscriptionOrder::STATUS_PAID)
                <svg class="icon-svg" viewBox="0 0 80 80" fill="none">
                    <circle class="ic-bg" cx="40" cy="40" r="36"/>
                    <circle class="ic-ring" cx="40" cy="40" r="36"/>
                    <path class="ic-shape" d="M26 41.5 36 51.5 55 30"/>
                </svg>
            @elseif ($status === \App\Models\SubscriptionOrder::STATUS_FAILED)
                <svg class="icon-svg" viewBox="0 0 80 80" fill="none">
                    <circle class="ic-bg" cx="40" cy="40" r="36"/>
                    <circle class="ic-ring" cx="40" cy="40" r="36"/>
                    <path class="ic-shape" d="M29 29 51 51"/>
                    <path class="ic-shape" d="M51 29 29 51"/>
                </svg>
            @else
                <svg class="icon-svg" viewBox="0 0 80 80" fill="none">
                    <circle class="ic-bg" cx="40" cy="40" r="36"/>
                    <circle class="ic-ring" cx="40" cy="40" r="36"/>
                    <path class="ic-shape" d="M40 24 v16 l11 7"/>
                </svg>
            @endif
        </div>

        {{-- ============ تیتر ============ --}}
        @if ($status === \App\Models\SubscriptionOrder::STATUS_PAID)
            <h1>پرداخت اشتراک با موفقیت انجام شد</h1>
        @elseif ($status === \App\Models\SubscriptionOrder::STATUS_FAILED)
            <h1>پرداخت ناموفق بود</h1>
        @else
            <h1>در انتظار تأیید پرداخت</h1>
        @endif
        <p class="msg">{{ $message }}</p>

        {{-- ============ جزئیات سفارش اشتراک ============ --}}
        <section class="details" aria-label="جزئیات سفارش اشتراک">
            @if ($order->plan_name)
                <div class="row">
                    <span class="k">طرح اشتراک</span>
                    <span class="v">{{ $order->plan_name }}</span>
                </div>
            @endif
            @if ($planDuration)
                <div class="row">
                    <span class="k">مدت اعتبار اشتراک</span>
                    <span class="v">{{ $planDuration }}</span>
                </div>
            @endif
            @if ($packagesCount > 0)
                <div class="row">
                    <span class="k">پکیج‌های رایگان همراه</span>
                    <span class="v">{{ $packagesCount }}</span>
                </div>
            @endif
            @if ($gatewayName)
                <div class="row">
                    <span class="k">درگاه پرداخت</span>
                    <span class="v">{{ $gatewayName }}</span>
                </div>
            @endif
            @if ($order->transaction_id)
                <div class="row">
                    <span class="k">شماره تراکنش</span>
                    <span class="v mono">
                        {{ $order->transaction_id }}
                        <button type="button" class="copy-btn" data-copy="{{ $order->transaction_id }}" title="کپی شماره تراکنش" aria-label="کپی شماره تراکنش">
                            <svg class="ic-copy" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="12" height="12" rx="2.5"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                            <svg class="ic-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                        </button>
                    </span>
                </div>
            @endif
            <div class="row amount">
                <span class="k">مبلغ</span>
                <span class="v">{{ fa_num(money($order->final_amount)) }}</span>
            </div>
            @if ($status === \App\Models\SubscriptionOrder::STATUS_PAID)
                <div class="row">
                    <span class="k">وضعیت اشتراک</span>
                    <span class="v">در انتظار تأیید مدیر</span>
                </div>
                <div class="row">
                    <span class="k">شماره سفارش</span>
                    <span class="v mono">#{{ $order->id }}</span>
                </div>
            @endif
        </section>

        {{-- ============ کلید عمومی راستی‌آزمایی (فقط پرداخت موفق — درخواست کاربر) ============ --}}
        @if ($status === \App\Models\SubscriptionOrder::STATUS_PAID && $licenseKeyBox)
            <section class="keybox" aria-label="کلید عمومی راستی‌آزمایی لایسنس">
                <div class="kb-head">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z"/><circle cx="16.5" cy="7.5" r=".5" fill="currentColor"/></svg>
                    <span>کلید عمومی راستی‌آزمایی لایسنس</span>
                    <span class="kb-badge">برای همهٔ پکیج‌ها مشترک است</span>
                </div>
                <p class="kb-note">
                    اشتراک همهٔ پکیج‌ها را یک‌جا فعال می‌کند؛ برای فعال‌شدن‌شان همین یک کلیدِ <b>«عمومی»</b> کافی است —
                    <b>برای همهٔ پکیج‌ها، اشتراک‌ها و مشتری‌ها یکی است</b> و راز محسوب نمی‌شود.
                    کلید خصوصیِ سرور هرگز نمایش داده نمی‌شود.
                </p>
                <p class="kb-note kb-how">
                    در پنل فروشگاه: <b>تنظیمات برنامه‌نویس ← تب «آپدیت‌کننده» ← «کلید عمومی راستی‌آزمایی لایسنس»</b>
                    مقدار زیر را کامل بچسبانید و ذخیره کنید (یا در فایل <b dir="ltr">.env</b> فروشگاه قرار دهید).
                    اگر این مرحله انجام نشود، نصب و فعال‌سازی پکیج‌های اشتراک ممکن نمی‌شود.
                </p>
                <div class="kb-value">
                    <div class="kb-text" dir="ltr" tabindex="0">{{ $licenseKeyBox['env_line'] }}</div>
                    <button type="button" class="copy-btn" data-copy="{{ $licenseKeyBox['env_line'] }}" title="کپی کلید عمومی" aria-label="کپی کلید عمومی راستی‌آزمایی">
                        <svg class="ic-copy" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="12" height="12" rx="2.5"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        <svg class="ic-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                    </button>
                </div>
                @if ($licenseKeyBox['fingerprint'])
                    <p class="kb-fp">
                        <span>اثر انگشت کلید (SHA-256) — برای تطبیق با پنل فروشگاه:</span>
                        <b>{{ $licenseKeyBox['fingerprint'] }}</b>
                    </p>
                @endif
            </section>
        @endif

        {{-- ============ شمارش معکوس ============ --}}
        <section class="countdown" id="countdown" aria-live="polite">
            <div class="count-ring-wrap">
                <svg class="count-ring" viewBox="0 0 96 96" aria-hidden="true">
                    <circle class="cr-bg" cx="48" cy="48" r="42"/>
                    <circle class="cr-fg" id="crFg" cx="48" cy="48" r="42"/>
                </svg>
                <span class="count-num" id="crNum">{{ fa_num($seconds) }}</span>
            </div>
            <p class="count-label" id="countLabel">
                {{ $isInternal ? 'انتقال خودکار تا' : 'بازگشت خودکار به فروشگاه تا' }}
                <b id="crSecs">{{ fa_num($seconds) }}</b>
                ثانیه دیگر…
            </p>
        </section>

        {{-- ============ دکمه بازگشت ============ --}}
        <a class="btn-return" id="btnReturn" href="{{ $returnUrl }}">
            @if ($isInternal)
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
                <span>مشاهده نتیجه اشتراک</span>
            @else
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
                <span>بازگشت به فروشگاه</span>
                @if ($returnHost)
                    <span class="host">{{ $returnHost }}</span>
                @endif
            @endif
        </a>

        <button class="link-stop" id="btnStop" type="button">توقف شمارش خودکار</button>
    </article>

    <footer class="foot">
        <span class="dot" aria-hidden="true"></span>
        <span>{{ config('app.name') }}</span>
    </footer>
</main>

<script>
    (function () {
        var RETURN_URL = @json($returnUrl);
        var TOTAL = {{ (int) $seconds }};
        var left = TOTAL;
        var stopped = false;
        var timer = null;

        var numEl = document.getElementById('crNum');
        var secsEl = document.getElementById('crSecs');
        var fg = document.getElementById('crFg');
        var countdown = document.getElementById('countdown');
        var label = document.getElementById('countLabel');
        var C = 2 * Math.PI * 42;

        fg.style.strokeDasharray = C;
        fg.style.strokeDashoffset = 0;

        function fa(n) {
            return String(n).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[+d]; });
        }

        function paint() {
            numEl.textContent = fa(left);
            secsEl.textContent = fa(left);
            fg.style.strokeDashoffset = C * (1 - left / TOTAL);
        }

        function go() {
            window.location.replace(RETURN_URL);
        }

        function stopCountdown(text) {
            stopped = true;
            clearInterval(timer);
            var btn = document.getElementById('btnStop');
            if (btn) {
                btn.textContent = text || 'شمارش متوقف شد';
                btn.classList.add('stopped');
                btn.disabled = true;
            }
            if (countdown) countdown.classList.add('dim');
        }

        paint();
        timer = setInterval(function () {
            if (stopped) return;
            left--;
            if (left <= 0) {
                clearInterval(timer);
                numEl.textContent = fa(0);
                secsEl.textContent = fa(0);
                fg.style.strokeDashoffset = C;
                go();
                return;
            }
            paint();
        }, 1000);

        var btnReturn = document.getElementById('btnReturn');
        if (btnReturn) btnReturn.addEventListener('click', function () { stopCountdown('در حال انتقال…'); });

        var btnStop = document.getElementById('btnStop');
        if (btnStop) btnStop.addEventListener('click', function () { stopCountdown(); });

        /* ---- کپی در کلیپ‌بورد ---- */
        function fallbackCopy(text, done) {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.top = '-100px';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); done(); } catch (e) {}
            document.body.removeChild(ta);
        }

        document.querySelectorAll('[data-copy]').forEach(function (b) {
            b.addEventListener('click', function () {
                var text = b.getAttribute('data-copy');
                function done() {
                    b.classList.add('copied');
                    b.title = 'کپی شد';
                    setTimeout(function () {
                        b.classList.remove('copied');
                        b.title = 'کپی';
                    }, 1800);
                }
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(done, function () { fallbackCopy(text, done); });
                } else {
                    fallbackCopy(text, done);
                }
            });
        });
    })();
</script>
</body>
</html>
