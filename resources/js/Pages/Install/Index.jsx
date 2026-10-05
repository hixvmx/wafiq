import { Head, router, useForm } from '@inertiajs/react';
import { Check, CheckCircle2, Copy, XCircle } from 'lucide-react';
import PropTypes from 'prop-types';
import { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Field, Select, TextInput } from '@/Components/ui/Field';
import { Toaster } from '@/Components/ui/Toaster';
import { cn } from '@/lib/format';
import { useT } from '@/lib/i18n';

/** The web installer: one screen per step, progress kept on the server. */
export default function Install(props) {
    const t = useT();
    const { step, steps } = props;
    const current = steps.indexOf(step);

    const screens = { requirements: Requirements, database: Database, company: Company, mail: Mail, done: Done };
    const Screen = screens[step];

    return (
        <>
            <Head title={t('install.title')} />
            <main className="mx-auto flex min-h-screen max-w-2xl flex-col justify-center px-4 py-10">
                <p className="text-ink mb-6 flex items-center gap-2 text-xl font-bold">
                    <span className="bg-brand-600 inline-flex size-9 items-center justify-center rounded-lg text-white">و</span>
                    {t('install.title')}
                </p>

                <ol className="mb-6 flex flex-wrap gap-x-4 gap-y-2" aria-label={t('install.title')}>
                    {steps.map((key, i) => (
                        <li
                            key={key}
                            aria-current={i === current ? 'step' : undefined}
                            className={cn(
                                'flex items-center gap-1.5 text-sm',
                                i === current ? 'text-brand-700 font-semibold' : i < current ? 'text-ink-muted' : 'text-ink-subtle',
                            )}
                        >
                            <span
                                className={cn(
                                    'flex size-6 items-center justify-center rounded-full text-xs',
                                    i < current ? 'bg-brand-600 text-white' : i === current ? 'bg-brand-100 text-brand-700' : 'bg-surface ring-line ring-1',
                                )}
                            >
                                {i < current ? <Check className="size-3.5" /> : i + 1}
                            </span>
                            {t(`install.steps.${key}`)}
                        </li>
                    ))}
                </ol>

                <Card className="p-6 sm:p-8">
                    <Screen {...props} />
                </Card>

                {current > 0 && (
                    <button
                        type="button"
                        onClick={() => router.post('/install/restart')}
                        className="text-ink-subtle hover:text-ink mt-4 self-center text-sm hover:underline"
                    >
                        {t('install.restart')}
                    </button>
                )}
            </main>
            <Toaster />
        </>
    );
}

Install.propTypes = {
    step: PropTypes.oneOf(['requirements', 'database', 'company', 'mail', 'done']).isRequired,
    steps: PropTypes.arrayOf(PropTypes.string).isRequired,
};

function Requirements({ requirements, requirementsMet }) {
    const t = useT();

    return (
        <div className="space-y-5">
            <p className="text-ink-muted text-sm">{t('install.requirements_intro')}</p>
            <ul className="divide-line border-line divide-y rounded-xl border">
                {requirements.map((check) => (
                    <li key={check.label} className="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
                        <span className="text-ink font-mono text-xs" dir="ltr">
                            {check.label}
                        </span>
                        <span className={cn('flex items-center gap-1.5', check.ok ? 'text-success' : 'text-danger font-medium')}>
                            {check.ok ? <CheckCircle2 className="size-4" /> : <XCircle className="size-4" />}
                            <span dir="ltr">{check.detail}</span>
                        </span>
                    </li>
                ))}
            </ul>
            <div className="flex justify-end gap-2">
                {!requirementsMet && (
                    <Button variant="secondary" onClick={() => router.reload()}>
                        {t('install.recheck')}
                    </Button>
                )}
                <Button disabled={!requirementsMet} onClick={() => router.post('/install/requirements')}>
                    {t('install.next')}
                </Button>
            </div>
        </div>
    );
}

Requirements.propTypes = { requirements: PropTypes.array.isRequired, requirementsMet: PropTypes.bool.isRequired };

/** Text input bound to a useForm field, with label and error. */
function Input({ form, name, label, hint, type = 'text', ltr = false, ...rest }) {
    return (
        <Field label={label} hint={hint} error={form.errors[name]}>
            {(id, describedBy) => (
                <TextInput
                    id={id}
                    type={type}
                    dir={ltr ? 'ltr' : undefined}
                    value={form.data[name] ?? ''}
                    onChange={(e) => form.setData(name, e.target.value)}
                    error={form.errors[name]}
                    aria-describedby={describedBy}
                    {...rest}
                />
            )}
        </Field>
    );
}

Input.propTypes = {
    form: PropTypes.object.isRequired,
    name: PropTypes.string.isRequired,
    label: PropTypes.string.isRequired,
    hint: PropTypes.string,
    type: PropTypes.string,
    ltr: PropTypes.bool,
};

function Database({ defaults }) {
    const t = useT();
    const form = useForm({ host: defaults.db_host, port: defaults.db_port, database: '', username: '', password: '' });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.post('/install/database');
            }}
            className="space-y-5"
        >
            <p className="text-ink-muted text-sm">{t('install.database_intro')}</p>
            {form.errors.database && (
                <p className="text-danger rounded-lg bg-red-50 px-3 py-2 text-sm" role="alert">
                    {form.errors.database}
                </p>
            )}
            <div className="grid gap-4 sm:grid-cols-[1fr_7rem]">
                <Input form={form} name="host" label={t('install.db_host')} ltr required />
                <Input form={form} name="port" label={t('install.db_port')} ltr required inputMode="numeric" />
            </div>
            <Input form={form} name="database" label={t('install.db_name')} ltr required />
            <div className="grid gap-4 sm:grid-cols-2">
                <Input form={form} name="username" label={t('install.db_user')} ltr required autoComplete="off" />
                <Input form={form} name="password" label={t('install.db_password')} type="password" ltr autoComplete="new-password" />
            </div>
            <div className="flex justify-end">
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? t('common.processing') : t('install.db_connect')}
                </Button>
            </div>
        </form>
    );
}

Database.propTypes = { defaults: PropTypes.object.isRequired };

function Company({ currencies }) {
    const t = useT();
    const form = useForm({ company: '', currency: 'SAR', name: '', email: '' });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.post('/install/company');
            }}
            className="space-y-5"
        >
            <p className="text-ink-muted text-sm">{t('install.company_intro')}</p>
            <div className="grid gap-4 sm:grid-cols-[1fr_12rem]">
                <Input form={form} name="company" label={t('install.company_name')} required autoFocus />
                <Field label={t('install.currency')} error={form.errors.currency}>
                    {(id) => (
                        <Select id={id} value={form.data.currency} onChange={(e) => form.setData('currency', e.target.value)}>
                            {currencies.map((c) => (
                                <option key={c.code} value={c.code}>
                                    {c.name} ({c.code})
                                </option>
                            ))}
                        </Select>
                    )}
                </Field>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
                <Input form={form} name="name" label={t('install.owner_name')} required autoComplete="name" />
                <Input
                    form={form}
                    name="email"
                    type="email"
                    label={t('install.owner_email')}
                    hint={t('install.owner_email_hint')}
                    required
                    autoComplete="email"
                />
            </div>
            <div className="flex justify-end">
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? t('common.processing') : t('install.create_company')}
                </Button>
            </div>
        </form>
    );
}

Company.propTypes = { currencies: PropTypes.array.isRequired };

function Mail({ ownerEmail, defaults }) {
    const t = useT();
    const form = useForm({
        mailer: 'smtp',
        host: '',
        port: defaults.mail_port,
        username: '',
        password: '',
        resend_key: '',
        from_address: ownerEmail ?? '',
    });
    const smtp = form.data.mailer === 'smtp';
    const resend = form.data.mailer === 'resend';

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.post('/install/mail');
            }}
            className="space-y-5"
        >
            <p className="text-ink-muted text-sm">{t('install.mail_intro', { email: ownerEmail ?? '' })}</p>
            {form.errors.mailer && (
                <p className="text-danger rounded-lg bg-red-50 px-3 py-2 text-sm" role="alert">
                    {form.errors.mailer}
                </p>
            )}
            <Field label={t('install.mailer')}>
                {(id) => (
                    <Select id={id} value={form.data.mailer} onChange={(e) => form.setData('mailer', e.target.value)}>
                        {['smtp', 'resend', 'sendmail'].map((key) => (
                            <option key={key} value={key}>
                                {t(`install.mailers.${key}`)}
                            </option>
                        ))}
                    </Select>
                )}
            </Field>
            {smtp && (
                <>
                    <div className="grid gap-4 sm:grid-cols-[1fr_7rem]">
                        <Input form={form} name="host" label={t('install.mail_host')} ltr placeholder="mail.example.com" required />
                        <Input form={form} name="port" label={t('install.mail_port')} hint={t('install.mail_port_hint')} ltr required inputMode="numeric" />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Input form={form} name="username" label={t('install.mail_username')} ltr autoComplete="off" />
                        <Input form={form} name="password" label={t('install.mail_password')} type="password" ltr autoComplete="new-password" />
                    </div>
                </>
            )}
            {resend && (
                <Input
                    form={form}
                    name="resend_key"
                    label={t('install.resend_key')}
                    hint={t('install.resend_hint')}
                    type="password"
                    ltr
                    autoComplete="off"
                    placeholder="re_…"
                    required
                />
            )}
            <Input
                form={form}
                name="from_address"
                type="email"
                label={t('install.from_address')}
                hint={resend ? t('install.resend_from_hint') : t('install.from_address_hint')}
                required
            />
            <div className="border-line flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                <button
                    type="button"
                    onClick={() => router.post('/install/mail/skip')}
                    className="text-ink-muted text-sm hover:underline"
                    title={t('install.skip_mail_hint')}
                >
                    {t('install.skip_mail')}
                </button>
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? t('common.processing') : t('install.send_test')}
                </Button>
            </div>
        </form>
    );
}

Mail.propTypes = { ownerEmail: PropTypes.string, defaults: PropTypes.object.isRequired };

function Done({ cron, rescue, mailSkipped }) {
    const t = useT();

    return (
        <div className="space-y-5">
            <p className="text-ink flex items-center gap-2 font-semibold">
                <CheckCircle2 className="text-success size-5" />
                {t('install.done_intro')}
            </p>
            <div>
                <p className="text-ink text-sm font-semibold">{t('install.cron_title')}</p>
                <p className="text-ink-muted mt-1 text-sm">{t('install.cron_help')}</p>
                <CopyLine text={cron} />
            </div>
            {mailSkipped ? (
                <div>
                    <p className="text-warning text-sm">{t('install.mail_skipped_note')}</p>
                    <CopyLine text={rescue} />
                </div>
            ) : (
                <p className="text-ink-muted text-sm">{t('install.test_mail_check')}</p>
            )}
            <div className="flex justify-end">
                <Button onClick={() => router.post('/install/finish')}>{t('install.go')}</Button>
            </div>
        </div>
    );
}

Done.propTypes = { cron: PropTypes.string.isRequired, rescue: PropTypes.string.isRequired, mailSkipped: PropTypes.bool };

function CopyLine({ text }) {
    const t = useT();
    const [copied, setCopied] = useState(false);

    return (
        <div className="mt-2 flex gap-2" dir="ltr">
            <code className="bg-surface border-line text-ink flex-1 overflow-x-auto rounded-lg border px-3 py-2 font-mono text-xs whitespace-nowrap">
                {text}
            </code>
            <Button
                variant="secondary"
                size="sm"
                icon={copied ? <Check className="size-4" /> : <Copy className="size-4" />}
                onClick={() => navigator.clipboard?.writeText(text).then(() => setCopied(true))}
            >
                {copied ? t('install.copied') : t('install.copy')}
            </Button>
        </div>
    );
}

CopyLine.propTypes = { text: PropTypes.string.isRequired };
