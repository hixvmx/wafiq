import { Building2, Phone, Plus, User } from 'lucide-react';
import PropTypes from 'prop-types';
import { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Combobox } from '@/Components/ui/Combobox';
import { useT } from '@/lib/i18n';

/**
 * Chooses the document's client: search existing clients, or add one on the spot.
 * The "new client" dialog is opened by the parent (it must live outside the editor's <form>).
 */
export function ClientPicker({ client, onChange, onAddNew, canAdd, error }) {
    const t = useT();
    const [query, setQuery] = useState('');

    if (client) {
        const Icon = client.type === 'person' ? User : Building2;
        return (
            <div className="flex items-center gap-3 rounded-xl border border-line-strong bg-surface px-4 py-3">
                <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-700">
                    <Icon className="size-5" />
                </span>
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-semibold text-ink">{client.name}</p>
                    <p className="flex flex-wrap gap-x-3 text-xs text-ink-muted">
                        {client.contact_name && <span>{client.contact_name}</span>}
                        {client.phone && (
                            <span className="inline-flex items-center gap-1" dir="ltr">
                                <Phone className="size-3" />
                                {client.phone}
                            </span>
                        )}
                    </p>
                </div>
                <Button variant="ghost" size="sm" onClick={() => onChange(null)}>
                    {t('documents.form.change_client')}
                </Button>
            </div>
        );
    }

    return (
        <div>
            <Combobox
                value={query}
                onChange={setQuery}
                onSelect={(selected) => {
                    onChange(selected);
                    setQuery('');
                }}
                searchUrl={(q) => `/clients/search?q=${encodeURIComponent(q)}`}
                placeholder={t('documents.form.search_client')}
                error={error}
                aria-label={t('documents.client')}
                renderOption={(option) => (
                    <div className="flex items-center justify-between gap-3">
                        <span className="truncate font-medium text-ink">{option.name}</span>
                        {option.phone && (
                            <span className="shrink-0 text-xs text-ink-subtle" dir="ltr">
                                {option.phone}
                            </span>
                        )}
                    </div>
                )}
                footer={
                    canAdd
                        ? (close) => (
                              <button
                                  type="button"
                                  onClick={() => {
                                      close();
                                      onAddNew(query);
                                  }}
                                  className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-brand-700 hover:bg-brand-50"
                              >
                                  <Plus className="size-4" />
                                  {t('documents.form.new_client')}
                              </button>
                          )
                        : undefined
                }
            />
            {error && (
                <p className="mt-1.5 text-xs font-medium text-danger" role="alert">
                    {error}
                </p>
            )}
        </div>
    );
}

ClientPicker.propTypes = {
    client: PropTypes.shape({ id: PropTypes.number, type: PropTypes.string, name: PropTypes.string, contact_name: PropTypes.string, phone: PropTypes.string }),
    onChange: PropTypes.func.isRequired,
    onAddNew: PropTypes.func.isRequired,
    canAdd: PropTypes.bool,
    error: PropTypes.string,
};
