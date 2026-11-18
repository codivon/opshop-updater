{{--
    کپچای چهاردرایوره — بسته به درایورِ نقطه از پنل «امنیت و امکانات» رندر می‌شود:

      math      → تصویر PNG سؤال ریاضی (پیش‌فرض؛ خودمیزبان، سازگار با اینترنت ایران)
      image     → تصویر PNG حرف‌عددی (۵ نویسهٔ بی‌ابهام)
      hcaptcha  → ویجت ابری hCaptcha (اسکریپت رسمی + توکن مخفی به فرم/Livewire)
      turnstile → ویجت ابری Cloudflare Turnstile (اسکریپت رسمی + توکن مخفی)

    درایور ابری بدون کلید سایت/رمز → خودکار به math فال‌بک می‌شود (Features::captchaDriver).

    استفاده در کامپوننت Livewire (با متد refreshCaptcha() روی همان کامپوننت):
        <x-captcha spot="customer_login" wireTarget="captcha" refreshWire="refreshCaptcha" />

    استفاده در فرم کلاسیک HTTP (ورود/ثبت‌نام مدیر):
        <x-captcha spot="admin_login" name="captcha" />
--}}
@php
    /** @var string $spot نقطهٔ کپچا برای خواندن درایور از پنل */
    $spot  = $spot ?? 'generic';
    $driver = \App\Support\Features::captchaDriver($spot);

    /** @var string $name نام فیلد در فرم کلاسیک + کلید خطا */
    $name       = $name ?? 'captcha';
    $wireTarget = $wireTarget ?? null;  // اگر ست شود، input با wire:model به این پراپرتی بسته می‌شود
    $refreshWire = $refreshWire ?? null; // متد عمومی کامپوننت Livewire برای تازه‌سازی سؤال
    $label      = $label ?? 'سؤال امنیتی';

    // شناسهٔ یکتا برای ویجت ابری (چند ویجت در یک صفحه ممکن است)
    $uid = substr(md5($spot.'|'.$name.'|'.uniqid('', true)), 0, 10);

    $isCloud = in_array($driver, ['hcaptcha', 'turnstile'], true);

    $cloudSite = $driver === 'hcaptcha'
        ? \App\Support\Features::captchaCloudSite('hcaptcha')
        : ($driver === 'turnstile' ? \App\Support\Features::captchaCloudSite('turnstile') : '');

    // نام فیلد توکن برای فرم کلاسیک یا binding به Livewire
    $inputAttrs = $wireTarget
        ? 'wire:model="'.e($wireTarget).'"'
        : 'name="'.e($name).'" required';

    // Task 93 — جداسازی سشن مدیر/مشتری: اندپوینت‌های کپچا (تصویر/رفرش) باید
    // کوکی سشنِ «همان سمت» فرم را بگیرند تا سؤال و پاسخ در یک سشن بمانند
    $version = \App\Services\CaptchaService::version();
    $captchaCtx = in_array($spot, ['admin_login', 'admin_register'], true) ? 'admin' : 'shop';
    $captchaImgUrl = route('captcha.image').'?ctx='.$captchaCtx.'&v='.$version;
    $captchaRefreshUrl = route('captcha.refresh', ['spot' => $spot, 'ctx' => $captchaCtx]);
@endphp

<div {{ $attributes->merge(['class' => 'space-y-1.5']) }}>
    {{-- ================= ویجت ابری (hCaptcha / Turnstile) ================= --}}
    @if($isCloud)
        <div class="flex items-center justify-between gap-2 rounded-xl bg-zinc-50 px-3.5 py-2.5 ring-1 ring-zinc-200/70 dark:bg-zinc-800/60 dark:ring-zinc-700/60">
            <span class="flex min-w-0 items-center gap-2 text-sm">
                <x-icon name="shield-check" class="size-4.5 shrink-0 text-brand-600 dark:text-brand-400" />
                <span class="font-medium text-zinc-600 dark:text-zinc-300">{{ $driver === 'hcaptcha' ? 'کپچای ابری hCaptcha' : 'کپچای ابری Cloudflare Turnstile' }}:</span>
            </span>
        </div>

        <div id="cc-{{ $uid }}" class="min-h-[65px] overflow-hidden" wire:ignore
             aria-label="{{ $driver === 'hcaptcha' ? 'ویجت کپچای ابری hCaptcha' : 'ویجت کپچای ابری Cloudflare Turnstile' }}"></div>

        <input type="hidden" {{ $inputAttrs }} id="cc-token-{{ $uid }}" autocomplete="off" />

        @assets
            @if($driver === 'hcaptcha')
                <script src="https://js.hcaptcha.com/1/api.js?onload=__ccHcaptchaReady&render=explicit" async defer></script>
            @else
                <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?onload=__ccTurnstileReady&render=explicit" async defer></script>
            @endif

            <script>
                (function () {
                    // آماده‌شدن اسکریپت سرویس ابری → رویداد سراسری برای همهٔ ویجت‌های صفحه
                    window.__ccHcaptchaReady = window.__ccHcaptchaReady || function () {
                        window.dispatchEvent(new Event('cc-captcha-ready'));
                    };
                    window.__ccTurnstileReady = window.__ccTurnstileReady || function () {
                        window.dispatchEvent(new Event('cc-captcha-ready'));
                    };
                })();
            </script>
        @endassets

        <script>
            (function () {
                var uid = @js($uid);
                var driver = @js($driver);
                var siteKey = @js($cloudSite);
                var widgetId = null;

                function setToken(token) {
                    var input = document.getElementById('cc-token-' + uid);

                    if (input) {
                        input.value = token || '';
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                }

                function render() {
                    var el = document.getElementById('cc-' + uid);

                    if (!el || widgetId !== null) { return; }

                    if (driver === 'hcaptcha' && window.hcaptcha) {
                        widgetId = window.hcaptcha.render(el, {
                            sitekey: siteKey,
                            theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
                            callback: function (token) { setToken(token); },
                            'expired-callback': function () { setToken(''); },
                            'error-callback': function () { setToken(''); }
                        });
                    } else if (driver === 'turnstile' && window.turnstile) {
                        widgetId = window.turnstile.render(el, {
                            sitekey: siteKey,
                            theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
                            callback: function (token) { setToken(token); },
                            'expired-callback': function () { setToken(''); },
                            'error-callback': function () { setToken(''); }
                        });
                    }
                }

                render();
                window.addEventListener('cc-captcha-ready', render);
                document.addEventListener('livewire:navigated', render);
            })();
        </script>

        @error($name)
            <p class="text-xs font-bold text-rose-600 dark:text-rose-400">{{ $message }}</p>
        @enderror
    @endif

    {{-- ================= کپچای خودمیزبان (math / image) ================= --}}
    @unless($isCloud)
        <div class="flex items-center justify-between gap-2 rounded-xl bg-zinc-50 px-3.5 py-2.5 ring-1 ring-zinc-200/70 dark:bg-zinc-800/60 dark:ring-zinc-700/60">
            <span class="flex min-w-0 items-center gap-2 text-sm">
                <x-icon name="shield-check" class="size-4.5 shrink-0 text-brand-600 dark:text-brand-400" />
                <span class="font-medium text-zinc-600 dark:text-zinc-300">{{ $label }}:</span>
            </span>

            {{-- تصویر سؤال — قابل کلیک برای سؤال دیگر؛ v= نسخهٔ سشن برای cache-busting --}}
            @if($refreshWire)
                <button type="button" wire:click="{{ $refreshWire }}" wire:loading.attr="disabled"
                        class="inline-flex shrink-0 cursor-pointer items-center rounded-lg bg-white p-1 ring-1 ring-zinc-200/80 transition-opacity hover:opacity-75 dark:bg-white dark:ring-zinc-300/40"
                        title="سؤال دیگر — برای خوانایی کلیک کنید">
                    <img src="{{ $captchaImgUrl }}"
                         alt="{{ $driver === 'image' ? 'تصویر کد امنیتی حرف‌عددی؛ نویسه‌ها را بنویسید' : 'تصویر سؤال امنیتی؛ اعداد و عملگرها را بخوانید' }}"
                         width="150" height="38"
                         class="block h-9 w-auto select-none" draggable="false" />
                </button>
            @else
                <a href="{{ $captchaRefreshUrl }}"
                   class="inline-flex shrink-0 items-center rounded-lg bg-white p-1 ring-1 ring-zinc-200/80 transition-opacity hover:opacity-75 dark:bg-white dark:ring-zinc-300/40"
                   title="سؤال دیگر — برای خوانایی کلیک کنید">
                    <img src="{{ $captchaImgUrl }}"
                         alt="{{ $driver === 'image' ? 'تصویر کد امنیتی حرف‌عددی؛ نویسه‌ها را بنویسید' : 'تصویر سؤال امنیتی؛ اعداد و عملگرها را بخوانید' }}"
                         width="150" height="38"
                         class="block h-9 w-auto select-none" draggable="false" />
                </a>
            @endif

            @if($refreshWire)
                <button type="button" wire:click="{{ $refreshWire }}" wire:loading.attr="disabled"
                        class="inline-flex shrink-0 cursor-pointer items-center gap-1 rounded-lg px-2 py-1 text-[11px] font-bold text-zinc-500 transition-colors hover:bg-zinc-200/60 hover:text-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-700/60 dark:hover:text-zinc-200"
                        title="سؤال دیگر">
                    <x-icon name="refresh-cw" class="size-3.5" />
                    سؤال دیگر
                </button>
            @else
                <a href="{{ $captchaRefreshUrl }}"
                   class="inline-flex shrink-0 items-center gap-1 rounded-lg px-2 py-1 text-[11px] font-bold text-zinc-500 transition-colors hover:bg-zinc-200/60 hover:text-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-700/60 dark:hover:text-zinc-200"
                   title="سؤال دیگر">
                    <x-icon name="refresh-cw" class="size-3.5" />
                    سؤال دیگر
                </a>
            @endif
        </div>

        @if($wireTarget)
            @if($driver === 'image')
                <x-input wire:model="{{ $wireTarget }}" placeholder="کد امنیتی را بنویسید…"
                         autocomplete="off" :class="$errors->has($name) ? 'input-error' : ''" />
            @else
                <x-input wire:model="{{ $wireTarget }}" inputmode="numeric" placeholder="پاسخ را بنویسید…"
                         autocomplete="off" :class="$errors->has($name) ? 'input-error' : ''" />
            @endif
        @else
            @if($driver === 'image')
                <x-input name="{{ $name }}" placeholder="کد امنیتی را بنویسید…"
                         autocomplete="off" required :class="$errors->has($name) ? 'input-error' : ''" />
            @else
                <x-input name="{{ $name }}" inputmode="numeric" placeholder="پاسخ را بنویسید…"
                         autocomplete="off" required :class="$errors->has($name) ? 'input-error' : ''" />
            @endif
        @endif

        @error($name)
            <p class="text-xs font-bold text-rose-600 dark:text-rose-400">{{ $message }}</p>
        @enderror
    @endunless
</div>
