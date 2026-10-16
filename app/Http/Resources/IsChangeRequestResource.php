<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\HasWorkflowFields;
use App\Models\IsChangeRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IsChangeRequestResource extends JsonResource
{
    use HasWorkflowFields;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'control_number' => $this->control_number,
            ...$this->workflowFields($request),
            'stage' => $this->stage,
            'stage_label' => $this->stageLabel(),

            'date_of_request' => $this->date_of_request?->format('Y-m-d'),
            'department_office' => $this->department_office,
            ...$this->officeResourceFields(),
            'information_system' => $this->information_system,

            'request_type' => $this->request_type,
            'request_type_other' => $this->request_type_other,
            'request_type_label' => $this->requestTypeLabel(),
            'modules_affected' => $this->modules_affected,
            'details' => $this->details,
            'attachments_description' => $this->attachments_description,

            'priority' => $this->priority,
            'priority_label' => IsChangeRequest::PRIORITIES[$this->priority] ?? $this->priority,
            'date_required' => $this->date_required?->format('Y-m-d'),

            'requested_by' => $this->requested_by,

            'reviewed_by' => $this->reviewed_by,
            'reviewed_date' => $this->reviewed_date?->format('Y-m-d'),

            'testing_schedule' => $this->testing_schedule,
            'deployed_by' => $this->deployed_by,
            'approved_by' => $this->approved_by,
            'approved_date' => $this->approved_date?->format('Y-m-d'),
            'remarks' => $this->remarks,

            'feedback_rating' => $this->feedback_rating,
            'feedback_name' => $this->feedback_name,
            'feedback_date' => $this->feedback_date?->format('Y-m-d'),

            'accepted_by' => $this->accepted_by,
            'accepted_date' => $this->accepted_date?->format('Y-m-d'),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
