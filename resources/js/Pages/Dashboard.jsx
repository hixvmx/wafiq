import { Link, router } from '@inertiajs/react';
import { CheckCircle2, Clock, Eye, EyeOff, FileEdit, Hourglass, Plus, Send, Timer, XCircle } from 'lucide-react';
import PropTypes from 'prop-types';
import { StatusBadge } from '@/Components/Document/StatusBadge';
import { ButtonLink } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Select } from '@/Components/ui/Field';
import AppLayout from '@/Layouts/AppLayout';
import { cn, formatDate, timeAgo } from '@/lib/format';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';

const STATUSES = [
    ['draft', FileEdit],
    ['sent', Send],
    ['viewed', Eye],
    ['approved', CheckCircle2],
    ['rejected', XCircle],
    ['expired', Clock],
];

const ATTENTION = [
    ['not_viewed', EyeOff],
    ['no_answer', Hourglass],
    ['expiring', Timer],
];

/** "12,500 SAR": whole units are enough for a headline figure. */
const headline = (value, currency) => `${new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(Number(value))} ${currency}`;

/**
 * Home page: what's waiting for the client, approval rate and speed, the pipeline by status,
 * and the documents that need a follow-up now.
 */
export default function Dashboard({ type, period, currency, pipeline, kpis, attention, canCreate }) {
    const t = useT();
    const prefix = type === 'invoice' ? '/invoices' : '/quotes';
    const visit = (params) => router.get('/', { type, period, ...params }, { preserveState: true, preserveScroll: true, replace: true });

    return (
        <AppLayout
            title={t('nav.dashboard')}
            actions={
                canCreate && (
                    <ButtonLink href={`${prefix}/create`} icon={<Plus className="size-4" />} className="hidden sm:inline-flex">
                        {t(`documents.types.${type}.new`)}
                    </ButtonLink>
                )
            }
        >
            <div className="mx-auto max-w-6xl space-y-6">
                {/* Filters: one row above everything they affect. */}
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="border-line-strong bg-card flex rounded-xl border p-1" role="tablist">
                        {['quote', 'invoice'].map((key) => (
                            <button
                                key={key}
                                type="button"
                                role="tab"
                                aria-selected={type === key}
                                onClick={() => visit({ type: key })}
                                className={cn(
                                    'rounded-lg px-4 py-1.5 text-sm font-medium',
                                    type === key ? 'bg-brand-600 text-white' : 'text-ink-muted hover:text-ink',
                                )}
                            >
                                {t(`documents.types.${key}.many`)}
                            </button>
                        ))}
                    </div>
                    <Select value={period} onChange={(e) => visit({ period: e.target.value })} className="h-10 w-44" aria-label={t('dashboard.period')}>
                        {['30', '90', '365', 'all'].map((key) => (
                            <option key={key} value={key}>
                                {t(`dashboard.periods.${key}`)}
                            </option>
                        ))}
                    </Select>
                </div>

                <Kpis kpis={kpis} currency={currency} />

                <Card className="p-5">
                    <h2 className="text-ink text-base font-bold">{t('dashboard.pipeline')}</h2>
                    <p className="text-ink-subtle mt-0.5 mb-4 text-xs">{t('dashboard.pipeline_hint')}</p>
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                        {STATUSES.map(([status, Icon]) => (
                            <Link
                                key={status}
                                href={`${prefix}?status=${status}`}
                                className="border-line hover:border-brand-300 hover:bg-surface rounded-xl border p-3 transition-colors"
                            >
                                <p className="text-ink-muted flex items-center gap-1.5 text-xs font-medium">
                                    <Icon className="size-3.5" />
                                    {t(`statuses.${status}`)}
                                </p>
                                <p className="text-ink mt-2 text-2xl font-semibold">{pipeline[status].count}</p>
                                <p className="text-ink-subtle mt-0.5 truncate text-xs" dir="ltr">
                                    {headline(pipeline[status].value, currency)}
                                </p>
                                <OtherCurrencies others={pipeline[status].others} />
                            </Link>
                        ))}
                    </div>
                </Card>

                <Attention attention={attention} prefix={prefix} />
            </div>
        </AppLayout>
    );
}

const amountsShape = PropTypes.shape({ value: PropTypes.string.isRequired, others: PropTypes.array.isRequired });

Dashboard.propTypes = {
    type: PropTypes.oneOf(['quote', 'invoice']).isRequired,
    period: PropTypes.string.isRequired,
    currency: PropTypes.string.isRequired,
    pipeline: PropTypes.objectOf(
        PropTypes.shape({ count: PropTypes.number.isRequired, value: PropTypes.string.isRequired, others: PropTypes.array.isRequired }),
    ).isRequired,
    kpis: PropTypes.shape({
        sent: PropTypes.number.isRequired,
        approved: PropTypes.number.isRequired,
        approval_rate: PropTypes.number,
        avg_hours_to_approval: PropTypes.number,
        waiting: amountsShape.isRequired,
    }).isRequired,
    attention: PropTypes.object.isRequired,
    canCreate: PropTypes.bool,
};

function Kpis({ kpis, currency }) {
    const t = useT();
    const hours = kpis.avg_hours_to_approval;
    const avgTime =
        hours === null
            ? t('dashboard.kpis.none')
            : hours < 48
              ? t('dashboard.kpis.hours', { count: Math.round(hours) })
              : t('dashboard.kpis.days', { count: Math.round((hours / 24) * 10) / 10 });

    return (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {/* Hero figure: the money the team is waiting on. */}
            <Tile
                label={t('dashboard.kpis.waiting')}
                hint={t('dashboard.kpis.waiting_hint', { count: kpis.waiting.count })}
                className="sm:col-span-2 lg:col-span-1"
            >
                <p className="text-ink text-3xl font-semibold" dir="ltr">
                    {headline(kpis.waiting.value, currency)}
                </p>
                <OtherCurrencies others={kpis.waiting.others} />
            </Tile>

            <Tile label={t('dashboard.kpis.approval_rate')} hint={t('dashboard.kpis.approval_hint', { approved: kpis.approved, sent: kpis.sent })}>
                <p className="text-ink text-3xl font-semibold" dir="ltr">
                    {kpis.approval_rate === null ? t('dashboard.kpis.none') : `${kpis.approval_rate}%`}
                </p>
                {/* Meter: the track is a lighter step of the same hue as the fill. */}
                <div
                    className="bg-brand-100 mt-3 h-2 overflow-hidden rounded-full"
                    role="meter"
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuenow={kpis.approval_rate ?? 0}
                    aria-label={t('dashboard.kpis.approval_rate')}
                >
                    <div className="bg-brand-600 h-full rounded-full" style={{ width: `${kpis.approval_rate ?? 0}%` }} />
                </div>
            </Tile>

            <Tile label={t('dashboard.kpis.avg_time')} hint={t('dashboard.kpis.avg_time_hint')}>
                <p className="text-ink text-3xl font-semibold">{avgTime}</p>
            </Tile>

            <Tile label={t('dashboard.kpis.sent')} hint={t('dashboard.kpis.sent_hint')}>
                <p className="text-ink text-3xl font-semibold">{kpis.sent}</p>
            </Tile>
        </div>
    );
}

Kpis.propTypes = { kpis: Dashboard.propTypes.kpis, currency: PropTypes.string.isRequired };

/** Stat tile: label, value, a short explanation. */
function Tile({ label, hint, className, children }) {
    return (
        <Card className={cn('p-5', className)}>
            <p className="text-ink-muted text-sm font-medium">{label}</p>
            <div className="mt-2">{children}</div>
            {hint && <p className="text-ink-subtle mt-2 text-xs">{hint}</p>}
        </Card>
    );
}

Tile.propTypes = { label: PropTypes.string.isRequired, hint: PropTypes.string, className: PropTypes.string, children: PropTypes.node };

/** Totals in other currencies are listed, never converted. */
function OtherCurrencies({ others }) {
    const t = useT();
    if (!others.length) return null;

    return (
        <p className="text-ink-subtle mt-0.5 truncate text-xs">
            {t('dashboard.other_currencies', { amounts: '' })}
            <span dir="ltr">{others.map((o) => headline(o.value, o.currency)).join(' · ')}</span>
        </p>
    );
}

OtherCurrencies.propTypes = { others: PropTypes.arrayOf(PropTypes.shape({ currency: PropTypes.string, value: PropTypes.string })).isRequired };

function Attention({ attention, prefix }) {
    const t = useT();
    const total = ATTENTION.reduce((sum, [key]) => sum + attention[key].count, 0);

    return (
        <Card className="p-5">
            <h2 className="text-ink mb-4 text-base font-bold">{t('dashboard.attention')}</h2>
            {total === 0 ? (
                <p className="text-ink-muted flex items-center gap-2 text-sm">
                    <CheckCircle2 className="text-success size-4" />
                    {t('dashboard.attention_empty')}
                </p>
            ) : (
                <div className="grid gap-5 lg:grid-cols-3">
                    {ATTENTION.map(([key, Icon]) => (
                        <section key={key}>
                            <h3 className="text-ink-muted mb-2 flex items-center gap-1.5 text-sm font-semibold">
                                <Icon className="text-warning size-4" />
                                {t(`dashboard.groups.${key}`)}
                                <span className="bg-surface text-ink-subtle ring-line rounded-full px-1.5 text-xs ring-1">{attention[key].count}</span>
                            </h3>
                            <ul className="divide-line divide-y">
                                {attention[key].items.map((doc) => (
                                    <li key={doc.id}>
                                        <Link href={`${prefix}/${doc.id}`} className="hover:bg-surface -mx-2 flex items-center gap-3 rounded-lg px-2 py-2">
                                            <div className="min-w-0 flex-1">
                                                <p className="text-ink truncate text-sm">
                                                    <span className="font-mono font-semibold" dir="ltr">
                                                        {doc.number}
                                                    </span>{' '}
                                                    · {doc.client_name}
                                                </p>
                                                <p className="text-ink-subtle text-xs">
                                                    {key === 'not_viewed' && t('dashboard.sent_ago', { time: timeAgo(doc.sent_at) })}
                                                    {key === 'no_answer' && t('dashboard.viewed_ago', { time: timeAgo(doc.first_viewed_at) })}
                                                    {key === 'expiring' && t('dashboard.expires_on', { date: formatDate(doc.valid_until) })}
                                                    {' · '}
                                                    <span dir="ltr">{formatMoney(doc.total, doc.currency)}</span>
                                                </p>
                                            </div>
                                            <StatusBadge status={doc.status} />
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                            {attention[key].count > attention[key].items.length && key === 'not_viewed' && (
                                <Link href={`${prefix}?not_viewed=1`} className="text-brand-700 mt-2 inline-block text-xs font-medium hover:underline">
                                    {t('dashboard.see_all', { count: attention[key].count })}
                                </Link>
                            )}
                        </section>
                    ))}
                </div>
            )}
        </Card>
    );
}

Attention.propTypes = { attention: PropTypes.object.isRequired, prefix: PropTypes.string.isRequired };
