/* Modals — markup from the mockup: <div class="modal-back" id="…" hidden><form class="modal">…</form></div>.
     Open:  any element with data-modal-open="<id>"
     Close: any element with data-modal-close (or the mockup's data-close) inside the modal,
            a click on the backdrop, or Esc.
   A modal marked data-modal-autoopen opens on page load (used when its form failed validation). */
export function openModal(id) {
    const back = document.getElementById(id);
    if (!back) return;
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
        if (opener) { e.preventDefault(); openModal(opener.dataset.modalOpen); return; }
        if (e.target.closest('[data-modal-close], [data-close]')) { closeModal(e.target.closest('.modal-back')); return; }
        if (e.target.classList && e.target.classList.contains('modal-back')) closeModal(e.target);
    });
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        document.querySelectorAll('.modal-back:not([hidden])').forEach(closeModal);
    });
    document.querySelectorAll('.modal-back[data-modal-autoopen]').forEach((b) => openModal(b.id));
}
