/* Confirmations for destructive actions. Buttons are never disabled — the check happens on submit,
   and the controller always re-checks.

   1. Type-to-confirm (<x-confirm-form>): <form data-confirm-word="RESET"> with an input name="confirm".
      Submitting without the exact word shows [data-confirm-msg] instead.
   2. Tap twice (the mockup's "Tap again to confirm cancel"): <button data-confirm-twice="Tap again to cancel">.
      The first click only re-labels the button for 4 seconds; a second click submits. */
export function initConfirm() {
    document.addEventListener('submit', (e) => {
        const form = e.target.closest('form[data-confirm-word]');
        if (!form) return;
        const input = form.querySelector('[name="confirm"]');
        const msg = form.querySelector('[data-confirm-msg]');
        if (input && input.value.trim() === form.dataset.confirmWord) return;
        e.preventDefault();
        if (msg) msg.hidden = false;
        if (input) input.focus();
    });

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-confirm-twice]');
        if (!btn) return;
        if (btn.dataset.armed) return; // second click: let the form submit
        e.preventDefault();
        btn.dataset.armed = '1';
        btn.dataset.label = btn.textContent;
        btn.textContent = btn.dataset.confirmTwice;
        btn.classList.add('armed');
        setTimeout(() => {
            delete btn.dataset.armed;
            btn.textContent = btn.dataset.label;
            btn.classList.remove('armed');
        }, 4000);
    });
}
