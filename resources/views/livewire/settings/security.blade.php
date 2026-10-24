@php($errBag = $errors ?? new \Illuminate\Support\ViewErrorBag)
<div class="space-y-6">
    {{-- header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-black text-zinc-900 dark:text-zinc-50">امنیت و امکانات</h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">هر قابلیت را جداگانه فعال/غیرفعال و شخصی‌سازی کنید.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.settings.index') }}" wire:navigate>
                <x-btn variant="secondary" icon="settings">تنظیمات عمومی</x-btn>
            </a>
        </div>
    </div>

    <form wire:submit="save" class="space-y-6">
        <x-tabs :tabs="[
            ['id' => 'security', 'label' => 'امنیت', 'icon' => 'shield'],
            ['id' => 'features', 'label' => 'امکانات', 'icon' => 'sparkles'],
        ]">
            <x-slot:security>
                <div class="space-y-6">
                    {{-- ربات‌گیر (کپچا) --}}
                    <x-card title="ربات‌گیر (کپچای خودمیزبان)" subtitle="سؤال امنیتی ریاضی بدون سرویس خارجی — سازگار با اینترنت ایران.">
                        <div class="space-y-5">
                            <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                                <x-toggle wire:model="form.sec_captcha_enabled" label="فعال‌سازی ربات‌گیر (کلید اصلی)" id="sec_captcha_enabled" />
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="rounded-xl bg-zinc-50 p-3.5 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                                    <x-toggle wire:model="form.sec_captcha_admin_login" label="فرم ورود مدیر" id="sec_captcha_admin_login" />
                                </div>
                                <div class="rounded-xl bg-zinc-50 p-3.5 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                                    <x-toggle wire:model="form.sec_captcha_admin_register" label="فرم ثبت‌نام مدیر" id="sec_captcha_admin_register" />
                                </div>
                                <div class="rounded-xl bg-zinc-50 p-3.5 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                                    <x-toggle wire:model="form.sec_captcha_customer_login" label="ورود مشتریان (کد آپدیت)" id="sec_captcha_customer_login" />
                                </div>
                                <div class="rounded-xl bg-zinc-50 p-3.5 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                                    <x-toggle wire:model="form.sec_captcha_comments" label="فرم دیدگاه‌های فروشگاه" id="sec_captcha_comments" />
                                </div>
                            </div>

                            <x-field label="تلهٔ زمان (حداقل ثانیهٔ پاسخ‌دادن)" for="sec_captcha_min_seconds" hint="پاسخ سریع‌تر از این حد = ربات. صفر یعنی خاموش.">
                                <x-input id="sec_captcha_min_seconds" wire:model="form.sec_captcha_min_seconds" type="number" min="0" max="60" dir="ltr" class="w-28" />
                            </x-field>
                        </div>
                    </x-card>

                    {{-- هانی‌پات --}}
                    <x-card title="هانی‌پات (تلهٔ فیلد مخفی)" subtitle="فیلد مخفی website که ربات‌های ساده پر می‌کنند — سبک‌ترین لایهٔ ضدربات.">
                        <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                            <x-toggle wire:model="form.sec_honeypot_enabled" label="فعال‌سازی هانی‌پات روی فرم‌ها" id="sec_honeypot_enabled" />
                        </div>
                    </x-card>

                    {{-- قفل تلاش‌ها --}}
                    <x-card title="قفل تلاش‌های ناموفق" subtitle="سقف تلاش در پنجرهٔ زمانی — ضد جست‌وجوی کد آپدیت و گذرواژه.">
                        <div class="space-y-5">
                            <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                                <x-toggle wire:model="form.sec_lockout_enabled" label="فعال‌سازی قفل تلاش‌ها (کلید اصلی)" id="sec_lockout_enabled" />
                            </div>

                            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                                <x-field label="ورود مدیر — سقف تلاش" for="sec_lockout_admin_max">
                                    <x-input id="sec_lockout_admin_max" wire:model="form.sec_lockout_admin_max" type="number" min="1" max="100" dir="ltr" />
                                </x-field>
                                <x-field label="ورود مدیر — پنجره (دقیقه)" for="sec_lockout_admin_decay">
                                    <x-input id="sec_lockout_admin_decay" wire:model="form.sec_lockout_admin_decay" type="number" min="1" max="1440" dir="ltr" />
                                </x-field>
                                <div></div>

                                <x-field label="ورود مشتری — سقف تلاش" for="sec_lockout_customer_max">
                                    <x-input id="sec_lockout_customer_max" wire:model="form.sec_lockout_customer_max" type="number" min="1" max="100" dir="ltr" />
                                </x-field>
                                <x-field label="ورود مشتری — پنجره (دقیقه)" for="sec_lockout_customer_decay">
                                    <x-input id="sec_lockout_customer_decay" wire:model="form.sec_lockout_customer_decay" type="number" min="1" max="1440" dir="ltr" />
                                </x-field>
                                <div></div>

                                <x-field label="دیدگاه‌ها — سقف ارسال" for="sec_lockout_comment_max">
                                    <x-input id="sec_lockout_comment_max" wire:model="form.sec_lockout_comment_max" type="number" min="1" max="100" dir="ltr" />
                                </x-field>
                                <x-field label="دیدگاه‌ها — پنجره (دقیقه)" for="sec_lockout_comment_decay">
                                    <x-input id="sec_lockout_comment_decay" wire:model="form.sec_lockout_comment_decay" type="number" min="1" max="1440" dir="ltr" />
                                </x-field>
                            </div>
                        </div>
                    </x-card>

                    {{-- هدرهای امنیتی --}}
                    <x-card title="هدرهای امنیتی" subtitle="ضدکلیک‌جک، nosniff، Referrer-Policy و Permissions-Policy روی همهٔ پاسخ‌ها.">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                                <x-toggle wire:model="form.sec_headers_enabled" label="ارسال هدرهای امنیتی (کلید اصلی)" id="sec_headers_enabled" />
                            </div>
                            <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                                <x-toggle wire:model="form.sec_headers_hsts" label="HSTS (اجبار HTTPS در مرورگر پس از اولین بازدید)" id="sec_headers_hsts" />
                            </div>
                        </div>
                    </x-card>

                    {{-- ورود دومرحله‌ای --}}
                    <x-card title="ورود دومرحله‌ای مدیر (2FA)" subtitle="پس از ورود موفق، کد ۶ رقمی یک‌بارمصرف ارسال می‌شود.">
                        <div class="space-y-5">
                            <div class="rounded-xl bg-amber-50 p-4 text-xs leading-6 text-amber-800 ring-1 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-400/20">
                                قبل از فعال‌سازی: کانال ایمیل نیازمند «استفاده از تنظیمات ایمیل پنل» (صفحهٔ تنظیمات) یا SMTP سرور است؛ کانال پیامک نیازمند تنظیم پیامک و شمارهٔ مدیر. اگر هیچ کانالی کار نکند، کد پشتیبان در فایل laravel.log ثبت می‌شود.
                            </div>

                            <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                                <x-toggle wire:model="form.sec_2fa_enabled" label="فعال‌سازی ورود دومرحله‌ای مدیر" id="sec_2fa_enabled" />
                            </div>

                            <x-field label="کانال ارسال کد" for="sec_2fa_channel">
                                <x-select id="sec_2fa_channel" wire:model="form.sec_2fa_channel">
                                    <option value="email">ایمیل</option>
                                    <option value="sms">پیامک (شمارهٔ مدیر)</option>
                                    <option value="both">هر دو</option>
                                </x-select>
                            </x-field>
                        </div>
                    </x-card>

                    {{-- نقش‌های مدیر --}}
                    <x-card title="نقش‌های مدیر" subtitle="فقط کاربران با نقش «مدیر» به پنل دسترسی دارند؛ کاربر «اپراتور» محدود می‌شود.">
                        <div class="space-y-5">
                            <div class="rounded-xl bg-rose-50 p-4 text-xs leading-6 text-rose-800 ring-1 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20">
                                ⚠️ قبل از روشن‌کردن، نقش کاربر خودتان را در صفحهٔ «کاربران» روی مدیر (admin) بگذارید؛ وگرنه دسترسی پنل از دست می‌رود. (در آن صورت با خاموش‌کردن این کلید در جدول settings یا تنظیم نقش از دیتابیس قابل بازگشت است.)
                            </div>

                            <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                                <x-toggle wire:model="form.sec_roles_enabled" label="اجبار نقش مدیر برای ورود به پنل" id="sec_roles_enabled" />
                            </div>
                        </div>
                    </x-card>
                </div>
            </x-slot:security>

            <x-slot:features>
                <div class="space-y-6">
                    {{-- نوار اعلان --}}
                    <x-card title="نوار اعلان فروشگاه" subtitle="نوار بالای سایت برای اطلاع‌رسانی تخفیف/بروزرسانی — رنگ و متن دلخواه.">
                        <div class="space-y-5">
                            <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                                <x-toggle wire:model="form.feat_announcement_enabled" label="نمایش نوار اعلان در فروشگاه" id="feat_announcement_enabled" />
                            </div>

                            <div class="sm:col-span-2">
                                <x-field label="متن اعلان" for="feat_announcement_text">
                                    <x-input id="feat_announcement_text" wire:model="form.feat_announcement_text" placeholder="مثلاً: ۲۰٪ تخفیف ویژهٔ پایان هفته فعال شد! 🎉" />
                                </x-field>
                            </div>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <x-field label="رنگ پس‌زمینه" for="feat_announcement_bg">
                                    <div class="flex items-center gap-3">
                                        <input type="color" wire:model="form.feat_announcement_bg" class="h-10 w-16 shrink-0 cursor-pointer rounded-xl border border-zinc-300 bg-white p-1 dark:border-zinc-700 dark:bg-zinc-800" />
                                        <input type="text" wire:model="form.feat_announcement_bg" dir="ltr" placeholder="#10b981" class="input font-mono" />
                                    </div>
                                </x-field>

                                <x-field label="رنگ متن" for="feat_announcement_color">
                                    <div class="flex items-center gap-3">
                                        <input type="color" wire:model="form.feat_announcement_color" class="h-10 w-16 shrink-0 cursor-pointer rounded-xl border border-zinc-300 bg-white p-1 dark:border-zinc-700 dark:bg-zinc-800" />
                                        <input type="text" wire:model="form.feat_announcement_color" dir="ltr" placeholder="#ffffff" class="input font-mono" />
                                    </div>
                                </x-field>

                                <x-field label="لینک (اختیاری)" for="feat_announcement_link" hint="آدرس کامل مثل https://example.com">
                                    <x-input id="feat_announcement_link" wire:model="form.feat_announcement_link" dir="ltr" placeholder="https://…" icon="link" />
                                </x-field>

                                <x-field label="متن لینک (اختیاری)" for="feat_announcement_link_text">
                                    <x-input id="feat_announcement_link_text" wire:model="form.feat_announcement_link_text" placeholder="اطلاعات بیشتر" />
                                </x-field>
                            </div>

                            <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                                <x-toggle wire:model="form.feat_announcement_dismissible" label="بازدیدکننده بتواند نوار را ببندد" id="feat_announcement_dismissible" />
                            </div>
                        </div>
                    </x-card>

                    {{-- حالت تعمیر --}}
                    <x-card title="حالت تعمیر و نگهداری" subtitle="کل سایت 503 با پیام دلخواه؛ پنل، پرداخت و API همیشه باز می‌مانند.">
                        <div class="space-y-5">
                            <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                                <x-toggle wire:model="form.feat_maintenance_enabled" label="فعال‌سازی حالت تعمیر" id="feat_maintenance_enabled" />
                            </div>

                            <x-field label="پیام صفحهٔ تعمیر" for="feat_maintenance_message">
                                <x-textarea id="feat_maintenance_message" wire:model="form.feat_maintenance_message" rows="3" placeholder="سایت موقتاً در حال بروزرسانی است؛ کمی دیگر دوباره سر بزنید." />
                            </x-field>

                            <p class="text-xs leading-6 text-zinc-500 dark:text-zinc-400">
                                همیشه باز می‌مانند: پنل مدیریت، ورود مدیر، کال‌بک درگاه پرداخت، API نسخهٔ ۱، دانلود get-update. مدیر واردشده هم سایت را می‌بیند.
                            </p>
                        </div>
                    </x-card>

                    {{-- تغییرات عمومی --}}
                    <x-card title="صفحهٔ عمومی تغییرات (Changelog)" subtitle="تایم‌لاین نسخه‌های پکیج/محصول/قالب در آدرس /changelog.">
                        <div class="space-y-5">
                            <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                                <x-toggle wire:model="form.feat_changelog_enabled" label="نمایش صفحهٔ تغییرات + لینک در منوی فروشگاه" id="feat_changelog_enabled" />
                            </div>

                            <x-field label="عنوان صفحه" for="feat_changelog_title">
                                <x-input id="feat_changelog_title" wire:model="form.feat_changelog_title" placeholder="تغییرات و بروزرسانی‌ها" />
                            </x-field>
                        </div>
                    </x-card>

                    {{-- وضعیت لایسنس‌ها در داشبورد --}}
                    <x-card title="وضعیت لایسنس‌ها در داشبورد" subtitle="پنل‌های «در حال اتمام» و «منقضی‌شده» داشبورد مدیریت.">
                        <x-field label="آستانهٔ هشدار انقضا (روز)" for="feat_lic_expiry_days"
                                 hint="لایسنس‌های فعال با انقضای داخل این بازه، در بخش «در حال اتمام» داشبورد دیده می‌شوند.">
                            <x-input id="feat_lic_expiry_days" wire:model="form.feat_lic_expiry_days" type="number" min="1" max="365" dir="ltr" class="w-28" />
                        </x-field>
                    </x-card>

                    {{-- شخصی‌سازی فوتر --}}
                    <x-card title="شخصی‌سازی فوتر فروشگاه" subtitle="نام سایت از «تنظیمات عمومی» می‌آید؛ متن‌ها و شبکه‌های اجتماعی اینجا.">
                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-field label="متن کپی‌رایت (خالی = پیش‌فرض)" for="feat_footer_text">
                                <x-input id="feat_footer_text" wire:model="form.feat_footer_text" placeholder="© … تمامی حقوق محفوظ است." />
                            </x-field>

                            <x-field label="توضیح یک‌خطی زیر نام سایت (خالی = پیش‌فرض)" for="feat_footer_description">
                                <x-input id="feat_footer_description" wire:model="form.feat_footer_description" placeholder="مرکز توزیع پکیج و آپدیت پروژه‌ها" />
                            </x-field>

                            <x-field label="لینک تلگرام" for="feat_social_telegram">
                                <x-input id="feat_social_telegram" wire:model="form.feat_social_telegram" dir="ltr" placeholder="https://t.me/username" icon="send" />
                            </x-field>

                            <x-field label="لینک اینستاگرام" for="feat_social_instagram">
                                <x-input id="feat_social_instagram" wire:model="form.feat_social_instagram" dir="ltr" placeholder="https://instagram.com/username" icon="instagram" />
                            </x-field>

                            <x-field label="لینک واتس‌اپ" for="feat_social_whatsapp">
                                <x-input id="feat_social_whatsapp" wire:model="form.feat_social_whatsapp" dir="ltr" placeholder="https://wa.me/98912…" icon="message-circle" />
                            </x-field>

                            <x-field label="ایمیل نمایشی" for="feat_social_email">
                                <x-input id="feat_social_email" wire:model="form.feat_social_email" dir="ltr" placeholder="info@example.com" icon="mail" />
                            </x-field>
                        </div>
                    </x-card>

                    {{-- اعلان فروش --}}
                    <x-card title="اعلان پیامکی فروش به مدیر" subtitle="برای هر نوع فروش جداگانه — قالب‌ها از صفحهٔ «قالب‌های پیامک» قابل ویرایش‌اند.">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="rounded-xl bg-zinc-50 p-3.5 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                                <x-toggle wire:model="form.notify_sale_package" label="فروش پکیج" id="notify_sale_package" />
                            </div>
                            <div class="rounded-xl bg-zinc-50 p-3.5 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                                <x-toggle wire:model="form.notify_sale_product" label="فروش محصول (پروژه)" id="notify_sale_product" />
                            </div>
                            <div class="rounded-xl bg-zinc-50 p-3.5 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                                <x-toggle wire:model="form.notify_sale_template" label="فروش قالب" id="notify_sale_template" />
                            </div>
                            <div class="rounded-xl bg-zinc-50 p-3.5 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                                <x-toggle wire:model="form.notify_sale_subscription" label="خرید اشتراک" id="notify_sale_subscription" />
                            </div>
                        </div>
                        <p class="mt-4 text-xs leading-6 text-zinc-500 dark:text-zinc-400">
                            نیاز به فعال‌بودن سیستم پیامک + «اطلاع‌رسانی به مدیر» در صفحهٔ تنظیمات دارد.
                        </p>
                    </x-card>
                </div>
            </x-slot:features>
        </x-tabs>

        <div class="sticky bottom-4 z-10 flex justify-end">
            <x-btn type="submit" size="lg" icon="save">
                ذخیره تنظیمات
            </x-btn>
        </div>
    </form>
</div>
