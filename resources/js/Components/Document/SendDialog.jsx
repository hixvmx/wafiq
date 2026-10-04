import { router } from '@inertiajs/react';
import axios from 'axios';
import { Check, Copy, Link2, Mail, MessageCircle, Send } from 'lucide-react';
import PropTypes from 'prop-types';
import { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Dialog } from '@/Components/ui/Dialog';
import { Field, TextArea, TextInput } from '@/Components/ui/Field';
import { cn } from '@/lib/format';
import { useT } from '@/lib/i18n';

const CHANNELS = [
    ['whatsapp', MessageCircle],
    ['email', Mail],
    ['link', Link2],
];

/**
 * Send a document: WhatsApp (opens wa.me with the message), email, or a link to copy.
 * Each send creates its own tracked link on the server.
 */
export function SendDialog({ open, onClose, url, number, defaults }) {
    const t = useT();
    const [channel, setChannel] = useState('whatsapp');
    const [values, setValues] = useState(() => ({
        whatsapp: { recipient: defaults.whatsapp.phone ?? '', message: defaults.whatsapp.message },
        email: { recipient: defaults.email.to ?? '', subject: defaults.email.subject, message: defaults.email.body },
        link: {},
    }));
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);
    const [result, setResult] = useState(null); // { channel, url, whatsapp_url, email_failed, copied, blocked }

    const set = (key, value) => setValues((v) => ({ ...v, [channel]: { ...v[channel], [key]: value } }));

    const send = async () => {
        setBusy(true);
        setErrors({});
        // Open the WhatsApp tab now, while we still have the click: browsers block windows opened later.
        const whatsappWindow = channel === 'whatsapp' ? window.open('', '_blank') : null;

        try {
            const { data } = await axios.post(url, { channel, ...values[channel] }, { headers: { Accept: 'application/json' } });
            let copied = false;
            let blocked = false;

            if (channel === 'whatsapp') {
                if (whatsappWindow) whatsappWindow.location.href = data.whatsapp_url;
                else blocked = true;
            }
            if (channel === 'link') {
                copied = await navigator.clipboard?.writeText(data.url).then(
                    () => true,
                    () => false,
                );
            }

            setResult({ channel, ...data, copied, blocked });
            router.reload({ only: ['document', 'sends', 'can', 'sendDefaults'] });
        } catch (error) {
            whatsappWindow?.close();
            const fieldErrors = error.response?.data?.errors ?? {};
            setErrors(Object.fromEntries(Object.entries(fieldErrors).map(([key, messages]) => [key, messages[0]])));
        } finally {
            setBusy(false);
        }
    };

    const close = () => {
        setResult(null);
        setErrors({});
        onClose();
    };

    return (
        <Dialog open={open} title={t('share.title', { number })} onClose={close} size="lg">
            {result ? (
                <SendResult result={result} onDone={close} />
            ) : (
                <div className="space-y-5">
                    <div className="grid grid-cols-3 gap-2" role="tablist">
                        {CHANNELS.map(([key, Icon]) => (
                            <button
                                key={key}
                                type="button"
                                role="tab"
                                aria-selected={channel === key}
                                onClick={() => {
                                    setChannel(key);
                                    setErrors({});
                                }}
                                className={cn(
                                    'flex flex-col items-center gap-1 rounded-xl border px-3 py-3 text-sm font-medium transition-colors',
                                    channel === key ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-line-strong text-ink-muted hover:bg-surface',
                                )}
                            >
                                <Icon className="size-5" />
                                {t(`share.channels.${key}`)}
                            </button>
                        ))}
                    </div>

                    {channel === 'whatsapp' && (
                        <>
                            <Field label={t('share.phone')} hint={t('share.phone_hint')} error={errors.recipient}>
                                {(id, describedBy) => (
                                    <TextInput
                                        id={id}
                                        type="tel"
                                        dir="ltr"
                                        value={values.whatsapp.recipient}
                                        onChange={(e) => set('recipient', e.target.value)}
                                        error={errors.recipient}
                                        aria-describedby={describedBy}
                                    />
                                )}
                            </Field>
                            <Field label={t('share.message')} hint={t('share.message_hint')} error={errors.message}>
                                {(id, describedBy) => (
                                    <TextArea
                                        id={id}
                                        className="min-h-40"
                                        value={values.whatsapp.message}
                                        onChange={(e) => set('message', e.target.value)}
                                        error={errors.message}
                                        aria-describedby={describedBy}
                                    />
                                )}
                            </Field>
                        </>
                    )}

                    {channel === 'email' && (
                        <>
                            <Field label={t('share.to')} error={errors.recipient}>
                                {(id) => (
                                    <TextInput
                                        id={id}
                                        type="email"
                                        value={values.email.recipient}
                                        onChange={(e) => set('recipient', e.target.value)}
                                        error={errors.recipient}
                                    />
                                )}
                            </Field>
                            <Field label={t('share.subject')} error={errors.subject}>
                                {(id) => (
                                    <TextInput id={id} value={values.email.subject} onChange={(e) => set('subject', e.target.value)} error={errors.subject} />
                                )}
                            </Field>
                            <Field label={t('share.body')} hint={t('share.message_hint')} error={errors.message}>
                                {(id, describedBy) => (
                                    <TextArea
                                        id={id}
                                        className="min-h-40"
                                        value={values.email.message}
                                        onChange={(e) => set('message', e.target.value)}
                                        error={errors.message}
                                        aria-describedby={describedBy}
                                    />
                                )}
                            </Field>
                        </>
                    )}

                    {channel === 'link' && <p className="bg-surface text-ink-muted rounded-xl p-4 text-sm">{t('share.link_hint')}</p>}

                    <div className="border-line flex justify-end gap-2 border-t pt-4">
                        <Button variant="secondary" onClick={close}>
                            {t('common.cancel')}
                        </Button>
                        <Button onClick={send} disabled={busy} icon={channel === 'link' ? <Copy className="size-4" /> : <Send className="size-4" />}>
                            {busy
                                ? t('common.processing')
                                : t(channel === 'whatsapp' ? 'share.open_whatsapp' : channel === 'email' ? 'share.send_email' : 'share.create_link')}
                        </Button>
                    </div>
                </div>
            )}
        </Dialog>
    );
}

SendDialog.propTypes = {
    open: PropTypes.bool.isRequired,
    onClose: PropTypes.func.isRequired,
    url: PropTypes.string.isRequired,
    number: PropTypes.string.isRequired,
    defaults: PropTypes.shape({
        whatsapp: PropTypes.shape({ phone: PropTypes.string, message: PropTypes.string }).isRequired,
        email: PropTypes.shape({ to: PropTypes.string, subject: PropTypes.string, body: PropTypes.string }).isRequired,
    }).isRequired,
};

function SendResult({ result, onDone }) {
    const t = useT();
    const [copied, setCopied] = useState(result.copied);

    const copy = () => navigator.clipboard?.writeText(result.url).then(() => setCopied(true));

    let message;
    if (result.channel === 'whatsapp') message = result.blocked ? null : t('share.whatsapp_opened');
    else if (result.channel === 'email') message = result.email_failed ? t('share.email_failed') : t('share.email_sent', { email: result.recipient });
    else message = copied ? t('share.copied') : null;

    return (
        <div className="space-y-4">
            {message && (
                <p
                    className={cn(
                        'flex items-center gap-2 rounded-xl px-4 py-3 text-sm',
                        result.email_failed ? 'text-danger bg-red-50' : 'bg-green-50 text-green-800',
                    )}
                >
                    <Check className="size-4 shrink-0" />
                    {message}
                </p>
            )}
            {result.blocked && (
                <a
                    href={result.whatsapp_url}
                    target="_blank"
                    rel="noreferrer"
                    className="block rounded-xl bg-green-50 px-4 py-3 text-sm font-semibold text-green-800 underline"
                >
                    {t('share.whatsapp_blocked')}
                </a>
            )}
            <div className="flex gap-2" dir="ltr">
                <TextInput readOnly value={result.url} onFocus={(e) => e.target.select()} className="font-mono text-xs" aria-label="link" />
                <Button variant="secondary" onClick={copy} icon={copied ? <Check className="size-4" /> : <Copy className="size-4" />}>
                    {t('share.copy')}
                </Button>
            </div>
            <div className="border-line flex justify-end border-t pt-4">
                <Button onClick={onDone}>{t('common.close')}</Button>
            </div>
        </div>
    );
}

SendResult.propTypes = { result: PropTypes.object.isRequired, onDone: PropTypes.func.isRequired };
