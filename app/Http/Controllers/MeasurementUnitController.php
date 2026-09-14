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
    public function index(Request $request)
    {
        Gate::authorize('measurement_units.view');

        $query = MeasurementUnit::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('abbreviation', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // Sorting (default to ID) -- whitelisted against real, sortable
        // columns so an arbitrary `sort` query param can't be used to probe
        // schema/column names or order by something not meant to be exposed.
        $sortable = ['id', 'name', 'abbreviation', 'created_at', 'updated_at'];

        if ($request->filled('sort') && in_array($request->sort, $sortable, true)) {
            $order = $request->input('order') === 'desc' ? 'desc' : 'asc';
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
            ],
        ]);
    }

    public function store(StoreMeasurementUnitRequest $request)
    {
        Gate::authorize('measurement_units.create');

        $data = $request->validated();

        $measurementUnit = MeasurementUnit::create($data);

        return new MeasurementUnitResource($measurementUnit);
    }

    public function update(UpdateMeasurementUnitRequest $request, MeasurementUnit $measurementUnit)
    {
        Gate::authorize('measurement_units.update');

        $data = $request->validated();

        $measurementUnit->update($data);

        return new MeasurementUnitResource($measurementUnit);
    }

    public function destroy(MeasurementUnit $measurementUnit)
    {
        Gate::authorize('measurement_units.delete');

        // it_supplies.measurement_unit_id is cascadeOnDelete(), so deleting a
        // measurement unit still in use would silently wipe out every IT
        // supply record that references it -- block it instead.
        $itSuppliesCount = $measurementUnit->it_supplies()->count();

        if ($itSuppliesCount > 0) {
            abort(422, "Cannot delete \"{$measurementUnit->name}\": {$itSuppliesCount} IT supply record(s) are still linked to it. Reassign or delete those first.");
        }

        $measurementUnit->delete();

        return new MeasurementUnitResource($measurementUnit);
    }

    public function select()
    {
        Gate::authorize('measurement_units.select');

        $measurementUnits = MeasurementUnit::all();

        return response()->json([
            'data' => MeasurementUnitResource::collection($measurementUnits),
        ]);
    }
}
