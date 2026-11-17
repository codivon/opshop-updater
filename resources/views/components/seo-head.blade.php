{{--
    سربرگ سئو (Task 82) — همهٔ متاهای صفحات فروشگاه.
    ورودی: ['seo' => آرایهٔ Seo::meta(), 'siteSchemas' => آرایهٔ اسکیمای پایه سایت]
--}}
@props(['seo', 'siteSchemas' => []])

{{-- توضیح + کلمات کلیدی --}}
@if($seo['description'] !== '')
    <meta name="description" content="{{ $seo['description'] }}">
@endif
@if(\App\Support\Seo::str('seo_keywords') !== '')
    <meta name="keywords" content="{{ \App\Support\Seo::str('seo_keywords') }}">
@endif

{{-- robots --}}
<meta name="robots" content="{{ $seo['robots'] }}">

{{-- canonical --}}
<link rel="canonical" href="{{ $seo['canonical'] }}">

{{-- تأیید مالکیت --}}
@if(\App\Support\Seo::str('seo_verif_google') !== '')
    <meta name="google-site-verification" content="{{ \App\Support\Seo::str('seo_verif_google') }}">
@endif
@if(\App\Support\Seo::str('seo_verif_bing') !== '')
    <meta name="msvalidate.01" content="{{ \App\Support\Seo::str('seo_verif_bing') }}">
@endif
@if(\App\Support\Seo::str('seo_verif_yandex') !== '')
    <meta name="yandex-verification" content="{{ \App\Support\Seo::str('seo_verif_yandex') }}">
@endif

{{-- Open Graph --}}
<meta property="og:type" content="{{ $seo['ogType'] }}">
<meta property="og:site_name" content="{{ \App\Support\Features::siteName() }}">
<meta property="og:title" content="{{ $seo['title'] }}">
@if($seo['description'] !== '')
    <meta property="og:description" content="{{ $seo['description'] }}">
@endif
<meta property="og:url" content="{{ $seo['canonical'] }}">
<meta property="og:locale" content="fa_IR">
@if($seo['image'] !== '')
    <meta property="og:image" content="{{ $seo['image'] }}">
@endif

{{-- Twitter Card --}}
<meta name="twitter:card" content="{{ $seo['image'] !== '' ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $seo['title'] }}">
@if($seo['description'] !== '')
    <meta name="twitter:description" content="{{ $seo['description'] }}">
@endif
@if($seo['image'] !== '')
    <meta name="twitter:image" content="{{ $seo['image'] }}">
@endif
@if(\App\Support\Seo::str('seo_twitter_handle') !== '')
    <meta name="twitter:site" content="{{ \App\Support\Seo::str('seo_twitter_handle') }}">
@endif

{{-- favicon --}}
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

{{-- دادهٔ ساخت‌یافته: اسکیمای پایه سایت (همهٔ صفحات) --}}
@foreach($siteSchemas as $schema)
    <script type="application/ld+json">{!! json_encode($schema, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
@endforeach

{{-- دادهٔ ساخت‌یافتهٔ اختصاصی صفحه (SoftwareApplication + BreadcrumbList و...) --}}
@foreach($seo['jsonld'] as $schema)
    <script type="application/ld+json">{!! json_encode($schema, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
@endforeach
