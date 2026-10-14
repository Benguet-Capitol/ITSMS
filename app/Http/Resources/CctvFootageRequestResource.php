<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CctvFootageRequestResource extends JsonResource
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
            'next_statuses' => $this->nextStatuses(),

            'date_of_request' => $this->date_of_request?->format('Y-m-d'),
            'requester_name' => $this->requester_name,
            'requester_address' => $this->requester_address,
            'requester_contact_number' => $this->requester_contact_number,

            'schedule' => $this->schedule ?? [],
            'schedule_text' => $this->scheduleText(),
            'date_of_incident' => $this->date_of_incident?->format('Y-m-d'),
            'footage_time_from' => $this->footage_time_from ? substr($this->footage_time_from, 0, 5) : null,
            'footage_time_to' => $this->footage_time_to ? substr($this->footage_time_to, 0, 5) : null,
            'camera_location' => $this->camera_location,

            'requester_capacity' => $this->requester_capacity,
            'requester_capacity_other' => $this->requester_capacity_other,

            'purpose' => $this->purpose,
            'reasons' => $this->reasons ?? [],
            'reason_others' => $this->reason_others,
            'description' => $this->description,

            'documents_presented' => $this->documents_presented ?? [],

            'recommended_at' => $this->recommended_at,
            'approved_at' => $this->approved_at,
            'denied_at' => $this->denied_at,
            'denial_reason' => $this->denial_reason,

            'extracted_at' => $this->extracted_at?->format('Y-m-d H:i'),
            'camera_name' => $this->camera_name,
            'nvr_number' => $this->nvr_number,
            'file_names' => $this->file_names,
            'extracted_by' => $this->extracted_by,

            'released_by' => $this->released_by,
            'received_by' => $this->received_by,
            'released_at' => $this->released_at,
            'cancelled_at' => $this->cancelled_at,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
