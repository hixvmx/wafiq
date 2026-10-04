import { Link, useForm } from '@inertiajs/react';
import { MailCheck } from 'lucide-react';
import PropTypes from 'prop-types';
import { Button } from '@/Components/ui/Button';
import { Field, TextInput } from '@/Components/ui/Field';
import AuthLayout from '@/Layouts/AuthLayout';
import { useT } from '@/lib/i18n';

export default function Login({ sentTo, minutes }) {
    const t = useT();
    const form = useForm({ email: sentTo ?? '' });

    if (sentTo) {
        return (
            <AuthLayout title={t('auth.check_inbox')} footer={t('auth.no_email_hint')}>
                <div className="text-center">
                    <MailCheck className="text-brand-600 mx-auto size-12" />
                    <p className="text-ink-muted mt-4 text-sm leading-relaxed">{t('auth.link_sent', { email: sentTo, minutes })}</p>
                    <Link href="/login" className="text-brand-700 mt-6 inline-block text-sm font-semibold hover:underline">
                        {t('auth.use_other_email')}
                    </Link>
                </div>
            </AuthLayout>
        );
    }

    const submit = (e) => {
        e.preventDefault();
        form.post('/login');
    };

    return (
        <AuthLayout title={t('auth.login_title')} subtitle={t('auth.login_subtitle')}>
            <form onSubmit={submit} className="space-y-5">
                <Field label={t('auth.email')} error={form.errors.email}>
                    {(id, describedBy) => (
                        <TextInput
                            id={id}
                            type="email"
                            autoComplete="email"
                            autoFocus
                            required
                            value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)}
                            error={form.errors.email}
                            aria-describedby={describedBy}
                        />
                    )}
                </Field>
                <Button type="submit" className="w-full" disabled={form.processing}>
                    {form.processing ? t('auth.sending') : t('auth.send_link')}
                </Button>
            </form>
        </AuthLayout>
    );
}

Login.propTypes = { sentTo: PropTypes.string, minutes: PropTypes.number.isRequired };
