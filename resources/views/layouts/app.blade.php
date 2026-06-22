<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'BISAN Holding')) — {{ $pageTitle ?? 'زیرساخت رشد هوشمند' }}</title>
    <meta name="description" content="@yield('description', 'هلدینگ فناوری بیسان — راهبر CRM، نوژارو و افزونه وردپرس برای مدیریت فروش، خدمات و رشد کسب‌وکار')">
    <meta name="keywords" content="بیسان, BISAN, CRM, راهبر, نوژارو, نرم‌افزار, SaaS, ایران">
    <meta name="author" content="BISAN Holding">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="{{ url()->current() }}">

    <meta property="og:type" content="website">
    <meta property="og:locale" content="fa_IR">
    <meta property="og:site_name" content="BISAN Holding">
    <meta property="og:title" content="@yield('og_title', config('app.name') . ' — زیرساخت رشد هوشمند')">
    <meta property="og:description" content="@yield('description', 'مجموعه‌ای از محصولات نرم‌افزاری برای مدیریت، ارتباط با مشتری و توسعه کسب‌وکارها')">
    <meta property="og:url" content="{{ url()->current() }}">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('og_title', config('app.name'))">
    <meta name="twitter:description" content="@yield('description', 'مجموعه‌ای از محصولات نرم‌افزاری برای مدیریت، ارتباط با مشتری و توسعه کسب‌وکارها')">

    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@type": "Organization",
        "name": "BISAN Holding",
        "alternateName": "بیسان",
        "url": "{{ config('app.url') }}",
        "description": "مجموعه‌ای از محصولات نرم‌افزاری برای مدیریت، ارتباط با مشتری و توسعه کسب‌وکارها در دنیای دیجیتال",
        "sameAs": []
    }
    </script>

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
