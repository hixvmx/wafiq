import { router, useForm } from '@inertiajs/react';
import { Mail, RotateCw, Trash2, UserPlus, X } from 'lucide-react';
import PropTypes from 'prop-types';
import { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Avatar, Badge, Card } from '@/Components/ui/Card';
import { ConfirmDialog } from '@/Components/ui/ConfirmDialog';
import { Field, Select, TextInput } from '@/Components/ui/Field';
import AppLayout from '@/Layouts/AppLayout';
import { timeAgo } from '@/lib/format';
import { useT } from '@/lib/i18n';

export default function TeamIndex({ members, invitations, assignableRoles }) {
    const t = useT();
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
                <p className="text-ink-muted text-sm">{t('team.subtitle')}</p>

                <InviteForm assignableRoles={assignableRoles} />

                <Card>
                    <h2 className="border-line text-ink border-b px-5 py-4 text-base font-bold">
                        {t('team.members')} <span className="text-ink-subtle">({members.length})</span>
                    </h2>
                    <ul className="divide-line divide-y">
                        {members.map((member) => (
                            <MemberRow
                                key={member.id}
                                member={member}
                                assignableRoles={assignableRoles}
                                onRemove={() => setConfirming({ type: 'member', item: member })}
                            />
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
    members: PropTypes.arrayOf(PropTypes.object).isRequired,
    invitations: PropTypes.arrayOf(PropTypes.object).isRequired,
    assignableRoles: PropTypes.arrayOf(PropTypes.string).isRequired,
};

function InviteForm({ assignableRoles }) {
    const t = useT();
    const form = useForm({ email: '', role: 'sales' });

    const submit = (e) => {
        e.preventDefault();
        form.post('/team/invitations', { preserveScroll: true, onSuccess: () => form.reset('email') });
    };

    return (
        <Card className="p-5">
            <h2 className="text-ink mb-4 flex items-center gap-2 text-base font-bold">
                <UserPlus className="text-brand-600 size-5" />
                {t('team.invite_title')}
            </h2>
            <form onSubmit={submit} className="grid gap-4 sm:grid-cols-[1fr_12rem_auto] sm:items-start">
                <Field label={t('team.email')} error={form.errors.email}>
                    {(id, describedBy) => (
                        <TextInput
                            id={id}
                            type="email"
                            required
                            value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)}
                            error={form.errors.email}
                            aria-describedby={describedBy}
                        />
                    )}
                </Field>
                <Field label={t('team.role')} error={form.errors.role} hint={t(`team.role_hints.${form.data.role}`)}>
                    {(id, describedBy) => (
                        <Select
                            id={id}
                            value={form.data.role}
                            onChange={(e) => form.setData('role', e.target.value)}
                            error={form.errors.role}
                            aria-describedby={describedBy}
                        >
                            {assignableRoles.map((role) => (
                                <option key={role} value={role}>
                                    {t(`roles.${role}`)}
                                </option>
                            ))}
                        </Select>
                    )}
                </Field>
                <Button type="submit" className="sm:mt-6.5" icon={<Mail className="size-4" />} disabled={form.processing}>
                    {form.processing ? t('common.processing') : t('team.invite_send')}
                </Button>
            </form>
        </Card>
    );
}

InviteForm.propTypes = { assignableRoles: PropTypes.arrayOf(PropTypes.string).isRequired };

function MemberRow({ member, assignableRoles, onRemove }) {
    const t = useT();
    const editable = member.role !== 'owner' && !member.is_me;

    const changeRole = (role) => router.put(`/team/members/${member.id}`, { role }, { preserveScroll: true });

    return (
        <li className="flex flex-wrap items-center gap-3 px-5 py-4">
            <Avatar src={member.avatar} name={member.name} />
            <div className="min-w-0 flex-1">
                <p className="text-ink flex items-center gap-2 truncate text-sm font-semibold">
                    {member.name}
                    {member.is_me && <Badge tone="brand">{t('team.you')}</Badge>}
                </p>
                <p className="text-ink-subtle truncate text-xs">
                    <span dir="ltr">{member.email}</span>
                    {' · '}
                    {member.last_login_at ? t('team.last_login', { time: timeAgo(member.last_login_at) }) : t('team.never_logged_in')}
                </p>
            </div>
            {editable ? (
                <>
                    <Select value={member.role} onChange={(e) => changeRole(e.target.value)} className="h-9 w-36" aria-label={t('team.role')}>
                        {assignableRoles.map((role) => (
                            <option key={role} value={role}>
                                {t(`roles.${role}`)}
                            </option>
                        ))}
                    </Select>
                    <Button variant="ghost" size="sm" onClick={onRemove} aria-label={t('team.remove')} title={t('team.remove')}>
                        <Trash2 className="size-4" />
                    </Button>
                </>
            ) : (
                <Badge tone={member.role === 'owner' ? 'brand' : 'neutral'}>{t(`roles.${member.role}`)}</Badge>
            )}
        </li>
    );
}

MemberRow.propTypes = {
    member: PropTypes.shape({
        id: PropTypes.number.isRequired,
        name: PropTypes.string.isRequired,
        email: PropTypes.string.isRequired,
        avatar: PropTypes.string,
        role: PropTypes.string.isRequired,
        last_login_at: PropTypes.string,
        is_me: PropTypes.bool.isRequired,
    }).isRequired,
    assignableRoles: PropTypes.arrayOf(PropTypes.string).isRequired,
    onRemove: PropTypes.func.isRequired,
};

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
