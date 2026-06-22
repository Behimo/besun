<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <link rel="icon" href="{{ asset('favicon.ico') }}" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  @if(app()->environment('production'))
    <meta name="robots" content="index, follow" />
  @else
    <meta name="robots" content="noindex, nofollow" />
  @endif
  <meta name="description" content="راهبر CRM — نرم‌افزار CRM فارسی چندمجموعه‌ای برای مدیریت لید، معامله، کمپین، قیف فروش و گزارش. Rahbar CRM SaaS." />
  <meta name="keywords" content="CRM فارسی, راهبر CRM, Rahbar CRM, نرم افزار CRM, قیف فروش, مدیریت مشتری" />
  <meta name="author" content="Rahbar CRM" />
  <meta property="og:locale" content="fa_IR" />
  <meta property="og:site_name" content="راهبر CRM" />
  <meta property="og:type" content="website" />
  <meta property="og:title" content="راهبر CRM | Rahbar CRM — CRM فارسی فروش و بازاریابی" />
  <meta property="og:description" content="پلتفرم SaaS فارسی برای فروش، بازاریابی و مدیریت تیم — Workspace جدا، گزارش قیف و ماژول‌های تکمیلی." />
  <meta property="og:image" content="{{ asset('marketing/crm-dashboard.png') }}" />
  <meta name="twitter:card" content="summary_large_image" />
  <title>راهبر CRM | Rahbar CRM — CRM فارسی فروش و بازاریابی</title>
  <link rel="stylesheet" type="text/css" href="{{ asset('loader.css') }}" />
  @vite(['resources/js/main.js'])
</head>

<body>
  <div id="app">
    <div id="loading-bg">
      <div class="loading-logo">
        <svg width="86" height="48" viewBox="0 0 34 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path fill-rule="evenodd" clip-rule="evenodd"
            d="M0.00183571 0.3125V7.59485C0.00183571 7.59485 -0.141502 9.88783 2.10473 11.8288L14.5469 23.6837L21.0172 23.6005L19.9794 10.8126L17.5261 7.93369L9.81536 0.3125H0.00183571Z"
            fill="var(--initial-loader-color)"
/>
          <path opacity="0.06" fill-rule="evenodd" clip-rule="evenodd"
            d="M8.17969 17.7762L13.3027 3.75173L17.589 8.02192L8.17969 17.7762Z" fill="#161616"
/>
          <path opacity="0.06" fill-rule="evenodd" clip-rule="evenodd"
            d="M8.58203 17.2248L14.8129 5.24231L17.6211 8.05247L8.58203 17.2248Z" fill="#161616"
/>
          <path fill-rule="evenodd" clip-rule="evenodd"
            d="M8.25781 17.6914L25.1339 0.3125H33.9991V7.62657C33.9991 7.62657 33.8144 10.0645 32.5743 11.3686L21.0179 23.6875H14.5487L8.25781 17.6914Z"
            fill="var(--initial-loader-color)"
/>
        </svg>
      </div>
      <div class=" loading">
        <div class="effect-1 effects"></div>
        <div class="effect-2 effects"></div>
        <div class="effect-3 effects"></div>
      </div>
    </div>
  </div>
  
  <script>
    const loaderColor = localStorage.getItem('vuexy-initial-loader-bg') || '#FFFFFF'
    const primaryColor = localStorage.getItem('vuexy-initial-loader-color') || '#7367F0'

    if (loaderColor)
      document.documentElement.style.setProperty('--initial-loader-bg', loaderColor)

    if (primaryColor)
      document.documentElement.style.setProperty('--initial-loader-color', primaryColor)
    </script>
  </body>
</html>
