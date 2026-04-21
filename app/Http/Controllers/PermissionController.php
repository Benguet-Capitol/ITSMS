<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Permission;
use App\Http\Resources\PermissionResource;
use App\Http\Requests\StorePermissionRequest;
use App\Http\Requests\UpdatePermissionRequest;
use Illuminate\Support\Facades\Gate;

class PermissionController extends Controller
{
    public function index(Request $request) {
      // Gate::authorize('permission_index');

      $query = Permission::query();

      if($request->has('search')) {
        $search = $request->search;
        $query->where(function ($q) use($search) {
          $q->where('title', 'LIKE', "%{$search}%");
        });
      }

      // Sorting (default to ID)
      if ($request->has('sort')) {
        $order = $request->input('order', 'asc');
        $query->orderBy($request->sort, $order);
      }

      // Paginate with customizable per-page count
      $permissions = $query->paginate($request->input('per_page', 5))->appends($request->query());

      return response()->json([
          'data' => PermissionResource::collection($permissions),
          'meta' => [
              'total' => $permissions->total(),
              'per_page' => $permissions->perPage(),
              'current_page' => $permissions->currentPage(),
              'last_page' => $permissions->lastPage(),
          ]
      ]);
    }

    public function store(StorePermissionRequest $request) {
      // Gate::authorize('permission_store');
      
      $data = $request->validated();

      $permission = Permission::create($data);

      return new PermissionResource($permission);
    }

    public function update(UpdatePermissionRequest $request, Permission $permission) {
      // Gate::authorize('permission_update');

      $data = $request->validated();

      $permission->update($data);

      return new PermissionResource($permission);
    }

    public function destroy(Permission $permission) {
      // Gate::authorize('permission_destroy');

      $permission->delete();
      
      return new PermissionResource($permission);
    }

    public function permissionAll() {
        $permissions = Permission::all();

        return response()->json([
          'data' => PermissionResource::collection($permissions)
        ]);
    }
}
