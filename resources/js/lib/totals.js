/**
 * Live totals in the document editor. Mirrors App\Services\TotalsCalculator exactly
 * (same steps, same half-up rounding, BigInt so nothing overflows). The server
 * recalculates on save; tests/fixtures/totals.json is checked against both.
 */

/** "2.5" → 2500n (three decimals). Invalid or empty input counts as 0. */
export function scaled(decimal) {
    const match = /^(\d+)(?:\.(\d{1,3}))?$/.exec(String(decimal ?? '').trim());
    return match ? BigInt(match[1] + (match[2] ?? '').padEnd(3, '0')) : 0n;
}

/** round(a × b ÷ divisor), half up, for a, b ≥ 0. */
function mulDiv(a, b, divisor) {
    const n = a * b;
    const d = BigInt(divisor);
    return (2n * n + d) / (2n * d);
}

function spread(amount, weights, total) {
    if (amount === 0n || total === 0n) return weights.map(() => 0n);

    const shares = weights.map((w) => (amount * w) / total);
    const remainders = weights.map((w) => (amount * w) % total);
    let left = amount - shares.reduce((sum, s) => sum + s, 0n);

    const order = weights.map((_, i) => i).sort((a, b) => (remainders[b] > remainders[a] ? 1 : remainders[b] < remainders[a] ? -1 : a - b));
    for (const i of order) {
        if (left <= 0n) break;
        shares[i] += 1n;
        left -= 1n;
    }

    return shares;
}

/**
 * @param {{qty: string, unit_price_minor: number|bigint|string, discount: string, tax_rate: string}[]} lines
 * @param {{type: 'percent'|'amount', value: string|number}} discount percent string, or amount in minor units
 * @returns {{lines: object[], subtotal: bigint, discount: bigint, taxable: bigint, tax: bigint, total: bigint}} BigInt values
 */
export function calculateTotals(lines, discount) {
    let subtotal = 0n;

    const computed = lines.map((line) => {
        const gross = mulDiv(scaled(line.qty), BigInt(line.unit_price_minor || 0), 1000);
        const lineDiscount = mulDiv(gross, scaled(line.discount), 100000);
        const net = gross - lineDiscount;
        subtotal += net;
        return { gross, discount: lineDiscount, net, rate: scaled(line.tax_rate) };
    });

    let documentDiscount;
    if (discount.type === 'amount') {
        const amount = BigInt(discount.value || 0);
        documentDiscount = amount < subtotal ? amount : subtotal;
    } else {
        documentDiscount = mulDiv(subtotal, scaled(discount.value), 100000);
    }

    const shares = spread(
        documentDiscount,
        computed.map((line) => line.net),
        subtotal,
    );

    let tax = 0n;
    const resultLines = computed.map((line, i) => {
        const taxable = line.net - shares[i];
        const lineTax = mulDiv(taxable, line.rate, 100000);
        tax += lineTax;
        return { gross: line.gross, discount: line.discount, net: line.net, discount_share: shares[i], taxable, tax: lineTax };
    });

    const taxable = subtotal - documentDiscount;

    return { lines: resultLines, subtotal, discount: documentDiscount, taxable, tax, total: taxable + tax };
}

/** "1250.5" with 2 decimals → 125050n; invalid → 0n. Mirrors App\Support\Money::toMinor for form input. */
export function toMinor(amount, decimals) {
    const text = String(amount ?? '')
        .replace(/[,\s٬]/g, '')
        .replace('٫', '.')
        .replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d));
    const match = /^(\d+)(?:\.(\d+))?$/.exec(text);
    if (!match || (match[2] ?? '').length > decimals) return 0n;
    return BigInt(match[1] + (match[2] ?? '').padEnd(decimals, '0'));
}

/** 125050n with 2 decimals → "1,250.50" */
export function formatMinor(minor, decimals) {
    const value = BigInt(minor);
    const negative = value < 0n;
    const digits = (negative ? -value : value).toString().padStart(decimals + 1, '0');
    const whole = digits.slice(0, digits.length - decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    const fraction = decimals ? `.${digits.slice(-decimals)}` : '';
    return `${negative ? '-' : ''}${whole}${fraction}`;
}
