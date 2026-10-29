<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InspectorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'position' => $this->position,
            'office' => $this->office,
            ...$this->officeResourceFields(),
            'designation' => $this->designation(),
            'is_active' => $this->is_active,
            'inspection_requests_count' => $this->whenCounted('inspectionRequests'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
