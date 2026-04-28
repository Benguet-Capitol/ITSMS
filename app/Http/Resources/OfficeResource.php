<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfficeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this['id'] ?? null,
            'office_code' => $this['office_code'] ?? null,
            'office_desc' => $this['office_desc'] ?? null,
            'created_at' => $this['created_at'] ?? null,
            'updated_at' => $this['updated_at'] ?? null,

            // Handy aliases for UI
            'code' => $this['office_code'] ?? null,
            'name' => $this['office_desc'] ?? null,
            'label' => ($this['office_code'] ?? '') . ' - ' . ($this['office_desc'] ?? ''),
        ];
    }
}
