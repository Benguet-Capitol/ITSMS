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

class UserController extends Controller
{
    public function index(Request $request) {
      Gate::authorize('user_index');

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
      Gate::authorize('user_store');
      
      $data = $request->validated();
      $data['img_path'] = null;
      
      if($request->hasFile('photo_id')) {
        $path = $request->file('photo_id')->store('images/users/personnel', 'public');
        $data['img_path'] = $path;
      }
      
      $user = User::create([
        'username' => $data['username'],
        'email' => $data['email'],
        'password' => Hash::make($data['password']),
      ]);
      
      if($user){
        $user->roles()->syncWithoutDetaching([$data['role']]);
        Profile::create([
          'user_id' => $user->id,
          'display_name' => $data['display_name'],
          'name' => $data['name'],
          'gender' => $data['gender'],
          'designation' => $data['designation'],
          'engagement' => 'ready',
          'img_path' => $data['img_path'],
        ]);
          if (!empty($data['offices_assigned_ids'])) {
              $user->profile->departments()->sync($data['offices_assigned_ids']);
          }
          
          if (!empty($data['agencies_assigned_ids'])) {
              $user->profile->agencies()->sync($data['agencies_assigned_ids']);
          }
      }
      
      return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user) {
      // Gate::authorize('user_update');

      $data = $request->validated();

      $user_data = [];
      $profile_data = [];
      $changedData = [];
      foreach ($data as $key => $value) {
        if ($user->$key !== $value) {
          $changedData[$key] = $value;
          if($key === 'email' || $key === 'username' || $key === 'role') {
            $user_data[$key] = $value;
          } else if($key === 'display_name' || $key === 'name' || $key === 'gender' || $key === 'designation') {
            $profile_data[$key] = $value;
          }
        }
      }

      if (!empty($changedData)) {
        if($request->hasFile('photo_id')) {
          $storage_public = Storage::disk('public');
          if ($user->profile->img_path && $storage_public->exists($user->profile->img_path)) {
            $storage_public->delete($user->profile->img_path);
          }

          $path = $request->file('photo_id')->store('images/users/personnel', 'public');
          $profile_data['img_path'] = $path;
        }

        $user->update($user_data);
      
        if($user){
          $user->roles()->sync([$data['role']]);

          Profile::where('user_id', $user->id)->update($profile_data);
          if (!empty($data['offices_assigned_ids'])) {
              $user->profile->departments()->sync($data['offices_assigned_ids']);
          }
          
          if (!empty($data['agencies_assigned_ids'])) {
              $user->profile->agencies()->sync($data['agencies_assigned_ids']);
          }
        }
      }

      return new UserResource($user);
    }

    public function destroy(User $user) {
      // Gate::authorize('user_destroy');

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
}
