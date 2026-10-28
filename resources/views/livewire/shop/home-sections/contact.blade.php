{{-- بخش «contact» صفحهٔ اصلی — محتوا/سوییچ/ترتیب از پنل (Task 59) --}}
        @if($ho['home_contact_enabled'])
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6">
                <section id="contact" class="scroll-mt-24 space-y-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="max-w-2xl">
                            <h2 class="flex items-center gap-2 text-lg font-black text-zinc-900 dark:text-zinc-50">
                                <x-icon name="message-circle" class="size-5 text-brand-600" />
                                {{ $hs['home_contact_title'] }}
                            </h2>
                            <p class="mt-1.5 text-sm leading-7 text-zinc-500 dark:text-zinc-400">
                                {{ $hs['home_contact_intro'] }}
                            </p>
                        </div>
                    </div>

                    {{-- ---------- سه مسیر درخواست ---------- --}}
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        @foreach([
                            ['icon' => 'layout-template', 'title' => 'قالب اختصاصی', 'desc' => $hs['contact_type_template_desc']],
                            ['icon' => 'rocket',          'title' => 'پروژه اختصاصی', 'desc' => $hs['contact_type_project_desc']],
                            ['icon' => 'lightbulb',       'title' => 'استارتاپ اختصاصی', 'desc' => $hs['contact_type_startup_desc']],
                        ] as $ti => $type)
                            @if(trim($type['desc']) !== '')
                                <div wire:key="ctype-{{ $ti }}"
                                     class="group relative overflow-hidden rounded-2xl bg-white p-5 ring-1 ring-zinc-200/80 transition-all hover:-translate-y-0.5 hover:shadow-card-lg dark:bg-zinc-900 dark:ring-zinc-700/60">
                                    <span class="flex size-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600 transition-colors group-hover:bg-brand-100 dark:bg-brand-500/10 dark:text-brand-400">
                                        <x-icon name="{{ $type['icon'] }}" class="size-5.5" />
                                    </span>
                                    <h3 class="mt-3.5 text-sm font-black text-zinc-900 dark:text-zinc-50">{{ $type['title'] }}</h3>
                                    <p class="mt-1.5 text-xs leading-6 text-zinc-500 dark:text-zinc-400">{{ $type['desc'] }}</p>
                                    <span class="mt-3 inline-flex items-center gap-1 text-[11px] font-black text-brand-600 dark:text-brand-400">
                                        <x-icon name="send" class="size-3" />
                                        درخواست از فرم پایین
                                    </span>
                                </div>
                            @endif
                        @endforeach
                    </div>

                    {{-- ---------- فرم + راه‌های تماس ---------- --}}
                    <div class="grid gap-6 lg:grid-cols-5">
                        <div class="lg:col-span-3">
                            @if($ho['contact_form_enabled'])
                                <livewire:shop.contact-form />
                            @endif
                        </div>

                        <div class="space-y-4 lg:col-span-2">
                            {{-- راه‌های ارتباطی --}}
                            <div class="card p-5 sm:p-6">
                                <h3 class="text-base font-black text-zinc-900 dark:text-zinc-50">راه‌های ارتباطی</h3>
                                <div class="mt-4 space-y-2.5">
                                    @if($hs['contact_phone'] !== '')
                                        <a href="tel:{{ $hs['contact_phone'] }}" class="flex items-center gap-3 rounded-xl bg-zinc-50 p-3.5 ring-1 ring-zinc-200/60 transition-colors hover:bg-brand-50 hover:ring-brand-200 dark:bg-zinc-800/40 dark:ring-zinc-700/60 dark:hover:bg-brand-500/10 dark:hover:ring-brand-400/20">
                                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white text-brand-600 shadow-xs dark:bg-zinc-900 dark:text-brand-400">
                                                <x-icon name="phone" class="size-4.5" />
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block text-[10px] text-zinc-400">تلفن همراه</span>
                                                <span class="block text-sm font-black tabular-nums text-zinc-800 dark:text-zinc-100" dir="ltr">{{ fa_num($hs['contact_phone']) }}</span>
                                            </span>
                                        </a>
                                    @endif
                                    @if($hs['contact_email'] !== '')
                                        <a href="mailto:{{ $hs['contact_email'] }}" class="flex items-center gap-3 rounded-xl bg-zinc-50 p-3.5 ring-1 ring-zinc-200/60 transition-colors hover:bg-brand-50 hover:ring-brand-200 dark:bg-zinc-800/40 dark:ring-zinc-700/60 dark:hover:bg-brand-500/10 dark:hover:ring-brand-400/20">
                                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white text-brand-600 shadow-xs dark:bg-zinc-900 dark:text-brand-400">
                                                <x-icon name="mail" class="size-4.5" />
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block text-[10px] text-zinc-400">ایمیل</span>
                                                <span class="block truncate text-sm font-black text-zinc-800 dark:text-zinc-100" dir="ltr">{{ $hs['contact_email'] }}</span>
                                            </span>
                                        </a>
                                    @endif
                                    @if(preg_match('#^https?://#', $hs['contact_telegram']))
                                        <a href="{{ $hs['contact_telegram'] }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-3 rounded-xl bg-zinc-50 p-3.5 ring-1 ring-zinc-200/60 transition-colors hover:bg-brand-50 hover:ring-brand-200 dark:bg-zinc-800/40 dark:ring-zinc-700/60 dark:hover:bg-brand-500/10 dark:hover:ring-brand-400/20">
                                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white text-brand-600 shadow-xs dark:bg-zinc-900 dark:text-brand-400">
                                                <x-icon name="send" class="size-4.5" />
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block text-[10px] text-zinc-400">تلگرام</span>
                                                <span class="block truncate text-sm font-black text-zinc-800 dark:text-zinc-100" dir="ltr">{{ $hs['contact_telegram'] }}</span>
                                            </span>
                                        </a>
                                    @endif
                                    @if($hs['contact_address'] !== '')
                                        <div class="flex items-center gap-3 rounded-xl bg-zinc-50 p-3.5 ring-1 ring-zinc-200/60 dark:bg-zinc-800/40 dark:ring-zinc-700/60">
                                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white text-brand-600 shadow-xs dark:bg-zinc-900 dark:text-brand-400">
                                                <x-icon name="map-pin" class="size-4.5" />
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block text-[10px] text-zinc-400">آدرس</span>
                                                <span class="block text-sm font-black text-zinc-800 dark:text-zinc-100">{{ $hs['contact_address'] }}</span>
                                            </span>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- تضمین‌های اعتماد --}}
                            <div class="card p-5 sm:p-6">
                                <h3 class="text-base font-black text-zinc-900 dark:text-zinc-50">با خیال راحت شروع کنید</h3>
                                <ul class="mt-4 space-y-3 text-xs leading-6 text-zinc-600 dark:text-zinc-300">
                                    <li class="flex items-start gap-2">
                                        <x-icon name="badge-check" class="mt-0.5 size-4 shrink-0 text-brand-600 dark:text-brand-400" />
                                        مشاوره اولیه رایگان است؛ بدون هیچ تعهدی ایده‌تان را بپرسید.
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <x-icon name="shield-check" class="mt-0.5 size-4 shrink-0 text-brand-600 dark:text-brand-400" />
                                        برآورد هزینه و زمان تحویل قبل از شروع کار، شفاف و کتبی اعلام می‌شود.
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <x-icon name="heart-handshake" class="mt-0.5 size-4 shrink-0 text-brand-600 dark:text-brand-400" />
                                        بعد از تحویل هم تنها نیستید؛ پشتیبانی و توسعه ادامه دارد.
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </section>
        </div>
        @endif
