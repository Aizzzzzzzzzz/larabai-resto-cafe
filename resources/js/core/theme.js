/* Light / dark switch — same behaviour as the mockup's BBTheme.
   The saved choice wins; otherwise follow the device. The kitchen display keeps its own
   setting and starts dark. The first paint is handled by the inline script in layouts/base,
   so there is no flash of the wrong theme. */
const isKitchen = () => document.body.dataset.module === 'kitchen';
const key = () => (isKitchen() ? 'bbThemeKitchen' : 'bbTheme');

function saved() {
    try { return localStorage.getItem(key()); } catch (e) { return null; }
}

function system() {
    return window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

export function currentTheme() {
    return saved() || (isKitchen() ? 'dark' : system());
}

export function applyTheme() {
    const dark = currentTheme() === 'dark';
    document.documentElement.dataset.theme = dark ? 'dark' : 'light';
    document.querySelectorAll('[data-theme-toggle]').forEach((b) => {
        b.setAttribute('aria-pressed', String(dark));
        b.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
        b.title = dark ? 'Light mode' : 'Dark mode';
    });
}

export function initTheme() {
    applyTheme();
    document.addEventListener('click', (e) => {
        if (!e.target.closest('[data-theme-toggle]')) return;
        try { localStorage.setItem(key(), currentTheme() === 'dark' ? 'light' : 'dark'); } catch (err) { /* storage blocked */ }
        applyTheme();
    });
    if (window.matchMedia) {
        matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => { if (!saved()) applyTheme(); });
    }
}
