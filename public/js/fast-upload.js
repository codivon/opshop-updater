/*
|--------------------------------------------------------------------------
| fast-upload.js — آپلود سریع برای اینترنت ایران
|--------------------------------------------------------------------------
| دو ابزار مستقل که بدون نیاز به build (Vite) کار می‌کنند:
|
| ۱) فشرده‌سازی تصویر در مرورگر (data-compress):
|      <input type="file" wire:model="thumbnailFile" data-compress />
|      تصویر قبل از آپلود به حداکثر ۲۲۰۰ پیکسل resize و دوباره encode می‌شود
|      (WebP/JPEG) — معمولاً ۵ تا ۱۰ برابر کوچک‌تر = آپلود چند برابر سریع‌تر.
|
| ۲) آپلود تکه‌ای (data-chunk-upload) برای فایل‌های حجیم (ZIP نسخه، آپدیت، پشتیبان):
|      فایل در تکه‌های ۴ مگابایتی ارسال می‌شود؛ هر تکه ۳ بار تلاش مجدد دارد،
|      اگر اینترنت قطع شود با انتخاب دوبارهٔ همان فایل از همان‌جا ادامه می‌دهد
|      (resume) و درصد پیشرفت واقعی را روی نوار <x-upload-progress> نشان می‌دهد.
|
| رویدادهای livewire-upload-start/progress/finish/error را روی خود input
| ارسال می‌کند تا نوار پیشرفت موجود (upload-progress.blade.php) بدون تغییر کار کند.
*/
(function () {
    'use strict';

    var csrf = function () {
        if (window.livewireScriptConfig && window.livewireScriptConfig.csrf) return window.livewireScriptConfig.csrf;
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    };

    function toast(message, type) {
        try { window.dispatchEvent(new CustomEvent('toast', { detail: { message: message, type: type || 'success' } })); } catch (e) { /* noop */ }
    }

    function wireId(input) {
        var host = input.closest('[wire\\:id]');
        return host ? host.getAttribute('wire:id') : null;
    }

    function setProp(input, prop, value) {
        var id = wireId(input);
        if (id && window.Livewire && window.Livewire.find) {
            try { window.Livewire.find(id).set(prop, value); } catch (e) { /* noop */ }
        }
    }

    function uploadEvent(input, type, progress) {
        var detail = { id: wireId(input) || '', property: input.getAttribute('data-progress-name') || '' };
        if (typeof progress === 'number') detail.progress = progress;
        input.dispatchEvent(new CustomEvent(type, { bubbles: true, detail: detail }));
    }

    function fmtSize(bytes) {
        if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
        if (bytes >= 1024) return Math.round(bytes / 1024) + ' KB';
        return bytes + ' B';
    }

    /* ==================================================================
     *  ۱) فشرده‌سازی تصویر
     * ================================================================== */

    function compressImageFile(file, maxDim, quality) {
        return new Promise(function (resolve) {
            if (!window.createImageBitmap || !/^image\//.test(file.type) || /gif|svg/.test(file.type)) return resolve(file);
            if (file.size <= 300 * 1024) return resolve(file); // کوچک است؛ دست نزن

            var done = function (out) { resolve(out && out.size < file.size ? out : file); };

            window.createImageBitmap(file).then(function (bmp) {
                var scale = Math.min(1, maxDim / Math.max(bmp.width, bmp.height));
                var canvas = document.createElement('canvas');
                canvas.width = Math.max(1, Math.round(bmp.width * scale));
                canvas.height = Math.max(1, Math.round(bmp.height * scale));
                var ctx = canvas.getContext('2d');
                if (!ctx) { bmp.close && bmp.close(); return done(null); }

                // PNG شفافیت دارد → فقط WebP/PNG؛ JPEG پس‌زمینه سفید می‌گیرد
                var flatten = file.type !== 'image/png';
                if (flatten) { ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, canvas.width, canvas.height); }
                ctx.drawImage(bmp, 0, 0, canvas.width, canvas.height);
                bmp.close && bmp.close();

                var types = file.type === 'image/png' ? ['image/webp', 'image/png'] : ['image/webp', 'image/jpeg'];
                var exts = { 'image/webp': '.webp', 'image/jpeg': '.jpg', 'image/png': '.png' };

                var tryEncode = function (i) {
                    if (i >= types.length) return done(null);
                    canvas.toBlob(function (blob) {
                        if (!blob) return tryEncode(i + 1);
                        var out = new File([blob], file.name.replace(/\.[^.]+$/, '') + exts[types[i]], { type: types[i], lastModified: Date.now() });
                        done(out);
                    }, types[i], quality);
                };
                tryEncode(0);
            }, function () { resolve(file); });
        });
    }

    function handleCompress(input) {
        var files = Array.prototype.slice.call(input.files || []);
        if (!files.length) return;

        var maxDim = parseInt(input.getAttribute('data-compress-max') || '2200', 10);
        var quality = parseFloat(input.getAttribute('data-compress-quality') || '0.85');
        var before = 0, after = 0, compressed = 0;

        var chain = Promise.resolve();
        var results = new Array(files.length);

        files.forEach(function (f, i) {
            chain = chain.then(function () {
                return compressImageFile(f, maxDim, quality).then(function (out) {
                    results[i] = out;
                    if (out !== f) { compressed++; before += f.size; after += out.size; }
                });
            });
        });

        chain.then(function () {
            if (compressed) {
                try {
                    var dt = new DataTransfer();
                    results.forEach(function (f) { if (f) dt.items.add(f); });
                    input.files = dt.files;
                } catch (e) { /* مرورگر قدیمی — فایل اصلی آپلود می‌شود */ }
                var saved = before - after;
                if (saved > 0) {
                    toast('تصویر فشرده شد (' + fmtSize(saved) + ' کمتر) — آپلود سریع‌تر شد.', 'success');
                }
            }
            var ev = new Event('change', { bubbles: true });
            ev.__fu = true;
            input.dispatchEvent(ev);
        });
    }

    /* ==================================================================
     *  ۲) آپلود تکه‌ای (Chunked) با resume
     * ================================================================== */

    var ENDPOINT = '/admin/upload/chunk';

    function fingerprint(file) {
        return ['fu', file.name, file.size, file.lastModified].join('|').slice(0, 180);
    }

    function resumeRecord(file) {
        try {
            var raw = localStorage.getItem(fingerprint(file));
            return raw ? JSON.parse(raw) : null;
        } catch (e) { return null; }
    }

    function saveResume(file, record) {
        try { localStorage.setItem(fingerprint(file), JSON.stringify(record)); } catch (e) { /* noop */ }
    }

    function clearResume(file) {
        try { localStorage.removeItem(fingerprint(file)); } catch (e) { /* noop */ }
    }

    function serverSize(id) {
        return fetch(ENDPOINT + '/status?id=' + encodeURIComponent(id), { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : { exists: false, size: 0 }; })
            .catch(function () { return { exists: false, size: 0 }; });
    }

    function sendChunk(id, offset, blob, maxMb) {
        var fd = new FormData();
        fd.append('upload_id', id);
        fd.append('offset', String(offset));
        fd.append('max_mb', String(maxMb));
        fd.append('chunk', blob);
        return fetch(ENDPOINT, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: fd,
            credentials: 'same-origin',
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (body) {
                if (!r.ok) {
                    body.__http = r.status;
                    return Promise.reject(body);
                }
                return body;
            });
        });
    }

    function uuid() {
        if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    function handleChunk(input, file) {
        if (input.__fuBusy) return;
        if (!wireId(input)) {
            toast('اتصال به فرم برقرار نشد — صفحه را رفرش کنید.', 'error');
            return;
        }
        input.__fuBusy = true;

        var props = (input.getAttribute('data-chunk-props') || '').split('|');
        var pathProp = props[0] || null;
        var nameProp = props[1] || null;
        var sizeProp = props[2] || null;
        var maxMb = parseInt(input.getAttribute('data-chunk-max') || '512', 10);
        var chunkBytes = parseInt(input.getAttribute('data-chunk-bytes') || String(4 * 1024 * 1024), 10);
        var accept = (input.getAttribute('data-chunk-accept') || '').split(',').map(function (s) { return s.trim().toLowerCase(); }).filter(Boolean);

        var finishProps = function (relPath) {
            if (pathProp) setProp(input, pathProp, relPath);
            if (nameProp) setProp(input, nameProp, file.name);
            if (sizeProp) setProp(input, sizeProp, file.size);
        };
        var resetProps = function () {
            if (pathProp) setProp(input, pathProp, null);
            if (nameProp) setProp(input, nameProp, null);
            if (sizeProp) setProp(input, sizeProp, null);
        };

        // اعتبارسنجی سریع سمت کلاینت
        if (!file.size) {
            input.__fuBusy = false;
            toast('فایل خالی است.', 'error');
            return;
        }
        var ext = (file.name.split('.').pop() || '').toLowerCase();
        if (accept.length && accept.indexOf(ext) === -1) {
            input.__fuBusy = false;
            toast('فرمت فایل مجاز نیست (فقط ' + accept.join('، ') + ').', 'error');
            return;
        }
        if (file.size > maxMb * 1048576) {
            input.__fuBusy = false;
            toast('حجم فایل بیشتر از ' + maxMb + ' مگابایت است.', 'error');
            return;
        }

        uploadEvent(input, 'livewire-upload-start');

        // resume: اگر همین فایل قبلاً نیمه‌کاره آپلود شده، از همان‌جا ادامه بده
        var rec = resumeRecord(file);
        var id = rec && rec.id ? rec.id : uuid();
        var startOffset = 0;

        serverSize(id).then(function (st) {
            if (st.exists && st.size > 0 && st.size < file.size) {
                startOffset = st.size; // ادامه از وسط
            } else if (st.exists && st.size >= file.size && file.size > 0) {
                // کامل شده ولی ثبت نشده
                finishProps('chunk-uploads/' + id + '.part');
                uploadEvent(input, 'livewire-upload-finish');
                clearResume(file);
                input.__fuBusy = false;
                toast('آپلود کامل شد.', 'success');
                return null;
            } else {
                id = uuid(); // فایل سمت سرور نیست → شناسه تازه
            }

            saveResume(file, { id: id, size: file.size, name: file.name, lastModified: file.lastModified });

            var offset = startOffset;
            var retries = 0;
            var gaps = 0;

            var next = function () {
                if (offset >= file.size) {
                    finishProps('chunk-uploads/' + id + '.part');
                    uploadEvent(input, 'livewire-upload-finish');
                    clearResume(file);
                    input.__fuBusy = false;
                    if (startOffset > 0) toast('آپلود از ' + fmtSize(startOffset) + ' ادامه یافت و کامل شد.', 'success');
                    else toast('آپلود کامل شد.', 'success');
                    return;
                }

                var end = Math.min(offset + chunkBytes, file.size);
                var blob = file.slice(offset, end);

                return sendChunk(id, offset, blob, maxMb).then(function (body) {
                    retries = 0;
                    gaps = 0;
                    var size = (body && body.size) || end;
                    if (size > offset) {
                        offset = size;
                        saveResume(file, { id: id, size: file.size, name: file.name, lastModified: file.lastModified });
                        uploadEvent(input, 'livewire-upload-progress', Math.min(99, Math.round((offset / file.size) * 100)));
                    }
                    return next();
                }, function (err) {
                    if (err && err.__http === 409 && typeof err.size === 'number') {
                        // همگام‌سازی با اندازهٔ واقعی سرور (جلو یا عقب)
                        gaps++;
                        if (gaps > 8) throw new Error('همگام‌سازی تکه‌ها ناموفق بود — دوباره فایل را انتخاب کنید.');
                        retries = 0;
                        offset = Math.max(0, Math.min(err.size, file.size));
                        uploadEvent(input, 'livewire-upload-progress', Math.min(99, Math.round((offset / file.size) * 100)));
                        return next();
                    }
                    if (err && (err.__http === 413 || err.__http === 422)) {
                        throw new Error((err.message) || 'خطای اعتبارسنجی سرور');
                    }
                    if (retries >= 3) {
                        throw new Error('ارتباط قطع شد — دوباره همین فایل را انتخاب کنید تا از همان‌جا ادامه یابد.');
                    }
                    retries++;
                    return new Promise(function (res) { setTimeout(res, 1200 * retries); }).then(next);
                });
            };

            return next().catch(function (e) {
                uploadEvent(input, 'livewire-upload-error');
                resetProps();
                input.__fuBusy = false;
                toast('آپلود ناتمام ماند' + (e && e.message ? ': ' + e.message : '.') + ' با انتخاب دوبارهٔ همین فایل ادامه می‌دهد.', 'warning');
            });
        });
    }

    /* ==================================================================
     *  رهگیری انتخاب فایل (فاز capture → زودتر از Livewire)
     * ================================================================== */

    document.addEventListener('change', function (e) {
        var input = e.target;
        if (e.__fu) return;
        if (!input || input.tagName !== 'INPUT' || input.type !== 'file') return;
        if (!input.files || !input.files.length) return; // انصراف از انتخاب

        if (input.hasAttribute('data-chunk-upload')) {
            e.preventDefault();
            e.stopPropagation();
            handleChunk(input, input.files[0]);
            return;
        }
        if (input.hasAttribute('data-compress')) {
            e.preventDefault();
            e.stopPropagation();
            handleCompress(input);
        }
    }, true);
})();
