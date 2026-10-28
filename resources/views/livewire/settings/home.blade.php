@php($errBag = $errors ?? new \Illuminate\Support\ViewErrorBag)
<div class="space-y-6">

    {{-- header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-black text-zinc-900 dark:text-zinc-50">صفحهٔ اصلی فروشگاه</h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">ویجت‌ها و محتوای صفحهٔ اصلی را فعال/غیرفعال و شخصی‌سازی کنید — معرفی، آمار، نظرات و فرم تماس.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.settings.index') }}" wire:navigate>
                <x-btn variant="secondary" icon="settings">تنظیمات عمومی</x-btn>
            </a>
            <a href="{{ route('shop.home') }}" target="_blank" rel="noopener">
                <x-btn variant="secondary" icon="external-link">مشاهده صفحه</x-btn>
            </a>
        </div>
    </div>

    <form wire:submit="save" class="space-y-6">
        <x-tabs :tabs="[
            ['id' => 'sections', 'label' => 'بخش‌ها', 'icon' => 'layout-grid'],
            ['id' => 'hero',     'label' => 'هیرو', 'icon' => 'sparkles'],
            ['id' => 'about',    'label' => 'معرفی', 'icon' => 'users'],
            ['id' => 'trust',    'label' => 'اعتماد', 'icon' => 'badge-check'],
            ['id' => 'contact',  'label' => 'تماس با ما', 'icon' => 'message-circle'],
            ['id' => 'footer',   'label' => 'فوتر', 'icon' => 'panel-top'],
        ]">

            {{-- ==================== بخش‌ها (روشن/خاموش) ==================== --}}
            <x-slot:sections>
                <div class="space-y-6">
                    <x-card title="نمایش بخش‌ها" subtitle="هر ویجت صفحهٔ اصلی را جداگانه روشن/خاموش کنید؛ ترتیب بخش‌ها ثابت است: هیرو → آمار → معرفی → چرا ما → نظرات → دسته‌بندی → محصولات → اشتراک → تماس.">
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach([
                                'home_hero_enabled'         => ['هیرو (معرفی بالا)', 'sparkles'],
                                'home_stats_enabled'        => ['آمار اعتماد', 'bar-chart'],
                                'home_about_enabled'        => ['معرفی (درباره ما)', 'users'],
                                'home_why_enabled'          => ['چرا ما', 'badge-check'],
                                'home_testimonials_enabled' => ['نظرات مشتریان', 'quote'],
                                'home_categories_enabled'   => ['دسته‌بندی محصولات', 'folder-tree'],
                                'home_packages_enabled'     => ['پکیج‌ها (محصولات)', 'package'],
                                'home_products_enabled'     => ['پروژه‌های آماده', 'rocket'],
                                'home_templates_enabled'    => ['قالب‌های آماده', 'layout-template'],
                                'home_subscription_enabled' => ['اشتراک‌ها', 'crown'],
                                'home_contact_enabled'      => ['تماس با ما', 'message-circle'],
                                'contact_form_enabled'      => ['فرم تماس (داخل بخش تماس)', 'send'],
                            ] as $key => $meta)
                                <div class="rounded-xl bg-zinc-50 p-3.5 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                                    <x-toggle wire:model="form.{{ $key }}" label="{{ $meta[0] }}" id="{{ $key }}" />
                                </div>
                            @endforeach
                        </div>
                    </x-card>
                </div>
            </x-slot:sections>

            {{-- ==================== هیرو ==================== --}}
            <x-slot:hero>
                <div class="space-y-6">
                    <x-card title="معرفی بالا (هیرو)" subtitle="اولین چیزی که بازدیدکننده می‌بیند — عنوان، توضیح و دکمه‌ها.">
                        <div class="space-y-5">
                            <x-field label="برچسب کوچک بالای عنوان" for="home_hero_badge">
                                <x-input id="home_hero_badge" wire:model="form.home_hero_badge" placeholder="آماده همکاری و پروژه جدید" />
                            </x-field>

                            <x-field label="عنوان اصلی" for="home_hero_title" required
                                     hint="مثلاً: از اول تا بزرگ شدن استارتاپ کنار شما هستیم">
                                <x-input id="home_hero_title" wire:model="form.home_hero_title" />
                            </x-field>

                            <x-field label="توضیح زیر عنوان" for="home_hero_subtitle">
                                <x-textarea id="home_hero_subtitle" wire:model="form.home_hero_subtitle" rows="3" />
                            </x-field>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <x-field label="متن دکمهٔ اول" for="home_hero_cta_text">
                                    <x-input id="home_hero_cta_text" wire:model="form.home_hero_cta_text" placeholder="مشاهده محصولات" />
                                </x-field>
                                <x-field label="لینک دکمهٔ اول" for="home_hero_cta_link"
                                         hint="لنگر داخلی (#shop-packages) یا آدرس کامل">
                                    <x-input id="home_hero_cta_link" wire:model="form.home_hero_cta_link" dir="ltr" placeholder="#shop-packages" icon="link" />
                                </x-field>
                                <x-field label="متن دکمهٔ دوم" for="home_hero_cta2_text">
                                    <x-input id="home_hero_cta2_text" wire:model="form.home_hero_cta2_text" placeholder="درخواست مشاوره" />
                                </x-field>
                                <x-field label="لینک دکمهٔ دوم" for="home_hero_cta2_link">
                                    <x-input id="home_hero_cta2_link" wire:model="form.home_hero_cta2_link" dir="ltr" placeholder="#contact" icon="link" />
                                </x-field>
                            </div>
                        </div>
                    </x-card>
                </div>
            </x-slot:hero>

            {{-- ==================== معرفی ==================== --}}
            <x-slot:about>
                <div class="space-y-6">
                    <x-card title="بخش معرفی (درباره ما)" subtitle="متن معرفی تیم — هر خط، یک پاراگراف نمایش داده می‌شود.">
                        <div class="space-y-5">
                            <x-field label="عنوان بخش" for="home_about_title">
                                <x-input id="home_about_title" wire:model="form.home_about_title" placeholder="معرفی" />
                            </x-field>

                            <x-field label="متن معرفی" for="home_about_body"
                                     hint="برای پاراگراف جدید، Enter بزنید">
                                <x-textarea id="home_about_body" wire:model="form.home_about_body" rows="7" />
                            </x-field>
                        </div>
                    </x-card>
                </div>
            </x-slot:about>

            {{-- ==================== اعتماد (آمار + چرا ما + نظرات) ==================== --}}
            <x-slot:trust>
                <div class="space-y-6">
                    <x-card title="آمار اعتماد" subtitle="چهار عدد اعتمادساز زیر هیرو (خالی = مخفی).">
                        <div class="grid gap-5 sm:grid-cols-2">
                            @for($i = 1; $i <= 4; $i++)
                                <div class="grid grid-cols-2 gap-3 rounded-xl bg-zinc-50 p-3.5 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                                    <x-field label="مقدار {{ fa_num($i) }}" for="home_stat{{ $i }}_value">
                                        <x-input id="home_stat{{ $i }}_value" wire:model="form.home_stat{{ $i }}_value" placeholder="+۷" />
                                    </x-field>
                                    <x-field label="برچسب" for="home_stat{{ $i }}_label">
                                        <x-input id="home_stat{{ $i }}_label" wire:model="form.home_stat{{ $i }}_label" placeholder="سال تجربه" />
                                    </x-field>
                                </div>
                            @endfor
                        </div>
                    </x-card>

                    <x-card title="چرا به ما اعتماد می‌کنند" subtitle="شش کارت ویژگی — آیتم‌های کاملاً خالی نمایش داده نمی‌شوند.">
                        <div class="space-y-5">
                            <div class="grid gap-5 sm:grid-cols-2">
                                <x-field label="عنوان بخش" for="home_why_title">
                                    <x-input id="home_why_title" wire:model="form.home_why_title" />
                                </x-field>
                                <x-field label="توضیح یک‌خطی" for="home_why_intro">
                                    <x-input id="home_why_intro" wire:model="form.home_why_intro" />
                                </x-field>
                            </div>

                            <div class="space-y-4">
                                @for($i = 1; $i <= 6; $i++)
                                    <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                                        <p class="mb-3 text-xs font-black text-zinc-500 dark:text-zinc-400">ویجت {{ fa_num($i) }}</p>
                                        <div class="grid gap-4 lg:grid-cols-3">
                                            <x-field label="آیکون" for="home_why{{ $i }}_icon">
                                                <x-select id="home_why{{ $i }}_icon" wire:model="form.home_why{{ $i }}_icon" :options="$this->iconOptions" />
                                            </x-field>
                                            <x-field label="عنوان" for="home_why{{ $i }}_title">
                                                <x-input id="home_why{{ $i }}_title" wire:model="form.home_why{{ $i }}_title" />
                                            </x-field>
                                            <x-field label="توضیح" for="home_why{{ $i }}_text">
                                                <x-textarea id="home_why{{ $i }}_text" wire:model="form.home_why{{ $i }}_text" rows="2" />
                                            </x-field>
                                        </div>
                                    </div>
                                @endfor
                            </div>
                        </div>
                    </x-card>

                    <x-card title="نظرات مشتریان" subtitle="سه نظر واقعی مشتریان — متن خالی یعنی آن کارت نمایش داده نمی‌شود.">
                        <div class="space-y-5">
                            <x-field label="عنوان بخش" for="home_testimonials_title">
                                <x-input id="home_testimonials_title" wire:model="form.home_testimonials_title" />
                            </x-field>

                            @for($i = 1; $i <= 3; $i++)
                                <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                                    <p class="mb-3 text-xs font-black text-zinc-500 dark:text-zinc-400">نظر {{ fa_num($i) }}</p>
                                    <div class="grid gap-4 lg:grid-cols-3">
                                        <div class="lg:col-span-2">
                                            <x-field label="متن نظر" for="home_testimonial{{ $i }}_text">
                                                <x-textarea id="home_testimonial{{ $i }}_text" wire:model="form.home_testimonial{{ $i }}_text" rows="3" />
                                            </x-field>
                                        </div>
                                        <x-field label="نام و سمت" for="home_testimonial{{ $i }}_author">
                                            <x-input id="home_testimonial{{ $i }}_author" wire:model="form.home_testimonial{{ $i }}_author" placeholder="مدیر فنی شرکت …" />
                                        </x-field>
                                    </div>
                                </div>
                            @endfor
                        </div>
                    </x-card>
                </div>
            </x-slot:trust>

            {{-- ==================== تماس با ما ==================== --}}
            <x-slot:contact>
                <div class="space-y-6">
                    <x-card title="بخش تماس با ما" subtitle="توضیح سه مسیر درخواست (قالب/پروژه/استارتاپ اختصاصی) + راه‌های ارتباطی و فرم.">
                        <div class="space-y-5">
                            <x-field label="عنوان بخش" for="home_contact_title">
                                <x-input id="home_contact_title" wire:model="form.home_contact_title" />
                            </x-field>

                            <x-field label="توضیح بالای فرم" for="home_contact_intro">
                                <x-textarea id="home_contact_intro" wire:model="form.home_contact_intro" rows="4" />
                            </x-field>

                            <div class="grid gap-5 lg:grid-cols-3">
                                <x-field label="توضیح «قالب اختصاصی»" for="contact_type_template_desc">
                                    <x-textarea id="contact_type_template_desc" wire:model="form.contact_type_template_desc" rows="4" />
                                </x-field>
                                <x-field label="توضیح «پروژه اختصاصی»" for="contact_type_project_desc">
                                    <x-textarea id="contact_type_project_desc" wire:model="form.contact_type_project_desc" rows="4" />
                                </x-field>
                                <x-field label="توضیح «استارتاپ اختصاصی»" for="contact_type_startup_desc">
                                    <x-textarea id="contact_type_startup_desc" wire:model="form.contact_type_startup_desc" rows="4" />
                                </x-field>
                            </div>
                        </div>
                    </x-card>

                    <x-card title="راه‌های ارتباطی" subtitle="در کنار فرم نمایش داده می‌شوند؛ فیلد خالی مخفی می‌شود.">
                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-field label="تلفن همراه" for="contact_phone">
                                <x-input id="contact_phone" wire:model="form.contact_phone" dir="ltr" placeholder="09123456789" icon="phone" />
                            </x-field>
                            <x-field label="ایمیل" for="contact_email">
                                <x-input id="contact_email" wire:model="form.contact_email" dir="ltr" placeholder="info@example.com" icon="mail" />
                            </x-field>
                            <x-field label="لینک تلگرام" for="contact_telegram">
                                <x-input id="contact_telegram" wire:model="form.contact_telegram" dir="ltr" placeholder="https://t.me/username" icon="send" />
                            </x-field>
                            <x-field label="آدرس" for="contact_address">
                                <x-input id="contact_address" wire:model="form.contact_address" placeholder="تهران، ایران" icon="map-pin" />
                            </x-field>
                        </div>
                    </x-card>
                </div>
            </x-slot:contact>

            {{-- ==================== فوتر ==================== --}}
            <x-slot:footer>
                <div class="space-y-6">
                    <x-card title="نشان‌های اعتماد فوتر (اینماد)" subtitle="کد HTML اینماد (یا هر نشان اعتماد دیگر) را اینجا بچسبانید؛ در فوتر همهٔ صفحات فروشگاه نمایش داده می‌شود.">
                        <div class="space-y-5">
                            <div class="rounded-xl bg-amber-50 p-4 text-xs leading-6 text-amber-800 ring-1 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-400/20">
                                کد را از پنل اینماد کپی کنید (معمولاً یک تگ <a> با تصویر یا یک اسکریپت) و عیناً در کادر زیر قرار دهید. تا وقتی این کادر خالی باشد، هیچ نشان نمایش داده نمی‌شود. این فیلد HTML خام است؛ فقط از کد رسمی اینماد استفاده کنید.
                            </div>

                            <x-field label="کد اینماد (HTML)" for="footer_enamad_code">
                                <x-textarea id="footer_enamad_code" wire:model="form.footer_enamad_code" rows="6" dir="ltr"
                                            class="font-mono text-xs" placeholder='<a href="https://trustseal.enamad.ir/?id=…"><img src="…" alt=""/></a>' />
                            </x-field>
                        </div>
                    </x-card>
                </div>
            </x-slot:footer>
        </x-tabs>

        <div class="sticky bottom-4 z-10 flex justify-end">
            <x-btn type="submit" size="lg" icon="save">
                ذخیره تنظیمات
            </x-btn>
        </div>
    </form>
</div>
