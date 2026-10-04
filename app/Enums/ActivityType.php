<?php

namespace App\Enums;

/** Entries of a document's activity timeline. Texts: lang/ar/ui.php activity.types. */
enum ActivityType: string
{
    // Team actions
    case Created = 'created';
    case Updated = 'updated';
    case Duplicated = 'duplicated';   // data: from (number)
    case Revised = 'revised';         // data: revision
    case Converted = 'converted';     // on the quote; data: invoice (number)
    case CreatedFromQuote = 'created_from_quote'; // on the invoice; data: quote (number)
    case Sent = 'sent';               // data: channel, recipient
    case Extended = 'extended';       // data: valid_until

    // Client and system events
    case Viewed = 'viewed';           // data: channel, recipient, device
    case Approved = 'approved';       // data: name
    case Rejected = 'rejected';       // data: reason
    case Expired = 'expired';

    /** Shown with a client / system icon rather than a team member. */
    public function isClientEvent(): bool
    {
        return in_array($this, [self::Viewed, self::Approved, self::Rejected, self::Expired], true);
    }
}
