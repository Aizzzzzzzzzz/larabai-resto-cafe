/* Cashier screens (resources/views/cashier). Display-only helpers: the POS ticket is posted as one
   form on "Charge", and the payment form is checked and computed again on the server. */
import { peso } from '../core/money';
import { openModal, closeModal } from '../core/modal';
import { toast } from '../core/toast';

export function initCashier() {
    if (document.body.dataset.module !== 'cashier') return;
    initPos();
    initPayment();
}

// ---- POS ---------------------------------------------------------------------------------
const TICKET_KEY = 'bbPosTicket'; // per-device convenience: survives a refresh, nothing more

function initPos() {
    const form = document.querySelector('[data-pos]');
    if (!form) return;
    const menu = JSON.parse(document.getElementById('pos-menu').textContent);
    const modal = document.querySelector('[data-pos-modal]');
    const load = () => { try { return JSON.parse(localStorage.getItem(TICKET_KEY)) || []; } catch (e) { return []; } };
    const save = (lines) => { try { localStorage.setItem(TICKET_KEY, JSON.stringify(lines)); } catch (e) { /* storage blocked */ } };
    if (form.hasAttribute('data-reset')) save([]);

    let lines = load().filter((l) => menu[l.code]); // drop items no longer on the menu

    // Category tabs + search filter the tiles already on the page
    let cat = 'all';
    const filter = () => {
        const q = form.querySelector('[data-pos-search]').value.trim().toLowerCase();
        let shown = 0;
        form.querySelectorAll('.tile').forEach((t) => {
            const match = (cat === 'all' || t.dataset.cat === cat) && (!q || t.dataset.search.includes(q));
            t.hidden = !match;
            if (match) shown++;
        });
        form.querySelector('[data-pos-none]').hidden = shown > 0;
    };
    form.querySelector('.pos-tools .tabs').addEventListener('click', (e) => {
        const b = e.target.closest('button[data-cat]');
        if (!b) return;
        cat = b.dataset.cat;
        form.querySelectorAll('.pos-tools .tabs button').forEach((x) => x.classList.toggle('on', x === b));
        filter();
    });
    const search = form.querySelector('[data-pos-search]');
    search.addEventListener('input', filter);
    search.addEventListener('keydown', (e) => { if (e.key === 'Enter') e.preventDefault(); }); // Enter must not charge

    // Dine-in shows the table picker
    const typeChanged = () => {
        form.querySelector('[data-pos-table]').hidden = form.querySelector('input[name="order_type"]:checked').value !== 'dine-in';
    };
    form.querySelectorAll('input[name="order_type"]').forEach((r) => r.addEventListener('change', typeChanged));

    // Tiles: items with options open the modal, the rest go straight onto the ticket
    let current = null;
    let qty = 1;
    form.querySelector('.grid').addEventListener('click', (e) => {
        const tile = e.target.closest('.tile');
        if (!tile || tile.disabled) return;
        const tpl = document.getElementById('pos-item-' + tile.dataset.item);
        if (!tpl) { add(tile.dataset.item, [], '', 1); return; }
        current = tile.dataset.item;
        qty = 1;
        modal.querySelector('[data-pos-options]').replaceChildren(tpl.content.cloneNode(true));
        modal.querySelector('input[name="notes"]').value = '';
        modal.querySelector('#posModalTitle').textContent = `${menu[current].name} · ${peso(menu[current].price, false)}`;
        updateModal();
        openModal('posModal');
    });

    const picked = () => [...modal.querySelectorAll('[data-pos-options] input:checked')].map((i) => ({
        group: i.name, choice: i.value, name: i.dataset.name, price: Number(i.dataset.price),
    }));
    const updateModal = () => {
        modal.querySelector('[data-pos-qty]').textContent = qty;
        const unit = menu[current].price + picked().reduce((n, o) => n + o.price, 0);
        modal.querySelector('[data-pos-add]').textContent = `Add · ${peso(unit * qty, false)}`;
    };
    modal.addEventListener('change', updateModal);
    modal.addEventListener('click', (e) => {
        const step = e.target.closest('[data-qty]');
        if (!step) return;
        qty = Math.min(50, Math.max(1, qty + Number(step.dataset.qty)));
        updateModal();
    });
    modal.addEventListener('submit', (e) => {
        e.preventDefault(); // this modal only edits the ticket; nothing is sent until Charge
        add(current, picked(), modal.querySelector('input[name="notes"]').value.trim(), qty);
        closeModal(modal.closest('.modal-back'));
    });

    function add(code, options, notes, n) {
        const key = code + '|' + options.map((o) => `${o.group}:${o.choice}`).join(',');
        const same = lines.find((l) => l.key === key && l.notes === notes);
        if (same) same.qty = Math.min(50, same.qty + n);
        else lines.push({ id: Date.now(), key, code, options, notes, qty: n });
        render();
    }

    // Ticket lines
    const linesBox = form.querySelector('[data-pos-lines]');
    const unitOf = (l) => menu[l.code].price + l.options.reduce((n, o) => n + o.price, 0);
    const optionText = (l) => {
        const singles = l.options.filter((o) => o.group !== 'addons' && o.group !== 'side').map((o) => (o.group === 'sugar' ? `${o.name} sugar` : o.name));
        const multis = l.options.filter((o) => o.group === 'addons' || o.group === 'side').map((o) => o.name);
        return [...singles, multis.join(', ')].filter(Boolean).join(' · ');
    };
    const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

    function render() {
        save(lines);
        linesBox.innerHTML = lines.length ? lines.map((l) => `
            <div class="tl" data-id="${l.id}">
                <span><b>${esc(menu[l.code].name)}</b>${optionText(l) ? `<small>${esc(optionText(l))}</small>` : ''}${l.notes ? `<small><i>${esc(l.notes)}</i></small>` : ''}</span>
                <span class="amt">${peso(unitOf(l) * l.qty)}</span>
                <span class="qty-row"><button type="button" data-act="minus" aria-label="Decrease">−</button><output>${l.qty}</output><button type="button" data-act="plus" aria-label="Increase">+</button></span>
                <button type="button" class="rm" data-act="rm">Remove</button>
            </div>`).join('') : '<p class="no-lines">Tap an item on the left to start an order.</p>';
        const total = lines.reduce((n, l) => n + unitOf(l) * l.qty, 0);
        form.querySelector('[data-pos-total]').textContent = peso(total);
        form.querySelector('[data-pos-charge]').textContent = lines.length ? `Charge ${peso(total)}` : 'Charge';
    }

    linesBox.addEventListener('click', (e) => {
        const b = e.target.closest('button[data-act]');
        if (!b) return;
        const line = lines.find((l) => l.id === Number(b.closest('.tl').dataset.id));
        if (b.dataset.act === 'plus') line.qty = Math.min(50, line.qty + 1);
        if (b.dataset.act === 'minus') line.qty--;
        if (b.dataset.act === 'rm') line.qty = 0;
        lines = lines.filter((l) => l.qty > 0);
        render();
    });
    form.querySelector('[data-pos-clear]').addEventListener('click', () => { lines = []; render(); });

    // Charge: write the ticket as array inputs, then let the form post normally
    form.addEventListener('submit', (e) => {
        if (!lines.length) { e.preventDefault(); toast('Tap an item on the left to start an order.'); return; }
        const box = form.querySelector('[data-pos-inputs]');
        box.replaceChildren();
        const field = (name, value) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            box.append(input);
        };
        lines.forEach((l, i) => {
            field(`items[${i}][menu_item]`, l.code);
            field(`items[${i}][qty]`, l.qty);
            field(`items[${i}][notes]`, l.notes);
            l.options.forEach((o) => field(`items[${i}][options][${o.group}][]`, o.choice));
        });
    });

    typeChanged();
    filter();
    render();
}

// ---- Payment preview ------------------------------------------------------------------------
function initPayment() {
    const form = document.querySelector('[data-payment]');
    if (!form) return;
    const subtotal = Number(form.dataset.subtotal);
    const fee = Number(form.dataset.deliveryFee);
    const vat = Number(form.dataset.vat);
    const percent = Number(form.dataset.percent);
    const $ = (sel) => form.querySelector(sel);
    const val = (name) => form.querySelector(`[name="${name}"]`);
    const round2 = (n) => Math.round(n * 100) / 100;

    function discount() {
        if (!form.querySelector('input[type="checkbox"][name="discount"]').checked) return null;
        const eligible = Math.max(1, parseInt(val('eligible_count').value, 10) || 1);
        const diners = Math.max(eligible, parseInt(val('diner_count').value, 10) || 1);
        const share = round2(subtotal * eligible / diners);
        const amount = round2(share - share / (1 + vat) * (1 - percent / 100));
        return { eligible, diners, share, amount, type: val('discount_type').selectedOptions[0].textContent };
    }

    function update() {
        const d = discount();
        const due = Math.max(0, round2(subtotal - (d ? d.amount : 0) + fee));
        const method = form.querySelector('input[name="method"]:checked').value;

        $('[data-discount-fields]').hidden = !d;
        $('[data-discount-row]').hidden = !d;
        if (d) {
            $('[data-discount-label]').textContent = `${d.type} discount (${d.eligible}/${d.diners})`;
            $('[data-discount-amount]').textContent = '−' + peso(d.amount);
            $('[data-discount-note]').textContent = `Eligible share ${peso(d.share)} ÷ 1.12 = ${peso(d.share / (1 + vat))} (VAT-exempt), less 20% = ${peso(d.share - d.amount)}.`;
        }
        $('[data-amount-due]').textContent = peso(due);

        $('[data-cash-box]').hidden = method !== 'CASH';
        $('[data-wallet-box]').hidden = method === 'CASH';
        const quick = [due, Math.ceil(due / 50) * 50, Math.ceil(due / 100) * 100, 500, 1000].filter((v, i, a) => v >= due && a.indexOf(v) === i);
        $('[data-quick]').innerHTML = quick.map((v, i) => `<button type="button" class="btn btn-ghost btn-sm" data-cash="${v}">${i === 0 ? 'Exact ' : ''}${peso(v, i === 0)}</button>`).join('');
        const tendered = parseFloat(val('amount_tendered').value) || 0;
        $('[data-change]').textContent = peso(Math.max(0, tendered - due));
        const label = { CASH: 'cash', GCASH: 'GCash', MAYA: 'Maya' }[method];
        $('[data-confirm-pay]').textContent = `Confirm ${label} payment · ${peso(due)}`;
    }

    form.addEventListener('input', update);
    form.addEventListener('change', update);
    form.addEventListener('click', (e) => {
        const b = e.target.closest('[data-cash]');
        if (!b) return;
        val('amount_tendered').value = b.dataset.cash;
        update();
    });
    update();
}
