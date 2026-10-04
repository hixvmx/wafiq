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
                    <Row
                        label={
                            <>
                                {t('documents.totals.discount')}
                                {discountLabel && (
                                    <>
                                        {' ('}
                                        <bdi dir="ltr">{discountLabel}</bdi>)
                                    </>
                                )}
                            </>
                        }
                        value={`− ${discount}`}
                    />
                    <Row label={t('documents.totals.taxable')} value={taxable} />
                </>
            )}
            {taxes.map((tax) => (
                <Row
                    key={`${tax.name}-${tax.rate}`}
                    label={
                        <>
                            {tax.name ?? t('documents.totals.tax')} <bdi dir="ltr">{tax.rate}%</bdi>
                        </>
                    }
                    value={tax.amount}
                />
            ))}
            <div className="border-line flex items-baseline justify-between border-t pt-3">
                <dt className="text-ink text-base font-bold">{t('documents.totals.total')}</dt>
                <dd className="text-ink text-lg font-bold" dir="ltr">
                    {total} <span className="text-ink-muted text-sm font-semibold">{currency}</span>
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
            <dd className="text-ink font-medium" dir="ltr">
                {value}
            </dd>
        </div>
    );
}

// Labels can mix Arabic and a left-to-right rate: <bdi dir="ltr"> keeps "5%" from turning into "%5".
Row.propTypes = { label: PropTypes.node.isRequired, value: PropTypes.string };
