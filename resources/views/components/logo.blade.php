@props(['size' => 'md', 'variant' => 'full'])

@php
    $sizes = [
        'sm' => ['full' => 'h-10', 'icon' => 'h-9 w-9'],
        'md' => ['full' => 'h-12', 'icon' => 'h-11 w-11'],
        'lg' => ['full' => 'h-16', 'icon' => 'h-14 w-14'],
    ];
    $s = $sizes[$size] ?? $sizes['md'];
    $isIcon = $variant === 'icon';
    $src = asset($isIcon ? 'images/bisan/logo-icon.png' : 'images/bisan/logo-full.png');
    $classes = trim(($isIcon ? $s['icon'] : $s['full'].' w-auto').' object-contain besun-logo besun-logo--'.$variant);
@endphp

<img
    src="{{ $src }}"
    alt="بیسان — تجارت هوشمند ایرانیان"
    {{ $attributes->merge(['class' => $classes]) }}
    decoding="async"
/>
