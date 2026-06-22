{{-- Interactive particle grid — inspired by 21st.dev ParticleHero --}}
<div
    class="particle-field"
    x-data="particleField(14)"
    @mousemove="onPointerMove($event)"
    @touchmove.passive="onPointerMove($event)"
    aria-hidden="true"
>
    <div class="particle-field__glow particle-field__glow--orange"></div>
    <div class="particle-field__glow particle-field__glow--purple"></div>
    <div class="particle-field__glow particle-field__glow--blue"></div>
    <div class="particle-field__center">
        <div x-ref="grid" class="particle-field__grid"></div>
    </div>
    <div class="particle-field__vignette"></div>
</div>
