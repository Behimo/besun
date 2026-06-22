@extends('layouts.site')

@section('page')
    <x-hero :hero="$hero ?? []" :hero-pills="$heroPills" />

    @if (!empty($trustBadges))
        <section class="trust-bar border-y border-white/5 bg-white/[0.02] py-4">
            <div class="landing-container">
                <div class="flex flex-wrap items-center justify-center gap-6 lg:gap-12">
                    @foreach ($trustBadges as $badge)
                        <div class="trust-badge flex items-center gap-2 text-sm text-slate-300">
                            <span class="trust-badge__icon flex h-8 w-8 items-center justify-center rounded-full bg-bisan-orange/10 text-bisan-orange">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                            </span>
                            <span>{{ $badge['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section id="products" class="landing-section section-products">
        <div class="landing-container">
            <x-section-header
                badge="محصولات ما"
                title="کدام یک"
                highlight="مناسب شماست؟"
                subtitle="۳ راه‌حل آماده — امتحان‌شده روی بیش از ۱۲۰۰ کسب‌وکار."
            />
            <div class="grid gap-6 lg:grid-cols-3 lg:gap-8 mt-10">
                @foreach ($products as $product)
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
                    />
                @endforeach
            </div>
            <div class="mt-10 text-center">
                <a href="{{ route('products.index') }}" class="btn-outline">مشاهده همه محصولات</a>
            </div>
        </div>
    </section>

    <section class="landing-section section-why-preview">
        <div class="landing-container">
            <x-section-header
                badge="چرا بیسان؟"
                title="۴ دلیل"
                highlight="انتخاب ما"
                subtitle="بیش از ۱۲۰۰ کسب‌وکار به ما اعتماد کرده‌اند."
            />
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mt-10">
                @foreach ($whyBisan as $item)
                    <div class="glass-card-3d p-6">
                        <span class="text-xs font-medium text-bisan-orange">{{ $item['tag'] ?? '' }}</span>
                        <h3 class="mt-2 font-semibold text-white">{{ $item['title'] }}</h3>
                        <p class="mt-2 text-sm leading-7 text-slate-400">{{ $item['desc'] }}</p>
                    </div>
                @endforeach
            </div>
            <div class="mt-8 text-center">
                <a href="{{ route('why-bisan') }}" class="btn-outline">بیشتر بدانید</a>
            </div>
        </div>
    </section>

    @if (!empty($testimonials))
        <section class="landing-section section-testimonials">
            <div class="landing-container">
                <x-section-header
                    badge="نظر مشتریان"
                    title="آنچه"
                    highlight="می‌گویند"
                    subtitle="تجربه واقعی کسب‌وکارهایی که با بیسان رشد کرده‌اند."
                />
                <div class="grid gap-6 md:grid-cols-3 mt-10">
                    @foreach ($testimonials as $testimonial)
                        <div class="testimonial-card glass-card-3d p-6 flex flex-col">
                            <div class="flex gap-1 mb-4 text-bisan-orange">
                                @for ($i = 0; $i < ($testimonial['rating'] ?? 5); $i++)
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                @endfor
                            </div>
                            <p class="text-sm leading-8 text-slate-300 flex-1">«{{ $testimonial['text'] }}»</p>
                            <div class="mt-6 pt-4 border-t border-white/5">
                                <p class="font-semibold text-white">{{ $testimonial['name'] }}</p>
                                <p class="text-xs text-slate-500 mt-1">{{ $testimonial['role'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="landing-section section-stats">
        <div class="landing-container">
            <x-section-header badge="اعتماد شما" title="این‌ها" highlight="حرف ماست" />
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4 mt-10">
                @foreach ($stats as $stat)
                    <div class="glass-card-3d p-6 text-center stat-card" x-data="counter('{{ $stat['value'] }}')">
                        <p class="mb-1 text-3xl font-extrabold text-white" x-text="display">{{ $stat['value'] }}</p>
                        <p class="text-sm font-medium text-slate-300">{{ $stat['label'] }}</p>
                        @if (!empty($stat['hint']))
                            <p class="mt-1 text-xs text-slate-500">{{ $stat['hint'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    @if (isset($latestPosts) && $latestPosts->isNotEmpty())
        <section class="landing-section section-blog-preview">
            <div class="landing-container">
                <x-section-header
                    badge="بلاگ"
                    title="آخرین"
                    highlight="مقالات"
                    subtitle="راهنماها و نکات کاربردی برای رشد کسب‌وکار."
                />
                <div class="grid gap-6 md:grid-cols-3 mt-10">
                    @foreach ($latestPosts as $post)
                        <a href="{{ route('blog.show', $post->slug) }}" class="blog-preview-card glass-card-3d overflow-hidden group block">
                            @if ($post->featured_image)
                                <div class="aspect-video overflow-hidden">
                                    <img src="{{ $post->featured_image }}" alt="{{ $post->title }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                                </div>
                            @else
                                <div class="aspect-video bg-gradient-to-br from-bisan-orange/20 to-bisan-purple/20 flex items-center justify-center">
                                    <span class="text-4xl font-bold text-white/20">B</span>
                                </div>
                            @endif
                            <div class="p-5">
                                @if ($post->category)
                                    <span class="text-xs text-bisan-orange">{{ $post->category->name }}</span>
                                @endif
                                <h3 class="mt-2 font-semibold text-white group-hover:text-bisan-orange-light transition">{{ $post->title }}</h3>
                                <p class="mt-2 text-sm text-slate-400 line-clamp-2">{{ $post->excerpt }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
                <div class="mt-8 text-center">
                    <a href="{{ route('blog.index') }}" class="btn-outline">مشاهده همه مقالات</a>
                </div>
            </div>
        </section>
    @endif

    @if (!empty($partners))
        <section class="landing-section section-partners">
            <div class="landing-container text-center">
                <p class="text-sm text-slate-500 mb-6">همکاری با برندهای معتبر</p>
                <div class="partners-marquee flex flex-wrap items-center justify-center gap-8 lg:gap-14">
                    @foreach ($partners as $partner)
                        <span class="partner-logo text-xl font-bold text-white/25 hover:text-white/70 transition tracking-wide">{{ $partner }}</span>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="landing-section section-cta">
        <div class="landing-container">
            <div class="cta-banner glass-card-3d relative overflow-hidden rounded-3xl p-10 lg:p-14 text-center">
                <div class="cta-banner__glow" aria-hidden="true"></div>
                <h2 class="section-heading text-white mb-4 relative z-10">{{ $cta['title'] ?? 'آماده شروع هستید؟' }}</h2>
                <p class="text-slate-400 mb-8 max-w-xl mx-auto relative z-10">{{ $cta['subtitle'] ?? 'محصول آماده یا پروژه اختصاصی — با یک تماس راه را پیدا می‌کنیم.' }}</p>
                <div class="flex flex-wrap justify-center gap-4 relative z-10">
                    <a href="{{ route('contact') }}" class="btn-primary">مشاوره رایگان</a>
                    <a href="{{ route('services') }}" class="btn-outline">پروژه اختصاصی</a>
                </div>
            </div>
        </div>
    </section>
@endsection
