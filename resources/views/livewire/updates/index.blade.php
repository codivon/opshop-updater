<div class="space-y-6">
    {{-- header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-black text-zinc-900 dark:text-zinc-50">آپدیت‌ها</h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">مدیریت نسخه‌ها و بسته‌های به‌روزرسانی پروژه‌ها.</p>
        </div>
        <x-btn icon="plus" wire:click="openCreate">آپدیت جدید</x-btn>
    </div>

    {{-- filters --}}
    <div class="card flex flex-col gap-3 p-4 lg:flex-row lg:items-center">
        <div class="relative flex-1">
            <x-icon name="search" class="pointer-events-none absolute start-3.5 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
            <input type="search" wire:model.live.debounce.400ms="search" placeholder="جستجوی عنوان، نسخه یا توضیحات…" class="input ps-10" />
        </div>
        <select wire:model.live="status" class="input lg:w-40">
            <option value="">همه وضعیت‌ها</option>
            <option value="draft">پیش‌نویس</option>
            <option value="active">فعال</option>
            <option value="archived">بایگانی شده</option>
        </select>
        <select wire:model.live="type" class="input lg:w-36">
            <option value="">همه انواع</option>
            <option value="major">اصلی</option>
            <option value="minor">فرعی</option>
            <option value="patch">اصلاحی</option>
        </select>
        <select wire:model.live="project_id" class="input lg:w-44">
            <option value="0">همه پروژه‌ها</option>
            @foreach($this->projects as $project)
                <option value="{{ $project->id }}">{{ $project->name }}</option>
            @endforeach
        </select>
        <div class="relative lg:w-44">
            <x-icon name="arrow-up-down" class="pointer-events-none absolute start-3.5 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
            <select wire:model.live="sort" class="input ps-10" aria-label="ترتیب نمایش">
                <option value="newest">جدیدترین</option>
                <option value="oldest">قدیمی‌ترین</option>
                <option value="id_desc">شناسه (نزولی)</option>
                <option value="id_asc">شناسه (صعودی)</option>
                <option value="release_newest">بر اساس تاریخ انتشار - جدیدترین</option>
                <option value="release_oldest">بر اساس تاریخ انتشار - قدیمی‌ترین</option>
            </select>
        </div>
        <div wire:loading wire:target="search, status, type, project_id, sort" class="flex items-center gap-2 text-xs text-brand-600 dark:text-brand-400">
            <x-icon name="loader" class="size-4 animate-spin" />
            در حال فیلتر…
        </div>
    </div>

    {{-- bulk toolbar --}}
    @if($selectedIds)
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-rose-50 p-3 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:ring-rose-400/20">
            <div class="flex items-center gap-2 text-sm font-bold text-rose-700 dark:text-rose-400">
                <x-icon name="check-square" class="size-4.5" />
                {{ fa_num(count($selectedIds)) }} آپدیت انتخاب شده است
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
                            <th class="w-10">
                                <input type="checkbox" class="checkbox" aria-label="انتخاب همه"
                                       @if($this->allPageSelected()) checked @endif
                                       wire:click="toggleSelectAll" />
                            </th>
                            <th>آپدیت</th>
                            <th class="hidden md:table-cell">پروژه</th>
                            <th class="hidden lg:table-cell">نوع</th>
                            <th>وضعیت</th>
                            <th class="hidden md:table-cell">اجباری</th>
                            <th class="hidden lg:table-cell">حجم فایل</th>
                            <th class="hidden sm:table-cell">تاریخ انتشار</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->records as $record)
                            <tr wire:key="update-{{ $record->id }}">
                                <td>
                                    <input type="checkbox" class="checkbox" aria-label="انتخاب این آپدیت"
                                           wire:model.live="selectedIds" value="{{ $record->id }}" />
                                </td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                                            <x-icon name="git-branch" class="size-5" />
                                        </div>
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-zinc-800 dark:text-zinc-100">{{ $record->title }}</p>
                                            <span class="mt-0.5 inline-flex items-center rounded-md bg-zinc-100 px-1.5 py-0.5 font-mono text-xs text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300" dir="ltr">v{{ $record->version }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="hidden md:table-cell">
                                    @if($record->project)
                                        <div class="flex items-center gap-2">
                                            <span class="size-2 shrink-0 rounded-full {{ match($record->project->status) {
                                                'active' => 'bg-emerald-500',
                                                'pending' => 'bg-amber-500',
                                                default => 'bg-zinc-400',
                                            } }}"></span>
                                            <span class="truncate text-sm text-zinc-600 dark:text-zinc-300">{{ $record->project->name }}</span>
                                        </div>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </td>
                                <td class="hidden lg:table-cell">
                                    @php($typeVariant = match($record->type) {
                                        'major' => 'primary',
                                        'minor' => 'info',
                                        'patch' => 'neutral',
                                        default => 'neutral',
                                    })
                                    @php($typeLabels = ['major' => 'اصلی', 'minor' => 'فرعی', 'patch' => 'اصلاحی'])
                                    <x-badge :variant="$typeVariant">{{ $typeLabels[$record->type] ?? $record->type }}</x-badge>
                                </td>
                                <td>
                                    @php($statusVariant = match($record->status) {
                                        'active' => 'success',
                                        'draft' => 'warning',
                                        'archived' => 'neutral',
                                        default => 'neutral',
                                    })
                                    @php($statusLabels = ['draft' => 'پیش‌نویس', 'active' => 'فعال', 'archived' => 'بایگانی شده'])
                                    <x-badge :variant="$statusVariant">{{ $statusLabels[$record->status] ?? $record->status }}</x-badge>
                                </td>
                                <td class="hidden md:table-cell">
                                    @if($record->is_mandatory)
                                        <x-badge variant="warning" icon="zap">اجباری</x-badge>
                                    @else
                                        <x-badge variant="neutral">اختیاری</x-badge>
                                    @endif
                                </td>
                                <td class="hidden text-sm tabular-nums text-zinc-600 lg:table-cell dark:text-zinc-300" dir="ltr">
                                    @if(filled($record->file_size))
                                        {{ is_numeric($record->file_size) ? bytes_human($record->file_size) : $record->file_size }}
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </td>
                                <td class="hidden text-sm text-zinc-500 sm:table-cell dark:text-zinc-400">
                                    {{ verta_date($record->release_date) ?? '—' }}
                                </td>
                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        <button type="button" wire:click="openEdit({{ $record->id }})"
                                                class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10 dark:hover:text-brand-400"
                                                title="ویرایش">
                                            <x-icon name="pencil" class="size-4.5" />
                                        </button>
                                        <button type="button" wire:click="$set('deleteId', {{ $record->id }})"
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
            <x-empty icon="git-branch" title="آپدیتی یافت نشد" description="با انتشار اولین آپدیت شروع کنید یا فیلترها را تغییر دهید.">
                <x-btn variant="soft" icon="plus" wire:click="openCreate">انتشار آپدیت</x-btn>
            </x-empty>
        @endif
    </div>

    {{-- create / edit modal --}}
    <x-modal wire:model="showModal" :title="$editingId ? 'ویرایش آپدیت' : 'آپدیت جدید'" size="md">
        <form wire:submit="save" class="space-y-5">
            <x-field label="عنوان آپدیت" required error="form.title">
                <x-input wire:model="form.title" placeholder="مثلاً رفع باگ پرداخت" icon="git-branch" :class="$errors->has('form.title') ? 'input-error' : ''" />
            </x-field>

            <x-field label="شماره نسخه" required error="form.version">
                <x-input wire:model="form.version" placeholder="v2.5.1" icon="layers" dir="ltr" :class="$errors->has('form.version') ? 'input-error' : ''" />
            </x-field>

            <x-field label="پروژه" required error="form.project_id">
                <x-select wire:model="form.project_id" placeholder="انتخاب پروژه…" :options="$this->projects->pluck('name', 'id')" :class="$errors->has('form.project_id') ? 'input-error' : ''" />
            </x-field>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <x-field label="نوع آپدیت" required error="form.type">
                    <x-select wire:model="form.type" :options="['major' => 'اصلی', 'minor' => 'فرعی', 'patch' => 'اصلاحی']" :class="$errors->has('form.type') ? 'input-error' : ''" />
                </x-field>

                <x-field label="وضعیت" required error="form.status">
                    <x-select wire:model="form.status" :options="['draft' => 'پیش‌نویس', 'active' => 'فعال', 'archived' => 'بایگانی شده']" :class="$errors->has('form.status') ? 'input-error' : ''" />
                </x-field>
            </div>

            <x-field label="تاریخ انتشار" hint="تاریخ میلادی؛ در فهرست به شمسی نمایش داده می‌شود." error="form.release_date">
                <input type="date" wire:model="form.release_date" dir="ltr" class="input {{ $errors->has('form.release_date') ? 'input-error' : '' }}" />
            </x-field>

            <div class="pt-1">
                <x-toggle wire:model="form.is_mandatory" label="آپدیت اجباری" />
            </div>

            <x-field label="توضیحات" required error="form.description">
                <x-textarea wire:model="form.description" rows="3" placeholder="شرح تغییرات این نسخه…" :class="$errors->has('form.description') ? 'input-error' : ''" />
            </x-field>

            <x-field label="فایل آپدیت" hint="فرمت‌های مجاز: zip، rar، tar، gz — حداکثر حجم ۳۰۰ مگابایت." for="update-file" error="form.file">
                <div class="space-y-2">
                    @if($editingId && filled($form['download_link'] ?? null))
                        <div class="flex items-center justify-between gap-3 rounded-xl bg-zinc-100/70 px-4 py-2.5 ring-1 ring-zinc-900/5 dark:bg-zinc-800/60 dark:ring-white/5">
                            <div class="flex min-w-0 items-center gap-2.5">
                                <x-icon name="file-text" class="size-4.5 shrink-0 text-zinc-400" />
                                <div class="min-w-0">
                                    <p class="text-xs font-medium text-zinc-600 dark:text-zinc-300">فایل فعلی</p>
                                    <p class="truncate font-mono text-xs text-zinc-500 dark:text-zinc-400" dir="ltr">{{ \Illuminate\Support\Str::limit($form['download_link'], 42) }}</p>
                                </div>
                            </div>
                            <x-btn variant="danger-soft" size="sm" icon="trash" wire:click="removeFile">حذف فایل</x-btn>
                        </div>
                    @elseif($editingId && blank($form['download_link'] ?? null) && \App\Models\Update::find($editingId)?->download_link)
                        <p class="rounded-xl bg-rose-50 px-4 py-2.5 text-xs font-medium leading-5 text-rose-600 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20">
                            فایل فعلی با ذخیره تغییرات حذف می‌شود.
                        </p>
                    @endif

                    <label for="update-file" class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-dashed border-zinc-300 bg-zinc-50/60 px-4 py-3.5 text-sm text-zinc-500 transition-colors hover:border-brand-400 hover:bg-brand-50/50 hover:text-brand-600 dark:border-zinc-700 dark:bg-zinc-800/40 dark:text-zinc-400 dark:hover:border-brand-500 dark:hover:bg-brand-500/10 dark:hover:text-brand-400">
                        <span class="flex items-center gap-2">
                            <x-icon name="upload" class="size-4.5 shrink-0" />
                            <span wire:loading.remove wire:target="form.file">{{ filled($form['file'] ?? null) ? 'فایل انتخاب شد' : 'انتخاب فایل بسته آپدیت' }}</span>
                            <span wire:loading wire:target="form.file" class="flex items-center gap-2 text-brand-600 dark:text-brand-400">
                                <x-icon name="loader" class="size-4 animate-spin" />
                                در حال آپلود…
                            </span>
                        </span>
                        @if(filled($form['file'] ?? null))
                            <span class="max-w-[45%] truncate font-mono text-xs text-brand-600 dark:text-brand-400" dir="ltr">{{ $form['file']?->getClientOriginalName() }}</span>
                        @endif
                    </label>
                    <input type="file" id="update-file" wire:model="form.file" class="hidden" />
                    <x-upload-progress target="form.file" label="در حال آپلود فایل آپدیت" />
                </div>
            </x-field>

            <div class="flex items-center justify-end gap-2 pt-2">
                <x-btn variant="secondary" wire:click="$set('showModal', false)">انصراف</x-btn>
                <x-btn type="submit" icon="save" :loading="true">ذخیره</x-btn>
            </div>
        </form>
    </x-modal>

    {{-- delete confirm --}}
    <x-modal wire:model="deleteId" title="حذف آپدیت" size="sm">
        @if($deleteId)
            @php($target = \App\Models\Update::find($deleteId))
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-rose-50 p-4 text-rose-700 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20">
                    <x-icon name="alert-triangle" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">آیا از حذف آپدیت «<strong>{{ $target?->title }}</strong>» نسخه <strong dir="ltr">v{{ $target?->version }}</strong> مطمئن هستید؟ در صورت وجود، فایل پیوست آن نیز از دیسک حذف می‌شود. این عمل قابل بازگشت نیست.</p>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <x-btn variant="secondary" wire:click="$set('deleteId', null)">انصراف</x-btn>
                    <x-btn variant="danger" icon="trash" wire:click="delete" :loading="true">حذف قطعی</x-btn>
                </div>
            </div>
        @endif
    </x-modal>

    {{-- bulk delete confirm --}}
    <x-modal wire:model="confirmingBulkDelete" title="حذف گروهی آپدیت‌ها" size="sm">
        @if($confirmingBulkDelete)
            <div class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl bg-rose-50 p-4 text-rose-700 ring-1 ring-rose-600/10 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20">
                    <x-icon name="trash" class="size-6 shrink-0" />
                    <p class="text-sm leading-6">
                        <strong>{{ fa_num(count($selectedIds)) }}</strong> آپدیت انتخاب‌شده برای همیشه حذف شود؟
                        در صورت وجود، فایل پیوست هر آپدیت نیز از دیسک حذف می‌شود. این عمل قابل بازگشت نیست.
                    </p>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <x-btn variant="secondary" wire:click="clearSelection">انصراف</x-btn>
                    <x-btn variant="danger" icon="trash" wire:click="bulkDelete" :loading="true">حذف {{ fa_num(count($selectedIds)) }} آپدیت</x-btn>
                </div>
            </div>
        @endif
    </x-modal>
</div>
