@php($errBag = $errors ?? new \Illuminate\Support\ViewErrorBag)
<div class="space-y-6">
    {{-- header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-black text-zinc-900 dark:text-zinc-50">تنظیمات</h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">تنظیمات عمومی سایت، ایمیل و ابزارهای نگهداری سیستم.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.settings.gateways') }}" wire:navigate>
                <x-btn variant="secondary" icon="credit-card">مدیریت درگاه‌های پرداخت</x-btn>
            </a>
            <x-btn variant="secondary" icon="refresh-cw" wire:click="$set('confirmCache', true)">پاک‌سازی کش</x-btn>
            <x-btn variant="secondary" icon="zap" wire:click="$set('confirmOptimize', true)">بهینه‌سازی</x-btn>
        </div>
    </div>

    <form wire:submit="save" class="space-y-6">
        {{-- general settings --}}
        <x-card title="تنظیمات عمومی" subtitle="اطلاعات پایه سایت که در پنل و ایمیل‌های سیستمی استفاده می‌شود.">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field label="نام سایت" for="site_name" required>
                    <x-input id="site_name" wire:model="form.site_name" placeholder="پنل مدیریت آپدیت" icon="globe" class="{{ $errBag->has('form.site_name') ? 'input-error' : '' }}" />
                </x-field>

                <x-field label="لوگو (URL)" for="site_logo" hint="آدرس تصویر لوگوی سایت (اختیاری).">
                    <x-input id="site_logo" wire:model="form.site_logo" placeholder="https://example.com/logo.png" icon="image" dir="ltr" class="{{ $errBag->has('form.site_logo') ? 'input-error' : '' }}" />
                </x-field>

                <div class="sm:col-span-2">
                    <x-field label="توضیحات کوتاه سایت" for="site_description">
                        <x-textarea id="site_description" wire:model="form.site_description" rows="3" placeholder="توضیح کوتاهی درباره سرویس…" class="{{ $errBag->has('form.site_description') ? 'input-error' : '' }}" />
                    </x-field>
                </div>

                <x-field label="رنگ اصلی تم" for="theme_color" hint="کد رنگ برند سایت.">
                    <div class="flex items-center gap-3">
                        <input type="color" id="theme_color" wire:model="form.theme_color"
                               class="h-10 w-16 shrink-0 cursor-pointer rounded-xl border border-zinc-300 bg-white p-1 dark:border-zinc-700 dark:bg-zinc-800" />
                        <input type="text" wire:model="form.theme_color" dir="ltr" placeholder="#10b981"
                               class="input font-mono {{ $errBag->has('form.theme_color') ? 'input-error' : '' }}" />
                    </div>
                </x-field>
            </div>
        </x-card>

        {{-- email settings --}}
        <x-card title="تنظیمات ایمیل" subtitle="تنظیمات سرور SMTP برای ارسال ایمیل‌های سیستمی.">
            <div class="space-y-5">
                <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                    <x-toggle wire:model="form.mail_enabled" label="فعال‌سازی ارسال ایمیل" id="mail_enabled" />
                </div>

                <div class="rounded-xl bg-amber-50 p-4 ring-1 ring-amber-600/20 dark:bg-amber-500/10 dark:ring-amber-400/20">
                    <x-toggle wire:model="form.mail_use_settings" label="استفاده از همین تنظیمات SMTP برای ارسال ایمیل‌های سیستمی (مثل کد ورود دومرحله‌ای)" id="mail_use_settings" />
                    <p class="mt-2 text-xs leading-6 text-amber-700 dark:text-amber-400">
                        اگر روشن باشد، ایمیل‌های سیستمی با تنظیمات همین صفحه ارسال می‌شوند؛ در غیر این صورت از پیکربندی .env سرور استفاده می‌شود.
                    </p>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field label="آدرس ایمیل فرستنده" for="mail_from_address">
                        <x-input id="mail_from_address" wire:model="form.mail_from_address" type="email" placeholder="no-reply@example.com" icon="mail" dir="ltr" class="{{ $errBag->has('form.mail_from_address') ? 'input-error' : '' }}" />
                    </x-field>

                    <x-field label="نام فرستنده" for="mail_from_name">
                        <x-input id="mail_from_name" wire:model="form.mail_from_name" placeholder="پنل مدیریت آپدیت" icon="user" class="{{ $errBag->has('form.mail_from_name') ? 'input-error' : '' }}" />
                    </x-field>

                    <x-field label="SMTP Host" for="mail_host">
                        <x-input id="mail_host" wire:model="form.mail_host" placeholder="smtp.example.com" icon="server" dir="ltr" class="{{ $errBag->has('form.mail_host') ? 'input-error' : '' }}" />
                    </x-field>

                    <x-field label="SMTP Port" for="mail_port">
                        <x-input id="mail_port" wire:model="form.mail_port" type="number" placeholder="587" icon="lock" dir="ltr" class="{{ $errBag->has('form.mail_port') ? 'input-error' : '' }}" />
                    </x-field>

                    <x-field label="SMTP Username" for="mail_username">
                        <x-input id="mail_username" wire:model="form.mail_username" placeholder="username" icon="user" dir="ltr" class="{{ $errBag->has('form.mail_username') ? 'input-error' : '' }}" />
                    </x-field>

                    <x-field label="SMTP Password" for="mail_password">
                        <x-input id="mail_password" wire:model="form.mail_password" type="password" placeholder="••••••••" icon="key" dir="ltr" class="{{ $errBag->has('form.mail_password') ? 'input-error' : '' }}" />
                    </x-field>
                </div>
            </div>
        </x-card>

        {{-- payment settings --}}
        <x-card id="payment" title="تنظیمات پرداخت" subtitle="پروکسی شاپرک و عیب‌یابی اتصال درگاه‌های بانکی.">
            <div class="space-y-5">
                <div class="rounded-xl bg-amber-50 p-4 text-xs leading-6 text-amber-800 ring-1 ring-amber-600/10 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/20">
                    <div class="flex items-start gap-2">
                        <x-icon name="info" class="size-4.5 shrink-0" />
                        <p><b>محدودیت جغرافیایی شاپرک:</b> شاپرک (سامان، سپهر، سداد و…) فقط به IPهای داخل ایران پاسخ می‌دهد. اگر هاست شما خارج از ایران است، تمام درخواست‌های شاپرک Timeout می‌شوند. راه‌حل: یک <b>پروکسی یا سرور واسط ایرانی</b> تهیه کنید و آدرسش را اینجا ثبت کنید — همه درخواست‌های درگاه‌های شاپرکی (سامان کلاسیک، سامان SEP، سپهر) از آن عبور خواهند کرد.</p>
                    </div>
                </div>

                <x-field label="پروکسی شاپرک (اختیاری)" for="payment_shaparak_proxy" hint="مثال: http://IP:PORT یا http://user:pass@IP:PORT یا socks5://IP:PORT">
                    <x-input id="payment_shaparak_proxy" wire:model="form.payment_shaparak_proxy" placeholder="socks5://1.2.3.4:1080" icon="globe" dir="ltr" class="{{ $errBag->has('form.payment_shaparak_proxy') ? 'input-error' : '' }}" />
                </x-field>

                <div class="flex flex-wrap items-center gap-3">
                    <x-btn variant="secondary" icon="activity" wire:click="testShaparakConnection" :loading="true">تست اتصال به شاپرک</x-btn>
                    <span class="text-xs text-zinc-500">DNS + TCP + HTTPS به sep.shaparak.ir — با پروکسی ثبت‌شده (در صورت وجود)</span>
                </div>

                @if($shaparakTest)
                    <div class="rounded-xl p-4 ring-1 {{ $shaparakTest['ok'] ? 'bg-brand-50 text-brand-800 ring-brand-600/10 dark:bg-brand-500/10 dark:text-brand-300 dark:ring-brand-400/20' : 'bg-rose-50 text-rose-800 ring-rose-600/10 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-400/20' }}">
                        <div class="flex items-start gap-2.5">
                            <x-icon name="{{ $shaparakTest['ok'] ? 'check-circle' : 'alert-triangle' }}" class="size-5 shrink-0" />
                            <div class="space-y-1">
                                <p class="text-sm font-black">{{ $shaparakTest['title'] }}</p>
                                <p class="text-xs leading-6">{{ $shaparakTest['message'] }}</p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </x-card>

        {{-- sms settings --}}
        <x-card id="sms" title="تنظیمات پیامک" subtitle="سیستم پیامک برای خرید، اشتراک، تمدید و انقضا — با ۵ درایور ایرانی.">
            <div class="space-y-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                        <x-toggle wire:model="form.sms_enabled" label="فعال‌سازی سیستم پیامک" id="sms_enabled" />
                    </div>
                    <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                        <x-toggle wire:model="form.sms_notify_admin" label="اطلاع‌رسانی خریدها به مدیر" id="sms_notify_admin" />
                    </div>
                </div>

                <div class="grid gap-5 sm:grid-cols-3">
                    <x-field label="درایور پیامک" for="sms_driver">
                        <select id="sms_driver" wire:model.live="form.sms_driver" class="input">
                            @foreach(\App\Models\SmsTemplate::DRIVERS as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-field>

                    <x-field label="شماره موبایل مدیر" for="sms_admin_mobile" hint="برای اطلاع‌رسانی خریدها (اگر فعال باشد).">
                        <x-input id="sms_admin_mobile" wire:model="form.sms_admin_mobile" placeholder="09123456789" dir="ltr" class="{{ $errBag->has('form.sms_admin_mobile') ? 'input-error' : '' }}" />
                    </x-field>

                    <x-field label="هشدار چند روز قبل از انقضا؟" for="sms_expire_days" hint="پیامک «روبه‌انقضا» به مشتری ارسال می‌شود.">
                        <x-input id="sms_expire_days" wire:model="form.sms_expire_days" type="number" min="1" max="60" placeholder="3" dir="ltr" class="{{ $errBag->has('form.sms_expire_days') ? 'input-error' : '' }}" />
                    </x-field>
                </div>

                {{-- credentials per driver --}}
                <div class="space-y-4">
                    @if($form['sms_driver'] === 'kavenegar')
                        <div class="rounded-xl border border-brand-300/50 bg-brand-50/40 p-4 dark:border-brand-400/20 dark:bg-brand-500/5">
                            <p class="mb-3 text-sm font-bold text-zinc-800 dark:text-zinc-100">🟢 کاوه‌نگار — <span class="text-xs font-medium text-zinc-500">ارسال پترنی (Verify Lookup) + متن خام</span></p>
                            <x-field label="کلید API" for="sms_kavenegar_apikey" hint="از پنل کاوه‌نگار: بخش API Key.">
                                <x-input id="sms_kavenegar_apikey" wire:model="form.sms_kavenegar_apikey" placeholder="کلید API…" dir="ltr" class="{{ $errBag->has('form.sms_kavenegar_apikey') ? 'input-error' : '' }}" />
                            </x-field>
                        </div>
                    @elseif($form['sms_driver'] === 'melipayamak')
                        <div class="rounded-xl border border-brand-300/50 bg-brand-50/40 p-4 dark:border-brand-400/20 dark:bg-brand-500/5">
                            <p class="mb-3 text-sm font-bold text-zinc-800 dark:text-zinc-100">🟢 ملی‌پیامک — <span class="text-xs font-medium text-zinc-500">ارسال پترنی (BaseServiceNumber) + متن خام</span></p>
                            <div class="grid gap-4 sm:grid-cols-3">
                                <x-field label="نام کاربری" for="sms_melipayamak_username">
                                    <x-input id="sms_melipayamak_username" wire:model="form.sms_melipayamak_username" dir="ltr" />
                                </x-field>
                                <x-field label="رمز عبور" for="sms_melipayamak_password">
                                    <x-input id="sms_melipayamak_password" wire:model="form.sms_melipayamak_password" type="password" dir="ltr" />
                                </x-field>
                                <x-field label="شماره خط فرستنده" for="sms_melipayamak_from" hint="مثلاً 5000…">
                                    <x-input id="sms_melipayamak_from" wire:model="form.sms_melipayamak_from" dir="ltr" />
                                </x-field>
                            </div>
                        </div>
                    @elseif($form['sms_driver'] === 'ippanel')
                        <div class="rounded-xl border border-brand-300/50 bg-brand-50/40 p-4 dark:border-brand-400/20 dark:bg-brand-500/5">
                            <p class="mb-3 text-sm font-bold text-zinc-800 dark:text-zinc-100">🟢 آی‌پی‌پنل — <span class="text-xs font-medium text-zinc-500">فقط ارسال پترنی؛ کد پترن هر قالب را در صفحه قالب‌ها وارد کنید</span></p>
                            <div class="grid gap-4 sm:grid-cols-3">
                                <x-field label="نام کاربری" for="sms_ippanel_username">
                                    <x-input id="sms_ippanel_username" wire:model="form.sms_ippanel_username" dir="ltr" />
                                </x-field>
                                <x-field label="رمز عبور" for="sms_ippanel_password">
                                    <x-input id="sms_ippanel_password" wire:model="form.sms_ippanel_password" type="password" dir="ltr" />
                                </x-field>
                                <x-field label="خط فرستنده (Originator)" for="sms_ippanel_from">
                                    <x-input id="sms_ippanel_from" wire:model="form.sms_ippanel_from" dir="ltr" />
                                </x-field>
                            </div>
                        </div>
                    @elseif($form['sms_driver'] === 'farazsms')
                        <div class="rounded-xl border border-brand-300/50 bg-brand-50/40 p-4 dark:border-brand-400/20 dark:bg-brand-500/5">
                            <p class="mb-3 text-sm font-bold text-zinc-800 dark:text-zinc-100">🟢 فراز اس‌ام‌اس — <span class="text-xs font-medium text-zinc-500">فقط ارسال پترنی (api.iranpayamak.com)</span></p>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <x-field label="کلید API" for="sms_farazsms_apikey" hint="از پنل فراز اس‌ام‌اس / ایوان پیامک.">
                                    <x-input id="sms_farazsms_apikey" wire:model="form.sms_farazsms_apikey" dir="ltr" />
                                </x-field>
                                <x-field label="شماره خط" for="sms_farazsms_from">
                                    <x-input id="sms_farazsms_from" wire:model="form.sms_farazsms_from" dir="ltr" />
                                </x-field>
                            </div>
                        </div>
                    @elseif($form['sms_driver'] === 'idehpardazan')
                        <div class="rounded-xl border border-brand-300/50 bg-brand-50/40 p-4 dark:border-brand-400/20 dark:bg-brand-500/5">
                            <p class="mb-3 text-sm font-bold text-zinc-800 dark:text-zinc-100">🟢 ایده پردازان — <span class="text-xs font-medium text-zinc-500">فقط ارسال پترنی (UltraFastSend / TemplateId)</span></p>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <x-field label="کلید API (UserApiKey)" for="sms_idehpardazan_apikey">
                                    <x-input id="sms_idehpardazan_apikey" wire:model="form.sms_idehpardazan_apikey" dir="ltr" />
                                </x-field>
                                <x-field label="SecretKey" for="sms_idehpardazan_secretkey">
                                    <x-input id="sms_idehpardazan_secretkey" wire:model="form.sms_idehpardazan_secretkey" dir="ltr" />
                                </x-field>
                            </div>
                        </div>
                    @endif

                    <div class="flex flex-wrap items-center gap-3 rounded-xl bg-amber-50 p-4 text-xs leading-6 text-amber-800 ring-1 ring-amber-600/10 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/20">
                        <x-icon name="info" class="size-4.5 shrink-0" />
                        <p>متن قالب‌ها و <b>کد پترن</b> هر درایور را از <a href="{{ route('admin.sms.templates') }}" class="font-bold underline underline-offset-2">قالب‌های پیامک ←</a> مدیریت کنید. نتیجه ارسال‌ها در <a href="{{ route('admin.sms.logs') }}" class="font-bold underline underline-offset-2">لاگ پیامک‌ها</a> ثبت می‌شود. برای پیامک‌های روبه‌انقضا/منقضی‌شده یک کران‌جاب روزانه روی <code dir="ltr" class="rounded bg-amber-100/70 px-1 font-mono dark:bg-amber-500/10">php artisan schedule:run</code> تنظیم کنید.</p>
                    </div>
                </div>
            </div>
        </x-card>

        {{-- save --}}
        <div class="flex items-center justify-end gap-2">
            <x-btn type="submit" icon="save" :loading="true">ذخیره تنظیمات</x-btn>
        </div>
    </form>

    {{-- clear cache confirm --}}
    <x-modal wire:model="confirmCache" title="پاک‌سازی کش سیستم" size="sm">
        @if($confirmCache)
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-amber-50 p-4 text-amber-700 ring-1 ring-amber-600/10 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-400/20">
                    <x-icon name="refresh-cw" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">کش برنامه، تنظیمات و ویوها پاک می‌شود. این عملیات ایمن است؛ ادامه می‌دهید؟</p>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <x-btn variant="secondary" wire:click="$set('confirmCache', false)">انصراف</x-btn>
                    <x-btn icon="refresh-cw" wire:click="clearCache" :loading="true">پاک‌سازی کش</x-btn>
                </div>
            </div>
        @endif
    </x-modal>

    {{-- optimize confirm --}}
    <x-modal wire:model="confirmOptimize" title="بهینه‌سازی سیستم" size="sm">
        @if($confirmOptimize)
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-brand-50 p-4 text-brand-700 ring-1 ring-brand-600/10 dark:bg-brand-500/10 dark:text-brand-400 dark:ring-brand-400/20">
                    <x-icon name="zap" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">تنظیمات، مسیرها و ویوها کش می‌شوند تا سرعت برنامه بالا برود؛ ادامه می‌دهید؟</p>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <x-btn variant="secondary" wire:click="$set('confirmOptimize', false)">انصراف</x-btn>
                    <x-btn icon="zap" wire:click="optimize" :loading="true">بهینه‌سازی</x-btn>
                </div>
            </div>
        @endif
    </x-modal>
</div>
