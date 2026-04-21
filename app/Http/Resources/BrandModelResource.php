<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\BrandResource;
use App\Http\Resources\ItemTypeResource;

class BrandModelResource extends JsonResource
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
          'name' => $this->name,
          'specification' => $this->specification,
          'specifications_json' => $this->specifications_json,
          'option_attribute_description' => $this->name ? "{$this->item_type->type}, {$this->specification} {$this->name}, {$this->brand->name}" : "{$this->item_type->type}, {$this->specification}, {$this->brand->name}",
          'brand' => BrandResource::make($this->whenLoaded('brand')),
          'item_type' => ItemTypeResource::make($this->whenLoaded('item_type')),
          'image' => $this->image,
          'year_released' => $this->year_released,
          'status' => $this->status,
          'created_at' => $this->created_at,
          'updated_at' => $this->updated_at,
        ];
    }
}
