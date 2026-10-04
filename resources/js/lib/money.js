/**
 * Display helpers for amounts. The server stores minor units and sends decimal strings
 * ("1250.50"); totals are calculated on the server (Phase 4 mirrors them here).
 */

/**
 * "1250.5", "SAR" → "1,250.50 SAR"
 * @param {string|number} amount decimal string or number
 * @param {string} currency ISO code
 * @param {number} [decimals] defaults to what the string carries, at least 2
 */
export function formatMoney(amount, currency, decimals) {
    const text = String(amount ?? '0');
    const digits = decimals ?? Math.max(2, text.includes('.') ? text.split('.')[1].length : 0);
    const value = new Intl.NumberFormat('en-US', { minimumFractionDigits: digits, maximumFractionDigits: digits }).format(Number(text));

    return `${value} ${currency}`;
}
