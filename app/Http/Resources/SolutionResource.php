<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\ProfileResource;

class SolutionResource extends JsonResource
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
          'description' => $this->description,
          'author' => ProfileResource::make($this->whenLoaded('author')),
          'title' => $this->title,
          'error_code' => $this->error_code,
          'reference_url' => $this->reference_url,
          'description_updated_at' => $this->description_updated_at,
          'created_at' => $this->created_at,
          'updated_at' => $this->updated_at,
        ];
    }
}
