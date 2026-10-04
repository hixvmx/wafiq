import { useForm } from '@inertiajs/react';
import { LinkIcon } from 'lucide-react';
import PropTypes from 'prop-types';
import { Button, ButtonLink } from '@/Components/ui/Button';
import AuthLayout from '@/Layouts/AuthLayout';
import { useT } from '@/lib/i18n';

/** Opened from the emailed link. The link is only used when the member presses the button. */
export default function ConfirmLogin({ token, valid }) {
    const t = useT();
    const form = useForm({});

    if (!valid) {
        return (
            <AuthLayout title={t('auth.invalid_title')}>
                <p className="text-ink-muted text-sm leading-relaxed">{t('auth.invalid_text')}</p>
                <ButtonLink href="/login" className="mt-6 w-full">
                    {t('auth.request_new')}
                </ButtonLink>
            </AuthLayout>
        );
    }

    return (
        <AuthLayout title={t('auth.confirm_title')} subtitle={t('auth.confirm_subtitle')}>
            <Button className="w-full" icon={<LinkIcon className="size-4" />} disabled={form.processing} onClick={() => form.post(`/login/${token}`)}>
                {t('auth.confirm_button')}
            </Button>
        </AuthLayout>
    );
}

ConfirmLogin.propTypes = { token: PropTypes.string.isRequired, valid: PropTypes.bool.isRequired };
