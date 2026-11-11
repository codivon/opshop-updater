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
            ['id' => 'menu',     'label' => 'منوی هدر', 'icon' => 'menu'],
            ['id' => 'hero',     'label' => 'هیرو', 'icon' => 'sparkles'],
            ['id' => 'showcase', 'label' => 'اسلایدر معرفی', 'icon' => 'image'],
            ['id' => 'about',    'label' => 'معرفی', 'icon' => 'users'],
            ['id' => 'trust',    'label' => 'اعتماد', 'icon' => 'badge-check'],
            ['id' => 'contact',  'label' => 'تماس با ما', 'icon' => 'message-circle'],
            ['id' => 'footer',   'label' => 'فوتر', 'icon' => 'panel-top'],
        ]">

            {{-- ==================== بخش‌ها (روشن/خاموش + ترتیب) ==================== --}}
            <x-slot:sections>
                <div class="space-y-6">
                    <x-card title="نمایش و ترتیب بخش‌ها" subtitle="هر ویجت را با فلش‌ها بالا/پایین ببرید و با سوییچ روشن/خاموش کنید؛ ترتیب جدید بعد از «ذخیره تنظیمات» روی صفحهٔ اصلی اعمال می‌شود.">
                        <div class="space-y-2.5">
                            @foreach($this->sectionRows as $row)
                                <div class="flex flex-wrap items-center gap-3 rounded-xl bg-zinc-50 p-3.5 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60"
                                     wire:key="secrow-{{ $row['key'] }}">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white text-zinc-500 shadow-xs dark:bg-zinc-900 dark:text-zinc-400">
                                        <x-icon name="{{ $row['icon'] }}" class="size-4.5" />
                                    </span>
                                    <span class="min-w-0 flex-1 text-sm font-bold text-zinc-800 dark:text-zinc-100">{{ $row['label'] }}</span>
                                    <span class="flex items-center gap-1">
                                        <button type="button" wire:click="moveSectionUp('{{ $row['key'] }}')" wire:key="secup-{{ $row['key'] }}"
                                                class="rounded-lg bg-white p-1.5 text-zinc-500 ring-1 ring-zinc-200/70 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:bg-zinc-900 dark:ring-zinc-700/60 dark:hover:bg-brand-500/10 dark:hover:text-brand-400"
                                                title="انتقال به بالا" aria-label="انتقال {{ $row['label'] }} به بالا">
                                            <x-icon name="chevron-up" class="size-4" />
                                        </button>
                                        <button type="button" wire:click="moveSectionDown('{{ $row['key'] }}')" wire:key="secdown-{{ $row['key'] }}"
                                                class="rounded-lg bg-white p-1.5 text-zinc-500 ring-1 ring-zinc-200/70 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:bg-zinc-900 dark:ring-zinc-700/60 dark:hover:bg-brand-500/10 dark:hover:text-brand-400"
                                                title="انتقال به پایین" aria-label="انتقال {{ $row['label'] }} به پایین">
                                            <x-icon name="chevron-down" class="size-4" />
                                        </button>
                                    </span>
                                    <x-toggle wire:model="form.home_{{ $row['key'] }}_enabled" id="home_{{ $row['key'] }}_enabled" />
                                </div>
                            @endforeach
                        </div>
                    </x-card>

                    <x-card title="فرم تماس" subtitle="فرم تماس داخل بخش «تماس با ما» نمایش داده می‌شود و ترتیب مستقل ندارد.">
                        <x-toggle wire:model="form.contact_form_enabled" label="نمایش فرم تماس" id="contact_form_enabled" />
                    </x-card>
                </div>
            </x-slot:sections>

            {{-- ==================== منوی هدر (ترتیب + روشن/خاموش) — Task 61 ==================== --}}
            <x-slot:menu>
                <div class="space-y-6">
                    <x-card title="منوی هدر فروشگاه" subtitle="ترتیب لینک‌های ناوبری هدر را با فلش‌ها تغییر دهید و آیتم‌های لازم را خاموش/روشن کنید؛ تغییرات بعد از «ذخیره تنظیمات» روی هدر همهٔ صفحات فروشگاه اعمال می‌شود. خاموشی آیتم فقط لینک هدر را مخفی می‌کند؛ دسترسی مستقیم با آدرس سرجایش است.">
                        <div class="space-y-2.5">
                            @foreach($this->menuRows as $row)
                                <div class="flex flex-wrap items-center gap-3 rounded-xl bg-zinc-50 p-3.5 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60"
                                     wire:key="menurow-{{ $row['key'] }}">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white text-zinc-500 shadow-xs dark:bg-zinc-900 dark:text-zinc-400">
                                        <x-icon name="{{ $row['icon'] }}" class="size-4.5" />
                                    </span>
                                    <span class="min-w-0 flex-1 text-sm font-bold text-zinc-800 dark:text-zinc-100">{{ $row['label'] }}</span>
                                    <span class="flex items-center gap-1">
                                        <button type="button" wire:click="moveMenuUp('{{ $row['key'] }}')" wire:key="menuup-{{ $row['key'] }}"
                                                class="rounded-lg bg-white p-1.5 text-zinc-500 ring-1 ring-zinc-200/70 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:bg-zinc-900 dark:ring-zinc-700/60 dark:hover:bg-brand-500/10 dark:hover:text-brand-400"
                                                title="انتقال به بالا" aria-label="انتقال {{ $row['label'] }} به بالا">
                                            <x-icon name="chevron-up" class="size-4" />
                                        </button>
                                        <button type="button" wire:click="moveMenuDown('{{ $row['key'] }}')" wire:key="menudown-{{ $row['key'] }}"
                                                class="rounded-lg bg-white p-1.5 text-zinc-500 ring-1 ring-zinc-200/70 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:bg-zinc-900 dark:ring-zinc-700/60 dark:hover:bg-brand-500/10 dark:hover:text-brand-400"
                                                title="انتقال به پایین" aria-label="انتقال {{ $row['label'] }} به پایین">
                                            <x-icon name="chevron-down" class="size-4" />
                                        </button>
                                    </span>
                                    <x-toggle wire:model="form.menu_{{ $row['key'] }}_enabled" id="menu_{{ $row['key'] }}_enabled" />
                                </div>
                            @endforeach
                        </div>
                    </x-card>
                </div>
            </x-slot:menu>

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
                                <x-field label="متن دکمهٔ سوم" for="home_hero_cta3_text"
                                         hint="لینک سایت برند (درباره من / نمونه‌کارها) — خالی = نمایش داده نمی‌شود">
                                    <x-input id="home_hero_cta3_text" wire:model="form.home_hero_cta3_text" placeholder="بیشتر درباره من و نمونه‌کارها" />
                                </x-field>
                                <x-field label="لینک دکمهٔ سوم" for="home_hero_cta3_link"
                                         hint="در تب جدید باز می‌شود — مثلاً سایت اصلی برند">
                                    <x-input id="home_hero_cta3_link" wire:model="form.home_hero_cta3_link" dir="ltr" placeholder="https://weblak.ir" icon="link" />
                                </x-field>
                            </div>
                        </div>
                    </x-card>
                </div>
            </x-slot:hero>

            {{-- ==================== اسلایدر معرفی (Task 71 + Task 73) ==================== --}}
            <x-slot:showcase>
                <div class="space-y-6">
                    {{-- راهنمای قاعدهٔ نمایش --}}
                    <div class="rounded-xl bg-sky-50 p-4 text-xs leading-6 text-sky-800 ring-1 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-400 dark:ring-sky-400/20">
                        <strong class="font-black">قاعدهٔ نمایش:</strong>
                        تا وقتی هیچ اسلاید دستیِ فعالی نداشته باشید، اسلایدر به‌صورت خودکار از «پکیج‌های فعال» + «بنر ویژهٔ InstaShop» ساخته می‌شود.
                        با افزودن اولین اسلاید دستی، فقط اسلایدهای دستی نمایش داده می‌شوند (برای بازگشت به پیش‌فرض، همهٔ اسلایدهای دستی را حذف یا غیرفعال کنید).
                        اکشن‌های اسلاید (افزودن/ویرایش/حذف/ترتیب) بلافاصله ذخیره می‌شوند؛ کلیدهای روشن/خاموش، تنظیمات نمایش و متن‌های بنر InstaShop با «ذخیره تنظیمات» اعمال می‌شوند.
                    </div>

                    {{-- تنظیمات نمایش اسلایدر (Task 73) — بخشی از فرم اصلی --}}
                    <x-card title="تنظیمات نمایش اسلایدر" subtitle="رفتار و ظاهر کلی اسلایدر — با دکمهٔ «ذخیره تنظیمات» پایین صفحه اعمال می‌شود.">
                        <div class="space-y-5">
                            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-zinc-50 p-3.5 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                                <span class="text-sm font-bold text-zinc-800 dark:text-zinc-100">نمایش سربرگ بخش (برچسب + عنوان بالای اسلایدر)</span>
                                <x-toggle wire:model="form.home_showcase_header_enabled" id="home_showcase_header_enabled" />
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <x-field label="برچسب کوچک سربرگ" for="showcase_eyebrow" hint="خالی = مخفی">
                                    <x-input id="showcase_eyebrow" wire:model="form.home_showcase_eyebrow" placeholder="ویترین محصولات" />
                                </x-field>
                                <x-field label="عنوان سربرگ" for="showcase_heading" hint="خالی = مخفی">
                                    <x-input id="showcase_heading" wire:model="form.home_showcase_heading" placeholder="محصولات ویژهٔ ما را ببینید" />
                                </x-field>
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-zinc-50 p-3.5 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                                <span class="text-sm font-bold text-zinc-800 dark:text-zinc-100">پخش خودکار اسلایدها</span>
                                <x-toggle wire:model="form.home_showcase_autoplay" id="home_showcase_autoplay" />
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <x-field label="سرعت پخش خودکار (ثانیه)" for="showcase_autoplay_speed" hint="بین ۳ تا ۱۵ ثانیه">
                                    <x-input id="showcase_autoplay_speed" wire:model="form.home_showcase_autoplay_speed" type="number" min="3" max="15" dir="ltr" :error="'form.home_showcase_autoplay_speed'" />
                                </x-field>
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-zinc-50 p-3.5 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                                <span class="text-sm font-bold text-zinc-800 dark:text-zinc-100">دات‌های ناوبری (پایین اسلایدر)</span>
                                <x-toggle wire:model="form.home_showcase_dots" id="home_showcase_dots" />
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-zinc-50 p-3.5 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                                <span class="text-sm font-bold text-zinc-800 dark:text-zinc-100">فلش‌های قبلی/بعدی (دسکتاپ)</span>
                                <x-toggle wire:model="form.home_showcase_arrows" id="home_showcase_arrows" />
                            </div>
                        </div>
                    </x-card>

                    {{-- پیش‌فرض‌ها — بخشی از فرم اصلی (ذخیره با دکمهٔ «ذخیره تنظیمات») --}}
                    <x-card title="پیش‌فرض‌ها (وقتی اسلاید دستی فعال ندارید)" subtitle="بنر خودکار پکیج‌ها: از تصویر/عنوان/متن هر پکیج فعال. بنر ویژهٔ InstaShop: تصویر اختصاصی با متن قابل ویرایش.">
                        <div class="space-y-5">
                            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-zinc-50 p-3.5 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                                <span class="text-sm font-bold text-zinc-800 dark:text-zinc-100">بنر خودکار از پکیج‌های فعال (حداکثر ۶ عدد، جدیدترین‌ها)</span>
                                <x-toggle wire:model="form.home_showcase_packages_enabled" id="home_showcase_packages_enabled" />
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-zinc-50 p-3.5 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                                <span class="text-sm font-bold text-zinc-800 dark:text-zinc-100">بنر ویژهٔ InstaShop (پیج اینستاگرام ← فروشگاه آنلاین)</span>
                                <x-toggle wire:model="form.home_showcase_instashop_enabled" id="home_showcase_instashop_enabled" />
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <x-field label="نشان بنر InstaShop" for="showcase_insta_badge">
                                    <x-input id="showcase_insta_badge" wire:model="form.home_showcase_instashop_badge" placeholder="بیزینس آماده" />
                                </x-field>
                                <x-field label="متن دکمه" for="showcase_insta_link_text">
                                    <x-input id="showcase_insta_link_text" wire:model="form.home_showcase_instashop_link_text" placeholder="مشاهدهٔ دمو" />
                                </x-field>
                                <x-field label="عنوان" for="showcase_insta_title" hint="پیش‌فرض: پیج اینستاگرامت را به یک فروشگاه آنلاین تبدیل کن" class="sm:col-span-2">
                                    <x-input id="showcase_insta_title" wire:model="form.home_showcase_instashop_title" />
                                </x-field>
                                <x-field label="متن توضیح" for="showcase_insta_text" class="sm:col-span-2">
                                    <x-textarea id="showcase_insta_text" wire:model="form.home_showcase_instashop_text" rows="3" />
                                </x-field>
                                <x-field label="لینک دکمه" for="showcase_insta_link" class="sm:col-span-2">
                                    <x-input id="showcase_insta_link" wire:model="form.home_showcase_instashop_link" dir="ltr" placeholder="https://insta-shop.opashop.ir" icon="link" />
                                </x-field>
                            </div>
                        </div>
                    </x-card>

                    {{-- اسلایدهای دستی — ذخیرهٔ لحظه‌ای --}}
                    <x-card title="اسلایدهای دستی ({{ $this->slides->count() }} عدد)" subtitle="این اسلایدها بلافاصله ذخیره می‌شوند و اولویت نمایش دارند.">
                        <div class="space-y-3">
                            @foreach($this->slides as $s)
                                <div class="flex flex-wrap items-center gap-3 rounded-xl bg-zinc-50 p-3 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60"
                                     wire:key="slide-row-{{ $s->id }}">
                                    {{-- بندانگشتی --}}
                                    @if($s->image)
                                        <img src="{{ template_asset_url($s->image) }}" alt="{{ $s->title }}"
                                             class="size-14 shrink-0 rounded-lg object-cover ring-1 ring-zinc-200 dark:ring-zinc-700" />
                                    @else
                                        <span class="flex size-14 shrink-0 items-center justify-center rounded-lg bg-gradient-to-bl from-brand-600 to-teal-700 text-white/80">
                                            <x-icon name="image" class="size-5" />
                                        </span>
                                    @endif

                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-black text-zinc-900 dark:text-zinc-50">
                                            <span class="me-1.5 inline-flex size-5 items-center justify-center rounded-md bg-zinc-200/80 text-[10px] font-black text-zinc-600 dark:bg-zinc-700/80 dark:text-zinc-300">{{ fa_num($loop->index + 1) }}</span>
                                            {{ $s->title }}
                                        </p>
                                        <p class="mt-0.5 flex flex-wrap items-center gap-1.5 text-[11px] text-zinc-500 dark:text-zinc-400">
                                            @if($s->badge)
                                                <span class="rounded-full bg-zinc-200/70 px-2 py-0.5 dark:bg-zinc-700/70">{{ $s->badge }}</span>
                                            @endif
                                            @if($s->link)
                                                <span class="inline-flex items-center gap-1"><x-icon name="link" class="size-3" /> دکمه دارد</span>
                                            @endif
                                            <span class="inline-flex items-center gap-1"><x-icon name="move" class="size-3" /> جایگاه {{ fa_num($s->sort_order) }}</span>
                                            <span class="{{ $s->is_active ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-400' }}">
                                                {{ $s->is_active ? 'فعال' : 'غیرفعال' }}
                                            </span>
                                        </p>
                                    </div>

                                    <span class="flex items-center gap-1">
                                        <button type="button" wire:click="moveSlide({{ $s->id }}, 'up')"
                                                class="rounded-lg bg-white p-1.5 text-zinc-500 ring-1 ring-zinc-200/70 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:bg-zinc-900 dark:ring-zinc-700/60"
                                                title="انتقال به بالا" aria-label="انتقال اسلاید به بالا">
                                            <x-icon name="chevron-up" class="size-4" />
                                        </button>
                                        <button type="button" wire:click="moveSlide({{ $s->id }}, 'down')"
                                                class="rounded-lg bg-white p-1.5 text-zinc-500 ring-1 ring-zinc-200/70 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:bg-zinc-900 dark:ring-zinc-700/60"
                                                title="انتقال به پایین" aria-label="انتقال اسلاید به پایین">
                                            <x-icon name="chevron-down" class="size-4" />
                                        </button>
                                        <button type="button" wire:click="editSlide({{ $s->id }})"
                                                class="rounded-lg bg-white p-1.5 text-zinc-500 ring-1 ring-zinc-200/70 transition-colors hover:bg-sky-50 hover:text-sky-600 dark:bg-zinc-900 dark:ring-zinc-700/60"
                                                title="ویرایش" aria-label="ویرایش اسلاید">
                                            <x-icon name="pencil" class="size-4" />
                                        </button>
                                        <button type="button" wire:click="deleteSlide({{ $s->id }})"
                                                wire:confirm="این اسلاید حذف شود؟"
                                                class="rounded-lg bg-white p-1.5 text-zinc-500 ring-1 ring-zinc-200/70 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:bg-zinc-900 dark:ring-zinc-700/60"
                                                title="حذف" aria-label="حذف اسلاید">
                                            <x-icon name="trash-2" class="size-4" />
                                        </button>
                                        <span class="ms-1 scale-90" title="{{ $s->is_active ? 'فعال — کلیک برای غیرفعال‌کردن' : 'غیرفعال — کلیک برای فعال‌کردن' }}">
                                            <button type="button" wire:click="toggleSlide({{ $s->id }})"
                                                    class="relative h-5 w-9 rounded-full transition-colors {{ $s->is_active ? 'bg-brand-600' : 'bg-zinc-300 dark:bg-zinc-600' }}"
                                                    aria-label="تغییر وضعیت نمایش اسلاید">
                                                <span class="absolute top-0.5 {{ $s->is_active ? 'start-0.5' : 'end-0.5' }} size-4 rounded-full bg-white shadow"></span>
                                            </button>
                                        </span>
                                    </span>
                                </div>
                            @endforeach

                            @if($this->slides->isEmpty())
                                <p class="rounded-xl bg-zinc-50 p-4 text-center text-xs font-bold text-zinc-500 dark:bg-zinc-800/40 dark:text-zinc-400">
                                    هنوز اسلاید دستی ندارید — اسلایدر از پیش‌فرض‌ها (پکیج‌ها + InstaShop) ساخته می‌شود.
                                </p>
                            @endif

                            {{-- دکمهٔ افزودن / فرم --}}
                            @if(!$showSlideForm)
                                <button type="button" wire:click="newSlide"
                                        class="flex w-full items-center justify-center gap-2 rounded-xl border-2 border-dashed border-zinc-300 py-3.5 text-sm font-black text-zinc-500 transition-colors hover:border-brand-400 hover:bg-brand-50/50 hover:text-brand-600 dark:border-zinc-600 dark:text-zinc-400 dark:hover:border-brand-500/50 dark:hover:bg-brand-500/5 dark:hover:text-brand-400">
                                    <x-icon name="plus" class="size-4.5" />
                                    افزودن اسلاید جدید
                                </button>
                            @else
                                <div class="space-y-4 rounded-xl bg-white p-4 ring-1 ring-brand-500/30 dark:bg-zinc-900 dark:ring-brand-500/25">
                                    <p class="flex items-center gap-2 text-sm font-black text-zinc-800 dark:text-zinc-100">
                                        <x-icon name="{{ $editingSlideId ? 'pencil' : 'plus' }}" class="size-4 text-brand-600 dark:text-brand-400" />
                                        {{ $editingSlideId ? 'ویرایش اسلاید' : 'اسلاید جدید' }}
                                        <span class="ms-auto text-[11px] font-bold text-zinc-400">ذخیرهٔ لحظه‌ای — بدون نیاز به «ذخیره تنظیمات»</span>
                                    </p>

                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <x-field label="نشان (اختیاری)" for="slide_badge">
                                            <x-input id="slide_badge" wire:model="slide.badge" placeholder="مثلاً: پکیج ویژه" />
                                        </x-field>
                                        <x-field label="متن دکمه (اختیاری)" for="slide_link_text">
                                            <x-input id="slide_link_text" wire:model="slide.link_text" placeholder="مشاهده" />
                                        </x-field>
                                        <x-field label="عنوان" for="slide_title" required class="sm:col-span-2" :error="'slide.title'">
                                            <x-input id="slide_title" wire:model="slide.title" wire:keydown.enter.prevent="saveSlide" />
                                        </x-field>
                                        <x-field label="متن توضیح (اختیاری)" for="slide_text" class="sm:col-span-2" :error="'slide.text'">
                                            <x-textarea id="slide_text" wire:model="slide.text" rows="3" />
                                        </x-field>
                                        <x-field label="لینک دکمه (اختیاری)" for="slide_link" class="sm:col-span-2" :error="'slide.link'">
                                            <x-input id="slide_link" wire:model="slide.link" wire:keydown.enter.prevent="saveSlide" dir="ltr" placeholder="https:// یا /packages/…" />
                                        </x-field>

                                        {{-- شخصی‌سازی جایگاه و ظاهر (Task 73) --}}
                                        <x-field label="جایگاه اسلاید" for="slide_sort_order" hint="عدد کوچک‌تر = نمایش زودتر؛ فلش‌ها هم همین را تغییر می‌دهند">
                                            <x-input id="slide_sort_order" wire:model="slide.sort_order" type="number" min="1" max="999" dir="ltr" :error="'slide.sort_order'" />
                                        </x-field>
                                        <x-field label="محل افقی محتوا" for="slide_align">
                                            <x-select id="slide_align" wire:model="slide.content_align" :options="\App\Models\HomeSlide::ALIGNS" />
                                        </x-field>
                                        <x-field label="محل عمودی محتوا" for="slide_vpos">
                                            <x-select id="slide_vpos" wire:model="slide.content_vpos" :options="\App\Models\HomeSlide::VPOSS" />
                                        </x-field>
                                        <x-field label="استایل دکمه" for="slide_button_style">
                                            <x-select id="slide_button_style" wire:model="slide.button_style" :options="\App\Models\HomeSlide::BUTTON_STYLES" />
                                        </x-field>
                                        <x-field label="شدت تیرگی روی تصویر (٪)" for="slide_overlay" hint="خالی = خودکار (گرادیان هوشمند)؛ ۰ تا ۹۰">
                                            <x-input id="slide_overlay" wire:model="slide.overlay" type="number" min="0" max="90" dir="ltr" placeholder="خودکار" :error="'slide.overlay'" />
                                        </x-field>

                                        <x-field label="تصویر اسلاید (اختیاری — JPG/PNG/WEBP تا ۳MB)" for="slide_image" class="sm:col-span-2" :error="'slideImage'">
                                            <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" wire:model="slideImage" id="slide_image" class="input" />
                                            <x-upload-progress target="slideImage" label="در حال آپلود تصویر اسلاید" />
                                            @if($editingSlideId && !$slideImage && ($this->slides->firstWhere('id', $editingSlideId)->image ?? null))
                                                <p class="mt-1 text-[11px] text-zinc-400">تصویر فعلی حفظ می‌شود؛ با انتخاب فایل جدید جایگزین می‌شود.</p>
                                            @endif
                                        </x-field>
                                    </div>

                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <span class="flex flex-wrap items-center gap-4">
                                            <label class="flex cursor-pointer items-center gap-2 text-sm font-bold text-zinc-700 dark:text-zinc-200">
                                                <input type="checkbox" wire:model="slide.is_active" class="checkbox" />
                                                فعال (نمایش در صفحهٔ اصلی)
                                            </label>
                                            <label class="flex cursor-pointer items-center gap-2 text-sm font-bold text-zinc-700 dark:text-zinc-200">
                                                <input type="checkbox" wire:model="slide.new_tab" class="checkbox" />
                                                باز شدن دکمه در تب جدید
                                            </label>
                                        </span>
                                        <span class="flex items-center gap-2">
                                            <button type="button" wire:click="closeSlideForm"
                                                    class="rounded-xl px-4 py-2 text-sm font-bold text-zinc-500 transition-colors hover:bg-zinc-100 dark:hover:bg-zinc-800">
                                                انصراف
                                            </button>
                                            <x-btn type="button" wire:click="saveSlide" icon="save" wire:loading.attr="disabled">
                                                {{ $editingSlideId ? 'اعمال ویرایش' : 'ذخیرهٔ اسلاید' }}
                                            </x-btn>
                                        </span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </x-card>
                </div>
            </x-slot:showcase>

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

                            <div class="grid gap-5 sm:grid-cols-2">
                                <x-field label="متن لینک نمونه‌کارها" for="home_about_more_text"
                                         hint="کنار دکمهٔ «صحبت درباره ایده شما» — خالی = مخفی">
                                    <x-input id="home_about_more_text" wire:model="form.home_about_more_text" placeholder="مشاهده نمونه‌کارها در وب‌لاک" />
                                </x-field>
                                <x-field label="لینک نمونه‌کارها" for="home_about_more_link"
                                         hint="سایت اصلی برند — در تب جدید باز می‌شود">
                                    <x-input id="home_about_more_link" wire:model="form.home_about_more_link" dir="ltr" placeholder="https://weblak.ir" icon="link" />
                                </x-field>
                            </div>
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
