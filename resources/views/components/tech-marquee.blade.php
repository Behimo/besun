@props(['items' => []])

@if (!empty($items))
    <section class="landing-section section-tech py-16 lg:py-20" aria-label="فناوری‌های مورد استفاده">
        <div class="landing-container mb-8 text-center" data-sr="up">
            <p class="text-sm font-medium text-slate-500">فناوری‌هایی که با آن‌ها می‌سازیم</p>
        </div>
        <div class="tech-marquee">
            <div class="tech-marquee__track">
                @foreach (array_merge($items, $items) as $tech)
                    <span class="tech-marquee__item tech-marquee__item--{{ $tech['color'] ?? 'orange' }}">
                        <span class="tech-marquee__dot"></span>
                        {{ $tech['name'] }}
                    </span>
                @endforeach
            </div>
        </div>
    </section>
@endif
