import { X } from 'lucide-react';
import PropTypes from 'prop-types';
import { useEffect, useRef } from 'react';
import { useT } from '@/lib/i18n';

/** Modal built on the native <dialog> element (focus trap and Esc for free). */
export function Dialog({ open, title, onClose, children, footer, size = 'md' }) {
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
            className={`m-auto w-[calc(100%-2rem)] ${size === 'lg' ? 'max-w-2xl' : 'max-w-lg'} rounded-card bg-card p-0 shadow-2xl backdrop:bg-ink/50`}
        >
            {open && (
                <div className="flex max-h-[calc(100dvh-2rem)] flex-col">
                    <header className="flex items-center justify-between gap-4 border-b border-line px-5 py-4">
                        <h2 className="text-base font-bold text-ink">{title}</h2>
                        <button type="button" onClick={onClose} className="rounded-lg p-1.5 text-ink-subtle hover:bg-surface hover:text-ink" aria-label={t('common.close')}>
                            <X className="size-5" />
                        </button>
                    </header>
                    <div className="overflow-y-auto p-5">{children}</div>
                    {footer && <footer className="flex justify-end gap-2 border-t border-line px-5 py-4">{footer}</footer>}
                </div>
            )}
        </dialog>
    );
}

Dialog.propTypes = {
    open: PropTypes.bool.isRequired,
    title: PropTypes.node.isRequired,
    onClose: PropTypes.func.isRequired,
    children: PropTypes.node,
    footer: PropTypes.node,
    size: PropTypes.oneOf(['md', 'lg']),
};
