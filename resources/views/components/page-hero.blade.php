@props(['title' => '', 'highlight' => null, 'subtitle' => null, 'badge' => null])

<section class="page-hero landing-section pt-28 pb-12">
    <div class="landing-container">
        @if ($badge)
            <span class="section-badge mb-4 inline-block">{{ $badge }}</span>
        @endif
        <h1 class="section-heading text-white">
            {{ $title }}
            @if ($highlight)
                <span class="block bg-gradient-to-l from-bisan-orange to-amber-300 bg-clip-text text-transparent">{{ $highlight }}</span>
            @endif
        </h1>
        @if ($subtitle)
            <p class="mt-4 max-w-2xl text-lg leading-relaxed text-slate-400">{{ $subtitle }}</p>
        @endif
        {{ $slot ?? '' }}
    </div>
</section>
