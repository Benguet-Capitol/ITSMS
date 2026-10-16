<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Models\Concerns\HasDateSchedule;
use App\Models\Concerns\HasRequesterOffice;
use App\Models\Concerns\HasRequestWorkflow;
use App\Models\Concerns\HasYearlyControlNumber;
use Illuminate\Database\Eloquent\Model;

/**
 * Status follows the shared request workflow (App\Enums\RequestStatus) and
 * is set only by RequestWorkflowController. The control number is
 * server-assigned (YYYY-NNNN, restarting each year) like the other request
 * types; numbers typed in before that (e.g. "2026-07-45") are kept.
 */
class OtherItServiceRequest extends Model
{
    use HasDateSchedule, HasRequesterOffice, HasRequestWorkflow, HasYearlyControlNumber;

    /** Who gives the requestor's feedback (HasRequestWorkflow). */
    public const REQUESTER_FIELD = 'requestor_name';

    /** Department/Office text column (HasRequesterOffice). */
    public const OFFICE_FIELD = 'department_office';

    public const ALLOWS_OTHER_AGENCY = true;

    protected $fillable = [
        'control_number',
        'date_of_request',
        'department_office',
        'requestor_name',

        'service_printing',
        'service_information_material',
        'service_program_paper',
        'service_brochure',
        'service_iec_material',
        'service_handbook',
        'service_certificates',
        'service_others',
        'service_qty',
        'service_laptop_tv_setup',
        'service_ledwall_eoc',
        'service_other_service',
        'service_others_specify',

        'program_activity_details',
        'activity_date_text',
        'activity_time',
        'activity_venue',

        'action_taken',

        'feedback_rating',
        'feedback_date',
    ];

    /**
     * Mirrors the column defaults so a just-created record returns false
     * (not null) for services that weren't ticked.
     */
    protected $attributes = [
        'status' => RequestStatus::Pending->value,
        'service_printing' => false,
        'service_information_material' => false,
        'service_program_paper' => false,
        'service_brochure' => false,
        'service_iec_material' => false,
        'service_handbook' => false,
        'service_certificates' => false,
        'service_others' => false,
        'service_laptop_tv_setup' => false,
        'service_ledwall_eoc' => false,
        'service_other_service' => false,
    ];

    protected $casts = [
        'date_of_request' => 'date',
        'feedback_date' => 'date',
        'service_printing' => 'boolean',
        'service_information_material' => 'boolean',
        'service_program_paper' => 'boolean',
        'service_brochure' => 'boolean',
        'service_iec_material' => 'boolean',
        'service_handbook' => 'boolean',
        'service_certificates' => 'boolean',
        'service_others' => 'boolean',
        'service_laptop_tv_setup' => 'boolean',
        'service_ledwall_eoc' => 'boolean',
        'service_other_service' => 'boolean',
    ];

    /**
     * `activity_date_text` (what the printout and older screens show) is
     * written from the schedule. Records from before the schedule existed
     * keep their free text until a schedule is entered for them; clearing a
     * schedule clears the text too.
     */
    protected function syncScheduleColumns(): void
    {
        if ($this->schedule) {
            $this->activity_date_text = $this->scheduleText();
        } elseif ($this->getOriginal('schedule')) {
            $this->activity_date_text = null;
        }
    }
}
