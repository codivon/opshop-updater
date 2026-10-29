/**
 * حفظ موقعیت اسکرول کانتینرهای اسکرول‌شونده در طول morph لایووایر.
 *
 * مشکل (گزارش کاربر — Task 67): در مودال افزودن/ویرایش طرح اشتراک، هر تعامل
 * (باز/بسته کردن «قابلیت‌ها و پکیج‌های این مدت»، افزودن/حذف ردیف قابلیت یا پکیج)
 * باعث پرش اسکرول مودال به بالا می‌شد و کاربر باید دوباره به پایین اسکرول می‌کرد.
 *
 * علت (اندازه‌گیری زندهٔ فریم‌به‌فریم + MutationObserver):
 * حین morph، محتوای کانتینر اسکرول موقتاً خالی/جابه‌جا می‌شود — در همین پنجرهٔ
 * کوتاه scrollTop کانتینر صفر خوانده می‌شود (بدون رویداد scroll و گاهی حتی قبل
 * از رسیدن پاسخ). اگر موقعیت فقط چند بار ثابتِ بعد از morph بازگردانی شود،
 * وقتی محتوا هنوز برنگشته باشد مرورگر آن را به صفر clamp می‌کند و پرش باقی
 * می‌ماند (روی ماشین‌های کندتر پنجرهٔ خالی بودن طولانی‌تر است).
 *
 * راه‌حل:
 * ۱) هر کانتینر با attribute «data-scroll-preserve» علامت‌گذاری می‌شود؛ درست
 *    قبل از اعمال morph (لحظهٔ دریافت پاسخ — یعنی آخرین اسکرول کاربر هم ثبت
 *    شده) موقعیت ذخیره می‌شود.
 * ۲) بعد از morph، موقعیت «هر فریم تا بازگشت محتوا» اعمال می‌شود (تا سقف کوتاه).
 *    وقتی کانتینر خالی است کاربر اصلاً نمی‌تواند اسکرول کند، پس این تلاش مجدد
 *    هرگز با اسکرول دستی کاربر تداخل ندارد؛ به محض بازگشت محتوا یک بار اعمال
 *    و تمام می‌شود.
 * ۳) اگر لحظهٔ capture کانتینر سالم نبود (خالی/جابه‌جاشده)، آخرین موقعیت سالمِ
 *    همان کانتینر به‌کار می‌رود — که با شنیدهٔ مستمر رویدادهای scroll همیشه
 *    به‌روز است، نه فقط در لحظه‌های morph.
 */
const tracked = [];
// «شناسهٔ ریشهٔ کامپوننت|نام#اندیس» → آخرین موقعیت سالم دیده‌شده
// (شناسهٔ ریشه در کلید هست چون چند کامپوننت می‌توانند کانتینر هم‌نام داشته باشند)
const lastGood = new Map();

/** آیا کانتینر این لحظه سالم و واقعاً اسکرول‌پذیر است؟ */
function healthy(el) {
    // حاشیهٔ ۵۰px برای رد کردن حالت‌های نیمه‌خالیِ گذرا حین morph
    return el.clientHeight > 0 && el.scrollHeight > el.clientHeight + 50;
}

/** کلید یکتای کانتینر: ریشهٔ کامپوننت + نام + ترتیب بین هم‌نام‌ها */
function keyOf(el) {
    const scope = el.closest('[wire\\:id]')?.getAttribute('wire:id') ?? '';
    const name = el.getAttribute('data-scroll-preserve');
    const root = scope ? document.querySelector(`[wire\\:id="${scope}"]`) : null;
    const idx = root
        ? [...root.querySelectorAll(`[data-scroll-preserve="${name}"]`)].indexOf(el)
        : 0;
    return scope + '|' + name + '#' + Math.max(idx, 0);
}

// به‌روزرسانی مستمر «آخرین موقعیت سالم» — پرش‌های morph رویداد scroll نمی‌فرستند،
// پس فقط اسکرول واقعی (کاربر یا بازگردانی خود ما) اینجا ثبت می‌شود.
document.addEventListener(
    'scroll',
    (e) => {
        const el = e.target;
        if (!(el instanceof Element) || !el.matches?.('[data-scroll-preserve]')) return;
        if (!healthy(el)) return;
        lastGood.set(keyOf(el), el.scrollTop);
    },
    { capture: true, passive: true },
);

/** گردآوری کانتینرهای علامت‌گذاری‌شده داخل ریشه (خود ریشه هم بررسی می‌شود) */
function collect(root) {
    if (!root || typeof root.querySelectorAll !== 'function') return [];
    return root.matches?.('[data-scroll-preserve]')
        ? [root, ...root.querySelectorAll('[data-scroll-preserve]')]
        : [...root.querySelectorAll('[data-scroll-preserve]')];
}

/** ذخیرهٔ موقعیت همهٔ کانتینرها — در هوک morph (لحظهٔ رسیدن پاسخ) */
function capture(root) {
    tracked.length = 0;

    const els = collect(root);
    const counts = {};
    els.forEach((el) => {
        const name = el.getAttribute('data-scroll-preserve');
        const idx = (counts[name] = (counts[name] ?? -1) + 1); // ترتیب DOM بین هم‌نام‌ها
        // کانتینر سالم نیست؟ scrollTopِ فعلی ناشی از به‌هم‌ریختگی morph است —
        // آخرین موقعیت سالمِ ثبت‌شده (که با اسکرول کاربر هم به‌روز می‌شود) معتبرتر است
        const key = (root?.getAttribute?.('wire:id') ?? '') + '|' + name + '#' + idx;
        const top = healthy(el) ? el.scrollTop : (lastGood.get(key) ?? 0);
        tracked.push({ name, idx, top, settled: false });
    });
}

/**
 * اعمال موقعیت ذخیره‌شده — فقط وقتی محتوای کانتینر واقعاً برگشته باشد.
 * هر بار از DOM زنده کوئری تازه می‌زنیم (نود ممکن است هر لحظه توسط morph
 * جایگزین شده باشد؛ تکیه بر مرجع قدیمی یعنی بازگردانی به نود جدا از DOM).
 */
function reapply(root) {
    if (!root || typeof root.querySelectorAll !== 'function' || tracked.length === 0) return;

    const els = collect(root);
    const counts = {};
    els.forEach((el) => {
        const name = el.getAttribute('data-scroll-preserve');
        const idx = (counts[name] = (counts[name] ?? -1) + 1);
        const t = tracked.find((x) => x.name === name && x.idx === idx && !x.settled);
        if (!t) return;
        if (!healthy(el)) return; // محتوا هنوز برنگشته — در فریم بعد
        if (el.scrollTop !== t.top) el.scrollTop = t.top;
        t.settled = true; // حتی اگر clamp شد، همین نهایتِ مجازِ محتوای فعلی است
    });
}

Livewire.hook('morph', ({ el }) => capture(el));

Livewire.hook('morphed', ({ el }) => {
    reapply(el); // مسیر سریع: معمولاً محتوا همین لحظه سر جایش است

    // تلاش مجدد فریم‌به‌فریم: تا وقتی محتوای کانتینر خالی است set کردن scrollTop
    // بی‌اثر (clamp به صفر) است؛ به محض بازگشت محتوا یک بار اعمال و تمام.
    let tries = 0;
    const tick = () => {
        tries++;
        reapply(el);
        if (tries < 45 && tracked.some((t) => !t.settled)) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
});
