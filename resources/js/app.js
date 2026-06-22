import Alpine from 'alpinejs';
import { registerMagicAnimations } from './landing/magic-animations';

window.Alpine = Alpine;

registerMagicAnimations(Alpine);

Alpine.store('landing', {
    activeSection: 'hero',
    scrollProgress: 0,
    scrollY: 0,
});

Alpine.data('landingExperience', () => ({
    init() {
        this.onScroll = this.onScroll.bind(this);
        window.addEventListener('scroll', this.onScroll, { passive: true });
        this.onScroll();
        this.initScrollReveal();
        this.initSections();
    },

    onScroll() {
        const doc = document.documentElement;
        const max = doc.scrollHeight - doc.clientHeight;
        const progress = max > 0 ? window.scrollY / max : 0;

        Alpine.store('landing').scrollY = window.scrollY;
        Alpine.store('landing').scrollProgress = progress;
        document.documentElement.style.setProperty('--scroll', String(progress));

        this.$el.querySelectorAll('[data-parallax]').forEach((el) => {
            const speed = parseFloat(el.dataset.parallax) || 0.08;
            const rect = el.getBoundingClientRect();
            const offset = (rect.top + rect.height * 0.5 - window.innerHeight * 0.5) * speed;
            el.style.transform = `translate3d(0, ${offset}px, 0)`;
        });
    },

    initScrollReveal() {
        const staggerGroups = new Map();

        this.$el.querySelectorAll('[data-sr]').forEach((el) => {
            const group = el.dataset.srGroup;
            if (group) {
                if (!staggerGroups.has(group)) staggerGroups.set(group, []);
                staggerGroups.get(group).push(el);
            }
        });

        staggerGroups.forEach((elements) => {
            elements.forEach((el, index) => {
                if (!el.dataset.srDelay) {
                    el.dataset.srDelay = String(index * 90);
                }
            });
        });

        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;

                    const el = entry.target;
                    const delay = parseInt(el.dataset.srDelay || '0', 10);

                    setTimeout(() => {
                        el.classList.add('sr-visible');
                    }, delay);

                    observer.unobserve(el);
                });
            },
            { threshold: 0.12, rootMargin: '0px 0px -6% 0px' },
        );

        this.$el.querySelectorAll('[data-sr]').forEach((el) => observer.observe(el));
    },

    initSections() {
        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        Alpine.store('landing').activeSection = entry.target.dataset.section;
                    }
                });
            },
            { threshold: 0.4, rootMargin: '-20% 0px -20% 0px' },
        );

        document.querySelectorAll('[data-section]').forEach((section) => observer.observe(section));
    },
}));

Alpine.data('scrollReveal', () => ({
    init() {
        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    }
                });
            },
            { threshold: 0.15, rootMargin: '0px 0px -40px 0px' },
        );

        this.$el.querySelectorAll('.reveal').forEach((el) => observer.observe(el));
    },
}));

Alpine.data('counter', (target) => ({
    display: target,
    init() {
        const toAsciiDigits = (value) => String(value).replace(/[۰-۹]/g, (d) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d)));

        if (String(target).includes('/')) {
            this.display = target;
            return;
        }

        const normalized = toAsciiDigits(target);
        const numeric = parseInt(normalized.replace(/\D/g, ''), 10);
        const hasPercent = normalized.includes('%');
        const prefix = normalized.startsWith('+') ? '+' : '';

        if (Number.isNaN(numeric)) {
            this.display = target;
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                if (!entries[0].isIntersecting) return;

                const duration = 1800;
                const start = performance.now();

                const tick = (now) => {
                    const progress = Math.min((now - start) / duration, 1);
                    const eased = 1 - Math.pow(1 - progress, 3);
                    const current = Math.round(numeric * eased);

                    this.display = prefix + current + (hasPercent ? '%' : '');

                    if (progress < 1) {
                        requestAnimationFrame(tick);
                    } else {
                        this.display = target;
                    }
                };

                requestAnimationFrame(tick);
                observer.disconnect();
            },
            { threshold: 0.5 },
        );

        observer.observe(this.$el);
    },
}));

Alpine.data('heroScene', () => ({
    tiltX: 0,
    tiltY: 0,
    parallaxX: 0,
    parallaxY: 0,

    get tiltStyle() {
        return `transform: perspective(1400px) rotateX(${this.tiltX}deg) rotateY(${this.tiltY}deg)`;
    },

    badgeStyle(multX, multY) {
        const x = this.parallaxX * multX * 40;
        const y = this.parallaxY * multY * 40;
        return `transform: translate3d(${x}px, ${y}px, 0)`;
    },

    onMouseMove(event) {
        const rect = this.$el.getBoundingClientRect();
        const x = (event.clientX - rect.left) / rect.width - 0.5;
        const y = (event.clientY - rect.top) / rect.height - 0.5;

        this.tiltX = -y * 14;
        this.tiltY = x * 14;
        this.parallaxX = x;
        this.parallaxY = y;
    },

    onMouseLeave() {
        this.tiltX = 0;
        this.tiltY = 0;
        this.parallaxX = 0;
        this.parallaxY = 0;
    },
}));

Alpine.data('cardTilt', () => ({
    rotateX: 0,
    rotateY: 0,
    glowX: 50,
    glowY: 50,
    scale: 1,
    bounceRotate: 0,

    get cardStyle() {
        return `transform: perspective(1000px) rotateX(${this.rotateX}deg) rotateY(${this.rotateY}deg) scale(${this.scale}) rotate(${this.bounceRotate}deg) translateZ(0);`;
    },

    get glowStyle() {
        return `background: radial-gradient(circle at ${this.glowX}% ${this.glowY}%, rgba(255,102,0,0.15), transparent 55%);`;
    },

    onEnter() {
        this.scale = 0.97;
        this.bounceRotate = -0.6;
    },

    onMove(event) {
        const rect = this.$el.getBoundingClientRect();
        const x = (event.clientX - rect.left) / rect.width;
        const y = (event.clientY - rect.top) / rect.height;

        this.rotateX = (0.5 - y) * 10;
        this.rotateY = (x - 0.5) * 10;
        this.glowX = x * 100;
        this.glowY = y * 100;
    },

    onLeave() {
        this.rotateX = 0;
        this.rotateY = 0;
        this.glowX = 50;
        this.glowY = 50;
        this.scale = 1;
        this.bounceRotate = 0;
    },
}));

Alpine.start();
