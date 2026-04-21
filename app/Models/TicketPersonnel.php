<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TicketPersonnel extends Pivot
{
    protected $table = 'ticket_personnel';
    public $timestamps = true;

    protected static function booted() {
        static::saved(function (TicketPersonnel $pivot) {
            $profile = Profile::find($pivot->profile_id);
            if ($profile) {
                self::syncProfileEngagement($profile);
            }
        });

        static::deleted(function (TicketPersonnel $pivot) {
            $profile = Profile::find($pivot->profile_id);
            if ($profile) {
                self::syncProfileEngagement($profile);
            }
        });
    }

    /**
     * Keep profile engagement in sync with their tickets
     */
    protected static function syncProfileEngagement(Profile $profile): void
    {
        $hasActiveTickets = $profile->ticketPersonnel()
            ->whereIn('request_status', [TicketStatus::Accepted, TicketStatus::InProgress])
            ->exists();

        $profile->update([
            'engagement' => $hasActiveTickets
                ? Profile::ENGAGEMENT_BUSY
                : Profile::ENGAGEMENT_READY,
        ]);
    }
}
