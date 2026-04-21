<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ItemType;
use App\Http\Resources\ItemTypeResource;
use App\Http\Requests\StoreItemTypeRequest;
use App\Http\Requests\UpdateItemTypeRequest;
use Illuminate\Auth\Access\Gate;

class ItemTypeController extends Controller
{
    public function index(Request $request) {
      // Gate::authorize('item_type_index');

      $query = ItemType::query();

      if($request->has('search')) {
        $search = $request->search;
        $query->where(function ($q) use($search) {
          $q->where('type', 'LIKE', "%{$search}%");
        });
      }

      if ($request->has('classification')) {
        $query->where('classification', $request->classification);
      }

      // Sorting (default to ID)
      if ($request->has('sort')) {
        $order = $request->input('order', 'asc');
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
          ]
      ]);
    }

    public function store(StoreItemTypeRequest $request) {
      // Gate::authorize('item_type_store');
      
      $data = $request->validated();

      $item_type = ItemType::create($data);

      return new ItemTypeResource($item_type);
    }

    public function update(UpdateItemTypeRequest $request, ItemType $item_type) {
      // Gate::authorize('item_type_update');

      $data = $request->validated();

      $item_type->update($data);

      return new ItemTypeResource($item_type);
    }

    public function destroy(ItemType $item_type) {
      // Gate::authorize('item_type_destroy');

      $item_type->delete();
      
      return new ItemTypeResource($item_type);
    }

    public function select() {
        $item_types = ItemType::all();

        return response()->json([
          'data' => ItemTypeResource::collection($item_types)
        ]);
    }

// id	name	is_main_asset	is_component
// 1	Desktop/CPU	TRUE	FALSE
// 2	Monitor	TRUE	TRUE
// 3	Printer	TRUE	TRUE
// 4	UPS	TRUE	TRUE
// 5	Processor	FALSE	TRUE
// 6	Motherboard	FALSE	TRUE
// 7	Memory Module	FALSE	TRUE
// 8	Storage	FALSE	TRUE
// 9	Video/Graphics Card	FALSE	TRUE
// 10	CD-ROM Optical Drive	FALSE	TRUE
// 11	Mouse	FALSE	TRUE
// 12	Keyboard	FALSE	TRUE
// 13	Speaker	FALSE	TRUE
// 14	Webcam	FALSE	TRUE
// 15	Operating System	FALSE	FALSE
// 16	Office Suite	FALSE	FALSE
// 17	Antivirus	FALSE	FALSE
}
