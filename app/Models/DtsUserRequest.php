<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Models\Concerns\HasRequesterOffice;
use App\Models\Concerns\HasRequestWorkflow;
use App\Models\Concerns\HasYearlyControlNumber;
use Illuminate\Database\Eloquent\Model;

/**
 * Request for New DTS User / Update of User Details. There is intentionally
 * no password attribute -- see the create_dts_user_requests migration.
 *
 * Status follows the shared request workflow (App\Enums\RequestStatus) and
 * is set only by RequestWorkflowController -- the form has no approval
 * chain of its own.
 */
class DtsUserRequest extends Model
{
    use HasRequesterOffice, HasRequestWorkflow, HasYearlyControlNumber;

    /** Who gives the requestor's feedback (HasRequestWorkflow). */
    public const REQUESTER_FIELD = 'requested_by';

    /** Department/Office text column (HasRequesterOffice). */
    public const OFFICE_FIELD = 'department';

    public const ALLOWS_OTHER_AGENCY = true;

    public const REQUEST_TYPES = [
        'new' => 'New DTS User',
        'update' => 'Update of User Details',
    ];

    protected $fillable = [
        'control_number',
        'date_of_request',
        'request_type',
        'update_details',
        'full_name',
        'department',
        'position',
        'dts_username',
        'requested_by',
        'created_by',
        'date_created',
        'feedback_rating',
        'feedback_date',
    ];

    protected $attributes = [
        'status' => RequestStatus::Pending->value,
    ];

    protected $casts = [
        'date_of_request' => 'date:Y-m-d',
        'date_created' => 'date:Y-m-d',
        'feedback_date' => 'date:Y-m-d',
        'feedback_rating' => 'integer',
    ];

    public function requestTypeLabel(): string
    {
        return self::REQUEST_TYPES[$this->request_type] ?? $this->request_type;
    }
}
