import { router, useForm } from '@inertiajs/react';
import { Camera, Trash2 } from 'lucide-react';
import PropTypes from 'prop-types';
import { useRef, useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Avatar, Badge, Card } from '@/Components/ui/Card';
import { Field, TextInput } from '@/Components/ui/Field';
import AppLayout from '@/Layouts/AppLayout';
import { useT } from '@/lib/i18n';

export default function ProfileEdit({ profile }) {
    const t = useT();

    return (
        <AppLayout title={t('profile.title')}>
            <div className="mx-auto max-w-2xl space-y-6">
                <p className="text-ink-muted text-sm">{t('profile.subtitle')}</p>
                <PhotoCard name={profile.name} avatar={profile.avatar} />
                <DetailsCard profile={profile} />
            </div>
        </AppLayout>
    );
}

ProfileEdit.propTypes = {
    profile: PropTypes.shape({
        name: PropTypes.string.isRequired,
        email: PropTypes.string.isRequired,
        job_title: PropTypes.string,
        phone: PropTypes.string,
        avatar: PropTypes.string,
        role: PropTypes.string,
    }).isRequired,
};

function PhotoCard({ name, avatar }) {
    const t = useT();
    const input = useRef(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);

    const options = {
        preserveScroll: true,
        onStart: () => {
            setBusy(true);
            setError(null);
        },
        onError: (errors) => setError(errors.avatar ?? null),
        onFinish: () => {
            setBusy(false);
            if (input.current) input.current.value = '';
        },
    };

    const upload = (file) => file && router.post('/profile/avatar', { avatar: file }, { ...options, forceFormData: true });
    const remove = () => router.delete('/profile/avatar', options);

    return (
        <Card className="p-5">
            <h2 className="text-ink mb-4 text-base font-bold">{t('profile.photo')}</h2>
            <div className="flex flex-wrap items-center gap-5">
                <Avatar src={avatar} name={name} size={88} />
                <div className="min-w-0 flex-1 space-y-3">
                    <div className="flex flex-wrap gap-2">
                        <input
                            ref={input}
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            className="hidden"
                            onChange={(e) => upload(e.target.files[0])}
                        />
                        <Button variant="secondary" size="sm" icon={<Camera className="size-4" />} disabled={busy} onClick={() => input.current.click()}>
                            {busy ? t('profile.uploading') : avatar ? t('profile.replace') : t('profile.upload')}
                        </Button>
                        {avatar && (
                            <Button variant="ghost" size="sm" icon={<Trash2 className="size-4" />} disabled={busy} onClick={remove}>
                                {t('profile.remove_photo')}
                            </Button>
                        )}
                    </div>
                    <p className="text-ink-subtle text-xs">{t('profile.photo_hint')}</p>
                    {error && (
                        <p className="text-danger text-sm" role="alert">
                            {error}
                        </p>
                    )}
                </div>
            </div>
        </Card>
    );
}

PhotoCard.propTypes = { name: PropTypes.string.isRequired, avatar: PropTypes.string };

function DetailsCard({ profile }) {
    const t = useT();
    const form = useForm({ name: profile.name, job_title: profile.job_title ?? '', phone: profile.phone ?? '' });

    const submit = (e) => {
        e.preventDefault();
        form.put('/profile', { preserveScroll: true, onSuccess: () => form.setDefaults() });
    };

    const input = (name, props = {}) => (
        <Field label={t(`profile.${name}`)} error={form.errors[name]}>
            {(id, describedBy) => (
                <TextInput
                    id={id}
                    value={form.data[name]}
                    onChange={(e) => form.setData(name, e.target.value)}
                    error={form.errors[name]}
                    aria-describedby={describedBy}
                    {...props}
                />
            )}
        </Field>
    );

    return (
        <Card>
            <form onSubmit={submit}>
                <div className="border-line flex items-center justify-between gap-3 border-b px-5 py-4">
                    <h2 className="text-ink text-base font-bold">{t('profile.details')}</h2>
                    {profile.role && (
                        <span className="text-ink-subtle flex items-center gap-2 text-xs">
                            {t('profile.role')}
                            <Badge tone="brand">{t(`roles.${profile.role}`)}</Badge>
                        </span>
                    )}
                </div>
                <div className="grid gap-5 p-5 sm:grid-cols-2">
                    {input('name', { required: true, maxLength: 100, autoComplete: 'name' })}
                    {input('job_title', { maxLength: 100, placeholder: t('profile.job_title_placeholder'), autoComplete: 'organization-title' })}
                    {input('phone', { type: 'tel', dir: 'ltr', maxLength: 30, placeholder: '+966 5x xxx xxxx', autoComplete: 'tel' })}
                    <Field label={t('profile.email')} hint={t('profile.email_hint')}>
                        {(id, describedBy) => <TextInput id={id} value={profile.email} dir="ltr" readOnly disabled aria-describedby={describedBy} />}
                    </Field>
                </div>
                <div className="border-line flex justify-end border-t px-5 py-4">
                    <Button type="submit" disabled={form.processing || !form.isDirty}>
                        {form.processing ? t('common.saving') : t('common.save')}
                    </Button>
                </div>
            </form>
        </Card>
    );
}

DetailsCard.propTypes = { profile: ProfileEdit.propTypes.profile };
