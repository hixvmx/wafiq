<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Support\DocumentPresenter;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * The status machine (section 3 of idea-wafiq.md). Every status change goes through here.
 *
 *   Draft ──► Sent ──► Viewed ──► Approved ✓
 *               │         └─────► Rejected ✗
 *               └─────────┴─────► Expired ⏱ ──(extend validity)──► Sent
 *
 * Approved and Rejected are final for that revision. Updates that change the status are
 * conditional ("… where status in (…)") so two clicks at once can't both win.
 */
class DocumentWorkflow
{
    /** Statuses a document can be (re)sent from. Expired needs "extend validity" first. */
    private const SENDABLE = [DocumentStatus::Draft, DocumentStatus::Sent, DocumentStatus::Viewed];

    /** Statuses in which the client can still answer. */
    private const OPEN = [DocumentStatus::Sent, DocumentStatus::Viewed];

    public function canSend(Document $document): bool
    {
        return $document->is_latest && in_array($document->status, self::SENDABLE, true) && ! $this->isPastValidity($document);
    }

    /** First send: Draft → Sent, and the client and company details are frozen into the document. */
    public function markSent(Document $document): void
    {
        if (! $this->canSend($document)) {
            throw new LogicException("Document {$document->id} can't be sent in status {$document->status->value}.");
        }

        if ($document->isDraft()) {
            $document->update([
                'status' => DocumentStatus::Sent,
                'sent_at' => now(),
                'client_snapshot' => DocumentPresenter::client($document->client),
                'company_snapshot' => DocumentPresenter::company($document->company),
            ]);
        }
    }

    /** The client's first real view: Sent → Viewed. */
    public function markViewed(Document $document): void
    {
        $now = now();

        Document::whereKey($document->id)->increment('views_count', 1, ['last_viewed_at' => $now]);
        Document::whereKey($document->id)->whereNull('first_viewed_at')->update(['first_viewed_at' => $now]);
        Document::whereKey($document->id)->where('status', DocumentStatus::Sent)->update(['status' => DocumentStatus::Viewed]);

        $document->refresh();
    }

    /** @return bool false when the document was no longer open (double click, expired, replaced) */
    public function approve(Document $document, string $name, ?string $ip, ?string $userAgent): bool
    {
        return $this->answer($document, [
            'status' => DocumentStatus::Approved,
            'approved_at' => now(),
            'approved_by_name' => $name,
            'approved_ip' => $ip,
            'approved_user_agent' => $userAgent,
        ]);
    }

    public function reject(Document $document, ?string $reason): bool
    {
        return $this->answer($document, [
            'status' => DocumentStatus::Rejected,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    /** Sent / Viewed past their validity date → Expired. Returns how many changed. */
    public function expireDue(): int
    {
        return Document::withoutGlobalScope('company')
            ->whereIn('status', self::OPEN)
            ->whereNotNull('valid_until')
            ->where('valid_until', '<', Carbon::today()->toDateString())
            ->update(['status' => DocumentStatus::Expired, 'expired_at' => now()]);
    }

    /** Expires this one document if its date has passed (the client page doesn't wait for the scheduler). */
    public function expireIfDue(Document $document): void
    {
        if (in_array($document->status, self::OPEN, true) && $this->isPastValidity($document)) {
            $document->update(['status' => DocumentStatus::Expired, 'expired_at' => now()]);
        }
    }

    /** New validity date. An expired document goes back to Sent so it can be resent. */
    public function extend(Document $document, Carbon $validUntil): void
    {
        if ($document->status->isFinal() || ! $document->is_latest) {
            throw new LogicException('Approved, rejected or replaced documents keep their validity.');
        }

        $document->update([
            'valid_until' => $validUntil,
            ...($document->status === DocumentStatus::Expired ? ['status' => DocumentStatus::Sent, 'expired_at' => null] : []),
        ]);
    }

    /** Can the client approve or reject this revision right now? */
    public function canClientRespond(Document $document): bool
    {
        return in_array($document->status, self::OPEN, true) && ! $this->isPastValidity($document) && ! $this->isReplaced($document);
    }

    /** A newer revision has been sent: this one's link shows "replaced". */
    public function isReplaced(Document $document): bool
    {
        return $this->newestSentRevision($document)?->id !== $document->id;
    }

    public function newestSentRevision(Document $document): ?Document
    {
        return Document::withoutGlobalScope('company')
            ->where('root_id', $document->root_id)
            ->where('status', '!=', DocumentStatus::Draft)
            ->orderByDesc('revision')
            ->first();
    }

    public function isPastValidity(Document $document): bool
    {
        return $document->valid_until !== null && $document->valid_until->lt(Carbon::today());
    }

    private function answer(Document $document, array $changes): bool
    {
        if (! $this->canClientRespond($document)) {
            return false;
        }

        $updated = Document::whereKey($document->id)->whereIn('status', self::OPEN)->update($changes);
        $document->refresh();

        return $updated === 1;
    }
}
