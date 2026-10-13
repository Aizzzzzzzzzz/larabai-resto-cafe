/* Any <select> or checkbox inside a <form> marked with — or carrying — data-autosubmit submits its
   form as soon as it changes (filters, switches). A <noscript> button is the no-JS fallback. */
export function initAutosubmit() {
    document.addEventListener('change', (e) => {
        const control = e.target.closest('[data-autosubmit]');
        const form = control && (control.form || control.closest('form[data-autosubmit]'));
        if (form) form.requestSubmit();
    });
}
