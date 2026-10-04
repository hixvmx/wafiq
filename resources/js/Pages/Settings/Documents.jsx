import { useForm } from '@inertiajs/react';
import PropTypes from 'prop-types';
import { Field, TextArea, TextInput } from '@/Components/ui/Field';
import SettingsLayout, { SaveButton, SettingsCard } from '@/Layouts/SettingsLayout';
import { useT } from '@/lib/i18n';

/** Days field differs per type: quotes have a validity, invoices a payment term. */
const DAYS = { quote: { key: 'validity_days', min: 1 }, invoice: { key: 'due_days', min: 0 } };

export default function Documents({ documents }) {
    const t = useT();
    const form = useForm(documents);

    const set = (type, key, value) => form.setData(type, { ...form.data[type], [key]: value });
    const error = (type, key) => form.errors[`${type}.${key}`];

    const submit = (e) => {
        e.preventDefault();
        form.put('/settings/documents', { preserveScroll: true });
    };

    return (
        <SettingsLayout>
            <form onSubmit={submit} className="space-y-6">
                {Object.entries(DAYS).map(([type, days]) => (
                    <SettingsCard key={type} title={t(`settings.types.${type}`)}>
                        <Field label={t(`settings.documents.${days.key}`)} hint={t(`settings.documents.${days.key}_hint`)} error={error(type, days.key)} className="sm:w-72">
                            {(id, describedBy) => (
                                <TextInput
                                    id={id}
                                    type="number"
                                    min={days.min}
                                    max="365"
                                    value={form.data[type][days.key]}
                                    onChange={(e) => set(type, days.key, e.target.value)}
                                    error={error(type, days.key)}
                                    aria-describedby={describedBy}
                                />
                            )}
                        </Field>
                        {['terms', 'notes'].map((key) => (
                            <Field key={key} label={t(`settings.documents.${key}`)} error={error(type, key)} optional={key === 'notes'} optionalLabel={t('common.optional')}>
                                {(id) => <TextArea id={id} className="min-h-24" value={form.data[type][key] ?? ''} onChange={(e) => set(type, key, e.target.value)} error={error(type, key)} />}
                            </Field>
                        ))}
                    </SettingsCard>
                ))}
                <div className="flex justify-end">
                    <SaveButton processing={form.processing} />
                </div>
            </form>
        </SettingsLayout>
    );
}

Documents.propTypes = {
    documents: PropTypes.shape({
        quote: PropTypes.shape({ validity_days: PropTypes.number, terms: PropTypes.string, notes: PropTypes.string }).isRequired,
        invoice: PropTypes.shape({ due_days: PropTypes.number, terms: PropTypes.string, notes: PropTypes.string }).isRequired,
    }).isRequired,
};
