import { Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';

/* ================================================================== */
/*  Global loading feedback                                            */
/*  1) Top progress bar on EVERY Livewire commit (not just navigate)   */
/*  2) Auto spinner + disabled state on any clicked action button      */
/*     (buttons already wired via x-btn :loading are skipped)          */
/* ================================================================== */

const SPINNER_SVG =
    '<svg xmlns="http://www.w3.org/2000/svg" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2v4"/><path d="m16.2 7.8 2.9-2.9"/><path d="M18 12h4"/><path d="m16.2 16.2 2.9 2.9"/><path d="M12 18v4"/><path d="m4.9 19.1 2.9-2.9"/><path d="M2 12h4"/><path d="m4.9 4.9 2.9 2.9"/></svg>';

function ensureBar() {
    let bar = document.querySelector('.livewire-progress');
    if (!bar) {
        bar = document.createElement('div');
        bar.className = 'livewire-progress';
        document.head.appendChild(bar); // head persists through wire:navigate body morphs
    }
    return bar;
}

let pending = 0;

function barStart() {
    const bar = ensureBar();
    bar.style.opacity = '1';
    bar.style.width = '30%';
}

function barDone() {
    const bar = document.querySelector('.livewire-progress');
    if (!bar) return;
    bar.style.width = '100%';
    setTimeout(() => {
        bar.style.opacity = '0';
        setTimeout(() => {
            bar.style.width = '0%';
        }, 300);
    }, 150);
}

/* ---- 1) progress bar for every Livewire round-trip ---- */
Livewire.hook('commit', ({ succeed, fail }) => {
    pending++;
    if (pending === 1) {
        barStart();
        window.dispatchEvent(new CustomEvent('livewire:busy'));
    }

    const settle = () => {
        pending = Math.max(0, pending - 1);
        if (pending === 0) {
            barDone();
            window.dispatchEvent(new CustomEvent('livewire:idle'));
        }
    };

    succeed(() => settle());
    fail(() => settle());
    setTimeout(() => settle(), 20000); // safety: never leave the bar stuck
});

/* ---- 2) auto loading on clicked action buttons ---- */
document.addEventListener(
    'click',
    (e) => {
        const btn = e.target.closest('button');
        if (!btn || btn.disabled || btn.dataset.autoLoading === '1') return;

        /* skip buttons already managed by x-btn's :loading prop */
        if (btn.hasAttribute('wire:loading.attr')) return;

        /* only buttons that trigger a Livewire server action */
        const isSubmitInLivewireForm =
            (btn.getAttribute('type') || 'button') === 'submit' && !!btn.closest('form[wire\\:submit]');
        if (!btn.hasAttribute('wire:click') && !btn.hasAttribute('wire:submit') && !isSubmitInLivewireForm) return;

        /* FIX (ذخیره‌ی مودال‌ها هیچ اتفاقی نمی‌افتاد):Decorator باید در tick بعدی اجرا شود،
           نه هم‌زمان با dispatch رویداد click. غیرفعال‌کردن (disabled) هم‌زمانِ
           dispatch، رفتار پیش‌فرض کلیک (فعال‌سازی دکمه submit = ارسال فرم) را
           کنسل می‌کند؛ چون مرورگر وضعیت disabled را هنگام اجرای activation
           behavior بررسی می‌کند. با setTimeout(0) رویداد submit اول کامل
           dispatch می‌شود (Livewire هم آن را intercept می‌کند) و بعد دکمه
           برای جلوگیری از دوبار‌کلیک غیرفعال می‌شود. */
        setTimeout(() => {
            /* if a morph already replaced the button, skip */
            if (!btn.isConnected || btn.disabled || btn.dataset.autoLoading === '1') return;

            btn.dataset.autoLoading = '1';
            btn.setAttribute('disabled', '');
            btn.classList.add('cursor-wait', 'opacity-60');
            const spin = document.createElement('span');
            spin.className = 'pointer-events-none inline-flex size-4 shrink-0 items-center justify-center';
            spin.innerHTML = SPINNER_SVG;
            btn.prepend(spin);

            let released = false;
            let sawBusy = false;
            const release = () => {
                if (released) return;
                released = true;
                window.removeEventListener('livewire:busy', onBusy);
                window.removeEventListener('livewire:idle', onIdle);
                window.removeEventListener('livewire:navigated', onNav);
                if (!btn.isConnected) return;
                delete btn.dataset.autoLoading;
                btn.removeAttribute('disabled');
                btn.classList.remove('cursor-wait', 'opacity-60');
                spin.remove();
            };
            const onBusy = () => {
                sawBusy = true;
            };
            const onIdle = () => {
                if (sawBusy) release();
            };
            const onNav = () => release();

            window.addEventListener('livewire:busy', onBusy);
            window.addEventListener('livewire:idle', onIdle);
            window.addEventListener('livewire:navigated', onNav, { once: true });

            /* safety: no commit within 600ms → client-only action, restore */
            setTimeout(() => {
                if (!sawBusy) release();
            }, 600);
            /* hard cap */
            setTimeout(release, 10000);
        }, 0);
    },
    true,
);
