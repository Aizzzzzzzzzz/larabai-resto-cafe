/* Admin screens (resources/views/admin). Saving is always a normal form post checked again by the
   server; this file only builds the dynamic rows and toggles what applies. */

export function initAdmin() {
    if (document.body.dataset.module !== 'admin') return;
    initItemEditor();
    initUserForm();
}

// ---- Users & roles: the add / edit modal --------------------------------------------------------
function initUserForm() {
    const form = document.querySelector('[data-user-form]');
    if (!form) return;

    // Your own account: role and "can log in" are locked (disabled fields aren't sent; the server refuses changes too)
    function refresh() {
        const editing = form.elements.mode.value === 'edit';
        const self = editing && form.elements.self.value === '1';

        form.querySelector('[data-pass-label]').textContent = editing ? 'New password (leave blank to keep)' : 'Password';
        form.elements.role.disabled = self;
        [...form.elements.is_active].forEach((el) => { el.disabled = self; });
        form.querySelector('[data-self-note]').hidden = !self;
        form.elements.password.value = '';
    }

    form.addEventListener('modal:filled', refresh);
    refresh();
}

// ---- Menu & prices: the item editor -------------------------------------------------------------
function initItemEditor() {
    const form = document.querySelector('[data-item-form]');
    if (!form) return;
    const rows = form.querySelector('[data-ing-rows]');
    const template = document.getElementById('ingRowTemplate');
    const deleteForm = document.getElementById('itemDeleteForm');
    const field = (name) => form.elements[name];

    const nextIndex = () => Math.max(-1, ...[...rows.querySelectorAll('[name^="ingredients["]')]
        .map((el) => Number(el.name.match(/^ingredients\[(\d+)\]/)?.[1]))) + 1;

    function addRow(code = '', qty = '') {
        rows.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__i__', nextIndex()));
        const row = rows.lastElementChild;
        if (code) row.querySelector('[data-ing="code"]').value = code;
        row.querySelector('[name$="[qty]"]').value = qty;
        syncUnit(row);
        return row;
    }

    const syncUnit = (row) => {
        const select = row.querySelector('[data-ing="code"]');
        row.querySelector('[data-ing="unit"]').textContent = select.selectedOptions[0]?.dataset.unit ?? '';
    };

    // Shows what applies: Large price only for items with a size choice, the options template only when adding,
    // Remove only when editing, and the cup note only for drinks
    function refresh() {
        const editing = field('mode').value === 'edit';
        const hasSize = editing
            ? field('has_size').value === '1'
            : field('template').selectedOptions[0]?.dataset.hasSize === '1';
        const drink = field('category').selectedOptions[0]?.dataset.drink === '1';

        form.querySelector('[data-template]').hidden = editing;
        form.querySelector('[data-large]').hidden = !hasSize;
        form.querySelector('[data-item-delete]').hidden = !editing;
        form.querySelector('[data-cup-note]').textContent = drink
            ? 'Same amounts for every size. A 12 or 16 oz cup and a lid are deducted automatically.'
            : 'Same amounts for every serving.';
        if (deleteForm) deleteForm.action = form.action; // the same URL: PUT saves, DELETE removes
    }

    // Photo: show the current one (with a "remove" box) when editing, and a preview of a newly chosen file
    const photoInput = form.querySelector('[data-photo-input]');
    const photoBox = form.querySelector('[data-photo-box]');
    const photoImg = form.querySelector('[data-photo-preview]');
    const photoRemove = form.querySelector('[data-photo-remove]');
    let blobUrl = null;

    function showPhoto(url, removable) {
        if (blobUrl && url !== blobUrl) { URL.revokeObjectURL(blobUrl); blobUrl = null; }
        photoBox.hidden = !url;
        if (url) photoImg.src = url;
        photoRemove.hidden = !removable;
        photoRemove.querySelector('input[type=checkbox]').checked = false;
    }

    photoInput?.addEventListener('change', () => {
        const file = photoInput.files[0];
        if (!file) return;
        blobUrl = URL.createObjectURL(file);
        showPhoto(blobUrl, false);
    });

    form.addEventListener('modal:filled', (e) => {
        if (photoInput) {
            photoInput.value = '';
            showPhoto(e.detail?.image_url ?? '', Boolean(e.detail?.image_url));
        }
        if (Array.isArray(e.detail?.ingredients)) {
            rows.replaceChildren();
            e.detail.ingredients.forEach((row) => addRow(row.code, row.qty));
        }
        refresh();
    });
    form.addEventListener('change', (e) => {
        if (e.target.matches('[data-ing="code"]')) syncUnit(e.target.closest('.ing-row'));
        refresh();
    });
    form.addEventListener('click', (e) => {
        if (e.target.closest('[data-ing="add"]')) addRow().querySelector('[data-ing="code"]').focus();
        const remove = e.target.closest('[data-ing="remove"]');
        if (remove) remove.closest('.ing-row').remove();
    });

    refresh(); // a modal re-opened after a failed save
}
