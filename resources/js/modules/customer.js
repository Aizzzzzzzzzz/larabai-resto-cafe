/* Customer screens (resources/views/customer). Each part runs only when its markup is on the page.
   Nothing here saves anything: the item sheet, cart buttons, checkout and wallet are real forms. */
import { peso } from '../core/money';

export function initCustomer() {
    if (document.body.dataset.module !== 'customer') return;
    initMenu();
    initCheckout();
    initWallet();
    initTrackerPoll();
}

// ---- Menu: category chips, search, item sheet ----------------------------------------------
function initMenu() {
    const menu = document.getElementById('menu');
    if (!menu) return;
    const $ = (id) => document.getElementById(id);

    // Category chips scroll to their section; the section in view highlights its chip
    const chips = [...document.querySelectorAll('.cat-bar button')];
    const setActive = (cat) => chips.forEach((b) => {
        const on = b.dataset.cat === cat;
        b.classList.toggle('on', on);
        if (on) b.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
    });
    chips.forEach((b) => b.addEventListener('click', () => {
        $('sec-' + b.dataset.cat).scrollIntoView({ behavior: 'smooth' });
        setActive(b.dataset.cat);
    }));
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((e) => { if (e.isIntersecting) setActive(e.target.dataset.cat); });
    }, { rootMargin: '-60px 0px -70% 0px' });
    document.querySelectorAll('.menu-section').forEach((s) => observer.observe(s));

    // Search filters the rows already on the page
    const search = document.querySelector('[data-menu-search]');
    search?.addEventListener('input', () => {
        const q = search.value.trim().toLowerCase();
        let shown = 0;
        document.querySelectorAll('.menu-section').forEach((sec) => {
            let inSec = 0;
            sec.querySelectorAll('.m-item').forEach((row) => {
                const match = !q || row.dataset.search.includes(q);
                row.hidden = !match;
                if (match) inSec++;
            });
            sec.hidden = inSec === 0;
            shown += inSec;
        });
        $('noResults').hidden = shown > 0;
    });

    // Item sheet: copy the item's server-rendered <template> into the form
    const sheet = $('sheet');
    let base = 0;
    let qty = 1;

    const unitPrice = () => base + [...sheet.querySelectorAll('#sheetOptions input:checked')]
        .reduce((n, input) => n + Number(input.dataset.price || 0), 0);
    const updateTotal = () => {
        $('qty').textContent = qty;
        $('sheetQty').value = qty;
        $('sheetAdd').textContent = `Add to order · ${peso(unitPrice() * qty, false)}`;
    };

    function openSheet(code) {
        const tpl = $('item-' + code);
        if (!tpl) return;
        const content = tpl.content.cloneNode(true);
        base = Number(tpl.dataset.price);
        qty = 1;

        $('sheetHero').querySelectorAll('.art-glass, .art-plate').forEach((el) => el.remove());
        $('sheetHero').append(content.querySelector('.art-glass, .art-plate'));
        $('sheetOptions').replaceChildren(content);
        $('sheetItem').value = code;
        $('sheetTitle').textContent = tpl.dataset.name;
        $('sheetDesc').textContent = tpl.dataset.description;
        $('sheetBase').textContent = peso(base, false);
        $('sheetNotes').value = '';
        updateTotal();

        sheet.hidden = false;
        $('sheetBackdrop').hidden = false;
        document.body.style.overflow = 'hidden';
        $('sheetClose').focus();
    }

    function closeSheet() {
        sheet.hidden = true;
        $('sheetBackdrop').hidden = true;
        document.body.style.overflow = '';
    }

    menu.addEventListener('click', (e) => {
        const row = e.target.closest('.m-item');
        if (row && !row.disabled) openSheet(row.dataset.item);
    });
    sheet.addEventListener('change', updateTotal);
    sheet.addEventListener('click', (e) => {
        const step = e.target.closest('[data-qty]');
        if (!step) return;
        qty = Math.min(20, Math.max(1, qty + Number(step.dataset.qty)));
        updateTotal();
    });
    $('sheetClose').addEventListener('click', closeSheet);
    $('sheetBackdrop').addEventListener('click', closeSheet);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !sheet.hidden) closeSheet(); });
}

// ---- Checkout: show what fits the chosen order type and payment method ---------------------
function initCheckout() {
    const form = document.querySelector('[data-checkout]');
    if (!form) return;
    const subtotal = Number(form.dataset.subtotal);
    const fee = Number(form.dataset.deliveryFee);
    const picked = (name) => form.querySelector(`input[name="${name}"]:checked`)?.value;

    function update() {
        const delivery = picked('order_type') === 'delivery';
        const method = picked('payment_method');
        const total = peso(subtotal + (delivery ? fee : 0));

        form.querySelectorAll('[data-show-for="delivery"]').forEach((el) => { el.hidden = !delivery; });
        form.querySelectorAll('[data-hide-for="delivery"]').forEach((el) => { el.hidden = delivery; });
        form.querySelector('[data-checkout-total]').textContent = total;
        form.querySelector('[data-checkout-submit]').textContent =
            method === 'GCASH' ? `Pay ${total} with GCash`
                : method === 'MAYA' ? `Pay ${total} with Maya`
                    : `Place order · ${total}`;
        form.querySelector('[data-checkout-hint]').textContent = method === 'CASH'
            ? (delivery ? 'Pay the rider in cash when your order arrives.' : 'Your order is sent to the kitchen once the cashier receives your payment.')
            : 'Simulated payment: no real money is charged.';
    }

    form.addEventListener('change', update);
    update();
}

// ---- Simulated wallet: show "Processing payment" while the form submits --------------------
function initWallet() {
    const form = document.querySelector('[data-wallet]');
    if (!form) return;
    form.addEventListener('submit', () => {
        const btn = form.querySelector('[data-wallet-pay]');
        btn.innerHTML = '<span class="processing" aria-hidden="true"></span> Processing payment';
        btn.style.pointerEvents = 'none'; // stop a double tap from paying twice
        form.querySelector('[data-wallet-cancel]').hidden = true;
    });
}

// ---- Tracker: reload when the kitchen or cashier moves the order ---------------------------
function initTrackerPoll() {
    const host = document.querySelector('[data-poll]');
    if (!host) return;
    setInterval(async () => {
        try {
            const res = await fetch(host.dataset.poll, { headers: { Accept: 'application/json' } });
            if (!res.ok) return;
            const { status } = await res.json();
            if (status !== host.dataset.status) location.reload();
        } catch (e) { /* offline for a moment — try again next tick */ }
    }, 10000);
}
