<?php

namespace App\Models;

use App\Enums\ServiceMethod;
use Illuminate\Database\Eloquent\Model;

class TicketResolution extends Model
{
    protected $fillable = [
        'ticket_id',
        'solution_id',
        'service_method',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'service_method' => ServiceMethod::class,
        'resolved_at' => 'datetime',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function solution()
    {
        return $this->belongsTo(Solution::class);
    }
}
