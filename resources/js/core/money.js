/* ₱ formatting for live previews — same output as the PHP peso() helper. Display only:
   the server always recomputes prices and totals. */
export function peso(amount, decimals = true) {
    return '₱' + Number(amount).toLocaleString('en-PH', {
        minimumFractionDigits: decimals ? 2 : 0,
        maximumFractionDigits: decimals ? 2 : 0,
    });
}
