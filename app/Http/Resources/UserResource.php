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

        $user_roles_permissions = $this?->loadMissing('roles.permissions');
        // $profile_departments = $this?->loadMissing('profile.departments');
        // $profile_agencies = $this?->loadMissing('profile.agencies');
        $permissions = [];
        $roles = [];
        $offices_assigned = [];
        $offices_assigned_ids = [];
        $agencies_assigned = [];
        $agencies_assigned_ids = [];
        $offices_agencies_assigned = [];

        if($user_roles_permissions) {
          foreach($user_roles_permissions->roles as $role) {
            $roles[] = [
              'id' => $role->id,
              'title' => $role->title,
            ];

            foreach($role->permissions as $singlePermission) {
              $permissions[] = $singlePermission->title;
            }
          }
        }

        // if($profile_departments) {
        //   foreach($profile_departments->profile->departments as $department) {
        //     $offices_assigned[] = [
        //       'id' => $department->id,
        //       'abbreviation' => $department->abbreviation,
        //     ];

        //     $offices_agencies_assigned[] = [
        //       'id' => $department->id,
        //       'abbreviation' => $department->abbreviation,
        //     ];

        //     $offices_assigned_ids[] = $department->id;
        //   }
        // }

        // if($profile_agencies) {
        //   foreach($profile_agencies->profile->agencies as $agency) {
        //     $agencies_assigned[] = [
        //       'id' => $agency->id,
        //       'abbreviation' => $agency->abbreviation,
        //     ];

        //     $offices_agencies_assigned[] = [
        //       'id' => $agency->id,
        //       'abbreviation' => $agency->abbreviation,
        //     ];

        //     $agencies_assigned_ids[] = $agency->id;
        //   }
        // }

        return [
          'id' => $this->id,
          'username' => $this->username,
          'email' => $this->email,
          'profile' => new ProfileResource($this->whenLoaded('profile')),
          // 'status' => $this->status ? 'Active' : 'Inactive',
          'created_at' => $this->created_at,
          'roles' => $roles,
          'permissions' => collect($permissions)->unique()->map(function($permission) {
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
