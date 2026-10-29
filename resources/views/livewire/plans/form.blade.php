{{-- فرم افزودن/ویرایش طرح اشتراک — کامپوننت مجزا (Plans\Form) تا هر تعامل داخل مودال
     فقط همین مودال کوچک را رندر کند، نه کل جدول و آمار صفحه (رفع کندی — Task 64) --}}
<x-modal wire:model="showModal" :title="$editingId ? 'ویرایش طرح اشتراک' : 'طرح اشتراک جدید'" size="xl">
    <div class="space-y-5" wire:loading.class="opacity-60" wire:loading.delay>
        {{-- basic --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-field label="نام طرح *" error="form.name">
                <x-input wire:model="form.name" placeholder="مثلاً اشتراک حرفه‌ای" />
            </x-field>
            <x-field label="شناسه (slug)" error="form.slug">
                <x-input wire:model="form.slug" placeholder="خالی = خودکار" dir="ltr" class="font-mono" />
            </x-field>
        </div>

        <x-field label="توضیحات">
            <x-textarea wire:model="form.description" rows="2" placeholder="توضیح کوتاه طرح برای صفحه فروشگاه…" />
        </x-field>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-field label="ترتیب نمایش طرح" error="form.sort_order">
                <x-input wire:model="form.sort_order" type="number" min="0" />
                <p class="mt-1 text-xs text-zinc-400">ترتیب کارت طرح در فروشگاه (عدد کوچک‌تر = بالاتر)</p>
            </x-field>
        </div>

        {{-- مدت‌های اعتبار (چندگزینه‌ای) --}}
        <div class="rounded-2xl ring-1 ring-zinc-200/70 dark:ring-zinc-700/50">
            <div class="flex items-center justify-between gap-2 border-b border-zinc-100 px-4 py-3 dark:border-zinc-800">
                <div>
                    <div class="flex items-center gap-2 text-sm font-bold text-zinc-800 dark:text-zinc-100">
                        <x-icon name="clock" class="size-4 text-brand-600 dark:text-brand-400" />
                        مدت‌های اعتبار (چندگزینه‌ای)
                    </div>
                    <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                        هر ردیف یک گزینه برای مشتری است (مثلاً ۱ ماهه / ۳ ماهه / سالانه) با قیمت و تخفیف مستقل؛
                        <span class="font-bold text-zinc-600 dark:text-zinc-300">حداقل یک مدت الزامی است</span> و ترتیب نمایش با فلش‌ها تغییر می‌کند (ردیف اول = گزینهٔ پیش‌فرض فروشگاه و API).
                    </p>
                </div>
                <x-btn variant="soft" size="sm" icon="plus" wire:click="addDurationRow">افزودن مدت</x-btn>
            </div>
            <div class="space-y-2 p-4">
                @foreach($formDurations as $i => $row)
                    <div class="flex flex-wrap items-center gap-2 rounded-xl bg-zinc-50/60 p-2 dark:bg-zinc-800/40" wire:key="plan-duration-{{ $i }}">
                        <div class="flex items-center gap-1.5">
                            <input type="number" wire:model="formDurations.{{ $i }}.duration_months" min="0" max="120" placeholder="مدت"
                                   class="input w-24" title="مدت اعتبار (ماه) — ۰ = نامحدود" aria-label="مدت اعتبار (ماه)" />
                            <span class="whitespace-nowrap text-xs text-zinc-400">ماه</span>
                        </div>
                        <div class="flex min-w-40 flex-1 flex-col">
                            <div class="flex items-center gap-1.5">
                                <input id="plan-dur-{{ $i }}-price" type="number" wire:model="formDurations.{{ $i }}.price" min="0" placeholder="قیمت"
                                       class="input flex-1" title="قیمت این مدت (تومان)" aria-label="قیمت این مدت" />
                                <span class="whitespace-nowrap text-xs text-zinc-400">تومان</span>
                            </div>
                            <x-money-preview target="plan-dur-{{ $i }}-price" />
                        </div>
                        <div class="flex min-w-32 flex-col">
                            <div class="flex items-center gap-1.5">
                                <input id="plan-dur-{{ $i }}-disc" type="number" wire:model="formDurations.{{ $i }}.discount_price" min="0" placeholder="تخفیف"
                                       class="input w-28" title="مبلغ تخفیف (اختیاری)" aria-label="مبلغ تخفیف" />
                            </div>
                            <x-money-preview target="plan-dur-{{ $i }}-disc" suffix="تومان تخفیف" />
                        </div>
                        <label class="flex cursor-pointer items-center gap-1.5" title="نمایش در فروشگاه">
                            <x-toggle wire:model="formDurations.{{ $i }}.is_active" />
                            <span class="whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400">فعال</span>
                        </label>
                        <div class="flex items-center gap-0.5">
                            @if($i > 0)
                                <button type="button" wire:click="moveDurationUp({{ $i }})"
                                        class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10 dark:hover:text-brand-400"
                                        title="انتقال به بالا — نمایش زودتر در فروشگاه" aria-label="انتقال این مدت به بالا">
                                    <x-icon name="chevron-up" class="size-4" />
                                </button>
                            @endif
                            @if($i < count($formDurations) - 1)
                                <button type="button" wire:click="moveDurationDown({{ $i }})"
                                        class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10 dark:hover:text-brand-400"
                                        title="انتقال به پایین — نمایش دیرتر در فروشگاه" aria-label="انتقال این مدت به پایین">
                                    <x-icon name="chevron-down" class="size-4" />
                                </button>
                            @endif
                            <button type="button" wire:click="removeDurationRow({{ $i }})"
                                    class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
                                    title="حذف مدت">
                                <x-icon name="trash" class="size-4" />
                            </button>
                        </div>
                        @foreach(['duration_months', 'price', 'discount_price'] as $field)
                            @error('formDurations.' . $i . '.' . $field)
                                <p class="w-full text-xs font-bold text-rose-500">{{ $message }}</p>
                            @enderror
                        @endforeach

                        {{-- پیکربندی اختصاصی این مدت: قابلیت‌ها + پکیج‌ها (Task 63) —
                             باز/بسته با state کامپوننت (نه <details>) تا بعد از افزودن/حذف ردیف‌ها باز بماند --}}
                        <div class="w-full" wire:key="plan-duration-config-{{ $i }}">
                            <button type="button" wire:click="toggleDurationConfig({{ $i }})"
                                    class="flex w-full cursor-pointer select-none items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-bold text-zinc-500 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:text-zinc-400 dark:hover:bg-brand-500/10 dark:hover:text-brand-400"
                                    aria-expanded="{{ ($openDurationConfigs[$i] ?? false) ? 'true' : 'false' }}">
                                <x-icon name="settings" class="size-3.5" />
                                قابلیت‌ها و پکیج‌های این مدت
                                @if(collect($row['features'] ?? [])->filter(fn ($f) => trim((string) $f) !== '')->isNotEmpty() || count($row['packages'] ?? []) > 0)
                                    <span class="rounded-full bg-brand-100 px-1.5 py-0.5 text-[10px] font-black text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">سفارشی</span>
                                @else
                                    <span class="text-[10px] font-medium text-zinc-400">(خالی = ارث‌بری از طرح)</span>
                                @endif
                                <x-icon name="{{ ($openDurationConfigs[$i] ?? false) ? 'chevron-up' : 'chevron-down' }}" class="ms-auto size-3.5" />
                            </button>
                            @if($openDurationConfigs[$i] ?? false)
                            <div class="mt-2 grid gap-3 rounded-xl bg-white p-3 ring-1 ring-zinc-200/70 lg:grid-cols-2 dark:bg-zinc-900/40 dark:ring-zinc-700/50">
                                {{-- کدام مدت در حال ویرایش است — وضوح بیشتر (Task 67) --}}
                                <p class="col-span-full flex flex-wrap items-center gap-1.5 border-b border-zinc-100 pb-2 text-[11px] font-black text-brand-600 dark:border-zinc-800 dark:text-brand-400">
                                    <x-icon name="calendar-clock" class="size-3.5 shrink-0" />
                                    پیکربندی اختصاصی مدت
                                    <span class="rounded-lg bg-brand-50 px-2 py-0.5 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">
                                        {{ ((int) ($row['duration_months'] ?? 0)) === 0 ? 'نامحدود' : fa_num($row['duration_months']) . ' ماه' }}
                                    </span>
                                    <span class="font-medium text-zinc-400">— فقط به همین مدت اعمال می‌شود؛ خالی بودن هر بخش = ارث‌بری از طرح</span>
                                </p>
                                {{-- قابلیت‌های اختصاصی این مدت — ریپیتر افزودن/حذف مثل قابلیت‌های طرح --}}
                                <div>
                                    <div class="mb-1 flex items-center justify-between gap-2">
                                        <p class="text-xs font-bold text-zinc-600 dark:text-zinc-300">قابلیت‌های این مدت</p>
                                        <button type="button" wire:click="addDurationFeature({{ $i }})"
                                                class="flex items-center gap-1 rounded-lg px-2 py-1 text-[11px] font-bold text-brand-600 transition-colors hover:bg-brand-50 dark:hover:bg-brand-500/10"
                                                title="افزودن قابلیت به این مدت" aria-label="افزودن قابلیت به این مدت">
                                            <x-icon name="plus" class="size-3.5" />
                                            افزودن
                                        </button>
                                    </div>
                                    <div class="space-y-1.5">
                                        @foreach($row['features'] ?? [] as $j => $feature)
                                            <div class="flex items-center gap-1.5" wire:key="plan-durfeat-{{ $i }}-{{ $j }}">
                                                <x-icon name="check-circle" class="size-3.5 shrink-0 text-brand-500" />
                                                <input type="text" wire:model="formDurations.{{ $i }}.features.{{ $j }}"
                                                       placeholder="مثلاً پشتیبانی اختصاصی ۳ ماهه" class="input flex-1" aria-label="قابلیت این مدت" />
                                                <button type="button" wire:click="removeDurationFeature({{ $i }}, {{ $j }})"
                                                        class="rounded-lg p-1.5 text-zinc-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
                                                        title="حذف قابلیت" aria-label="حذف قابلیت از این مدت">
                                                    <x-icon name="trash" class="size-3.5" />
                                                </button>
                                            </div>
                                        @endforeach
                                        @if(count($row['features'] ?? []) === 0)
                                            <p class="text-[11px] text-zinc-400">قابلیتی ثبت نشده — «افزودن» را بزنید (خالی = همان قابلیت‌های طرح).</p>
                                        @endif
                                    </div>
                                </div>
                                {{-- پکیج‌های اختصاصی این مدت --}}
                                <div>
                                    <p class="mb-1 text-xs font-bold text-zinc-600 dark:text-zinc-300">پکیج‌های این مدت + مدت دسترسی رایگان</p>
                                    <div class="space-y-1.5">
                                        @foreach($row['packages'] ?? [] as $j => $prow)
                                            <div class="flex items-center gap-1.5" wire:key="plan-durpkg-{{ $i }}-{{ $j }}">
                                                <select wire:model="formDurations.{{ $i }}.packages.{{ $j }}.package_id" class="input min-w-32 flex-1" aria-label="پکیج این مدت">
                                                    <option value="">انتخاب پکیج…</option>
                                                    @foreach($this->packages as $package)
                                                        <option value="{{ $package->id }}">{{ $package->name }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="number" wire:model="formDurations.{{ $i }}.packages.{{ $j }}.free_months" min="0" max="120"
                                                       class="input w-20" title="مدت دسترسی رایگان (ماه)" aria-label="ماه رایگان این پکیج" />
                                                <span class="whitespace-nowrap text-[10px] text-zinc-400">ماه</span>
                                                <button type="button" wire:click="removeDurationPackage({{ $i }}, {{ $j }})"
                                                        class="rounded-lg p-1.5 text-zinc-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
                                                        title="حذف پکیج از این مدت" aria-label="حذف پکیج از این مدت">
                                                    <x-icon name="x" class="size-3.5" />
                                                </button>
                                            </div>
                                            @error('formDurations.' . $i . '.packages.' . $j . '.free_months')
                                                <p class="text-xs font-bold text-rose-500">{{ $message }}</p>
                                            @enderror
                                        @endforeach
                                        <div class="flex items-center gap-1.5">
                                            <select wire:model="formDurations.{{ $i }}.packageToAdd" class="input min-w-32 flex-1" aria-label="افزودن پکیج به این مدت">
                                                <option value="">افزودن پکیج…</option>
                                                @foreach($this->packages as $package)
                                                    <option value="{{ $package->id }}">{{ $package->name }}</option>
                                                @endforeach
                                            </select>
                                            <button type="button" wire:click="addDurationPackage({{ $i }})"
                                                    class="rounded-lg p-2 text-brand-600 transition-colors hover:bg-brand-50 dark:hover:bg-brand-500/10"
                                                    title="افزودن پکیج به این مدت" aria-label="افزودن پکیج به این مدت">
                                                <x-icon name="plus" class="size-4" />
                                            </button>
                                        </div>
                                        <p class="text-[10px] text-zinc-400">خالی = همان پکیج‌های همراه طرح با مدت‌های خودشان</p>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                @endforeach
                @if(count($formDurations) === 0)
                    <p class="text-xs font-bold text-amber-600">مدتی ثبت نشده — حداقل یک مدت اعتبار الزامی است؛ «افزودن مدت» را بزنید.</p>
                @endif
                @error('formDurations')
                    <p class="text-xs font-bold text-rose-500">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="rounded-2xl bg-zinc-50 p-4 ring-1 ring-zinc-200/70 dark:bg-zinc-800/60 dark:ring-zinc-700/50">
                <label class="flex cursor-pointer items-start gap-3">
                    <x-toggle wire:model="form.is_one_time" />
                    <span>
                        <span class="block text-sm font-bold text-zinc-800 dark:text-zinc-100">یک‌بارمصرف</span>
                        <span class="mt-0.5 block text-xs leading-5 text-zinc-500 dark:text-zinc-400">هر مشتری فقط یک‌بار می‌تواند از این طرح استفاده کند.</span>
                    </span>
                </label>
            </div>
            <div class="rounded-2xl bg-zinc-50 p-4 ring-1 ring-zinc-200/70 dark:bg-zinc-800/60 dark:ring-zinc-700/50">
                <label class="flex cursor-pointer items-start gap-3">
                    <x-toggle wire:model="form.is_active" />
                    <span>
                        <span class="block text-sm font-bold text-zinc-800 dark:text-zinc-100">فعال در فروشگاه</span>
                        <span class="mt-0.5 block text-xs leading-5 text-zinc-500 dark:text-zinc-400">طرح فعال در صفحه اشتراک‌های فروشگاه و API نمایش داده می‌شود.</span>
                    </span>
                </label>
            </div>
        </div>

        {{-- features editor --}}
        <div class="rounded-2xl ring-1 ring-zinc-200/70 dark:ring-zinc-700/50">
            <div class="flex items-center justify-between gap-2 border-b border-zinc-100 px-4 py-3 dark:border-zinc-800">
                <div class="flex items-center gap-2 text-sm font-bold text-zinc-800 dark:text-zinc-100">
                    <x-icon name="list-checks" class="size-4 text-brand-600 dark:text-brand-400" />
                    قابلیت‌های طرح
                </div>
                <x-btn variant="soft" size="sm" icon="plus" wire:click="addFeature">افزودن قابلیت</x-btn>
            </div>
            <div class="space-y-2 p-4">
                @foreach($formFeatures as $i => $feature)
                    <div class="flex items-center gap-2" wire:key="feature-{{ $i }}">
                        <x-icon name="check-circle" class="size-4 shrink-0 text-brand-500" />
                        <input type="text" wire:model="formFeatures.{{ $i }}" placeholder="مثلاً پشتیبانی رایگان، نصب رایگان، …" class="input flex-1" />
                        <button type="button" wire:click="removeFeature({{ $i }})"
                                class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
                                title="حذف قابلیت">
                            <x-icon name="trash" class="size-4" />
                        </button>
                    </div>
                @endforeach
                @if(count($formFeatures) === 0)
                    <p class="text-xs text-zinc-400">قابلیتی ثبت نشده — «افزودن قابلیت» را بزنید.</p>
                @endif
            </div>
        </div>

        {{-- packages editor --}}
        <div class="rounded-2xl ring-1 ring-zinc-200/70 dark:ring-zinc-700/50">
            <div class="flex items-center justify-between gap-2 border-b border-zinc-100 px-4 py-3 dark:border-zinc-800">
                <div>
                    <div class="flex items-center gap-2 text-sm font-bold text-zinc-800 dark:text-zinc-100">
                        <x-icon name="package" class="size-4 text-brand-600 dark:text-brand-400" />
                        پکیج‌های همراه طرح
                    </div>
                    <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">با خرید این طرح، این پکیج‌ها به‌مدت تعیین‌شده رایگان قابل نصب می‌شوند. برای هر مدت می‌توانید از بخش «قابلیت‌ها و پکیج‌های این مدت» پیکربندی جداگانه بدهید.</p>
                </div>
                <x-btn variant="soft" size="sm" icon="plus" wire:click="addPackageRow">افزودن پکیج</x-btn>
            </div>
            <div class="space-y-2 p-4">
                @foreach($formPackages as $i => $row)
                    <div class="flex flex-wrap items-center gap-2" wire:key="plan-pkg-{{ $i }}">
                        <select wire:model="formPackages.{{ $i }}.package_id" class="input min-w-40 flex-1">
                            <option value="">انتخاب پکیج…</option>
                            @foreach($this->packages as $package)
                                <option value="{{ $package->id }}">{{ $package->name }}</option>
                            @endforeach
                        </select>
                        <div class="flex items-center gap-2">
                            <input type="number" wire:model="formPackages.{{ $i }}.free_months" min="0" max="120" class="input w-24" title="مدت دسترسی رایگان (ماه)" />
                            <span class="whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400">ماه رایگان{{ (int)($row['free_months'] ?? 1) === 0 ? ' (نامحدود)' : '' }}</span>
                        </div>
                        <button type="button" wire:click="removePackageRow({{ $i }})"
                                class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
                                title="حذف">
                            <x-icon name="trash" class="size-4" />
                        </button>
                    </div>
                @endforeach
                @if(count($formPackages) === 0)
                    <p class="text-xs text-zinc-400">پکیجی اضافه نشده — «افزودن پکیج» را بزنید.</p>
                @endif
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 border-t border-zinc-100 pt-4 dark:border-zinc-800">
            <x-btn variant="secondary" wire:click="$set('showModal', false)">انصراف</x-btn>
            <x-btn icon="save" wire:click="save" :loading="true">{{ $editingId ? 'ذخیره تغییرات' : 'ایجاد طرح' }}</x-btn>
        </div>
    </div>
</x-modal>
