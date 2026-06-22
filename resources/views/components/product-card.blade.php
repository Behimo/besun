@props([
    'title',
    'subtitle',
    'description',
    'accent' => 'orange',
    'visual' => 'crm',
    'features' => [],
    'href' => '#',
    'audience' => null,
    'cta' => 'اطلاعات بیشتر',
])

@php
    $accents = [
        'orange' => [
            'border' => 'hover:border-bisan-orange/40',
            'glow' => 'group-hover:shadow-[0_0_40px_rgba(255,102,0,0.15)]',
            'icon_bg' => 'from-bisan-orange/20 to-orange-600/10',
            'icon_text' => 'text-bisan-orange',
            'badge' => 'bg-bisan-orange/10 text-bisan-orange-light border-bisan-orange/20',
            'cta' => 'text-bisan-orange hover:text-bisan-orange-light',
            'gradient' => 'from-bisan-orange/5 via-transparent to-transparent',
        ],
        'purple' => [
            'border' => 'hover:border-bisan-purple/40',
            'glow' => 'group-hover:shadow-[0_0_40px_rgba(168,85,247,0.15)]',
            'icon_bg' => 'from-bisan-purple/20 to-purple-600/10',
            'icon_text' => 'text-bisan-purple',
            'badge' => 'bg-bisan-purple/10 text-purple-300 border-bisan-purple/20',
            'cta' => 'text-bisan-purple hover:text-purple-300',
            'gradient' => 'from-bisan-purple/5 via-transparent to-transparent',
        ],
        'blue' => [
            'border' => 'hover:border-bisan-blue/40',
            'glow' => 'group-hover:shadow-[0_0_40px_rgba(59,130,246,0.15)]',
            'icon_bg' => 'from-bisan-blue/20 to-blue-600/10',
            'icon_text' => 'text-bisan-blue',
            'badge' => 'bg-bisan-blue/10 text-blue-300 border-bisan-blue/20',
            'cta' => 'text-bisan-blue hover:text-blue-300',
            'gradient' => 'from-bisan-blue/5 via-transparent to-transparent',
        ],
    ];

    $theme = $accents[$accent] ?? $accents['orange'];
@endphp

<article
    x-data="cardTilt"
    @mousemove="onMove($event)"
    @mouseleave="onLeave()"
    @mouseenter="onEnter()"
    {{ $attributes->merge(['class' => "card-3d group relative overflow-hidden p-6 transition-shadow duration-500 {$theme['border']} {$theme['glow']}"]) }}
    :style="cardStyle"
>
    <div class="pointer-events-none absolute inset-0 opacity-0 transition-opacity duration-300 group-hover:opacity-100" :style="glowStyle"></div>
    <div class="pointer-events-none absolute inset-0 bg-gradient-to-b {{ $theme['gradient'] }}"></div>

    <div class="card-3d__shine"></div>

    <div class="relative">
        <div class="mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br {{ $theme['icon_bg'] }} ring-1 ring-white/10">
            @if ($visual === 'wordpress')
                <img src="{{ asset('images/brands/wordpress.svg') }}" alt="WordPress" class="h-7 w-7 object-contain">
            @elseif ($visual === 'service')
                <svg class="h-7 w-7 {{ $theme['icon_text'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 4.75h9a2.75 2.75 0 012.75 2.75v9a2.75 2.75 0 01-2.75 2.75h-9a2.75 2.75 0 01-2.75-2.75v-9A2.75 2.75 0 017.5 4.75z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.75 9.25h6.5M8.75 12h3.5M8.75 14.75h5.5"/>
                </svg>
            @else
                <svg class="h-7 w-7 {{ $theme['icon_text'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            @endif
        </div>

        <span class="mb-3 inline-block rounded-full border px-3 py-0.5 text-xs font-medium {{ $theme['badge'] }}">
            {{ $subtitle }}
        </span>

        @if ($audience)
            <p class="mb-2 text-xs text-slate-500">اگر {{ $audience }} هستید</p>
        @endif

        <h3 class="mb-2 text-2xl font-bold text-white">{{ $title }}</h3>
        <p class="mb-5 text-sm leading-relaxed text-slate-400">{{ $description }}</p>

        <div class="product-visual mb-5 overflow-hidden rounded-[1.35rem] border border-white/10 bg-black/40">
            @if ($visual === 'wordpress')
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_70%_20%,rgba(59,130,246,0.35),transparent_35%)]"></div>
                <div class="absolute -left-8 bottom-6 h-28 w-28 rounded-full bg-bisan-blue/20 blur-2xl"></div>
                <div class="absolute right-6 top-6 flex h-14 w-14 items-center justify-center rounded-2xl border border-white/10 bg-white/10 backdrop-blur-md">
                    <img src="{{ asset('images/brands/wordpress.svg') }}" alt="WordPress" class="h-9 w-9 object-contain">
                </div>
                <div class="absolute left-5 top-5 h-8 w-24 rounded-full border border-white/10 bg-white/5"></div>
                <div class="absolute bottom-5 right-5 left-5 rounded-2xl border border-white/10 bg-slate-950/80 p-4 backdrop-blur-xl">
                    <div class="mb-3 flex items-center gap-3">
                        <div class="h-8 w-8 rounded-xl bg-bisan-blue/20"></div>
                        <div class="space-y-1">
                            <div class="h-2 w-24 rounded bg-white/15"></div>
                            <div class="h-2 w-16 rounded bg-white/10"></div>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <div class="h-10 rounded-xl bg-white/5"></div>
                        <div class="h-10 rounded-xl bg-bisan-blue/10"></div>
                        <div class="h-10 rounded-xl bg-white/5"></div>
                    </div>
                </div>
            @elseif ($visual === 'service')
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_15%_20%,rgba(168,85,247,0.35),transparent_30%)]"></div>
                <div class="absolute right-6 top-6 h-16 w-24 rounded-2xl border border-white/10 bg-white/8 backdrop-blur-md"></div>
                <div class="absolute left-5 top-5 flex gap-2">
                    <div class="h-2.5 w-2.5 rounded-full bg-bisan-purple"></div>
                    <div class="h-2.5 w-2.5 rounded-full bg-white/20"></div>
                    <div class="h-2.5 w-2.5 rounded-full bg-white/20"></div>
                </div>
                <div class="absolute bottom-5 left-5 right-5 grid grid-cols-[1.2fr_.8fr] gap-3">
                    <div class="rounded-2xl border border-white/10 bg-slate-950/75 p-4">
                        <div class="mb-3 flex items-center justify-between">
                            <div class="h-2 w-20 rounded bg-white/15"></div>
                            <div class="h-6 w-6 rounded-lg bg-bisan-purple/20"></div>
                        </div>
                        <div class="space-y-2">
                            <div class="h-10 rounded-xl bg-white/5"></div>
                            <div class="h-16 rounded-xl bg-gradient-to-br from-bisan-purple/25 to-transparent"></div>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-3">
                            <div class="h-14 rounded-xl bg-bisan-purple/15"></div>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-3">
                            <div class="h-8 rounded-xl bg-white/8"></div>
                        </div>
                    </div>
                </div>
            @else
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_80%_15%,rgba(255,102,0,0.35),transparent_30%)]"></div>
                <div class="absolute left-5 top-5 flex items-center gap-2 rounded-full border border-white/10 bg-black/30 px-3 py-1 text-[11px] text-slate-300">
                    <span class="h-2 w-2 rounded-full bg-bisan-orange"></span>
                    Sales Analytics
                </div>
                <div class="absolute right-5 top-5 h-10 w-10 rounded-2xl border border-white/10 bg-white/8"></div>
                <div class="absolute inset-x-5 bottom-5 rounded-[1.25rem] border border-white/10 bg-slate-950/75 p-4 backdrop-blur-xl">
                    <div class="mb-3 flex items-end gap-2">
                        <div class="h-12 w-1/5 rounded-t-xl bg-bisan-orange/20"></div>
                        <div class="h-20 w-1/5 rounded-t-xl bg-bisan-orange/35"></div>
                        <div class="h-14 w-1/5 rounded-t-xl bg-bisan-orange/25"></div>
                        <div class="h-24 w-1/5 rounded-t-xl bg-bisan-orange/50"></div>
                        <div class="h-16 w-1/5 rounded-t-xl bg-bisan-orange/30"></div>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <div class="h-8 rounded-xl bg-white/5"></div>
                        <div class="h-8 rounded-xl bg-white/10"></div>
                        <div class="h-8 rounded-xl bg-white/5"></div>
                    </div>
                </div>
            @endif
        </div>

        @if (count($features))
            <ul class="mb-6 space-y-2">
                @foreach ($features as $feature)
                    <li class="flex items-center gap-2 text-sm text-slate-400">
                        <svg class="h-4 w-4 shrink-0 {{ $theme['icon_text'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        {{ $feature }}
                    </li>
                @endforeach
            </ul>
        @endif

        <a href="{{ $href }}" class="inline-flex items-center gap-2 text-sm font-semibold transition {{ $theme['cta'] }}">
            <span>{{ $cta }}</span>
            <svg class="h-4 w-4 rotate-180 transition group-hover:-translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
            </svg>
        </a>
    </div>
</article>
