import { router, useForm } from '@inertiajs/react';
import { ImageIcon, Trash2, Upload } from 'lucide-react';
import PropTypes from 'prop-types';
import { useRef, useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Field, TextInput } from '@/Components/ui/Field';
import SettingsLayout, { SaveButton, SettingsCard } from '@/Layouts/SettingsLayout';
import { useT } from '@/lib/i18n';

export default function Branding({ brandColor, images }) {
    const t = useT();
    const form = useForm({ brand_color: brandColor });

    const submit = (e) => {
        e.preventDefault();
        form.put('/settings/branding', { preserveScroll: true });
    };

    return (
        <SettingsLayout>
            <SettingsCard title={t('settings.sections.branding')} intro={t('settings.branding.intro')}>
                <div className="grid gap-4 sm:grid-cols-3">
                    {Object.keys(images).map((kind) => (
                        <ImageSlot key={kind} kind={kind} url={images[kind]} />
                    ))}
                </div>
            </SettingsCard>

            <form onSubmit={submit}>
                <SettingsCard footer={<SaveButton processing={form.processing} />}>
                    <Field label={t('settings.branding.color')} hint={t('settings.branding.color_hint')} error={form.errors.brand_color}>
                        {(id, describedBy) => (
                            <div className="flex items-center gap-3">
                                <input
                                    type="color"
                                    value={form.data.brand_color}
                                    onChange={(e) => form.setData('brand_color', e.target.value)}
                                    className="h-11 w-14 cursor-pointer rounded-xl border border-line-strong bg-card p-1"
                                    aria-label={t('settings.branding.color')}
                                />
                                <TextInput
                                    id={id}
                                    dir="ltr"
                                    className="w-32 font-mono"
                                    value={form.data.brand_color}
                                    onChange={(e) => form.setData('brand_color', e.target.value)}
                                    error={form.errors.brand_color}
                                    aria-describedby={describedBy}
                                />
                                <span className="h-11 flex-1 rounded-xl" style={{ background: form.data.brand_color }} aria-hidden />
                            </div>
                        )}
                    </Field>
                </SettingsCard>
            </form>
        </SettingsLayout>
    );
}

Branding.propTypes = { brandColor: PropTypes.string.isRequired, images: PropTypes.objectOf(PropTypes.string).isRequired };

/** One image (logo / stamp / signature): preview, upload or replace, remove. */
function ImageSlot({ kind, url }) {
    const t = useT();
    const input = useRef(null);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState(null);

    const upload = (file) => {
        if (!file) return;
        router.post(
            `/settings/branding/${kind}`,
            { image: file },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => {
                    setUploading(true);
                    setError(null);
                },
                onError: (errors) => setError(errors.image),
                onFinish: () => {
                    setUploading(false);
                    input.current.value = '';
                },
            },
        );
    };

    const remove = () => router.delete(`/settings/branding/${kind}`, { preserveScroll: true });

    return (
        <div className="flex flex-col rounded-xl border border-line p-4">
            <p className="text-sm font-semibold text-ink">{t(`settings.branding.images.${kind}`)}</p>
            <p className="mt-0.5 text-xs text-ink-subtle">{t(`settings.branding.image_hints.${kind}`)}</p>

            <div className="my-4 flex h-28 items-center justify-center rounded-lg bg-[repeating-conic-gradient(#f4f4f5_0_25%,#fff_0_50%)] bg-[length:16px_16px]">
                {url ? (
                    <img src={url} alt={t(`settings.branding.images.${kind}`)} className="max-h-24 max-w-full object-contain" />
                ) : (
                    <span className="flex flex-col items-center gap-1 text-xs text-ink-subtle">
                        <ImageIcon className="size-6" />
                        {t('settings.branding.empty')}
                    </span>
                )}
            </div>

            {error && (
                <p className="mb-2 text-xs font-medium text-danger" role="alert">
                    {error}
                </p>
            )}

            <div className="mt-auto flex gap-2">
                <input ref={input} type="file" accept="image/png,image/jpeg,image/webp" className="hidden" onChange={(e) => upload(e.target.files[0])} />
                <Button variant="secondary" size="sm" className="flex-1" icon={<Upload className="size-4" />} disabled={uploading} onClick={() => input.current.click()}>
                    {uploading ? t('settings.branding.uploading') : url ? t('settings.branding.replace') : t('settings.branding.upload')}
                </Button>
                {url && (
                    <Button variant="ghost" size="sm" onClick={remove} aria-label={t('settings.branding.remove')} title={t('settings.branding.remove')}>
                        <Trash2 className="size-4" />
                    </Button>
                )}
            </div>
        </div>
    );
}

ImageSlot.propTypes = { kind: PropTypes.string.isRequired, url: PropTypes.string };
