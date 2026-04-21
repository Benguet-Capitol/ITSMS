<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Http\Resources\BrandResource;
use Illuminate\Http\Request;
use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;

class BrandController extends Controller
{
    public function index(Request $request) {
      // Gate::authorize('brand_index');

      $query = Brand::query();

      if($request->has('search')) {
        $search = $request->search;
        $query->where(function ($q) use($search) {
          $q->where('name', 'LIKE', "%{$search}%");
        });
      }

      // Sorting (default to ID)
      if ($request->has('sort')) {
        $order = $request->input('order', 'asc');
        $query->orderBy($request->sort, $order);
      }

      // Paginate with customizable per-page count
      $brands = $query->paginate($request->input('per_page', 5))->appends($request->query());

      return response()->json([
          'data' => BrandResource::collection($brands),
          'meta' => [
              'total' => $brands->total(),
              'per_page' => $brands->perPage(),
              'current_page' => $brands->currentPage(),
              'last_page' => $brands->lastPage(),
          ]
      ]);
    }

    public function store(StoreBrandRequest $request) {
      // Gate::authorize('brand_store');
      
      $data = $request->validated();

      $brand = Brand::create($data);

      return new BrandResource($brand);
    }

    public function update(UpdateBrandRequest $request, Brand $brand) {
      // Gate::authorize('brand_update');

      $data = $request->validated();

      $brand->update($data);

      return new BrandResource($brand);
    }

    public function destroy(Brand $brand) {
      // Gate::authorize('brand_destroy');

      $brand->delete();
      
      return new BrandResource($brand);
    }

    public function select() {
        $brands = Brand::all();

        return response()->json([
          'data' => BrandResource::collection($brands)
        ]);
    }
}
