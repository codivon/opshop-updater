{{--
    صفحهٔ اصلی فروشگاه — بخش‌ها به‌صورت ویجت‌های مستقل.
    ترتیب رندر از کلیدهای home_sort_* (پنل «صفحهٔ اصلی فروشگاه» ← تب بخش‌ها)
    و روشن/خاموشی هر بخش از home_*_enabled خوانده می‌شود (Task 59).
    هر بخش partial خودش را در livewire/shop/home-sections دارد.
--}}
<div class="space-y-10">
    @foreach($sections as $sectionKey)
        <div wire:key="home-section-{{ $sectionKey }}">
            @include('livewire.shop.home-sections.'.$sectionKey)
        </div>
    @endforeach
</div>
