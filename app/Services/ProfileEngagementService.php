<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Enums\TicketStatus;
use App\Models\Profile;
use App\Support\RequestTypes;
use Illuminate\Database\Eloquent\Model;

class ProfileEngagementService
{
    /**
     * Sync a profile's engagement (READY/BUSY) based on their active
     * tickets and active requests (in progress or on hold).
     */
    public static function sync(Profile $profile): void
    {
        $isBusy = $profile->ticketPersonnel()
            ->whereIn('request_status', [TicketStatus::Accepted, TicketStatus::Reopened])
            ->whereNotIn('query_status', [TicketStatus::Resolved, TicketStatus::Cancelled])
            ->exists()
            || self::hasActiveRequests($profile);

        $profile->update([
            'engagement' => $isBusy
                ? Profile::ENGAGEMENT_BUSY
                : Profile::ENGAGEMENT_READY,
        ]);
    }

    /** Sync everyone on a request (any HasRequestWorkflow model). */
    public static function syncRequest(Model $request): void
    {
        foreach ($request->personnel()->get() as $profile) {
            self::sync($profile);
        }
    }

    private static function hasActiveRequests(Profile $profile): bool
    {
        foreach (RequestTypes::morphMap() as $model) {
            $active = $model::query()
                ->assignedTo($profile->id)
                ->whereIn('status', RequestStatus::active())
                ->exists();

            if ($active) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sync all profiles attached to a ticket.
     *
     * Queries personnel fresh rather than using $ticket->personnel: that
     * relation is in Ticket's $with, so it's eager-loaded (and cached) the
     * moment the ticket is route-model-bound -- before the same request's
     * attach()/detach() call runs. Reading the cached property here would
     * silently skip whichever profile was just attached (their engagement
     * would never flip to busy) or include one that still needs a stale
     * re-check after being detached.
     */
    public static function syncTicket($ticket): void
    {
        foreach ($ticket->personnel()->get() as $profile) {
            self::sync($profile);
        }
    }
}
