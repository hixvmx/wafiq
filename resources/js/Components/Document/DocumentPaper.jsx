import PropTypes from 'prop-types';
import { formatDate } from '@/lib/format';
import { groupDecimal } from '@/lib/money';
import { useT } from '@/lib/i18n';
import { TotalsBox } from './TotalsBox';

/**
 * The document as the client sees it: letterhead, client, lines, totals, amount in words,
 * notes, terms. Used on the team's document page, the client's page and the print view.
 */
export function DocumentPaper({ document }) {
    const t = useT();
    const { company, client } = document;
    const isInvoice = document.type === 'invoice';
    const title = isInvoice
        ? company.vat_number
            ? t('documents.paper.tax_invoice_title')
            : t('documents.paper.invoice_title')
        : t('documents.paper.quote_title');
    const hasLineDiscount = document.lines.some((line) => Number(line.discount_percent) > 0);
    const hasTax = document.lines.some((line) => Number(line.tax_rate) > 0);

    return (
        <article
            className="rounded-card border-line text-ink shadow-card overflow-hidden border bg-white print:rounded-none print:border-0 print:shadow-none"
            style={{ '--paper-brand': company.brand_color }}
        >
            <div className="h-1.5 bg-(--paper-brand)" />
            <div className="space-y-8 p-6 sm:p-10">
                {/* Letterhead */}
                <header className="flex flex-wrap items-start justify-between gap-6">
                    <div className="space-y-1">
                        {company.logo_url ? <img src={company.logo_url} alt={company.name} className="mb-2 h-14 w-auto max-w-48 object-contain" /> : null}
                        <p className="text-lg font-bold">{company.legal_name || company.name}</p>
                        {company.address && <p className="text-ink-muted text-sm whitespace-pre-line">{company.address}</p>}
                        <p className="text-ink-muted flex flex-wrap gap-x-3 text-xs">
                            {company.vat_number && <span>{t('documents.paper.vat', { number: company.vat_number })}</span>}
                            {company.cr_number && <span>{t('documents.paper.cr', { number: company.cr_number })}</span>}
                        </p>
                        {(company.phone || company.email) && (
                            <p className="text-ink-muted text-xs" dir="ltr">
                                {[company.phone, company.email].filter(Boolean).join(' · ')}
                            </p>
                        )}
                    </div>
                    <div className="text-end">
                        <h1 className="text-2xl font-bold text-(--paper-brand)">{title}</h1>
                        <p className="mt-1 font-mono text-sm font-semibold" dir="ltr">
                            {document.number}
                        </p>
                        <dl className="mt-3 space-y-0.5 text-sm">
                            <DateRow label={t('documents.issue_date')} value={document.issue_date} />
                            {document.valid_until && <DateRow label={t('documents.valid_until')} value={document.valid_until} />}
                            {document.due_date && <DateRow label={t('documents.due_date')} value={document.due_date} />}
                        </dl>
                    </div>
                </header>

                {/* Client */}
                {client && (
                    <section className="bg-surface rounded-xl p-4">
                        <p className="text-ink-subtle text-xs font-medium">{t('documents.paper.to')}</p>
                        <p className="mt-1 font-semibold">{client.name}</p>
                        {client.contact_name && <p className="text-ink-muted text-sm">{client.contact_name}</p>}
                        {client.address && <p className="text-ink-muted text-sm whitespace-pre-line">{client.address}</p>}
                        <p className="text-ink-muted flex flex-wrap gap-x-3 text-xs">
                            {client.vat_number && <span>{t('documents.paper.vat', { number: client.vat_number })}</span>}
                            {client.cr_number && <span>{t('documents.paper.cr', { number: client.cr_number })}</span>}
                        </p>
                    </section>
                )}

                {/* Lines */}
                {/* Phones: one card per line. Wider screens: the table. */}
                <ul className="divide-line border-line divide-y border-y sm:hidden">
                    {document.lines.map((line, i) => (
                        <li key={line.id} className="flex gap-3 py-3 text-sm">
                            <span className="text-ink-subtle">{i + 1}</span>
                            <div className="min-w-0 flex-1">
                                <p className="font-medium">{line.name}</p>
                                {line.description && <p className="text-ink-muted mt-0.5 text-xs whitespace-pre-line">{line.description}</p>}
                                <p className="text-ink-muted mt-1 text-xs">
                                    <bdi dir="ltr">{line.qty}</bdi> {line.unit} × <bdi dir="ltr">{groupDecimal(line.unit_price)}</bdi>
                                    {Number(line.discount_percent) > 0 && (
                                        <>
                                            {' · '}
                                            {t('documents.paper.col_discount')} <bdi dir="ltr">{line.discount_percent}%</bdi>
                                        </>
                                    )}
                                    {Number(line.tax_rate) > 0 && (
                                        <>
                                            {' · '}
                                            {t('documents.paper.col_tax')} <bdi dir="ltr">{line.tax_rate}%</bdi>
                                        </>
                                    )}
                                </p>
                            </div>
                            <p className="shrink-0 font-semibold" dir="ltr">
                                {groupDecimal(line.net)}
                            </p>
                        </li>
                    ))}
                </ul>
                <div className="hidden sm:block">
                    <table className="w-full min-w-xl text-sm">
                        <thead>
                            <tr className="text-ink-muted border-b-2 border-(--paper-brand) text-xs">
                                <th className="py-2 pe-2 text-start font-medium">{t('documents.paper.col_number')}</th>
                                <th className="py-2 pe-2 text-start font-medium">{t('documents.paper.col_item')}</th>
                                <th className="px-2 py-2 text-end font-medium">{t('documents.paper.col_qty')}</th>
                                <th className="px-2 py-2 text-end font-medium">{t('documents.paper.col_price')}</th>
                                {hasLineDiscount && <th className="px-2 py-2 text-end font-medium">{t('documents.paper.col_discount')}</th>}
                                {hasTax && <th className="px-2 py-2 text-end font-medium">{t('documents.paper.col_tax')}</th>}
                                <th className="py-2 ps-2 text-end font-medium">{t('documents.paper.col_amount')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {document.lines.map((line, i) => (
                                <tr key={line.id} className="border-line border-b align-top">
                                    <td className="text-ink-subtle py-3 pe-2">{i + 1}</td>
                                    <td className="py-3 pe-2">
                                        <p className="font-medium">{line.name}</p>
                                        {line.description && <p className="text-ink-muted mt-0.5 text-xs whitespace-pre-line">{line.description}</p>}
                                    </td>
                                    <td className="px-2 py-3 text-end whitespace-nowrap">
                                        <span dir="ltr">{line.qty}</span> {line.unit}
                                    </td>
                                    <td className="px-2 py-3 text-end" dir="ltr">
                                        {groupDecimal(line.unit_price)}
                                    </td>
                                    {hasLineDiscount && (
                                        <td className="px-2 py-3 text-end" dir="ltr">
                                            {Number(line.discount_percent) > 0 ? `${line.discount_percent}%` : '—'}
                                        </td>
                                    )}
                                    {hasTax && (
                                        <td className="px-2 py-3 text-end" dir="ltr">
                                            {Number(line.tax_rate) > 0 ? `${line.tax_rate}%` : '—'}
                                        </td>
                                    )}
                                    <td className="py-3 ps-2 text-end font-medium" dir="ltr">
                                        {groupDecimal(line.net)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Totals + amount in words */}
                <div className="grid gap-6 sm:grid-cols-[minmax(0,1fr)_20rem]">
                    <div className="order-2 sm:order-1">
                        <p className="text-ink-subtle text-xs font-medium">{t('documents.paper.amount_in_words')}</p>
                        <p className="mt-1 text-sm leading-relaxed font-medium">{document.amount_in_words}</p>
                    </div>
                    <div className="order-1 sm:order-2">
                        <TotalsBox
                            currency={document.currency}
                            subtotal={groupDecimal(document.subtotal)}
                            discount={groupDecimal(document.discount)}
                            discountLabel={document.discount_label}
                            taxable={groupDecimal(document.taxable)}
                            taxes={document.tax_breakdown.map((tax) => ({ ...tax, amount: groupDecimal(tax.amount) }))}
                            total={groupDecimal(document.total)}
                        />
                    </div>
                </div>

                {(document.notes || document.terms || (isInvoice && company.bank_details)) && (
                    <div className="border-line grid gap-6 border-t pt-6 sm:grid-cols-2">
                        {document.notes && <TextBlock title={t('documents.paper.notes')} text={document.notes} />}
                        {document.terms && <TextBlock title={t('documents.paper.terms')} text={document.terms} />}
                        {isInvoice && company.bank_details && <TextBlock title={t('documents.paper.bank_details')} text={company.bank_details} />}
                    </div>
                )}

                {(company.stamp_url || company.signature_url) && (
                    <div className="flex justify-end gap-6">
                        {company.signature_url && <img src={company.signature_url} alt="" className="h-20 w-auto object-contain" />}
                        {company.stamp_url && <img src={company.stamp_url} alt="" className="h-24 w-auto object-contain" />}
                    </div>
                )}
            </div>
        </article>
    );
}

DocumentPaper.propTypes = {
    document: PropTypes.shape({
        type: PropTypes.string.isRequired,
        number: PropTypes.string.isRequired,
        company: PropTypes.object.isRequired,
        client: PropTypes.object,
        lines: PropTypes.array.isRequired,
        currency: PropTypes.string.isRequired,
        subtotal: PropTypes.string.isRequired,
        discount: PropTypes.string,
        discount_label: PropTypes.string,
        taxable: PropTypes.string,
        tax_breakdown: PropTypes.array.isRequired,
        total: PropTypes.string.isRequired,
        amount_in_words: PropTypes.string.isRequired,
        issue_date: PropTypes.string,
        valid_until: PropTypes.string,
        due_date: PropTypes.string,
        notes: PropTypes.string,
        terms: PropTypes.string,
    }).isRequired,
};

function DateRow({ label, value }) {
    return (
        <div className="flex justify-end gap-2">
            <dt className="text-ink-muted">{label}:</dt>
            <dd className="font-medium">{formatDate(value)}</dd>
        </div>
    );
}

DateRow.propTypes = { label: PropTypes.string.isRequired, value: PropTypes.string };

function TextBlock({ title, text }) {
    return (
        <div>
            <p className="text-ink-subtle text-xs font-medium">{title}</p>
            <p className="mt-1 text-sm leading-relaxed whitespace-pre-line">{text}</p>
        </div>
    );
}

TextBlock.propTypes = { title: PropTypes.string.isRequired, text: PropTypes.string.isRequired };
