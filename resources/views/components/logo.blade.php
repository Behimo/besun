@props(['size' => 'md'])

@php
    $sizes = [
        'sm' => ['box' => 'h-9 w-9', 'text' => 'text-base', 'sub' => 'text-[9px]', 'name' => 'text-base'],
        'md' => ['box' => 'h-11 w-11', 'text' => 'text-xl', 'sub' => 'text-[10px]', 'name' => 'text-lg'],
        'lg' => ['box' => 'h-14 w-14', 'text' => 'text-2xl', 'sub' => 'text-xs', 'name' => 'text-xl'],
    ];
    $s = $sizes[$size] ?? $sizes['md'];
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-3']) }}>
    <div class="relative flex {{ $s['box'] }} shrink-0 items-center justify-center">
        <div class="absolute inset-0 rotate-45 rounded-lg bg-bisan-orange/25 blur-md"></div>
        <div class="relative flex {{ $s['box'] }} rotate-45 items-center justify-center rounded-lg border border-bisan-orange/40 bg-gradient-to-br from-bisan-orange to-orange-700 shadow-[0_0_20px_rgba(255,102,0,0.4)]">
            <span class="-rotate-45 {{ $s['text'] }} logo-mark font-black text-white">۳</span>
        </div>
    </div>
    <div class="leading-tight">
        <span class="block {{ $s['name'] }} logo-name font-bold tracking-wide text-white">BISAN</span>
        <span class="block {{ $s['sub'] }} logo-sub font-medium text-slate-400">تجارت هوشمند ایرانیان</span>
    </div>
</div>
