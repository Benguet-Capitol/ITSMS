<?php

namespace App\Models;

use App\Models\Concerns\HasYearlyControlNumber;
use Illuminate\Database\Eloquent\Model;

/**
 * Request for New DTS User / Update of User Details. There is intentionally
 * no password attribute -- see the create_dts_user_requests migration.
 */
class DtsUserRequest extends Model
{
    use HasYearlyControlNumber;

    /** Same lifecycle as the other request modules -- the form has no approval chain. */
    public const STATUSES = ['pending', 'in_progress', 'completed', 'on_hold', 'cancelled'];

    public const REQUEST_TYPES = [
        'new' => 'New DTS User',
        'update' => 'Update of User Details',
    ];

    protected $fillable = [
        'control_number',
        'status',
        'date_of_request',
        'request_type',
        'update_details',
        'full_name',
        'department',
        'position',
        'dts_username',
        'requested_by',
        'assigned_personnel',
        'created_by',
        'date_created',
        'feedback_rating',
        'feedback_name',
        'feedback_date',
    ];

    protected $attributes = [
        'status' => 'pending',
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
