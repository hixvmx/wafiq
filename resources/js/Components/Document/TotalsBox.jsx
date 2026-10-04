import PropTypes from 'prop-types';
import { useT } from '@/lib/i18n';

/**
 * Subtotal → discount → tax → total. Values are already-formatted strings,
 * so the same box serves the live editor and the saved document.
 */
export function TotalsBox({ currency, subtotal, discount, discountLabel, taxable, taxes, total, children }) {
    const t = useT();
    const hasDiscount = discount && !/^0[.,0]*$/.test(discount);

    return (
        <dl className="space-y-2 text-sm">
            <Row label={t('documents.totals.subtotal')} value={subtotal} />
            {children}
            {hasDiscount && (
                <>
                    <Row label={`${t('documents.totals.discount')}${discountLabel ? ` (${discountLabel})` : ''}`} value={`− ${discount}`} />
                    <Row label={t('documents.totals.taxable')} value={taxable} />
                </>
            )}
            {taxes.map((tax) => (
                <Row key={`${tax.name}-${tax.rate}`} label={`${tax.name ?? t('documents.totals.tax')} ${tax.rate}%`} value={tax.amount} />
            ))}
            <div className="flex items-baseline justify-between border-t border-line pt-3">
                <dt className="text-base font-bold text-ink">{t('documents.totals.total')}</dt>
                <dd className="text-lg font-bold text-ink" dir="ltr">
                    {total} <span className="text-sm font-semibold text-ink-muted">{currency}</span>
                </dd>
            </div>
        </dl>
    );
}

TotalsBox.propTypes = {
    currency: PropTypes.string.isRequired,
    subtotal: PropTypes.string.isRequired,
    discount: PropTypes.string,
    discountLabel: PropTypes.string,
    taxable: PropTypes.string,
    taxes: PropTypes.arrayOf(PropTypes.shape({ name: PropTypes.string, rate: PropTypes.string, amount: PropTypes.string })).isRequired,
    total: PropTypes.string.isRequired,
    children: PropTypes.node,
};

function Row({ label, value }) {
    return (
        <div className="flex items-baseline justify-between gap-4">
            <dt className="text-ink-muted">{label}</dt>
            <dd className="font-medium text-ink" dir="ltr">
                {value}
            </dd>
        </div>
    );
}

Row.propTypes = { label: PropTypes.string.isRequired, value: PropTypes.string };
