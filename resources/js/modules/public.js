/* Public pages: the homepage "What's in the glass" tabs, and the staff login helpers.
   Nothing here saves anything — the login is a normal form post checked by the server. */

export function initPublic() {
    initCupTabs();
    initLogin();
}

// ---- Homepage: switch the glass between the best sellers ----------------------------------------
function initCupTabs() {
    const card = document.querySelector('[data-cups]');
    if (!card) return;
    const cups = JSON.parse(card.dataset.cups);
    const tabs = card.querySelector('.cup-tabs');
    const layers = card.querySelectorAll('.glass .layer');
    const money = (n) => '₱' + Number(n).toLocaleString('en-PH');
    const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    let current = 0;

    function show(i) {
        const cup = cups[i];
        tabs.querySelectorAll('button').forEach((b, k) => { b.setAttribute('aria-selected', String(k === i)); b.tabIndex = k === i ? 0 : -1; });
        layers.forEach((el, k) => {
            const layer = cup.layers[k]; // [what, how much, height %, colour, extra class]
            el.style.height = layer ? `${layer[2]}%` : '0';
            el.style.backgroundColor = layer ? layer[3] : 'transparent';
            el.className = 'layer' + (layer && layer[4] ? ` ${layer[4]}` : '');
        });
        card.querySelector('.legend').innerHTML = cup.layers.map((l) => `<li><i style="background:${l[3]}"></i><span>${esc(l[0])}</span><span>${esc(l[1])}</span></li>`).join('');
        card.querySelector('#cupName').textContent = cup.name;
        card.querySelector('#cupNote').textContent = cup.note;
        card.querySelector('#cupPrice').textContent = money(cup.price);
    }

    tabs.addEventListener('click', (e) => {
        const b = e.target.closest('button');
        if (b) show(current = Number(b.dataset.i));
    });
    // Arrow keys move between tabs, like a native tab list
    tabs.addEventListener('keydown', (e) => {
        if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
        current = (current + (e.key === 'ArrowRight' ? 1 : cups.length - 1)) % cups.length;
        show(current);
        tabs.querySelectorAll('button')[current].focus();
    });
}

// ---- Login: show / hide the password, and fill the form from a demo account ---------------------
function initLogin() {
    const form = document.querySelector('[data-login]');
    if (!form) return;

    form.querySelector('[data-show-password]').addEventListener('click', (e) => {
        const input = form.elements.password;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        e.currentTarget.textContent = show ? 'Hide' : 'Show';
        e.currentTarget.setAttribute('aria-pressed', String(show));
    });

    // Typing again clears the "wrong password" markup
    form.addEventListener('input', () => {
        form.querySelector('.err')?.setAttribute('hidden', '');
        form.querySelectorAll('[aria-invalid]').forEach((el) => el.removeAttribute('aria-invalid'));
    });

    document.querySelector('.accts')?.addEventListener('click', (e) => {
        const account = e.target.closest('.acct');
        if (!account) return;
        document.querySelectorAll('.acct').forEach((a) => a.setAttribute('aria-pressed', String(a === account)));
        form.elements.username.value = account.dataset.username;
        form.elements.password.value = account.dataset.password;
        form.querySelector('.err')?.setAttribute('hidden', '');
        form.querySelector('[type="submit"]').focus();
    });
}
