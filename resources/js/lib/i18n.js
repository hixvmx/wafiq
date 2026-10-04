import { usePage } from '@inertiajs/react';
import { useCallback, useMemo } from 'react';

/**
 * @param {object|undefined} tree
 * @param {string} key
 */
function lookup(tree, key) {
    return key.split('.').reduce((node, part) => (node && typeof node === 'object' ? node[part] : undefined), tree);
}

/**
 * @param {string} text
 * @param {Record<string, string|number>} replacements
 */
function replace(text, replacements) {
    return Object.entries(replacements).reduce((out, [name, value]) => out.replaceAll(`:${name}`, String(value)), text);
}

/**
 * Translation hook backed by lang/ar/ui.php (shared by HandleInertiaRequests).
 *
 *   const t = useT();
 *   t('nav.quotes')                          → "عروض الأسعار"
 *   t('pagination.showing', { from: 1, … })  → "عرض 1–20 من 54"
 *   t('errors.404.title', {}, 'fallback')    → fallback when the key is missing
 *   t.list('some.list')                      → string[]
 */
export function useT() {
    const translations = usePage().props.translations;

    const t = useCallback(
        /**
         * @param {string} key
         * @param {Record<string, string|number>} [replacements]
         * @param {string} [fallback]
         * @returns {string}
         */
        (key, replacements = {}, fallback) => {
            const value = lookup(translations, key);
            return replace(typeof value === 'string' ? value : (fallback ?? key), replacements);
        },
        [translations],
    );

    return useMemo(
        () =>
            Object.assign(t, {
                /** @param {string} key @returns {string[]} */
                list: (key) => {
                    const value = lookup(translations, key);
                    return Array.isArray(value) ? value : [];
                },
            }),
        [t, translations],
    );
}
