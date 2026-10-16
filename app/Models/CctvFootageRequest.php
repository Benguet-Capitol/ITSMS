<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Models\Concerns\HasDateSchedule;
use App\Models\Concerns\HasRequestWorkflow;
use App\Models\Concerns\HasYearlyControlNumber;
use Illuminate\Database\Eloquent\Model;

/**
 * The DPO's recommendation and the Provincial Administrator's approval are
 * signed on the paper form before a request is entered, so ITSMS has no
 * approval chain of its own: status follows the shared request workflow
 * (App\Enums\RequestStatus), set only by RequestWorkflowController. The IT
 * personnel section (extraction and release) must be filled before the
 * request is marked for completion.
 */
class CctvFootageRequest extends Model
{
    use HasDateSchedule, HasRequestWorkflow, HasYearlyControlNumber;

    /** Who gives the requestor's feedback (HasRequestWorkflow). */
    public const REQUESTER_FIELD = 'requester_name';

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
        'extracted_at',
        'camera_name',
        'nvr_number',
        'file_names',
        'extracted_by',
        'released_by',
        'received_by',
        'feedback_rating',
        'feedback_date',
    ];

    protected $attributes = [
        'status' => RequestStatus::Pending->value,
    ];

    protected $casts = [
        'date_of_request' => 'date:Y-m-d',
        'date_of_incident' => 'date:Y-m-d',
        'reasons' => 'array',
        'documents_presented' => 'array',
        'extracted_at' => 'datetime:Y-m-d H:i',
        'feedback_date' => 'date:Y-m-d',
        'feedback_rating' => 'integer',
    ];

    /**
     * The "Details to be completed by IT personnel" section -- what used to
     * be required to release footage -- is required before marking the
     * request for completion. File names only apply to a copy.
     */
    public function finishRequirements(): array
    {
        $required = [
            'extracted_at' => 'The date and time of playback/extraction is required before marking this for completion.',
            'camera_name' => 'The camera name is required before marking this for completion.',
            'extracted_by' => 'The IT personnel who extracted the footage is required before marking this for completion.',
            'released_by' => 'The name of the person who released the footage is required before marking this for completion.',
            'received_by' => 'The name of the person who viewed/received the footage is required before marking this for completion.',
        ];

        if ($this->purpose === 'copy') {
            $required['file_names'] = 'The file name/s are required before marking a copy for completion.';
        }

        return array_filter(
            $required,
            fn (string $message, string $field) => blank($this->{$field}),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /** `date_of_incident` keeps the earliest incident date, for sorting. */
    protected function syncScheduleColumns(): void
    {
        $this->date_of_incident = $this->scheduleStart();
    }
}
