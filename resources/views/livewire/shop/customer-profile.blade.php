<div class="mx-auto w-full max-w-7xl space-y-6 px-4 pt-8 sm:px-6">

    {{-- ============ Header ============ --}}
    @php($customer = auth('customer')->user())
    <header class="card flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:p-6">
        <x-avatar :name="$customer?->name ?? 'مشتری'" size="lg" />

        <div class="min-w-0 flex-1">
            <h1 class="text-lg font-black text-zinc-900 dark:text-zinc-50">{{ $customer?->name }}</h1>
            <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                <span class="inline-flex items-center gap-1">
                    <x-icon name="key" class="size-3.5" />
                    کد آپدیت: <span class="font-mono font-bold text-zinc-700 dark:text-zinc-200" dir="ltr">{{ $customer?->update_code }}</span>
                </span>
                @if($this->mySubscription?->plan)
                    <span class="inline-flex items-center gap-1 text-amber-600 dark:text-amber-400">
                        <x-icon name="crown" class="size-3.5" />
                        طرح «{{ $this->mySubscription->plan->name }}» فعال
                    </span>
                @endif
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('shop.home') }}" wire:navigate>
                <x-btn variant="secondary" size="sm" icon="store">فروشگاه</x-btn>
            </a>
            <x-btn variant="ghost" size="sm" icon="log-out" wire:click="logout">خروج</x-btn>
        </div>
    </header>

    {{-- ============ Stats ============ --}}
    <section class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="card flex items-center gap-4 p-5">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-100 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                <x-icon name="package" class="size-5.5" />
            </span>
            <div>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">پکیج خریده‌شده</p>
                <p class="text-xl font-black tabular-nums text-zinc-900 dark:text-zinc-50">{{ fa_num($this->stats['purchases']) }}</p>
            </div>
        </div>
        <div class="card flex items-center gap-4 p-5">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400">
                <x-icon name="shield-check" class="size-5.5" />
            </span>
            <div>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">لایسنس فعال</p>
                <p class="text-xl font-black tabular-nums text-zinc-900 dark:text-zinc-50">{{ fa_num($this->stats['licenses']) }}</p>
            </div>
        </div>
        <div class="card flex items-center gap-4 p-5">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400">
                <x-icon name="wallet" class="size-5.5" />
            </span>
            <div>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">مجموع خرید</p>
                <p class="text-xl font-black tabular-nums text-zinc-900 dark:text-zinc-50">{{ money($this->stats['spent']) }} تومان</p>
            </div>
        </div>
    </section>

    {{-- ============ Tabs ============ --}}
    <div class="flex items-center justify-center px-3">
        {{-- max-w-full + اسکرول افقی: با ۴ تب، در موبایل سرریز نمی‌کند (نوار پنهان) --}}
        <div class="inline-flex max-w-full overflow-x-auto rounded-2xl bg-zinc-200/70 p-1 [scrollbar-width:none] dark:bg-zinc-800/70 [&::-webkit-scrollbar]:hidden" role="tablist">
            <button type="button" wire:click="switchTab('purchases')" role="tab"
                    @class(['flex shrink-0 cursor-pointer items-center gap-2 whitespace-nowrap rounded-xl px-4 py-2 text-sm font-black transition-all',
                        $tab === 'purchases' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-900 dark:text-zinc-50' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400'])
                    aria-current="{{ $tab === 'purchases' ? 'true' : 'false' }}">
                <x-icon name="package" class="size-4" /> پکیج‌های من
                @if($this->purchases->total())
                    <span class="rounded-full bg-brand-100 px-2 py-0.5 text-[11px] tabular-nums text-brand-700 dark:bg-brand-500/15 dark:text-brand-400">{{ fa_num($this->purchases->total()) }}</span>
                @endif
            </button>
            <button type="button" wire:click="switchTab('licenses')" role="tab"
                    @class(['flex shrink-0 cursor-pointer items-center gap-2 whitespace-nowrap rounded-xl px-4 py-2 text-sm font-black transition-all',
                        $tab === 'licenses' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-900 dark:text-zinc-50' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400'])
                    aria-current="{{ $tab === 'licenses' ? 'true' : 'false' }}">
                <x-icon name="shield-check" class="size-4" /> لایسنس‌ها
                @if($this->stats['licenses_total'])
                    <span class="rounded-full bg-brand-100 px-2 py-0.5 text-[11px] tabular-nums text-brand-700 dark:bg-brand-500/15 dark:text-brand-400">{{ fa_num($this->stats['licenses_total']) }}</span>
                @endif
            </button>
            <button type="button" wire:click="switchTab('tickets')" role="tab"
                    @class(['flex shrink-0 cursor-pointer items-center gap-2 whitespace-nowrap rounded-xl px-4 py-2 text-sm font-black transition-all',
                        $tab === 'tickets' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-900 dark:text-zinc-50' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400'])
                    aria-current="{{ $tab === 'tickets' ? 'true' : 'false' }}">
                <x-icon name="message-circle" class="size-4" /> تیکت‌های پشتیبانی
                @if($this->ticketsCount)
                    <span class="rounded-full bg-brand-100 px-2 py-0.5 text-[11px] tabular-nums text-brand-700 dark:bg-brand-500/15 dark:text-brand-400">{{ fa_num($this->ticketsCount) }}</span>
                @endif
            </button>
            <button type="button" wire:click="switchTab('info')" role="tab"
                    @class(['flex shrink-0 cursor-pointer items-center gap-2 whitespace-nowrap rounded-xl px-4 py-2 text-sm font-black transition-all',
                        $tab === 'info' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-900 dark:text-zinc-50' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400'])
                    aria-current="{{ $tab === 'info' ? 'true' : 'false' }}">
                <x-icon name="user-pen" class="size-4" /> مشخصات
            </button>
        </div>
    </div>

    {{-- ==================== TAB: پکیج‌های من ==================== --}}
    @if($tab === 'purchases')
        @if($this->purchases->count())
            <div class="space-y-4">
                @foreach($this->purchases as $purchase)
                    @php($license = \App\Models\PackageLicense::query()
                        ->where('customer_id', $customer->id)
                        ->where('package_id', $purchase->package_id)
                        ->where('status', \App\Models\PackageLicense::STATUS_ACTIVE)
                        ->latest('id')->first())
                    <div wire:key="purchase-{{ $purchase->id }}" class="card flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:p-5">
                        {{-- thumbnail --}}
                        <div class="h-24 w-full shrink-0 overflow-hidden rounded-xl bg-zinc-100 sm:h-20 sm:w-32 dark:bg-zinc-800">
                            <img src="{{ $purchase->package->thumbnail_url }}" alt="{{ $purchase->package->name }}"
                                 class="size-full object-cover" onerror="this.style.display='none';this.parentElement.classList.add('shop-thumb')">
                        </div>

                        {{-- info --}}
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('shop.package', $purchase->package->slug) }}" wire:navigate
                                   class="text-sm font-black text-zinc-900 transition-colors hover:text-brand-600 dark:text-zinc-50">
                                    {{ $purchase->package->name }}
                                </a>
                                @if($license)
                                    <x-badge variant="success" icon="shield-check">لایسنس فعال</x-badge>
                                @else
                                    <x-badge variant="neutral" icon="receipt">خرید موفق</x-badge>
                                @endif
                            </div>
                            <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                                @if($purchase->version)
                                    <span class="font-mono" dir="ltr">v{{ $purchase->version->version }}</span>
                                @endif
                                @if($purchase->pricingPlan)
                                    <span class="inline-flex items-center gap-1"><x-icon name="layers" class="size-3.5" /> {{ $purchase->pricingPlan->name }}</span>
                                @endif
                                <span class="inline-flex items-center gap-1"><x-icon name="calendar" class="size-3.5" /> {{ verta_date($purchase->paid_at) }}</span>
                                @if($purchase->amount > 0)
                                    <span class="inline-flex items-center gap-1 tabular-nums">{{ money($purchase->amount) }} تومان</span>
                                @else
                                    <span class="text-emerald-600 dark:text-emerald-400">رایگان</span>
                                @endif
                            </div>
                            @if($license?->expires_at)
                                <p class="mt-1 inline-flex items-center gap-1 text-xs text-zinc-400">
                                    <x-icon name="calendar-clock" class="size-3.5" />
                                    اعتبار تا {{ verta_date($license->expires_at) }}
                                </p>
                            @endif
                        </div>

                        {{-- actions --}}
                        <div class="flex shrink-0 items-center gap-2">
                            <a href="{{ route('shop.package', $purchase->package->slug) }}" wire:navigate>
                                <x-btn variant="secondary" size="sm" icon="external-link">صفحه پکیج</x-btn>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- راهنمای نصب: مشتری پکیج را مستقیم دانلود نمی‌کند؛ با کد آپدیت در فروشگاه خودش نصب می‌کند --}}
            <div class="flex items-start gap-3 rounded-2xl bg-brand-50 p-4 ring-1 ring-brand-100 dark:bg-brand-500/10 dark:ring-brand-400/20">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-white text-brand-600 shadow-xs dark:bg-zinc-900 dark:text-brand-400">
                    <x-icon name="info" class="size-4.5" />
                </span>
                <p class="text-xs leading-6 text-brand-800/90 dark:text-brand-200/80">
                    دانلود مستقیم فایل پکیج‌ها ممکن نیست؛ برای نصب، در افزونه/فروشگاه ساز خود
                    <span class="font-black">کد آپدیت</span>
                    (<span class="font-mono font-bold" dir="ltr">{{ $customer->update_code }}</span>) را وارد کنید و
                    پکیج خریداری‌شده به‌صورت خودکار نصب و به‌روزرسانی می‌شود.
                </p>
            </div>

            <div>{{ $this->purchases->links() }}</div>
        @else
            <div class="card">
                <x-empty icon="package" title="هنوز پکیجی نخریده‌اید"
                         description="از فروشگاه پکیج موردنظرتان را تهیه کنید تا با کد آپدیت، در فروشگاه ساز شما قابل نصب باشد.">
                    <a href="{{ route('shop.home') }}" wire:navigate>
                        <x-btn variant="soft" size="sm" icon="store">رفتن به فروشگاه</x-btn>
                    </a>
                </x-empty>
            </div>
        @endif

    {{-- ==================== TAB: لایسنس‌ها ==================== --}}
    @elseif($tab === 'licenses')

        {{-- فیلترها: جستجو + وضعیت --}}
        <div class="card flex flex-col gap-3 p-4 lg:flex-row lg:items-center">
            <div class="relative flex-1">
                <x-icon name="search" class="pointer-events-none absolute start-3.5 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
                <input type="search" wire:model.live.debounce.400ms="licenseSearch"
                       placeholder="جستجوی نام پکیج یا کلید لایسنس…" class="input ps-10" aria-label="جستجوی لایسنس" />
            </div>
            <select wire:model.live="licenseStatus" class="input lg:w-44" aria-label="فیلتر وضعیت لایسنس">
                <option value="">همه وضعیت‌ها</option>
                <option value="active">فعال</option>
                <option value="expired">منقضی</option>
                <option value="revoked">لغو شده</option>
            </select>
        </div>

        @if($this->licenses->count())
            <div class="card overflow-hidden p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 bg-zinc-50 text-xs text-zinc-500 dark:border-zinc-700/60 dark:bg-zinc-800/60 dark:text-zinc-400">
                                <th class="px-4 py-3 text-start font-bold">پکیج</th>
                                <th class="px-4 py-3 text-start font-bold">کلید لایسنس</th>
                                <th class="px-4 py-3 text-start font-bold">وضعیت</th>
                                <th class="px-4 py-3 text-start font-bold">اعتبار</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->licenses as $license)
                                <tr wire:key="lic-{{ $license->id }}" class="border-b border-zinc-100 last:border-0 dark:border-zinc-700/40">
                                    <td class="px-4 py-3 font-bold text-zinc-800 dark:text-zinc-100">{{ $license->package?->name }}</td>
                                    <td class="px-4 py-3 font-mono text-xs text-zinc-600 dark:text-zinc-300" dir="ltr">{{ $license->license_key }}</td>
                                    <td class="px-4 py-3">
                                        @if($license->status === \App\Models\PackageLicense::STATUS_ACTIVE)
                                            <x-badge variant="success" icon="check-circle">فعال</x-badge>
                                        @elseif($license->status === \App\Models\PackageLicense::STATUS_EXPIRED)
                                            <x-badge variant="warning" icon="clock">منقضی</x-badge>
                                        @else
                                            <x-badge variant="danger" icon="x-circle">لغو شده</x-badge>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 tabular-nums text-zinc-500">
                                        {{ $license->expires_at ? verta_date($license->expires_at) : 'نامحدود' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div>{{ $this->licenses->links() }}</div>
        @else
            <div class="card">
                <x-empty icon="shield-check"
                         title="لایسنسی یافت نشد"
                         description="{{ ($licenseSearch !== '' || $licenseStatus !== '') ? 'با این فیلترها لایسنسی یافت نشد؛ فیلترها را تغییر دهید.' : 'پس از خرید پکیج، لایسنس آن در این بخش نمایش داده می‌شود.' }}" />
            </div>
        @endif

    {{-- ==================== TAB: تیکت‌های پشتیبانی ==================== --}}
    @elseif($tab === 'tickets')
        @php($gate = $this->canUseTickets)
        @if(!$gate)
            {{-- گات: بدون اشتراک فعال — پیام واضح به‌جای فرم --}}
            <div class="card mx-auto max-w-xl p-6 text-center">
                <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400">
                    <x-icon name="lock" class="size-7" />
                </span>
                <h3 class="mt-4 text-base font-black text-zinc-900 dark:text-zinc-50">پشتیبانی مخصوص مشتریانِ دارای اشتراک فعال است</h3>
                <p class="mx-auto mt-2 max-w-sm text-sm leading-7 text-zinc-500 dark:text-zinc-400">
                    برای ثبت تیکت و گفتگو با تیم پشتیبانی باید اشتراک فعال داشته باشید.
                    با خرید هر طرح اشتراک، پشتیبانی فوری و ثبت تیکت برای شما باز می‌شود.
                </p>
                <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
                    <a href="{{ route('shop.subscriptions') }}" wire:navigate>
                        <x-btn icon="crown">مشاهده طرح‌های اشتراک</x-btn>
                    </a>
                    @if($this->mySubscription)
                        <a href="{{ route('shop.package', $this->mySubscription->plan?->slug ?? '') }}" wire:navigate>
                            <x-btn variant="secondary" icon="external-link">طرح من</x-btn>
                        </a>
                    @endif
                </div>
            </div>
        @else
            <div class="space-y-4">
                {{-- سربرگ: دکمهٔ تیکت جدید --}}
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                        <span class="inline-flex size-2 rounded-full bg-emerald-500"></span>
                        اشتراک شما فعال است — می‌توانید تیکت ثبت کنید.
                    </p>
                    @if(!$showTicketForm)
                        <x-btn icon="plus" wire:click="openTicketForm" :loading="true">تیکت جدید</x-btn>
                    @endif
                </div>

                {{-- فرم تیکت جدید --}}
                @if($showTicketForm)
                    <form wire:submit="createTicket" class="card space-y-4 p-5">
                        <div class="flex items-center gap-2 border-b border-zinc-100 pb-3 dark:border-zinc-800">
                            <x-icon name="message-circle" class="size-5 text-brand-600" />
                            <h3 class="text-sm font-black text-zinc-900 dark:text-zinc-50">ثبت تیکت جدید</h3>
                        </div>

                        <x-field label="موضوع" for="ticket-subject" required>
                            <x-input id="ticket-subject" wire:model="ticketSubject" placeholder="مثلاً: خطا در نصب پکیج روی سایت" class="{{ $errors->has('ticketSubject') ? 'input-error' : '' }}" />
                            @error('ticketSubject') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                        </x-field>

                        <x-field label="توضیحات" for="ticket-body" required hint="مشکل را کامل توضیح دهید؛ اگر خطا دارید متن خطا را هم بنویسید.">
                            <textarea id="ticket-body" wire:model="ticketBody" rows="5" placeholder="توضیح کامل مشکل…"
                                      class="input {{ $errors->has('ticketBody') ? 'input-error' : '' }}"></textarea>
                            @error('ticketBody') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                        </x-field>

                        <div class="flex items-center justify-start gap-2 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                            <x-btn type="submit" icon="send" :loading="true">ارسال تیکت</x-btn>
                            <x-btn type="button" variant="secondary" wire:click="hideTicketForm">انصراف</x-btn>
                        </div>
                    </form>
                @endif

                {{-- لیست تیکت‌ها --}}
                @forelse($this->myTickets as $ticket)
                    <div wire:key="ticket-{{ $ticket->id }}" class="card overflow-hidden p-0">
                        {{-- ردیف تیکت --}}
                        <button type="button" wire:click="toggleConversation({{ $ticket->id }})"
                                class="flex w-full cursor-pointer flex-wrap items-center gap-3 p-4 text-start transition-colors hover:bg-zinc-50/80 sm:p-5 dark:hover:bg-zinc-800/40">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-xl {{ $ticket->isClosed() ? 'bg-zinc-100 text-zinc-400 dark:bg-zinc-800' : 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400' }}">
                                <x-icon name="{{ $ticket->isClosed() ? 'check-circle' : 'message-circle' }}" class="size-4.5" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="truncate text-sm font-black text-zinc-900 dark:text-zinc-50">{{ $ticket->subject }}</span>
                                    @if($ticket->isOpen())
                                        <x-badge variant="warning" icon="clock">در انتظار پاسخ</x-badge>
                                    @elseif($ticket->isAnswered())
                                        <x-badge variant="success" icon="corner-up-left">پاسخ داده شد</x-badge>
                                    @else
                                        <x-badge variant="neutral" icon="archive">بسته‌شده</x-badge>
                                    @endif
                                </span>
                                <span class="mt-1 block text-xs text-zinc-400">
                                    {{ verta_date($ticket->created_at) }} · {{ fa_num($ticket->replies->count()) }} پیام
                                    @if($openTicketId === $ticket->id) · برای بستن کلیک کنید @else · برای گفتگو کلیک کنید @endif
                                </span>
                            </span>
                            <x-icon name="chevron-down" class="size-4 shrink-0 text-zinc-400 {{ $openTicketId === $ticket->id ? 'rotate-180 transition-transform' : '' }}" />
                        </button>

                        {{-- گفتگو --}}
                        @if($openTicketId === $ticket->id)
                            <div class="space-y-3 border-t border-zinc-100 bg-zinc-50/60 p-4 sm:p-5 dark:border-zinc-800 dark:bg-zinc-800/30">
                                @foreach($ticket->replies as $reply)
                                    <div wire:key="reply-{{ $reply->id }}"
                                         class="flex flex-col gap-1.5 rounded-2xl p-4 ring-1 {{ $reply->isFromAdmin()
                                             ? 'bg-brand-50/80 ring-brand-100 dark:bg-brand-500/10 dark:ring-brand-400/20'
                                             : 'bg-white ring-zinc-200/70 dark:bg-zinc-900 dark:ring-zinc-700/60' }}">
                                        <p class="flex items-center gap-2 text-xs font-black {{ $reply->isFromAdmin() ? 'text-brand-700 dark:text-brand-400' : 'text-zinc-600 dark:text-zinc-300' }}">
                                            <x-icon name="{{ $reply->isFromAdmin() ? 'badge-check' : 'user' }}" class="size-3.5" />
                                            {{ $reply->authorLabel() }}
                                            <span class="font-medium text-zinc-400">{{ verta_date($reply->created_at) }}</span>
                                        </p>
                                        <p class="whitespace-pre-line text-sm leading-7 text-zinc-700 dark:text-zinc-200">{{ $reply->body }}</p>
                                    </div>
                                @endforeach

                                {{-- فرم پاسخ مشتری (تیکت باز) --}}
                                @if(!$ticket->isClosed())
                                    <form wire:submit="replyToTicket({{ $ticket->id }})" class="flex items-end gap-2 pt-1">
                                        <div class="flex-1">
                                            <textarea wire:model="replyBody" rows="2" placeholder="پاسخ خود را بنویسید…"
                                                      class="input {{ $errors->has('replyBody') ? 'input-error' : '' }}"></textarea>
                                            @error('replyBody') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                                        </div>
                                        <x-btn type="submit" icon="send" :loading="true">ارسال</x-btn>
                                    </form>
                                    <div class="flex items-center justify-end">
                                        <x-btn variant="secondary" size="sm" icon="archive" wire:click="closeTicket({{ $ticket->id }})">مشکل حل شد — بستن تیکت</x-btn>
                                    </div>
                                @else
                                    <p class="rounded-xl bg-zinc-100 p-3 text-center text-xs text-zinc-500 dark:bg-zinc-800/60 dark:text-zinc-400">
                                        این تیکت بسته شده است؛ برای موضوع جدید یک تیکت تازه ثبت کنید.
                                    </p>
                                @endif
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="card">
                        <x-empty icon="message-circle"
                                 title="تیکتی یافت نشد"
                                 description="هنوز تیکتی ثبت نکرده‌اید. با «تیکت جدید» سؤال یا مشکل خود را مطرح کنید." />
                    </div>
                @endforelse

                <div>{{ $this->myTickets->links() }}</div>
            </div>
        @endif

    {{-- ==================== TAB: مشخصات ==================== --}}
    @else
        <div class="card mx-auto w-full max-w-xl p-5 sm:p-6">
            <form wire:submit="saveInfo" class="space-y-4">
                <x-field label="نام و نام خانوادگی" for="pf-name" required>
                    <x-input id="pf-name" wire:model="form.name" placeholder="نام شما" />
                </x-field>

                <x-field label="ایمیل" for="pf-email">
                    <x-input id="pf-email" type="email" wire:model="form.email" placeholder="you@example.com" dir="ltr" class="text-start" />
                </x-field>

                <x-field label="شماره موبایل" for="pf-phone">
                    <x-input id="pf-phone" wire:model="form.phone" placeholder="۰۹۱۲…" dir="ltr" class="text-start" />
                </x-field>

                <x-field label="آدرس سایت" for="pf-site" hint="سایتی که پکیج‌ها را روی آن نصب کرده‌اید (اختیاری)">
                    <x-input id="pf-site" wire:model="form.website_url" placeholder="https://example.com" dir="ltr" class="text-start" />
                </x-field>

                <div class="flex items-center justify-end border-t border-zinc-100 pt-4 dark:border-zinc-700/50">
                    <x-btn type="submit" icon="save" :loading="true">ذخیره مشخصات</x-btn>
                </div>
            </form>
        </div>
    @endif

</div>
