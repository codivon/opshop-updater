{{--
    Switch toggle for booleans (RTL app – knob slides to logical end).
    <x-toggle wire:model="form.is_free" label="پکیج رایگان است" />

    FIX (چک‌باکس‌ها فعال/غیرفعال نمی‌شدند): قبلاً uniqid() دوبار صدا زده می‌شد و
    مقدار for با id فرق می‌کرد → کلیک روی label به هیچ inputی نمی‌رسید.
    حالا: اگر id صریح نبود، اصلاً for/id چاپ نمی‌شود و اتصال label از طریق
    descendant خودِ input انجام می‌شود (پایدار در برابر morph لیووایر).
--}}
@props(['label' => null, 'id' => null])

@php($hasId = filled($id))
{{-- relative: باکس جای‌گیری input مخفی (sr-only) خودِ label می‌شود تا ۱px مخفی همیشه
     داخل ناحیهٔ دیدنی سوییچ بماند؛ در غیر این صورت focus مرورگر روی input، والدهای
     overflow-hidden (مثل پنل مودال) را اسکرول می‌کند و کل مودال سفید می‌شود. --}}
<label @if($hasId) for="{{ $id }}" @endif class="group relative inline-flex cursor-pointer select-none items-center gap-3">
    <input type="checkbox" @if($hasId) id="{{ $id }}" @endif {{ $attributes->merge(['class' => 'peer sr-only']) }} />
    <span class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full bg-zinc-300 transition-colors duration-200 peer-checked:bg-brand-600 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500/40 dark:bg-zinc-700
                   after:absolute after:start-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition-transform after:duration-200
                   peer-checked:after:-translate-x-5">
    </span>
    @if($label)
        <span class="text-sm font-medium text-zinc-700 group-hover:text-zinc-900 dark:text-zinc-300 dark:group-hover:text-zinc-100">{{ $label }}</span>
    @endif
</label>
