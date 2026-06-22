@props(['items' => []])

@php
    $accents = ['orange', 'purple', 'blue'];
    $shortLabels = ['CRM', 'خدمات', 'وردپرس'];
    $parallax = [
        ['x' => 0.04, 'y' => -0.03],
        ['x' => -0.05, 'y' => 0.04],
        ['x' => 0.03, 'y' => 0.05],
    ];
@endphp

<div
    class="hero-orbit"
    x-data="heroScene"
    @mousemove="onMouseMove($event)"
    @mouseleave="onMouseLeave()"
    aria-hidden="true"
>
    <div class="hero-orbit__halo"></div>

    <div class="hero-orbit__stage" :style="tiltStyle">
        <svg class="hero-orbit__svg" viewBox="0 0 400 400" fill="none">
            <circle cx="200" cy="200" r="118" class="hero-orbit__ring-path" />
            <circle cx="200" cy="200" r="88" class="hero-orbit__ring-path hero-orbit__ring-path--inner" />
            <path d="M200 200 L310 95" class="hero-orbit__connector hero-orbit__connector--1" />
            <path d="M200 200 L72 285" class="hero-orbit__connector hero-orbit__connector--2" />
            <path d="M200 200 L318 300" class="hero-orbit__connector hero-orbit__connector--3" />
        </svg>

        <div class="hero-orbit__pulse hero-orbit__pulse--1"></div>
        <div class="hero-orbit__pulse hero-orbit__pulse--2"></div>

        <div class="hero-orbit__core">
            <div class="hero-orbit__core-glow"></div>
            <div class="hero-orbit__core-inner">
                <span class="hero-orbit__core-num">۳</span>
                <span class="hero-orbit__core-label">محصول فعال · یک اکوسیستم</span>
            </div>
        </div>

        @foreach ($items as $item)
            <div class="hero-orbit__node hero-orbit__node--{{ $accents[$loop->index % 3] }} hero-orbit__node--{{ $loop->iteration }}">
                <div
                    class="hero-orbit__node-card"
                    :style="badgeStyle({{ $parallax[$loop->index % 3]['x'] }}, {{ $parallax[$loop->index % 3]['y'] }})"
                >
                    <span class="hero-orbit__node-tag">{{ $shortLabels[$loop->index % 3] }}</span>
                    <span class="hero-orbit__node-title">{{ $item['title'] }}</span>
                </div>
            </div>
        @endforeach

        <div class="hero-orbit__ring-spin hero-orbit__ring-spin--1">
            <span class="hero-orbit__satellite hero-orbit__satellite--1"></span>
        </div>
        <div class="hero-orbit__ring-spin hero-orbit__ring-spin--2">
            <span class="hero-orbit__satellite hero-orbit__satellite--2"></span>
        </div>
        <div class="hero-orbit__ring-spin hero-orbit__ring-spin--3">
            <span class="hero-orbit__satellite hero-orbit__satellite--3"></span>
        </div>
    </div>
</div>
