import { useForm } from '@inertiajs/react';
import axios from 'axios';
import { Building2, User } from 'lucide-react';
import PropTypes from 'prop-types';
import { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Dialog } from '@/Components/ui/Dialog';
import { Field, Select, TextArea, TextInput } from '@/Components/ui/Field';
import { cn } from '@/lib/format';
import { useT } from '@/lib/i18n';

const EMPTY = { type: 'company', name: '', contact_name: '', email: '', phone_code: '966', phone_number: '', vat_number: '', cr_number: '', address: '' };

/**
 * Create or edit a client. Pass `client` to edit; omit it to create.
 * With `onCreated`, a new client is saved without a page visit and handed back (document editor).
 */
export function ClientDialog({ open, client, phoneCodes, onClose, onCreated }) {
    const t = useT();

    return (
        <Dialog open={open} title={client ? t('clients.edit') : t('clients.add')} onClose={onClose} size="lg">
            {/* Remount per client so the form starts from that client's values. */}
            <ClientForm key={client?.id ?? 'new'} client={client} phoneCodes={phoneCodes} onDone={onClose} onCreated={onCreated} />
        </Dialog>
    );
}

const clientShape = PropTypes.shape({
    id: PropTypes.number.isRequired,
    type: PropTypes.string,
    name: PropTypes.string,
    phone_code: PropTypes.string,
    phone_number: PropTypes.string,
});

ClientDialog.propTypes = {
    open: PropTypes.bool.isRequired,
    client: clientShape,
    phoneCodes: PropTypes.objectOf(PropTypes.string).isRequired,
    onClose: PropTypes.func.isRequired,
    onCreated: PropTypes.func,
};

function ClientForm({ client, phoneCodes, onDone, onCreated }) {
    const t = useT();
    const initial = client ? Object.fromEntries(Object.keys(EMPTY).map((key) => [key, client[key] ?? EMPTY[key]])) : EMPTY;
    const form = useForm(initial);
    const [saving, setSaving] = useState(false); // quick-create mode posts with axios, not Inertia
    const isCompany = form.data.type === 'company';

    const submit = (e) => {
        e.preventDefault();
        if (!client && onCreated) {
            form.clearErrors();
            setSaving(true);
            axios
                .post('/clients', form.data, { headers: { Accept: 'application/json' } })
                .then(({ data }) => {
                    onCreated(data.client);
                    onDone();
                })
                .catch((error) => {
                    const errors = error.response?.data?.errors ?? {};
                    form.setError(Object.fromEntries(Object.entries(errors).map(([key, messages]) => [key, messages[0]])));
                })
                .finally(() => setSaving(false));
            return;
        }

        const options = { preserveScroll: true, onSuccess: onDone };
        client ? form.put(`/clients/${client.id}`, options) : form.post('/clients', options);
    };

    const input = (name, options = {}) => (
        <Field label={options.label ?? t(`clients.${name}`)} hint={options.hint} error={form.errors[name]}>
            {(id, describedBy) => (
                <TextInput
                    id={id}
                    value={form.data[name] ?? ''}
                    onChange={(e) => form.setData(name, e.target.value)}
                    error={form.errors[name]}
                    aria-describedby={describedBy}
                    {...options.input}
                />
            )}
        </Field>
    );

    // Codes are unique per value; show "SA +966".
    const codes = Object.entries(phoneCodes);

    return (
        <form onSubmit={submit} className="space-y-5">
            <div className="grid grid-cols-2 gap-2" role="radiogroup" aria-label={t('clients.type')}>
                {[
                    ['company', Building2],
                    ['person', User],
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
                        {t(`clients.types.${type}`)}
                    </button>
                ))}
            </div>

            <div className="grid gap-5 sm:grid-cols-2">
                {input('name', { label: isCompany ? t('clients.company_name') : t('clients.person_name'), input: { required: true, autoFocus: true } })}
                {isCompany && input('contact_name')}
                {input('email', { input: { type: 'email' } })}

                <Field
                    label={t('clients.phone')}
                    hint={t('clients.phone_hint')}
                    error={form.errors.phone_number ?? form.errors.phone_code}
                    className="sm:col-span-2"
                >
                    {(id, describedBy) => (
                        <div className="flex gap-2" dir="ltr">
                            <Select
                                value={form.data.phone_code ?? ''}
                                onChange={(e) => form.setData('phone_code', e.target.value)}
                                className="w-32 shrink-0"
                                aria-label={t('clients.phone_code')}
                            >
                                {codes.map(([country, code]) => (
                                    <option key={country} value={code}>
                                        {country} +{code}
                                    </option>
                                ))}
                            </Select>
                            <TextInput
                                id={id}
                                type="tel"
                                inputMode="tel"
                                placeholder="0501234567"
                                value={form.data.phone_number ?? ''}
                                onChange={(e) => form.setData('phone_number', e.target.value)}
                                error={form.errors.phone_number}
                                aria-describedby={describedBy}
                            />
                        </div>
                    )}
                </Field>

                {isCompany && input('vat_number', { input: { dir: 'ltr' } })}
                {isCompany && input('cr_number', { input: { dir: 'ltr' } })}
            </div>

            <Field label={t('clients.address')} error={form.errors.address}>
                {(id) => (
                    <TextArea
                        id={id}
                        className="min-h-20"
                        value={form.data.address ?? ''}
                        onChange={(e) => form.setData('address', e.target.value)}
                        error={form.errors.address}
                    />
                )}
            </Field>

            <div className="border-line flex justify-end gap-2 border-t pt-4">
                <Button variant="secondary" onClick={onDone}>
                    {t('common.cancel')}
                </Button>
                <Button type="submit" disabled={form.processing || saving}>
                    {form.processing || saving ? t('common.saving') : t('common.save')}
                </Button>
            </div>
        </form>
    );
}

ClientForm.propTypes = {
    client: clientShape,
    phoneCodes: PropTypes.objectOf(PropTypes.string).isRequired,
    onDone: PropTypes.func.isRequired,
    onCreated: PropTypes.func,
};
