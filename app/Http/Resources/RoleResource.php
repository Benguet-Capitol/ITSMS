<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        $role = $this?->loadMissing('permissions');

        $permissions = [];

        if($role) {
          foreach($role->permissions as $permission) {
            $permissions[] = [
              'id' => $permission->id,
              'title' => $permission->title,
            ];
          }
        }

        return [
          'id' => $this->id,
          'title' => $this->title,
          'created_at' => $this->created_at,
          'updated_at' => $this->updated_at,
          'permissions' => $permissions,
        ];
    }
}
