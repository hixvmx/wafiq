import { router, useForm } from '@inertiajs/react';
import { Check, Pencil, Plus, Star, Trash2, X } from 'lucide-react';
import PropTypes from 'prop-types';
import { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Badge } from '@/Components/ui/Card';
import { ConfirmDialog } from '@/Components/ui/ConfirmDialog';
import { Field, Select, TextInput } from '@/Components/ui/Field';
import SettingsLayout, { SaveButton, SettingsCard } from '@/Layouts/SettingsLayout';
import { formatNumber } from '@/lib/format';
import { useT } from '@/lib/i18n';

const taxShape = PropTypes.shape({ id: PropTypes.number.isRequired, name: PropTypes.string.isRequired, rate: PropTypes.number.isRequired, is_default: PropTypes.bool.isRequired });

export default function Taxes({ taxRates, presets, currency, currencies, allCurrencies }) {
    const t = useT();
    const [deleting, setDeleting] = useState(null);

    return (
        <SettingsLayout>
            <SettingsCard title={t('settings.taxes.rates')} intro={t('settings.taxes.rates_intro')}>
                {taxRates.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-line-strong p-6 text-center text-sm text-ink-subtle">{t('settings.taxes.empty')}</p>
                ) : (
                    <ul className="divide-y divide-line rounded-xl border border-line">
                        {taxRates.map((tax) => (
                            <TaxRow key={tax.id} tax={tax} onDelete={() => setDeleting(tax)} />
                        ))}
                    </ul>
                )}
                <AddTaxForm presets={presets} />
            </SettingsCard>

            <CurrenciesForm currency={currency} currencies={currencies} allCurrencies={allCurrencies} />

            <ConfirmDialog
                open={deleting !== null}
                title={t('settings.taxes.delete_title', { name: deleting?.name ?? '' })}
                message={t('settings.taxes.delete_message')}
                onConfirm={() => router.delete(`/settings/taxes/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) })}
                onClose={() => setDeleting(null)}
            />
        </SettingsLayout>
    );
}

Taxes.propTypes = {
    taxRates: PropTypes.arrayOf(taxShape).isRequired,
    presets: PropTypes.arrayOf(PropTypes.shape({ code: PropTypes.string, name: PropTypes.string, rate: PropTypes.number })).isRequired,
    currency: PropTypes.string.isRequired,
    currencies: PropTypes.arrayOf(PropTypes.string).isRequired,
    allCurrencies: PropTypes.arrayOf(PropTypes.shape({ code: PropTypes.string, name: PropTypes.string })).isRequired,
};

function TaxRow({ tax, onDelete }) {
    const t = useT();
    const [editing, setEditing] = useState(false);
    const form = useForm({ name: tax.name, rate: tax.rate, is_default: tax.is_default });

    const save = (e) => {
        e.preventDefault();
        form.put(`/settings/taxes/${tax.id}`, { preserveScroll: true, onSuccess: () => setEditing(false) });
    };

    const makeDefault = () => router.put(`/settings/taxes/${tax.id}`, { name: tax.name, rate: tax.rate, is_default: true }, { preserveScroll: true });

    if (editing) {
        return (
            <li className="p-3">
                <form onSubmit={save} className="flex flex-wrap items-start gap-2">
                    <TextInput className="h-9 min-w-40 flex-1" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} aria-label={t('settings.taxes.name')} required />
                    <TextInput className="h-9 w-24" type="number" step="0.001" min="0" max="100" value={form.data.rate} onChange={(e) => form.setData('rate', e.target.value)} error={form.errors.rate} aria-label={t('settings.taxes.rate')} required />
                    <Button type="submit" size="sm" disabled={form.processing} aria-label={t('common.save')}>
                        <Check className="size-4" />
                    </Button>
                    <Button variant="ghost" size="sm" onClick={() => setEditing(false)} aria-label={t('common.cancel')}>
                        <X className="size-4" />
                    </Button>
                </form>
            </li>
        );
    }

    return (
        <li className="flex items-center gap-3 px-4 py-3">
            <div className="min-w-0 flex-1">
                <p className="flex items-center gap-2 text-sm font-medium text-ink">
                    {tax.name}
                    {tax.is_default && <Badge tone="brand">{t('settings.taxes.default')}</Badge>}
                </p>
            </div>
            <span className="text-sm font-semibold text-ink" dir="ltr">
                {formatNumber(tax.rate)}%
            </span>
            {!tax.is_default && (
                <Button variant="ghost" size="sm" onClick={makeDefault} title={t('settings.taxes.make_default')} aria-label={t('settings.taxes.make_default')}>
                    <Star className="size-4" />
                </Button>
            )}
            <Button variant="ghost" size="sm" onClick={() => setEditing(true)} title={t('common.edit')} aria-label={t('common.edit')}>
                <Pencil className="size-4" />
            </Button>
            <Button variant="ghost" size="sm" onClick={onDelete} title={t('common.delete')} aria-label={t('common.delete')}>
                <Trash2 className="size-4" />
            </Button>
        </li>
    );
}

TaxRow.propTypes = { tax: taxShape.isRequired, onDelete: PropTypes.func.isRequired };

function AddTaxForm({ presets }) {
    const t = useT();
    const form = useForm({ name: '', rate: '', is_default: false });

    const applyPreset = (code) => {
        const preset = presets.find((p) => p.code === code);
        if (preset) form.setData({ ...form.data, name: preset.name, rate: preset.rate });
    };

    const submit = (e) => {
        e.preventDefault();
        form.post('/settings/taxes', { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <form onSubmit={submit} className="grid gap-3 rounded-xl bg-surface p-4 sm:grid-cols-[1fr_7rem_auto] sm:items-start">
            <div className="space-y-2 sm:col-span-3">
                <Select value="" onChange={(e) => applyPreset(e.target.value)} className="h-9 sm:w-72" aria-label={t('settings.taxes.add_preset')}>
                    <option value="">{t('settings.taxes.add_preset')}</option>
                    {presets.map((preset) => (
                        <option key={preset.code} value={preset.code}>
                            {preset.name} — {preset.rate}%
                        </option>
                    ))}
                </Select>
            </div>
            <Field label={t('settings.taxes.name')} error={form.errors.name}>
                {(id) => <TextInput id={id} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} required />}
            </Field>
            <Field label={t('settings.taxes.rate')} error={form.errors.rate}>
                {(id) => (
                    <TextInput id={id} type="number" step="0.001" min="0" max="100" value={form.data.rate} onChange={(e) => form.setData('rate', e.target.value)} error={form.errors.rate} required />
                )}
            </Field>
            <Button type="submit" className="sm:mt-6.5" icon={<Plus className="size-4" />} disabled={form.processing}>
                {t('settings.taxes.add')}
            </Button>
        </form>
    );
}

AddTaxForm.propTypes = { presets: Taxes.propTypes.presets };

function CurrenciesForm({ currency, currencies, allCurrencies }) {
    const t = useT();
    const form = useForm({ currency, currencies });

    const toggle = (code) =>
        form.setData('currencies', form.data.currencies.includes(code) ? form.data.currencies.filter((c) => c !== code) : [...form.data.currencies, code]);

    const submit = (e) => {
        e.preventDefault();
        form.put('/settings/currencies', { preserveScroll: true });
    };

    return (
        <form onSubmit={submit}>
            <SettingsCard title={t('settings.taxes.currencies')} intro={t('settings.taxes.currencies_intro')} footer={<SaveButton processing={form.processing} />}>
                <Field label={t('settings.taxes.default_currency')} error={form.errors.currency} className="sm:w-72">
                    {(id) => (
                        <Select id={id} value={form.data.currency} onChange={(e) => form.setData('currency', e.target.value)} error={form.errors.currency}>
                            {allCurrencies.map(({ code, name }) => (
                                <option key={code} value={code}>
                                    {name} ({code})
                                </option>
                            ))}
                        </Select>
                    )}
                </Field>
                <fieldset>
                    <legend className="mb-2 text-sm font-medium text-ink">{t('settings.taxes.enabled_currencies')}</legend>
                    <div className="grid gap-2 sm:grid-cols-3">
                        {allCurrencies.map(({ code, name }) => {
                            const isDefault = code === form.data.currency;
                            return (
                                <label key={code} className="flex cursor-pointer items-center gap-2.5 rounded-xl border border-line px-3 py-2 text-sm has-checked:border-brand-500 has-checked:bg-brand-50">
                                    <input
                                        type="checkbox"
                                        className="size-4 accent-brand-600"
                                        checked={isDefault || form.data.currencies.includes(code)}
                                        disabled={isDefault}
                                        onChange={() => toggle(code)}
                                    />
                                    <span className="flex-1 text-ink">{name}</span>
                                    <span className="text-xs text-ink-subtle" dir="ltr">
                                        {code}
                                    </span>
                                </label>
                            );
                        })}
                    </div>
                    {form.errors.currencies && <p className="mt-2 text-xs font-medium text-danger">{form.errors.currencies}</p>}
                </fieldset>
            </SettingsCard>
        </form>
    );
}

CurrenciesForm.propTypes = {
    currency: PropTypes.string.isRequired,
    currencies: PropTypes.arrayOf(PropTypes.string).isRequired,
    allCurrencies: PropTypes.arrayOf(PropTypes.shape({ code: PropTypes.string, name: PropTypes.string })).isRequired,
};
