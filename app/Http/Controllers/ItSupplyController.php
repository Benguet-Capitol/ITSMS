<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ItSupply;
use App\Http\Resources\ItSupplyResource;
use App\Http\Requests\StoreItSupplyRequest;
use App\Http\Requests\UpdateItSupplyRequest;

class ItSupplyController extends Controller
{
    public function index(Request $request) {
      // Gate::authorize('item_type_index');

      $query = ItSupply::query();

      if($request->has('search')) {
        $search = $request->search;
        $query->where(function ($q) use($search) {
          $q->where('item_number', 'LIKE', "%{$search}%")
          ->orWhere('stock_number', 'LIKE', "%{$search}%")
          ->orWhere('ics_number', 'LIKE', "%{$search}%")
          ->orWhere('iar_number', 'LIKE', "%{$search}%")
          ->orWhere('po_number', 'LIKE', "%{$search}%")
          ->orWhereHas('brand_model', function ($q2) use($search) {
            $q2->where('name', 'LIKE', "%{$search}%")
            ->orWhereHas('brand', function ($q3) use($search) {
              $q3->where('name', 'LIKE', "%{$search}%");
            })
            ->orWhereHas('item_type', function ($q4) use($search) {
              $q4->where('type', 'LIKE', "%{$search}%");
            });
          });
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
          'data' => ItSupplyResource::collection($items),
          'meta' => [
              'total' => $items->total(),
              'per_page' => $items->perPage(),
              'current_page' => $items->currentPage(),
              'last_page' => $items->lastPage(),
          ]
      ]);
    }

    public function store(StoreItSupplyRequest $request) {
      // Gate::authorize('item_store');
      
      $data = $request->validated();

      $itSupply = ItSupply::create($data);

      return new ItSupplyResource($itSupply);
    }

    public function update(UpdateItSupplyRequest $request, ItSupply $itSupply) {
      // Gate::authorize('item_update');

      $data = $request->validated();

      $itSupply->update($data);

      return new ItSupplyResource($itSupply);
    }

    public function destroy(ItSupply $itSupply) {
      // Gate::authorize('item_destroy');

      $itSupply->delete();
      
      return new ItSupplyResource($itSupply);
    }

    public function select() {
        $itSupplies = ItSupply::all();

        return response()->json([
          'data' => ItSupplyResource::collection($itSupplies)
        ]);
    }

    public function search(Request $request) {
      $query = $request->get('q');
      $limit = (int) $request->get('limit', 20);
      $page = (int) $request->get('page', 1);
      $offset = ($page - 1) * $limit;

      $it_supplies = ItSupply::query()
          ->when($query, fn($qBuilder) =>
              $qBuilder->where('item_number', 'like', "%$query%")
              ->orWhere('stock_number', 'like', "%$query%")
              ->orWhere('description', 'like', "%$query%")
              ->orWhereHas('brand_model', function ($q2) use($query) {
                $q2->where('name', 'like', "%$query%")
                ->orWhereHas('brand', function ($q3) use($query) {
                  $q3->where('name', 'like', "%$query%");
                })
                ->orWhereHas('item_type', function ($q4) use($query) {
                  $q4->where('type', 'like', "%$query%");
                });
              })
          )
          ->offset($offset)
          ->limit($limit)
          ->get();

      return response()->json([
          'data' => ItSupplyResource::collection($it_supplies),
      ]);
    }
}
