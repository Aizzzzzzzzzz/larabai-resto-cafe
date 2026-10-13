/* Number formatting for live previews — same output as the PHP peso() helper and
   App\Support\StockLevel::format(). Display only: the server always recomputes. */
export function peso(amount, decimals = true) {
    return '₱' + Number(amount).toLocaleString('en-PH', {
        minimumFractionDigits: decimals ? 2 : 0,
        maximumFractionDigits: decimals ? 2 : 0,
    });
}

const round2 = (n) => Math.round(n * 100) / 100;

/** 12400 g → "12.4 kg", 850 ml → "850 ml", 12 pc → "12 pcs". */
export function qty(amount, unit) {
    const n = Number(amount);
    const text = (x) => round2(x).toLocaleString('en-PH');
    if (unit === 'g' && Math.abs(n) >= 1000) return `${text(n / 1000)} kg`;
    if (unit === 'ml' && Math.abs(n) >= 1000) return `${text(n / 1000)} L`;
    if (unit === 'pc') return `${text(n)}${Math.abs(n) === 1 ? ' pc' : ' pcs'}`;
    return `${text(n)} ${unit}`;
}
