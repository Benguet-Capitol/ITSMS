<?php

namespace App\Observers;

use App\Models\Ticket;
use App\Models\Profile;
use App\Enums\TicketStatus;

class TicketObserver
{
    /**
     * Ensure profile engagement stays in sync with tickets
     */
    protected function syncProfileEngagement(Profile $profile): void {
      $hasActiveTickets = $profile->ticketPersonnel()
          ->whereIn('request_status', [TicketStatus::Accepted, TicketStatus::InProgress])
          ->exists();

      $profile->update([
          'engagement' => $hasActiveTickets
              ? Profile::ENGAGEMENT_BUSY
              : Profile::ENGAGEMENT_READY,
      ]);
    }

    /**
     * Handle the Ticket "created" event.
     */
    public function created(Ticket $ticket): void
    {
      foreach ($ticket->personnel as $profile) {
          $this->syncProfileEngagement($profile);
      }
    }

    /**
     * Handle the Ticket "updated" event.
     */
    public function updated(Ticket $ticket): void
    {
      foreach ($ticket->personnel as $profile) {
          $this->syncProfileEngagement($profile);
      }
    }

    /**
     * Handle the Ticket "deleted" event.
     */
    public function deleted(Ticket $ticket): void
    {
      foreach ($ticket->personnel as $profile) {
          $this->syncProfileEngagement($profile);
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
