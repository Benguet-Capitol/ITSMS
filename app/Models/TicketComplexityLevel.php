<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketComplexityLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'description',
        'examples',
        'min_minutes',
        'max_minutes',
        'color',
        'sort_order',
    ];

    protected $casts = [
        'min_minutes' => 'integer',
        'max_minutes' => 'integer',
        'sort_order' => 'integer',
    ];

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'complexity_level_id');
    }
}
