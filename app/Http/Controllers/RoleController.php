<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('roles.view');

        $query = Role::query();

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
        $roles = $query->paginate($request->input('per_page', 5))->appends($request->query());

        return response()->json([
            'data' => RoleResource::collection($roles),
            'meta' => [
                'total' => $roles->total(),
                'per_page' => $roles->perPage(),
                'current_page' => $roles->currentPage(),
                'last_page' => $roles->lastPage(),
            ],
        ]);
    }

    public function store(StoreRoleRequest $request)
    {
        Gate::authorize('roles.create');

        $data = $request->validated();

        $role = Role::create($data);
        $role->permissions()->sync($data['permission_ids']);

        return new RoleResource($role);
    }

    public function update(UpdateRoleRequest $request, Role $role)
    {
        Gate::authorize('roles.update');

        $data = $request->validated();

        $role->update([
            'title' => $data['title'],
        ]);

        $currentPermissionIds = $role->permissions()->pluck('id')->sort()->values();
        $newPermissionIds = collect($data['permission_ids'] ?? [])->sort()->values();

        if ($currentPermissionIds->toJson() !== $newPermissionIds->toJson()) {
            $role->permissions()->sync($newPermissionIds);
        }

        return new RoleResource($role);
    }

    // role_user.role_id cascades on role_id, so deleting a role doesn't
    // destroy anything -- it just unassigns it (and every permission it
    // carries) from every user who has it. Not destructive enough to
    // block, but surprising enough that the frontend shows this count
    // before letting the user confirm delete.
    public function usage(Role $role)
    {
        Gate::authorize('roles.delete');

        return response()->json([
            'users_count' => $role->users()->count(),
        ]);
    }

    public function destroy(Role $role)
    {
        Gate::authorize('roles.delete');

        $role->delete();

        return new RoleResource($role);
    }

    public function select()
    {
        Gate::authorize('roles.select');
        // ?? Member no active membership
        $roles = Role::all();
        // $members = Member::with('memberships')->whereDoesntHave('memberships', function ($query) {
        //     $query->where('status', true);
        // })->get();

        return response()->json([
            'data' => RoleResource::collection($roles),
        ]);
    }
}
