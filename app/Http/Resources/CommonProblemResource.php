<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommonProblemResource extends JsonResource
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
          'item_type' => ItemTypeResource::make($this->whenLoaded('itemType')),
          'code' => $this->code,
          'general_term' => $this->general_term,
          'information' => $this->information,
          'created_at' => $this->created_at,
          'updated_at' => $this->updated_at,
        ];
    }
}
