<?php

namespace App\Models;

use App\Models\Concerns\HasYearlyControlNumber;
use Illuminate\Database\Eloquent\Model;

/**
 * Request for Change or Modification of Information System. The control
 * number is the form's "Change Request No.".
 */
class IsChangeRequest extends Model
{
    use HasYearlyControlNumber;

    /**
     * Software-change lifecycle matching the form's review, development,
     * end-user testing and sign-off sections. Any status may be set
     * directly -- the form defines no enforced order.
     */
    public const STATUSES = [
        'pending' => 'Pending',
        'reviewed' => 'Reviewed',
        'in_development' => 'In Development',
        'testing' => 'For Testing',
        'completed' => 'Completed',
        'on_hold' => 'On Hold',
        'cancelled' => 'Cancelled',
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
        'status',
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
        'programmer_name',
        'date_received',
        'testing_schedule',
        'deployed_by',
        'approved_by',
        'approved_date',
        'remarks',
        'feedback_rating',
        'feedback_name',
        'feedback_date',
        'accepted_by',
        'accepted_date',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected $casts = [
        'date_of_request' => 'date:Y-m-d',
        'date_required' => 'date:Y-m-d',
        'reviewed_date' => 'date:Y-m-d',
        'date_received' => 'date:Y-m-d',
        'approved_date' => 'date:Y-m-d',
        'feedback_date' => 'date:Y-m-d',
        'accepted_date' => 'date:Y-m-d',
        'feedback_rating' => 'integer',
    ];

    /** "Others: <specification>" for the others type, the plain label otherwise. */
    public function requestTypeLabel(): string
    {
        $label = self::REQUEST_TYPES[$this->request_type] ?? $this->request_type;

        return $this->request_type === 'others' && $this->request_type_other
            ? "{$label}: {$this->request_type_other}"
            : $label;
    }
}
