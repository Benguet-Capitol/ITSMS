<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketAssessment extends Model
{
    use SoftDeletes;

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
        $prefix = Carbon::now()->format('Y-m');
        $lastSerial = self::withTrashed()
            ->where('control_number', 'like', "{$prefix}-%")
            ->orderByDesc('control_number')
            ->value('control_number');

        $lastNumber = $lastSerial ? (int) substr($lastSerial, -4) : 0;

        return sprintf('%s-%04d', $prefix, $lastNumber + 1); // 2025-10-0001
    }
}
