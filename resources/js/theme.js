const STORAGE_KEY = 'bisan-theme';

export function applyTheme(mode) {
    const theme = mode === 'light' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', theme);
    document.documentElement.style.colorScheme = theme;
}

export function readStoredTheme() {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored === 'light' || stored === 'dark') {
            return stored;
        }
    } catch (_) {
        /* localStorage unavailable */
    }

    return 'dark';
}

export function initTheme(Alpine) {
    applyTheme(readStoredTheme());

    Alpine.store('theme', {
        mode: document.documentElement.getAttribute('data-theme') || 'dark',

        toggle() {
            this.set(this.mode === 'dark' ? 'light' : 'dark');
        },

        set(mode) {
            this.mode = mode === 'light' ? 'light' : 'dark';

            try {
                localStorage.setItem(STORAGE_KEY, this.mode);
            } catch (_) {
                /* ignore */
            }

            applyTheme(this.mode);
        },
    });
}
