import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import PropTypes from 'prop-types';
import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/format';

/**
 * Search box + type filter for list pages. Updates the URL (?q=…&type=…) as you type,
 * so results can be shared and survive a refresh.
 */
export function ListToolbar({ url, filters, placeholder, types, action }) {
    const [q, setQ] = useState(filters.q ?? '');
    const first = useRef(true);

    const visit = (params) =>
        router.get(url, Object.fromEntries(Object.entries(params).filter(([, v]) => v)), { preserveState: true, preserveScroll: true, replace: true });

    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const timer = setTimeout(() => visit({ q, type: filters.type }), 300);
        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps -- only re-run when the text changes
    }, [q]);

    return (
        <div className="flex flex-wrap items-center gap-3">
            <div className="relative min-w-56 flex-1">
                <Search className="text-ink-subtle pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2" />
                <input
                    type="search"
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    placeholder={placeholder}
                    aria-label={placeholder}
                    className="border-line-strong bg-card placeholder:text-ink-subtle focus:border-brand-500 focus:ring-brand-100 h-11 w-full rounded-xl border ps-9 pe-3 text-sm focus:ring-3 focus:outline-none"
                />
            </div>
            {types && (
                <div className="border-line-strong bg-card flex rounded-xl border p-1">
                    {types.map(({ value, label }) => (
                        <button
                            key={value ?? 'all'}
                            type="button"
                            onClick={() => visit({ q, type: value })}
                            aria-pressed={filters.type === value}
                            className={cn(
                                'rounded-lg px-3 py-1.5 text-sm font-medium transition-colors',
                                filters.type === value ? 'bg-brand-600 text-white' : 'text-ink-muted hover:text-ink',
                            )}
                        >
                            {label}
                        </button>
                    ))}
                </div>
            )}
            {action}
        </div>
    );
}

ListToolbar.propTypes = {
    url: PropTypes.string.isRequired,
    filters: PropTypes.shape({ q: PropTypes.string, type: PropTypes.string }).isRequired,
    placeholder: PropTypes.string.isRequired,
    types: PropTypes.arrayOf(PropTypes.shape({ value: PropTypes.string, label: PropTypes.string.isRequired })),
    action: PropTypes.node,
};
