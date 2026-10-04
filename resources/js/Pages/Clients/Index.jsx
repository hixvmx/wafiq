import { router, usePage } from '@inertiajs/react';
import { Building2, Mail, Pencil, Phone, Plus, Trash2, User, Users } from 'lucide-react';
import PropTypes from 'prop-types';
import { useState } from 'react';
import { ClientDialog } from '@/Components/Clients/ClientDialog';
import { Button } from '@/Components/ui/Button';
import { Card, EmptyState } from '@/Components/ui/Card';
import { ConfirmDialog } from '@/Components/ui/ConfirmDialog';
import { ListToolbar } from '@/Components/ui/ListToolbar';
import { Pagination } from '@/Components/ui/Pagination';
import AppLayout from '@/Layouts/AppLayout';
import { useT } from '@/lib/i18n';

export default function ClientsIndex({ clients, filters, phoneCodes }) {
    const t = useT();
    const canManage = usePage().props.auth.can.manage_clients;
    // null = closed, {} = new client, {…client} = editing
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const filtered = Boolean(filters.q || filters.type);

    const addButton = canManage && (
        <Button icon={<Plus className="size-4" />} onClick={() => setEditing({})}>
            {t('clients.add')}
        </Button>
    );

    return (
        <AppLayout title={t('clients.title')}>
            <div className="mx-auto max-w-5xl space-y-5">
                <ListToolbar
                    url="/clients"
                    filters={filters}
                    placeholder={t('clients.search')}
                    types={[
                        { value: null, label: t('common.all') },
                        { value: 'company', label: t('clients.types.company') },
                        { value: 'person', label: t('clients.types.person') },
                    ]}
                    action={addButton}
                />

                {clients.data.length === 0 ? (
                    filtered ? (
                        <EmptyState title={t('clients.no_results')} />
                    ) : (
                        <EmptyState icon={<Users className="size-8" />} title={t('clients.empty')} text={t('clients.empty_text')} action={addButton} />
                    )
                ) : (
                    <Card>
                        <ul className="divide-line divide-y">
                            {clients.data.map((client) => (
                                <ClientRow
                                    key={client.id}
                                    client={client}
                                    canManage={canManage}
                                    onEdit={() => setEditing(client)}
                                    onDelete={() => setDeleting(client)}
                                />
                            ))}
                        </ul>
                    </Card>
                )}

                <Pagination meta={clients} />
            </div>

            <ClientDialog open={editing !== null} client={editing?.id ? editing : undefined} phoneCodes={phoneCodes} onClose={() => setEditing(null)} />

            <ConfirmDialog
                open={deleting !== null}
                title={t('clients.delete_title', { name: deleting?.name ?? '' })}
                message={t('clients.delete_message')}
                onConfirm={() => router.delete(`/clients/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) })}
                onClose={() => setDeleting(null)}
            />
        </AppLayout>
    );
}

ClientsIndex.propTypes = {
    clients: PropTypes.shape({ data: PropTypes.arrayOf(PropTypes.object).isRequired }).isRequired,
    filters: PropTypes.shape({ q: PropTypes.string, type: PropTypes.string }).isRequired,
    phoneCodes: PropTypes.objectOf(PropTypes.string).isRequired,
};

function ClientRow({ client, canManage, onEdit, onDelete }) {
    const t = useT();
    const Icon = client.type === 'company' ? Building2 : User;

    return (
        <li className="flex items-center gap-4 px-5 py-4">
            <span className="bg-brand-50 text-brand-700 flex size-10 shrink-0 items-center justify-center rounded-full">
                <Icon className="size-5" />
            </span>
            <div className="min-w-0 flex-1">
                <p className="text-ink truncate text-sm font-semibold">
                    {client.name}
                    {client.contact_name && <span className="text-ink-subtle font-normal"> · {client.contact_name}</span>}
                </p>
                <div className="text-ink-muted mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                    {client.phone && (
                        <span className="inline-flex items-center gap-1" dir="ltr">
                            <Phone className="size-3.5" />
                            {client.phone}
                        </span>
                    )}
                    {client.email && (
                        <span className="inline-flex items-center gap-1" dir="ltr">
                            <Mail className="size-3.5" />
                            {client.email}
                        </span>
                    )}
                </div>
            </div>
            {canManage && (
                <div className="flex shrink-0 gap-1">
                    <Button variant="ghost" size="sm" onClick={onEdit} aria-label={t('common.edit')} title={t('common.edit')}>
                        <Pencil className="size-4" />
                    </Button>
                    <Button variant="ghost" size="sm" onClick={onDelete} aria-label={t('common.delete')} title={t('common.delete')}>
                        <Trash2 className="size-4" />
                    </Button>
                </div>
            )}
        </li>
    );
}

ClientRow.propTypes = {
    client: PropTypes.shape({
        id: PropTypes.number.isRequired,
        type: PropTypes.string.isRequired,
        name: PropTypes.string.isRequired,
        contact_name: PropTypes.string,
        phone: PropTypes.string,
        email: PropTypes.string,
    }).isRequired,
    canManage: PropTypes.bool,
    onEdit: PropTypes.func.isRequired,
    onDelete: PropTypes.func.isRequired,
};
