{{-- Magnetic glow cursor — desktop only --}}
<div
    x-data="magicCursor"
    x-cloak
    class="magic-cursor"
    :class="{ 'magic-cursor--visible': visible, 'magic-cursor--hover': hovering }"
    aria-hidden="true"
>
    <div class="magic-cursor__dot" :style="dotStyle"></div>
    <div class="magic-cursor__ring" :style="ringStyle"></div>
</div>
