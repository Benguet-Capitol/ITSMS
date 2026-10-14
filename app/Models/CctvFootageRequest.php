<?php

namespace App\Models;

use App\Models\Concerns\HasDateSchedule;
use App\Models\Concerns\HasYearlyControlNumber;
use Illuminate\Database\Eloquent\Model;

class CctvFootageRequest extends Model
{
    use HasDateSchedule, HasYearlyControlNumber;

    public const STATUS_PENDING = 'pending';

    public const STATUS_RECOMMENDED = 'recommended';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_DENIED = 'denied';

    public const STATUS_RELEASED = 'released';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_RECOMMENDED,
        self::STATUS_APPROVED,
        self::STATUS_DENIED,
        self::STATUS_RELEASED,
        self::STATUS_CANCELLED,
    ];

    /**
     * Mirrors the form's signature flow: the DPO recommends, the Provincial
     * Administrator approves (or the request is denied at either step), and
     * IT releases/plays back the footage. Denied, released and cancelled
     * are final.
     */
    public const TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_RECOMMENDED, self::STATUS_DENIED, self::STATUS_CANCELLED],
        self::STATUS_RECOMMENDED => [self::STATUS_APPROVED, self::STATUS_DENIED, self::STATUS_CANCELLED],
        self::STATUS_APPROVED => [self::STATUS_RELEASED, self::STATUS_CANCELLED],
        self::STATUS_DENIED => [],
        self::STATUS_RELEASED => [],
        self::STATUS_CANCELLED => [],
    ];

    /** Status => the timestamp column stamped when a request enters it. */
    public const STATUS_TIMESTAMPS = [
        self::STATUS_RECOMMENDED => 'recommended_at',
        self::STATUS_APPROVED => 'approved_at',
        self::STATUS_DENIED => 'denied_at',
        self::STATUS_RELEASED => 'released_at',
        self::STATUS_CANCELLED => 'cancelled_at',
    ];

    public const CAPACITIES = [
        'person_in_footage',
        'property_owner',
        'authorized_representative',
        'law_enforcement',
        'court_order_holder',
        'other',
    ];

    public const PURPOSES = ['viewing', 'copy'];

    public const REASONS = [
        'vehicular_accident',
        'personal_injury',
        'theft_robbery',
        'vandalism',
        'property_damage',
        'security_investigation',
        'law_enforcement_investigation',
        'legal_evidence',
        'others',
    ];

    public const DOCUMENTS = [
        'valid_id',
        'spa',
        'request_letter',
        'court_order_subpoena',
        'police_blotter',
        'proof_of_investigation',
    ];

    protected $fillable = [
        'control_number',
        'status',
        'date_of_request',
        'requester_name',
        'requester_address',
        'requester_contact_number',
        'date_of_incident',
        'footage_time_from',
        'footage_time_to',
        'camera_location',
        'requester_capacity',
        'requester_capacity_other',
        'purpose',
        'reasons',
        'reason_others',
        'description',
        'documents_presented',
        'denial_reason',
        'extracted_at',
        'camera_name',
        'nvr_number',
        'file_names',
        'extracted_by',
        'released_by',
        'received_by',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected $casts = [
        'date_of_request' => 'date:Y-m-d',
        'date_of_incident' => 'date:Y-m-d',
        'reasons' => 'array',
        'documents_presented' => 'array',
        'recommended_at' => 'datetime',
        'approved_at' => 'datetime',
        'denied_at' => 'datetime',
        'released_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'extracted_at' => 'datetime:Y-m-d H:i',
    ];

    protected static function booted(): void
    {
        static::saving(function (CctvFootageRequest $request) {
            $column = self::STATUS_TIMESTAMPS[$request->status] ?? null;

            if ($column && $request->isDirty('status') && ! $request->{$column}) {
                $request->{$column} = now();
            }
        });
    }

    /** @return list<string> */
    public function nextStatuses(): array
    {
        return self::TRANSITIONS[$this->status] ?? [];
    }

    public function canTransitionTo(string $status): bool
    {
        return $status === $this->status || in_array($status, $this->nextStatuses(), true);
    }

    /** `date_of_incident` keeps the earliest incident date, for sorting. */
    protected function syncScheduleColumns(): void
    {
        $this->date_of_incident = $this->scheduleStart();
    }
}
