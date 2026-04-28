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
        $user_roles_permissions = $this?->loadMissing([
            'roles.permissions',
            'profile.profileOffices',
            'profile.agencies',
        ]);

        $permissions = [];
        $roles = [];
        $agencies_assigned = [];
        $agencies_assigned_ids = [];
        $offices_agencies_assigned = [];

        if ($user_roles_permissions) {
            foreach ($user_roles_permissions->roles as $role) {
                $roles[] = [
                    'id' => $role->id,
                    'title' => $role->title,
                ];

                foreach ($role->permissions as $singlePermission) {
                    $permissions[] = $singlePermission->title;
                }
            }

            if ($user_roles_permissions->profile) {
                foreach ($user_roles_permissions->profile->agencies as $agency) {
                    $agencies_assigned[] = [
                        'id' => $agency->id,
                        'abbreviation' => $agency->abbreviation,
                    ];

                    $offices_agencies_assigned[] = [
                        'id' => $agency->id,
                        'abbreviation' => $agency->abbreviation,
                    ];

                    $agencies_assigned_ids[] = $agency->id;
                }
            }
        }

        $offices_assigned = $this->profile?->offices_assigned ?? [];
        $offices_assigned_ids = $this->profile?->office_ids ?? [];

        foreach ($offices_assigned as $office) {
            $offices_agencies_assigned[] = [
                'id' => $office['id'],
                'abbreviation' => $office['abbreviation'] ?? $office['office_code'] ?? null,
            ];
        }

        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'profile' => new ProfileResource($this->whenLoaded('profile')),
            'created_at' => $this->created_at,
            'roles' => $roles,
            'permissions' => collect($permissions)->unique()->map(function ($permission) {
                return [
                    $permission => true
                ];
            })->collapse()->toArray(),
            'offices_assigned' => $offices_assigned,
            'agencies_assigned' => $agencies_assigned,
            'offices_assigned_ids' => $offices_assigned_ids,
            'agencies_assigned_ids' => $agencies_assigned_ids,
            'offices_agencies_assigned' => $offices_agencies_assigned,
        ];
    }
}
