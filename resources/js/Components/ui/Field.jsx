import PropTypes from 'prop-types';
import { useId } from 'react';
import { cn } from '@/lib/format';

const control =
    'block w-full rounded-xl border bg-card px-3.5 text-sm text-ink placeholder:text-ink-subtle transition-colors focus:border-brand-500 focus:outline-none focus:ring-3 focus:ring-brand-100 disabled:bg-surface';

function controlClass(error, extra) {
    return cn(control, error ? 'border-danger' : 'border-line-strong', extra);
}

/**
 * Label + control + hint + error, wired together for screen readers.
 *
 *   <Field label="البريد" error={errors.email}>
 *       {(id, describedBy) => <TextInput id={id} aria-describedby={describedBy} … />}
 *   </Field>
 */
export function Field({ label, hint, error, optional, optionalLabel, className, children }) {
    const id = useId();
    const hintId = hint ? `${id}-hint` : undefined;
    const errorId = error ? `${id}-error` : undefined;
    const describedBy = [hintId, errorId].filter(Boolean).join(' ') || undefined;

    return (
        <div className={cn('space-y-1.5', className)}>
            {label && (
                <label htmlFor={id} className="text-ink block text-sm font-medium">
                    {label}
                    {optional && <span className="text-ink-subtle ms-1.5 text-xs font-normal">({optionalLabel})</span>}
                </label>
            )}
            {children(id, describedBy)}
            {hint && !error && (
                <p id={hintId} className="text-ink-subtle text-xs">
                    {hint}
                </p>
            )}
            {error && (
                <p id={errorId} className="text-danger text-xs font-medium" role="alert">
                    {error}
                </p>
            )}
        </div>
    );
}

Field.propTypes = {
    label: PropTypes.node,
    hint: PropTypes.node,
    error: PropTypes.string,
    optional: PropTypes.bool,
    optionalLabel: PropTypes.string,
    className: PropTypes.string,
    children: PropTypes.func.isRequired,
};

const controlProps = { error: PropTypes.string, className: PropTypes.string };

export function TextInput({ error, className, ...props }) {
    return <input className={controlClass(error, cn('h-11', className))} aria-invalid={!!error} {...props} />;
}

TextInput.propTypes = controlProps;

export function TextArea({ error, className, ...props }) {
    return <textarea className={controlClass(error, cn('min-h-32 py-2.5 leading-relaxed', className))} aria-invalid={!!error} {...props} />;
}

TextArea.propTypes = controlProps;

export function Select({ error, className, children, ...props }) {
    return (
        <select className={controlClass(error, cn('h-11 pe-9', className))} aria-invalid={!!error} {...props}>
            {children}
        </select>
    );
}

Select.propTypes = { ...controlProps, children: PropTypes.node };

export function Checkbox({ label, hint, ...props }) {
    return (
        <label className="flex cursor-pointer items-start gap-3">
            <input type="checkbox" className="border-line-strong accent-brand-600 mt-0.5 size-4.5 rounded" {...props} />
            <span>
                <span className="text-ink block text-sm font-medium">{label}</span>
                {hint && <span className="text-ink-subtle block text-xs">{hint}</span>}
            </span>
        </label>
    );
}

Checkbox.propTypes = { label: PropTypes.node.isRequired, hint: PropTypes.node };
