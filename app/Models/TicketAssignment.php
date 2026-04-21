<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketAssignment extends Model
{
    protected $fillable = [
        'status',
        'accepted_at',
    ];

    public function ticket() {
      return $this->belongsTo(Ticket::class);
    }
}
