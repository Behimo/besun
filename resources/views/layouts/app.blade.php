<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php $seoMeta = $seo ?? []; @endphp

    <title>{{ $seoMeta['title'] ?? config('cms.site_name') }}</title>
    <meta name="description" content="{{ $seoMeta['description'] ?? '' }}">
    @if (!empty($seoMeta['keywords']))
        <meta name="keywords" content="{{ $seoMeta['keywords'] }}">
    @endif
    <meta name="author" content="{{ config('cms.site_name') }}">
    <meta name="robots" content="{{ $seoMeta['robots'] ?? 'index, follow' }}">
    <link rel="canonical" href="{{ $seoMeta['canonical'] ?? url()->current() }}">

    <meta property="og:type" content="website">
    <meta property="og:locale" content="fa_IR">
    <meta property="og:site_name" content="{{ config('cms.site_name') }}">
    <meta property="og:title" content="{{ $seoMeta['og_title'] ?? $seoMeta['title'] ?? config('cms.site_name') }}">
    <meta property="og:description" content="{{ $seoMeta['description'] ?? '' }}">
    <meta property="og:url" content="{{ $seoMeta['canonical'] ?? url()->current() }}">
    @if (!empty($seoMeta['og_image']))
        <meta property="og:image" content="{{ $seoMeta['og_image'] }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="{{ $seoMeta['og_title'] ?? $seoMeta['title'] ?? '' }}">
    @endif

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seoMeta['og_title'] ?? $seoMeta['title'] ?? '' }}">
    <meta name="twitter:description" content="{{ $seoMeta['description'] ?? '' }}">
    @if (!empty($seoMeta['og_image']))
        <meta name="twitter:image" content="{{ $seoMeta['og_image'] }}">
    @endif
    @if (config('cms.twitter_handle'))
        <meta name="twitter:site" content="{{ config('cms.twitter_handle') }}">
    @endif

    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate" hreflang="fa-IR" href="{{ url()->current() }}">

    @if (!empty($structuredData))
        @foreach ($structuredData as $schema)
            <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
        @endforeach
    @else
        <script type="application/ld+json">{!! json_encode(app(\App\Services\SeoService::class)->organizationSchema(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="font-sans overflow-x-hidden bg-bisan-bg" x-data="{ mobileMenu: false }">
    <x-custom-cursor />
    <div class="pointer-events-none fixed inset-0 z-[100] opacity-[0.015]" style="background-image: url('data:image/svg+xml,%3Csvg viewBox=%220 0 256 256%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cfilter id=%22noise%22%3E%3CfeTurbulence type=%22fractalNoise%22 baseFrequency=%220.9%22 numOctaves=%224%22 stitchTiles=%22stitch%22/%3E%3C/filter%3E%3Crect width=%22100%25%22 height=%22100%25%22 filter=%22url(%23noise)%22/%3E%3C/svg%3E');"></div>
    @yield('content')
</body>
</html>
