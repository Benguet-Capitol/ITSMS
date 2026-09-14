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
    public function index(Request $request)
    {
        Gate::authorize('brand_models.view');

        // select() is required once a join is added below (brand_models.*),
        // otherwise brands/item_types columns of the same name (id, name)
        // would collide and corrupt the hydrated BrandModel attributes.
        $query = BrandModel::query()->select('brand_models.*');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('specification', 'LIKE', "%{$search}%")
                    ->orWhere('name', 'LIKE', "%{$search}%")
                    ->orWhereHas('brand', function ($q2) use ($search) {
                        $q2->where('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        // Sorting (default to ID) -- whitelisted against real, sortable
        // columns so an arbitrary `sort` query param can't be used to probe
        // schema/column names or order by something not meant to be exposed.
        // The frontend also advertises brand.name/item_type.type/
        // .classification/.purpose as sortable, which live on related
        // tables and need an explicit join -- orderBy() can't reach them
        // on its own.
        $directSortable = ['id', 'name', 'specification', 'status', 'year_released', 'created_at', 'updated_at'];

        $relationSortColumns = [
            'brand.name' => 'brands.name',
            'item_type.type' => 'item_types.type',
            'item_type.classification' => 'item_types.classification',
            'item_type.purpose' => 'item_types.purpose',
        ];

        if ($request->filled('sort')) {
            $sortColumn = $request->sort;
            $order = $request->input('order') === 'desc' ? 'desc' : 'asc';

            if (in_array($sortColumn, $directSortable, true)) {
                $query->orderBy($sortColumn, $order);
            } elseif (isset($relationSortColumns[$sortColumn])) {
                if (str_starts_with($sortColumn, 'brand.')) {
                    $query->leftJoin('brands', 'brands.id', '=', 'brand_models.brand_id');
                } else {
                    $query->leftJoin('item_types', 'item_types.id', '=', 'brand_models.item_type_id');
                }

                $query->orderBy($relationSortColumns[$sortColumn], $order);
            }
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
            ],
        ]);
    }

    public function store(StoreBrandModelRequest $request)
    {
        Gate::authorize('brand_models.create');

        $data = $request->validated();

        $brand_model = BrandModel::create($data);

        return new BrandModelResource($brand_model);
    }

    public function update(UpdateBrandModelRequest $request, BrandModel $brand_model)
    {
        Gate::authorize('brand_models.update');

        $data = $request->validated();

        $brand_model->update($data);

        return new BrandModelResource($brand_model);
    }

    // brand_model_id on inventories/inventory_internal_components is
    // nullOnDelete (unlike brands.id -> brand_models, which cascades), so
    // deleting a brand model doesn't destroy those records -- it just
    // clears the reference. Not destructive enough to block outright, but
    // surprising enough that the frontend shows this count before letting
    // the user confirm delete.
    public function usage(BrandModel $brand_model)
    {
        Gate::authorize('brand_models.delete');

        return response()->json([
            'inventories_count' => $brand_model->inventories()->count(),
            'internal_components_count' => $brand_model->internal_components()->count(),
        ]);
    }

    public function destroy(BrandModel $brand_model)
    {
        Gate::authorize('brand_models.delete');

        $brand_model->delete();

        return new BrandModelResource($brand_model);
    }

    public function select(Request $request)
    {
        // Registered at routes/api.php as GET /lookups/brand-models, but this
        // method didn't actually exist (only commented out) -- calling that
        // route would have fataled with "Call to undefined method".
        Gate::authorize('brand_models.select');

        $query = $request->input('q');

        $brand_models = BrandModel::query()
            ->when($query, fn ($qBuilder) => $qBuilder->where('specification', 'like', "%{$query}%")
                ->orWhere('name', 'like', "%{$query}%")
                ->orWhereHas('brand', function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%");
                })
            )
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => BrandModelResource::collection($brand_models),
        ]);
    }

    public function search(Request $request)
    {
        Gate::authorize('brand_models.search');

        $query = $request->input('q');
        $item_type_id = $request->input('item_type_id');
        $limit = (int) $request->input('limit', 20);
        $page = (int) $request->input('page', 1);
        $offset = ($page - 1) * $limit;

        // if($item_type_id) {
        $brand_models = BrandModel::query()
            ->when($item_type_id, fn ($qBuilder) => $qBuilder->where('item_type_id', $item_type_id)
            )
            ->when($query, fn ($qBuilder) => $qBuilder->where('specification', 'like', "%$query%")->orWhere('name', 'like', "%$query%")->orWhereHas('brand', function ($q) use ($query) {
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
