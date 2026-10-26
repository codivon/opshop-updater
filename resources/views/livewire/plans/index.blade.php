<div class="space-y-6">
    {{-- header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="flex items-center gap-2 text-lg font-black text-zinc-900 dark:text-zinc-50">
                <x-icon name="crown" class="size-5 text-brand-600 dark:text-brand-400" />
                طرح‌های اشتراک
            </h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">طرح‌هایی که مشتریان از فروشگاه خریداری می‌کنند؛ رایگان یا پولی، همراه پکیج‌های دسترسی رایگان و قابلیت‌ها.</p>
        </div>
        <x-btn icon="plus" wire:click="openCreate" :loading="true">طرح جدید</x-btn>
    </div>

    {{-- stats --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-stat icon="crown" :value="fa_num(\App\Models\SubscriptionPlan::count())" label="کل طرح‌ها" />
        <x-stat icon="check-circle" :value="fa_num(\App\Models\SubscriptionPlan::where('is_active', true)->count())" label="طرح‌های فعال" />
        <x-stat icon="banknote" :value="fa_num(\App\Models\SubscriptionPlan::where('price', 0)->count())" label="طرح‌های رایگان" />
    </div>

    {{-- filters --}}
    <div class="card flex flex-col gap-3 p-4 lg:flex-row lg:items-center">
        <div class="relative flex-1">
            <x-icon name="search" class="pointer-events-none absolute start-3.5 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
            <input type="search" wire:model.live.debounce.400ms="search" placeholder="جستجوی نام، شناسه یا توضیحات طرح…" class="input ps-10" />
        </div>
        <select wire:model.live="status" class="input lg:w-40">
            <option value="">همه وضعیت‌ها</option>
            <option value="active">فعال</option>
            <option value="inactive">غیرفعال</option>
        </select>
        <div class="relative lg:w-48">
            <x-icon name="arrow-up-down" class="pointer-events-none absolute start-3.5 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
            <select wire:model.live="sort" class="input ps-10" aria-label="ترتیب نمایش">
                <option value="newest">جدیدترین</option>
                <option value="oldest">قدیمی‌ترین</option>
                <option value="id_desc">شناسه (نزولی)</option>
                <option value="id_asc">شناسه (صعودی)</option>
                <option value="name_asc">بر اساس نام (الفبا)</option>
                <option value="name_desc">بر اساس نام (معکوس)</option>
                <option value="price_desc">گران‌ترین</option>
                <option value="price_asc">ارزان‌ترین</option>
                <option value="sort_order">ترتیب نمایش فرانت</option>
            </select>
        </div>
        <div wire:loading wire:target="search, status, sort" class="flex items-center gap-2 text-xs text-brand-600 dark:text-brand-400">
            <x-icon name="loader" class="size-4 animate-spin" />
            در حال فیلتر…
        </div>
    </div>

    {{-- bulk toolbar --}}
    @if($selectedIds)
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-rose-50 p-3 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:ring-rose-400/20">
            <div class="flex items-center gap-2 text-sm font-bold text-rose-700 dark:text-rose-400">
                <x-icon name="check-square" class="size-4.5" />
                {{ fa_num(count($selectedIds)) }} طرح انتخاب شده است
            </div>
            <div class="flex items-center gap-2">
                <x-btn variant="secondary" size="sm" wire:click="clearSelection">انصراف از انتخاب</x-btn>
                <x-btn variant="danger" icon="trash" size="sm" wire:click="$set('confirmingBulkDelete', true)">حذف گروهی</x-btn>
            </div>
        </div>
    @endif

    {{-- table --}}
    <div class="card overflow-hidden">
        @if($this->records->count())
            <div class="table-wrap ring-0">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="w-8">
                                <input type="checkbox" class="checkbox" aria-label="انتخاب همه"
                                       @if($this->allPageSelected()) checked @endif
                                       wire:click="toggleSelectAll" />
                            </th>
                            <th>طرح</th>
                            <th>مدت اعتبار</th>
                            <th>قیمت</th>
                            <th class="hidden md:table-cell">پکیج‌های همراه</th>
                            <th class="hidden lg:table-cell">خریدها</th>
                            <th>وضعیت</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->records as $plan)
                            <tr wire:key="plan-{{ $plan->id }}">
                                <td>
                                    <input type="checkbox" class="checkbox" aria-label="انتخاب این طرح"
                                           wire:model.live="selectedIds" value="{{ $plan->id }}" />
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <x-icon name="crown" class="size-4 shrink-0 text-amber-500" />
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-bold text-zinc-800 dark:text-zinc-100">{{ $plan->name }}</p>
                                            <p class="truncate text-xs text-zinc-400" dir="ltr">{{ $plan->slug }}</p>
                                        </div>
                                    </div>
                                    @if($plan->description)
                                        <p class="mt-1 max-w-xs truncate text-xs text-zinc-500 dark:text-zinc-400">{{ \Illuminate\Support\Str::limit($plan->description, 60) }}</p>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap">
                                    @if($plan->durations->where('is_active', true)->isNotEmpty())
                                        {{-- چندگزینه‌ای: چیپ هر مدت + قیمت نهایی آن --}}
                                        <div class="flex max-w-xs flex-wrap items-center gap-1">
                                            @foreach($plan->durations->filter(fn ($d) => $d->is_active)->take(4) as $duration)
                                                <span class="inline-flex items-center gap-1 rounded-lg bg-zinc-100 px-2 py-0.5 text-[11px] font-bold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300"
                                                      title="{{ fa_num(money($duration->final_price)) }} تومان">
                                                    {{ $duration->duration_label }}
                                                    <span class="tabular-nums text-zinc-400">{{ $duration->final_price <= 0 ? 'رایگان' : fa_num(money($duration->final_price)) }}</span>
                                                </span>
                                            @endforeach
                                            @if($plan->durations->where('is_active', true)->count() > 4)
                                                <span class="text-[11px] font-bold text-zinc-400">+{{ fa_num($plan->durations->where('is_active', true)->count() - 4) }}</span>
                                            @endif
                                        </div>
                                    @elseif((int) $plan->duration_months === 0)
                                        <span class="inline-flex items-center gap-1 text-xs font-bold text-brand-600 dark:text-brand-400">
                                            <x-icon name="infinity" class="size-3.5" />
                                            نامحدود
                                        </span>
                                    @else
                                        <span class="text-sm tabular-nums text-zinc-600 dark:text-zinc-300">{{ fa_num($plan->duration_months) }} ماه</span>
                                    @endif
                                    @if($plan->is_one_time)
                                        <div class="mt-1"><x-badge variant="warning">یک‌بارمصرف</x-badge></div>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap">
                                    @if($plan->final_price <= 0)
                                        <x-badge variant="success" icon="gift">رایگان</x-badge>
                                    @else
                                        <span class="text-sm font-bold tabular-nums text-zinc-800 dark:text-zinc-100">{{ fa_num(money($plan->final_price)) }}</span>
                                        @if($plan->discount_price)
                                            <p class="text-xs tabular-nums text-zinc-400 line-through">{{ fa_num(money($plan->price)) }}</p>
                                        @endif
                                    @endif
                                </td>
                                <td class="hidden md:table-cell">
                                    @if($plan->packages_count > 0)
                                        <span class="inline-flex items-center gap-1.5 rounded-xl bg-brand-50 px-2.5 py-1 text-xs font-bold text-brand-700 dark:bg-brand-500/10 dark:text-brand-400">
                                            <x-icon name="package" class="size-3.5" />
                                            {{ fa_num($plan->packages_count) }} پکیج رایگان
                                        </span>
                                    @else
                                        <span class="text-xs text-zinc-400">—</span>
                                    @endif
                                </td>
                                <td class="hidden whitespace-nowrap text-sm tabular-nums text-zinc-600 lg:table-cell dark:text-zinc-300">
                                    {{ fa_num($plan->paid_orders_count) }}
                                </td>
                                <td>
                                    @if($plan->features && count($plan->features))
                                        <span class="me-1 inline-flex items-center gap-1 text-xs text-zinc-400" title="{{ implode(' · ', $plan->features) }}">
                                            <x-icon name="list-checks" class="size-3.5" />
                                            {{ fa_num(count($plan->features)) }} قابلیت
                                        </span>
                                    @endif
                                    <x-badge variant="{{ $plan->is_active ? 'success' : 'neutral' }}" class="mt-1">
                                        {{ $plan->is_active ? 'فعال' : 'غیرفعال' }}
                                    </x-badge>
                                </td>
                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        <button type="button" wire:click="openEdit({{ $plan->id }})"
                                                class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10 dark:hover:text-brand-400"
                                                title="ویرایش">
                                            <x-icon name="pencil" class="size-4.5" />
                                        </button>
                                        <button type="button" wire:click="toggleActive({{ $plan->id }})"
                                                class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-amber-50 hover:text-amber-600 dark:hover:bg-amber-500/10 dark:hover:text-amber-400"
                                                title="{{ $plan->is_active ? 'غیرفعال کردن' : 'فعال کردن' }}">
                                            <x-icon name="{{ $plan->is_active ? 'x-circle' : 'check-circle' }}" class="size-4.5" />
                                        </button>
                                        <button type="button" wire:click="$set('deleteId', {{ $plan->id }})"
                                                class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
                                                title="حذف">
                                            <x-icon name="trash" class="size-4.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-zinc-100 px-4 py-3 dark:border-zinc-800">
                {{ $this->records->links() }}
            </div>
        @else
            <x-empty icon="crown" title="طرحی یافت نشد" description="اولین طرح اشتراک را بسازید تا در فروشگاه به مشتریان نمایش داده شود." />
        @endif
    </div>

    {{-- create/edit modal --}}
    <x-modal wire:model="showModal" :title="$editingId ? 'ویرایش طرح اشتراک' : 'طرح اشتراک جدید'" size="xl">
        <div class="space-y-5">
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
                        <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">با خرید این طرح، این پکیج‌ها به‌مدت تعیین‌شده رایگان قابل نصب می‌شوند.</p>
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

    {{-- delete confirm --}}
    <x-modal wire:model="deleteId" title="حذف طرح اشتراک" size="sm">
        @if($deleteId)
            @php($target = \App\Models\SubscriptionPlan::find($deleteId))
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-rose-50 p-4 text-rose-700 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20">
                    <x-icon name="trash" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">
                        طرح «<strong>{{ $target?->name }}</strong>» حذف شود؟
                        سفارش‌های ثبت‌شده حفظ می‌شوند اما به «طرح حذف‌شده» تبدیل می‌گردند.
                    </p>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <x-btn variant="secondary" wire:click="$set('deleteId', null)">انصراف</x-btn>
                    <x-btn variant="danger" icon="trash" wire:click="delete" :loading="true">حذف قطعی</x-btn>
                </div>
            </div>
        @endif
    </x-modal>

    {{-- bulk delete confirm --}}
    <x-modal wire:model="confirmingBulkDelete" title="حذف گروهی طرح‌ها" size="sm">
        @if($confirmingBulkDelete)
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-rose-50 p-4 text-rose-700 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20">
                    <x-icon name="trash" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">
                        <strong>{{ fa_num(count($selectedIds)) }}</strong> طرح انتخاب‌شده برای همیشه حذف شود؟
                    </p>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <x-btn variant="secondary" wire:click="clearSelection">انصراف</x-btn>
                    <x-btn variant="danger" icon="trash" wire:click="bulkDelete" :loading="true">حذف {{ fa_num(count($selectedIds)) }} طرح</x-btn>
                </div>
            </div>
        @endif
    </x-modal>
</div>
