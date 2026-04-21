<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketComment extends Model
{
    protected $fillable = [
        'comment',
    ];

    public function profile() {
      return $this->belongsTo(Profile::class);
    }

    public function ticket() {
      return $this->belongsTo(Ticket::class);
    }
}
