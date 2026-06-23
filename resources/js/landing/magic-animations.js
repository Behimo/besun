/**
 * Magic animations — ported from 21st.dev MCP inspiration
 * Particle field, text rotate, magnetic cursor, bouncy cards
 */

export function registerMagicAnimations(Alpine) {
    Alpine.data('particleField', (rows = 12) => ({
        cursor: { x: 0, y: 0 },
        autoMode: true,
        staticAnim: false,
        staticCursor: { x: 0, y: 0 },
        startTime: Date.now(),
        lastMove: Date.now(),
        autoTimer: null,
        staticTimer: null,
        raf: null,

        init() {
            this.buildParticles();
            this.animate();
            window.addEventListener('resize', () => this.buildParticles());
        },

        destroy() {
            if (this.raf) cancelAnimationFrame(this.raf);
            clearTimeout(this.autoTimer);
            clearTimeout(this.staticTimer);
        },

        buildParticles() {
            const container = this.$refs.grid;
            if (!container) return;

            container.innerHTML = '';
            const center = Math.floor(rows / 2);

            for (let i = 0; i < rows * rows; i++) {
                const row = Math.floor(i / rows);
                const col = i % rows;
                const dist = Math.hypot(row - center, col - center);
                const scale = Math.max(0.08, 1.15 - dist * 0.11);
                const opacity = Math.max(0.04, 0.9 - dist * 0.09);
                const lightness = Math.max(18, 72 - dist * 5);
                const glow = Math.max(0.4, 5 - dist * 0.4);

                const el = document.createElement('span');
                el.className = 'magic-particle';
                el.dataset.row = row;
                el.dataset.col = col;
                el.dataset.dist = dist;
                el.dataset.scale = scale;
                el.style.cssText = `
                    left: ${col * 1.6}rem;
                    top: ${row * 1.6}rem;
                    transform: scale(${scale});
                    opacity: ${opacity};
                    background: hsl(24, 95%, ${lightness}%);
                    box-shadow: 0 0 ${glow * 0.25}rem 0 hsl(24, 95%, 55%);
                    z-index: ${Math.round(rows * rows - dist * 4)};
                `;
                container.appendChild(el);
            }

            container.style.width = `${rows * 1.6}rem`;
            container.style.height = `${rows * 1.6}rem`;
        },

        animate() {
            const tick = () => {
                const t = (Date.now() - this.startTime) * 0.001;

                if (this.autoMode) {
                    this.cursor = {
                        x: Math.sin(t * 0.3) * 180 + Math.sin(t * 0.17) * 90,
                        y: Math.cos(t * 0.2) * 130 + Math.cos(t * 0.23) * 70,
                    };
                } else if (this.staticAnim) {
                    const since = Date.now() - this.lastMove;
                    if (since > 200) {
                        const strength = Math.min((since - 200) / 1000, 1);
                        this.cursor = {
                            x: this.staticCursor.x + Math.sin(t * 1.5) * 18 * strength,
                            y: this.staticCursor.y + Math.cos(t * 1.2) * 14 * strength,
                        };
                    }
                }

                this.updateParticles();
                this.raf = requestAnimationFrame(tick);
            };

            this.raf = requestAnimationFrame(tick);
        },

        updateParticles() {
            const container = this.$refs.grid;
            if (!container) return;

            container.querySelectorAll('.magic-particle').forEach((el) => {
                const dist = parseFloat(el.dataset.dist);
                const scale = parseFloat(el.dataset.scale);
                const damp = Math.max(0.25, 1 - dist * 0.07);
                const delay = dist * 6;
                const dur = 100 + dist * 18;

                setTimeout(() => {
                    el.style.transform = `translate(${this.cursor.x * damp}px, ${this.cursor.y * damp}px) scale(${scale})`;
                    el.style.transition = `transform ${dur}ms cubic-bezier(0.25, 0.46, 0.45, 0.94)`;
                }, delay);
            });
        },

        onPointerMove(e) {
            const pt = e.touches ? e.touches[0] : e;
            const cx = window.innerWidth / 2;
            const cy = window.innerHeight / 2;

            this.cursor = {
                x: (pt.clientX - cx) * 0.7,
                y: (pt.clientY - cy) * 0.7,
            };
            this.staticCursor = { ...this.cursor };
            this.autoMode = false;
            this.staticAnim = false;
            this.lastMove = Date.now();

            clearTimeout(this.staticTimer);
            this.staticTimer = setTimeout(() => { this.staticAnim = true; }, 500);

            clearTimeout(this.autoTimer);
            this.autoTimer = setTimeout(() => {
                if (Date.now() - this.lastMove >= 3500) {
                    this.autoMode = true;
                    this.staticAnim = false;
                    this.startTime = Date.now();
                }
            }, 4000);
        },
    }));

    Alpine.data('textRotate', (words = [], interval = 2800) => ({
        index: 0,
        current: '',
        exiting: false,
        timer: null,

        init() {
            if (!words.length) return;
            this.current = words[0];
            this.timer = setInterval(() => this.next(), interval);
        },

        destroy() {
            clearInterval(this.timer);
        },

        next() {
            this.exiting = true;
            setTimeout(() => {
                this.index = (this.index + 1) % words.length;
                this.current = words[this.index];
                this.exiting = false;
            }, 400);
        },

        get chars() {
            return [...this.current];
        },
    }));

    Alpine.data('magicCursor', () => ({
        x: -100,
        y: -100,
        visible: false,
        hovering: false,
        ringScale: 1,
        active: false,

        init() {
            if (window.matchMedia('(pointer: coarse)').matches) return;

            this.onMove = (e) => {
                this.x = e.clientX;
                this.y = e.clientY;
                this.visible = true;
            };

            this.onLeave = () => { this.visible = false; };

            this.onHoverEnter = () => { this.hovering = true; this.ringScale = 1.8; };
            this.onHoverLeave = () => { this.hovering = false; this.ringScale = 1; };

            document.querySelectorAll('a, button, [data-magnetic]').forEach((el) => {
                el.addEventListener('mouseenter', this.onHoverEnter);
                el.addEventListener('mouseleave', this.onHoverLeave);
            });

            this.$watch('$store.theme.mode', (mode) => this.syncTheme(mode));
            this.syncTheme(this.$store.theme.mode);
        },

        syncTheme(mode) {
            if (mode === 'light') {
                this.deactivate();
            } else {
                this.activate();
            }
        },

        activate() {
            if (this.active) return;
            this.active = true;
            document.body.classList.add('has-magic-cursor');
            window.addEventListener('mousemove', this.onMove);
            document.addEventListener('mouseleave', this.onLeave);
        },

        deactivate() {
            if (!this.active) return;
            this.active = false;
            this.visible = false;
            this.hovering = false;
            this.ringScale = 1;
            document.body.classList.remove('has-magic-cursor');
            window.removeEventListener('mousemove', this.onMove);
            document.removeEventListener('mouseleave', this.onLeave);
        },

        destroy() {
            this.deactivate();
            document.querySelectorAll('a, button, [data-magnetic]').forEach((el) => {
                el.removeEventListener('mouseenter', this.onHoverEnter);
                el.removeEventListener('mouseleave', this.onHoverLeave);
            });
        },

        get dotStyle() {
            return `transform: translate(${this.x}px, ${this.y}px) translate(-50%, -50%);`;
        },

        get ringStyle() {
            const s = this.ringScale;
            return `transform: translate(${this.x}px, ${this.y}px) translate(-50%, -50%) scale(${s});`;
        },
    }));

    Alpine.data('magneticBtn', () => ({
        tx: 0,
        ty: 0,

        onMove(e) {
            const rect = this.$el.getBoundingClientRect();
            const x = (e.clientX - rect.left) / rect.width - 0.5;
            const y = (e.clientY - rect.top) / rect.height - 0.5;
            this.tx = x * 14;
            this.ty = y * 10;
        },

        onLeave() {
            this.tx = 0;
            this.ty = 0;
        },

        get style() {
            return `transform: translate(${this.tx}px, ${this.ty}px);`;
        },
    }));

    Alpine.data('bouncyCard', () => ({
        hover: false,

        onEnter() { this.hover = true; },
        onLeave() { this.hover = false; },

        get style() {
            if (!this.hover) return 'transform: scale(1) rotate(0deg);';
            return 'transform: scale(0.96) rotate(-0.8deg);';
        },
    }));

    Alpine.data('horizontalScroll', () => ({
        init() {
            const track = this.$refs.track;
            if (!track) return;

            let isDown = false;
            let startX = 0;
            let scrollLeft = 0;

            track.addEventListener('mousedown', (e) => {
                isDown = true;
                startX = e.pageX - track.offsetLeft;
                scrollLeft = track.scrollLeft;
                track.classList.add('is-dragging');
            });

            track.addEventListener('mouseleave', () => { isDown = false; track.classList.remove('is-dragging'); });
            track.addEventListener('mouseup', () => { isDown = false; track.classList.remove('is-dragging'); });

            track.addEventListener('mousemove', (e) => {
                if (!isDown) return;
                e.preventDefault();
                const x = e.pageX - track.offsetLeft;
                track.scrollLeft = scrollLeft - (x - startX) * 1.5;
            });
        },
    }));

    Alpine.data('blobMorph', () => ({
        init() {
            let t = 0;
            const blobs = this.$el.querySelectorAll('.morph-blob');

            const tick = () => {
                t += 0.008;
                blobs.forEach((blob, i) => {
                    const phase = t + i * 1.2;
                    const x = Math.sin(phase) * 8 + Math.cos(phase * 0.7) * 5;
                    const y = Math.cos(phase * 0.8) * 6 + Math.sin(phase * 1.1) * 4;
                    const s = 1 + Math.sin(phase * 0.5) * 0.08;
                    blob.style.transform = `translate(${x}%, ${y}%) scale(${s})`;
                });
                requestAnimationFrame(tick);
            };

            requestAnimationFrame(tick);
        },
    }));
}
