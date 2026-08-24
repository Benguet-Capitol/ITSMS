<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketAssessment extends Model
{
    protected $fillable = [
        'ticket_id',
        'findings',
        'recommendations',
        'replacement_available',
        'specifications',
        'acquisition_cost',
        'is_set',
        'components',
        'reviewed_by',
        'assessed_by',
        'reviewed_by_position',
        'assessed_by_position',
    ];

    protected $casts = [
        'components'             => 'array',
        'replacement_available'  => 'boolean',
        'acquisition_cost'       => 'decimal:2',
        'is_set' => 'boolean',
    ];

    public function ticket() {
        return $this->belongsTo(Ticket::class);
    }
}
