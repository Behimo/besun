@props(['items' => []])

<section id="why" data-section="why" class="landing-section section-why">
    <div class="section-glow section-glow--purple" data-parallax="-0.04"></div>
    <div class="landing-container">
        <x-section-header
            badge="مزیت رقابتی"
            title="چرا"
            highlight="بیسان؟"
            subtitle="زیرساختی یکپارچه برای فروش، عملیات و ارتباط با مشتری — نه فقط یک نرم‌افزار جداگانه"
        />

        <div class="bento-grid" data-sr="scale" data-sr-delay="100">
            @foreach ($items as $item)
                <div
                    class="bento-card bento-card--{{ $item['size'] }} bento-card--{{ $item['accent'] }}"
                    x-data="bouncyCard"
                    @mouseenter="onEnter()"
                    @mouseleave="onLeave()"
                    :style="style"
                    data-sr="up"
                    data-sr-group="bento"
                >
                    <div class="bento-card__inner">
                        <div class="bento-card__visual">
                            @if ($item['visual'] === 'ecosystem')
                                <div class="bento-visual-ecosystem" aria-hidden="true">
                                    <span class="bento-visual-ecosystem__node bento-visual-ecosystem__node--orange"></span>
                                    <span class="bento-visual-ecosystem__node bento-visual-ecosystem__node--purple"></span>
                                    <span class="bento-visual-ecosystem__node bento-visual-ecosystem__node--blue"></span>
                                    <span class="bento-visual-ecosystem__line"></span>
                                </div>
                            @elseif ($item['visual'] === 'speed')
                                <div class="bento-card__visual--speed">
                                    <span class="bento-speed-num">۳ روز</span>
                                    <div class="bento-speed-bar"><div class="bento-speed-fill"></div></div>
                                    <span class="mt-2 text-xs text-slate-500">میانگین راه‌اندازی</span>
                                </div>
                            @elseif ($item['visual'] === 'analytics')
                                <div class="bento-card__visual--layout">
                                    <div class="bento-chart-bars" aria-hidden="true">
                                        <span style="height: 40%"></span>
                                        <span style="height: 70%"></span>
                                        <span style="height: 55%"></span>
                                        <span style="height: 90%"></span>
                                        <span style="height: 65%"></span>
                                    </div>
                                </div>
                            @elseif ($item['visual'] === 'support')
                                <div class="bento-card__visual--orbit">
                                    <div class="bento-orbit">
                                        <div class="bento-orbit__ring"></div>
                                        <div class="bento-orbit__core"></div>
                                    </div>
                                </div>
                            @endif
                        </div>
                        <div class="bento-card__content">
                            <span class="bento-card__tag">{{ $item['tag'] }}</span>
                            <h3 class="bento-card__title">{{ $item['title'] }}</h3>
                            <p class="bento-card__desc">{{ $item['desc'] }}</p>
                        </div>
                        <div class="bento-card__reveal bento-card__reveal--{{ $item['accent'] }}"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
