<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->loadMissing([
            'roles.permissions',
            'profile.profileOffices',
            'profile.agencies',
        ]);

        $permissions = [];
        $roles = [];
        $agenciesAssigned = [];
        $agenciesAssignedIds = [];
        $officesAgenciesAssigned = [];

        foreach ($this->roles as $role) {
            $roles[] = [
                'id' => $role->id,
                'title' => $role->title,
            ];

            foreach ($role->permissions as $permission) {
                $permissions[$permission->title] = true;
            }
        }

        if ($this->profile) {
            foreach ($this->profile->agencies as $agency) {
                $agencyData = [
                    'id' => $agency->id,
                    'abbreviation' => $agency->abbreviation,
                ];

                $agenciesAssigned[] = $agencyData;
                $officesAgenciesAssigned[] = $agencyData;
                $agenciesAssignedIds[] = $agency->id;
            }
        }

        $officesAssigned = $this->profile?->offices_assigned ?? [];
        $officesAssignedIds = $this->profile?->office_ids ?? [];

        foreach ($officesAssigned as $office) {
            $officesAgenciesAssigned[] = [
                'id' => $office['id'],
                'abbreviation' => $office['abbreviation']
                    ?? $office['office_code']
                    ?? null,
            ];
        }

        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'profile' => new ProfileResource(
                $this->whenLoaded('profile')
            ),
            'created_at' => $this->created_at,
            'roles' => $roles,
            'permissions' => $permissions,
            'offices_assigned' => $officesAssigned,
            'agencies_assigned' => $agenciesAssigned,
            'offices_assigned_ids' => $officesAssignedIds,
            'agencies_assigned_ids' => array_values(
                array_unique($agenciesAssignedIds)
            ),
            'offices_agencies_assigned' => $officesAgenciesAssigned,
        ];
    }
}
