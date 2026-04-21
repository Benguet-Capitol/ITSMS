<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Department;
use App\Http\Resources\DepartmentResource;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use Illuminate\Support\Facades\Gate;

class DepartmentController extends Controller
{
    public function index(Request $request) {
      // Gate::authorize('department_index');

      $query = Department::query();

      if($request->has('search')) {
        $search = $request->search;
        $query->where(function ($q) use($search) {
          $q->where('name', 'LIKE', "%{$search}%")
          ->orWhere('full_name', 'LIKE', "%{$search}%")
          ->orWhere('abbreviation', 'LIKE', "%{$search}%");
        });
      }

      // Sorting (default to ID)
      if ($request->has('sort')) {
        $order = $request->input('order', 'asc');
        $query->orderBy($request->sort, $order);
      }

      // Paginate with customizable per-page count
      $departments = $query->paginate($request->input('per_page', 5))->appends($request->query());

      return response()->json([
          'data' => DepartmentResource::collection($departments),
          'meta' => [
              'total' => $departments->total(),
              'per_page' => $departments->perPage(),
              'current_page' => $departments->currentPage(),
              'last_page' => $departments->lastPage(),
          ]
      ]);

    }

    public function store(StoreDepartmentRequest $request) {
      // Gate::authorize('department_store');
      
      $data = $request->validated();

      $department = Department::create($data);

      return new DepartmentResource($department);
    }

    public function update(UpdateDepartmentRequest $request, Department $department) {
      // Gate::authorize('department_update');

      $data = $request->validated();

      $department->update($data);

      return new DepartmentResource($department);
    }

    public function destroy(Department $department) {
      // Gate::authorize('department_destroy');

      $department->delete();
      
      return new DepartmentResource($department);
    }

    public function select() {
        $departments = Department::all();

        return response()->json([
          'data' => DepartmentResource::collection($departments)
        ]);
    }

}
