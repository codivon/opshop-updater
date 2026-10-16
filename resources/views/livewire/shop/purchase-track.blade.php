<div class="rounded-2xl bg-white p-5 ring-1 ring-zinc-200/80 dark:bg-zinc-900 dark:ring-zinc-700/60 sm:p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
        <div class="flex min-w-0 flex-1 items-start gap-4">
            <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-100 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                <x-icon name="search" class="size-5" />
            </div>
            <div class="min-w-0">
                <h3 class="text-sm font-black text-zinc-900 dark:text-zinc-50">خرید قبلی دارید؟</h3>
                <p class="mt-0.5 text-xs leading-5 text-zinc-500 dark:text-zinc-400">
                    شماره تراکنش خرید خود را وارد کنید تا دوباره به صفحه دانلود بروید.
                </p>
            </div>
        </div>
        <form wire:submit="track" class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
            <div class="sm:w-64">
                <x-input wire:model="transactionId" icon="receipt" placeholder="شماره تراکنش…" dir="ltr" class="text-start font-mono" />
            </div>
            <x-btn type="submit" icon="search" :loading="true" class="shrink-0" size="md">پیگیری خرید</x-btn>
        </form>
    </div>
</div>
