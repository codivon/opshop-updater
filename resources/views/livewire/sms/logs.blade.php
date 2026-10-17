<div class="space-y-6">
    {{-- header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="flex items-center gap-2 text-lg font-black text-zinc-900 dark:text-zinc-50">
                <x-icon name="send" class="size-5 text-brand-600 dark:text-brand-400" />
                لاگ پیامک‌ها
            </h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">تاریخچه ارسال‌های پیامکی سیستم همراه پاسخ سرویس پیامک.</p>
        </div>
        <x-btn variant="secondary" icon="message-square" href="{{ route('admin.sms.templates') }}">قالب‌های پیامک</x-btn>
    </div>

    {{-- stats --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-stat icon="send" :value="fa_num(\App\Models\SmsLog::where('status', 'sent')->count())" label="ارسال موفق" />
        <x-stat icon="x-circle" :value="fa_num(\App\Models\SmsLog::where('status', 'failed')->count())" label="ناموفق" />
        <x-stat icon="clock" :value="fa_num(\App\Models\SmsLog::where('created_at', '>=', now()->subDay())->count())" label="۲۴ ساعت گذشته" />
    </div>

    {{-- filters --}}
    <div class="card flex flex-col gap-3 p-4 lg:flex-row lg:items-center">
        <div class="relative flex-1">
            <x-icon name="search" class="pointer-events-none absolute start-3.5 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
            <input type="search" wire:model.live.debounce.400ms="search" placeholder="جستجوی شماره، کلید قالب یا متن…" class="input ps-10" />
        </div>
        <select wire:model.live="status" class="input lg:w-44">
            <option value="">همه وضعیت‌ها</option>
            <option value="sent">ارسال شده</option>
            <option value="failed">ناموفق</option>
            <option value="skipped">رد شده</option>
        </select>
    </div>

    {{-- table --}}
    @if($this->records->isEmpty())
        <x-empty icon="send" title="رکوردی یافت نشد" description="هنوز پیامکی ارسال نشده یا فیلترها نتیجه‌ای ندارند." />
    @else
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>شماره</th>
                            <th>قالب</th>
                            <th>درایور</th>
                            <th>متن</th>
                            <th>وضعیت</th>
                            <th>تاریخ</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->records as $log)
                            <tr>
                                <td class="font-mono text-xs tabular-nums" dir="ltr">{{ $log->mobile }}</td>
                                <td>
                                    <code dir="ltr" class="font-mono text-[11px] text-zinc-500 dark:text-zinc-400">{{ $log->template_key ?? '—' }}</code>
                                    @if($log->pattern_code)
                                        <x-badge variant="info" class="mt-1">پترن: <span dir="ltr" class="font-mono">{{ \Illuminate\Support\Str::limit($log->pattern_code, 18) }}</span></x-badge>
                                    @endif
                                </td>
                                <td class="text-xs">{{ \App\Models\SmsTemplate::DRIVERS[$log->driver] ?? $log->driver }}</td>
                                <td class="max-w-72">
                                    <p class="truncate text-xs leading-5 text-zinc-500 dark:text-zinc-400" title="{{ $log->message }}">{{ \Illuminate\Support\Str::limit($log->message, 90) }}</p>
                                </td>
                                <td>
                                    @php($variant = match($log->status) {
                                        'sent' => 'success',
                                        'failed' => 'danger',
                                        default => 'neutral',
                                    })
                                    <x-badge :variant="$variant">{{ $log->statusLabel() }}</x-badge>
                                </td>
                                <td class="text-xs text-zinc-500 dark:text-zinc-400">{{ verta_date($log->created_at, 'Y/m/d H:i') }}</td>
                                <td>
                                    <button type="button" wire:click="show({{ $log->id }})"
                                            class="rounded-lg p-2 text-zinc-400 transition-colors hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10 dark:hover:text-brand-400"
                                            title="مشاهده جزئیات">
                                        <x-icon name="eye" class="size-4.5" />
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-zinc-100 p-3 dark:border-zinc-800">
                {{ $this->records->links() }}
            </div>
        </div>
    @endif

    {{-- detail modal --}}
    <x-modal wire:model="showId" title="جزئیات ارسال" size="lg">
        @if($detail)
            <div class="space-y-4 text-sm">
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-800/60">
                        <p class="text-xs text-zinc-400">شماره</p>
                        <p class="font-mono tabular-nums" dir="ltr">{{ $detail->mobile }}</p>
                    </div>
                    <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-800/60">
                        <p class="text-xs text-zinc-400">قالب / درایور</p>
                        <p dir="ltr" class="text-xs">{{ $detail->template_key }} / {{ $detail->driver }}</p>
                    </div>
                </div>

                <div>
                    <p class="mb-1 text-xs font-bold text-zinc-500 dark:text-zinc-400">متن ارسالی</p>
                    <pre class="whitespace-pre-wrap rounded-xl bg-zinc-50 p-3 text-xs leading-6 text-zinc-700 dark:bg-zinc-800/60 dark:text-zinc-300">{{ $detail->message ?? '—' }}</pre>
                </div>

                @if($detail->error)
                    <div class="rounded-xl bg-rose-50 p-3 text-xs leading-6 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400">
                        <b>خطا:</b> {{ $detail->error }}
                    </div>
                @endif

                <div>
                    <p class="mb-1 text-xs font-bold text-zinc-500 dark:text-zinc-400">پاسخ سرویس</p>
                    <pre class="max-h-48 overflow-y-auto whitespace-pre-wrap rounded-xl bg-zinc-900 p-3 text-[11px] leading-5 text-zinc-300" dir="ltr">{{ $detail->response ?? '—' }}</pre>
                </div>

                <p class="text-xs text-zinc-400">{{ verta_date($detail->created_at, 'Y/m/d H:i') }}</p>
            </div>
        @endif
        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-btn variant="secondary" wire:click="$set('showId', null)">بستن</x-btn>
            </div>
        </x-slot:footer>
    </x-modal>
</div>
