import PropTypes from 'prop-types';
import { cn } from '@/lib/format';

export function Card({ className, ...props }) {
    return <div className={cn('rounded-card border border-line bg-card shadow-card', className)} {...props} />;
}

Card.propTypes = { className: PropTypes.string };

export function SectionTitle({ title, subtitle, action }) {
    return (
        <div className="mb-5 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 className="text-xl font-bold text-ink sm:text-2xl">{title}</h2>
                {subtitle && <p className="mt-1 text-sm text-ink-muted">{subtitle}</p>}
            </div>
            {action}
        </div>
    );
}

SectionTitle.propTypes = { title: PropTypes.node.isRequired, subtitle: PropTypes.node, action: PropTypes.node };

const tones = {
    neutral: 'bg-surface text-ink-muted ring-line',
    brand: 'bg-brand-50 text-brand-700 ring-brand-200',
    success: 'bg-green-50 text-success ring-green-200',
    warning: 'bg-amber-50 text-warning ring-amber-200',
    danger: 'bg-red-50 text-danger ring-red-200',
};

export function Badge({ tone = 'neutral', className, ...props }) {
    return <span className={cn('inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset', tones[tone], className)} {...props} />;
}

Badge.propTypes = { tone: PropTypes.oneOf(Object.keys(tones)), className: PropTypes.string };

export function EmptyState({ icon, title, text, action }) {
    return (
        <div className="flex flex-col items-center rounded-card border border-dashed border-line-strong bg-card px-6 py-14 text-center">
            {icon && <div className="mb-4 rounded-full bg-surface p-4 text-ink-subtle">{icon}</div>}
            <h3 className="text-base font-semibold text-ink">{title}</h3>
            {text && <p className="mt-1 max-w-sm text-sm text-ink-muted">{text}</p>}
            {action && <div className="mt-6">{action}</div>}
        </div>
    );
}

EmptyState.propTypes = { icon: PropTypes.node, title: PropTypes.string.isRequired, text: PropTypes.string, action: PropTypes.node };

export function Avatar({ src, name, size = 40 }) {
    const style = { width: size, height: size };

    if (src) {
        return <img src={src} alt={name} style={style} className="shrink-0 rounded-full object-cover ring-1 ring-line" />;
    }

    return (
        <span style={{ ...style, fontSize: size * 0.4 }} className="inline-flex shrink-0 items-center justify-center rounded-full bg-brand-100 font-bold text-brand-700" aria-hidden>
            {name.trim().charAt(0) || '?'}
        </span>
    );
}

Avatar.propTypes = { src: PropTypes.string, name: PropTypes.string.isRequired, size: PropTypes.number };
