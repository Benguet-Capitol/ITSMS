<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class TicketAssessment extends Model
{
    protected $fillable = [
        'ticket_id',
        'control_number',
        'recommendations',
        'replacement_available',
        'specifications',
        'acquisition_cost',
        'is_set',
        'components',
        'component_findings',
        'reviewed_by',
        'assessed_by',
        'reviewed_by_position',
        'assessed_by_position',
    ];

    protected $casts = [
        'components' => 'array',
        'component_findings' => 'array',
        'replacement_available' => 'boolean',
        'acquisition_cost' => 'decimal:2',
        'is_set' => 'boolean',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public static function generateControlNumber(): string
    {
        $now = Carbon::now();

        $count = self::whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->count();

        $serial = str_pad($count + 1, 4, '0', STR_PAD_LEFT);

        return "{$now->format('Y-m')}-{$serial}"; // 2025-10-0001
    }
}
