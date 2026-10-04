<?php

namespace App\Enums;

/** The six statuses shared by quotations and invoices (section 3 of idea-wafiq.md). */
enum DocumentStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Viewed = 'viewed';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function label(): string
    {
        return __("ui.statuses.{$this->value}");
    }

    /** Approved and rejected are final for that revision; changes need a new revision. */
    public function isFinal(): bool
    {
        return in_array($this, [self::Approved, self::Rejected], true);
    }
}
