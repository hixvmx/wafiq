import PropTypes from 'prop-types';
import { cn } from '@/lib/format';

export function Card({ className, ...props }) {
    return <div className={cn('rounded-card border-line bg-card shadow-card border', className)} {...props} />;
}

Card.propTypes = { className: PropTypes.string };

export function SectionTitle({ title, subtitle, action }) {
    return (
        <div className="mb-5 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 className="text-ink text-xl font-bold sm:text-2xl">{title}</h2>
                {subtitle && <p className="text-ink-muted mt-1 text-sm">{subtitle}</p>}
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
    return (
        <span
            className={cn('inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset', tones[tone], className)}
            {...props}
        />
    );
}

Badge.propTypes = { tone: PropTypes.oneOf(Object.keys(tones)), className: PropTypes.string };

export function EmptyState({ icon, title, text, action }) {
    return (
        <div className="rounded-card border-line-strong bg-card flex flex-col items-center border border-dashed px-6 py-14 text-center">
            {icon && <div className="bg-surface text-ink-subtle mb-4 rounded-full p-4">{icon}</div>}
            <h3 className="text-ink text-base font-semibold">{title}</h3>
            {text && <p className="text-ink-muted mt-1 max-w-sm text-sm">{text}</p>}
            {action && <div className="mt-6">{action}</div>}
        </div>
    );
}

EmptyState.propTypes = { icon: PropTypes.node, title: PropTypes.string.isRequired, text: PropTypes.string, action: PropTypes.node };

export function Avatar({ src, name, size = 40 }) {
    const style = { width: size, height: size };

    if (src) {
        return <img src={src} alt={name} style={style} className="ring-line shrink-0 rounded-full object-cover ring-1" />;
    }

    return (
        <span
            style={{ ...style, fontSize: size * 0.4 }}
            className="bg-brand-100 text-brand-700 inline-flex shrink-0 items-center justify-center rounded-full font-bold"
            aria-hidden
        >
            {name.trim().charAt(0) || '?'}
        </span>
    );
}

Avatar.propTypes = { src: PropTypes.string, name: PropTypes.string.isRequired, size: PropTypes.number };
