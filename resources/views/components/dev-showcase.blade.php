@props(['capabilities' => [], 'devStats' => []])

<section id="team" data-section="team" class="landing-section section-dev-showcase">
    <div class="section-glow section-glow--blue" data-parallax="0.04"></div>
    <div class="landing-container">
        <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
            <div>
                <header class="mb-10 text-center lg:text-right" data-sr="up">
                    <span class="mb-4 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.04] px-4 py-1.5 text-xs font-medium text-bisan-orange-light backdrop-blur-xl">
                        <span class="h-1.5 w-1.5 rounded-full bg-bisan-orange shadow-[0_0_10px_rgba(255,102,0,0.8)]"></span>
                        قدرت تیم مهندسی
                    </span>
                    <h2 class="section-heading mb-4 text-white">
                        برنامه‌نویسی
                        <span class="bg-gradient-to-l from-amber-200 via-bisan-orange to-orange-500 bg-clip-text text-transparent">در سطح حرفه‌ای</span>
                    </h2>
                    <p class="text-base leading-8 text-slate-400">
                        ما فقط نرم‌افزار نمی‌فروشیم — هر روز روی محصولات خودمان کد می‌زنیم.
                        معماری تمیز، API قدرتمند و تیمی که پروژه اختصاصی شما را هم با همان استاندارد می‌سازد.
                    </p>
                </header>

                <div class="grid gap-3 sm:grid-cols-2" data-sr-group="dev-cap">
                    @foreach ($capabilities as $cap)
                        <div class="dev-cap-card" data-sr="up">
                            <div class="dev-cap-card__icon">
                                <svg class="h-5 w-5 text-bisan-orange" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $cap['icon'] }}"/>
                                </svg>
                            </div>
                            <h3 class="dev-cap-card__title">{{ $cap['title'] }}</h3>
                            <p class="dev-cap-card__desc">{{ $cap['desc'] }}</p>
                        </div>
                    @endforeach
                </div>

                @if (!empty($devStats))
                    <div class="dev-stats-row mt-8" data-sr="up" data-sr-delay="200">
                        @foreach ($devStats as $stat)
                            <div class="dev-stat">
                                <span class="dev-stat__value">{{ $stat['value'] }}</span>
                                <span class="dev-stat__label">{{ $stat['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-8 flex flex-wrap justify-center gap-3 lg:justify-start" data-sr="up" data-sr-delay="280">
                    <a href="{{ route('services') }}" class="btn-primary">پروژه اختصاصی شما</a>
                    <a href="{{ route('about') }}" class="btn-outline">درباره تیم ما</a>
                </div>
            </div>

            <x-code-terminal />
        </div>
    </div>
</section>
