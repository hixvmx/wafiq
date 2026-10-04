import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import PropTypes from 'prop-types';
import { cn } from '@/lib/format';
import { useT } from '@/lib/i18n';

/** Numbered pagination built from Laravel's paginator links (RTL: "previous" points right). */
export function Pagination({ meta }) {
    const t = useT();

    if (meta.last_page <= 1) return null;

    // Laravel's first and last links are prev/next; the middle ones are page numbers or "...".
    const pages = meta.links.slice(1, -1);
    const prev = meta.links[0]?.url;
    const next = meta.links[meta.links.length - 1]?.url;
    const itemClass = 'inline-flex h-10 min-w-10 items-center justify-center rounded-xl border px-3 text-sm font-medium transition-colors';

    return (
        <nav className="mt-8 flex flex-col items-center gap-3" aria-label="pagination">
            <div className="flex flex-wrap items-center justify-center gap-1.5">
                <PageLink url={prev} className={itemClass} label={t('pagination.previous')}>
                    <ChevronRight className="size-4" />
                </PageLink>
                {pages.map((link, i) =>
                    link.url ? (
                        <Link
                            key={i}
                            href={link.url}
                            preserveScroll={false}
                            aria-current={link.active ? 'page' : undefined}
                            className={cn(itemClass, link.active ? 'border-brand-600 bg-brand-600 text-white' : 'border-line bg-card text-ink hover:bg-surface')}
                        >
                            {link.label}
                        </Link>
                    ) : (
                        <span key={i} className="px-1 text-ink-subtle">
                            …
                        </span>
                    ),
                )}
                <PageLink url={next} className={itemClass} label={t('pagination.next')}>
                    <ChevronLeft className="size-4" />
                </PageLink>
            </div>
            {meta.from !== null && (
                <p className="text-xs text-ink-subtle">{t('pagination.showing', { from: meta.from, to: meta.to ?? meta.from, total: meta.total })}</p>
            )}
        </nav>
    );
}

Pagination.propTypes = {
    meta: PropTypes.shape({
        last_page: PropTypes.number.isRequired,
        from: PropTypes.number,
        to: PropTypes.number,
        total: PropTypes.number.isRequired,
        links: PropTypes.arrayOf(PropTypes.shape({ url: PropTypes.string, label: PropTypes.string, active: PropTypes.bool })).isRequired,
    }).isRequired,
};

function PageLink({ url, className, label, children }) {
    if (!url) {
        return (
            <span className={cn(className, 'cursor-not-allowed border-line bg-card text-ink-subtle opacity-50')} aria-label={label}>
                {children}
            </span>
        );
    }

    return (
        <Link href={url} className={cn(className, 'border-line bg-card text-ink hover:bg-surface')} aria-label={label}>
            {children}
        </Link>
    );
}

PageLink.propTypes = { url: PropTypes.string, className: PropTypes.string, label: PropTypes.string, children: PropTypes.node };
