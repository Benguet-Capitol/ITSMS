<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/** A profile on a request, plus how they got there (request_personnel pivot). */
class RequestPersonnelResource extends ProfileResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            // null = accepted it themselves; otherwise the assigning admin.
            'assigned_by_profile_id' => $this->pivot?->assigned_by_profile_id,
            'joined_at' => $this->pivot?->created_at,
        ];
    }
}
