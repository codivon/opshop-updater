<div class="space-y-6">
    {{-- back link --}}
    <a href="{{ route('admin.packages.index') }}" wire:navigate
       class="inline-flex items-center gap-1.5 text-sm font-medium text-zinc-500 transition-colors hover:text-brand-500 dark:text-zinc-400 dark:hover:text-brand-400">
        <x-icon name="arrow-right" class="size-4" />
        بازگشت به پکیج‌ها
    </a>

    {{-- hero --}}
    <div class="card overflow-hidden">
        <div class="p-5 text-white sm:p-6" style="background-image: linear-gradient(to bottom left, var(--color-brand-600), var(--color-teal-600))">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex min-w-0 flex-1 items-start gap-4">
                    @if($package->thumbnail)
                        <img src="{{ $package->thumbnail_url }}" alt="{{ $package->name }}"
                             class="size-14 shrink-0 rounded-2xl object-cover" style="box-shadow: 0 0 0 1px rgba(255,255,255,.25)" />
                    @else
                        <div class="flex size-14 shrink-0 items-center justify-center rounded-2xl" style="background: rgba(255,255,255,.22)">
                            <x-icon name="package" class="size-6" />
                        </div>
                    @endif

                    <div class="min-w-0">
                        <h2 class="truncate text-lg font-black text-white sm:text-xl">{{ $package->name }}</h2>
                        <p class="mt-1 font-mono text-xs opacity-80" dir="ltr">{{ $package->slug }}</p>

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            @php($statusLabel = match($package->status) {
                                'active'   => 'منتشر شده',
                                'draft'    => 'پیش‌نویس',
                                'archived' => 'آرشیو شده',
                                default    => $package->status,
                            })
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold" style="background: rgba(255,255,255,.22)">
                                <x-icon name="info" class="size-3" />
                                {{ $statusLabel }}
                            </span>
                            @if($package->categoryModel)
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold" style="background: rgba(255,255,255,.22)">
                                    <x-icon name="tag" class="size-3" />
                                    {{ $package->categoryModel->name }}
                                </span>
                            @endif
                            @if($package->is_free)
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold" style="background: rgba(255,255,255,.22)">
                                    <x-icon name="gift" class="size-3" />
                                    رایگان
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <button type="button" wire:click="openEditPackage"
                            class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-white/50"
                            style="background: rgba(255,255,255,.15)">
                        <x-icon name="pencil" class="size-4" />
                        ویرایش
                    </button>
                    <button type="button" wire:click="$set('deleteId', {{ $package->id }})"
                            class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2 text-sm font-semibold text-rose-600 transition-colors hover:bg-rose-50">
                        <x-icon name="trash" class="size-4" />
                        حذف
                    </button>
                </div>
            </div>

            {{-- stats row --}}
            <div class="mt-5 flex flex-wrap items-center gap-3">
                <div class="inline-flex items-center gap-2 rounded-xl px-3 py-2" style="background: rgba(255,255,255,.15)">
                    <x-icon name="download" class="size-4 opacity-80" />
                    <span class="text-sm font-black tabular-nums">{{ fa_num($package->downloads_count) }}</span>
                    <span class="text-xs opacity-80">دانلود</span>
                </div>
                <div class="inline-flex items-center gap-2 rounded-xl px-3 py-2" style="background: rgba(255,255,255,.15)">
                    <x-icon name="credit-card" class="size-4 opacity-80" />
                    <span class="text-sm font-black tabular-nums">{{ fa_num($package->purchases_count) }}</span>
                    <span class="text-xs opacity-80">خرید</span>
                </div>
                <div class="inline-flex items-center gap-2 rounded-xl px-3 py-2" style="background: rgba(255,255,255,.15)">
                    <x-icon name="layers" class="size-4 opacity-80" />
                    <span class="text-sm font-black tabular-nums">{{ fa_num($this->counts['versions']) }}</span>
                    <span class="text-xs opacity-80">نسخه</span>
                </div>
                <div class="inline-flex items-center gap-2 rounded-xl px-3 py-2" style="background: rgba(255,255,255,.15)">
                    <x-icon name="key" class="size-4 opacity-80" />
                    <span class="text-sm font-black tabular-nums">{{ fa_num($this->counts['active_licenses']) }}</span>
                    <span class="text-xs opacity-80">لایسنس فعال</span>
                </div>
            </div>

            {{-- price + latest version + project --}}
            <div class="mt-5 flex flex-wrap items-center justify-between gap-4 border-t pt-4" style="border-color: rgba(255,255,255,.25)">
                <div class="flex flex-wrap items-center gap-6">
                    <div>
                        <p class="text-[11px] font-semibold uppercase opacity-80">قیمت</p>
                        <p class="mt-1 text-lg font-black tabular-nums text-white">{{ $package->is_free ? 'رایگان' : money($package->default_price) }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase opacity-80">آخرین نسخه</p>
                        @if($package->latestVersion)
                            <p class="mt-1 font-mono text-lg font-black text-white" dir="ltr">v{{ $package->latestVersion->version }}</p>
                        @else
                            <p class="mt-1 text-lg font-black text-white">—</p>
                        @endif
                    </div>
                </div>
                <a href="{{ route('admin.projects.index') }}" wire:navigate
                   class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-medium text-white transition-colors hover:bg-white/50" style="background: rgba(255,255,255,.15)">
                    <x-icon name="folder" class="size-3.5" />
                    {{ $package->project?->name ?? 'بدون پروژه' }}
                </a>
            </div>
        </div>
    </div>

    {{-- tabs --}}
    <div class="card p-5 sm:p-6">
        @php($tabs = [
            ['id' => 'info',      'label' => 'اطلاعات',       'icon' => 'info'],
            ['id' => 'versions',  'label' => 'نسخه‌ها',        'icon' => 'layers', 'count' => fa_num($this->counts['versions'])],
            ['id' => 'plans',     'label' => 'پلن‌های قیمت',   'icon' => 'tag',    'count' => fa_num($this->counts['plans'])],
            ['id' => 'licenses',  'label' => 'لایسنس‌ها',      'icon' => 'key',    'count' => fa_num($this->counts['licenses'])],
            ['id' => 'images',    'label' => 'گالری',          'icon' => 'image',  'count' => fa_num($this->counts['images'])],
        ])
        <x-tabs :tabs="$tabs">
        <x-slot:info>
                <div class="space-y-6">
                    <div class="space-y-5">
                        <div>
                            <h3 class="mb-2 flex items-center gap-2 text-sm font-bold text-zinc-800 dark:text-zinc-100">
                                <x-icon name="file-text" class="size-4 text-brand-600" />
                                توضیحات
                            </h3>
                            <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200 dark:bg-zinc-800/40 dark:ring-zinc-700">
                                {{-- Raw HTML rendering (CKEditor). Legacy plain-text descriptions are
                                     escaped + nl2br'd so old content still reads correctly. --}}
                                @php($descriptionHtml = preg_match('/<[a-z][^>]*>/i', (string) $package->description) ? (string) $package->description : ($package->description ? '<p>' . nl2br(e($package->description)) . '</p>' : ''))
                                <div class="rich-content">{!! $descriptionHtml ?: '<p>توضیحاتی ثبت نشده است.</p>' !!}</div>
                            </div>
                        </div>

                        @if($package->short_description)
                            <div>
                                <h3 class="mb-2 flex items-center gap-2 text-sm font-bold text-zinc-800 dark:text-zinc-100">
                                    <x-icon name="info" class="size-4 text-brand-600" />
                                    توضیح کوتاه
                                </h3>
                                <p class="max-h-96 overflow-y-auto whitespace-pre-line text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ $package->short_description }}</p>
                            </div>
                        @endif
                    </div>

                    <div>
                        <div class="text-sm">
                            <div class="flex items-center justify-between gap-4 border-b border-zinc-100 py-3 dark:border-zinc-800">
                                <span class="text-zinc-500 dark:text-zinc-400">نویسنده</span>
                                <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $package->author ?: '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-4 border-b border-zinc-100 py-3 dark:border-zinc-800">
                                <span class="text-zinc-500 dark:text-zinc-400">نام ماژول</span>
                                <span class="flex items-center gap-1.5">
                                    <code class="font-mono text-xs text-zinc-800 dark:text-zinc-200" dir="ltr">{{ $package->module_name ?: '—' }}</code>
                                    @if($package->module_name)
                                        <button type="button" data-copy="{{ $package->module_name }}"
                                                onclick="navigator.clipboard.writeText(this.dataset.copy).then(() => window.dispatchEvent(new CustomEvent('toast', {detail: {message: 'نام ماژول کپی شد', type: 'success'}})))"
                                                class="rounded-lg p-1.5 text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-300" title="کپی">
                                            <x-icon name="copy" class="size-3.5" />
                                        </button>
                                    @endif
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-4 border-b border-zinc-100 py-3 dark:border-zinc-800">
                                <span class="text-zinc-500 dark:text-zinc-400">ترتیب نمایش</span>
                                <span class="font-medium tabular-nums text-zinc-800 dark:text-zinc-200">{{ fa_num($package->sort_order) }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-4 border-b border-zinc-100 py-3 dark:border-zinc-800">
                                <span class="text-zinc-500 dark:text-zinc-400">تاریخ ایجاد</span>
                                <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ verta_date($package->created_at) }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-4 border-b border-zinc-100 py-3 dark:border-zinc-800">
                                <span class="text-zinc-500 dark:text-zinc-400">آخرین به‌روزرسانی</span>
                                <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ verta_date($package->updated_at) }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-4 py-3">
                                <span class="text-zinc-500 dark:text-zinc-400">پروژه</span>
                                <a href="{{ route('admin.projects.index') }}" wire:navigate class="link inline-flex items-center gap-1.5">
                                    <x-icon name="folder" class="size-3.5" />
                                    {{ $package->project?->name ?? '—' }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
        </x-slot:info>
        <x-slot:versions>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">نسخه‌های منتشرشده و فایل ZIP هر نسخه.</p>
                    <x-btn icon="plus" size="sm" wire:click="openVersionCreate">نسخه جدید</x-btn>
                </div>

                @if($this->versions->count())
                    <div class="mt-4 table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>نسخه</th>
                                    <th>نوع</th>
                                    <th class="hidden sm:table-cell">تاریخ انتشار</th>
                                    <th class="hidden md:table-cell">حجم فایل</th>
                                    <th class="hidden lg:table-cell">دانلود</th>
                                    <th class="hidden md:table-cell">الزام</th>
                                    <th>وضعیت</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($this->versions as $version)
                                    <tr wire:key="version-{{ $version->id }}">
                                        <td>
                                            <span class="font-mono font-bold text-zinc-800 dark:text-zinc-100" dir="ltr">v{{ $version->version }}</span>
                                        </td>
                                        <td>
                                            @php($typeVariant = match($version->type) {
                                                'major' => 'primary',
                                                'minor' => 'info',
                                                default => 'neutral',
                                            })
                                            <x-badge :variant="$typeVariant">{{ $version->type }}</x-badge>
                                        </td>
                                        <td class="hidden text-sm text-zinc-500 sm:table-cell">{{ verta_date($version->release_date) ?? '—' }}</td>
                                        <td class="hidden text-sm text-zinc-500 md:table-cell">{{ bytes_human($version->file_size) ?? '—' }}</td>
                                        <td class="hidden tabular-nums text-zinc-600 lg:table-cell dark:text-zinc-300">{{ fa_num($version->downloads_count) }}</td>
                                        <td class="hidden md:table-cell">
                                            @if($version->is_mandatory)
                                                <x-badge variant="warning">اجباری</x-badge>
                                            @else
                                                <span class="text-xs text-zinc-400">اختیاری</span>
                                            @endif
                                        </td>
                                        <td>
                                            @php($vStatusVariant = match($version->status) {
                                                'active'   => 'success',
                                                'draft'    => 'warning',
                                                'archived' => 'neutral',
                                                default    => 'neutral',
                                            })
                                            <x-badge :variant="$vStatusVariant">{{ match($version->status) { 'active' => 'منتشر شده', 'draft' => 'پیش‌نویس', 'archived' => 'آرشیو', default => $version->status } }}</x-badge>
                                        </td>
                                        <td>
                                            <div class="flex items-center justify-end gap-1">
                                                <button type="button" wire:click="openVersionEdit({{ $version->id }})"
                                                        class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10 dark:hover:text-brand-400"
                                                        title="ویرایش نسخه">
                                                    <x-icon name="pencil" class="size-4" />
                                                </button>
                                                <button type="button" wire:click="$set('deleteVersionId', {{ $version->id }})"
                                                        class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
                                                        title="حذف نسخه">
                                                    <x-icon name="trash" class="size-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                        {{ $this->versions->links() }}
                    </div>
                @else
                    <x-empty icon="layers" title="نسخه‌ای ثبت نشده" description="اولین نسخه پکیج را با آپلود فایل ZIP ایجاد کنید.">
                        <x-btn variant="soft" icon="plus" wire:click="openVersionCreate">ایجاد نسخه</x-btn>
                    </x-empty>
                @endif
        </x-slot:versions>
        <x-slot:plans>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">طرح‌های قیمت‌گذاری خرید این پکیج.</p>
                    <x-btn icon="plus" size="sm" wire:click="openPlanCreate">پلن جدید</x-btn>
                </div>

                @if($this->pricingPlans->count())
                    <div class="mt-4 table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>نام طرح</th>
                                    <th>مدت</th>
                                    <th>قیمت</th>
                                    <th>قیمت با تخفیف</th>
                                    <th class="hidden md:table-cell">وضعیت</th>
                                    <th class="hidden lg:table-cell">ترتیب</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($this->pricingPlans as $plan)
                                    <tr wire:key="plan-{{ $plan->id }}">
                                        <td>
                                            <p class="font-semibold text-zinc-800 dark:text-zinc-100">{{ $plan->name }}</p>
                                            @if($plan->description)
                                                <p class="mt-0.5 truncate text-xs text-zinc-400">{{ $plan->description }}</p>
                                            @endif
                                        </td>
                                        <td class="text-sm text-zinc-600 dark:text-zinc-300">
                                            {{ $plan->is_one_time ? 'یک‌بار مصرف' : ($plan->duration_months ? fa_num($plan->duration_months) . ' ماه' : 'نامحدود') }}
                                        </td>
                                        <td class="text-sm font-semibold tabular-nums text-zinc-800 dark:text-zinc-100">{{ money($plan->price) }}</td>
                                        <td class="text-sm tabular-nums">
                                            @if($plan->discount_price !== null)
                                                <span class="font-semibold text-amber-600 dark:text-amber-400">{{ money($plan->discount_price) }}</span>
                                            @else
                                                <span class="text-zinc-400">—</span>
                                            @endif
                                        </td>
                                        <td class="hidden md:table-cell">
                                            @if($plan->is_active)
                                                <x-badge variant="success">فعال</x-badge>
                                            @else
                                                <x-badge variant="neutral">غیرفعال</x-badge>
                                            @endif
                                        </td>
                                        <td class="hidden tabular-nums text-zinc-500 lg:table-cell">{{ fa_num($plan->sort_order) }}</td>
                                        <td>
                                            <div class="flex items-center justify-end gap-1">
                                                <button type="button" wire:click="openPlanEdit({{ $plan->id }})"
                                                        class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10 dark:hover:text-brand-400"
                                                        title="ویرایش پلن">
                                                    <x-icon name="pencil" class="size-4" />
                                                </button>
                                                <button type="button" wire:click="$set('deletePlanId', {{ $plan->id }})"
                                                        class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
                                                        title="حذف پلن">
                                                    <x-icon name="trash" class="size-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <x-empty icon="tag" title="پلن قیمتی ثبت نشده" description="برای فروش پکیج حداقل یک طرح قیمت‌گذاری ایجاد کنید.">
                        <x-btn variant="soft" icon="plus" wire:click="openPlanCreate">ایجاد پلن</x-btn>
                    </x-empty>
                @endif
        </x-slot:plans>
        <x-slot:licenses>
                @if($this->licenses->count())
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>کلید لایسنس</th>
                                    <th>مشتری</th>
                                    <th>وضعیت</th>
                                    <th class="hidden sm:table-cell">اعتبار</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($this->licenses as $license)
                                    <tr wire:key="license-{{ $license->id }}">
                                        <td>
                                            <span class="flex items-center gap-1.5">
                                                <code class="font-mono text-xs text-zinc-800 dark:text-zinc-200" dir="ltr">{{ $license->license_key }}</code>
                                                <button type="button" data-copy="{{ $license->license_key }}"
                                                        onclick="navigator.clipboard.writeText(this.dataset.copy).then(() => window.dispatchEvent(new CustomEvent('toast', {detail: {message: 'کلید لایسنس کپی شد', type: 'success'}})))"
                                                        class="rounded-lg p-1.5 text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-300" title="کپی">
                                                    <x-icon name="copy" class="size-3.5" />
                                                </button>
                                            </span>
                                        </td>
                                        <td class="text-sm text-zinc-600 dark:text-zinc-300">{{ $license->customer?->name ?? '—' }}</td>
                                        <td>
                                            @php($lVariant = match($license->status) {
                                                'active'  => 'success',
                                                'expired' => 'danger',
                                                'revoked' => 'danger',
                                                default   => 'neutral',
                                            })
                                            <x-badge :variant="$lVariant">{{ match($license->status) { 'active' => 'فعال', 'expired' => 'منقضی', 'revoked' => 'لغو شده', default => $license->status } }}</x-badge>
                                        </td>
                                        <td class="hidden text-sm text-zinc-500 sm:table-cell">
                                            {{ verta_date($license->starts_at) ?? '—' }}
                                            <x-icon name="arrow-left" class="inline size-3 text-zinc-300" />
                                            {{ verta_date($license->expires_at) ?? 'نامحدود' }}
                                        </td>
                                        <td>
                                            <div class="flex items-center justify-end">
                                                <a href="{{ route('admin.licenses.show', $license) }}" wire:navigate
                                                   class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-brand-600 transition-colors hover:bg-brand-50 dark:text-brand-400 dark:hover:bg-brand-500/10">
                                                    <x-icon name="eye" class="size-4" />
                                                    مشاهده
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                        {{ $this->licenses->links() }}
                    </div>
                @else
                    <x-empty icon="key" title="لایسنسی برای این پکیج ثبت نشده" description="لایسنس‌ها پس از خرید مشتریان به‌صورت خودکار ایجاد می‌شوند." />
                @endif
        </x-slot:licenses>
        <x-slot:images>
                {{-- upload box --}}
                <div class="rounded-xl border border-dashed border-zinc-200 p-5 dark:border-zinc-700">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="flex items-center gap-2 text-sm font-bold text-zinc-800 dark:text-zinc-100">
                                <x-icon name="upload" class="size-4 text-brand-600" />
                                افزودن تصاویر به گالری
                            </p>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">JPG، PNG، WEBP یا GIF — حداکثر ۳ مگابایت برای هر تصویر و ۱۰ تصویر در هر بار.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="file" multiple accept="image/jpeg,image/png,image/webp,image/gif" wire:model="newImages" class="input w-full sm:flex-1" />
                            <x-btn icon="upload" wire:click="uploadImages" :loading="true">آپلود</x-btn>
                        </div>
                    </div>
                    @if(count($this->newImages))
                        <p class="mt-3 text-xs font-medium text-brand-600 dark:text-brand-400">
                            {{ fa_num(count($this->newImages)) }} تصویر انتخاب شد — برای افزودن به گالری «آپلود» را بزنید.
                        </p>
                    @endif
                    <div wire:loading wire:target="newImages" class="mt-3 flex items-center gap-2 text-xs text-brand-600 dark:text-brand-400">
                        <x-icon name="loader" class="size-4 animate-spin" />
                        در حال آپلود…
                    </div>
                </div>

                {{-- image grid --}}
                @if($this->images->count())
                    <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach($this->images as $image)
                            <div class="overflow-hidden rounded-xl bg-white ring-1 ring-zinc-200/80 dark:bg-zinc-900 dark:ring-zinc-700" wire:key="pkg-image-{{ $image->id }}">
                                <div class="aspect-video w-full overflow-hidden bg-zinc-100 dark:bg-zinc-800">
                                    <img src="{{ $image->url }}" alt="{{ $image->original_name }}" loading="lazy" class="h-full w-full object-cover" />
                                </div>
                                <div class="flex items-center justify-between gap-2 p-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-xs font-medium text-zinc-700 dark:text-zinc-200" dir="ltr">{{ $image->original_name }}</p>
                                        <p class="mt-0.5 text-[11px] text-zinc-400">{{ bytes_human($image->size) ?? '—' }}</p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-1">
                                        <button type="button" wire:click="moveImage({{ $image->id }}, 'up')" {{ $loop->first ? 'disabled' : '' }}
                                                class="rounded-lg p-1.5 text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-600 disabled:pointer-events-none disabled:opacity-50 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                                                title="انتقال به بالا">
                                            <x-icon name="chevron-up" class="size-4" />
                                        </button>
                                        <button type="button" wire:click="moveImage({{ $image->id }}, 'down')" {{ $loop->last ? 'disabled' : '' }}
                                                class="rounded-lg p-1.5 text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-600 disabled:pointer-events-none disabled:opacity-50 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                                                title="انتقال به پایین">
                                            <x-icon name="chevron-down" class="size-4" />
                                        </button>
                                        <button type="button" wire:click="$set('deleteImageId', {{ $image->id }})"
                                                class="rounded-lg p-1.5 text-zinc-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
                                                title="حذف تصویر">
                                            <x-icon name="trash" class="size-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <x-empty icon="image" title="گالری خالی است" description="تصاویری از محیط پکیج اضافه کنید تا در صفحه فروش نمایش داده شوند." />
                @endif
        </x-slot:images>
        </x-tabs>
    </div>

    {{-- ==================== package edit modal ==================== --}}
    <x-modal wire:model="showModal" title="ویرایش پکیج" size="lg">
        <form wire:submit="savePackage" class="space-y-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="پروژه" required>
                    <select wire:model="form.project_id" class="input {{ $errors->has('form.project_id') ? 'input-error' : '' }}">
                        <option value="">انتخاب پروژه…</option>
                        @foreach($this->projects as $project)
                            <option value="{{ $project->id }}">{{ $project->name }}</option>
                        @endforeach
                    </select>
                </x-field>

                <x-field label="وضعیت" required>
                    <x-select wire:model="form.status" :options="[
                        'draft'    => 'پیش‌نویس',
                        'active'   => 'منتشر شده',
                        'archived' => 'آرشیو شده',
                    ]" />
                </x-field>
            </div>

            <x-field label="نام پکیج" required>
                <x-input wire:model="form.name" icon="package" :class="$errors->has('form.name') ? 'input-error' : ''" />
            </x-field>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="نامک (Slug)" hint="در صورت خالی بودن، از نام پکیج ساخته می‌شود.">
                    <x-input wire:model="form.slug" icon="link" dir="ltr" :class="$errors->has('form.slug') ? 'input-error' : ''" />
                </x-field>

                <x-field label="نام ماژول">
                    <x-input wire:model="form.module_name" icon="terminal" dir="ltr" :class="$errors->has('form.module_name') ? 'input-error' : ''" />
                </x-field>
            </div>

            <x-field label="توضیح کوتاه" hint="متن کامل (تا ۱۰ هزار کاراکتر) — پشتیبانی از بیش از ۱۰۰۰ کلمه.">
                <x-textarea wire:model="form.short_description" rows="5" :class="$errors->has('form.short_description') ? 'input-error' : ''"></x-textarea>
            </x-field>

            <x-ckeditor model="form.description" label="توضیحات کامل" :value="$form['description'] ?? ''" error="form.description" />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="نویسنده">
                    <x-input wire:model="form.author" icon="user" :class="$errors->has('form.author') ? 'input-error' : ''" />
                </x-field>

                <x-field label="دسته‌بندی">
                    <x-select wire:model="form.category_id" :options="$this->categories" placeholder="انتخاب دسته‌بندی…" />
                </x-field>
            </div>

            {{-- thumbnail management --}}
            <div class="space-y-3 rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200 dark:bg-zinc-800/40 dark:ring-zinc-700">
                <p class="flex items-center gap-2 text-sm font-bold text-zinc-800 dark:text-zinc-100">
                    <x-icon name="image" class="size-4 text-brand-600" />
                    تصویر شاخص
                </p>

                @if($package->thumbnail)
                    <div class="flex items-center gap-3">
                        <img src="{{ $package->thumbnail_url }}" alt="{{ $package->name }}" class="size-14 shrink-0 rounded-xl object-cover" />
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">تصویر شاخص فعلی</p>
                    </div>
                    <x-toggle wire:model="removeThumbnail" label="حذف تصویر شاخص فعلی" />
                @endif

                <x-field label="فایل تصویر جدید">
                    {{-- wire:ignore keeps the generated preview nodes alive across Livewire morphs --}}
                    <div wire:key="ck-thumb-preview" wire:ignore x-data="filePreview()" class="space-y-2">
                        <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" wire:model="thumbnailFile" x-ref="input" class="input" />
                        <template x-for="(preview, i) in previews" :key="i">
                            <div class="flex items-center gap-3">
                                <img :src="preview.url" :alt="preview.name"
                                     class="size-20 shrink-0 rounded-xl bg-zinc-100 object-cover ring-1 ring-zinc-200 dark:bg-zinc-800 dark:ring-zinc-700" />
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-medium text-zinc-700 dark:text-zinc-200" x-text="preview.name"></p>
                                    <p class="mt-0.5 text-[11px] text-zinc-400">پیش‌نمایش تصویر انتخاب‌شده — با ذخیره جایگزین می‌شود</p>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div wire:loading wire:target="thumbnailFile" class="mt-2 flex items-center gap-2 text-xs text-brand-600 dark:text-brand-400">
                        <x-icon name="loader" class="size-4 animate-spin" />
                        در حال آپلود…
                    </div>
                </x-field>

                <x-field label="یا آدرس تصویر (URL)" error="form.thumbnail_url">
                    <x-input wire:model="form.thumbnail_url" placeholder="https://example.com/thumb.png" icon="link" dir="ltr" :class="$errors->has('form.thumbnail_url') ? 'input-error' : ''" />
                </x-field>
            </div>

            {{-- gallery upload --}}
            <div class="space-y-2 rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200 dark:bg-zinc-800/40 dark:ring-zinc-700">
                <p class="flex items-center gap-2 text-sm font-bold text-zinc-800 dark:text-zinc-100">
                    <x-icon name="upload" class="size-4 text-brand-600" />
                    افزودن تصاویر گالری
                </p>
                {{-- wire:ignore keeps the generated preview grid alive across Livewire morphs --}}
                <div wire:key="ck-gallery-preview" wire:ignore x-data="filePreview({ multiple: true })" class="space-y-2">
                    <input type="file" multiple accept="image/jpeg,image/png,image/webp,image/gif" wire:model="newGallery" x-ref="input" class="input" />
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">حداکثر ۱۰ تصویر؛ به گالری فعلی اضافه می‌شود.</p>
                    <template x-if="previews.length">
                        <div class="grid grid-cols-6 gap-2">
                            <template x-for="(preview, i) in previews" :key="i">
                                <img :src="preview.url" :alt="preview.name" :title="preview.name"
                                     class="h-16 w-full rounded-lg bg-zinc-100 object-cover ring-1 ring-zinc-200 dark:bg-zinc-800 dark:ring-zinc-700" />
                            </template>
                        </div>
                    </template>
                </div>
                <div wire:loading wire:target="newGallery" class="flex items-center gap-2 text-xs text-brand-600 dark:text-brand-400">
                    <x-icon name="loader" class="size-4 animate-spin" />
                    در حال آپلود…
                </div>
            </div>

            {{-- pricing --}}
            <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200 dark:bg-zinc-800/40 dark:ring-zinc-700">
                {{-- PERF: بدون .live — نمایش فیلد قیمت به Alpine ($wire) واگذار شد تا toggle بدون رفت‌وبرگشت سرور باشد؛ مقدار با ذخیره فرم ارسال می‌شود --}}
                <x-toggle wire:model="form.is_free" label="این پکیج رایگان است" />
                <div class="mt-4" x-show="! $wire.form.is_free" x-cloak>
                    <x-field label="قیمت پیش‌فرض (تومان)">
                        <x-input wire:model="form.default_price" type="number" min="0" icon="banknote" dir="ltr" :class="$errors->has('form.default_price') ? 'input-error' : ''" />
                    </x-field>
                </div>
            </div>

            <x-field label="ترتیب نمایش">
                <x-input wire:model="form.sort_order" type="number" min="0" icon="list" dir="ltr" class="sm:w-44 {{ $errors->has('form.sort_order') ? 'input-error' : '' }}" />
            </x-field>

            <div class="flex items-center justify-end gap-2 pt-2">
                <x-btn variant="secondary" wire:click="$set('showModal', false)">انصراف</x-btn>
                <x-btn type="submit" icon="save" :loading="true">ذخیره</x-btn>
            </div>
        </form>
    </x-modal>

    {{-- ==================== package delete confirm ==================== --}}
    <x-modal wire:model="deleteId" title="حذف پکیج" size="sm">
        @if($deleteId)
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-rose-50 p-4 text-rose-700 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20">
                    <x-icon name="alert-triangle" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">
                        آیا از حذف پکیج «<strong>{{ $package->name }}</strong>» مطمئن هستید؟
                        تصویر شاخص، گالری و تمام نسخه‌ها و فایل‌های آن نیز حذف می‌شوند. این عمل قابل بازگشت نیست.
                    </p>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <x-btn variant="secondary" wire:click="$set('deleteId', null)">انصراف</x-btn>
                    <x-btn variant="danger" icon="trash" wire:click="deletePackage" :loading="true">حذف قطعی</x-btn>
                </div>
            </div>
        @endif
    </x-modal>

    {{-- ==================== version modal ==================== --}}
    <x-modal wire:model="showVersionModal" :title="$editingVersionId ? 'ویرایش نسخه' : 'نسخه جدید'" subtitle="فایل ZIP نسخه روی دیسک local ذخیره و هش SHA-256 آن محاسبه می‌شود." size="xl">
        <form wire:submit="saveVersion" class="space-y-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="شماره نسخه" required hint="مثلاً 1.2.0 — برای هر پکیج یکتا است.">
                    <x-input wire:model="versionForm.version" placeholder="1.2.0" icon="layers" dir="ltr" :class="$errors->has('versionForm.version') ? 'input-error' : ''" />
                </x-field>

                <x-field label="نوع نسخه" required>
                    <x-select wire:model="versionForm.type" :options="[
                        'major' => 'Major (تغییرات بزرگ)',
                        'minor' => 'Minor (قابلیت جدید)',
                        'patch' => 'Patch (رفع باگ)',
                    ]" />
                </x-field>
            </div>

            {{-- zip file --}}
            <div class="space-y-2 rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200 dark:bg-zinc-800/40 dark:ring-zinc-700">
                <p class="flex items-center gap-2 text-sm font-bold text-zinc-800 dark:text-zinc-100">
                    <x-icon name="archive" class="size-4 text-brand-600" />
                    فایل ZIP نسخه
                </p>
                @if($editingVersionId)
                    @php($currentVersion = \App\Models\PackageVersion::find($editingVersionId))
                    @if($currentVersion?->file_path)
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            فایل فعلی: <code class="font-mono" dir="ltr">{{ \Illuminate\Support\Str::limit($currentVersion->file_path, 40) }}</code>
                            ({{ bytes_human($currentVersion->file_size) ?? '—' }}) — در صورت انتخاب فایل جدید، فایل قدیمی حذف و جایگزین می‌شود.
                        </p>
                    @endif
                @endif
                <input type="file" accept=".zip,application/zip" wire:model="versionFile" class="input" />
                @php($vfErrBag = $errors ?? null)
                @if($vfErrBag?->has('versionFile'))
                    <p class="flex items-center gap-1.5 text-xs font-medium text-rose-600 dark:text-rose-400">
                        <x-icon name="alert-circle" class="size-3.5" />
                        {{ $vfErrBag->first('versionFile') }}
                    </p>
                @endif
                <div wire:loading wire:target="versionFile" class="flex items-center gap-2 text-xs text-brand-600 dark:text-brand-400">
                    <x-icon name="loader" class="size-4 animate-spin" />
                    در حال آپلود…
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="تاریخ انتشار">
                    <input type="date" wire:model="versionForm.release_date" class="input {{ $errors->has('versionForm.release_date') ? 'input-error' : '' }}" dir="ltr" />
                </x-field>

                <x-field label="وضعیت" required>
                    <x-select wire:model="versionForm.status" :options="[
                        'draft'    => 'پیش‌نویس',
                        'active'   => 'منتشر شده',
                        'archived' => 'آرشیو شده',
                    ]" />
                </x-field>
            </div>

            <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200 dark:bg-zinc-800/40 dark:ring-zinc-700">
                <x-toggle wire:model="versionForm.is_mandatory" label="این نسخه برای همه کاربران اجباری است" />
            </div>

            {{-- requirements --}}
            <div>
                <p class="mb-2 text-sm font-bold text-zinc-800 dark:text-zinc-100">پیش‌نیازها</p>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-field label="حداقل نسخه پروژه">
                        <x-input wire:model="versionForm.min_project_version" placeholder="1.5.0" dir="ltr" :class="$errors->has('versionForm.min_project_version') ? 'input-error' : ''" />
                    </x-field>
                    <x-field label="حداقل نسخه PHP">
                        <x-input wire:model="versionForm.min_php_version" placeholder="8.1" dir="ltr" :class="$errors->has('versionForm.min_php_version') ? 'input-error' : ''" />
                    </x-field>
                    <x-field label="حداقل نسخه Laravel">
                        <x-input wire:model="versionForm.min_laravel_version" placeholder="10.0" dir="ltr" :class="$errors->has('versionForm.min_laravel_version') ? 'input-error' : ''" />
                    </x-field>
                </div>
            </div>

            {{-- changelogs --}}
            <div class="space-y-4">
                <p class="text-sm font-bold text-zinc-800 dark:text-zinc-100">شرح تغییرات</p>
                <x-field label="تاریخچه تغییرات (Changelog)">
                    <x-textarea wire:model="versionForm.changelog" rows="3" :class="$errors->has('versionForm.changelog') ? 'input-error' : ''" />
                </x-field>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-field label="افزوده‌ها">
                        <x-textarea wire:model="versionForm.what_added" rows="3" :class="$errors->has('versionForm.what_added') ? 'input-error' : ''" />
                    </x-field>
                    <x-field label="تغییرات">
                        <x-textarea wire:model="versionForm.what_changed" rows="3" :class="$errors->has('versionForm.what_changed') ? 'input-error' : ''" />
                    </x-field>
                    <x-field label="رفع اشکال">
                        <x-textarea wire:model="versionForm.what_fixed" rows="3" :class="$errors->has('versionForm.what_fixed') ? 'input-error' : ''" />
                    </x-field>
                </div>
            </div>

            {{-- dependencies --}}
            <x-field label="وابستگی‌ها (JSON)" error="versionForm.dependencies" hint='فرمت JSON نگاشت نامک به نسخه؛ مثال: {"seo-pro": "1.2.0", "kavenegar-sms": "2.0.1"}'>
                <x-textarea wire:model="versionForm.dependencies" rows="3" dir="ltr" class="font-mono {{ $errors->has('versionForm.dependencies') ? 'input-error' : '' }}" />
            </x-field>

            <div class="flex items-center justify-end gap-2 pt-2">
                <x-btn variant="secondary" wire:click="$set('showVersionModal', false)">انصراف</x-btn>
                <x-btn type="submit" icon="save" :loading="true">ذخیره نسخه</x-btn>
            </div>
        </form>
    </x-modal>

    {{-- ==================== version delete confirm ==================== --}}
    <x-modal wire:model="deleteVersionId" title="حذف نسخه" size="sm">
        @if($deleteVersionId)
            @php($targetVersion = \App\Models\PackageVersion::find($deleteVersionId))
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-rose-50 p-4 text-rose-700 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20">
                    <x-icon name="alert-triangle" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">
                        آیا از حذف نسخه «<strong class="font-mono" dir="ltr">v{{ $targetVersion?->version }}</strong>» و فایل ZIP آن مطمئن هستید؟
                    </p>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <x-btn variant="secondary" wire:click="$set('deleteVersionId', null)">انصراف</x-btn>
                    <x-btn variant="danger" icon="trash" wire:click="deleteVersion" :loading="true">حذف قطعی</x-btn>
                </div>
            </div>
        @endif
    </x-modal>

    {{-- ==================== plan modal ==================== --}}
    <x-modal wire:model="showPlanModal" :title="$editingPlanId ? 'ویرایش پلن قیمت' : 'پلن قیمت جدید'" size="md">
        <form wire:submit="savePlan" class="space-y-5">
            <x-field label="نام طرح" required>
                <x-input wire:model="planForm.name" placeholder="مثلاً یک‌ساله" icon="tag" :class="$errors->has('planForm.name') ? 'input-error' : ''" />
            </x-field>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="مدت (ماه)" required hint="۰ = نامحدود">
                    <x-input wire:model="planForm.duration_months" type="number" min="0" max="120" placeholder="12" dir="ltr" :class="$errors->has('planForm.duration_months') ? 'input-error' : ''" />
                </x-field>

                <x-field label="ترتیب نمایش">
                    <x-input wire:model="planForm.sort_order" type="number" min="0" placeholder="0" dir="ltr" :class="$errors->has('planForm.sort_order') ? 'input-error' : ''" />
                </x-field>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="قیمت (تومان)" required>
                    <x-input wire:model="planForm.price" type="number" min="0" placeholder="950000" icon="banknote" dir="ltr" :class="$errors->has('planForm.price') ? 'input-error' : ''" />
                </x-field>

                <x-field label="قیمت با تخفیف (تومان)" hint="اختیاری — باید کمتر از قیمت اصلی باشد.">
                    <x-input wire:model="planForm.discount_price" type="number" min="0" placeholder="807500" dir="ltr" :class="$errors->has('planForm.discount_price') ? 'input-error' : ''" />
                </x-field>
            </div>

            <div class="flex flex-col gap-3 rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200 dark:bg-zinc-800/40 dark:ring-zinc-700">
                <x-toggle wire:model="planForm.is_one_time" label="خرید یک‌بار مصرف (بدون تمدید)" />
                <x-toggle wire:model="planForm.is_active" label="فعال باشد" />
            </div>

            <x-field label="توضیحات">
                <x-textarea wire:model="planForm.description" rows="3" :class="$errors->has('planForm.description') ? 'input-error' : ''" />
            </x-field>

            <div class="flex items-center justify-end gap-2 pt-2">
                <x-btn variant="secondary" wire:click="$set('showPlanModal', false)">انصراف</x-btn>
                <x-btn type="submit" icon="save" :loading="true">ذخیره</x-btn>
            </div>
        </form>
    </x-modal>

    {{-- ==================== plan delete confirm ==================== --}}
    <x-modal wire:model="deletePlanId" title="حذف پلن قیمت" size="sm">
        @if($deletePlanId)
            @php($targetPlan = \App\Models\PackagePricingPlan::find($deletePlanId))
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-rose-50 p-4 text-rose-700 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20">
                    <x-icon name="alert-triangle" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">آیا از حذف طرح «<strong>{{ $targetPlan?->name }}</strong>» مطمئن هستید؟</p>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <x-btn variant="secondary" wire:click="$set('deletePlanId', null)">انصراف</x-btn>
                    <x-btn variant="danger" icon="trash" wire:click="deletePlan" :loading="true">حذف قطعی</x-btn>
                </div>
            </div>
        @endif
    </x-modal>

    {{-- ==================== image delete confirm ==================== --}}
    <x-modal wire:model="deleteImageId" title="حذف تصویر گالری" size="sm">
        @if($deleteImageId)
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-rose-50 p-4 text-rose-700 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20">
                    <x-icon name="alert-triangle" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">آیا از حذف این تصویر از گالری مطمئن هستید؟ فایل آن نیز از سرور حذف می‌شود.</p>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <x-btn variant="secondary" wire:click="$set('deleteImageId', null)">انصراف</x-btn>
                    <x-btn variant="danger" icon="trash" wire:click="deleteImage" :loading="true">حذف قطعی</x-btn>
                </div>
            </div>
        @endif
    </x-modal>
</div>
