import { Link, router } from '@inertiajs/react';
import { AtSign, BellOff, CheckCheck } from 'lucide-react';
import PropTypes from 'prop-types';
import { Button } from '@/Components/ui/Button';
import { Card, EmptyState } from '@/Components/ui/Card';
import { Pagination } from '@/Components/ui/Pagination';
import AppLayout from '@/Layouts/AppLayout';
import { cn, timeAgo } from '@/lib/format';
import { useT } from '@/lib/i18n';

export default function NotificationsIndex({ notifications }) {
    const t = useT();
    const hasUnread = notifications.data.some((n) => !n.read);

    return (
        <AppLayout
            title={t('notifications.title')}
            actions={
                hasUnread && (
                    <Button
                        variant="secondary"
                        size="sm"
                        icon={<CheckCheck className="size-4" />}
                        onClick={() => router.post('/notifications/read-all', {}, { preserveScroll: true })}
                    >
                        {t('notifications.read_all')}
                    </Button>
                )
            }
        >
            <div className="mx-auto max-w-3xl">
                {notifications.data.length === 0 ? (
                    <EmptyState icon={<BellOff className="size-8" />} title={t('notifications.empty')} />
                ) : (
                    <Card>
                        <ul className="divide-line divide-y">
                            {notifications.data.map((notification) => (
                                <li key={notification.id}>
                                    {/* Opening marks it read, then goes to the comment. */}
                                    <Link
                                        href={`/notifications/${notification.id}`}
                                        className={cn('hover:bg-surface flex gap-3 px-5 py-4', !notification.read && 'bg-brand-50/60')}
                                    >
                                        <span className="bg-brand-100 text-brand-700 flex size-9 shrink-0 items-center justify-center rounded-full">
                                            <AtSign className="size-4" />
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className={cn('text-ink text-sm', !notification.read && 'font-semibold')}>
                                                {t('notifications.mention', { author: notification.data.author ?? '', number: notification.data.number ?? '' })}
                                            </p>
                                            {notification.data.excerpt && (
                                                <p className="text-ink-muted mt-0.5 truncate text-sm">«{notification.data.excerpt}»</p>
                                            )}
                                            <p className="text-ink-subtle mt-1 text-xs">{timeAgo(notification.created_at)}</p>
                                        </div>
                                        {!notification.read && <span className="bg-brand-600 mt-2 size-2 shrink-0 rounded-full" aria-hidden />}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </Card>
                )}
                <Pagination meta={notifications} />
            </div>
        </AppLayout>
    );
}

NotificationsIndex.propTypes = {
    notifications: PropTypes.shape({ data: PropTypes.array.isRequired }).isRequired,
};
