import { useForm } from '@inertiajs/react';
import PropTypes from 'prop-types';
import { Checkbox, Field, Select, TextInput } from '@/Components/ui/Field';
import SettingsLayout, { SaveButton, SettingsCard } from '@/Layouts/SettingsLayout';
import { useT } from '@/lib/i18n';

const TYPES = ['quote', 'invoice'];

/** Same rule as App\Services\NumberSequence::format(). */
function formatNumber(format, year) {
    return [format.prefix || null, format.include_year ? year : null, String(format.next_number || 1).padStart(Number(format.padding), '0')].filter(Boolean).join('-');
}

export default function Numbering({ numbering, year }) {
    const t = useT();
    const form = useForm(numbering);

    const set = (type, key, value) => form.setData(type, { ...form.data[type], [key]: value });
    const error = (type, key) => form.errors[`${type}.${key}`];

    const submit = (e) => {
        e.preventDefault();
        form.put('/settings/numbering', { preserveScroll: true });
    };

    return (
        <SettingsLayout>
            <form onSubmit={submit} className="space-y-6">
                {TYPES.map((type) => {
                    const data = form.data[type];
                    return (
                        <SettingsCard key={type} title={t(`settings.types.${type}`)} intro={type === 'quote' ? t('settings.numbering.intro') : undefined}>
                            <p className="rounded-xl bg-brand-50 px-4 py-3 text-sm text-brand-800">
                                {t('settings.numbering.preview')} 
                                <span className="font-mono font-semibold" dir="ltr">
                                    {formatNumber(data, year)}
                                </span>
                            </p>
                            <div className="grid gap-5 sm:grid-cols-3">
                                <Field label={t('settings.numbering.prefix')} error={error(type, 'prefix')}>
                                    {(id) => (
                                        <TextInput id={id} dir="ltr" maxLength={10} value={data.prefix} onChange={(e) => set(type, 'prefix', e.target.value)} error={error(type, 'prefix')} />
                                    )}
                                </Field>
                                <Field label={t('settings.numbering.padding')} error={error(type, 'padding')}>
                                    {(id) => (
                                        <Select id={id} value={data.padding} onChange={(e) => set(type, 'padding', Number(e.target.value))}>
                                            {[1, 2, 3, 4, 5, 6, 7, 8].map((n) => (
                                                <option key={n} value={n}>
                                                    {n} ({'0'.repeat(n - 1)}1)
                                                </option>
                                            ))}
                                        </Select>
                                    )}
                                </Field>
                                <Field label={t('settings.numbering.next_number')} hint={t('settings.numbering.next_number_hint')} error={error(type, 'next_number')}>
                                    {(id, describedBy) => (
                                        <TextInput
                                            id={id}
                                            type="number"
                                            min="1"
                                            value={data.next_number}
                                            onChange={(e) => set(type, 'next_number', e.target.value)}
                                            error={error(type, 'next_number')}
                                            aria-describedby={describedBy}
                                        />
                                    )}
                                </Field>
                            </div>
                            <div className="flex flex-wrap gap-x-8 gap-y-3">
                                <Checkbox label={t('settings.numbering.include_year')} checked={data.include_year} onChange={(e) => set(type, 'include_year', e.target.checked)} />
                                <Checkbox label={t('settings.numbering.yearly_reset')} checked={data.yearly_reset} onChange={(e) => set(type, 'yearly_reset', e.target.checked)} />
                            </div>
                        </SettingsCard>
                    );
                })}
                <div className="flex justify-end">
                    <SaveButton processing={form.processing} />
                </div>
            </form>
        </SettingsLayout>
    );
}

const formatShape = PropTypes.shape({
    prefix: PropTypes.string.isRequired,
    include_year: PropTypes.bool.isRequired,
    padding: PropTypes.number.isRequired,
    yearly_reset: PropTypes.bool.isRequired,
    next_number: PropTypes.number.isRequired,
});

Numbering.propTypes = { numbering: PropTypes.shape({ quote: formatShape, invoice: formatShape }).isRequired, year: PropTypes.number.isRequired };
