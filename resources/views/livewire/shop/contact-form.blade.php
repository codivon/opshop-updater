<div class="card p-5 sm:p-6" id="contact-form">

    <div class="flex items-start gap-3">
        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
            <x-icon name="send" class="size-5" />
        </span>
        <div>
            <h3 class="text-base font-black text-zinc-900 dark:text-zinc-50">ثبت درخواست</h3>
            <p class="mt-1 text-xs leading-6 text-zinc-500 dark:text-zinc-400">
                فرم زیر را پر کنید؛ پس از بررسی، برای هماهنگی جزئیات با شما تماس می‌گیریم.
            </p>
        </div>
    </div>

    <form wire:submit="submit" class="mt-5 space-y-4">

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-field label="نام و نام خانوادگی" for="ct-name" required>
                <x-input id="ct-name" wire:model="name" placeholder="نام شما" autocomplete="name" />
            </x-field>

            <x-field label="شماره موبایل" for="ct-phone" required hint="برای هماهنگی تماس می‌گیریم">
                <x-input id="ct-phone" wire:model="phone" inputmode="tel" placeholder="۰۹۱۲۳۴۵۶۷۸۹" dir="ltr" class="text-start" autocomplete="tel" />
            </x-field>

            <x-field label="ایمیل (اختیاری)" for="ct-email">
                <x-input id="ct-email" type="email" wire:model="email" placeholder="you@example.com" dir="ltr" class="text-start" autocomplete="email" />
            </x-field>

            <x-field label="نوع درخواست" for="ct-type" required>
                <x-select id="ct-type" wire:model="requestType" :options="$this->types" />
            </x-field>
        </div>

        <x-field label="توضیح درخواست" for="ct-message" required
                 hint="هرچه دقیق‌تر بنویسید، پیشنهاد دقیق‌تری می‌گیرید؛ مثلاً هدف سایت، امکانات موردنیاز و بازه زمانی.">
            <x-textarea id="ct-message" wire:model="message" rows="5" placeholder="ایده یا پروژه‌ای که در ذهن دارید را توضیح دهید…" />
        </x-field>

        @if(\App\Support\Features::captcha('contact'))
            <x-captcha wireTarget="captcha" name="captcha" refreshWire="refreshCaptcha" />
        @endif

        {{-- هانی‌پات ضدربات — برای انسان نامرئی است --}}
        @if(\App\Support\Features::honeypot())
            <x-honeypot name="website" wireTarget="website" />
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="flex items-center gap-1.5 text-[11px] leading-5 text-zinc-400">
                <x-icon name="lock" class="size-3.5 shrink-0" />
                اطلاعات شما محرمانه می‌ماند و فقط برای پاسخ‌گویی استفاده می‌شود.
            </p>

            <x-btn type="submit" icon="send" :loading="true">
                ارسال درخواست
            </x-btn>
        </div>
    </form>
</div>
