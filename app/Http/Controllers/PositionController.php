<?php

namespace App\Http\Controllers;

use App\Models\Position;
use App\Http\Resources\PositionResource;
use Illuminate\Http\Request;
use App\Http\Requests\StorePositionRequest;
use App\Http\Requests\UpdatePositionRequest;
use Illuminate\Support\Facades\Gate;

class PositionController extends Controller
{
    public function index(Request $request) {
      // Gate::authorize('position_index');

      $query = Position::query();

      if($request->has('search')) {
        $search = $request->search;
        $query->where(function ($q) use($search) {
          $q->where('name', 'LIKE', "%{$search}%")
          ->orWhere('abbreviation', 'LIKE', "%{$search}%");
        });
      }

      // Sorting (default to ID)
      if ($request->has('sort')) {
        $order = $request->input('order', 'asc');
        $query->orderBy($request->sort, $order);
      }

      // Paginate with customizable per-page count
      $positions = $query->paginate($request->input('per_page', 5))->appends($request->query());

      return response()->json([
          'data' => PositionResource::collection($positions),
          'meta' => [
              'total' => $positions->total(),
              'per_page' => $positions->perPage(),
              'current_page' => $positions->currentPage(),
              'last_page' => $positions->lastPage(),
          ]
      ]);

    }

    public function store(StorePositionRequest $request) {
      // Gate::authorize('department_store');
      
      $data = $request->validated();

      $position = Position::create($data);

      return new PositionResource($position);
    }

    public function update(UpdatePositionRequest $request, Position $position) {
      // Gate::authorize('department_update');

      $data = $request->validated();

      $position->update($data);

      return new PositionResource($position);
    }

    public function destroy(Position $position) {
      // Gate::authorize('department_destroy');

      $position->delete();
      
      return new PositionResource($position);
    }

    public function select() {
        $positions = Position::all();

        return response()->json([
          'data' => PositionResource::collection($positions)
        ]);
    }
}
