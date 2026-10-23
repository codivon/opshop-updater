{{-- ==================================================================
    مستندات API نسخهٔ ۱ — صفحهٔ مدرن با شروع سریع، احراز هویت، جریان‌های
    خرید/آپدیت، مرجع کامل اندپوینت‌ها در تب‌ها و جدول خطاها.
    داده‌ها از ApiDocs (سینک با سورس کنترلرها) می‌آید.
=================================================================== --}}
@php($base = $this->baseUrl())

<div class="mx-auto max-w-6xl space-y-10 px-4 py-8 sm:px-6">

    {{-- ============================== HERO ============================== --}}
    <header class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-zinc-900 via-zinc-900 to-zinc-800 px-6 py-10 text-white shadow-xl sm:px-10 dark:from-zinc-900 dark:via-zinc-900 dark:to-zinc-950">
        <div class="pointer-events-none absolute inset-0 opacity-[0.07]" aria-hidden="true"
             style="background-image: linear-gradient(#fff 1px, transparent 1px), linear-gradient(90deg, #fff 1px, transparent 1px); background-size: 36px 36px;"></div>
        <div class="pointer-events-none absolute -top-24 -start-24 size-72 rounded-full bg-brand-500/20 blur-3xl" aria-hidden="true"></div>

        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <span class="flex size-14 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/20 backdrop-blur">
                        <x-icon name="braces" class="size-7" />
                    </span>
                    <div>
                        <h1 class="text-2xl font-black sm:text-3xl">مستندات API</h1>
                        <p class="mt-1 text-sm leading-6 text-zinc-400">
                            مرجع کامل رابط برنامه‌نویسی آپدیت‌سنتر — فروش، لایسنس و آپدیت خودکار برای پروژهٔ خریدار
                        </p>
                    </div>
                </div>
                <div class="mt-5 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-xs font-bold ring-1 ring-white/15"><x-icon name="zap" class="size-3.5" /> نسخهٔ v1</span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-xs font-bold ring-1 ring-white/15"><x-icon name="list" class="size-3.5" /> {{ fa_num($this->endpointsCount()) }} اندپوینت</span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-xs font-bold ring-1 ring-white/15"><x-icon name="key-round" class="size-3.5" /> احراز هویت Bearer</span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-xs font-bold ring-1 ring-white/15"><x-icon name="shield-check" class="size-3.5" /> سقف {{ fa_num(120) }} درخواست/دقیقه</span>
                </div>
            </div>

            <div class="w-full max-w-sm shrink-0 space-y-3">
                <div class="rounded-2xl bg-black/30 p-4 ring-1 ring-white/10 backdrop-blur">
                    <p class="flex items-center gap-1.5 text-[11px] font-bold text-zinc-400">
                        <x-icon name="globe" class="size-3.5" /> آدرس پایه (Base URL)
                    </p>
                    <div class="mt-2 flex items-center gap-2" x-data="{ copied: false }">
                        <code dir="ltr" class="min-w-0 flex-1 truncate rounded-lg bg-white/5 px-3 py-2 text-left font-mono text-[13px] text-brand-300">{{ $base }}</code>
                        <button type="button" @click="navigator.clipboard.writeText('{{ $base }}').then(() => { copied = true; $dispatch('toast', {message: 'کپی شد', type: 'success'}); setTimeout(() => copied = false, 1600) })"
                                class="inline-flex shrink-0 cursor-pointer items-center gap-1 rounded-lg bg-white/10 px-2.5 py-2 text-[11px] font-bold transition-colors hover:bg-white/20">
                            <x-icon name="copy" class="size-3.5" x-show="! copied" />
                            <x-icon name="check" class="size-3.5" x-show="copied" x-cloak />
                            <span x-text="copied ? 'کپی شد' : 'کپی'">کپی</span>
                        </button>
                    </div>
                </div>
                <a href="{{ route('admin.api-docs.agent-spec') }}" download
                   class="flex items-center justify-between gap-2 rounded-2xl bg-white/10 px-4 py-3 ring-1 ring-white/15 backdrop-blur transition-colors hover:bg-white/15">
                    <span class="flex items-center gap-2.5">
                        <x-icon name="file-text" class="size-5 text-brand-300" />
                        <span class="text-[13px] font-bold">مشخصات کامل API برای ایجنت</span>
                    </span>
                    <x-icon name="download" class="size-4 text-zinc-300" />
                </a>
            </div>
        </div>
    </header>

    {{-- ============================== شروع سریع ============================== --}}
    <section aria-labelledby="qs-title">
        <div class="mb-5 flex items-center gap-2.5">
            <span class="flex size-9 items-center justify-center rounded-xl bg-brand-500/10 text-brand-600 dark:text-brand-400"><x-icon name="rocket" class="size-5" /></span>
            <h2 id="qs-title" class="text-lg font-black text-zinc-900 dark:text-zinc-50">شروع سریع در ۳ گام</h2>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="card relative p-5">
                <span class="absolute -top-3 start-5 flex size-7 items-center justify-center rounded-full bg-brand-500 text-sm font-black text-white shadow-md shadow-brand-500/30">۱</span>
                <h3 class="mt-2 flex items-center gap-2 text-sm font-black text-zinc-900 dark:text-zinc-50"><x-icon name="key" class="size-4 text-brand-500" /> کد آپدیت را بگیرید</h3>
                <p class="mt-2 text-[13px] leading-6 text-zinc-500 dark:text-zinc-400">
                    مشتری کد آپدیت (update_code) را از پروفایلش در پنل یا پیامک خرید دریافت می‌کند. همین کد، توکن احراز هویت API شماست.
                </p>
            </div>
            <div class="card relative p-5">
                <span class="absolute -top-3 start-5 flex size-7 items-center justify-center rounded-full bg-brand-500 text-sm font-black text-white shadow-md shadow-brand-500/30">۲</span>
                <h3 class="mt-2 flex items-center gap-2 text-sm font-black text-zinc-900 dark:text-zinc-50"><x-icon name="globe" class="size-4 text-brand-500" /> دامنه را ثبت کنید</h3>
                <p class="mt-2 text-[13px] leading-6 text-zinc-500 dark:text-zinc-400">
                    در پنل، فیلد «آدرس وب‌سایت» مشتری را روی دامنهٔ پروژهٔ خریدار بگذارید؛ هدر X-Project-Url هر درخواست باید با همین دامنه بخواند (زیردامنه‌ها هم مجازند).
                </p>
            </div>
            <div class="card relative p-5">
                <span class="absolute -top-3 start-5 flex size-7 items-center justify-center rounded-full bg-brand-500 text-sm font-black text-white shadow-md shadow-brand-500/30">۳</span>
                <h3 class="mt-2 flex items-center gap-2 text-sm font-black text-zinc-900 dark:text-zinc-50"><x-icon name="play" class="size-4 text-brand-500" /> اولین درخواست را بزنید</h3>
                <p class="mt-2 text-[13px] leading-6 text-zinc-500 dark:text-zinc-400">
                    با دو هدر احراز، لیست پکیج‌ها را بگیرید. پاسخ همهٔ اندپوینت‌ها JSON و کدگذاری UTF-8 است.
                </p>
            </div>
        </div>

        @php($curlQuick = 'curl "'.$base.'/packages" \\'. "\n" .'  -H "Authorization: Bearer YOUR_UPDATE_CODE" \\'. "\n" .'  -H "X-Project-Url: https://your-site.com"')
        @php($quickSamples = $this->quickStartSamples())
        <div class="mt-5 grid gap-4 lg:grid-cols-3">
            <x-code-block label="اولین درخواست (cURL)" :code="$curlQuick" />
            <x-code-block label="اولین درخواست (Laravel HTTP Client)" :code="$quickSamples['laravel']" />
            <x-code-block label="اولین درخواست (PHP — Guzzle)" :code="$quickSamples['guzzle']" />
        </div>
    </section>

    {{-- ============================== فایل ایجنت ============================== --}}
    <section aria-labelledby="agent-title">
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-l from-brand-600 via-brand-500 to-brand-400 p-px shadow-xl">
            <div class="flex flex-col gap-6 rounded-3xl bg-white p-6 dark:bg-zinc-900 sm:p-8 lg:flex-row lg:items-center lg:justify-between">
                <div class="max-w-2xl">
                    <div class="flex items-center gap-2.5">
                        <span class="flex size-10 items-center justify-center rounded-xl bg-brand-500/10 text-brand-600 dark:text-brand-400"><x-icon name="file-text" class="size-5" /></span>
                        <h2 id="agent-title" class="text-lg font-black text-zinc-900 dark:text-zinc-50">دستور ساخت کامل برای ایجنت هوش مصنوعی</h2>
                    </div>
                    <p class="mt-3 text-[13.5px] leading-7 text-zinc-600 dark:text-zinc-300">
                        یک فایل Markdown مستقل که همه‌چیز لازم برای ساخت سمت فروشگاهی (پروژهٔ خریدار) داخلش است:
                        قرارداد کامل هر {{ fa_num($this->endpointsCount()) }} اندپوینت با نمونهٔ JSON واقعی، جریان‌های خرید/لایسنس/آپدیت گام‌به‌گام،
                        جدول خطاها، چک‌لیست امنیتی، معماری پیشنهادی فروشگاه و نمونه‌کد کامل Laravel و Guzzle.
                        کافی است فایل را به هر ایجنتی بدهید تا فروشگاه شما را کامل بسازد.
                    </p>
                    <ul class="mt-4 flex flex-wrap gap-x-5 gap-y-1.5 text-[12.5px] font-medium text-zinc-500 dark:text-zinc-400">
                        <li class="flex items-center gap-1.5"><x-icon name="check-circle" class="size-4 shrink-0 text-emerald-500" /> مرجع کامل اندپوینت‌ها</li>
                        <li class="flex items-center gap-1.5"><x-icon name="check-circle" class="size-4 shrink-0 text-emerald-500" /> دیاگرام جریان پرداخت و آپدیت</li>
                        <li class="flex items-center gap-1.5"><x-icon name="check-circle" class="size-4 shrink-0 text-emerald-500" /> نمونه‌کد Laravel + Guzzle + cURL</li>
                        <li class="flex items-center gap-1.5"><x-icon name="check-circle" class="size-4 shrink-0 text-emerald-500" /> چک‌لیست پذیرش برای ایجنت</li>
                    </ul>
                </div>
                <a href="{{ route('admin.api-docs.agent-spec') }}" download
                   class="group inline-flex shrink-0 cursor-pointer items-center gap-3 rounded-2xl bg-zinc-900 px-5 py-4 text-sm font-black text-white shadow-lg shadow-zinc-900/20 transition-all hover:-translate-y-0.5 hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-100">
                    <x-icon name="download" class="size-5 transition-transform group-hover:translate-y-0.5" />
                    <span class="flex flex-col items-start gap-0.5">
                        <span>دانلود فایل مستندات ایجنت</span>
                        <span class="text-[10px] font-semibold opacity-70">Markdown · قابل‌ارائه مستقیم به AI</span>
                    </span>
                </a>
            </div>
        </div>
    </section>

    {{-- ============================== احراز هویت ============================== --}}
    <section aria-labelledby="auth-title">
        <div class="mb-5 flex items-center gap-2.5">
            <span class="flex size-9 items-center justify-center rounded-xl bg-brand-500/10 text-brand-600 dark:text-brand-400"><x-icon name="lock" class="size-5" /></span>
            <h2 id="auth-title" class="text-lg font-black text-zinc-900 dark:text-zinc-50">احراز هویت و قواعد دسترسی</h2>
        </div>

        <div class="card overflow-hidden">
            <div class="p-5">
                <div class="grid gap-5 lg:grid-cols-2">
                    <div>
                        <h3 class="flex items-center gap-2 text-sm font-black text-zinc-800 dark:text-zinc-100"><x-icon name="key-round" class="size-4 text-brand-500" /> توکن (کد آپدیت مشتری)</h3>
                        <p class="mt-2 text-[13px] leading-6 text-zinc-500 dark:text-zinc-400">
                            توکن را می‌توانید به سه شکل بفرستید — اولویت با هدر Bearer است:
                        </p>
                        <ul class="mt-3 space-y-2 text-[13px] leading-6 text-zinc-600 dark:text-zinc-300">
                            <li class="flex items-start gap-2"><code dir="ltr" class="shrink-0 rounded-md bg-zinc-100 px-1.5 py-0.5 font-mono text-[11px] dark:bg-zinc-800">Authorization: Bearer {token}</code> روش توصیه‌شده</li>
                            <li class="flex items-start gap-2"><code dir="ltr" class="shrink-0 rounded-md bg-zinc-100 px-1.5 py-0.5 font-mono text-[11px] dark:bg-zinc-800">X-Project-Key: {token}</code> جایگزین</li>
                            <li class="flex items-start gap-2"><code dir="ltr" class="shrink-0 rounded-md bg-zinc-100 px-1.5 py-0.5 font-mono text-[11px] dark:bg-zinc-800">?token={token}</code> برای اندپوینت‌های قدیمی</li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="flex items-center gap-2 text-sm font-black text-zinc-800 dark:text-zinc-100"><x-icon name="globe" class="size-4 text-brand-500" /> هدر دامنه (الزامی)</h3>
                        <p class="mt-2 text-[13px] leading-6 text-zinc-500 dark:text-zinc-400">
                            همهٔ اندپوینت‌های محافظت‌شده به هدر <code dir="ltr" class="rounded-md bg-zinc-100 px-1.5 py-0.5 font-mono text-[11px] dark:bg-zinc-800">X-Project-Url</code> نیاز دارند:
                        </p>
                        <div class="mt-3">
                            <x-code-block label="هدر دامنه" code="X-Project-Url: https://your-site.com" />
                        </div>
                    </div>
                </div>
            </div>
            <div class="grid gap-px border-t border-zinc-200/70 bg-zinc-200/70 text-[13px] leading-6 dark:border-zinc-700/60 dark:bg-zinc-700/60 sm:grid-cols-3">
                <p class="flex items-start gap-2 bg-zinc-50 p-4 text-zinc-600 dark:bg-zinc-900/60 dark:text-zinc-300"><x-icon name="check-circle" class="mt-0.5 size-4 shrink-0 text-emerald-500" /> دامنهٔ هدر باید با «آدرس وب‌سایت» مشتری یکی باشد؛ زیردامنه‌ها (sub.example.com) پذیرفته می‌شوند.</p>
                <p class="flex items-start gap-2 bg-zinc-50 p-4 text-zinc-600 dark:bg-zinc-900/60 dark:text-zinc-300"><x-icon name="alert-triangle" class="mt-0.5 size-4 shrink-0 text-amber-500" /> کل API سقف {{ fa_num(120) }} درخواست در دقیقه برای هر IP دارد (خطای 429 در تجاوز).</p>
                <p class="flex items-start gap-2 bg-zinc-50 p-4 text-zinc-600 dark:bg-zinc-900/60 dark:text-zinc-300"><x-icon name="shield-alert" class="mt-0.5 size-4 shrink-0 text-rose-500" /> کد آپدیت را محرمانه نگه دارید؛ با آن می‌توان خرید و دانلود انجام داد. در صورت افشا، از پنل مشتری را غیرفعال کنید.</p>
            </div>
        </div>
    </section>

    {{-- ============================== جریان‌ها ============================== --}}
    <section aria-labelledby="flow-title">
        <div class="mb-5 flex items-center gap-2.5">
            <span class="flex size-9 items-center justify-center rounded-xl bg-brand-500/10 text-brand-600 dark:text-brand-400"><x-icon name="layers" class="size-5" /></span>
            <h2 id="flow-title" class="text-lg font-black text-zinc-900 dark:text-zinc-50">جریان‌های اصلی</h2>
        </div>

        {{-- خرید پکیج --}}
        <div class="card p-5 sm:p-6">
            <h3 class="flex items-center gap-2 text-sm font-black text-zinc-800 dark:text-zinc-100"><x-icon name="shopping-bag" class="size-4 text-brand-500" /> جریان خرید پکیج ({{ fa_num(6) }} مرحله)</h3>
            <ol class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach([
                    ['t' => 'لیست و طرح‌ها', 'd' => 'GET /packages — پکیج‌ها، طرح‌های قیمت و درگاه‌های فعال', 'i' => 'list'],
                    ['t' => 'ثبت خرید', 'd' => 'POST /packages/{slug}/purchase — با pricing_plan_id و callback_url', 'i' => 'shopping-cart'],
                    ['t' => 'پرداخت', 'd' => 'کاربر را با payment_url به درگاه بفرستید', 'i' => 'credit-card'],
                    ['t' => 'بازگشت خودکار', 'd' => 'درگاه به /payments/callback پنل برمی‌گرداند؛ پنل تأیید می‌کند و کاربر به callback_url شما هدایت می‌شود', 'i' => 'corner-up-left'],
                    ['t' => 'تأیید نهایی', 'd' => 'POST /payments/{transactionId}/verify — خروجی: license_key', 'i' => 'badge-check'],
                    ['t' => 'دانلود', 'd' => 'POST /packages/{slug}/download-url — لینک یک‌بارمصرف برای دکمهٔ دانلود', 'i' => 'download'],
                ] as $i => $step)
                    <li class="relative rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/70 dark:bg-zinc-800/50 dark:ring-zinc-700/60">
                        <div class="flex items-center gap-2.5">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand-500/10 font-black text-brand-600 dark:text-brand-400">{{ fa_num($i + 1) }}</span>
                            <span class="flex items-center gap-1.5 text-sm font-black text-zinc-800 dark:text-zinc-100"><x-icon :name="$step['i']" class="size-4 text-zinc-400" /> {{ $step['t'] }}</span>
                        </div>
                        <p dir="auto" class="mt-2 text-[12.5px] leading-6 text-zinc-500 dark:text-zinc-400">{{ $step['d'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>

        {{-- آپدیت خودکار --}}
        <div class="card mt-4 p-5 sm:p-6">
            <h3 class="flex items-center gap-2 text-sm font-black text-zinc-800 dark:text-zinc-100"><x-icon name="refresh-cw" class="size-4 text-brand-500" /> جریان آپدیت خودکار برنامهٔ خریدار ({{ fa_num(4) }} مرحله)</h3>
            <ol class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach([
                    ['t' => 'اعتبارسنجی لایسنس', 'd' => 'POST /packages/{slug}/verify-license — در هر اجرای برنامه', 'i' => 'shield-check'],
                    ['t' => 'مقایسهٔ نسخه', 'd' => 'GET /packages/{slug}/check-update?current_version=…', 'i' => 'git-branch'],
                    ['t' => 'دریافت فایل', 'd' => 'اگر has_update=true → POST download-url و دانلود ZIP', 'i' => 'download'],
                    ['t' => 'کنترل صحت', 'd' => 'SHA-256 فایل را با file_hash / هدر X-Package-Hash مقایسه کنید', 'i' => 'check-check'],
                ] as $i => $step)
                    <li class="relative rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/70 dark:bg-zinc-800/50 dark:ring-zinc-700/60">
                        <div class="flex items-center gap-2.5">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand-500/10 font-black text-brand-600 dark:text-brand-400">{{ fa_num($i + 1) }}</span>
                            <span class="flex items-center gap-1.5 text-sm font-black text-zinc-800 dark:text-zinc-100"><x-icon :name="$step['i']" class="size-4 text-zinc-400" /> {{ $step['t'] }}</span>
                        </div>
                        <p dir="auto" class="mt-2 text-[12.5px] leading-6 text-zinc-500 dark:text-zinc-400">{{ $step['d'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- ============================== مرجع اندپوینت‌ها ============================== --}}
    <section aria-labelledby="endpoints-title">
        <div class="mb-5 flex items-center gap-2.5">
            <span class="flex size-9 items-center justify-center rounded-xl bg-brand-500/10 text-brand-600 dark:text-brand-400"><x-icon name="braces" class="size-5" /></span>
            <div>
                <h2 id="endpoints-title" class="text-lg font-black text-zinc-900 dark:text-zinc-50">مرجع اندپوینت‌ها</h2>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">آدرس پایهٔ همهٔ مسیرها: <code dir="ltr" class="rounded-md bg-zinc-100 px-1.5 py-0.5 font-mono text-[11px] dark:bg-zinc-800">{{ $base }}</code></p>
            </div>
        </div>

        {{-- تب‌ها — همان الگوی x-tabs اما با حلقهٔ پویا (نام اسلات پویا در Blade ممکن نیست) --}}
        <div x-data="tabs('packages')" class="w-full">
            <div class="flex gap-1 overflow-x-auto rounded-xl bg-zinc-200/60 p-1 dark:bg-zinc-800/60" role="tablist">
                @foreach($this->groups() as $g)
                    <button type="button" role="tab" :aria-selected="tab === '{{ $g['id'] }}'"
                            @click="tab = '{{ $g['id'] }}'"
                            :class="tab === '{{ $g['id'] }}' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-900 dark:text-zinc-100' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200'"
                            class="flex shrink-0 cursor-pointer items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-semibold transition-all">
                        <x-icon :name="$g['icon']" class="size-4" />
                        {{ $g['label'] }}
                        <span class="rounded-full bg-zinc-300/70 px-1.5 py-0.5 text-[10px] font-bold tabular-nums text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">{{ fa_num(count($g['endpoints'])) }}</span>
                    </button>
                @endforeach
            </div>

            @foreach($this->groups() as $g)
                <div x-show="tab === '{{ $g['id'] }}'" x-cloak role="tabpanel" class="animate-fade-in pt-5">
                    <p class="mb-4 flex items-start gap-2 rounded-xl bg-brand-500/5 px-4 py-3 text-[13px] leading-6 text-zinc-600 ring-1 ring-brand-500/15 dark:text-zinc-300">
                        <x-icon :name="$g['icon']" class="mt-0.5 size-4 shrink-0 text-brand-500" />
                        {{ $g['desc'] }}
                    </p>

                    <div class="space-y-5">
                        @foreach($g['endpoints'] as $ep)
                            <article class="card overflow-hidden">
                                {{-- سربرگ اندپوینت --}}
                                <div class="flex flex-wrap items-center gap-3 border-b border-zinc-200/70 bg-zinc-50/60 px-4 py-3 dark:border-zinc-700/60 dark:bg-zinc-800/40">
                                    <span class="rounded-lg px-2.5 py-1 font-mono text-[11px] font-black tracking-wide
                                        {{ $ep['method'] === 'GET'
                                            ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/25 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-400/25'
                                            : 'bg-amber-50 text-amber-700 ring-1 ring-amber-600/25 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-400/25' }}">
                                        {{ $ep['method'] }}
                                    </span>
                                    <code dir="ltr" class="min-w-0 flex-1 truncate text-left font-mono text-[13px] font-bold text-zinc-800 dark:text-zinc-100">{{ $base }}{{ $ep['path'] }}</code>
                                    <h3 class="text-sm font-black text-zinc-700 dark:text-zinc-200">{{ $ep['title'] }}</h3>
                                </div>

                                <div class="space-y-4 p-4 sm:p-5">
                                    <p class="text-[13px] leading-7 text-zinc-600 dark:text-zinc-300">{{ $ep['desc'] }}</p>

                                    {{-- جدول پارامترها --}}
                                    @if(!empty($ep['params']))
                                        <div class="overflow-x-auto rounded-xl ring-1 ring-zinc-200/70 dark:ring-zinc-700/60">
                                            <table class="w-full min-w-[560px] text-right text-[12.5px]">
                                                <thead>
                                                    <tr class="bg-zinc-50 text-zinc-500 dark:bg-zinc-800/60 dark:text-zinc-400">
                                                        <th class="px-3.5 py-2 font-bold">پارامتر</th>
                                                        <th class="px-3.5 py-2 font-bold">مکان</th>
                                                        <th class="px-3.5 py-2 font-bold">نوع</th>
                                                        <th class="px-3.5 py-2 font-bold">وضعیت</th>
                                                        <th class="px-3.5 py-2 font-bold">توضیح</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-zinc-200/70 dark:divide-zinc-700/60">
                                                    @foreach($ep['params'] as $p)
                                                        <tr class="text-zinc-700 dark:text-zinc-200">
                                                            <td class="px-3.5 py-2"><code dir="ltr" class="font-mono text-[12px] font-bold">{{ $p['name'] }}</code></td>
                                                            <td class="px-3.5 py-2">
                                                                @php($inColors = ['query' => 'bg-teal-50 text-teal-700 ring-teal-600/25 dark:bg-teal-500/10 dark:text-teal-400 dark:ring-teal-400/25', 'body' => 'bg-violet-50 text-violet-700 ring-violet-600/25 dark:bg-violet-500/10 dark:text-violet-400 dark:ring-violet-400/25', 'path' => 'bg-amber-50 text-amber-700 ring-amber-600/25 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-400/25'])
                                                                <span class="rounded-md px-1.5 py-0.5 text-[11px] font-bold ring-1 ring-inset {{ $inColors[$p['in']] }}">{{ $p['in'] }}</span>
                                                            </td>
                                                            <td class="px-3.5 py-2 text-zinc-500 dark:text-zinc-400">{{ $p['type'] }}</td>
                                                            <td class="px-3.5 py-2">
                                                                @if($p['required'] === true)
                                                                    <span class="rounded-md bg-rose-50 px-1.5 py-0.5 text-[11px] font-bold text-rose-700 ring-1 ring-inset ring-rose-600/25 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/25">الزامی</span>
                                                                @elseif($p['required'] === 'if-paid')
                                                                    <span class="rounded-md bg-amber-50 px-1.5 py-0.5 text-[11px] font-bold text-amber-700 ring-1 ring-inset ring-amber-600/25 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-400/25">فقط خرید پولی</span>
                                                                @else
                                                                    <span class="rounded-md bg-zinc-100 px-1.5 py-0.5 text-[11px] font-bold text-zinc-600 ring-1 ring-inset ring-zinc-500/20 dark:bg-zinc-700/40 dark:text-zinc-300 dark:ring-zinc-400/20">اختیاری</span>
                                                                @endif
                                                            </td>
                                                            <td class="px-3.5 py-2 leading-6 text-zinc-600 dark:text-zinc-300">{{ $p['desc'] }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif

                                    {{-- نمونهٔ درخواست — سوییچ زبان cURL / Laravel / Guzzle + پاسخ --}}
                                    <div class="grid gap-4 xl:grid-cols-2">
                                        <div x-data="{ lang: 'curl' }" class="space-y-3 self-start">
                                            @php($sampleLangs = isset($ep['sample']) ? ['curl' => 'cURL', 'laravel' => 'Laravel', 'guzzle' => 'Guzzle'] : ['curl' => 'cURL'])
                                            @if(count($sampleLangs) > 1)
                                                <div class="flex w-fit gap-1 rounded-xl bg-zinc-100 p-1 dark:bg-zinc-800/70" role="tablist" aria-label="زبان نمونهٔ درخواست">
                                                    @foreach($sampleLangs as $langKey => $langLabel)
                                                        <button type="button" role="tab" @click="lang = '{{ $langKey }}'"
                                                                :aria-selected="lang === '{{ $langKey }}'"
                                                                :class="lang === '{{ $langKey }}' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-900 dark:text-zinc-100' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200'"
                                                                class="cursor-pointer rounded-lg px-3 py-1.5 font-mono text-[11.5px] font-bold transition-all">
                                                            {{ $langLabel }}
                                                        </button>
                                                    @endforeach
                                                </div>
                                            @endif
                                            <div x-show="lang === 'curl'" role="tabpanel">
                                                <x-code-block label="نمونهٔ درخواست (cURL)" :code="$ep['request']" />
                                            </div>
                                            @isset($ep['sample'])
                                                <div x-show="lang === 'laravel'" x-cloak role="tabpanel">
                                                    <x-code-block label="نمونهٔ درخواست (Laravel HTTP Client)" :code="$ep['sample']['laravel']" />
                                                </div>
                                                <div x-show="lang === 'guzzle'" x-cloak role="tabpanel">
                                                    <x-code-block label="نمونهٔ درخواست (PHP — Guzzle)" :code="$ep['sample']['guzzle']" />
                                                </div>
                                            @endisset
                                        </div>
                                        <div class="space-y-4">
                                            <x-code-block label="پاسخ (JSON)" :code="$ep['response']" collapsible />
                                            @if(!empty($ep['alt_response']))
                                                <x-code-block :label="$ep['alt_response_label'] ?? 'حالت دیگر'" :code="$ep['alt_response']" collapsible />
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ============================== خطاها ============================== --}}
    <section aria-labelledby="errors-title">
        <div class="mb-5 flex items-center gap-2.5">
            <span class="flex size-9 items-center justify-center rounded-xl bg-brand-500/10 text-brand-600 dark:text-brand-400"><x-icon name="alert-triangle" class="size-5" /></span>
            <h2 id="errors-title" class="text-lg font-black text-zinc-900 dark:text-zinc-50">کدهای خطا</h2>
        </div>

        <div class="card overflow-hidden">
            <p class="border-b border-zinc-200/70 px-5 py-3 text-[13px] leading-6 text-zinc-500 dark:border-zinc-700/60 dark:text-zinc-400">
                ساختار خطای همهٔ اندپوینت‌ها یکسان است: <code dir="ltr" class="rounded-md bg-zinc-100 px-1.5 py-0.5 font-mono text-[11px] dark:bg-zinc-800">{ "error": "پیام توضیحی" }</code> — پیام‌ها فارسی و آمادهٔ نمایش به کاربر نهایی هستند.
            </p>
            <div class="divide-y divide-zinc-200/70 dark:divide-zinc-700/60">
                @foreach($this->errorCodes() as $err)
                    <div class="flex items-start gap-4 px-5 py-3.5">
                        <x-badge :variant="$err['color']" class="font-mono">{{ $err['code'] }}</x-badge>
                        <p class="flex-1 text-[13px] leading-6 text-zinc-600 dark:text-zinc-300">{{ $err['when'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============================== نکات پایانی ============================== --}}
    <section aria-labelledby="notes-title">
        <div class="mb-5 flex items-center gap-2.5">
            <span class="flex size-9 items-center justify-center rounded-xl bg-brand-500/10 text-brand-600 dark:text-brand-400"><x-icon name="info" class="size-5" /></span>
            <h2 id="notes-title" class="text-lg font-black text-zinc-900 dark:text-zinc-50">نکات امنیتی و فنی</h2>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="card p-5">
                <h3 class="flex items-center gap-2 text-sm font-black text-zinc-800 dark:text-zinc-100"><x-icon name="clock" class="size-4 text-brand-500" /> توکن‌های دانلود یک‌بارمصرف</h3>
                <p class="mt-2 text-[13px] leading-6 text-zinc-500 dark:text-zinc-400">download_token فقط {{ fa_num(15) }} دقیقه و یک بار معتبر است؛ پس از استفاده markAsUsed می‌شود و شمارندهٔ دانلود نسخه/پکیج یکی بالا می‌رود.</p>
            </div>
            <div class="card p-5">
                <h3 class="flex items-center gap-2 text-sm font-black text-zinc-800 dark:text-zinc-100"><x-icon name="check-check" class="size-4 text-brand-500" /> امضای فایل (SHA-256)</h3>
                <p class="mt-2 text-[13px] leading-6 text-zinc-500 dark:text-zinc-400">مقدار file_hash در پاسخ‌ها و هدر X-Package-Hash در دانلود، هش SHA-256 فایل ZIP است — پس از دانلود با hash_file کنترل کنید تا خرابی/دستکاری فایل شناسایی شود.</p>
            </div>
            <div class="card p-5">
                <h3 class="flex items-center gap-2 text-sm font-black text-zinc-800 dark:text-zinc-100"><x-icon name="send" class="size-4 text-brand-500" /> پیامک‌های خودکار</h3>
                <p class="mt-2 text-[13px] leading-6 text-zinc-500 dark:text-zinc-400">تأیید پرداخت از مسیر API (مانند کال‌بک) پیامک خرید به مشتری و مدیر می‌فرستد و با قابلیت once از ارسال تکراری جلوگیری می‌شود. متن و فعال/غیرفعال‌بودن هر مورد در پنل قابل تنظیم است.</p>
            </div>
        </div>
    </section>

</div>
