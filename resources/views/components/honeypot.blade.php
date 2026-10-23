{{--
    هانی‌پات ضدربات — فیلد متنی مخفی «website».
    انسان آن را نمی‌بیند و پر نمی‌کند؛ ربات‌های ساده همهٔ inputها را پُر می‌کنند.
    اگر مقدارش در سرور پر بود، درخواست بدون ذخیره/ورود رد می‌شود.
--}}@props(['name' => 'website', 'wireTarget' => null])

<div class="absolute size-0 overflow-hidden opacity-0" aria-hidden="true">
    @if($wireTarget)
        <input type="text" tabindex="-1" autocomplete="off" wire:model="{{ $wireTarget }}">
    @else
        <input type="text" name="{{ $name }}" tabindex="-1" autocomplete="off" value="">
    @endif
</div>
