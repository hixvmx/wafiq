<?php

namespace App\Actions;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\DocumentSend;
use App\Models\DocumentView;
use App\Models\User;
use App\Services\DocumentWorkflow;
use App\Support\BotDetector;

/**
 * Called by the client page's "viewed" signal (sent by JavaScript after 2 visible seconds).
 * Robots, scanners and the company's own team are not counted. A reload from the same
 * place within 10 minutes is the same view.
 */
class RecordView
{
    private const SAME_VIEW_MINUTES = 10;

    public function __construct(private DocumentWorkflow $workflow) {}

    /** @return bool whether a view was counted */
    public function handle(DocumentSend $send, ?User $user, ?string $ip, ?string $userAgent): bool
    {
        $document = $send->document;

        if (BotDetector::isBot($userAgent) || $user?->roleIn($document->company)) {
            return false;
        }

        $now = now();
        $recent = DocumentView::where('document_send_id', $send->id)
            ->where('ip', $ip)
            ->where('viewed_at', '>=', $now->copy()->subMinutes(self::SAME_VIEW_MINUTES))
            ->exists();

        if ($recent) {
            $send->update(['last_viewed_at' => $now]);
            $document->update(['last_viewed_at' => $now]);

            return false;
        }

        DocumentView::create([
            'document_id' => $document->id,
            'document_send_id' => $send->id,
            'viewed_at' => $now,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'device' => BotDetector::device($userAgent),
        ]);

        $send->increment('views_count', 1, ['last_viewed_at' => $now, 'first_viewed_at' => $send->first_viewed_at ?? $now]);
        $this->workflow->markViewed($document);
        Activity::log($document, ActivityType::Viewed, [
            'channel' => $send->channel,
            'recipient' => $send->recipient,
            'device' => BotDetector::device($userAgent),
        ]);

        return true;
    }
}
