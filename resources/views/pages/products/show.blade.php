@extends('layouts.site')

@section('page')
    <x-page-hero
        :badge="$product['subtitle']"
        :title="$product['title']"
        :subtitle="$product['description']"
    />

    <section class="landing-section pb-20">
        <div class="landing-container">
            <x-breadcrumb :items="[
                ['name' => 'خانه', 'url' => route('home')],
                ['name' => 'محصولات', 'url' => route('products.index')],
                ['name' => $product['title'], 'url' => route('products.show', $product['slug'])],
            ]" />

            <div class="grid gap-12 lg:grid-cols-2">
                <div>
                    @if (!empty($product['audience']))
                        <div class="glass-card-3d p-4 mb-6 inline-block">
                            <p class="text-sm text-slate-400">مناسب برای</p>
                            <p class="font-semibold text-bisan-orange">{{ $product['audience'] }}</p>
                        </div>
                    @endif

                    @if (!empty($product['highlights']))
                        <div class="grid gap-4 sm:grid-cols-3 mb-8">
                            @foreach ($product['highlights'] as $highlight)
                                <div class="glass-card-3d p-4">
                                    <h3 class="text-sm font-semibold text-white">{{ $highlight['label'] }}</h3>
                                    <p class="mt-1 text-xs text-slate-400 leading-6">{{ $highlight['desc'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if (!empty($product['features']))
                        <h2 class="text-xl font-semibold text-white mb-4">ویژگی‌های کلیدی</h2>
                        <ul class="space-y-3 mb-8">
                            @foreach ($product['features'] as $feature)
                                <li class="flex items-start gap-3 text-slate-300">
                                    <svg class="h-5 w-5 shrink-0 text-bisan-orange mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    {{ $feature }}
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if (!empty($product['body']))
                        <h2 class="text-xl font-semibold text-white mb-4">درباره {{ $product['title'] }}</h2>
                        <div class="prose prose-invert max-w-none text-slate-300 leading-relaxed">
                            {!! nl2br(e($product['body'])) !!}
                        </div>
                    @endif
                </div>

                <div class="space-y-6">
                    @if (!empty($product['dashboard_image']))
                        <div class="glass-card-3d overflow-hidden p-2 sticky top-28">
                            <img
                                src="{{ $product['dashboard_image'] }}"
                                alt="داشبورد {{ $product['title'] }}"
                                class="w-full rounded-xl object-cover object-top"
                                loading="lazy"
                            >
                            @if (!empty($product['website_url']))
                                <div class="p-4 pt-3">
                                    <a
                                        href="{{ $product['website_url'] }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="btn-outline w-full justify-center"
                                    >
                                        مشاهده سایت {{ $product['title'] }}
                                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                        </svg>
                                    </a>
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="glass-card-3d p-8 {{ !empty($product['dashboard_image']) ? '' : 'sticky top-28' }}">
                        <h3 class="text-lg font-semibold text-white mb-4">درخواست دمو رایگان</h3>
                        <p class="text-slate-400 mb-6 text-sm">تیم ما در ۲۴ ساعت با شما تماس می‌گیرد و دمو اختصاصی ارائه می‌دهد.</p>
                        <a href="{{ route('contact', ['product' => $product['title']]) }}" class="btn-primary w-full justify-center mb-4">
                            {{ $product['cta'] ?? 'درخواست دمو' }}
                        </a>
                        @if (!empty($product['website_url']))
                            <a
                                href="{{ $product['website_url'] }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="btn-outline w-full justify-center mb-4"
                            >
                                ورود به سایت {{ $product['title'] }}
                            </a>
                        @endif
                        <a href="{{ route('products.index') }}" class="btn-outline w-full justify-center">بازگشت به محصولات</a>
                    </div>

                    <div class="glass-card-3d p-6">
                        <h4 class="font-semibold text-white mb-3">چرا {{ $product['title'] }}؟</h4>
                        <ul class="space-y-2 text-sm text-slate-400">
                            <li class="flex items-center gap-2">
                                <span class="h-1.5 w-1.5 rounded-full bg-bisan-orange"></span>
                                راه‌اندازی سریع — بدون نصب پیچیده
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="h-1.5 w-1.5 rounded-full bg-bisan-orange"></span>
                                پشتیبانی فارسی در ساعات کاری
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="h-1.5 w-1.5 rounded-full bg-bisan-orange"></span>
                                آموزش رایگان برای تیم شما
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <x-product-sales-sections :product="$product" />
@endsection
