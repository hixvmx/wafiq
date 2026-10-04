<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The magic login link. Sent immediately, not queued: the member is waiting
 * for it, and a missing queue cron must never lock everyone out.
 */
class LoginLinkNotification extends Notification
{
    public function __construct(public string $url) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('ui.mail.login_link.subject'))
            ->greeting(__('ui.mail.greeting', ['name' => $notifiable->name]))
            ->line(__('ui.mail.login_link.intro'))
            ->action(__('ui.mail.login_link.action'), $this->url)
            ->line(__('ui.mail.login_link.expiry', ['minutes' => config('wafiq.login_link_minutes')]))
            ->line(__('ui.mail.login_link.ignore'));
    }
}
