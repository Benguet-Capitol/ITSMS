<?php

namespace App\Observers;

use App\Models\Profile;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketCreatedNotification;
use App\Services\ProfileEngagementService;

class TicketObserver
{
    /**
     * Handle the Ticket "created" event.
     */
    public function created(Ticket $ticket): void
    {
        $technicians = User::whereHas('roles', fn ($q) => $q->where('title', 'IT Technical'))
            ->with('profile.profileOffices', 'profile.agencies')
            ->get();

        foreach ($technicians as $user) {
            $isPriorityMatch = $user->profile && (
                $user->profile->profileOffices->contains('office_id', $ticket->office_id) ||
                $user->profile->agencies->contains('id', $ticket->agency_id)
            );

            $user->notify(new TicketCreatedNotification($ticket, $isPriorityMatch));
        }

        foreach ($ticket->personnel()->get() as $profile) {
            ProfileEngagementService::sync($profile);
        }
    }

    /**
     * Handle the Ticket "updated" event.
     */
    public function updated(Ticket $ticket): void
    {
        foreach ($ticket->personnel()->get() as $profile) {
            ProfileEngagementService::sync($profile);
        }
    }

    /**
     * Handle the Ticket "deleted" event.
     */
    public function deleted(Ticket $ticket): void
    {
        // Unlike created()/updated(), this must read the cached $with-eager-
        // loaded relation rather than querying fresh: ticket_personnel rows
        // cascadeOnDelete, so by the time this fires the DB rows are already
        // gone -- a fresh query here would find nobody to re-sync, and every
        // technician who was on this ticket would stay stuck at whatever
        // engagement they had, forever.
        foreach ($ticket->personnel as $profile) {
            ProfileEngagementService::sync($profile);
        }
    }

    /**
     * Handle the Ticket "restored" event.
     */
    public function restored(Ticket $ticket): void
    {
        //
    }

    /**
     * Handle the Ticket "force deleted" event.
     */
    public function forceDeleted(Ticket $ticket): void
    {
        //
    }
}
