import { router, useForm } from '@inertiajs/react';
import axios from 'axios';
import { CheckCircle2, Clock, Eye, RefreshCw, XCircle } from 'lucide-react';
import PropTypes from 'prop-types';
import { useEffect, useState } from 'react';
import { DocumentPaper } from '@/Components/Document/DocumentPaper';
import { Button } from '@/Components/ui/Button';
import { Dialog } from '@/Components/ui/Dialog';
import { Checkbox, Field, Select, TextArea, TextInput } from '@/Components/ui/Field';
import { Toaster } from '@/Components/ui/Toaster';
import PublicLayout from '@/Layouts/PublicLayout';
import { formatDate, formatDateTime } from '@/lib/format';
import { useT } from '@/lib/i18n';

/** Seconds the page must be visible before it counts as viewed (preview robots never get there). */
const VIEW_AFTER_MS = 2000;

/** What the client sees behind a tracked link: the document, its state, Approve / Reject. */
export default function PublicDocument({ token, document: doc, state, answer, canRespond, isTeam, rejectReasons }) {
    const t = useT();
    const [dialog, setDialog] = useState(null); // 'approve' | 'reject' | null
    const typeName = t(`documents.types.${doc.type}.one`);

    useViewSignal(token, isTeam);

    return (
        <PublicLayout title={`${typeName} ${doc.number}`} brand={{ name: doc.company.name, logo_url: doc.company.logo_url, color: doc.company.brand_color }}>
            <div className="mx-auto max-w-4xl space-y-4 pb-28">
                {isTeam && <Banner tone="info" icon={Eye} text={t('public.team_banner')} />}
                <StateBanner state={state} answer={answer} validUntil={doc.valid_until} token={token} />
                <DocumentPaper document={doc} />
            </div>

            {canRespond && (
                <div className="border-line bg-card/95 fixed inset-x-0 bottom-0 z-20 border-t px-4 py-3 backdrop-blur">
                    <div className="mx-auto flex max-w-4xl items-center gap-3">
                        <p className="text-ink-muted hidden flex-1 text-sm sm:block">
                            {doc.valid_until && t('public.valid_until', { date: formatDate(doc.valid_until) })}
                        </p>
                        <Button variant="secondary" className="flex-1 sm:flex-none" icon={<XCircle className="size-4" />} onClick={() => setDialog('reject')}>
                            {t('public.reject')}
                        </Button>
                        <Button className="flex-1 sm:flex-none sm:px-10" icon={<CheckCircle2 className="size-4" />} onClick={() => setDialog('approve')}>
                            {t('public.approve')}
                        </Button>
                    </div>
                </div>
            )}

            <Dialog open={dialog === 'approve'} title={t('public.approve_title', { type: typeName })} onClose={() => setDialog(null)}>
                <ApproveForm token={token} onDone={() => setDialog(null)} />
            </Dialog>
            <Dialog open={dialog === 'reject'} title={t('public.reject_title', { type: typeName })} onClose={() => setDialog(null)}>
                <RejectForm token={token} reasons={rejectReasons} onDone={() => setDialog(null)} />
            </Dialog>
            <Toaster />
        </PublicLayout>
    );
}

PublicDocument.propTypes = {
    token: PropTypes.string.isRequired,
    document: PropTypes.object.isRequired,
    state: PropTypes.string.isRequired,
    answer: PropTypes.object.isRequired,
    canRespond: PropTypes.bool.isRequired,
    isTeam: PropTypes.bool.isRequired,
    rejectReasons: PropTypes.arrayOf(PropTypes.string).isRequired,
};

/**
 * Tells the server "a person is reading this" once the page has been visible for 2 seconds.
 * Link-preview robots don't run JavaScript or leave in less, so they never count.
 */
function useViewSignal(token, isTeam) {
    useEffect(() => {
        if (isTeam) return;
        let timer;
        let sent = false;

        const start = () => {
            clearTimeout(timer);
            if (sent || window.document.visibilityState !== 'visible') return;
            timer = setTimeout(() => {
                sent = true;
                axios.post(`/d/${token}/view`).catch(() => {});
            }, VIEW_AFTER_MS);
        };

        start();
        window.document.addEventListener('visibilitychange', start);
        return () => {
            clearTimeout(timer);
            window.document.removeEventListener('visibilitychange', start);
        };
    }, [token, isTeam]);
}

function StateBanner({ state, answer, token }) {
    const t = useT();

    switch (state) {
        case 'approved':
            return (
                <Banner
                    tone="success"
                    icon={CheckCircle2}
                    text={t('public.approved_banner', { name: answer.approved_by_name, date: formatDateTime(answer.approved_at) })}
                />
            );
        case 'rejected':
            return <Banner tone="danger" icon={XCircle} text={t('public.rejected_banner', { date: formatDateTime(answer.rejected_at) })} />;
        case 'expired':
            return <Banner tone="warning" icon={Clock} text={t('public.expired_banner', { date: formatDate(answer.expired_at) })} />;
        case 'replaced':
            return (
                <Banner tone="warning" icon={RefreshCw} text={t('public.replaced_banner')}>
                    <Button size="sm" onClick={() => router.post(`/d/${token}/latest`)}>
                        {t('public.view_latest')}
                    </Button>
                </Banner>
            );
        default:
            return null;
    }
}

StateBanner.propTypes = { state: PropTypes.string.isRequired, answer: PropTypes.object.isRequired, token: PropTypes.string.isRequired };

const TONES = {
    success: 'bg-green-50 text-green-800 ring-green-200',
    danger: 'bg-red-50 text-red-800 ring-red-200',
    warning: 'bg-amber-50 text-amber-900 ring-amber-200',
    info: 'bg-sky-50 text-sky-800 ring-sky-200',
};

function Banner({ tone, icon: Icon, text, children }) {
    return (
        <div className={`flex flex-wrap items-center gap-3 rounded-xl px-4 py-3 text-sm ring-1 ring-inset ${TONES[tone]}`}>
            <Icon className="size-5 shrink-0" />
            <p className="flex-1">{text}</p>
            {children}
        </div>
    );
}

Banner.propTypes = {
    tone: PropTypes.oneOf(Object.keys(TONES)).isRequired,
    icon: PropTypes.elementType.isRequired,
    text: PropTypes.string.isRequired,
    children: PropTypes.node,
};

function ApproveForm({ token, onDone }) {
    const t = useT();
    const form = useForm({ name: '', agree: false });

    const submit = (e) => {
        e.preventDefault();
        form.post(`/d/${token}/approve`, { preserveScroll: true, onSuccess: onDone });
    };

    return (
        <form onSubmit={submit} className="space-y-5">
            <Field label={t('public.your_name')} error={form.errors.name}>
                {(id) => (
                    <TextInput
                        id={id}
                        autoComplete="name"
                        autoFocus
                        required
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        error={form.errors.name}
                    />
                )}
            </Field>
            <div>
                <Checkbox label={t('public.agree')} checked={form.data.agree} onChange={(e) => form.setData('agree', e.target.checked)} required />
                {form.errors.agree && <p className="text-danger mt-1 text-xs font-medium">{form.errors.agree}</p>}
            </div>
            <p className="bg-surface text-ink-muted rounded-lg px-3 py-2 text-xs leading-relaxed">{t('public.approve_note')}</p>
            <Button type="submit" className="w-full" icon={<CheckCircle2 className="size-4" />} disabled={form.processing || !form.data.agree}>
                {form.processing ? t('common.processing') : t('public.confirm_approve')}
            </Button>
        </form>
    );
}

ApproveForm.propTypes = { token: PropTypes.string.isRequired, onDone: PropTypes.func.isRequired };

function RejectForm({ token, reasons, onDone }) {
    const t = useT();
    const form = useForm({ reason: '', details: '' });

    const submit = (e) => {
        e.preventDefault();
        form.post(`/d/${token}/reject`, { preserveScroll: true, onSuccess: onDone });
    };

    return (
        <form onSubmit={submit} className="space-y-5">
            <Field label={t('public.reject_reason')} error={form.errors.reason}>
                {(id) => (
                    <Select id={id} value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)}>
                        <option value="">{t('common.choose')}</option>
                        {reasons.map((reason) => (
                            <option key={reason} value={reason}>
                                {reason}
                            </option>
                        ))}
                    </Select>
                )}
            </Field>
            <Field label={t('public.reject_details')} error={form.errors.details}>
                {(id) => <TextArea id={id} className="min-h-24" value={form.data.details} onChange={(e) => form.setData('details', e.target.value)} />}
            </Field>
            <Button type="submit" variant="danger" className="w-full" disabled={form.processing}>
                {form.processing ? t('common.processing') : t('public.confirm_reject')}
            </Button>
        </form>
    );
}

RejectForm.propTypes = { token: PropTypes.string.isRequired, reasons: PropTypes.arrayOf(PropTypes.string).isRequired, onDone: PropTypes.func.isRequired };
