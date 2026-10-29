/**
 * حفظ موقعیت اسکرول کانتینرهای اسکرول‌شونده در طول morph لایووایر.
 *
 * مشکل (گزارش کاربر — Task 67): در مودال افزودن/ویرایش طرح اشتراک، هر تعامل
 * (باز/بسته کردن «قابلیت‌ها و پکیج‌های این مدت»، افزودن/حذف ردیف قابلیت یا پکیج)
 * باعث پرش اسکرول مودال به بالا می‌شد و کاربر باید دوباره به پایین اسکرول می‌کرد.
 *
 * علت: هنگام morph (بازسازی/جابه‌جایی نودها)، مرورگر موقعیت اسکرول کانتینر
 * اسکرول‌شونده را به‌هم می‌ریزد — حتی اگر نود درجا patch شود.
 *
 * راه‌حل: هر کانتینری با attribute به نام «data-scroll-preserve» علامت‌گذاری می‌شود؛
 * درست قبل از اعمال morph (لحظهٔ دریافت پاسخ — یعنی آخرین اسکرول کاربر هم در آن
 * لحظه ثبت شده) موقعیت اسکرول ذخیره و بعد از morph (همان لحظه + یک و دو فریم بعد
 * برای تنظیم‌های پس از layout مرورگر) بازگردانی می‌شود.
 */
const tracked = [];

/** جمع‌کردن موقعیت اسکرول همهٔ کانتینرهای علامت‌گذاری‌شده داخل ریشهٔ کامپوننت */
function capture(root) {
    tracked.length = 0;

    if (!root || typeof root.querySelectorAll !== 'function') return;

    const els = root.matches?.('[data-scroll-preserve]')
        ? [root, ...root.querySelectorAll('[data-scroll-preserve]')]
        : [...root.querySelectorAll('[data-scroll-preserve]')];

    const counts = {};
    els.forEach((el) => {
        const name = el.getAttribute('data-scroll-preserve');
        const idx = (counts[name] = (counts[name] ?? -1) + 1); // ترتیب DOM بین هم‌نام‌ها
        tracked.push({ name, idx, top: el.scrollTop, el, replaced: null });
    });
}

/** بازگردانی موقعیت اسکرول بعد از morph — حتی اگر نود جایگزین شده باشد */
function reapply(root) {
    if (!root || typeof root.querySelectorAll !== 'function' || tracked.length === 0) return;

    const els = root.matches?.('[data-scroll-preserve]')
        ? [root, ...root.querySelectorAll('[data-scroll-preserve]')]
        : [...root.querySelectorAll('[data-scroll-preserve]')];

    // نگاشت نام+ترتیب → نود تازه (بعد از جایگزینی احتمالی توسط morph)
    const counts = {};
    els.forEach((el) => {
        const name = el.getAttribute('data-scroll-preserve');
        const idx = (counts[name] = (counts[name] ?? -1) + 1);
        const t = tracked.find((x) => x.name === name && x.idx === idx && x.replaced === null);
        if (t) t.replaced = el;
    });

    tracked.forEach((t) => {
        const target = t.replaced ?? (t.el.isConnected ? t.el : null);
        if (target && target.scrollTop !== t.top) {
            target.scrollTop = t.top;
        }
    });
}

Livewire.hook('morph', ({ el }) => capture(el));

Livewire.hook('morphed', ({ el }) => {
    reapply(el); // بلافاصله
    requestAnimationFrame(() => reapply(el)); // بعد از layout
    requestAnimationFrame(() => requestAnimationFrame(() => reapply(el))); // محض اطمینان
});
