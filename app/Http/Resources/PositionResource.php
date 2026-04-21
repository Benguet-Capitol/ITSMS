<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PositionResource extends JsonResource
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
          'abbreviation' => $this->abbreviation,
          'salary_grade' => $this->salary_grade,
          'created_at' => $this->created_at,
          'updated_at' => $this->updated_at,
        ];
    }
}
