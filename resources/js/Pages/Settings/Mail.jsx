import { router, useForm } from '@inertiajs/react';
import { CheckCircle2, ExternalLink, Send, TriangleAlert } from 'lucide-react';
import PropTypes from 'prop-types';
import { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Field, TextInput } from '@/Components/ui/Field';
import SettingsLayout, { SettingsCard } from '@/Layouts/SettingsLayout';
import { cn } from '@/lib/format';
import { useT } from '@/lib/i18n';

const MAILERS = ['resend', 'smtp', 'sendmail'];

export default function Mail({ mail, testEmail }) {
    const t = useT();
    const form = useForm({
        mailer: mail.mailer,
        host: mail.host ?? '',
        port: mail.port ?? 465,
        username: mail.username ?? '',
        password: '',
        resend_key: '',
        from_address: mail.from_address ?? '',
        from_name: mail.from_name ?? '',
    });
    const [testing, setTesting] = useState(false);

    const submit = (e) => {
        e.preventDefault();
        form.put('/settings/mail', { preserveScroll: true, onSuccess: () => form.setData((data) => ({ ...data, password: '', resend_key: '' })) });
    };

    const sendTest = () => router.post('/settings/mail/test', {}, { preserveScroll: true, onStart: () => setTesting(true), onFinish: () => setTesting(false) });

    const input = (name, options = {}) => (
        <Field label={options.label ?? t(`install.mail_${name}`)} hint={options.hint} error={form.errors[name]}>
            {(id, describedBy) => (
                <TextInput
                    id={id}
                    dir="ltr"
                    value={form.data[name]}
                    onChange={(e) => form.setData(name, e.target.value)}
                    error={form.errors[name]}
                    aria-describedby={describedBy}
                    {...options.input}
                />
            )}
        </Field>
    );

    return (
        <SettingsLayout>
            {/* Where things stand right now. */}
            {mail.configured ? (
                <div className="border-success/30 bg-success/5 flex flex-wrap items-center gap-3 rounded-xl border p-4">
                    <CheckCircle2 className="text-success size-5 shrink-0" />
                    <p className="text-ink min-w-0 flex-1 text-sm">{t('mail_settings.working_with', { mailer: t(`install.mailers.${mail.mailer}`) })}</p>
                    <Button variant="secondary" size="sm" icon={<Send className="size-4" />} onClick={sendTest} disabled={testing}>
                        {testing ? t('common.processing') : t('mail_settings.test')}
                    </Button>
                </div>
            ) : (
                <div className="border-warning/30 bg-warning/5 flex items-center gap-3 rounded-xl border p-4">
                    <TriangleAlert className="text-warning size-5 shrink-0" />
                    <p className="text-ink text-sm">{t('mail_settings.not_configured')}</p>
                </div>
            )}

            <form onSubmit={submit}>
                <SettingsCard
                    title={t('settings.sections.mail')}
                    intro={t('mail_settings.intro', { email: testEmail })}
                    footer={
                        <Button type="submit" icon={<Send className="size-4" />} disabled={form.processing}>
                            {form.processing ? t('common.processing') : t('mail_settings.save')}
                        </Button>
                    }
                >
                    {form.errors.mailer && (
                        <p className="border-danger/30 bg-danger/5 text-danger rounded-xl border p-3 text-sm wrap-break-word" role="alert">
                            {form.errors.mailer}
                        </p>
                    )}

                    <fieldset>
                        <legend className="text-ink mb-2 text-sm font-medium">{t('mail_settings.mailer')}</legend>
                        <div className="grid gap-3 sm:grid-cols-3">
                            {MAILERS.map((key) => (
                                <label
                                    key={key}
                                    className={cn(
                                        'cursor-pointer rounded-xl border p-3.5 transition-colors',
                                        form.data.mailer === key ? 'border-brand-500 bg-brand-50 ring-brand-500 ring-1' : 'border-line hover:bg-surface',
                                    )}
                                >
                                    <span className="flex items-center gap-2">
                                        <input
                                            type="radio"
                                            name="mailer"
                                            value={key}
                                            checked={form.data.mailer === key}
                                            onChange={() => form.setData('mailer', key)}
                                            className="accent-brand-600"
                                        />
                                        <span className="text-ink text-sm font-semibold">{t(`install.mailers.${key}`)}</span>
                                    </span>
                                    <span className="text-ink-muted mt-1.5 block text-xs leading-relaxed">{t(`mail_settings.mailer_hints.${key}`)}</span>
                                </label>
                            ))}
                        </div>
                    </fieldset>

                    {form.data.mailer === 'resend' && (
                        <div className="bg-surface space-y-4 rounded-xl p-4">
                            <ol className="text-ink-muted list-inside list-decimal space-y-1 text-sm">
                                {t.list('mail_settings.resend_steps').map((step) => (
                                    <li key={step}>{step}</li>
                                ))}
                            </ol>
                            <a
                                href="https://resend.com/api-keys"
                                target="_blank"
                                rel="noreferrer"
                                className="text-brand-700 inline-flex items-center gap-1 text-sm font-medium hover:underline"
                            >
                                {t('mail_settings.open_resend')}
                                <ExternalLink className="size-3.5" />
                            </a>
                            {input('resend_key', {
                                label: t('install.resend_key'),
                                hint: mail.has_resend_key ? t('mail_settings.key_saved') : t('install.resend_hint'),
                                input: { type: 'password', autoComplete: 'off', placeholder: mail.has_resend_key ? '••••••••••••' : 're_…' },
                            })}
                        </div>
                    )}

                    {form.data.mailer === 'smtp' && (
                        <div className="grid gap-4 sm:grid-cols-2">
                            {input('host', { input: { placeholder: 'mail.example.com' } })}
                            {input('port', { hint: t('install.mail_port_hint'), input: { inputMode: 'numeric' } })}
                            {input('username', { input: { autoComplete: 'off' } })}
                            {input('password', {
                                hint: mail.has_password ? t('mail_settings.password_saved') : undefined,
                                input: { type: 'password', autoComplete: 'new-password', placeholder: mail.has_password ? '••••••••' : '' },
                            })}
                        </div>
                    )}

                    <div className="grid gap-4 sm:grid-cols-2">
                        {input('from_address', {
                            label: t('install.from_address'),
                            hint: form.data.mailer === 'resend' ? t('install.resend_from_hint') : t('install.from_address_hint'),
                            input: { type: 'email', required: true },
                        })}
                        {input('from_name', { label: t('mail_settings.from_name'), hint: t('mail_settings.from_name_hint'), input: { dir: 'auto' } })}
                    </div>
                </SettingsCard>
            </form>
        </SettingsLayout>
    );
}

Mail.propTypes = {
    mail: PropTypes.shape({
        mailer: PropTypes.oneOf(MAILERS).isRequired,
        configured: PropTypes.bool.isRequired,
        host: PropTypes.string,
        port: PropTypes.oneOfType([PropTypes.number, PropTypes.string]),
        username: PropTypes.string,
        has_password: PropTypes.bool.isRequired,
        has_resend_key: PropTypes.bool.isRequired,
        from_address: PropTypes.string,
        from_name: PropTypes.string,
    }).isRequired,
    testEmail: PropTypes.string.isRequired,
};
