/* Stock screen (resources/views/inventory/ingredients). The restock modal previews the new balance;
   saving is a normal form post and the server checks the numbers again. */
import { qty } from '../core/money';

export function initInventory() {
    const form = document.querySelector('[data-restock-form]');
    if (!form) return;
    const $ = (sel) => form.querySelector(sel);
    const field = (name) => form.elements[name];

    const mode = () => form.querySelector('input[name="mode"]:checked')?.value || 'add';

    function refresh() {
        const unit = field('ctx_unit').value;
        const stock = Number(field('ctx_stock').value);
        const add = mode() === 'add';

        $('[data-restock-onhand]').innerHTML = unit
            ? `On hand <b>${qty(stock, unit)}</b> · reorder at ${qty(field('ctx_reorder').value, unit)}`
            : '';
        $('[data-restock-label]').textContent = add ? 'Quantity received' : 'Counted on the shelf';
        $('[data-restock-unit]').textContent = unit;

        const raw = field('qty').value;
        if (raw === '' || !unit) { $('[data-restock-preview]').textContent = ''; return; }
        const after = add ? stock + Number(raw) : Number(raw);
        const diff = after - stock;
        $('[data-restock-preview]').innerHTML = `Stock becomes <b>${qty(after, unit)}</b> (${diff >= 0 ? '+' : ''}${qty(diff, unit)})`;
    }

    // Switching between "delivery" and "count" suggests the usual restock quantity, or what the books say is on hand
    form.addEventListener('change', (e) => {
        if (e.target.name === 'mode') field('qty').value = mode() === 'add' ? field('ctx_restock').value : field('ctx_stock').value;
        refresh();
    });
    form.addEventListener('input', refresh);
    form.addEventListener('modal:filled', refresh);
    refresh(); // a modal re-opened after a failed save
}
