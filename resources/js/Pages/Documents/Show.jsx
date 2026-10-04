import { Link, router, useForm } from '@inertiajs/react';
import { CalendarClock, Copy, FilePen, FileText, Pencil, RefreshCw, Send, Trash2 } from 'lucide-react';
import PropTypes from 'prop-types';
import { useState } from 'react';
import { DocumentPaper } from '@/Components/Document/DocumentPaper';
import { SendDialog } from '@/Components/Document/SendDialog';
import { StatusBadge } from '@/Components/Document/StatusBadge';
import { TrackingTimeline } from '@/Components/Document/TrackingTimeline';
import { Button, ButtonLink } from '@/Components/ui/Button';
import { ConfirmDialog } from '@/Components/ui/ConfirmDialog';
import { Dialog } from '@/Components/ui/Dialog';
import { Field, TextInput } from '@/Components/ui/Field';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/format';
import { useT } from '@/lib/i18n';
import { prefix } from './Form';

/** The team's view of one document: the paper, its status and what can be done next. */
export default function DocumentShow({ document, revisions, quote, invoice, can, sends, sendDefaults }) {
    const t = useT();
    const base = `/${prefix(document.type)}/${document.id}`;
    const [deleting, setDeleting] = useState(false);
    const [sending, setSending] = useState(false);
    const [extending, setExtending] = useState(false);
    const post = (action) => router.post(`${base}/${action}`);

    return (
        <AppLayout
            title={`${t(`documents.types.${document.type}.one`)} ${document.number}`}
            actions={<StatusBadge status={document.status} className="hidden sm:inline-flex" />}
        >
            <div className="mx-auto max-w-4xl space-y-5">
                {/* Actions */}
                <div className="flex flex-wrap items-center gap-2">
                    {can.send && (
                        <Button icon={<Send className="size-4" />} onClick={() => setSending(true)}>
                            {document.status === 'draft' ? t('share.send') : t('share.resend')}
                        </Button>
                    )}
                    {can.extend && document.type === 'quote' && (
                        <Button
                            variant={document.status === 'expired' ? 'primary' : 'secondary'}
                            icon={<CalendarClock className="size-4" />}
                            onClick={() => setExtending(true)}
                        >
                            {t('share.extend')}
                        </Button>
                    )}
                    {can.update && (
                        <ButtonLink href={`${base}/edit`} icon={<Pencil className="size-4" />}>
                            {t('documents.actions.edit')}
                        </ButtonLink>
                    )}
                    {can.revise && (
                        <Button icon={<FilePen className="size-4" />} onClick={() => post('revise')} title={t('documents.actions.revise_hint')}>
                            {t('documents.actions.revise')}
                        </Button>
                    )}
                    {can.convert && (
                        <Button icon={<FileText className="size-4" />} onClick={() => post('convert')}>
                            {t('documents.actions.convert')}
                        </Button>
                    )}
                    {can.duplicate && (
                        <Button variant="secondary" icon={<Copy className="size-4" />} onClick={() => post('duplicate')}>
                            {t('documents.actions.duplicate')}
                        </Button>
                    )}
                    {can.delete && (
                        <Button variant="ghost" icon={<Trash2 className="size-4" />} onClick={() => setDeleting(true)} className="ms-auto">
                            {t('documents.actions.delete')}
                        </Button>
                    )}
                </div>

                {document.is_replaced && (
                    <p className="flex items-center gap-2 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        <RefreshCw className="size-4 shrink-0" />
                        {t('tracking.replaced')}
                    </p>
                )}

                {/* Links between quote, invoice and revisions */}
                {(quote || invoice || revisions.length > 1) && (
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                        {quote && (
                            <Link href={`/quotes/${quote.id}`} className="text-brand-700 hover:underline">
                                {t('documents.from_quote', { number: quote.number })}
                            </Link>
                        )}
                        {invoice && (
                            <Link href={`/invoices/${invoice.id}`} className="text-brand-700 hover:underline">
                                {t('documents.converted_to', { number: invoice.number })}
                            </Link>
                        )}
                        {revisions.length > 1 && (
                            <span className="flex flex-wrap items-center gap-1.5">
                                <span className="text-ink-muted">{t('documents.revisions')}:</span>
                                {revisions.map((revision) => (
                                    <Link
                                        key={revision.id}
                                        href={`/${prefix(document.type)}/${revision.id}`}
                                        className={cn(
                                            'rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset',
                                            revision.is_current ? 'bg-brand-600 ring-brand-600 text-white' : 'bg-card text-ink-muted ring-line hover:text-ink',
                                        )}
                                        dir="ltr"
                                    >
                                        {revision.number}
                                    </Link>
                                ))}
                            </span>
                        )}
                    </div>
                )}

                {document.status !== 'draft' && <TrackingTimeline document={document} sends={sends} />}

                <DocumentPaper document={document} />
            </div>

            {sendDefaults && (
                <SendDialog open={sending} onClose={() => setSending(false)} url={`${base}/send`} number={document.number} defaults={sendDefaults} />
            )}

            <Dialog open={extending} title={t('share.extend_title')} onClose={() => setExtending(false)}>
                <ExtendForm url={`${base}/extend`} current={document.valid_until} onDone={() => setExtending(false)} />
            </Dialog>

            <ConfirmDialog
                open={deleting}
                title={t('documents.actions.delete_title', { number: document.number })}
                message={t('documents.actions.delete_message')}
                onConfirm={() => router.delete(base, { onFinish: () => setDeleting(false) })}
                onClose={() => setDeleting(false)}
            />
        </AppLayout>
    );
}

DocumentShow.propTypes = {
    document: PropTypes.shape({
        id: PropTypes.number.isRequired,
        type: PropTypes.string.isRequired,
        number: PropTypes.string.isRequired,
        status: PropTypes.string.isRequired,
        valid_until: PropTypes.string,
        is_replaced: PropTypes.bool,
    }).isRequired,
    revisions: PropTypes.arrayOf(PropTypes.shape({ id: PropTypes.number, number: PropTypes.string, is_current: PropTypes.bool })).isRequired,
    quote: PropTypes.shape({ id: PropTypes.number, number: PropTypes.string }),
    invoice: PropTypes.shape({ id: PropTypes.number, number: PropTypes.string }),
    can: PropTypes.objectOf(PropTypes.bool).isRequired,
    sends: PropTypes.array.isRequired,
    sendDefaults: PropTypes.object,
};

function ExtendForm({ url, current, onDone }) {
    const t = useT();
    const form = useForm({ valid_until: current ?? '' });

    const submit = (e) => {
        e.preventDefault();
        form.post(url, { preserveScroll: true, onSuccess: onDone });
    };

    return (
        <form onSubmit={submit} className="space-y-5">
            <Field label={t('documents.valid_until')} hint={t('share.extend_hint')} error={form.errors.valid_until}>
                {(id, describedBy) => (
                    <TextInput
                        id={id}
                        type="date"
                        dir="ltr"
                        value={form.data.valid_until}
                        onChange={(e) => form.setData('valid_until', e.target.value)}
                        error={form.errors.valid_until}
                        aria-describedby={describedBy}
                    />
                )}
            </Field>
            <div className="flex justify-end">
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? t('common.saving') : t('common.save')}
                </Button>
            </div>
        </form>
    );
}

ExtendForm.propTypes = { url: PropTypes.string.isRequired, current: PropTypes.string, onDone: PropTypes.func.isRequired };
