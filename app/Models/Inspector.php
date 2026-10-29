<?php

namespace App\Models;

use App\Models\Concerns\HasRequesterOffice;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Someone who inspects deliveries -- IT staff or not -- picked on an
 * Inspection Request (docs/requests-inspection.md). Their office comes
 * from the HRIS office list, like a requester's.
 */
class Inspector extends Model
{
    use HasFactory, HasRequesterOffice;

    /** Office text column (HasRequesterOffice). */
    public const OFFICE_FIELD = 'office';

    /** Provincial offices only, from HRIS. */
    public const ALLOWS_OTHER_AGENCY = false;

    protected $fillable = [
        'name',
        'position',
        'office',
        'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** "Position, OFFICE-CODE" for the report and the dropdown. */
    public function designation(): string
    {
        return collect([$this->position, $this->office_code ?: $this->office])
            ->filter()
            ->join(', ');
    }

    public function inspectionRequests(): BelongsToMany
    {
        return $this->belongsToMany(InspectionRequest::class);
    }
}
