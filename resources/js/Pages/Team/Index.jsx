import { router, useForm } from '@inertiajs/react';
import { Mail, Pencil, RotateCw, Trash2, UserPlus, X } from 'lucide-react';
import PropTypes from 'prop-types';
import { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Avatar, Badge, Card } from '@/Components/ui/Card';
import { ConfirmDialog } from '@/Components/ui/ConfirmDialog';
import { Dialog } from '@/Components/ui/Dialog';
import { Field, Select, TextInput } from '@/Components/ui/Field';
import AppLayout from '@/Layouts/AppLayout';
import { timeAgo } from '@/lib/format';
import { useT } from '@/lib/i18n';

const memberShape = PropTypes.shape({
    id: PropTypes.number.isRequired,
    name: PropTypes.string.isRequired,
    email: PropTypes.string.isRequired,
    job_title: PropTypes.string,
    phone: PropTypes.string,
    avatar: PropTypes.string,
    role: PropTypes.string.isRequired,
    last_login_at: PropTypes.string,
    is_me: PropTypes.bool.isRequired,
});

export default function TeamIndex({ members, invitations, assignableRoles }) {
    const t = useT();
    const [inviting, setInviting] = useState(false);
    const [editing, setEditing] = useState(null);
    // { type: 'member' | 'invitation', item } waiting for confirmation
    const [confirming, setConfirming] = useState(null);
    const [processing, setProcessing] = useState(false);

    const confirm = () => {
        const { type, item } = confirming;
        const url = type === 'member' ? `/team/members/${item.id}` : `/team/invitations/${item.id}`;
        router.delete(url, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setConfirming(null);
            },
        });
    };

    return (
        <AppLayout title={t('team.title')}>
            <div className="mx-auto max-w-4xl space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-ink-muted text-sm">{t('team.subtitle')}</p>
                    <Button icon={<UserPlus className="size-4" />} onClick={() => setInviting(true)}>
                        {t('team.add_new')}
                    </Button>
                </div>

                <Card>
                    <h2 className="border-line text-ink border-b px-5 py-4 text-base font-bold">
                        {t('team.members')} <span className="text-ink-subtle">({members.length})</span>
                    </h2>
                    <ul className="divide-line divide-y">
                        {members.map((member) => (
                            <MemberRow key={member.id} member={member} onEdit={() => setEditing(member)} />
                        ))}
                    </ul>
                </Card>

                {invitations.length > 0 && (
                    <Card>
                        <h2 className="border-line text-ink border-b px-5 py-4 text-base font-bold">{t('team.pending')}</h2>
                        <ul className="divide-line divide-y">
                            {invitations.map((invitation) => (
                                <InvitationRow
                                    key={invitation.id}
                                    invitation={invitation}
                                    onRevoke={() => setConfirming({ type: 'invitation', item: invitation })}
                                />
                            ))}
                        </ul>
                    </Card>
                )}
            </div>

            <InviteDialog open={inviting} assignableRoles={assignableRoles} onClose={() => setInviting(false)} />

            <EditMemberDialog
                key={editing?.id ?? 'none'}
                member={editing}
                assignableRoles={assignableRoles}
                onClose={() => setEditing(null)}
                onRemove={(member) => {
                    setEditing(null);
                    setConfirming({ type: 'member', item: member });
                }}
            />

            <ConfirmDialog
                open={confirming !== null}
                title={
                    confirming?.type === 'member'
                        ? t('team.remove_title', { name: confirming.item.name })
                        : t('team.revoke_title', { email: confirming?.item.email ?? '' })
                }
                message={confirming?.type === 'member' ? t('team.remove_message') : t('team.revoke_message')}
                confirmLabel={confirming?.type === 'member' ? t('team.remove') : t('team.revoke')}
                processing={processing}
                onConfirm={confirm}
                onClose={() => setConfirming(null)}
            />
        </AppLayout>
    );
}

TeamIndex.propTypes = {
    members: PropTypes.arrayOf(memberShape).isRequired,
    invitations: PropTypes.arrayOf(PropTypes.object).isRequired,
    assignableRoles: PropTypes.arrayOf(PropTypes.string).isRequired,
};

function RoleSelect({ value, onChange, error, assignableRoles }) {
    const t = useT();

    return (
        <Field label={t('team.role')} error={error} hint={t(`team.role_hints.${value}`)}>
            {(id, describedBy) => (
                <Select id={id} value={value} onChange={(e) => onChange(e.target.value)} error={error} aria-describedby={describedBy}>
                    {assignableRoles.map((role) => (
                        <option key={role} value={role}>
                            {t(`roles.${role}`)}
                        </option>
                    ))}
                </Select>
            )}
        </Field>
    );
}

RoleSelect.propTypes = {
    value: PropTypes.string.isRequired,
    onChange: PropTypes.func.isRequired,
    error: PropTypes.string,
    assignableRoles: PropTypes.arrayOf(PropTypes.string).isRequired,
};

function InviteDialog({ open, assignableRoles, onClose }) {
    const t = useT();
    const form = useForm({ email: '', role: 'sales' });

    const close = () => {
        form.reset();
        form.clearErrors();
        onClose();
    };

    const submit = (e) => {
        e.preventDefault();
        form.post('/team/invitations', { preserveScroll: true, onSuccess: close });
    };

    return (
        <Dialog
            open={open}
            title={t('team.invite_title')}
            onClose={close}
            footer={
                <>
                    <Button variant="secondary" onClick={close}>
                        {t('common.cancel')}
                    </Button>
                    <Button type="submit" form="invite-form" icon={<Mail className="size-4" />} disabled={form.processing}>
                        {form.processing ? t('common.processing') : t('team.invite_send')}
                    </Button>
                </>
            }
        >
            <form id="invite-form" onSubmit={submit} className="space-y-4">
                <Field label={t('team.email')} error={form.errors.email}>
                    {(id, describedBy) => (
                        <TextInput
                            id={id}
                            type="email"
                            dir="ltr"
                            required
                            autoFocus
                            value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)}
                            error={form.errors.email}
                            aria-describedby={describedBy}
                        />
                    )}
                </Field>
                <RoleSelect value={form.data.role} onChange={(role) => form.setData('role', role)} error={form.errors.role} assignableRoles={assignableRoles} />
            </form>
        </Dialog>
    );
}

InviteDialog.propTypes = {
    open: PropTypes.bool.isRequired,
    assignableRoles: PropTypes.arrayOf(PropTypes.string).isRequired,
    onClose: PropTypes.func.isRequired,
};

function EditMemberDialog({ member, assignableRoles, onClose, onRemove }) {
    const t = useT();
    // Remounted for each member (TeamIndex gives it a key), so this always starts from their role.
    const form = useForm({ role: member?.role ?? 'sales' });

    const submit = (e) => {
        e.preventDefault();
        form.put(`/team/members/${member.id}`, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Dialog
            open={member !== null}
            title={member ? t('team.edit_title', { name: member.name }) : ''}
            onClose={onClose}
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>
                        {t('common.cancel')}
                    </Button>
                    <Button type="submit" form="member-form" disabled={form.processing || form.data.role === member?.role}>
                        {form.processing ? t('common.saving') : t('team.save_role')}
                    </Button>
                </>
            }
        >
            {member && (
                <div className="space-y-5">
                    <div className="flex items-center gap-3">
                        <Avatar src={member.avatar} name={member.name} size={48} />
                        <div className="min-w-0">
                            <p className="text-ink truncate font-semibold">{member.name}</p>
                            {member.job_title && <p className="text-ink-muted truncate text-sm">{member.job_title}</p>}
                            <p className="text-ink-subtle truncate text-xs">
                                <span dir="ltr">{member.email}</span>
                                {member.phone && (
                                    <>
                                        {' · '}
                                        <span dir="ltr">{member.phone}</span>
                                    </>
                                )}
                            </p>
                        </div>
                    </div>

                    <form id="member-form" onSubmit={submit}>
                        <RoleSelect
                            value={form.data.role}
                            onChange={(role) => form.setData('role', role)}
                            error={form.errors.role}
                            assignableRoles={assignableRoles}
                        />
                    </form>

                    <div className="border-danger/30 bg-danger/5 flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4">
                        <div>
                            <p className="text-ink text-sm font-semibold">{t('team.danger_zone')}</p>
                            <p className="text-ink-muted text-xs">{t('team.danger_hint')}</p>
                        </div>
                        <Button variant="danger" size="sm" icon={<Trash2 className="size-4" />} onClick={() => onRemove(member)}>
                            {t('team.remove')}
                        </Button>
                    </div>
                </div>
            )}
        </Dialog>
    );
}

EditMemberDialog.propTypes = {
    member: memberShape,
    assignableRoles: PropTypes.arrayOf(PropTypes.string).isRequired,
    onClose: PropTypes.func.isRequired,
    onRemove: PropTypes.func.isRequired,
};

function MemberRow({ member, onEdit }) {
    const t = useT();
    const editable = member.role !== 'owner' && !member.is_me;

    return (
        <li className="flex flex-wrap items-center gap-3 px-5 py-4">
            <Avatar src={member.avatar} name={member.name} />
            <div className="min-w-0 flex-1">
                <p className="text-ink flex items-center gap-2 truncate text-sm font-semibold">
                    {member.name}
                    {member.is_me && <Badge tone="brand">{t('team.you')}</Badge>}
                </p>
                <p className="text-ink-subtle truncate text-xs">
                    {member.job_title && `${member.job_title} · `}
                    <span dir="ltr">{member.email}</span>
                    {' · '}
                    {member.last_login_at ? t('team.last_login', { time: timeAgo(member.last_login_at) }) : t('team.never_logged_in')}
                </p>
            </div>
            <Badge tone={member.role === 'owner' ? 'brand' : 'neutral'}>{t(`roles.${member.role}`)}</Badge>
            {editable ? (
                <Button variant="secondary" size="sm" icon={<Pencil className="size-4" />} onClick={onEdit}>
                    {t('team.edit')}
                </Button>
            ) : (
                // Keeps the role badges aligned with the editable rows.
                <span className="hidden w-22 sm:block" aria-hidden />
            )}
        </li>
    );
}

MemberRow.propTypes = { member: memberShape.isRequired, onEdit: PropTypes.func.isRequired };

function InvitationRow({ invitation, onRevoke }) {
    const t = useT();
    const resend = () => router.post(`/team/invitations/${invitation.id}/resend`, {}, { preserveScroll: true });

    return (
        <li className="flex flex-wrap items-center gap-3 px-5 py-4">
            <div className="min-w-0 flex-1">
                <p className="text-ink truncate text-sm font-semibold" dir="ltr">
                    {invitation.email}
                </p>
                <p className="text-ink-subtle mt-0.5 flex items-center gap-2 text-xs">
                    {t(`roles.${invitation.role}`)}
                    {' · '}
                    {invitation.expired ? <Badge tone="warning">{t('team.expired')}</Badge> : t('team.expires', { time: timeAgo(invitation.expires_at) })}
                </p>
            </div>
            <Button variant="secondary" size="sm" icon={<RotateCw className="size-4" />} onClick={resend}>
                {t('team.resend')}
            </Button>
            <Button variant="ghost" size="sm" onClick={onRevoke} aria-label={t('team.revoke')} title={t('team.revoke')}>
                <X className="size-4" />
            </Button>
        </li>
    );
}

InvitationRow.propTypes = {
    invitation: PropTypes.shape({
        id: PropTypes.number.isRequired,
        email: PropTypes.string.isRequired,
        role: PropTypes.string.isRequired,
        expires_at: PropTypes.string.isRequired,
        expired: PropTypes.bool.isRequired,
    }).isRequired,
    onRevoke: PropTypes.func.isRequired,
};
