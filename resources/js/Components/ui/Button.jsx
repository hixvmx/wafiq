import { Link } from '@inertiajs/react';
import PropTypes from 'prop-types';
import { cn } from '@/lib/format';

const base =
    'inline-flex items-center justify-center gap-2 rounded-xl font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-60 whitespace-nowrap';

const variants = {
    primary: 'bg-brand-600 text-white hover:bg-brand-700 shadow-sm',
    secondary: 'border border-line-strong bg-card text-ink hover:bg-surface',
    ghost: 'text-ink-muted hover:bg-surface hover:text-ink',
    danger: 'bg-danger text-white hover:bg-red-700',
};

const sizes = {
    sm: 'h-9 px-3 text-sm',
    md: 'h-11 px-5 text-sm',
    lg: 'h-13 px-7 text-base',
};

/**
 * @param {{ variant?: keyof typeof variants, size?: keyof typeof sizes }} [style]
 * @param {string} [extra]
 */
export function buttonClass({ variant = 'primary', size = 'md' } = {}, extra) {
    return cn(base, variants[variant], sizes[size], extra);
}

export function Button({ variant, size, icon, className, children, type = 'button', ...props }) {
    return (
        <button type={type} className={buttonClass({ variant, size }, className)} {...props}>
            {icon}
            {children}
        </button>
    );
}

export function ButtonLink({ variant, size, icon, className, children, ...props }) {
    return (
        <Link className={buttonClass({ variant, size }, className)} {...props}>
            {icon}
            {children}
        </Link>
    );
}

const styleProps = {
    variant: PropTypes.oneOf(Object.keys(variants)),
    size: PropTypes.oneOf(Object.keys(sizes)),
    icon: PropTypes.node,
    className: PropTypes.string,
    children: PropTypes.node,
};

Button.propTypes = { ...styleProps, type: PropTypes.oneOf(['button', 'submit', 'reset']) };
ButtonLink.propTypes = styleProps;
