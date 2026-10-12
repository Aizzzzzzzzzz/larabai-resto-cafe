/* Toast — the layout renders the session flash message as .toast-pop; this hides it after a
   moment. toast('…') can also be called from other modules for display-only messages. */
let timer;

export function toast(message) {
    let el = document.querySelector('.toast-pop');
    if (!el) {
        el = document.createElement('div');
        el.className = 'toast-pop';
        el.setAttribute('role', 'status');
        document.body.appendChild(el);
    }
    el.textContent = message;
    el.hidden = false;
    clearTimeout(timer);
    timer = setTimeout(() => { el.hidden = true; }, 2400);
}

export function initToast() {
    const flash = document.querySelector('.toast-pop:not([hidden])');
    if (flash) toast(flash.textContent.trim());
}
