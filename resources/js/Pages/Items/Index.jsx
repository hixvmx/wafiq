import { router, useForm, usePage } from '@inertiajs/react';
import { Package, Pencil, Plus, Trash2, Wrench } from 'lucide-react';
import PropTypes from 'prop-types';
import { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Badge, Card, EmptyState } from '@/Components/ui/Card';
import { ConfirmDialog } from '@/Components/ui/ConfirmDialog';
import { Dialog } from '@/Components/ui/Dialog';
import { Field, Select, TextArea, TextInput } from '@/Components/ui/Field';
import { ListToolbar } from '@/Components/ui/ListToolbar';
import { Pagination } from '@/Components/ui/Pagination';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/format';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';

const taxShape = PropTypes.shape({ id: PropTypes.number, name: PropTypes.string, rate: PropTypes.number, is_default: PropTypes.bool });

export default function ItemsIndex({ items, filters, taxRates, currencies, defaultCurrency, units }) {
    const t = useT();
    const canManage = usePage().props.auth.can.manage_items;
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const filtered = Boolean(filters.q || filters.type);

    const taxLabel = (id) => {
        const tax = id ? taxRates.find((rate) => rate.id === id) : taxRates.find((rate) => rate.is_default);
        return tax ? `${tax.name} ${tax.rate}%` : null;
    };

    const addButton = canManage && (
        <Button icon={<Plus className="size-4" />} onClick={() => setEditing({})}>
            {t('items.add')}
        </Button>
    );

    return (
        <AppLayout title={t('items.title')}>
            <div className="mx-auto max-w-5xl space-y-5">
                <ListToolbar
                    url="/items"
                    filters={filters}
                    placeholder={t('items.search')}
                    types={[
                        { value: null, label: t('common.all') },
                        { value: 'product', label: t('items.types.product') },
                        { value: 'service', label: t('items.types.service') },
                    ]}
                    action={addButton}
                />

                {items.data.length === 0 ? (
                    filtered ? (
                        <EmptyState title={t('items.no_results')} />
                    ) : (
                        <EmptyState icon={<Package className="size-8" />} title={t('items.empty')} text={t('items.empty_text')} action={addButton} />
                    )
                ) : (
                    <Card>
                        <ul className="divide-y divide-line">
                            {items.data.map((item) => {
                                const Icon = item.type === 'product' ? Package : Wrench;
                                return (
                                    <li key={item.id} className="flex items-center gap-4 px-5 py-4">
                                        <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-700">
                                            <Icon className="size-5" />
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-semibold text-ink">{item.name}</p>
                                            {item.description && <p className="mt-0.5 truncate text-xs text-ink-subtle">{item.description}</p>}
                                        </div>
                                        <div className="shrink-0 text-end">
                                            <p className="text-sm font-semibold text-ink" dir="ltr">
                                                {formatMoney(item.price, item.currency)}
                                            </p>
                                            <p className="mt-0.5 flex justify-end gap-1.5 text-xs text-ink-subtle">
                                                {item.unit && <span>/ {item.unit}</span>}
                                                {taxLabel(item.tax_rate_id) && <Badge>{taxLabel(item.tax_rate_id)}</Badge>}
                                            </p>
                                        </div>
                                        {canManage && (
                                            <div className="flex shrink-0 gap-1">
                                                <Button variant="ghost" size="sm" onClick={() => setEditing(item)} aria-label={t('common.edit')} title={t('common.edit')}>
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <Button variant="ghost" size="sm" onClick={() => setDeleting(item)} aria-label={t('common.delete')} title={t('common.delete')}>
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            </div>
                                        )}
                                    </li>
                                );
                            })}
                        </ul>
                    </Card>
                )}

                <Pagination meta={items} />
            </div>

            <Dialog open={editing !== null} title={editing?.id ? t('items.edit') : t('items.add')} onClose={() => setEditing(null)} size="lg">
                <ItemForm
                    key={editing?.id ?? 'new'}
                    item={editing?.id ? editing : undefined}
                    taxRates={taxRates}
                    currencies={currencies}
                    defaultCurrency={defaultCurrency}
                    units={units}
                    onDone={() => setEditing(null)}
                />
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                title={t('items.delete_title', { name: deleting?.name ?? '' })}
                message={t('items.delete_message')}
                onConfirm={() => router.delete(`/items/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) })}
                onClose={() => setDeleting(null)}
            />
        </AppLayout>
    );
}

ItemsIndex.propTypes = {
    items: PropTypes.shape({ data: PropTypes.arrayOf(PropTypes.object).isRequired }).isRequired,
    filters: PropTypes.shape({ q: PropTypes.string, type: PropTypes.string }).isRequired,
    taxRates: PropTypes.arrayOf(taxShape).isRequired,
    currencies: PropTypes.arrayOf(PropTypes.string).isRequired,
    defaultCurrency: PropTypes.string.isRequired,
    units: PropTypes.arrayOf(PropTypes.string).isRequired,
};

function ItemForm({ item, taxRates, currencies, defaultCurrency, units, onDone }) {
    const t = useT();
    const form = useForm({
        type: item?.type ?? 'service',
        name: item?.name ?? '',
        description: item?.description ?? '',
        unit: item?.unit ?? '',
        price: item?.price ?? '',
        currency: item?.currency ?? defaultCurrency,
        tax_rate_id: item?.tax_rate_id ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, tax_rate_id: data.tax_rate_id || null }));
        const options = { preserveScroll: true, onSuccess: onDone };
        item ? form.put(`/items/${item.id}`, options) : form.post('/items', options);
    };

    const defaultTax = taxRates.find((rate) => rate.is_default);
    // An item can keep a currency that was later disabled.
    const currencyOptions = currencies.includes(form.data.currency) ? currencies : [form.data.currency, ...currencies];

    return (
        <form onSubmit={submit} className="space-y-5">
            <div className="grid grid-cols-2 gap-2" role="radiogroup" aria-label={t('items.type')}>
                {[
                    ['service', Wrench],
                    ['product', Package],
                ].map(([type, Icon]) => (
                    <button
                        key={type}
                        type="button"
                        role="radio"
                        aria-checked={form.data.type === type}
                        onClick={() => form.setData('type', type)}
                        className={cn(
                            'flex items-center justify-center gap-2 rounded-xl border px-3 py-2.5 text-sm font-medium transition-colors',
                            form.data.type === type ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-line-strong text-ink-muted hover:bg-surface',
                        )}
                    >
                        <Icon className="size-4" />
                        {t(`items.types.${type}`)}
                    </button>
                ))}
            </div>

            <Field label={t('items.name')} error={form.errors.name}>
                {(id) => <TextInput id={id} required autoFocus value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} />}
            </Field>

            <Field label={t('items.description')} hint={t('items.description_hint')} error={form.errors.description} optional optionalLabel={t('common.optional')}>
                {(id, describedBy) => (
                    <TextArea
                        id={id}
                        className="min-h-20"
                        value={form.data.description}
                        onChange={(e) => form.setData('description', e.target.value)}
                        error={form.errors.description}
                        aria-describedby={describedBy}
                    />
                )}
            </Field>

            <div className="grid gap-5 sm:grid-cols-[1fr_8rem_9rem]">
                <Field label={t('items.price')} hint={t('items.price_hint')} error={form.errors.price}>
                    {(id, describedBy) => (
                        <TextInput
                            id={id}
                            inputMode="decimal"
                            dir="ltr"
                            required
                            placeholder="0.00"
                            value={form.data.price}
                            onChange={(e) => form.setData('price', e.target.value)}
                            error={form.errors.price}
                            aria-describedby={describedBy}
                        />
                    )}
                </Field>
                <Field label={t('items.currency')} error={form.errors.currency}>
                    {(id) => (
                        <Select id={id} value={form.data.currency} onChange={(e) => form.setData('currency', e.target.value)} error={form.errors.currency}>
                            {currencyOptions.map((code) => (
                                <option key={code} value={code}>
                                    {code}
                                </option>
                            ))}
                        </Select>
                    )}
                </Field>
                <Field label={t('items.unit')} error={form.errors.unit}>
                    {(id) => (
                        <>
                            <TextInput id={id} list="unit-suggestions" value={form.data.unit} onChange={(e) => form.setData('unit', e.target.value)} error={form.errors.unit} />
                            <datalist id="unit-suggestions">
                                {units.map((unit) => (
                                    <option key={unit} value={unit} />
                                ))}
                            </datalist>
                        </>
                    )}
                </Field>
            </div>

            <Field label={t('items.tax')} error={form.errors.tax_rate_id}>
                {(id) => (
                    <Select id={id} value={form.data.tax_rate_id} onChange={(e) => form.setData('tax_rate_id', e.target.value ? Number(e.target.value) : '')} error={form.errors.tax_rate_id}>
                        <option value="">
                            {t('items.default_tax')}
                            {defaultTax ? ` (${defaultTax.name} ${defaultTax.rate}%)` : ''}
                        </option>
                        {taxRates.map((rate) => (
                            <option key={rate.id} value={rate.id}>
                                {rate.name} {rate.rate}%
                            </option>
                        ))}
                    </Select>
                )}
            </Field>

            <div className="flex justify-end gap-2 border-t border-line pt-4">
                <Button variant="secondary" onClick={onDone}>
                    {t('common.cancel')}
                </Button>
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? t('common.saving') : t('common.save')}
                </Button>
            </div>
        </form>
    );
}

ItemForm.propTypes = {
    item: PropTypes.shape({
        id: PropTypes.number.isRequired,
        type: PropTypes.string,
        name: PropTypes.string,
        description: PropTypes.string,
        unit: PropTypes.string,
        price: PropTypes.string,
        currency: PropTypes.string,
        tax_rate_id: PropTypes.number,
    }),
    taxRates: PropTypes.arrayOf(taxShape).isRequired,
    currencies: PropTypes.arrayOf(PropTypes.string).isRequired,
    defaultCurrency: PropTypes.string.isRequired,
    units: PropTypes.arrayOf(PropTypes.string).isRequired,
    onDone: PropTypes.func.isRequired,
};
