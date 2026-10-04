import { AlertTriangle } from 'lucide-react';
import PropTypes from 'prop-types';
import { useEffect, useRef } from 'react';
import { useT } from '@/lib/i18n';
import { Button } from './Button';

/** Accessible confirmation modal built on the native <dialog> element. */
export function ConfirmDialog({ open, title, message, confirmLabel, processing, onConfirm, onClose }) {
    const t = useT();
    const ref = useRef(null);

    useEffect(() => {
        const dialog = ref.current;
        if (!dialog) return;
        if (open && !dialog.open) dialog.showModal();
        if (!open && dialog.open) dialog.close();
    }, [open]);

    return (
        <dialog
            ref={ref}
            onClose={onClose}
            onClick={(e) => e.target === ref.current && onClose()}
            className="m-auto w-[calc(100%-2rem)] max-w-md rounded-card bg-card p-0 shadow-2xl backdrop:bg-ink/50"
        >
            <div className="p-6">
                <div className="flex items-start gap-4">
                    <div className="rounded-full bg-red-50 p-2.5 text-danger">
                        <AlertTriangle className="size-5" />
                    </div>
                    <div>
                        <h2 className="text-base font-bold text-ink">{title}</h2>
                        <p className="mt-1.5 text-sm leading-relaxed text-ink-muted">{message}</p>
                    </div>
                </div>
                <div className="mt-6 flex justify-end gap-2">
                    <Button variant="secondary" onClick={onClose} disabled={processing}>
                        {t('common.cancel')}
                    </Button>
                    <Button variant="danger" onClick={onConfirm} disabled={processing}>
                        {processing ? t('common.processing') : (confirmLabel ?? t('common.delete'))}
                    </Button>
                </div>
            </div>
        </dialog>
    );
}

ConfirmDialog.propTypes = {
    open: PropTypes.bool.isRequired,
    title: PropTypes.string.isRequired,
    message: PropTypes.string.isRequired,
    confirmLabel: PropTypes.string,
    processing: PropTypes.bool,
    onConfirm: PropTypes.func.isRequired,
    onClose: PropTypes.func.isRequired,
};
