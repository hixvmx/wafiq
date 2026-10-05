import { Link, router } from '@inertiajs/react';
import { AlertTriangle, FileText, Plus, Receipt, Search } from 'lucide-react';
import PropTypes from 'prop-types';
import { useEffect, useRef, useState } from 'react';
import { StatusBadge } from '@/Components/Document/StatusBadge';
import { ButtonLink } from '@/Components/ui/Button';
import { Card, EmptyState } from '@/Components/ui/Card';
import { Pagination } from '@/Components/ui/Pagination';
import AppLayout from '@/Layouts/AppLayout';
import { cn, formatDate, timeAgo } from '@/lib/format';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { prefix } from './Form';

const STATUSES = ['draft', 'sent', 'viewed', 'approved', 'rejected', 'expired'];

/** Quotations or invoices: status tabs with counts, search, "not opened" filter. */
export default function DocumentsIndex({ type, documents, filters, counts, canCreate }) {
    const t = useT();
    const url = `/${prefix(type)}`;
    const total = Object.values(counts).reduce((sum, n) => sum + Number(n), 0);
    const filtered = Boolean(filters.q || filters.status || filters.not_viewed);

    const visit = (params) => {
        const query = { q: filters.q, status: filters.status, not_viewed: filters.not_viewed ? 1 : undefined, ...params };
        router.get(url, Object.fromEntries(Object.entries(query).filter(([, v]) => v)), { preserveState: true, preserveScroll: true, replace: true });
    };

    const createButton = canCreate && (
        <ButtonLink href={`${url}/create`} icon={<Plus className="size-4" />}>
            {t(`documents.types.${type}.new`)}
        </ButtonLink>
    );

    return (
        <AppLayout title={t(`documents.types.${type}.many`)} actions={createButton}>
            <div className="mx-auto max-w-5xl space-y-4">
                {/* Status tabs */}
                <div className="-mx-4 flex gap-1 overflow-x-auto px-4 pb-1">
                    <Tab
                        active={!filters.status && !filters.not_viewed}
                        onClick={() => visit({ status: undefined, not_viewed: undefined })}
                        label={t('documents.all')}
                        count={total}
                    />
                    {STATUSES.map((status) => (
                        <Tab
                            key={status}
                            active={filters.status === status}
                            onClick={() => visit({ status, not_viewed: undefined })}
                            label={t(`statuses.${status}`)}
                            count={counts[status] ?? 0}
                        />
                    ))}
                    <Tab
                        active={filters.not_viewed}
                        onClick={() => visit({ status: undefined, not_viewed: filters.not_viewed ? undefined : 1 })}
                        label={t('documents.not_viewed')}
                        warning
                    />
                </div>

                <SearchBox initial={filters.q} placeholder={t('documents.search')} onSearch={(q) => visit({ q: q || undefined })} />

                {documents.data.length === 0 ? (
                    filtered ? (
                        <EmptyState title={t('documents.no_results')} />
                    ) : (
                        <EmptyState
                            icon={type === 'invoice' ? <Receipt className="size-8" /> : <FileText className="size-8" />}
                            title={t(`documents.types.${type}.empty`)}
                            text={t(`documents.types.${type}.empty_text`)}
                            action={createButton}
                        />
                    )
                ) : (
                    <Card>
                        <ul className="divide-line divide-y">
                            {documents.data.map((document) => (
                                <li key={document.id}>
                                    <Link href={`${url}/${document.id}`} className="hover:bg-surface flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-4">
                                        {/* On phones the details take the first line, amount and status the second. */}
                                        <div className="w-full min-w-0 sm:w-auto sm:flex-1">
                                            <p className="text-ink flex min-w-0 items-center gap-2 text-sm font-semibold">
                                                <span className="shrink-0 font-mono whitespace-nowrap" dir="ltr">
                                                    {document.number}
                                                </span>
                                                <span className="text-ink-muted min-w-0 truncate font-normal">· {document.client_name}</span>
                                            </p>
                                            <p className="text-ink-subtle mt-1 flex flex-wrap gap-x-3 text-xs">
                                                <span>{formatDate(document.issue_date)}</span>
                                                {document.created_by && <span>{t('documents.created_by', { name: document.created_by })}</span>}
                                                {document.not_viewed_warning && (
                                                    <span className="text-warning inline-flex items-center gap-1 font-medium">
                                                        <AlertTriangle className="size-3.5" />
                                                        {t('documents.not_viewed_warning', { time: timeAgo(document.sent_at) })}
                                                    </span>
                                                )}
                                            </p>
                                        </div>
                                        <p className="text-ink text-sm font-semibold" dir="ltr">
                                            {formatMoney(document.total, document.currency)}
                                        </p>
                                        <StatusBadge status={document.status} />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </Card>
                )}

                <Pagination meta={documents} />
            </div>
        </AppLayout>
    );
}

DocumentsIndex.propTypes = {
    type: PropTypes.oneOf(['quote', 'invoice']).isRequired,
    documents: PropTypes.shape({ data: PropTypes.array.isRequired }).isRequired,
    filters: PropTypes.shape({ q: PropTypes.string, status: PropTypes.string, not_viewed: PropTypes.bool }).isRequired,
    counts: PropTypes.objectOf(PropTypes.number).isRequired,
    canCreate: PropTypes.bool,
};

function Tab({ active, onClick, label, count, warning }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            className={cn(
                'inline-flex shrink-0 items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors',
                active ? 'bg-card text-brand-700 shadow-card ring-line ring-1' : 'text-ink-muted hover:bg-card hover:text-ink',
                warning && !active && 'text-warning',
            )}
        >
            {warning && <AlertTriangle className="size-4" />}
            {label}
            {count !== undefined && <span className="bg-surface text-ink-subtle ring-line rounded-full px-1.5 text-xs ring-1">{count}</span>}
        </button>
    );
}

Tab.propTypes = {
    active: PropTypes.bool,
    onClick: PropTypes.func.isRequired,
    label: PropTypes.string.isRequired,
    count: PropTypes.number,
    warning: PropTypes.bool,
};

/** Searches as you type (debounced). */
function SearchBox({ initial, placeholder, onSearch }) {
    const [q, setQ] = useState(initial ?? '');
    const first = useRef(true);

    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const timer = setTimeout(() => onSearch(q), 300);
        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps -- only when the text changes
    }, [q]);

    return (
        <div className="relative">
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
    );
}

SearchBox.propTypes = { initial: PropTypes.string, placeholder: PropTypes.string.isRequired, onSearch: PropTypes.func.isRequired };
