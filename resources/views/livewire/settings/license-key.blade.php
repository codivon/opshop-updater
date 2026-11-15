<div class="space-y-6">
    {{-- header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-black text-zinc-900 dark:text-zinc-50">کلید امضای لایسنس</h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">پاکت لایسنس و مانیفست یکپارچگی با EC P-256 امضا می‌شود — ساخت و تعویض بدون نیاز به شِل.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.settings.security') }}" wire:navigate>
                <x-btn variant="secondary" icon="shield">امنیت و امکانات</x-btn>
            </a>
            <a href="{{ route('admin.settings.index') }}" wire:navigate>
                <x-btn variant="secondary" icon="settings">تنظیمات عمومی</x-btn>
            </a>
        </div>
    </div>

    {{-- وضعیت کلید --}}
    <x-card title="وضعیت کلید" subtitle="منبع حقیقت: خود فایل .env — مستقل از کش پیکربندی">
        <div class="space-y-5">
            <div class="flex flex-wrap items-center gap-3">
                @if(($status['state'] ?? '') === 'ok')
                    <x-badge variant="success" icon="shield-check">فعال</x-badge>
                @elseif(($status['state'] ?? '') === 'invalid')
                    <x-badge variant="warning" icon="alert-triangle">تنظیم شده اما نامعتبر</x-badge>
                @else
                    <x-badge variant="danger" icon="shield-alert">تنظیم نشده</x-badge>
                @endif

                @if(!empty($status['fingerprint']))
                    <span class="text-xs text-zinc-500 dark:text-zinc-400">
                        اثرانگشت (SHA-256): <span dir="ltr" class="font-mono font-semibold text-zinc-700 dark:text-zinc-300">{{ $status['fingerprint'] }}</span>
                    </span>
                @endif
            </div>

            @if(($status['state'] ?? '') === 'invalid')
                <div class="flex items-center gap-3 rounded-xl bg-amber-50 p-4 text-amber-700 ring-1 ring-amber-600/10 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-400/20">
                    <x-icon name="alert-triangle" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">مقدار ثبت‌شده در .env یک PEM قابل‌خواندن نیست (احتمالاً موقع ویرایش دستی خراب شده). با دکمهٔ «تعویض کلید» مقدار سالم بسازید.</p>
                </div>
            @endif

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl bg-zinc-50 p-3.5 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                    <p class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">فایل .env</p>
                    <p class="mt-1 text-sm font-bold text-zinc-800 dark:text-zinc-200">
                        {{ ($status['env_exists'] ?? false) ? (($status['env_writable'] ?? false) ? 'موجود و قابل‌نوشتن' : 'موجود اما بدون مجوز نوشتن') : 'پیدا نشد' }}
                    </p>
                </div>
                <div class="rounded-xl bg-zinc-50 p-3.5 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                    <p class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">کش پیکربندی</p>
                    <p class="mt-1 text-sm font-bold text-zinc-800 dark:text-zinc-200">
                        {{ ($status['config_cached'] ?? false) ? 'فعال — بعد از تغییر .env پاک‌سازی لازم است' : 'غیرفعال — .env مستقیم خوانده می‌شود' }}
                    </p>
                </div>
                <div class="rounded-xl bg-zinc-50 p-3.5 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                    <p class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">دورهٔ مهلت (grace)</p>
                    <p class="mt-1 text-sm font-bold text-zinc-800 dark:text-zinc-200">{{ fa_num($status["grace_hours"] ?? 24) }} ساعت</p>
                </div>
                <div class="rounded-xl bg-zinc-50 p-3.5 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                    <p class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">فاصلهٔ چک لایسنس</p>
                    <p class="mt-1 text-sm font-bold text-zinc-800 dark:text-zinc-200">هر {{ fa_num($status["check_interval_hours"] ?? 24) }} ساعت</p>
                </div>
            </div>

            @if(!empty($status['public_key']))
                <div>
                    <p class="mb-1.5 text-xs font-semibold text-zinc-500 dark:text-zinc-400">کلید عمومی متناظر (برای بکاپ):</p>
                    <pre dir="ltr" class="max-h-40 overflow-y-auto rounded-xl bg-zinc-950 p-4 font-mono text-xs leading-5 text-emerald-300 ring-1 ring-zinc-800">{{ $status['public_key'] }}</pre>
                </div>

                @if(!empty($status['shop_env_value']))
                    <div>
                        <p class="mb-1.5 text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                            برای فروشگاه (shop-pro-laravel-13) — این خط را در <span dir="ltr" class="font-mono">.env</span> فروشگاه بگذارید تا امضاهای این سرور تأیید شوند:
                        </p>
                        <x-code-block label=".env فروشگاه — PACKAGE_LICENSE_PUBLIC_KEY" :code="$status['shop_env_value']" />
                        <p class="mt-1.5 text-xs leading-5 text-zinc-500 dark:text-zinc-400">
                            بعد از گذاشتن این خط، <span dir="ltr" class="font-mono">bootstrap/cache/config.php</span> را حذف کنید (یا از دکمهٔ پاک‌سازی کش پایین صفحه استفاده کنید اگر پنل فروشگاه را دارید).
                            بدون این خط، اگر کلید این سرور تعویض شده باشد، پکیج‌های نصب‌شده در فروشگاه بسته می‌شوند.
                        </p>
                    </div>
                @endif
            @endif
        </div>
    </x-card>

    {{-- عملیات --}}
    @if(($status['state'] ?? '') === 'missing')
        <x-card title="ساخت کلید" subtitle="تا وقتی این کلید تنظیم نشود، نصب/بروزرسانی پکیج‌ها در فروشگاه‌ها با خطای 500 شکست می‌خورد.">
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-red-50 p-4 text-red-700 ring-1 ring-red-600/10 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-400/20">
                    <x-icon name="shield-alert" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">
                        کلید امضای لایسنس (PACKAGE_LICENSE_SIGNING_KEY) تنظیم نشده است.
                        با این دکمه جفت‌کلید EC P-256 ساخته می‌شود، خط آن در .env نوشته می‌شود و کش پیکربندی پاک می‌شود — همه‌چیز بدون شِل.
                    </p>
                </div>
                <x-btn icon="key" wire:click="$set('confirmGenerate', true)">ساخت کلید و ثبت در .env</x-btn>
            </div>
        </x-card>
    @else
        <x-card title="تعویض کلید (rotation)" subtitle="کلید فعلی فعال است — تعویض فقط برای موارد نشت/تراشهٔ امنیتی لازم است.">
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-red-50 p-4 text-red-700 ring-1 ring-red-600/10 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-400/20">
                    <x-icon name="alert-triangle" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">
                        فروشگاه‌های متصل با کلید عمومی امضا را راستی‌آزمایی می‌کنند؛ اگر کلید این سرور عوض شود و کلید عمومیِ جدید در
                        <span dir="ltr" class="font-mono">.env</span> فروشگاه (<span dir="ltr" class="font-mono">PACKAGE_LICENSE_PUBLIC_KEY</span>) ثبت نشده باشد،
                        <strong>همهٔ پکیج‌های نصب‌شده در فروشگاه بسته می‌شوند</strong> (خطای «امضای پاسخ سرور معتبر نیست» و «مانیفست یکپارچگی یافت نشد»).
                        ترتیب امن: اول خطِ کلید عمومی را از همین صفحه کپی و در فروشگاه ثبت کنید، بعد این‌جا کلید را تعویض کنید.
                    </p>
                </div>
                <x-btn variant="secondary" icon="refresh-cw" wire:click="$set('confirmRotate', true)">تعویض کلید</x-btn>
            </div>
        </x-card>
    @endif

    {{-- پاک‌سازی کش --}}
    <x-card title="پاک‌سازی کش پیکربندی" subtitle="اگر .env را از File Manager ویرایش کرده‌اید، معادل artisan config:clear را از این‌جا بزنید.">
        <x-btn variant="secondary" icon="refresh-cw" wire:click="$set('confirmClearCache', true)">پاک‌سازی کش پیکربندی</x-btn>
    </x-card>

    {{-- کلید عمومی تازه‌ساخته --}}
    @if($lastPublicKey)
        <x-card title="کلید عمومی تازه‌ساخته — همین حالا بکاپ کنید" subtitle="این کلید عمومی را در جای امن ذخیره کنید و خط .env فروشگاه را ثبت کنید.">
            <div class="space-y-3">
                <div class="flex flex-wrap items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                    <x-badge variant="success" icon="check">ثبت شد</x-badge>
                    @if($lastFingerprint)
                        <span>اثرانگشت: <span dir="ltr" class="font-mono font-semibold">{{ $lastFingerprint }}</span></span>
                    @endif
                </div>
                <pre dir="ltr" class="max-h-40 overflow-y-auto rounded-xl bg-zinc-950 p-4 font-mono text-xs leading-5 text-emerald-300 ring-1 ring-zinc-800">{{ $lastPublicKey }}</pre>
                @if($lastShopEnvValue)
                    <div>
                        <p class="mb-1.5 text-xs font-semibold text-red-600 dark:text-red-400">
                            گام بعدی الزامی: این خط را در <span dir="ltr" class="font-mono">.env</span> فروشگاه ثبت کنید (بعد از آن bootstrap/cache/config.php را حذف کنید):
                        </p>
                        <x-code-block label=".env فروشگاه — PACKAGE_LICENSE_PUBLIC_KEY" :code="$lastShopEnvValue" />
                    </div>
                @endif
                <p class="text-xs text-zinc-500 dark:text-zinc-400">کلید خصوصی هرگز نمایش داده نمی‌شود — در .env باقی می‌ماند؛ از آن بکاپ امن نگه دارید.</p>
            </div>
        </x-card>
    @endif

    {{-- confirm: generate --}}
    <x-modal wire:model="confirmGenerate" title="ساخت کلید امضای لایسنس" size="sm">
        @if($confirmGenerate)
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-emerald-50 p-4 text-emerald-700 ring-1 ring-emerald-600/10 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-400/20">
                    <x-icon name="key" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">جفت‌کلید EC P-256 ساخته می‌شود، خط PACKAGE_LICENSE_SIGNING_KEY در .env نوشته و کش پیکربندی پاک می‌شود. ادامه می‌دهید؟</p>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <x-btn variant="secondary" wire:click="$set('confirmGenerate', false)">انصراف</x-btn>
                    <x-btn icon="key" wire:click="generate" :loading="true">ساخت کلید</x-btn>
                </div>
            </div>
        @endif
    </x-modal>

    {{-- confirm: rotate --}}
    <x-modal wire:model="confirmRotate" title="تعویض کلید امضای لایسنس" size="sm">
        @if($confirmRotate)
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-red-50 p-4 text-red-700 ring-1 ring-red-600/10 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-400/20">
                    <x-icon name="alert-triangle" class="size-6 shrink-0" />
                    <div class="space-y-1 text-sm leading-6">
                        <p>کلید فعلی جایگزین می‌شود و پاکت‌های امضاشدهٔ قبلی بی‌اعتبار می‌شوند.</p>
                        <p class="font-bold">ترتیب امن: ۱) خطِ PACKAGE_LICENSE_PUBLIC_KEY را از همین صفحه کپی و در .env فروشگاه ثبت کنید ← ۲) بعد این‌جا کلید را تعویض کنید. اگر جاها برعکس شود، پکیج‌های فروشگاه تا ثبت کلید جدید بسته می‌مانند.</p>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <x-btn variant="secondary" wire:click="$set('confirmRotate', false)">انصراف</x-btn>
                    <x-btn variant="danger" icon="refresh-cw" wire:click="rotate" :loading="true">تعویض کلید</x-btn>
                </div>
            </div>
        @endif
    </x-modal>

    {{-- confirm: clear cache --}}
    <x-modal wire:model="confirmClearCache" title="پاک‌سازی کش پیکربندی" size="sm">
        @if($confirmClearCache)
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-amber-50 p-4 text-amber-700 ring-1 ring-amber-600/10 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-400/20">
                    <x-icon name="refresh-cw" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">فایل bootstrap/cache/config.php حذف می‌شود تا .env از نو خوانده شود. این عملیات ایمن است؛ ادامه می‌دهید؟</p>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <x-btn variant="secondary" wire:click="$set('confirmClearCache', false)">انصراف</x-btn>
                    <x-btn icon="refresh-cw" wire:click="clearConfigCache" :loading="true">پاک‌سازی</x-btn>
                </div>
            </div>
        @endif
    </x-modal>
</div>
