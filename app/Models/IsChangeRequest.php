<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Models\Concerns\HasRequesterOffice;
use App\Models\Concerns\HasRequestWorkflow;
use App\Models\Concerns\HasYearlyControlNumber;
use Illuminate\Database\Eloquent\Model;

/**
 * Request for Change or Modification of Information System. The control
 * number is the form's "Change Request No.".
 *
 * Status follows the shared request workflow (App\Enums\RequestStatus),
 * set only by RequestWorkflowController. The development step is a
 * separate `stage` (see STAGES).
 */
class IsChangeRequest extends Model
{
    use HasRequesterOffice, HasRequestWorkflow, HasYearlyControlNumber;

    /** Who gives the requestor's feedback (HasRequestWorkflow). */
    public const REQUESTER_FIELD = 'requested_by';

    /** Department/Office text column (HasRequesterOffice). */
    public const OFFICE_FIELD = 'department_office';

    public const ALLOWS_OTHER_AGENCY = false;

    /**
     * Development steps matching the form's review, development and
     * end-user testing sections. Any step may be set directly -- the form
     * defines no enforced order -- but only while the request is in
     * progress (UpdateIsChangeRequest).
     */
    public const STAGES = [
        'reviewed' => 'Reviewed',
        'in_development' => 'In Development',
        'testing' => 'For Testing',
    ];

    public const REQUEST_TYPES = [
        'new_requirement' => 'New Requirement',
        'change' => 'Change',
        'problem' => 'Problem/Issue',
        'others' => 'Others',
    ];

    public const PRIORITIES = [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'critical' => 'Critical',
    ];

    protected $fillable = [
        'control_number',
        'stage',
        'date_of_request',
        'department_office',
        'information_system',
        'request_type',
        'request_type_other',
        'modules_affected',
        'details',
        'attachments_description',
        'priority',
        'date_required',
        'requested_by',
        'reviewed_by',
        'reviewed_date',
        'testing_schedule',
        'deployed_by',
        'approved_by',
        'approved_date',
        'remarks',
        'feedback_rating',
        'feedback_date',
        'accepted_by',
        'accepted_date',
    ];

    protected $attributes = [
        'status' => RequestStatus::Pending->value,
    ];

    protected $casts = [
        'date_of_request' => 'date:Y-m-d',
        'date_required' => 'date:Y-m-d',
        'reviewed_date' => 'date:Y-m-d',
        'approved_date' => 'date:Y-m-d',
        'feedback_date' => 'date:Y-m-d',
        'accepted_date' => 'date:Y-m-d',
        'feedback_rating' => 'integer',
    ];

    public function stageLabel(): ?string
    {
        return $this->stage ? (self::STAGES[$this->stage] ?? $this->stage) : null;
    }

    /** "Others: <specification>" for the others type, the plain label otherwise. */
    public function requestTypeLabel(): string
    {
        $label = self::REQUEST_TYPES[$this->request_type] ?? $this->request_type;

        return $this->request_type === 'others' && $this->request_type_other
            ? "{$label}: {$this->request_type_other}"
            : $label;
    }
}
