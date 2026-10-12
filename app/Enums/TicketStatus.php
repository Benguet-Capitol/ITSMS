<?php

namespace App\Enums;

enum TicketStatus: string
{
    // Query Status
    case Queued = 'queued'; // Only when open
    case InProgress = 'in_progress'; // Only when accepted
    case CheckingStock = 'checking_stock'; // Only when accepted
    case AwaitingPart = 'awaiting_part'; // Only when accepted -- displayed as "Waiting for Parts" per the client's Scope of Work wording
    case AwaitingUser = 'awaiting_user'; // Only when accepted -- no controller action sets this and 0 rows use it locally, but not removed: cannot confirm the live production DB (a separate server) has no rows on this value, and Laravel's enum cast throws on an unrecognized backing value rather than degrading gracefully
    case AwaitingVendor = 'awaiting_vendor'; // Only when accepted -- same caveat as AwaitingUser above
    case Resolved = 'resolved';
    case Assessed = 'assessed';
    case Cancelled = 'cancelled';

    // Request Status
    case Open = 'open';
    case Accepted = 'accepted';
    case Closed = 'closed';
    case Reopened = 'reopened';
}
