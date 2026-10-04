import { router, useForm } from '@inertiajs/react';
import { CheckCircle2, Clock, Copy, Eye, FilePen, FilePlus2, Pencil, Send, Trash2, XCircle } from 'lucide-react';
import PropTypes from 'prop-types';
import { useMemo, useRef, useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Avatar, Card } from '@/Components/ui/Card';
import { cn, formatDate, formatDateTime, timeAgo } from '@/lib/format';
import { useT } from '@/lib/i18n';

const ICONS = {
    created: FilePlus2,
    updated: Pencil,
    duplicated: Copy,
    revised: FilePen,
    converted: FilePlus2,
    created_from_quote: FilePlus2,
    sent: Send,
    extended: Clock,
    viewed: Eye,
    approved: CheckCircle2,
    rejected: XCircle,
    expired: Clock,
};

const TONES = { viewed: 'text-violet-600', approved: 'text-success', rejected: 'text-danger', expired: 'text-warning' };

/** Long histories start collapsed to the latest entries. */
const COLLAPSED = 12;

/** Team actions, client events and internal comments in one timeline, with a comment box. */
export function ActivityFeed({ feed, members, commentUrl }) {
    const t = useT();
    const [showAll, setShowAll] = useState(false);
    const visible = showAll ? feed : feed.slice(-COLLAPSED);

    return (
        <Card className="p-5">
            <h2 className="text-ink mb-4 text-base font-bold">{t('activity.title')}</h2>

            {feed.length > COLLAPSED && !showAll && (
                <button type="button" onClick={() => setShowAll(true)} className="text-brand-700 mb-3 text-sm font-medium hover:underline">
                    {t('activity.show_all', { count: feed.length })}
                </button>
            )}

            {feed.length === 0 ? (
                <p className="text-ink-subtle text-sm">{t('activity.empty')}</p>
            ) : (
                <ol className="space-y-3">
                    {visible.map((entry) =>
                        entry.kind === 'comment' ? (
                            <CommentItem key={entry.key} comment={entry} members={members} />
                        ) : (
                            <ActivityItem key={entry.key} activity={entry} />
                        ),
                    )}
                </ol>
            )}

            <CommentBox url={commentUrl} members={members} />
        </Card>
    );
}

ActivityFeed.propTypes = {
    feed: PropTypes.arrayOf(PropTypes.object).isRequired,
    members: PropTypes.arrayOf(PropTypes.shape({ id: PropTypes.number, name: PropTypes.string })).isRequired,
    commentUrl: PropTypes.string.isRequired,
};

function ActivityItem({ activity }) {
    const t = useT();
    const Icon = ICONS[activity.type] ?? Clock;
    const data = activity.data ?? {};

    const text = t(`activity.types.${activity.type}`, {
        ...data,
        user: activity.user ?? t('activity.system'),
        channel: data.channel ? t(`share.channels.${data.channel}`) : '',
        device: data.device ? t(`activity.devices.${data.device}`) : '',
        date: data.valid_until ? formatDate(data.valid_until) : '',
    });
    const detail =
        activity.type === 'rejected' && data.reason
            ? t('activity.reason', { reason: data.reason })
            : activity.type === 'sent' && data.recipient
              ? t('activity.to', { recipient: data.recipient })
              : null;

    return (
        <li className="flex gap-3 text-sm">
            <span className={cn('mt-0.5 shrink-0', TONES[activity.type] ?? 'text-ink-subtle')}>
                <Icon className="size-4" />
            </span>
            <p className="text-ink-muted min-w-0 flex-1">
                <span className={activity.client_event ? 'text-ink font-medium' : ''}>{text}</span>
                {detail && <span className="text-ink-subtle"> · {detail}</span>}
                <span className="text-ink-subtle text-xs" title={formatDateTime(activity.at)}>
                    {' '}
                    · {timeAgo(activity.at)}
                </span>
            </p>
        </li>
    );
}

ActivityItem.propTypes = { activity: PropTypes.object.isRequired };

function CommentItem({ comment, members }) {
    const t = useT();

    return (
        <li id={`comment-${comment.id}`} className="bg-surface target:ring-brand-300 flex scroll-mt-24 gap-3 rounded-xl p-3 target:ring-2">
            <Avatar src={null} name={comment.user ?? '?'} size={30} />
            <div className="min-w-0 flex-1">
                <p className="text-xs">
                    <span className="text-ink font-semibold">{comment.user}</span>
                    <span className="text-ink-subtle" title={formatDateTime(comment.at)}>
                        {' '}
                        · {timeAgo(comment.at)}
                    </span>
                </p>
                <p className="text-ink mt-1 text-sm leading-relaxed break-words whitespace-pre-line">
                    <Highlighted text={comment.body} names={members.map((m) => m.name)} />
                </p>
            </div>
            {comment.can_delete && (
                <button
                    type="button"
                    onClick={() => router.delete(`/comments/${comment.id}`, { preserveScroll: true })}
                    className="text-ink-subtle hover:text-danger shrink-0 self-start rounded p-1"
                    aria-label={t('comments.delete')}
                    title={t('comments.delete')}
                >
                    <Trash2 className="size-3.5" />
                </button>
            )}
        </li>
    );
}

CommentItem.propTypes = { comment: PropTypes.object.isRequired, members: PropTypes.array.isRequired };

/** Shows "@Name" mentions in the brand colour. */
function Highlighted({ text, names }) {
    const parts = useMemo(() => {
        if (!names.length) return [text];
        const escaped = [...names].sort((a, b) => b.length - a.length).map((n) => n.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
        return text.split(new RegExp(`(@(?:${escaped.join('|')}))`, 'g'));
    }, [text, names]);

    return parts.map((part, i) =>
        part.startsWith('@') && names.includes(part.slice(1)) ? (
            <span key={i} className="text-brand-700 font-semibold">
                {part}
            </span>
        ) : (
            part
        ),
    );
}

/** Comment input with "@" suggestions from the team. */
function CommentBox({ url, members }) {
    const t = useT();
    const textarea = useRef(null);
    const form = useForm({ body: '', mentions: [] });
    const [query, setQuery] = useState(null); // text typed after "@", or null when not mentioning
    const [active, setActive] = useState(0);

    const matches = query === null ? [] : members.filter((m) => m.name.toLowerCase().includes(query.toLowerCase())).slice(0, 6);

    const onChange = (e) => {
        const value = e.target.value;
        form.setData('body', value);
        const beforeCursor = value.slice(0, e.target.selectionStart);
        const match = /(^|\s)@([^\s@]*)$/.exec(beforeCursor);
        setQuery(match ? match[2] : null);
        setActive(0);
    };

    const pick = (member) => {
        const el = textarea.current;
        const cursor = el.selectionStart;
        const before = form.data.body.slice(0, cursor).replace(/@([^\s@]*)$/, `@${member.name} `);
        const body = before + form.data.body.slice(cursor);
        form.setData({ body, mentions: [...new Set([...form.data.mentions, member.id])] });
        setQuery(null);
        requestAnimationFrame(() => {
            el.focus();
            el.setSelectionRange(before.length, before.length);
        });
    };

    const onKeyDown = (e) => {
        if (matches.length) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                setActive((i) => (i + 1) % matches.length);
                return;
            }
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                setActive((i) => (i - 1 + matches.length) % matches.length);
                return;
            }
            if (e.key === 'Enter' || e.key === 'Tab') {
                e.preventDefault();
                pick(matches[active]);
                return;
            }
            if (e.key === 'Escape') {
                setQuery(null);
                return;
            }
        }
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) submit(e);
    };

    const submit = (e) => {
        e.preventDefault();
        if (!form.data.body.trim()) return;
        form.post(url, { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <form onSubmit={submit} className="border-line mt-5 border-t pt-4">
            <div className="relative">
                <textarea
                    ref={textarea}
                    value={form.data.body}
                    onChange={onChange}
                    onKeyDown={onKeyDown}
                    rows={3}
                    placeholder={t('comments.placeholder')}
                    aria-label={t('comments.placeholder')}
                    className="border-line-strong bg-card focus:border-brand-500 focus:ring-brand-100 block w-full rounded-xl border px-3.5 py-2.5 text-sm leading-relaxed focus:ring-3 focus:outline-none"
                />
                {query !== null && (
                    <ul
                        className="border-line bg-card absolute inset-x-0 bottom-full z-20 mb-1 max-h-56 overflow-y-auto rounded-xl border py-1 shadow-lg"
                        role="listbox"
                    >
                        {matches.length === 0 ? (
                            <li className="text-ink-subtle px-3.5 py-2 text-sm">{t('comments.no_members')}</li>
                        ) : (
                            matches.map((member, i) => (
                                <li
                                    key={member.id}
                                    role="option"
                                    aria-selected={i === active}
                                    onMouseDown={(e) => {
                                        e.preventDefault();
                                        pick(member);
                                    }}
                                    className={cn(
                                        'flex cursor-pointer items-center gap-2 px-3.5 py-2 text-sm',
                                        i === active ? 'bg-brand-50' : 'hover:bg-surface',
                                    )}
                                >
                                    <Avatar src={null} name={member.name} size={24} />
                                    {member.name}
                                </li>
                            ))
                        )}
                    </ul>
                )}
            </div>
            {form.errors.body && <p className="text-danger mt-1 text-xs">{form.errors.body}</p>}
            <div className="mt-2 flex items-center justify-between gap-3">
                <p className="text-ink-subtle text-xs">{t('comments.internal')}</p>
                <Button type="submit" size="sm" disabled={form.processing || !form.data.body.trim()}>
                    {t('comments.send')}
                </Button>
            </div>
        </form>
    );
}

CommentBox.propTypes = { url: PropTypes.string.isRequired, members: PropTypes.array.isRequired };
