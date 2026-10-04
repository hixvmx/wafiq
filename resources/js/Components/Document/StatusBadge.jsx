import { CheckCircle2, Clock, Eye, FileEdit, Send, XCircle } from 'lucide-react';
import PropTypes from 'prop-types';
import { cn } from '@/lib/format';
import { useT } from '@/lib/i18n';

const STYLES = {
    draft: ['bg-surface text-ink-muted ring-line', FileEdit],
    sent: ['bg-sky-50 text-sky-700 ring-sky-200', Send],
    viewed: ['bg-violet-50 text-violet-700 ring-violet-200', Eye],
    approved: ['bg-green-50 text-success ring-green-200', CheckCircle2],
    rejected: ['bg-red-50 text-danger ring-red-200', XCircle],
    expired: ['bg-amber-50 text-warning ring-amber-200', Clock],
};

/** Draft / Sent / Viewed / Approved / Rejected / Expired. */
export function StatusBadge({ status, className }) {
    const t = useT();
    const [style, Icon] = STYLES[status] ?? STYLES.draft;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset',
                style,
                className,
            )}
        >
            <Icon className="size-3.5" />
            {t(`statuses.${status}`)}
        </span>
    );
}

StatusBadge.propTypes = { status: PropTypes.string.isRequired, className: PropTypes.string };
