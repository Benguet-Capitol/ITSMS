<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemTypeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
          'id' => $this->id,
          'type' => $this->type,
          'classification' => $this->classification,
          'purpose' => $this->purpose,
          'is_main_inventory' => $this->is_main_inventory,
          'is_component' => $this->is_component,
          'part_number' => $this->part_number,
          'status' => $this->status,
          'created_at' => $this->created_at,
          'updated_at' => $this->updated_at,
        ];
    }
}
