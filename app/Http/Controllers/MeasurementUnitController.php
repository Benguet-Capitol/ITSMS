<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMeasurementUnitRequest;
use App\Http\Requests\UpdateMeasurementUnitRequest;
use App\Http\Resources\MeasurementUnitResource;
use App\Models\MeasurementUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MeasurementUnitController extends Controller
{
    public function index(Request $request) {
      Gate::authorize('measurement_units.view');

      $query = MeasurementUnit::query();

      if($request->has('search')) {
        $search = $request->search;
        $query->where(function ($q) use($search) {
          $q->where('name', 'LIKE', "%{$search}%")
          ->orWhere('abbreviation', 'LIKE', "%{$search}%")
          ->orWhere('description', 'LIKE', "%{$search}%");
        });
      }

      // Sorting (default to ID)
      if ($request->has('sort')) {
        $order = $request->input('order', 'asc');
        $query->orderBy($request->sort, $order);
      }

      // Paginate with customizable per-page count
      $items = $query->paginate($request->input('per_page', 5))->appends($request->query());

      return response()->json([
          'data' => MeasurementUnitResource::collection($items),
          'meta' => [
              'total' => $items->total(),
              'per_page' => $items->perPage(),
              'current_page' => $items->currentPage(),
              'last_page' => $items->lastPage(),
          ]
      ]);
    }

    public function store(StoreMeasurementUnitRequest $request) {
      Gate::authorize('measurement_units.create');
      
      $data = $request->validated();

      $measurementUnit = MeasurementUnit::create($data);

      return new MeasurementUnitResource($measurementUnit);
    }

    public function update(UpdateMeasurementUnitRequest $request, MeasurementUnit $measurementUnit) {
      Gate::authorize('measurement_units.update');

      $data = $request->validated();

      $measurementUnit->update($data);

      return new MeasurementUnitResource($measurementUnit);
    }

    public function destroy(MeasurementUnit $measurementUnit) {
      Gate::authorize('measurement_units.delete');

      $measurementUnit->delete();
      
      return new MeasurementUnitResource($measurementUnit);
    }

    public function select() {
      Gate::authorize('measurement_units.view');
      
      $measurementUnits = MeasurementUnit::all();

      return response()->json([
        'data' => MeasurementUnitResource::collection($measurementUnits)
      ]);
    }
}
