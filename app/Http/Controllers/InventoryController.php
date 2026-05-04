<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use Illuminate\Http\Request;
use App\Http\Resources\InventoryResource;
use App\Models\InventoryInternalComponent;
use App\Http\Requests\StoreInventoryRequest;
use App\Http\Requests\UpdateInventoryRequest;
use Illuminate\Support\Facades\Cache;
use App\Services\HrisClientService;

class InventoryController extends Controller
{
    public function index(Request $request, HrisClientService $hris) {
        $search  = trim((string) $request->get('search', ''));
        $tab     = (string) $request->get('tab', 'all');
        $perPage = (int) $request->input('per_page', 10);
        $page    = (int) $request->input('page', 1);

        // ✅ HRIS employee map for InventoryResource
        $employeeMap = collect($hris->getEmployeesCached())
            ->filter(fn ($e) => isset($e['id']))
            ->keyBy(fn ($e) => (int) $e['id']);

        $request->attributes->set('employeeMap', $employeeMap);

        // ---------------------------
        // 1) BASE QUERY (shared by list + counts)
        // ---------------------------
        $baseQuery = Inventory::query();

        // ✅ Search
        if ($search !== '') {
            // Name search -> resolve HRIS IDs -> filter inventories.employee_id
            if (preg_match('/[a-zA-Z]/', $search)) {
                $needle = mb_strtolower($search);

                $ids = $employeeMap
                    ->filter(function ($e) use ($needle) {
                        $name = mb_strtolower($e['fullname'] ?? $e['full_name'] ?? '');
                        return $name !== '' && str_contains($name, $needle);
                    })
                    ->keys()
                    ->map(fn ($v) => (int) $v)
                    ->values()
                    ->all();

                // no matches => empty
                if (empty($ids)) {
                    $baseQuery->whereRaw('1=0');
                } else {
                    $baseQuery->whereIn('employee_id', $ids);
                }
            } else {
                // Inventory field search
                $baseQuery->where(function ($q) use ($search) {
                    $q->where('property_number', 'like', "%{$search}%")
                      ->orWhere('ip_address', 'like', "%{$search}%")
                      ->orWhere('serial_number', 'like', "%{$search}%");
                });
            }
        }

        // ---------------------------
        // 2) APPLY TAB FILTER
        // ---------------------------
        $applyTab = function ($q) use ($tab) {
            switch ($tab) {
                case 'parent_components':
                    $q->whereNull('parent_component_id');
                    break;

                case 'child_components':
                    $q->whereNotNull('parent_component_id');
                    break;

                case 'all':
                default:
                    // no filter
                    break;
            }
        };

        $query = (clone $baseQuery);
        $applyTab($query);

        // ✅ Sorting (optional)
        if ($request->filled('sort')) {
            $order = $request->input('order', 'asc');
            $query->orderBy($request->sort, $order);
        } else {
            $query->latest();
        }

        $inventories = $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->appends($request->query());

        // ---------------------------
        // 3) COUNTS (respect search; count per tab)
        // ---------------------------
        $counts = [
            'all' => (clone $baseQuery)->count(),
            'parent_components' => (clone $baseQuery)->whereNull('parent_component_id')->count(),
            'child_components' => (clone $baseQuery)->whereNotNull('parent_component_id')->count(),
        ];

        return response()->json([
            'data' => InventoryResource::collection($inventories),
            'meta' => [
                'total' => $inventories->total(),
                'per_page' => $inventories->perPage(),
                'current_page' => $inventories->currentPage(),
                'last_page' => $inventories->lastPage(),
                'counts' => $counts,
            ],
        ]);
    }


    public function store(StoreInventoryRequest $request) {
      // Gate::authorize('item_store');
      
      $data = $request->validated();
      
      $inventory = Inventory::create($data);
      
      if ((int) $data['item_type_id'] === 1) {
        foreach ($data['internal_components'] ?? [] as $component) {
          InventoryInternalComponent::create([
              'inventory_id'   => $inventory->id,
              'brand_model_id' => $component['brand_model']['id'],
              'quantity'       => $component['quantity'],
          ]);
        }
      }

      return new InventoryResource($inventory);
    }

    public function update(UpdateInventoryRequest $request, Inventory $inventory) {
      // Gate::authorize('item_update');

      $data = $request->validated();

      $inventory->update($data);

      // 2. Handle internal components if item_type_id = 1
      if ((int) $data['item_type_id'] === 1) {
        $newComponents = $data['internal_components'] ?? [];

        // Get current component IDs in DB
        $existingIds = $inventory->internal_components()->pluck('id')->toArray();

        // IDs from the request (existing ones)
        $incomingIds = collect($newComponents)
            ->pluck('id') // 'id' might be missing for new components
            ->filter()
            ->toArray();

        // Delete components that are in DB but not in request
        $toDelete = array_diff($existingIds, $incomingIds);
        InventoryInternalComponent::whereIn('id', $toDelete)->delete();

        // Add or update components from request
        foreach ($newComponents as $component) {
            if (isset($component['id']) && in_array($component['id'], $existingIds)) {
                // Update existing
                $comp = InventoryInternalComponent::find($component['id']);
                $comp->update([
                    'brand_model_id' => $component['brand_model']['id'],
                    'quantity'       => $component['quantity'],
                ]);
            } else {
                // Create new
                InventoryInternalComponent::create([
                    'inventory_id'   => $inventory->id,
                    'brand_model_id' => $component['brand_model']['id'],
                    'quantity'       => $component['quantity'],
                ]);
            }
        }
      } else {
        // If item type changed, remove all internal components
        $inventory->internal_components()->delete();
      }

      return new InventoryResource($inventory);
    }

    public function destroy(Inventory $inventory) {
      // Gate::authorize('item_destroy');

      $inventory->delete();
      
      return new InventoryResource($inventory);
    }

    public function search(Request $request, HrisClientService $hris) {
        $query = trim((string) $request->input('q', ''));
        $limit = (int) $request->input('limit', 20);
        $page = (int) $request->input('page', 1);
        $offset = ($page - 1) * $limit;

        $employeeMap = collect($hris->getEmployeesCached())
            ->filter(fn ($e) => isset($e['id']))
            ->keyBy(fn ($e) => (int) $e['id']);

        $request->attributes->set('employeeMap', $employeeMap);

        $inventories = Inventory::query()
            ->when($query, function ($qBuilder) use ($query, $employeeMap) {
                $needle = mb_strtolower($query);

                $employeeIds = $employeeMap
                    ->filter(function ($employee) use ($needle) {
                        $name = mb_strtolower($employee['fullname'] ?? $employee['full_name'] ?? '');
                        return $name !== '' && str_contains($name, $needle);
                    })
                    ->keys()
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();

                $qBuilder->where(function ($q) use ($query, $employeeIds) {
                    $q->where('property_number', 'like', "%{$query}%");

                    if (!empty($employeeIds)) {
                        $q->orWhereIn('employee_id', $employeeIds);
                    }
                });
            })
            ->offset($offset)
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => InventoryResource::collection($inventories),
        ]);
    }

    public function searchMainAsset(Request $request) {
      $query = $request->get('q');
      $limit = (int) $request->get('limit', 20);
      $page = (int) $request->get('page', 1);
      $offset = ($page - 1) * $limit;

      $exclude_id = $request->get('exclude_id');

      $inventories = Inventory::query()
          ->when($query, function ($qBuilder) use ($query) {
            $qBuilder->where('property_number', 'like', "%$query%")
                ->whereHas('item_type', function ($q4) {
                    $q4->where('is_main_inventory', true)
                        ->where('is_component', false);
                });
        })
        ->when($exclude_id, function ($qBuilder) use ($exclude_id) {
            $qBuilder->where('id', '!=', $exclude_id);
        })
        ->offset($offset)
        ->limit($limit)
        ->get();  

      return response()->json([
          'data' => InventoryResource::collection($inventories),
      ]);
    }
}
