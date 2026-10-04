<?php

namespace App\Support;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends notifications without ever breaking the user's action: a wrong SMTP setting
 * (very common on fresh installs) is logged instead of turning a click into an error page.
 */
final class Notifier
{
    /**
     * @param  mixed  $notifiables  a user, a collection/array of users, or Notification::route(…)
     * @return bool true when every notification was sent (or queued) without an error
     */
    public static function send(mixed $notifiables, Notification $notification): bool
    {
        $ok = true;

        foreach (is_iterable($notifiables) ? $notifiables : [$notifiables] as $notifiable) {
            try {
                $notifiable->notify($notification);
            } catch (Throwable $e) {
                $ok = false;
                Log::warning('Notification not sent: '.$e->getMessage(), [
                    'notification' => $notification::class,
                    'notifiable' => $notifiable->id ?? null,
                ]);
            }
        }

        return $ok;
    }
}
