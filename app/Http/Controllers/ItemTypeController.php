<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreItemTypeRequest;
use App\Http\Requests\UpdateItemTypeRequest;
use App\Http\Resources\ItemTypeResource;
use App\Models\ItemType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ItemTypeController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('item_types.view');

        $query = ItemType::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('type', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('classification')) {
            $query->where('classification', $request->classification);
        }

        // Sorting (default to ID) -- whitelisted against real, sortable
        // columns so an arbitrary `sort` query param can't be used to probe
        // schema/column names or order by something not meant to be exposed.
        $sortable = ['id', 'type', 'classification', 'purpose', 'status', 'created_at', 'updated_at'];

        if ($request->filled('sort') && in_array($request->sort, $sortable, true)) {
            $order = $request->input('order') === 'desc' ? 'desc' : 'asc';
            $query->orderBy($request->sort, $order);
        }

        // Paginate with customizable per-page count
        $item_types = $query->paginate($request->input('per_page', 5))->appends($request->query());

        return response()->json([
            'data' => ItemTypeResource::collection($item_types),
            'meta' => [
                'total' => $item_types->total(),
                'per_page' => $item_types->perPage(),
                'current_page' => $item_types->currentPage(),
                'last_page' => $item_types->lastPage(),
            ],
        ]);
    }

    public function store(StoreItemTypeRequest $request)
    {
        Gate::authorize('item_types.create');

        $data = $request->validated();

        $item_type = ItemType::create($data);

        return new ItemTypeResource($item_type);
    }

    public function update(UpdateItemTypeRequest $request, ItemType $item_type)
    {
        Gate::authorize('item_types.update');

        $data = $request->validated();

        $item_type->update($data);

        return new ItemTypeResource($item_type);
    }

    // item_type_id on brand_models/inventories/tickets/common_problems is
    // nullOnDelete, not cascading -- deleting this item type doesn't
    // destroy those records, it just clears their reference to it. Not
    // destructive enough to block, but surprising enough to warn about
    // with real counts before confirming (same pattern as BrandModel).
    public function usage(ItemType $item_type)
    {
        Gate::authorize('item_types.delete');

        return response()->json([
            'brand_models_count' => $item_type->brand_models()->count(),
            'inventories_count' => $item_type->inventories()->count(),
            'tickets_count' => $item_type->tickets()->count(),
            'common_problems_count' => $item_type->common_problems()->count(),
        ]);
    }

    public function destroy(ItemType $item_type)
    {
        Gate::authorize('item_types.delete');

        $item_type->delete();

        return new ItemTypeResource($item_type);
    }

    public function select()
    {
        Gate::authorize('item_types.select');

        $item_types = ItemType::orderBy('type')->get();

        return response()->json([
            'data' => ItemTypeResource::collection($item_types),
        ]);
    }
}
