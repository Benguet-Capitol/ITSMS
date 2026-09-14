<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePermissionRequest;
use App\Http\Requests\UpdatePermissionRequest;
use App\Http\Resources\PermissionResource;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('permissions.view');

        $query = Permission::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%");
            });
        }

        // Sorting (default to ID) -- whitelisted against real, sortable
        // columns so an arbitrary `sort` query param can't be used to probe
        // schema/column names or order by something not meant to be exposed.
        $sortable = ['id', 'title', 'created_at', 'updated_at'];

        if ($request->filled('sort') && in_array($request->sort, $sortable, true)) {
            $order = $request->input('order') === 'desc' ? 'desc' : 'asc';
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
            ],
        ]);
    }

    public function store(StorePermissionRequest $request)
    {
        Gate::authorize('permissions.create');

        $data = $request->validated();

        $permission = Permission::create($data);

        return new PermissionResource($permission);
    }

    public function update(UpdatePermissionRequest $request, Permission $permission)
    {
        Gate::authorize('permissions.update');

        $data = $request->validated();

        $permission->update($data);

        return new PermissionResource($permission);
    }

    // permission_role cascades on permission_id, so deleting a permission
    // doesn't destroy anything -- it just unassigns it from every role that
    // has it (and so revokes that access for every user in those roles).
    // Not destructive enough to block, but surprising enough that the
    // frontend shows this count before letting the user confirm delete.
    public function usage(Permission $permission)
    {
        Gate::authorize('permissions.delete');

        return response()->json([
            'roles_count' => $permission->roles()->count(),
        ]);
    }

    public function destroy(Permission $permission)
    {
        Gate::authorize('permissions.delete');

        $permission->delete();

        return new PermissionResource($permission);
    }

    public function permissionAll()
    {
        Gate::authorize('permissions.view');
        $permissions = Permission::all();

        return response()->json([
            'data' => PermissionResource::collection($permissions),
        ]);
    }
}
