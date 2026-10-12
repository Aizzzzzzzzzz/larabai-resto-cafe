/* Modals — markup from the mockup: <div class="modal-back" id="…" hidden><form class="modal">…</form></div>.
     Open:  any element with data-modal-open="<id>"
     Close: any element with data-modal-close (or the mockup's data-close) inside the modal,
            a click on the backdrop, or Esc.
   A modal marked data-modal-autoopen opens on page load (used when its form failed validation).

   One modal can serve many rows: the opener may also carry
     data-action  → the form's action URL (e.g. this row's /cashier/orders/7/void)
     data-title   → the modal heading
     data-text    → the text of [data-modal-text] inside the modal
     data-submit  → the label of [data-modal-submit]
   The action is also kept in a hidden _action field, so a modal re-opened after a failed
   validation still posts to the same row. */
export function openModal(id, opener = null) {
    const back = document.getElementById(id);
    if (!back) return;

    if (opener) {
        const form = back.querySelector('form');
        const set = (selector, text) => { const el = back.querySelector(selector); if (el && text !== undefined) el.textContent = text; };
        if (form && opener.dataset.action) {
            form.action = opener.dataset.action;
            const keep = form.querySelector('input[name="_action"]');
            if (keep) keep.value = opener.dataset.action;
        }
        set('h2', opener.dataset.title);
        set('[data-modal-text]', opener.dataset.text);
        set('[data-modal-submit]', opener.dataset.submit);
    }

    back.hidden = false;
    const first = back.querySelector('input:not([type=hidden]), select, textarea, button');
    if (first) first.focus();
}

export function closeModal(back) {
    if (back) back.hidden = true;
}

export function initModals() {
    document.addEventListener('click', (e) => {
        const opener = e.target.closest('[data-modal-open]');
        if (opener) { e.preventDefault(); openModal(opener.dataset.modalOpen, opener); return; }
        if (e.target.closest('[data-modal-close], [data-close]')) { closeModal(e.target.closest('.modal-back')); return; }
        if (e.target.classList && e.target.classList.contains('modal-back')) closeModal(e.target);
    });
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        document.querySelectorAll('.modal-back:not([hidden])').forEach(closeModal);
    });
    document.querySelectorAll('.modal-back[data-modal-autoopen]').forEach((b) => openModal(b.id));

    // Print buttons (receipts)
    document.addEventListener('click', (e) => { if (e.target.closest('[data-print]')) window.print(); });
}
