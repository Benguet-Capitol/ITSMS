<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBrandModelRequest;
use App\Http\Requests\UpdateBrandModelRequest;
use App\Http\Resources\BrandModelResource;
use App\Models\BrandModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BrandModelController extends Controller
{
    public function index(Request $request) {
      Gate::authorize('brand_models.view');

      $query = BrandModel::query();

      if($request->has('search')) {
        $search = $request->search;
        $query->where(function ($q) use($search) {
          $q->where('specification', 'LIKE', "%{$search}%");
          $q->orWhere('name', 'LIKE', "%{$search}%");
        });
      }

      // Sorting (default to ID)
      if ($request->has('sort')) {
        $order = $request->input('order', 'asc');
        $query->orderBy($request->sort, $order);
      }

      // Paginate with customizable per-page count
      $brand_models = $query->paginate($request->input('per_page', 5))->appends($request->query());

      return response()->json([
          'data' => BrandModelResource::collection($brand_models),
          'meta' => [
              'total' => $brand_models->total(),
              'per_page' => $brand_models->perPage(),
              'current_page' => $brand_models->currentPage(),
              'last_page' => $brand_models->lastPage(),
          ]
      ]);
    }

    public function store(StoreBrandModelRequest $request) {
      Gate::authorize('brand_models.create');
      
      $data = $request->validated();

      $brand_model = BrandModel::create($data);

      return new BrandModelResource($brand_model);
    }

    public function update(UpdateBrandModelRequest $request, BrandModel $brand_model) {
      Gate::authorize('brand_models.update');

      $data = $request->validated();

      $brand_model->update($data);

      return new BrandModelResource($brand_model);
    }

    public function destroy(BrandModel $brand_model) {
      Gate::authorize('brand_models.delete');

      $brand_model->delete();
      
      return new BrandModelResource($brand_model);
    }

    // public function select(Request $request) {
    //   $query = $request->get('q');
    //   $limit = (int) $request->get('limit', 20);
    //   $page = (int) $request->get('page', 1);
    //   $offset = ($page - 1) * $limit;

    //   $brand_models = BrandModel::query()
    //       ->when($query, fn($qBuilder) =>
    //           $qBuilder->where('specification', 'like', "%$query%")->orWhere('name', 'like', "%$query%")
    //       )
    //       ->offset($offset)
    //       ->limit($limit)
    //       ->get();

    //   return response()->json([
    //       'data' => BrandModelResource::collection($brand_models),
    //   ]);
    // }

    public function search(Request $request) {
      Gate::authorize('brand_models.view');
      
      $query = $request->input('q');
      $item_type_id = $request->input('item_type_id');
      $limit = (int) $request->input('limit', 20);
      $page = (int) $request->input('page', 1);
      $offset = ($page - 1) * $limit;

      // if($item_type_id) {
        $brand_models = BrandModel::query()
          ->when($item_type_id, fn($qBuilder) =>
              $qBuilder->where('item_type_id', $item_type_id)
          )
          ->when($query, fn($qBuilder) =>
              $qBuilder->where('specification', 'like', "%$query%")->orWhere('name', 'like', "%$query%")->orWhereHas('brand', function($q) use ($query) {
                  $q->where('name', 'like', "%$query%");
              })
          )
          ->offset($offset)
          ->limit($limit)
          ->get();
      // }

      return response()->json([
          'data' => BrandModelResource::collection($brand_models),
      ]);
    }
}
