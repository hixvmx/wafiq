<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Team invitation, sent to an email address (the person may not have an account yet). */
class InvitationNotification extends Notification
{
    public function __construct(
        public Invitation $invitation,
        public string $url,
        public string $companyName,
        public string $inviterName,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $params = [
            'company' => $this->companyName,
            'inviter' => $this->inviterName,
            'role' => $this->invitation->role->label(),
        ];

        return (new MailMessage)
            ->subject(__('ui.mail.invitation.subject', $params))
            ->greeting(__('ui.mail.greeting_plain'))
            ->line(__('ui.mail.invitation.intro', $params))
            ->action(__('ui.mail.invitation.action'), $this->url)
            ->line(__('ui.mail.invitation.expiry', ['days' => config('wafiq.invitation_days')]));
    }
}
