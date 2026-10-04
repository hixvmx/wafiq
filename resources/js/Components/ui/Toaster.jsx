import { usePage } from '@inertiajs/react';
import { CheckCircle2, X, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import { cn } from '@/lib/format';
import { useT } from '@/lib/i18n';

let nextId = 1;

/** Shows the session flash messages ("success" / "error") as dismissible toasts. */
export function Toaster() {
    const t = useT();
    const { flash } = usePage().props;
    const [toasts, setToasts] = useState([]);

    useEffect(() => {
        const incoming = [];
        if (flash?.success) incoming.push({ id: nextId++, type: 'success', message: flash.success });
        if (flash?.error) incoming.push({ id: nextId++, type: 'error', message: flash.error });
        if (!incoming.length) return;

        // eslint-disable-next-line react-hooks/set-state-in-effect -- flash props arrive from the server on each visit
        setToasts((current) => [...current, ...incoming]);
        const timer = setTimeout(() => setToasts((current) => current.filter((toast) => !incoming.includes(toast))), 5000);
        return () => clearTimeout(timer);
    }, [flash]);

    return (
        <div className="pointer-events-none fixed inset-x-0 bottom-6 z-50 flex flex-col items-center gap-2 px-4" aria-live="polite">
            {toasts.map((toast) => (
                <div
                    key={toast.id}
                    role="status"
                    className={cn(
                        'bg-card pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border p-4 text-sm shadow-lg',
                        toast.type === 'success' ? 'border-green-200' : 'border-red-200',
                    )}
                >
                    {toast.type === 'success' ? <CheckCircle2 className="text-success size-5 shrink-0" /> : <XCircle className="text-danger size-5 shrink-0" />}
                    <p className="text-ink flex-1">{toast.message}</p>
                    <button
                        type="button"
                        onClick={() => setToasts((current) => current.filter((item) => item.id !== toast.id))}
                        className="text-ink-subtle hover:text-ink"
                        aria-label={t('common.close')}
                    >
                        <X className="size-4" />
                    </button>
                </div>
            ))}
        </div>
    );
}
