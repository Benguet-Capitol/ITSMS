<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('brands.view');

        $query = Brand::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%");
            });
        }

        // Sorting (default to ID) -- whitelisted against real, sortable
        // columns so an arbitrary `sort` query param can't be used to probe
        // schema/column names or order by something not meant to be exposed.
        $sortable = ['id', 'name', 'status', 'created_at', 'updated_at'];

        if ($request->filled('sort') && in_array($request->sort, $sortable, true)) {
            $order = $request->input('order') === 'desc' ? 'desc' : 'asc';
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
            ],
        ]);
    }

    public function store(StoreBrandRequest $request)
    {
        Gate::authorize('brands.create');

        $data = $request->validated();

        $brand = Brand::create($data);

        return new BrandResource($brand);
    }

    public function update(UpdateBrandRequest $request, Brand $brand)
    {
        Gate::authorize('brands.update');

        $data = $request->validated();

        $brand->update($data);

        return new BrandResource($brand);
    }

    public function destroy(Brand $brand)
    {
        Gate::authorize('brands.delete');

        // brand_models.brand_id is cascadeOnDelete(), so deleting a brand
        // with models silently wipes out every one of them (and nulls
        // brand_model_id on any inventory using one) -- block it instead.
        $brandModelsCount = $brand->brand_models()->count();

        if ($brandModelsCount > 0) {
            abort(422, "Cannot delete \"{$brand->name}\": {$brandModelsCount} brand model(s) are still linked to it. Reassign or delete those first.");
        }

        $brand->delete();

        return new BrandResource($brand);
    }

    public function select()
    {
        Gate::authorize('brands.select');

        $brands = Brand::orderBy('name')->get();

        return response()->json([
            'data' => BrandResource::collection($brands),
        ]);
    }
}
