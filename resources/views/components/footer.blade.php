@props(['contact' => ['email' => 'info@bisan.ir']])

<footer class="site-footer" data-sr="up">
    <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-bisan-orange/8 to-transparent"></div>

    <div class="landing-container relative py-16">
        <div class="grid gap-12 lg:grid-cols-4">
            <div class="lg:col-span-1" data-sr="up" data-sr-group="footer">
                <a href="{{ route('home') }}" class="mb-4 inline-block">
                    <x-logo size="sm" />
                </a>
                <p class="text-sm leading-relaxed text-slate-400">
                    هلدینگ فناوری بیسان — زیرساخت رشد هوشمند برای کسب‌وکارهای ایرانی با محصولات SaaS بومی.
                </p>
                <a href="mailto:{{ $contact['email'] }}" class="mt-4 inline-flex items-center gap-2 text-sm text-bisan-orange-light transition hover:text-bisan-orange">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    {{ $contact['email'] }}
                </a>
            </div>

            @foreach ([
                'محصولات' => [
                    'راهبر CRM' => '#rahbar',
                    'نوژارو' => '#nojaro',
                    'افزونه وردپرس' => '#nojaro-plugin',
                ],
                'شرکت' => [
                    'چرا بیسان؟' => '#why',
                    'درباره ما' => '#about',
                    'آمار و دستاوردها' => '#stats',
                ],
                'ارتباط' => [
                    'درخواست دمو' => '#contact',
                    'همه محصولات' => '#products',
                ],
            ] as $heading => $links)
                <div data-sr="up" data-sr-group="footer">
                    <h4 class="mb-4 text-sm font-semibold text-white">{{ $heading }}</h4>
                    <ul class="space-y-3">
                        @foreach ($links as $label => $href)
                            <li>
                                <a href="{{ $href }}" class="footer-link">{{ $label }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        <div class="mt-12 flex flex-col items-center justify-between gap-4 border-t border-white/10 pt-8 sm:flex-row">
            <p class="text-sm text-slate-500">
                © {{ date('Y') }} BISAN Holding. تمامی حقوق محفوظ است.
            </p>

            <div class="flex items-center gap-4">
                @foreach ([
                    ['label' => 'LinkedIn', 'icon' => 'M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5V5c0-2.761-2.238-5-5-5zm-11 19H5V8h3v11zM6.5 6.732c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zM20 19h-3v-5.604c0-3.368-4-3.113-4 0V19h-3V8h3v1.765c1.396-2.586 7-2.777 7 2.476V19z'],
                    ['label' => 'Telegram', 'icon' => 'M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.446 1.394c-.14.18-.357.295-.6.295-.002 0-.003 0-.005 0l.213-3.054 5.56-5.022c.24-.213-.054-.334-.373-.121l-6.869 4.326-2.96-.924c-.64-.203-.658-.64.135-.954l11.566-4.458c.538-.196 1.006.128.832.941z'],
                    ['label' => 'Instagram', 'icon' => 'M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z'],
                ] as $social)
                    <a href="#" aria-label="{{ $social['label'] }}" class="social-btn">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="{{ $social['icon'] }}"/>
                        </svg>
                    </a>
                @endforeach
            </div>

            <p class="text-sm text-slate-500">
                ساخت و توسعه در ایران
            </p>
        </div>
    </div>
</footer>
