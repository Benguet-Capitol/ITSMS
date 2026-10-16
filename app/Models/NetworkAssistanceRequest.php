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
 * is set only by RequestWorkflowController -- the form has no approval
 * chain of its own.
 */
class NetworkAssistanceRequest extends Model
{
    use HasDateSchedule, HasRequesterOffice, HasRequestWorkflow, HasYearlyControlNumber;

    /** Who gives the requestor's feedback (HasRequestWorkflow). */
    public const REQUESTER_FIELD = 'requestor_name';

    /** Department/Office text column (HasRequesterOffice). */
    public const OFFICE_FIELD = 'department_office';

    public const ALLOWS_OTHER_AGENCY = true;

    /** Service checkbox column => label printed on form ICT-15. */
    public const SERVICES = [
        'service_wifi_installation' => 'Wi-Fi Installation',
        'service_ethernet_wiring' => 'Ethernet Wiring Setup',
        'service_network_planning' => 'Network Planning/Management',
        'service_other_support' => 'Other Network IT Support',
    ];

    protected $fillable = [
        'control_number',
        'date_of_request',
        'department_office',
        'requestor_name',
        'service_wifi_installation',
        'service_ethernet_wiring',
        'service_network_planning',
        'service_other_support',
        'service_other_specify',
        'program_activity_details',
        'activity_date',
        'activity_end_date',
        'activity_time',
        'venue',
        'action_taken',
        'feedback_rating',
        'feedback_date',
    ];

    protected $attributes = [
        'status' => RequestStatus::Pending->value,
        'service_wifi_installation' => false,
        'service_ethernet_wiring' => false,
        'service_network_planning' => false,
        'service_other_support' => false,
    ];

    protected $casts = [
        'date_of_request' => 'date:Y-m-d',
        'activity_date' => 'date:Y-m-d',
        'activity_end_date' => 'date:Y-m-d',
        'feedback_date' => 'date:Y-m-d',
        'service_wifi_installation' => 'boolean',
        'service_ethernet_wiring' => 'boolean',
        'service_network_planning' => 'boolean',
        'service_other_support' => 'boolean',
        'feedback_rating' => 'integer',
    ];

    /**
     * Labels of the services ticked on this request; "Other" carries its
     * specification when one was given.
     *
     * @return list<string>
     */
    public function serviceLabels(): array
    {
        $labels = [];

        foreach (self::SERVICES as $column => $label) {
            if (! $this->{$column}) {
                continue;
            }

            $labels[] = $column === 'service_other_support' && $this->service_other_specify
                ? "{$label}: {$this->service_other_specify}"
                : $label;
        }

        return $labels;
    }

    /** "HH:MM" (the time column is stored as HH:MM:SS), or null. */
    public function activityTimeHm(): ?string
    {
        return $this->activity_time ? substr($this->activity_time, 0, 5) : null;
    }

    /** First and last scheduled day; the end stays null for a single day. */
    protected function syncScheduleColumns(): void
    {
        $start = $this->scheduleStart();
        $end = $this->scheduleEnd();

        $this->activity_date = $start;
        $this->activity_end_date = $end !== $start ? $end : null;
    }
}
