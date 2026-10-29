import './livewire-config';
import './ckeditor';
import './loading';
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import './scroll-preserve';

/* ------------------------------------------------------------------ */
/*  Dark mode (persisted, class-based)                                 */
/* ------------------------------------------------------------------ */
window.initDarkMode = () => {
    const stored = localStorage.getItem('theme');
    const dark = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
    document.documentElement.classList.toggle('dark', dark);
    window.dispatchEvent(new CustomEvent('theme-changed', { detail: { dark } }));
    return dark;
};

window.toggleDarkMode = () => {
    const dark = !document.documentElement.classList.contains('dark');
    localStorage.setItem('theme', dark ? 'dark' : 'light');
    document.documentElement.classList.toggle('dark', dark);
    window.dispatchEvent(new CustomEvent('theme-changed', { detail: { dark } }));
    return dark;
};

/* ------------------------------------------------------------------ */
/*  Toast system                                                       */
/* ------------------------------------------------------------------ */
document.addEventListener('alpine:init', () => {
    Alpine.data('tabs', (initial) => ({
        tab: initial,
    }));

    Alpine.data('shell', () => ({
        collapsed: localStorage.getItem('sidebar-collapsed') === '1',
        mobileOpen: false,
    }));

    Alpine.store('toasts', {
        items: [],
        push(message, type = 'success', timeout = 4200) {
            const id = Date.now() + Math.random();
            this.items.push({ id, message, type });
            if (timeout) setTimeout(() => this.dismiss(id), timeout);
        },
        dismiss(id) { this.items = this.items.filter((t) => t.id !== id); },
    });

    // Livewire dispatches browser events on the component root -> they bubble here
    window.addEventListener('toast', (e) => {
        const { message, type } = e.detail ?? {};
        if (message) Alpine.store('toasts').push(message, type ?? 'success');
    });
});

/* wire:navigate progress bar */
window.addEventListener('livewire:navigating', () => {
    let bar = document.querySelector('.livewire-progress');
    if (!bar) {
        bar = document.createElement('div');
        bar.className = 'livewire-progress';
        document.head.appendChild(bar); // head persists through wire:navigate body morphs
    }
    bar.style.width = '30%';
    bar.style.opacity = '1';
});
window.addEventListener('livewire:navigated', () => {
    const bar = document.querySelector('.livewire-progress');
    if (bar) {
        bar.style.width = '100%';
        setTimeout(() => {
            bar.style.opacity = '0';
            setTimeout(() => (bar.style.width = '0%'), 300);
        }, 120);
    }
    // re-apply theme after morph
    window.initDarkMode();
});

Livewire.start();
