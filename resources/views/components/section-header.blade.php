@props([
    'badge' => null,
    'title',
    'highlight' => null,
    'subtitle' => null,
    'align' => 'center',
])

@php
    $alignClass = match ($align) {
        'right' => 'text-center lg:text-right',
        'left' => 'text-center lg:text-left',
        default => 'text-center',
    };
@endphp

<header {{ $attributes->merge(['class' => "mb-10 lg:mb-12 {$alignClass}"]) }} data-sr="up">
    @if ($badge)
        <span class="mb-4 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.04] px-4 py-1.5 text-xs font-medium text-bisan-orange-light backdrop-blur-xl">
            <span class="h-1.5 w-1.5 rounded-full bg-bisan-orange shadow-[0_0_10px_rgba(255,102,0,0.8)]"></span>
            {{ $badge }}
        </span>
    @endif

    <h2 class="section-heading mb-4 text-white">
        {{ $title }}
        @if ($highlight)
            <span class="bg-gradient-to-l from-amber-200 via-bisan-orange to-orange-500 bg-clip-text text-transparent">{{ $highlight }}</span>
        @endif
    </h2>

    @if ($subtitle)
        <p class="mx-auto max-w-2xl text-base leading-8 text-slate-400 {{ $align === 'center' ? '' : 'lg:mx-0' }}">
            {{ $subtitle }}
        </p>
    @endif
</header>
