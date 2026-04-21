<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryInternalComponentResource extends JsonResource
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
          'inventory' => InventoryResource::make($this->whenLoaded('inventory')),
          'brand_model' => BrandModelResource::make($this->whenLoaded('brand_model')),
          'specific_serial_number' => $this->specific_serial_number,
          'slot' => $this->slot,
          'quantity' => $this->quantity,
          'notes' => $this->notes,
          
          'created_at' => $this->created_at,
          'updated_at' => $this->updated_at,
        ];
    }
}
