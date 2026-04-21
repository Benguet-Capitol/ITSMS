<?php

namespace App\Services;

use App\Models\Profile;
use App\Enums\TicketStatus;

class ProfileEngagementService
{
    /**
     * Sync a profile's engagement (READY/BUSY) based on their active tickets.
     */
    public static function sync(Profile $profile): void {
        $hasActiveTickets = $profile->ticketPersonnel()
            ->whereIn('request_status', [TicketStatus::Accepted, TicketStatus::Reopened])
            ->whereNotIn('query_status', [TicketStatus::Resolved, TicketStatus::Cancelled])
            ->exists();

        $profile->update([
            'engagement' => $hasActiveTickets
                ? Profile::ENGAGEMENT_BUSY
                : Profile::ENGAGEMENT_READY,
        ]);
    }

    /**
     * Sync all profiles attached to a ticket.
     */
    public static function syncTicket($ticket): void {
        foreach ($ticket->personnel as $profile) {
            self::sync($profile);
        }
    }
}
