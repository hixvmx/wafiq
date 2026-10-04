<?php

namespace App\Notifications;

use App\Models\Comment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** "Sara mentioned you on QT-2026-0042": in the app (bell) and by email. */
class MentionedInComment extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Comment $comment) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $document = $this->comment->document;

        return [
            'kind' => 'mention',
            'company_id' => $document->company_id, // the bell only shows the current company's notifications
            'document_id' => $document->id,
            'document_type' => $document->type,
            'number' => $document->displayNumber(),
            'author' => $this->comment->user?->name,
            'excerpt' => Str::limit($this->comment->body, 140),
            'url' => route("{$document->routePrefix()}.show", $document).'#comment-'.$this->comment->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->toArray($notifiable);

        return (new MailMessage)
            ->subject(__('ui.mentions.mail_subject', ['author' => $data['author'], 'number' => $data['number']]))
            ->greeting(__('ui.mail.greeting', ['name' => $notifiable->name]))
            ->line(__('ui.mentions.mail_intro', ['author' => $data['author'], 'number' => $data['number']]))
            ->line('«'.$data['excerpt'].'»')
            ->action(__('ui.mentions.mail_action'), $data['url']);
    }
}
