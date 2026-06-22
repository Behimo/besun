@extends('layouts.app')



@section('title', 'بیسان | BISAN Holding')

@section('description', 'هلدینگ فناوری بیسان — راهبر CRM، نوژارو و افزونه وردپرس برای مدیریت فروش، خدمات و رشد کسب‌وکار')

@section('og_title', 'BISAN Holding — زیرساخت رشد هوشمند کسب‌وکار')



@section('content')

<div class="landing" x-data="landingExperience">

  <div class="scroll-progress" aria-hidden="true">

        <div class="scroll-progress__bar" :style="`transform: scaleX(${ $store.landing.scrollProgress })`"></div>

    </div>



    <div class="ambient-layer" aria-hidden="true">

        <div class="ambient-orb ambient-orb--orange" data-parallax="0.12"></div>

        <div class="ambient-orb ambient-orb--purple" data-parallax="-0.08"></div>

        <div class="ambient-orb ambient-orb--blue" data-parallax="0.06"></div>

        <div class="ambient-grid"></div>

    </div>



    <x-navbar :nav-links="$navLinks" />



    <main class="relative z-10">

        <x-hero :hero-pills="$heroPills" />



        <x-why-bisan :items="$whyBisan" />



        {{-- Products --}}

        <section id="products" data-section="products" class="landing-section section-products">

            <div class="section-glow section-glow--orange" data-parallax="0.04"></div>

            <div class="landing-container">

                <x-section-header

                    badge="محصولات بیسان"

                    title="راهکار مناسب"

                    highlight="کسب‌وکار شما"

                    subtitle="هر محصول برای یک نیاز مشخص طراحی شده — می‌توانید به‌صورت مستقل یا در کنار یکدیگر استفاده کنید"

                />



                <div class="products-grid" data-sr="scale" data-sr-delay="120">

                    <div class="products-stage__ring products-stage__ring--1" aria-hidden="true"></div>

                    <div class="products-stage__ring products-stage__ring--2" aria-hidden="true"></div>



                    <div class="grid gap-6 lg:grid-cols-3 lg:gap-8">

                        @foreach ($products as $index => $product)

                            <x-product-card

                                :title="$product['title']"

                                :subtitle="$product['subtitle']"

                                :description="$product['description']"

                                :accent="$product['accent']"

                                :visual="$product['visual']"

                                :features="$product['features']"

                                :href="$product['href']"

                                :audience="$product['audience']"

                                :cta="$product['cta']"

                                data-sr="up"

                                data-sr-group="products"

                                id="{{ match($product['accent']) {

                                    'orange' => 'rahbar',

                                    'purple' => 'nojaro',

                                    'blue' => 'nojaro-plugin',

                                    default => 'product-' . $index,

                                } }}"

                                @class([

                                    'product-card--featured' => $product['accent'] === 'orange',

                                ])

                            />

                        @endforeach

                    </div>

                </div>

            </div>

        </section>



        {{-- About --}}

        <section id="about" data-section="about" class="landing-section section-about">

            <div class="section-glow section-glow--purple" data-parallax="-0.05"></div>

            <div class="landing-container">

                <div class="grid items-center gap-14 lg:grid-cols-2 lg:gap-20">

                    <div class="about-visual" data-sr="right">

                        <div class="about-visual__frame">

                            <div class="about-timeline">

                                @foreach ($aboutTimeline as $step)

                                    <div class="about-timeline__item" data-sr="up" data-sr-group="timeline">

                                        <div class="about-timeline__node">

                                            <span class="about-timeline__year">{{ $step['year'] }}</span>

                                        </div>

                                        <div class="about-timeline__content glass-card-3d">

                                            <h3 class="font-semibold text-white">{{ $step['title'] }}</h3>

                                            <p class="mt-1 text-sm text-slate-400">{{ $step['desc'] }}</p>

                                        </div>

                                    </div>

                                @endforeach

                                <div class="about-timeline__line" aria-hidden="true"></div>

                            </div>

                        </div>

                    </div>



                    <div data-sr="left">

                        <x-section-header

                            align="right"

                            badge="درباره بیسان"

                            title="هلدینگ فناوری"

                            highlight="بیسان"

                            subtitle="توسعه‌دهنده محصولات SaaS بومی برای بازار ایران"

                            class="mb-8 lg:text-right"

                        />

                        <p class="text-lg leading-relaxed text-slate-300">

                            BISAN Holding (تجارت هوشمند ایرانیان) با تمرکز بر نیازهای واقعی کسب‌وکارهای ایرانی، راهکارهای نرم‌افزاری می‌سازد که فروش، عملیات و ارتباط با مشتری را ساده‌تر می‌کند.

                        </p>

                        <p class="mt-4 text-base leading-relaxed text-slate-500">

                            مأموریت ما ارائه زیرساختی قابل اعتماد است تا تیم‌ها کمتر درگیر ابزارهای پراکنده شوند و بیشتر روی رشد کسب‌وکار تمرکز کنند.

                        </p>



                        <div class="mt-8 grid gap-4 sm:grid-cols-2">

                            @foreach ($aboutPillars as $point)

                                <div class="pillar-card" data-sr="up" data-sr-group="pillars">

                                    <div class="pillar-card__icon">

                                        <svg class="h-5 w-5 text-bisan-orange" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">

                                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $point['icon'] }}"/>

                                        </svg>

                                    </div>

                                    <p class="text-sm leading-7 text-slate-300">{{ $point['text'] }}</p>

                                </div>

                            @endforeach

                        </div>

                    </div>

                </div>

            </div>

        </section>



        {{-- Stats --}}

        <section id="stats" data-section="stats" class="landing-section section-stats">

            <div class="stats-backdrop" data-parallax="0.03" aria-hidden="true">

                <div class="stats-globe">

                    <div class="stats-globe__sphere"></div>

                    <div class="stats-globe__ring stats-globe__ring--1"></div>

                    <div class="stats-globe__ring stats-globe__ring--2"></div>

                    @foreach ([[20,30],[70,25],[80,60],[25,75],[50,50],[60,40]] as [$x, $y])

                        <span class="stats-globe__node" style="left: {{ $x }}%; top: {{ $y }}%;"></span>

                    @endforeach

                </div>

            </div>



            <div class="landing-container relative z-10">

                <x-section-header

                    badge="اعتماد مشتریان"

                    title="اعداد،"

                    highlight="تعهد ما"

                    subtitle="بیش از یک دهه همراهی با کسب‌وکارهای ایرانی در مسیر دیجیتال‌شدن"

                />



                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">

                    @php

                        $statIcons = [

                            'business' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',

                            'heart' => 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z',

                            'clock' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',

                            'support' => 'M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z',

                        ];

                        $statColors = ['text-bisan-orange', 'text-bisan-purple', 'text-bisan-orange', 'text-bisan-blue'];

                    @endphp



                    @foreach ($stats as $index => $stat)

                        <div

                            class="stat-cube"

                            data-sr="flip"

                            data-sr-group="stats"

                            x-data="counter('{{ $stat['value'] }}')"

                        >

                            <div class="stat-cube__inner">

                                <div class="stat-cube__face stat-cube__face--front glass-card-3d p-6 text-center">

                                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-white/5 ring-1 ring-white/10">

                                        <svg class="h-7 w-7 {{ $statColors[$index] ?? 'text-bisan-orange' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">

                                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $statIcons[$stat['icon']] ?? $statIcons['business'] }}"/>

                                        </svg>

                                    </div>

                                    <p class="mb-1 text-3xl font-extrabold text-white lg:text-4xl" x-text="display">{{ $stat['value'] }}</p>

                                    <p class="text-sm font-medium text-slate-300">{{ $stat['label'] }}</p>

                                    @if (!empty($stat['hint']))

                                        <p class="mt-1 text-xs text-slate-500">{{ $stat['hint'] }}</p>

                                    @endif

                                </div>

                            </div>

                        </div>

                    @endforeach

                </div>

            </div>

        </section>



        {{-- Partners --}}

        <section class="landing-section section-partners border-y border-white/5 py-14">

            <div class="landing-container">

                <p class="mb-10 text-center text-sm text-slate-500" data-sr="up">

                    همراه کسب‌وکارها و برندهای شناخته‌شده در ایران

                </p>

                <div class="partners-marquee" data-sr="scale">

                    <div class="partners-marquee__track">

                        @foreach (array_merge($partners, $partners) as $partner)

                            <span class="partners-marquee__item">{{ $partner }}</span>

                        @endforeach

                    </div>

                </div>

            </div>

        </section>



        {{-- CTA --}}

        <section id="contact" data-section="contact" class="landing-section section-cta">

            <div class="section-glow section-glow--cta" data-parallax="0.07"></div>

            <div class="landing-container">

                <div class="cta-portal" data-sr="scale">

                    <div class="cta-portal__rings" aria-hidden="true">

                        <div class="cta-portal__ring cta-portal__ring--1"></div>

                        <div class="cta-portal__ring cta-portal__ring--2"></div>

                        <div class="cta-portal__ring cta-portal__ring--3"></div>

                    </div>



                    <div class="cta-portal__cubes" aria-hidden="true">

                        <div class="cta-cube cta-cube--orange"></div>

                        <div class="cta-cube cta-cube--purple"></div>

                        <div class="cta-cube cta-cube--blue"></div>

                    </div>



                    <div class="cta-portal__content glass-card-3d">

                        <div class="grid items-center gap-10 lg:grid-cols-[1.2fr_.8fr] lg:gap-12">

                            <div class="text-center lg:text-right" data-sr="right">

                                <h2 class="section-heading mb-4 text-white">

                                    آماده‌اید

                                    <span class="block bg-gradient-to-l from-bisan-orange to-amber-300 bg-clip-text text-transparent">رشد را شروع کنید؟</span>

                                </h2>

                                <p class="text-base leading-relaxed text-slate-400">

                                    برای مشاوره رایگان، دمو محصولات یا شروع همکاری با تیم فروش بیسان تماس بگیرید. پاسخگویی در اولین فرصت.

                                </p>

                            </div>



                            <div class="flex flex-col items-center gap-4 lg:items-end" data-sr="left">

                                <a href="mailto:{{ $contact['email'] }}" class="btn-primary w-full text-base sm:w-auto">

                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">

                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>

                                    </svg>

                                    {{ $contact['email'] }}

                                </a>

                                <a href="#products" class="btn-outline w-full sm:w-auto">

                                    مشاهده محصولات

                                </a>

                                <span class="text-xs text-slate-500">BISAN Holding · تجارت هوشمند ایرانیان</span>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </section>

    </main>



    <x-footer :contact="$contact" />

</div>

@endsection

