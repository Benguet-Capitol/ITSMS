<?php

namespace App\Enums;

enum TicketStatus: string
{
    // Query Status
    case Queued = 'queued'; // Only when open
    case InProgress = 'in_progress'; // Only when accepted
    case CheckingStock = 'checking_stock'; // Only when accepted
    case AwaitingPart = 'awaiting_part'; // Only when accepted
    case AwaitingUser = 'awaiting_user'; // Only when accepted
    case AwaitingVendor = 'awaiting_vendor'; // Only when accepted
    case Resolved = 'resolved';
    case Cancelled = 'cancelled';
    
    // Request Status
    case Open = 'open';
    case Accepted = 'accepted';
    case Closed = 'closed';
    case Reopened = 'reopened';
}
