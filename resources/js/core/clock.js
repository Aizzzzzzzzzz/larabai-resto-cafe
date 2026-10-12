/* Clock in staff page headers: <span class="clock" data-clock></span> */
export function initClock() {
    const els = document.querySelectorAll('[data-clock]');
    if (!els.length) return;
    const tick = () => {
        const text = new Date().toLocaleString('en-PH', { weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
        els.forEach((el) => { el.textContent = text; });
    };
    tick();
    setInterval(tick, 15000);
}
