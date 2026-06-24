<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OtherItServiceRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'control_number' => $this->control_number,
            'status'         => $this->status,

            'date_of_request'   => $this->date_of_request,
            'department_office' => $this->department_office,
            'requestor_name'    => $this->requestor_name,

            'service_printing'             => $this->service_printing,
            'service_information_material' => $this->service_information_material,
            'service_program_paper'        => $this->service_program_paper,
            'service_brochure'             => $this->service_brochure,
            'service_iec_material'         => $this->service_iec_material,
            'service_handbook'             => $this->service_handbook,
            'service_certificates'         => $this->service_certificates,
            'service_others'               => $this->service_others,
            'service_qty'                  => $this->service_qty,
            'service_laptop_tv_setup'      => $this->service_laptop_tv_setup,
            'service_others_specify'       => $this->service_others_specify,

            'program_activity_details' => $this->program_activity_details,
            'activity_date_text'       => $this->activity_date_text,
            'activity_time'            => $this->activity_time,

            'assigned_personnel' => $this->assigned_personnel,
            'date_received'      => $this->date_received,
            'action_taken'       => $this->action_taken,

            'feedback_rating' => $this->feedback_rating,
            'feedback_name'   => $this->feedback_name,
            'feedback_date'   => $this->feedback_date,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
