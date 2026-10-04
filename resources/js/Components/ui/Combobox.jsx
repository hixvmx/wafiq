import PropTypes from 'prop-types';
import { useEffect, useId, useRef, useState } from 'react';
import { cn } from '@/lib/format';

/**
 * A text input with live suggestions from a JSON endpoint ({ data: [...] }).
 * Free text stays allowed: choosing a suggestion calls onSelect, typing calls onChange.
 * Keyboard: ↓ ↑ to move, Enter to choose, Esc to close.
 */
export function Combobox({ value, onChange, onSelect, searchUrl, renderOption, placeholder, footer, inputClassName, error, autoFocus, ...inputProps }) {
    const [open, setOpen] = useState(false);
    const [options, setOptions] = useState([]);
    const [active, setActive] = useState(-1);
    const listId = useId();
    const box = useRef(null);
    const dirty = useRef(false);

    // Fetch suggestions as the user types (debounced; stale answers are ignored).
    useEffect(() => {
        if (!open || !dirty.current) return;
        const controller = new AbortController();
        const timer = setTimeout(() => {
            fetch(searchUrl(value ?? ''), { headers: { Accept: 'application/json' }, signal: controller.signal })
                .then((response) => (response.ok ? response.json() : { data: [] }))
                .then((json) => {
                    setOptions(json.data ?? []);
                    setActive(-1);
                })
                .catch(() => {});
        }, 200);
        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [value, open, searchUrl]);

    // Close when clicking elsewhere.
    useEffect(() => {
        const close = (e) => box.current && !box.current.contains(e.target) && setOpen(false);
        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
    }, []);

    const choose = (option) => {
        onSelect(option);
        setOpen(false);
        dirty.current = false;
    };

    const onKeyDown = (e) => {
        if (!open && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
            dirty.current = true;
            setOpen(true);
            return;
        }
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActive((i) => Math.min(i + 1, options.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActive((i) => Math.max(i - 1, 0));
        } else if (e.key === 'Enter' && open && active >= 0) {
            e.preventDefault();
            choose(options[active]);
        } else if (e.key === 'Escape') {
            setOpen(false);
        }
    };

    const showList = open && (options.length > 0 || footer);

    return (
        <div ref={box} className="relative">
            <input
                {...inputProps}
                type="text"
                role="combobox"
                aria-expanded={showList}
                aria-controls={listId}
                aria-autocomplete="list"
                aria-invalid={!!error}
                autoComplete="off"
                autoFocus={autoFocus}
                value={value ?? ''}
                placeholder={placeholder}
                onChange={(e) => {
                    dirty.current = true;
                    onChange(e.target.value);
                    setOpen(true);
                }}
                onFocus={() => {
                    dirty.current = true;
                    setOpen(true);
                }}
                onKeyDown={onKeyDown}
                className={cn(
                    'bg-card text-ink placeholder:text-ink-subtle focus:border-brand-500 focus:ring-brand-100 block h-11 w-full rounded-xl border px-3.5 text-sm focus:ring-3 focus:outline-none',
                    error ? 'border-danger' : 'border-line-strong',
                    inputClassName,
                )}
            />
            {showList && (
                <div className="border-line bg-card absolute inset-x-0 top-full z-30 mt-1 overflow-hidden rounded-xl border shadow-lg">
                    <ul id={listId} role="listbox" className="max-h-72 overflow-y-auto py-1">
                        {options.map((option, i) => (
                            <li
                                key={option.id}
                                role="option"
                                aria-selected={i === active}
                                onMouseDown={(e) => e.preventDefault()}
                                onClick={() => choose(option)}
                                onMouseEnter={() => setActive(i)}
                                className={cn('cursor-pointer px-3.5 py-2 text-sm', i === active ? 'bg-brand-50' : 'hover:bg-surface')}
                            >
                                {renderOption(option)}
                            </li>
                        ))}
                    </ul>
                    {footer && (
                        <div className="border-line border-t p-1" onMouseDown={(e) => e.preventDefault()}>
                            {footer(() => setOpen(false))}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

Combobox.propTypes = {
    value: PropTypes.string,
    onChange: PropTypes.func.isRequired,
    onSelect: PropTypes.func.isRequired,
    /** (query) => URL returning { data: [...] } */
    searchUrl: PropTypes.func.isRequired,
    renderOption: PropTypes.func.isRequired,
    placeholder: PropTypes.string,
    /** (close) => node shown under the options, e.g. an "add new" button */
    footer: PropTypes.func,
    inputClassName: PropTypes.string,
    error: PropTypes.string,
    autoFocus: PropTypes.bool,
};
