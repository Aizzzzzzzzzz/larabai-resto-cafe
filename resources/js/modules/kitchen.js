/* Kitchen display (resources/views/kitchen). Ticket buttons and sold-out switches are real forms;
   this file only refreshes the board, runs the timers, ticks off lines, and handles sound,
   full screen and the sold-out drawer. */

const TICKS_KEY = 'bbKitchenTicks'; // ticked-off lines, this screen only: { orderId: [lineIndex, …] }

export function initKitchen() {
    const host = document.querySelector('[data-kitchen]');
    if (!host) return;

    const loadTicks = () => { try { return JSON.parse(localStorage.getItem(TICKS_KEY)) || {}; } catch (e) { return {}; } };
    const saveTicks = (t) => { try { localStorage.setItem(TICKS_KEY, JSON.stringify(t)); } catch (e) { /* storage blocked */ } };

    // Show which lines are ticked (after every refresh too)
    const paintTicks = () => {
        const ticks = loadTicks();
        document.querySelectorAll('.tk').forEach((tk) => {
            const done = ticks[tk.dataset.id] || [];
            tk.querySelectorAll('li[data-line]').forEach((li) => li.classList.toggle('done', done.includes(Number(li.dataset.line))));
        });
    };

    document.addEventListener('click', (e) => {
        const li = e.target.closest('.tk li[data-line]');
        if (!li) return;
        const id = li.closest('.tk').dataset.id;
        const line = Number(li.dataset.line);
        const ticks = loadTicks();
        const list = ticks[id] || [];
        ticks[id] = list.includes(line) ? list.filter((x) => x !== line) : [...list, line];
        saveTicks(ticks);
        paintTicks();
    });

    // Timers tick every second without redrawing the board
    const tick = () => document.querySelectorAll('.tk-time[data-start]').forEach((el) => {
        const s = Math.max(0, Math.floor((Date.now() - new Date(el.dataset.start)) / 1000));
        const min = Math.floor(s / 60);
        el.textContent = `${min}:${String(s % 60).padStart(2, '0')}`;
        el.classList.toggle('warn', min >= 10 && min < 15);
        el.classList.toggle('late', min >= 15);
    });
    setInterval(tick, 1000);

    // Sound for new orders (browsers only allow audio after a click)
    let sound = false;
    let ctx = null;
    const soundBtn = document.querySelector('[data-sound]');
    const beep = () => {
        if (!sound || !ctx) return;
        [0, 0.18].forEach((d) => {
            const osc = ctx.createOscillator();
            const g = ctx.createGain();
            osc.frequency.value = 880;
            g.gain.value = 0.15;
            osc.connect(g);
            g.connect(ctx.destination);
            osc.start(ctx.currentTime + d);
            osc.stop(ctx.currentTime + d + 0.12);
        });
    };
    soundBtn?.addEventListener('click', () => {
        sound = !sound;
        if (sound && !ctx) { try { ctx = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) { sound = false; } }
        soundBtn.textContent = sound ? 'Sound on' : 'Sound off';
        soundBtn.classList.toggle('on', sound);
        soundBtn.setAttribute('aria-pressed', String(sound));
        beep();
    });

    document.querySelector('[data-fullscreen]')?.addEventListener('click', () => {
        if (document.fullscreenElement) document.exitFullscreen();
        else document.documentElement.requestFullscreen?.().catch(() => {});
    });

    // Sold-out drawer; each switch submits its own form
    const drawer = (open) => {
        document.querySelector('[data-drawer]').hidden = !open;
        document.querySelector('.drawer-back[data-drawer-close]').hidden = !open;
    };
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-drawer-open]')) drawer(true);
        else if (e.target.closest('[data-drawer-close]')) drawer(false);
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') drawer(false); });
    // (the switches' forms submit on change through core/autosubmit.js)

    // Refresh the board every 5 seconds; beep when the New column grows
    let lastNew = Number(document.querySelector('[data-k-part="columns"]').dataset.newCount);
    setInterval(async () => {
        if (!document.querySelector('[data-drawer]').hidden) return; // don't redraw under the drawer
        try {
            const res = await fetch(host.dataset.boardUrl, { headers: { Accept: 'text/html' } });
            if (!res.ok) return;
            const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
            doc.querySelectorAll('[data-k-part]').forEach((part) => {
                document.querySelector(`[data-k-part="${part.dataset.kPart}"]`)?.replaceWith(part);
            });
            const now = Number(document.querySelector('[data-k-part="columns"]').dataset.newCount);
            if (now > lastNew) beep();
            lastNew = now;
            paintTicks();
            tick();
        } catch (e) { /* offline for a moment — try again next time */ }
    }, 5000);

    paintTicks();
    tick();
}
