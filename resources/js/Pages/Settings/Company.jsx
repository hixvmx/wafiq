import { useForm } from '@inertiajs/react';
import PropTypes from 'prop-types';
import { Field, TextArea, TextInput } from '@/Components/ui/Field';
import SettingsLayout, { SaveButton, SettingsCard } from '@/Layouts/SettingsLayout';
import { useT } from '@/lib/i18n';

const FIELDS = [
    { name: 'name', required: true },
    { name: 'legal_name', hint: true },
    { name: 'vat_number', ltr: true },
    { name: 'cr_number', ltr: true },
    { name: 'phone', type: 'tel' },
    { name: 'email', type: 'email' },
];

export default function Company({ profile }) {
    const t = useT();
    const form = useForm({ ...profile, bank_details: profile.bank_details ?? '' });

    const submit = (e) => {
        e.preventDefault();
        form.put('/settings/company', { preserveScroll: true });
    };

    const text = (name) => form.data[name] ?? '';

    return (
        <SettingsLayout>
            <form onSubmit={submit}>
                <SettingsCard title={t('settings.sections.company')} intro={t('settings.company.intro')} footer={<SaveButton processing={form.processing} />}>
                    <div className="grid gap-5 sm:grid-cols-2">
                        {FIELDS.map(({ name, required, hint, ltr, type }) => (
                            <Field key={name} label={t(`settings.company.${name}`)} hint={hint && t(`settings.company.${name}_hint`)} error={form.errors[name]}>
                                {(id, describedBy) => (
                                    <TextInput
                                        id={id}
                                        type={type ?? 'text'}
                                        dir={ltr ? 'ltr' : undefined}
                                        required={required}
                                        value={text(name)}
                                        onChange={(e) => form.setData(name, e.target.value)}
                                        error={form.errors[name]}
                                        aria-describedby={describedBy}
                                    />
                                )}
                            </Field>
                        ))}
                    </div>
                    <Field label={t('settings.company.address')} error={form.errors.address}>
                        {(id) => (
                            <TextArea
                                id={id}
                                className="min-h-20"
                                value={text('address')}
                                onChange={(e) => form.setData('address', e.target.value)}
                                error={form.errors.address}
                            />
                        )}
                    </Field>
                    <Field label={t('settings.company.bank_details')} hint={t('settings.company.bank_details_hint')} error={form.errors.bank_details}>
                        {(id, describedBy) => (
                            <TextArea
                                id={id}
                                className="min-h-24"
                                value={text('bank_details')}
                                onChange={(e) => form.setData('bank_details', e.target.value)}
                                error={form.errors.bank_details}
                                aria-describedby={describedBy}
                            />
                        )}
                    </Field>
                </SettingsCard>
            </form>
        </SettingsLayout>
    );
}

Company.propTypes = { profile: PropTypes.object.isRequired };
