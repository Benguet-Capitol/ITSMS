<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DesignRequestResource extends JsonResource
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
            'date_needed' => $this->date_needed?->format('Y-m-d'),

            'items' => $this->items ?? [],
            'item_summaries' => $this->itemSummaries(),
            'file_formats' => $this->file_formats ?? [],
            'file_format_other' => $this->file_format_other,
            'media_provided' => $this->media_provided ?? [],

            'requester_name' => $this->requester_name,
            'requester_position' => $this->requester_position,
            'requester_email' => $this->requester_email,

            'assigned_personnel' => $this->assigned_personnel,
            'date_received' => $this->date_received?->format('Y-m-d'),
            'date_released' => $this->date_released?->format('Y-m-d'),
            'received_by' => $this->received_by,
            'remarks' => $this->remarks,

            'feedback_rating' => $this->feedback_rating,
            'feedback_name' => $this->feedback_name,
            'feedback_date' => $this->feedback_date?->format('Y-m-d'),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
