import { Link, router } from '@inertiajs/react';
import { Copy, FilePen, FileText, Info, Pencil, Trash2 } from 'lucide-react';
import PropTypes from 'prop-types';
import { useState } from 'react';
import { DocumentPaper } from '@/Components/Document/DocumentPaper';
import { StatusBadge } from '@/Components/Document/StatusBadge';
import { Button, ButtonLink } from '@/Components/ui/Button';
import { ConfirmDialog } from '@/Components/ui/ConfirmDialog';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/format';
import { useT } from '@/lib/i18n';
import { prefix } from './Form';

/** The team's view of one document: the paper, its status and what can be done next. */
export default function DocumentShow({ document, revisions, quote, invoice, can }) {
    const t = useT();
    const base = `/${prefix(document.type)}/${document.id}`;
    const [deleting, setDeleting] = useState(false);
    const post = (action) => router.post(`${base}/${action}`);

    return (
        <AppLayout
            title={`${t(`documents.types.${document.type}.one`)} ${document.number}`}
            actions={<StatusBadge status={document.status} className="hidden sm:inline-flex" />}
        >
            <div className="mx-auto max-w-4xl space-y-5">
                {/* Actions */}
                <div className="flex flex-wrap items-center gap-2">
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

                {document.status === 'draft' && (
                    <p className="flex items-center gap-2 rounded-xl bg-sky-50 px-4 py-3 text-sm text-sky-800">
                        <Info className="size-4 shrink-0" />
                        {t('documents.send_soon')}
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
                                            revision.is_current ? 'bg-brand-600 text-white ring-brand-600' : 'bg-card text-ink-muted ring-line hover:text-ink',
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

                <DocumentPaper document={document} />
            </div>

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
    document: PropTypes.shape({ id: PropTypes.number.isRequired, type: PropTypes.string.isRequired, number: PropTypes.string.isRequired, status: PropTypes.string.isRequired }).isRequired,
    revisions: PropTypes.arrayOf(PropTypes.shape({ id: PropTypes.number, number: PropTypes.string, is_current: PropTypes.bool })).isRequired,
    quote: PropTypes.shape({ id: PropTypes.number, number: PropTypes.string }),
    invoice: PropTypes.shape({ id: PropTypes.number, number: PropTypes.string }),
    can: PropTypes.objectOf(PropTypes.bool).isRequired,
};
