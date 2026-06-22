<header
    class="site-header"
    x-data="{
        scrolled: false,
        init() {
            const onScroll = () => { this.scrolled = window.scrollY > 24; };
            onScroll();
            window.addEventListener('scroll', onScroll, { passive: true });
        }
    }"
    :class="{ 'site-header--scrolled': scrolled }"
>
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <nav class="flex h-16 items-center justify-between lg:h-20" aria-label="ناوبری اصلی">
            <a href="{{ route('home') }}" class="group shrink-0">
                <x-logo size="sm" />
            </a>

            <div class="hidden items-center gap-1 lg:flex">
                @foreach (($navLinks ?? []) as $link)
                    @php
                        $href = isset($link['route'])
                            ? (isset($link['params']) ? route($link['route'], $link['params']) : route($link['route']))
                            : ($link['href'] ?? '#');
                        $isActive = isset($link['route']) && request()->routeIs($link['route'].(isset($link['params']) ? '' : ''));
                        if (isset($link['params']['slug'])) {
                            $isActive = request()->routeIs('pages.show') && request()->route('slug') === $link['params']['slug'];
                        }
                    @endphp
                    <a
                        href="{{ $href }}"
                        class="nav-link {{ $isActive ? 'nav-link--active' : '' }}"
                    >
                        <span>{{ $link['label'] }}</span>
                        <span class="nav-link__dot"></span>
                    </a>
                @endforeach
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('contact') }}" class="btn-demo hidden sm:inline-flex" data-magnetic>
                    <span>مشاوره رایگان</span>
                    <svg class="h-4 w-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                    </svg>
                </a>

                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-xl border border-white/10 p-2 text-white lg:hidden"
                    @click="mobileMenu = !mobileMenu"
                    :aria-expanded="mobileMenu"
                    aria-label="منوی موبایل"
                >
                    <svg x-show="!mobileMenu" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <svg x-show="mobileMenu" x-cloak class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </nav>

        <div x-show="mobileMenu" x-cloak x-transition class="glass-nav mb-4 rounded-2xl p-4 lg:hidden">
            <div class="flex flex-col gap-1">
                @foreach (($navLinks ?? []) as $link)
                    @php
                        $href = isset($link['route'])
                            ? (isset($link['params']) ? route($link['route'], $link['params']) : route($link['route']))
                            : ($link['href'] ?? '#');
                    @endphp
                    <a href="{{ $href }}" @click="mobileMenu = false" class="rounded-xl px-4 py-3 text-sm text-slate-300 hover:bg-white/5 hover:text-white">
                        {{ $link['label'] }}
                    </a>
                @endforeach
                <a href="{{ route('contact') }}" @click="mobileMenu = false" class="btn-demo mt-2 w-full justify-center">مشاوره رایگان</a>
            </div>
        </div>
    </div>
</header>
