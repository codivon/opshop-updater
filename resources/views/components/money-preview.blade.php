{{--
    پیش‌نمایش زندهٔ مبلغ (number_format فارسی) — برای جلوگیری از اشتباه در ورود مبالغ.

    استفاده: دقیقاً بعد از input مبلغی قرار می‌گیرد:
        <x-input id="form.price" type="number" ... />
        <x-money-preview target="form.price" />

    - با هر کلید، مقدار را زنده با جداکنندهٔ هزارگان و ارقام فارسی نشان می‌دهد
    - $suffix: واحد نمایشی (پیش‌فرض «تومان»؛ برای درصد «٪» بدهید)
    - وقتی ورودی خالی/صفر است مخفی می‌شود
--}}
@props(['target' => '', 'suffix' => 'تومان'])

<p {{ $attributes->merge([
        'class'       => 'mt-1.5 flex items-center gap-1 text-[11px] font-bold text-emerald-600 dark:text-emerald-400',
        'data-target' => $target,
        'data-suffix' => $suffix,
    ]) }}
     x-data="{
        show: false,
        text: '',
        preview(e) {
            if (e && e.target && e.target.id !== this.$el.dataset.target) return;
            const input = document.getElementById(this.$el.dataset.target);
            if (!input) { this.show = false; this.text = ''; return; }
            const n = parseInt(String(input.value).replace(/[^\d]/g, ''), 10);
            if (!isNaN(n) && n > 0) {
                const suf = this.$el.dataset.suffix || '';
                this.text = String(n.toLocaleString('en-US')).replace(/[0-9]/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]) + (suf ? ' ' + suf : '');
                this.show = true;
            } else {
                this.show = false;
                this.text = '';
            }
        }
     }"
     x-init="preview()"
     x-on:input.document="preview($event)"
     x-on:change.document="preview($event)"
     x-show="show"
     x-cloak
     role="status"
     aria-live="polite"
>
    <x-icon name="banknote" class="size-3.5 shrink-0" />
    <span x-text="text"></span>
</p>
