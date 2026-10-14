<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentationRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'control_number' => $this->control_number,
            'status' => $this->status,

            'date_of_request' => $this->date_of_request?->format('Y-m-d'),
            'department_office' => $this->department_office,
            'requestor_name' => $this->requestor_name,

            'service_photo' => $this->service_photo,
            'service_video' => $this->service_video,
            'service_live_feed' => $this->service_live_feed,
            'services' => $this->serviceLabels(),

            'program_activity_details' => $this->program_activity_details,
            'schedule' => $this->schedule ?? [],
            'schedule_text' => $this->scheduleText(),
            'documentation_date' => $this->documentation_date?->format('Y-m-d'),
            'documentation_end_date' => $this->documentation_end_date?->format('Y-m-d'),
            'specific_shots' => $this->specific_shots,
            'additional_notes' => $this->additional_notes,

            'assigned_personnel' => $this->assigned_personnel,
            'date_received' => $this->date_received?->format('Y-m-d'),
            'action_taken' => $this->action_taken,

            'feedback_rating' => $this->feedback_rating,
            'feedback_name' => $this->feedback_name,
            'feedback_date' => $this->feedback_date?->format('Y-m-d'),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
