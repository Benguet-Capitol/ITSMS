<?php

namespace App\Models;

use App\Models\Concerns\HasDateSchedule;
use App\Models\Concerns\HasYearlyControlNumber;
use Illuminate\Database\Eloquent\Model;

class DocumentationRequest extends Model
{
    use HasDateSchedule, HasYearlyControlNumber;

    /** Same lifecycle as Other IT Service requests -- the form has no approval chain. */
    public const STATUSES = ['pending', 'in_progress', 'completed', 'on_hold', 'cancelled'];

    /** Service checkbox column => label printed on form ICT-11. */
    public const SERVICES = [
        'service_photo' => 'Photo Documentation',
        'service_video' => 'Video Documentation',
        'service_live_feed' => 'Live Feed',
    ];

    protected $fillable = [
        'control_number',
        'status',
        'date_of_request',
        'department_office',
        'requestor_name',
        'service_photo',
        'service_video',
        'service_live_feed',
        'program_activity_details',
        'documentation_date',
        'documentation_end_date',
        'specific_shots',
        'additional_notes',
        'assigned_personnel',
        'date_received',
        'action_taken',
        'feedback_rating',
        'feedback_name',
        'feedback_date',
    ];

    protected $attributes = [
        'status' => 'pending',
        'service_photo' => false,
        'service_video' => false,
        'service_live_feed' => false,
    ];

    protected $casts = [
        'date_of_request' => 'date:Y-m-d',
        'documentation_date' => 'date:Y-m-d',
        'documentation_end_date' => 'date:Y-m-d',
        'date_received' => 'date:Y-m-d',
        'feedback_date' => 'date:Y-m-d',
        'service_photo' => 'boolean',
        'service_video' => 'boolean',
        'service_live_feed' => 'boolean',
        'feedback_rating' => 'integer',
    ];

    /** @return list<string> labels of the services ticked on this request */
    public function serviceLabels(): array
    {
        return array_values(array_filter(
            self::SERVICES,
            fn (string $label, string $column) => (bool) $this->{$column},
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    /** First and last scheduled day; the end stays null for a single day. */
    protected function syncScheduleColumns(): void
    {
        $start = $this->scheduleStart();
        $end = $this->scheduleEnd();

        $this->documentation_date = $start;
        $this->documentation_end_date = $end !== $start ? $end : null;
    }
}
