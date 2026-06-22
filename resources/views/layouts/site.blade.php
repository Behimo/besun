@extends('layouts.app')

@section('content')
<div class="landing" x-data="landingExperience">
    <div class="scroll-progress" aria-hidden="true">
        <div class="scroll-progress__bar" :style="`transform: scaleX(${ $store.landing.scrollProgress })`"></div>
    </div>

    <div class="ambient-layer" aria-hidden="true">
        <div class="ambient-orb ambient-orb--orange" data-parallax="0.12"></div>
        <div class="ambient-orb ambient-orb--purple" data-parallax="-0.08"></div>
        <div class="ambient-orb ambient-orb--blue" data-parallax="0.06"></div>
        <div class="ambient-grid"></div>
    </div>

    <x-navbar :nav-links="$navLinks" />

    <main class="relative z-10">
        @yield('page')
    </main>

    <x-footer :contact="$contact" />
</div>
@endsection
