{{-- بخش «track» (پیگیری خرید مهمان) صفحهٔ اصلی — سوییچ/ترتیب از پنل (Task 59) --}}
@if($ho['home_track_enabled'])
    <div class="mx-auto w-full max-w-7xl px-4 sm:px-6">
        <section aria-label="پیگیری خرید">
            <livewire:shop.purchase-track />
        </section>
    </div>
@endif
