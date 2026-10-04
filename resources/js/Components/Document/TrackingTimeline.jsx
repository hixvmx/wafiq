import { CheckCircle2, Clock, Eye, EyeOff, Link2, Mail, MessageCircle, XCircle } from 'lucide-react';
import PropTypes from 'prop-types';
import { Card } from '@/Components/ui/Card';
import { formatDateTime, ltr, timeAgo } from '@/lib/format';
import { useT } from '@/lib/i18n';

const CHANNEL_ICONS = { whatsapp: MessageCircle, email: Mail, link: Link2 };

/** Client events (answer, expiry) on top, then every tracked link with its views. */
export function TrackingTimeline({ document, sends }) {
    const t = useT();

    return (
        <Card className="p-5">
            <h2 className="text-ink mb-4 text-base font-bold">{t('tracking.title')}</h2>

            <ol className="space-y-4">
                {document.approved_at && (
                    <Event
                        icon={CheckCircle2}
                        tone="text-success"
                        title={t('tracking.approved', { name: document.approved_by_name })}
                        time={document.approved_at}
                        detail={document.approved_ip && `IP ${document.approved_ip}`}
                    />
                )}
                {document.rejected_at && (
                    <Event
                        icon={XCircle}
                        tone="text-danger"
                        title={t('tracking.rejected')}
                        time={document.rejected_at}
                        detail={document.rejection_reason && t('tracking.reason', { reason: document.rejection_reason })}
                    />
                )}
                {document.expired_at && <Event icon={Clock} tone="text-warning" title={t('tracking.expired')} time={document.expired_at} />}

                {sends.map((send) => {
                    const Icon = CHANNEL_ICONS[send.channel] ?? Link2;
                    const title = [
                        t('tracking.sent_via', { channel: t(`share.channels.${send.channel}`) }),
                        send.recipient && t('tracking.to', { recipient: ltr(send.recipient) }),
                    ]
                        .filter(Boolean)
                        .join(' ');
                    return (
                        <Event
                            key={send.id}
                            icon={Icon}
                            tone="text-brand-600"
                            title={title}
                            time={send.sent_at}
                            detail={send.sent_by ? t('tracking.by', { name: send.sent_by }) : t('tracking.opened_from_old_link')}
                        >
                            <p className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                                {send.views_count > 0 ? (
                                    <span className="inline-flex items-center gap-1 font-medium text-violet-700">
                                        <Eye className="size-3.5" />
                                        {t('tracking.views', { count: send.views_count })} · {t('tracking.last_viewed', { time: timeAgo(send.last_viewed_at) })}
                                    </span>
                                ) : (
                                    <span className="text-ink-subtle inline-flex items-center gap-1">
                                        <EyeOff className="size-3.5" />
                                        {t('tracking.not_opened')}
                                    </span>
                                )}
                                {send.email_status === 'failed' && <span className="text-danger font-medium">{t('tracking.email_failed')}</span>}
                            </p>
                        </Event>
                    );
                })}
            </ol>

            {sends.length === 0 && <p className="text-ink-subtle text-sm">{t('tracking.empty')}</p>}
        </Card>
    );
}

TrackingTimeline.propTypes = {
    document: PropTypes.object.isRequired,
    sends: PropTypes.arrayOf(
        PropTypes.shape({
            id: PropTypes.number.isRequired,
            channel: PropTypes.string.isRequired,
            recipient: PropTypes.string,
            sent_by: PropTypes.string,
            sent_at: PropTypes.string.isRequired,
            email_status: PropTypes.string,
            views_count: PropTypes.number.isRequired,
            last_viewed_at: PropTypes.string,
        }),
    ).isRequired,
};

function Event({ icon: Icon, tone, title, time, detail, children }) {
    return (
        <li className="flex gap-3">
            <span className={`mt-0.5 shrink-0 ${tone}`}>
                <Icon className="size-5" />
            </span>
            <div className="min-w-0 flex-1">
                <p className="text-ink text-sm font-medium">{title}</p>
                <p className="text-ink-subtle text-xs">
                    {formatDateTime(time)}
                    {detail && ` · ${detail}`}
                </p>
                {children}
            </div>
        </li>
    );
}

Event.propTypes = {
    icon: PropTypes.elementType.isRequired,
    tone: PropTypes.string.isRequired,
    title: PropTypes.string.isRequired,
    time: PropTypes.string.isRequired,
    detail: PropTypes.string,
    children: PropTypes.node,
};
