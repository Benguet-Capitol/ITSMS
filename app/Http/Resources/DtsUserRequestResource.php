<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\HasWorkflowFields;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DtsUserRequestResource extends JsonResource
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

            'date_of_request' => $this->date_of_request?->format('Y-m-d'),
            'request_type' => $this->request_type,
            'request_type_label' => $this->requestTypeLabel(),
            'update_details' => $this->update_details,

            'full_name' => $this->full_name,
            'department' => $this->department,
            ...$this->officeResourceFields(),
            'position' => $this->position,
            'dts_username' => $this->dts_username,

            'requested_by' => $this->requested_by,

            'created_by' => $this->created_by,
            'date_created' => $this->date_created?->format('Y-m-d'),

            'feedback_rating' => $this->feedback_rating,
            'feedback_name' => $this->feedback_name,
            'feedback_date' => $this->feedback_date?->format('Y-m-d'),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
