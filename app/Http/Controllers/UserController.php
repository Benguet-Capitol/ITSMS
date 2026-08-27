<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Models\Profile;
use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\StoreUserRequest;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\UpdateUserRequest;
use App\Models\ProfileOffice;
use Carbon\Carbon;

class UserController extends Controller
{
    public function index(Request $request) {
      Gate::authorize('users.view');

      $query = User::query();

      // Search by name or email
      if($request->has('search')) {
        $search = $request->search;
        $query->where(function ($q) use($search) {
          $q->where('username', 'LIKE', "%{$search}%")
          ->orWhere('email', 'LIKE', "%{$search}%")
          ->orWhereHas('profile', function ($q4) use($search) {
                  $q4->where('display_name', 'like', "%$search%");
                });
        });
      }

       // Status filter (active/inactive)
      if ($request->has('status')) {
        $query->where('status', $request->status);
      }

      // Sorting (default to ID)
      if ($request->has('sort')) {
        $order = $request->input('order', 'asc');
        $query->orderBy($request->sort, $order);
      }

      // Paginate with customizable per-page count
      $users = $query->paginate($request->input('per_page', 5))->appends($request->query());

      return response()->json([
          'data' => UserResource::collection($users),
          'meta' => [
              'total' => $users->total(),
              'per_page' => $users->perPage(),
              'current_page' => $users->currentPage(),
              'last_page' => $users->lastPage(),
          ]
      ]);
    }

    public function store(StoreUserRequest $request) {
      Gate::authorize('users.create');

      $data = $request->validated();
      $data['img_path'] = null;

      if ($request->hasFile('photo_id')) {
          $path = $request->file('photo_id')->store('images/users/personnel', 'public');
          $data['img_path'] = $path;
      }

      $user = User::create([
          'username' => $data['username'],
          'email' => $data['email'],
          'password' => Hash::make($data['password']),
      ]);

      if ($user) {
          $user->roles()->syncWithoutDetaching([$data['role']]);

          $profile = Profile::create([
              'user_id' => $user->id,
              'display_name' => $data['display_name'],
              'name' => $data['name'],
              'gender' => $data['gender'],
              'designation' => $data['designation'],
              'engagement' => 'ready',
              'img_path' => $data['img_path'],
          ]);

          $this->syncProfileOffices($profile, $data['offices_assigned'] ?? null);
          $profile->agencies()->sync($data['agencies_assigned_ids'] ?? []);
      }

      return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user) {
      Gate::authorize('users.update');
      
      $data = $request->validated();

      $user_data = [];
      $profile_data = [];
      $changedData = [];

      foreach ($data as $key => $value) {
          if ($user->$key !== $value) {
              $changedData[$key] = $value;

              if ($key === 'email' || $key === 'username' || $key === 'role') {
                  $user_data[$key] = $value;
              } else if ($key === 'display_name' || $key === 'name' || $key === 'gender' || $key === 'designation') {
                  $profile_data[$key] = $value;
              }
          }
      }

      if (!empty($changedData)) {
          if ($request->hasFile('photo_id')) {
              $storage_public = Storage::disk('public');

              if ($user->profile->img_path && $storage_public->exists($user->profile->img_path)) {
                  $storage_public->delete($user->profile->img_path);
              }

              $path = $request->file('photo_id')->store('images/users/personnel', 'public');
              $profile_data['img_path'] = $path;
          }

          $user->update($user_data);

          if ($user) {
              $user->roles()->sync([$data['role']]);

              Profile::where('user_id', $user->id)->update($profile_data);

              $user->refresh();
              $this->syncProfileOffices($user->profile, $data['offices_assigned'] ?? null);
              $user->profile->agencies()->sync($data['agencies_assigned_ids'] ?? []);
          }
      }

      return new UserResource($user->fresh());
    }

    public function destroy(User $user) {
      Gate::authorize('users.delete');

      $storage_public = Storage::disk('public');
      if ($user->img_path && $storage_public->exists($user->img_path)) {
        $storage_public->delete($user->img_path);
      }

      $user->delete();
      
      return new UserResource($user);
    }

    // Assign role to a user
    public function assignRoleToUser(Request $request)
    {
        $user = User::findOrFail($request->user_id);
        $role = Role::findOrFail($request->role_id);

        $user->roles()->syncWithoutDetaching([$role->id]);

        return response()->json(['message' => 'Role assigned to user successfully']);
    }

    private function syncProfileOffices(Profile $profile, ?string $officesAssignedJson = null): void {
        $profile->profileOffices()->delete();

        if (blank($officesAssignedJson)) {
            return;
        }

        $offices = json_decode($officesAssignedJson, true);

        if (!is_array($offices) || empty($offices)) {
            return;
        }

        $rows = collect($offices)
            ->filter(fn ($office) => !empty($office['id']))
            ->unique('id')
            ->map(function ($office) use ($profile) {
                return [
                    'profile_id' => $profile->id,
                    'office_id' => (string) $office['id'],
                    'office_code' => $office['office_code'] ?? $office['abbreviation'] ?? null,
                    'office_desc' => $office['office_desc'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })
            ->values()
            ->all();

        if (!empty($rows)) {
            ProfileOffice::insert($rows);
        }
    }

    /**
     * Handle user heartbeat to update profile's last_seen_at timestamp.
     * This is called periodically by the frontend to indicate the user is still active.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function heartbeat(Request $request)
    {
        $user = $request->user();
        
        // Update the user's profile last_seen_at timestamp
        $user->profile->update([
            'last_seen_at' => now(),
        ]);

        // Return 204 No Content - minimal response, no body needed
        return response()->noContent();
    }

    /**
     * Get all users with their online/offline/busy status.
     * Includes profile data, roles, and departments for display.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function onlineList()
    {
        $users = User::with([
            'profile',
            'roles',
            'profile.departments',
        ])
            ->get()
            ->map(function ($user) {
                $profile = $user->profile;

                $lastSeenAt = $profile?->last_seen_at
                    ? Carbon::parse($profile->last_seen_at)
                    : null;

                // $isRecentlyActive = $lastSeenAt
                //     && $lastSeenAt->greaterThanOrEqualTo($cutoff);

                // $status = $isRecentlyActive
                //     ? ($profile->status ?: Profile::STATUS_ONLINE)
                //     : Profile::STATUS_OFFLINE;

                return [
                  'id' => $user->id,
                  'email' => $user->email,
                  'username' => $user->username,
                  'display_name' => $profile?->display_name,
                  'designation' => $profile?->designation,

                  // Use the profile value maintained by the heartbeat endpoints.
                  'status' => $profile?->status ?? Profile::STATUS_OFFLINE,

                  'status_text' => $profile?->status_text,
                  'last_seen_at' => $profile?->last_seen_at,

                  'last_seen_at_humanized' => $lastSeenAt
                      ? $lastSeenAt->diffForHumans()
                      : null,

                  'roles' => $user->roles->map(fn ($role) => [
                      'id' => $role->id,
                      'title' => $role->title,
                  ])->values(),

                  'departments' => $profile?->departments
                      ? $profile->departments->map(fn ($department) => [
                          'id' => $department->id,
                          'name' => $department->name,
                      ])->values()
                      : [],

                  'img_path' => $profile?->img_path
                      ? asset("storage/{$profile->img_path}")
                      : null,
              ];
            })
            ->sortBy(fn ($user) => $user['display_name'] ?? $user['email'])
            ->values();

        return response()->json($users);
    }
}
