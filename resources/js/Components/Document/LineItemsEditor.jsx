import { ArrowDown, ArrowUp, Plus, Text, Trash2 } from 'lucide-react';
import PropTypes from 'prop-types';
import { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Combobox } from '@/Components/ui/Combobox';
import { cn } from '@/lib/format';
import { useT } from '@/lib/i18n';
import { formatMinor } from '@/lib/totals';

let nextKey = 1;

/** A blank line; `key` only lives in the browser (React list key). */
export function newLine(defaultTaxId) {
    return {
        key: `new-${nextKey++}`,
        item_id: null,
        name: '',
        description: '',
        qty: '1',
        unit: '',
        unit_price: '',
        discount: '',
        tax_rate_id: defaultTaxId ?? null,
    };
}

/** Gives lines loaded from the server a browser key. */
export function withKeys(lines) {
    return lines.map((line) => ({ ...line, key: `line-${nextKey++}` }));
}

const cell = 'block h-10 w-full rounded-lg border bg-card px-2.5 text-sm text-ink focus:border-brand-500 focus:ring-3 focus:ring-brand-100 focus:outline-none';

/**
 * The lines table: pick from the catalog or type freely, then qty, unit, price, discount %, tax.
 * `amounts` are the live line amounts (BigInt minor units) from calculateTotals().
 */
export function LineItemsEditor({ lines, onChange, taxRates, units, currency, decimals, amounts, errors }) {
    const t = useT();
    const defaultTaxId = taxRates.find((rate) => rate.is_default)?.id ?? null;

    const update = (index, changes) => onChange(lines.map((line, i) => (i === index ? { ...line, ...changes } : line)));
    const remove = (index) => onChange(lines.filter((_, i) => i !== index));
    const move = (index, step) => {
        const target = index + step;
        if (target < 0 || target >= lines.length) return;
        const copy = [...lines];
        [copy[index], copy[target]] = [copy[target], copy[index]];
        onChange(copy);
    };

    /** Catalog item chosen: fill the line (the price only when the currencies match). */
    const pick = (index, item) =>
        update(index, {
            item_id: item.id,
            name: item.name,
            description: item.description ?? '',
            unit: item.unit ?? '',
            ...(item.currency === currency ? { unit_price: item.price } : {}),
            tax_rate_id: item.tax_rate_id ?? defaultTaxId,
        });

    const error = (index, field) => errors[`lines.${index}.${field}`];

    return (
        <div className="space-y-3">
            {/* Column titles (desktop) */}
            <div className="text-ink-subtle hidden grid-cols-[2rem_minmax(0,1fr)_5.5rem_6rem_7.5rem_5rem_9rem_8rem_2.5rem] gap-2 px-1 text-xs font-medium lg:grid">
                <span />
                <span>{t('documents.form.item')}</span>
                <span>{t('documents.form.qty')}</span>
                <span>{t('documents.form.unit')}</span>
                <span>{t('documents.form.unit_price')}</span>
                <span>{t('documents.form.discount')}</span>
                <span>{t('documents.form.tax')}</span>
                <span className="text-end">{t('documents.form.amount')}</span>
                <span />
            </div>

            {lines.map((line, index) => (
                <LineRow
                    key={line.key}
                    line={line}
                    index={index}
                    count={lines.length}
                    taxRates={taxRates}
                    units={units}
                    currency={currency}
                    amount={amounts[index] !== undefined ? formatMinor(amounts[index], decimals) : ''}
                    error={(field) => error(index, field)}
                    onUpdate={(changes) => update(index, changes)}
                    onPick={(item) => pick(index, item)}
                    onRemove={() => remove(index)}
                    onMove={(step) => move(index, step)}
                />
            ))}

            <datalist id="line-units">
                {units.map((unit) => (
                    <option key={unit} value={unit} />
                ))}
            </datalist>

            <Button variant="secondary" size="sm" icon={<Plus className="size-4" />} onClick={() => onChange([...lines, newLine(defaultTaxId)])}>
                {t('documents.form.add_line')}
            </Button>
        </div>
    );
}

const taxShape = PropTypes.shape({ id: PropTypes.number, name: PropTypes.string, rate: PropTypes.number, is_default: PropTypes.bool });

LineItemsEditor.propTypes = {
    lines: PropTypes.arrayOf(PropTypes.object).isRequired,
    onChange: PropTypes.func.isRequired,
    taxRates: PropTypes.arrayOf(taxShape).isRequired,
    units: PropTypes.arrayOf(PropTypes.string).isRequired,
    currency: PropTypes.string.isRequired,
    decimals: PropTypes.number.isRequired,
    amounts: PropTypes.array.isRequired,
    errors: PropTypes.object.isRequired,
};

function LineRow({ line, index, count, taxRates, currency, amount, error, onUpdate, onPick, onRemove, onMove }) {
    const t = useT();
    const [showDescription, setShowDescription] = useState(Boolean(line.description));
    const field = (name, extra = {}) => ({
        value: line[name] ?? '',
        onChange: (e) => onUpdate({ [name]: e.target.value }),
        'aria-invalid': !!error(name),
        className: cn(cell, error(name) ? 'border-danger' : 'border-line-strong', extra.className),
    });

    return (
        <div className="border-line rounded-xl border p-3 lg:border-0 lg:p-0">
            <div className="grid grid-cols-2 gap-2 lg:grid-cols-[2rem_minmax(0,1fr)_5.5rem_6rem_7.5rem_5rem_9rem_8rem_2.5rem] lg:items-start">
                {/* Move up / down */}
                <div className="order-last col-span-2 flex gap-1 lg:order-none lg:col-span-1 lg:flex-col lg:gap-0 lg:pt-0.5">
                    <button
                        type="button"
                        onClick={() => onMove(-1)}
                        disabled={index === 0}
                        className="text-ink-subtle hover:text-ink rounded p-1 disabled:opacity-30"
                        aria-label={t('documents.form.move_up')}
                    >
                        <ArrowUp className="size-3.5" />
                    </button>
                    <button
                        type="button"
                        onClick={() => onMove(1)}
                        disabled={index === count - 1}
                        className="text-ink-subtle hover:text-ink rounded p-1 disabled:opacity-30"
                        aria-label={t('documents.form.move_down')}
                    >
                        <ArrowDown className="size-3.5" />
                    </button>
                </div>

                <div className="col-span-2 space-y-1.5 lg:col-span-1">
                    <div className="flex gap-1.5">
                        <div className="min-w-0 flex-1">
                            <Combobox
                                value={line.name}
                                onChange={(name) => onUpdate({ name, item_id: null })}
                                onSelect={onPick}
                                searchUrl={(q) => `/items/search?q=${encodeURIComponent(q)}`}
                                placeholder={t('documents.form.search_item')}
                                inputClassName="h-10 rounded-lg px-2.5"
                                error={error('name')}
                                aria-label={t('documents.form.item')}
                                renderOption={(item) => (
                                    <div className="flex items-center justify-between gap-3">
                                        <span className="text-ink truncate">{item.name}</span>
                                        <span className={cn('shrink-0 text-xs', item.currency === currency ? 'text-ink-subtle' : 'text-warning')} dir="ltr">
                                            {item.price} {item.currency}
                                        </span>
                                    </div>
                                )}
                            />
                        </div>
                        <button
                            type="button"
                            onClick={() => setShowDescription((v) => !v)}
                            className={cn('text-ink-subtle hover:bg-surface hover:text-ink rounded-lg px-2', showDescription && 'text-brand-700')}
                            aria-label={t('documents.form.description')}
                            title={t('documents.form.description')}
                        >
                            <Text className="size-4" />
                        </button>
                    </div>
                    {showDescription && (
                        <textarea
                            value={line.description ?? ''}
                            onChange={(e) => onUpdate({ description: e.target.value })}
                            placeholder={t('documents.form.description')}
                            rows={2}
                            className="border-line-strong bg-card focus:border-brand-500 focus:ring-brand-100 block w-full rounded-lg border px-2.5 py-2 text-sm focus:ring-3 focus:outline-none"
                        />
                    )}
                </div>

                <Labeled label={t('documents.form.qty')}>
                    <input {...field('qty')} inputMode="decimal" dir="ltr" aria-label={t('documents.form.qty')} />
                </Labeled>
                <Labeled label={t('documents.form.unit')}>
                    <input {...field('unit')} list="line-units" aria-label={t('documents.form.unit')} />
                </Labeled>
                <Labeled label={t('documents.form.unit_price')}>
                    <input {...field('unit_price')} inputMode="decimal" dir="ltr" placeholder="0.00" aria-label={t('documents.form.unit_price')} />
                </Labeled>
                <Labeled label={t('documents.form.discount')}>
                    <input {...field('discount')} inputMode="decimal" dir="ltr" placeholder="0" aria-label={t('documents.form.discount')} />
                </Labeled>
                <Labeled label={t('documents.form.tax')} className="col-span-2 lg:col-span-1">
                    <select
                        value={line.tax_rate_id ?? ''}
                        onChange={(e) => onUpdate({ tax_rate_id: e.target.value ? Number(e.target.value) : null })}
                        className={cn(cell, 'border-line-strong pe-7')}
                        aria-label={t('documents.form.tax')}
                    >
                        <option value="">{t('documents.form.no_tax')}</option>
                        {taxRates.map((rate) => (
                            <option key={rate.id} value={rate.id}>
                                {rate.name} {rate.rate}%
                            </option>
                        ))}
                    </select>
                </Labeled>

                <p className="text-ink col-span-2 flex h-10 items-center justify-end text-sm font-semibold lg:col-span-1" dir="ltr">
                    {amount}
                </p>

                <div className="order-last flex justify-end lg:order-none">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={onRemove}
                        disabled={count === 1}
                        aria-label={t('documents.form.remove_line')}
                        title={t('documents.form.remove_line')}
                    >
                        <Trash2 className="size-4" />
                    </Button>
                </div>
            </div>

            {['name', 'qty', 'unit_price', 'discount'].some((name) => error(name)) && (
                <p className="text-danger mt-1.5 text-xs font-medium lg:ps-10" role="alert">
                    {['name', 'qty', 'unit_price', 'discount'].map((name) => error(name)).filter(Boolean)[0]}
                </p>
            )}
        </div>
    );
}

LineRow.propTypes = {
    line: PropTypes.object.isRequired,
    index: PropTypes.number.isRequired,
    count: PropTypes.number.isRequired,
    taxRates: PropTypes.arrayOf(taxShape).isRequired,
    currency: PropTypes.string.isRequired,
    amount: PropTypes.string.isRequired,
    error: PropTypes.func.isRequired,
    onUpdate: PropTypes.func.isRequired,
    onPick: PropTypes.func.isRequired,
    onRemove: PropTypes.func.isRequired,
    onMove: PropTypes.func.isRequired,
};

/** Shows a small label above the input on phones; on desktop the column titles do that job. */
function Labeled({ label, className, children }) {
    return (
        <label className={cn('block', className)}>
            <span className="text-ink-subtle mb-1 block text-xs lg:hidden">{label}</span>
            {children}
        </label>
    );
}

Labeled.propTypes = { label: PropTypes.string.isRequired, className: PropTypes.string, children: PropTypes.node };
