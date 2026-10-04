import { useForm } from '@inertiajs/react';
import PropTypes from 'prop-types';
import { Button } from '@/Components/ui/Button';
import { Field, TextInput } from '@/Components/ui/Field';
import AuthLayout from '@/Layouts/AuthLayout';
import { useT } from '@/lib/i18n';

export default function AcceptInvitation({ token, invitation }) {
    const t = useT();
    const form = useForm({ name: '' });

    if (!invitation) {
        return (
            <AuthLayout title={t('invitations.invalid_title')}>
                <p className="text-ink-muted text-sm leading-relaxed">{t('invitations.invalid_text')}</p>
            </AuthLayout>
        );
    }

    const submit = (e) => {
        e.preventDefault();
        form.post(`/invitations/${token}`);
    };

    return (
        <AuthLayout title={t('invitations.title')} subtitle={t('invitations.subtitle', { company: invitation.company, role: invitation.role })}>
            <form onSubmit={submit} className="space-y-5">
                <Field label={t('invitations.email')}>{(id) => <TextInput id={id} type="email" value={invitation.email} disabled readOnly />}</Field>
                {!invitation.has_account && (
                    <Field label={t('invitations.name')} error={form.errors.name}>
                        {(id, describedBy) => (
                            <TextInput
                                id={id}
                                autoComplete="name"
                                autoFocus
                                required
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                error={form.errors.name}
                                aria-describedby={describedBy}
                            />
                        )}
                    </Field>
                )}
                <Button type="submit" className="w-full" disabled={form.processing}>
                    {form.processing ? t('common.processing') : t('invitations.accept')}
                </Button>
            </form>
        </AuthLayout>
    );
}

AcceptInvitation.propTypes = {
    token: PropTypes.string.isRequired,
    invitation: PropTypes.shape({
        email: PropTypes.string.isRequired,
        company: PropTypes.string.isRequired,
        role: PropTypes.string.isRequired,
        has_account: PropTypes.bool.isRequired,
    }),
};
