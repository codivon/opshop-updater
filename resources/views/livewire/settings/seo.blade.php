@php
    $boolKeys = \App\Livewire\Settings\SeoSettings::BOOL_KEYS;
    $aiBots = \App\Support\Seo::AI_BOTS;
@endphp
<div class="space-y-6">
    {{-- header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-black text-zinc-900 dark:text-zinc-50">سئو</h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">تنظیمات کامل سئو: متاهای همهٔ صفحات، موتورهای جستجو، خزنده‌های هوش مصنوعی، سایت‌مپ و llms.txt.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('shop.home') }}" target="_blank" rel="noopener">
                <x-btn variant="secondary" icon="external-link">مشاهدهٔ فروشگاه</x-btn>
            </a>
            <a href="{{ route('admin.settings.index') }}" wire:navigate>
                <x-btn variant="secondary" icon="settings">تنظیمات عمومی</x-btn>
            </a>
        </div>
    </div>

    <form wire:submit="save" class="space-y-6">
        {{-- ==================== عمومی ==================== --}}
        <x-card title="عمومی" subtitle="قالب عنوان و توضیح پیش‌فرض — مبنای متاهای همهٔ صفحات فروشگاه.">
            <div class="space-y-5">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field label="قالب عنوان صفحات" for="seo_title_template" required
                             hint="دو متغیر %page% (عنوان صفحه) و %site% (نام سایت) را جای‌گذاری کنید.">
                        <x-input id="seo_title_template" wire:model="form.seo_title_template" dir="ltr"
                                 placeholder="%page% · %site%" class="{{ $errors->has('form.seo_title_template') ? 'input-error' : '' }}" />
                        @error('form.seo_title_template') <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                        <p class="mt-2 text-[11px] leading-5 text-zinc-400">مثال خروجی: <span class="font-mono" dir="ltr">{{ str_replace(['%page%', '%site%'], ['پکیج‌ها', \App\Support\Features::siteName()], $form['seo_title_template']) }}</span></p>
                    </x-field>

                    <x-field label="توضیح پیش‌فرض سایت" for="seo_default_desc" hint="وقتی صفحه‌ای توضیح اختصاصی نداشته باشد این متن در متا description می‌آید.">
                        <x-textarea id="seo_default_desc" wire:model="form.seo_default_desc" rows="3" />
                    </x-field>

                    <x-field label="توضیح معرفی سایت (برای موتورهای هوش مصنوعی)" for="seo_site_desc" hint="در اسکیمای سایت و llms.txt استفاده می‌شود؛ معرفی یک‌خطی کامل و گویا بنویسید.">
                        <x-textarea id="seo_site_desc" wire:model="form.seo_site_desc" rows="2" />
                    </x-field>

                    <x-field label="کلمات کلیدی" for="seo_keywords" hint="با کاما جدا کنید (اختیاری — موتورهای اصلی دیگر معیار رتبه‌بندی نیستند).">
                        <x-textarea id="seo_keywords" wire:model="form.seo_keywords" rows="2" />
                    </x-field>

                    <x-field label="تصویر پیش‌فرض اشتراک‌گذاری (OG Image)" for="seo_og_image" hint="آدرس کامل تصویر — وقتی صفحه تصویر اختصاصی نداشته باشد استفاده می‌شود؛ پیشنهاد: ۱۲۰۰×۶۳۰.">
                        <x-input id="seo_og_image" wire:model="form.seo_og_image" dir="ltr" placeholder="https://…/og-image.jpg" class="font-mono text-xs" />
                    </x-field>

                    <x-field label="دامنهٔ canonical" for="seo_canonical_host" hint="اگر سایت با چند دامنه در دسترس است، دامنهٔ اصلی را بدهید تا همهٔ canonicalها به آن بازنویسی شوند.">
                        <x-input id="seo_canonical_host" wire:model="form.seo_canonical_host" dir="ltr" placeholder="https://opashop.ir" class="font-mono text-xs" />
                        @error('form.seo_canonical_host') <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                    </x-field>

                    <x-field label="شناسهٔ توییتر/X" for="seo_twitter_handle" hint="اختیاری — برای Twitter Card؛ با @ شروع کنید.">
                        <x-input id="seo_twitter_handle" wire:model="form.seo_twitter_handle" dir="ltr" placeholder="@username" class="font-mono text-xs" />
                    </x-field>
                </div>

                <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                    <p class="text-xs font-bold text-zinc-600 dark:text-zinc-300">صفحات حساب‌کاربری، پرداخت، دانلود و پنل مدیریت به‌صورت خودکار <span class="font-mono" dir="ltr">noindex, nofollow</span> هستند — کاری لازم نیست انجام دهید.</p>
                </div>
            </div>
        </x-card>

        {{-- ==================== سئوی هر صفحه ==================== --}}
        <x-card title="سئوی اختصاصی صفحات فروشگاه" subtitle="عنوان و توضیح هر صفحه — خالی بگذارید تا پیش‌فرض هوشمند استفاده شود.">
            <div class="space-y-6">
                @foreach([
                    'home' => ['صفحهٔ اصلی فروشگاه', 'home'],
                    'packages' => ['فهرست پکیج‌ها', 'package'],
                    'products' => ['فهرست پروژه‌های آماده', 'rocket'],
                    'templates' => ['فهرست قالب‌های آماده', 'layout-template'],
                    'subscriptions' => ['طرح‌های اشتراک', 'crown'],
                    'changelog' => ['تاریخچهٔ تغییرات', 'history'],
                ] as $key => [$label, $icon])
                    <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                        <p class="mb-3 flex items-center gap-2 text-sm font-black text-zinc-700 dark:text-zinc-200">
                            <x-icon name="{{ $icon }}" class="size-4 text-brand-600" />
                            {{ $label }}
                        </p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field label="عنوان (Title)" for="seo_page_{{ $key }}_title">
                                <x-input id="seo_page_{{ $key }}_title" wire:model="form.seo_page_{{ $key }}_title" placeholder="خالی = پیش‌فرض" />
                            </x-field>
                            <x-field label="توضیح (Description)" for="seo_page_{{ $key }}_desc" hint="۱۵۰ تا ۱۶۰ کاراکتر گویا با کلمات کلیدی.">
                                <x-textarea id="seo_page_{{ $key }}_desc" wire:model="form.seo_page_{{ $key }}_desc" rows="2" placeholder="خالی = پیش‌فرض" />
                            </x-field>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>

        {{-- ==================== ابزارهای وبمستران ==================== --}}
        <x-card title="تأیید مالکیت (ابزارهای وبمستران)" subtitle="کد تأیید را بچسبانید تا در head همهٔ صفحات به‌صورت متا درج شود — سپس در ابزار مربوطه «verify by meta tag» را بزنید.">
            <div class="grid gap-5 sm:grid-cols-3">
                <x-field label="Google Search Console" for="seo_verif_google" hint="مقدار content از تگ google-site-verification">
                    <x-input id="seo_verif_google" wire:model="form.seo_verif_google" dir="ltr" class="font-mono text-xs" />
                </x-field>
                <x-field label="Bing Webmaster" for="seo_verif_bing" hint="مقدار content از تگ msvalidate.01">
                    <x-input id="seo_verif_bing" wire:model="form.seo_verif_bing" dir="ltr" class="font-mono text-xs" />
                </x-field>
                <x-field label="Yandex Webmaster" for="seo_verif_yandex" hint="مقدار content از تگ yandex-verification">
                    <x-input id="seo_verif_yandex" wire:model="form.seo_verif_yandex" dir="ltr" class="font-mono text-xs" />
                </x-field>
            </div>
        </x-card>

        {{-- ==================== خزنده‌های هوش مصنوعی ==================== --}}
        <x-card title="خزنده‌های هوش مصنوعی (جستجوی AI)" subtitle="برای رتبه گرفتن در پاسخ‌های هوش مصنوعی (ChatGPT، Perplexity، Claude و...) مجاز بگذارید — در robots.txt اعمال می‌شود.">
            <div class="space-y-4">
                <div class="rounded-xl bg-brand-50 p-4 text-xs leading-6 text-brand-800 ring-1 ring-brand-600/20 dark:bg-brand-500/10 dark:text-brand-300 dark:ring-brand-400/20">
                    خزنده‌های AI محتوای سایت را می‌خوانند و در پاسخ‌هایشان نقل می‌کنند؛ مجاز بودن آن‌ها شانس دیده‌شدن پکیج‌ها در «جستجوی هوش مصنوعی» را بالا می‌برد.
                    علاوه بر این، فایل <span class="font-mono" dir="ltr">llms.txt</span> هم به‌صورت خودکار ساخته می‌شود — خلاصهٔ ساخت‌یافتهٔ سایت برای مدل‌های زبانی.
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($aiBots as $key => $bot)
                        <div class="flex items-center justify-between gap-3 rounded-xl bg-zinc-50 p-3.5 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                            <div class="min-w-0">
                                <p class="font-mono text-xs font-bold text-zinc-700 dark:text-zinc-200" dir="ltr">{{ $bot }}</p>
                                <p class="mt-0.5 text-[10px] text-zinc-400">{{ in_array($key, $boolKeys) && $form[$key] ? 'مجاز' : 'مسدود' }}</p>
                            </div>
                            <x-toggle wire:model="form.{{ $key }}" id="seo_{{ $key }}" label="" />
                        </div>
                    @endforeach
                </div>
            </div>
        </x-card>

        {{-- ==================== سایت‌مپ و llms ==================== --}}
        <x-card title="سایت‌مپ و llms.txt" subtitle="نقشهٔ سایت پویا از دیتابیس ساخته می‌شود (کش ۳۰ دقیقه) و در robots.txt هم معرفی می‌شود.">
            <div class="space-y-5">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                        <x-toggle wire:model="form.seo_sitemap_enabled" label="سایت‌مپ (sitemap.xml)" id="seo_sitemap_enabled" />
                        <p class="mt-2 text-[11px] leading-5 text-zinc-500 dark:text-zinc-400">همهٔ صفحات اصلی + تک‌تک پکیج‌ها/پروژه‌ها/قالب‌های فعال با تاریخ آخرین به‌روزرسانی.</p>
                        <a href="{{ url('sitemap.xml') }}" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-1 text-[11px] font-bold text-brand-600 hover:underline" dir="ltr">sitemap.xml <x-icon name="external-link" class="size-3" /></a>
                    </div>

                    <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                        <x-toggle wire:model="form.seo_llms_enabled" label="llms.txt (برای AI)" id="seo_llms_enabled" />
                        <p class="mt-2 text-[11px] leading-5 text-zinc-500 dark:text-zinc-400">خلاصهٔ سایت + فهرست کامل پکیج‌ها برای مدل‌های هوش مصنوعی.</p>
                        <a href="{{ url('llms.txt') }}" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-1 text-[11px] font-bold text-brand-600 hover:underline" dir="ltr">llms.txt <x-icon name="external-link" class="size-3" /></a>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                        <x-toggle wire:model="form.seo_schema_enabled" label="داده‌های ساخت‌یافته (JSON-LD)" id="seo_schema_enabled" />
                        <p class="mt-2 text-[11px] leading-5 text-zinc-500 dark:text-zinc-400">اسکیمای WebSite/Organization در همهٔ صفحات + SoftwareApplication وBreadcrumb در صفحات تک‌پکیج/پروژه/قالب — مهم‌ترین ابزار برای نتایج غنی گوگل و درک بهتر AI.</p>
                    </div>

                    <x-field label="متن سفارشی llms.txt (اختیاری)" for="seo_llms_text" hint="خالی = ساخت خودکار. اگر خواستید خودتان بنویسید، فرمت استاندارد llms.txt را رعایت کنید.">
                        <x-textarea id="seo_llms_text" wire:model="form.seo_llms_text" rows="4" dir="ltr" class="font-mono text-xs" />
                    </x-field>
                </div>
            </div>
        </x-card>

        {{-- سئوی هر پکیج/محصول/قالب — راهنما --}}
        <x-card title="سئوی تک‌تک پکیج‌ها، پروژه‌ها و قالب‌ها" subtitle="عنوان و توضیح متای اختصاصی هر رکورد در فرم ویرایش خودِ آن پکیج/محصول/قالب در پنل پر می‌شود.">
            <div class="grid gap-3 sm:grid-cols-3">
                <a href="{{ route('admin.packages.index') }}" wire:navigate
                   class="group flex items-center gap-3 rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/60 transition-all hover:-translate-y-0.5 hover:ring-brand-300 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-100 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">
                        <x-icon name="package" class="size-5" />
                    </span>
                    <span>
                        <span class="block text-sm font-black text-zinc-700 group-hover:text-brand-600 dark:text-zinc-200">پکیج‌ها</span>
                        <span class="mt-0.5 block text-[11px] text-zinc-400">در صفحهٔ هر پکیج ← ویرایش</span>
                    </span>
                </a>
                <a href="{{ route('admin.products.index') }}" wire:navigate
                   class="group flex items-center gap-3 rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/60 transition-all hover:-translate-y-0.5 hover:ring-brand-300 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-100 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">
                        <x-icon name="rocket" class="size-5" />
                    </span>
                    <span>
                        <span class="block text-sm font-black text-zinc-700 group-hover:text-brand-600 dark:text-zinc-200">محصولات (پروژه‌ها)</span>
                        <span class="mt-0.5 block text-[11px] text-zinc-400">در مودال ویرایش هر محصول</span>
                    </span>
                </a>
                <a href="{{ route('admin.templates.index') }}" wire:navigate
                   class="group flex items-center gap-3 rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/60 transition-all hover:-translate-y-0.5 hover:ring-brand-300 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-100 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">
                        <x-icon name="layout-template" class="size-5" />
                    </span>
                    <span>
                        <span class="block text-sm font-black text-zinc-700 group-hover:text-brand-600 dark:text-zinc-200">قالب‌ها</span>
                        <span class="mt-0.5 block text-[11px] text-zinc-400">در مودال ویرایش هر قالب</span>
                    </span>
                </a>
            </div>
        </x-card>

        <div class="sticky bottom-4 z-10 flex justify-end">
            <x-btn type="submit" size="lg" icon="save">ذخیره تنظیمات سئو</x-btn>
        </div>
    </form>
</div>
