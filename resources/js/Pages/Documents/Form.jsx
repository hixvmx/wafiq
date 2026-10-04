import { Link, useForm } from '@inertiajs/react';
import { ArrowRight, Save } from 'lucide-react';
import PropTypes from 'prop-types';
import { useMemo, useState } from 'react';
import { ClientDialog } from '@/Components/Clients/ClientDialog';
import { ClientPicker } from '@/Components/Document/ClientPicker';
import { LineItemsEditor, newLine, withKeys } from '@/Components/Document/LineItemsEditor';
import { TotalsBox } from '@/Components/Document/TotalsBox';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Field, Select, TextArea, TextInput } from '@/Components/ui/Field';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/format';
import { useT } from '@/lib/i18n';
import { calculateTotals, formatMinor, toMinor } from '@/lib/totals';

/** Create or edit a draft quotation / invoice. */
export default function DocumentForm({ type, document, values, taxRates, currencies, phoneCodes, units, canManageClients }) {
    const t = useT();
    const defaultTaxId = taxRates.find((rate) => rate.is_default)?.id ?? null;
    const [newClient, setNewClient] = useState(false);

    const form = useForm({
        ...values,
        lines: values.lines.length ? withKeys(values.lines) : [newLine(defaultTaxId)],
    });
    const { data, setData, errors } = form;

    const decimals = currencies.find((c) => c.code === data.currency)?.decimals ?? 2;
    const rateOf = (id) => taxRates.find((rate) => rate.id === id);

    // Live totals, same algorithm as the server (which recalculates on save).
    const totals = useMemo(
        () =>
            calculateTotals(
                data.lines.map((line) => ({
                    qty: line.qty,
                    unit_price_minor: toMinor(line.unit_price, decimals),
                    discount: line.discount,
                    tax_rate: String(rateOf(line.tax_rate_id)?.rate ?? 0),
                })),
                data.discount_type === 'amount'
                    ? { type: 'amount', value: toMinor(data.discount_value, decimals) }
                    : { type: 'percent', value: data.discount_value },
            ),
        // eslint-disable-next-line react-hooks/exhaustive-deps -- rateOf only reads taxRates
        [data.lines, data.discount_type, data.discount_value, decimals, taxRates],
    );

    const taxes = useMemo(() => {
        const groups = new Map();
        data.lines.forEach((line, i) => {
            const rate = rateOf(line.tax_rate_id);
            if (!rate || totals.lines[i].tax === 0n) return;
            const group = groups.get(rate.id) ?? { name: rate.name, rate: String(rate.rate), amount: 0n };
            group.amount += totals.lines[i].tax;
            groups.set(rate.id, group);
        });
        return [...groups.values()].map((group) => ({ ...group, amount: formatMinor(group.amount, decimals) }));
        // eslint-disable-next-line react-hooks/exhaustive-deps -- rateOf only reads taxRates
    }, [data.lines, totals, decimals, taxRates]);

    const submit = (e) => {
        e.preventDefault();
        form.transform(({ client, lines, ...rest }) => ({
            ...rest,
            client_id: client?.id ?? null,
            lines: lines.map(({ key, ...line }) => line), // eslint-disable-line no-unused-vars
        }));
        document ? form.put(`/${prefix(type)}/${document.id}`) : form.post(`/${prefix(type)}`);
    };

    const title = document ? t('documents.form.edit_title', { number: document.number }) : t(`documents.types.${type}.new`);
    const linesHaveErrors = Object.keys(errors).some((key) => key === 'lines' || key.startsWith('lines.'));

    return (
        <AppLayout
            title={title}
            actions={
                <Link
                    href={document ? `/${prefix(type)}/${document.id}` : `/${prefix(type)}`}
                    className="text-ink-muted hover:text-ink hidden text-sm sm:inline-flex sm:items-center sm:gap-1"
                >
                    <ArrowRight className="size-4" />
                    {t('documents.actions.back')}
                </Link>
            }
        >
            <form onSubmit={submit} className="mx-auto max-w-6xl space-y-5 pb-24">
                <Card className="grid gap-5 p-5 md:grid-cols-[minmax(0,1fr)_auto]">
                    <Field label={t('documents.client')} error={undefined}>
                        {() => (
                            <ClientPicker
                                client={data.client}
                                onChange={(client) => setData('client', client)}
                                onAddNew={() => setNewClient(true)}
                                canAdd={canManageClients}
                                error={errors.client_id}
                            />
                        )}
                    </Field>
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                        <Field label={t('documents.issue_date')} error={errors.issue_date}>
                            {(id) => (
                                <TextInput
                                    id={id}
                                    type="date"
                                    dir="ltr"
                                    value={data.issue_date}
                                    onChange={(e) => setData('issue_date', e.target.value)}
                                    error={errors.issue_date}
                                />
                            )}
                        </Field>
                        {type === 'quote' ? (
                            <Field label={t('documents.valid_until')} error={errors.valid_until}>
                                {(id) => (
                                    <TextInput
                                        id={id}
                                        type="date"
                                        dir="ltr"
                                        value={data.valid_until ?? ''}
                                        onChange={(e) => setData('valid_until', e.target.value)}
                                        error={errors.valid_until}
                                    />
                                )}
                            </Field>
                        ) : (
                            <Field label={t('documents.due_date')} error={errors.due_date}>
                                {(id) => (
                                    <TextInput
                                        id={id}
                                        type="date"
                                        dir="ltr"
                                        value={data.due_date ?? ''}
                                        onChange={(e) => setData('due_date', e.target.value)}
                                        error={errors.due_date}
                                    />
                                )}
                            </Field>
                        )}
                        <Field label={t('documents.currency')} error={errors.currency}>
                            {(id) => (
                                <Select id={id} value={data.currency} onChange={(e) => setData('currency', e.target.value)} error={errors.currency}>
                                    {currencies.map(({ code }) => (
                                        <option key={code} value={code}>
                                            {code}
                                        </option>
                                    ))}
                                </Select>
                            )}
                        </Field>
                    </div>
                </Card>

                <Card className="p-5">
                    <h2 className="text-ink mb-4 text-base font-bold">{t('documents.form.lines')}</h2>
                    {linesHaveErrors && (
                        <p className="text-danger mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm" role="alert">
                            {errors.lines ?? t('documents.form.lines_error')}
                        </p>
                    )}
                    <LineItemsEditor
                        lines={data.lines}
                        onChange={(lines) => setData('lines', lines)}
                        taxRates={taxRates}
                        units={units}
                        currency={data.currency}
                        decimals={decimals}
                        amounts={totals.lines.map((line) => line.net)}
                        errors={errors}
                    />
                </Card>

                <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_24rem]">
                    <Card className="space-y-5 p-5">
                        <Field label={t('documents.form.notes')} hint={t('documents.form.notes_hint')} error={errors.notes}>
                            {(id, describedBy) => (
                                <TextArea
                                    id={id}
                                    className="min-h-20"
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                    error={errors.notes}
                                    aria-describedby={describedBy}
                                />
                            )}
                        </Field>
                        <Field label={t('documents.form.terms')} error={errors.terms}>
                            {(id) => (
                                <TextArea
                                    id={id}
                                    className="min-h-28"
                                    value={data.terms}
                                    onChange={(e) => setData('terms', e.target.value)}
                                    error={errors.terms}
                                />
                            )}
                        </Field>
                    </Card>

                    <Card className="h-fit p-5">
                        <TotalsBox
                            currency={data.currency}
                            subtotal={formatMinor(totals.subtotal, decimals)}
                            discount={formatMinor(totals.discount, decimals)}
                            discountLabel={data.discount_type === 'percent' && data.discount_value ? `${data.discount_value}%` : null}
                            taxable={formatMinor(totals.taxable, decimals)}
                            taxes={taxes}
                            total={formatMinor(totals.total, decimals)}
                        >
                            <DiscountInput form={form} />
                        </TotalsBox>
                        <p className="text-ink-subtle mt-3 text-xs">{t('documents.form.totals_note')}</p>
                    </Card>
                </div>

                {/* Save bar stays in reach on long documents. */}
                <div className="border-line bg-card/95 fixed inset-x-0 bottom-0 z-20 border-t px-4 py-3 backdrop-blur lg:ps-68">
                    <div className="mx-auto flex max-w-6xl items-center justify-between gap-3">
                        <p className="text-ink-muted text-sm">
                            {t('documents.totals.total')}:{' '}
                            <span className="text-ink font-bold" dir="ltr">
                                {formatMinor(totals.total, decimals)} {data.currency}
                            </span>
                        </p>
                        <Button type="submit" icon={<Save className="size-4" />} disabled={form.processing}>
                            {form.processing ? t('common.saving') : t('documents.form.save')}
                        </Button>
                    </div>
                </div>
            </form>

            {/* Outside the <form>: a form can't contain another form. */}
            <ClientDialog
                open={newClient}
                phoneCodes={phoneCodes}
                onClose={() => setNewClient(false)}
                onCreated={(client) => {
                    setData('client', client);
                    setNewClient(false);
                }}
            />
        </AppLayout>
    );
}

DocumentForm.propTypes = {
    type: PropTypes.oneOf(['quote', 'invoice']).isRequired,
    document: PropTypes.shape({ id: PropTypes.number, number: PropTypes.string }),
    values: PropTypes.shape({ lines: PropTypes.array.isRequired }).isRequired,
    taxRates: PropTypes.array.isRequired,
    currencies: PropTypes.arrayOf(PropTypes.shape({ code: PropTypes.string, decimals: PropTypes.number })).isRequired,
    phoneCodes: PropTypes.objectOf(PropTypes.string).isRequired,
    units: PropTypes.arrayOf(PropTypes.string).isRequired,
    canManageClients: PropTypes.bool,
};

export function prefix(type) {
    return type === 'invoice' ? 'invoices' : 'quotes';
}

/** Document discount: percent or fixed amount, inside the totals box. */
function DiscountInput({ form }) {
    const t = useT();
    const { data, setData, errors } = form;

    return (
        <div className="bg-surface space-y-1.5 rounded-xl p-3">
            <div className="flex items-center justify-between gap-2">
                <span className="text-ink-muted text-sm">{t('documents.form.document_discount')}</span>
                <div className="border-line-strong bg-card flex rounded-lg border p-0.5 text-xs">
                    {['percent', 'amount'].map((kind) => (
                        <button
                            key={kind}
                            type="button"
                            onClick={() => setData('discount_type', kind)}
                            aria-pressed={data.discount_type === kind}
                            className={cn('rounded-md px-2 py-1 font-medium', data.discount_type === kind ? 'bg-brand-600 text-white' : 'text-ink-muted')}
                        >
                            {t(`documents.form.discount_${kind}`)}
                        </button>
                    ))}
                </div>
            </div>
            <TextInput
                className="h-9"
                inputMode="decimal"
                dir="ltr"
                placeholder="0"
                value={data.discount_value}
                onChange={(e) => setData('discount_value', e.target.value)}
                error={errors.discount_value}
                aria-label={t('documents.form.document_discount')}
            />
            {errors.discount_value && <p className="text-danger text-xs font-medium">{errors.discount_value}</p>}
        </div>
    );
}

DiscountInput.propTypes = { form: PropTypes.object.isRequired };
