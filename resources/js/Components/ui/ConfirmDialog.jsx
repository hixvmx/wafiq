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
            className="rounded-card bg-card backdrop:bg-ink/50 m-auto w-[calc(100%-2rem)] max-w-md p-0 shadow-2xl"
        >
            <div className="p-6">
                <div className="flex items-start gap-4">
                    <div className="text-danger rounded-full bg-red-50 p-2.5">
                        <AlertTriangle className="size-5" />
                    </div>
                    <div>
                        <h2 className="text-ink text-base font-bold">{title}</h2>
                        <p className="text-ink-muted mt-1.5 text-sm leading-relaxed">{message}</p>
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
