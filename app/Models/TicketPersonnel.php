<?php

namespace App\Models;

use App\Services\ProfileEngagementService;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TicketPersonnel extends Pivot
{
    protected $table = 'ticket_personnel';

    public $timestamps = true;

    protected static function booted()
    {
        static::saved(function (TicketPersonnel $pivot) {
            $profile = Profile::find($pivot->profile_id);
            if ($profile) {
                ProfileEngagementService::sync($profile);
            }
        });

        static::deleted(function (TicketPersonnel $pivot) {
            $profile = Profile::find($pivot->profile_id);
            if ($profile) {
                ProfileEngagementService::sync($profile);
            }
        });
    }
}
