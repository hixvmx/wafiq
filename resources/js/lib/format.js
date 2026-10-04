// Arabic text with Latin digits (what most Gulf and Egyptian businesses use on quotes and invoices).
const LOCALE = 'ar-u-nu-latn';

const numberFormat = new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 });
const dateFormat = new Intl.DateTimeFormat(LOCALE, { day: 'numeric', month: 'long', year: 'numeric' });
const dateTimeFormat = new Intl.DateTimeFormat(LOCALE, { day: 'numeric', month: 'long', year: 'numeric', hour: 'numeric', minute: '2-digit' });
const relativeFormat = new Intl.RelativeTimeFormat(LOCALE, { numeric: 'auto' });

/** @param {number|string} value */
export function formatNumber(value) {
    return numberFormat.format(Number(value));
}

/** @param {string|null|undefined} iso */
export function formatDate(iso) {
    return iso ? dateFormat.format(new Date(iso)) : '';
}

/** @param {string|null|undefined} iso */
export function formatDateTime(iso) {
    return iso ? dateTimeFormat.format(new Date(iso)) : '';
}

/** @type {[Intl.RelativeTimeFormatUnit, number][]} */
const UNITS = [
    ['year', 60 * 60 * 24 * 365],
    ['month', 60 * 60 * 24 * 30],
    ['week', 60 * 60 * 24 * 7],
    ['day', 60 * 60 * 24],
    ['hour', 60 * 60],
    ['minute', 60],
];

/**
 * "منذ 3 أيام", "أمس", "الآن"…
 * @param {string|null|undefined} iso
 */
export function timeAgo(iso) {
    if (!iso) return '';

    const seconds = Math.round((new Date(iso).getTime() - Date.now()) / 1000);

    for (const [unit, size] of UNITS) {
        if (Math.abs(seconds) >= size) {
            return relativeFormat.format(Math.round(seconds / size), unit);
        }
    }

    return relativeFormat.format(0, 'second');
}

/**
 * Join class names, skipping falsy values.
 * @param {...(string|false|null|undefined)} classes
 */
export function cn(...classes) {
    return classes.filter(Boolean).join(' ');
}
