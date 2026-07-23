<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryRequest;
use App\Http\Requests\UpdateInventoryRequest;
use App\Http\Resources\InventoryResource;
use App\Models\Inventory;
use App\Models\InventoryInternalComponent;
use App\Models\ItemType;
use App\Services\HrisClientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InventoryController extends Controller
{
    public function index(Request $request, HrisClientService $hris) {
        Gate::authorize('inventories.view');
        $search   = trim((string) $request->input('search', ''));
        $tab      = (string) $request->input('tab', 'all');
        $perPage  = (int) $request->input('per_page', 10);
        $page     = (int) $request->input('page', 1);
        $officeId = $request->input('office_id');

        $baseQuery = Inventory::query();

        // ── Search ──────────────────────────────────────────────────────────────
        if ($search !== '') {
            if (preg_match('/[a-zA-Z]/', $search)) {
                // Name search — ask HRIS only for matching employees
                $matched = collect($hris->searchEmployees($search))
                    ->filter(fn ($e) => isset($e['id']))
                    ->keys()
                    ->map(fn ($v) => (int) $v)
                    ->values()
                    ->all();

                // Re-key by id since searchEmployees returns a list, not a keyed map
                $ids = collect($hris->searchEmployees($search))
                    ->filter(fn ($e) => isset($e['id']))
                    ->pluck('id')
                    ->map(fn ($v) => (int) $v)
                    ->values()
                    ->all();

                empty($ids)
                    ? $baseQuery->whereRaw('1=0')
                    : $baseQuery->whereIn('employee_id', $ids);

            } else {
                $baseQuery->where(function ($q) use ($search) {
                    $q->where('property_number', 'like', "%{$search}%")
                      ->orWhere('ip_address', 'like', "%{$search}%")
                      ->orWhere('serial_number', 'like', "%{$search}%");
                });
            }
        }

        // ── Office filter ────────────────────────────────────────────────────────
        if ($request->filled('office_id')) {
            $officeId = (int) $request->input('office_id');

            $baseQuery->where(function ($q) use ($officeId) {
                $q->where('office_id', $officeId)
                  ->orWhereHas('parent_component', function ($q2) use ($officeId) {
                      $q2->where('office_id', $officeId);
                  });
            });
        }

        if ($request->filled('item_type')) {
            $baseQuery->where('item_type_id', $request->item_type);
        }

        if ($request->filled('division_id')) { // !? not used in frontend
            $divisionId = (int) $request->input('division_id');

            $baseQuery->where(function ($q) use ($divisionId) {
                $q->where('division_id', $divisionId)
                  ->orWhereHas('parent_component', function ($q2) use ($divisionId) {
                      $q2->where('division_id', $divisionId);
                  });
            });
        }

        // ── Tab filter ───────────────────────────────────────────────────────────
        $query = clone $baseQuery;
        match ($tab) {
            'parent_components' => $query->whereNull('parent_component_id'),
            'child_components'  => $query->whereNotNull('parent_component_id'),
            default             => null,
        };

        if ($request->filled('sort')) {
            $query->orderBy($request->sort, $request->input('order', 'asc'));
        } else {
            $query->latest();
        }

        $inventories = $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->appends($request->query());

        $allEmployees = collect($hris->getEmployeesCached(10))
            ->filter(fn ($e) => isset($e['id']))
            ->keyBy(fn ($e) => (int) $e['id']);

        $request->attributes->set('employeeMap', $allEmployees);

        // ── Counts ───────────────────────────────────────────────────────────────
        $counts = [
            'all'               => (clone $baseQuery)->count(),
            'parent_components' => (clone $baseQuery)->whereNull('parent_component_id')->count(),
            'child_components'  => (clone $baseQuery)->whereNotNull('parent_component_id')->count(),
        ];

        return response()->json([
            'data' => InventoryResource::collection($inventories),
            'meta' => [
                'total'        => $inventories->total(),
                'per_page'     => $inventories->perPage(),
                'current_page' => $inventories->currentPage(),
                'last_page'    => $inventories->lastPage(),
                'counts'       => $counts,
            ],
        ]);
    }

    public function store(StoreInventoryRequest $request) {
        Gate::authorize('inventories.create');

        $data = $request->validated();

        $inventory = Inventory::create($data);

        $itemType = ItemType::find($data['item_type_id']);

        if ($itemType?->is_main_inventory) {
            foreach ($data['internal_components'] ?? [] as $component) {
                InventoryInternalComponent::create([
                    'inventory_id'   => $inventory->id,
                    'brand_model_id' => $component['brand_model']['id'],
                    'quantity'       => $component['quantity'],
                ]);
            }
        }

        $this->injectEmployeeMap($request, $inventory->employee_id, app(HrisClientService::class));

        return new InventoryResource($inventory);
    }

    public function update(UpdateInventoryRequest $request, Inventory $inventory) {
        Gate::authorize('inventories.update');

        $data = $request->validated();

        $officeChanged =
            $inventory->office_id !== ($data['office_id'] ?? null) ||
            $inventory->office_code !== ($data['office_code'] ?? null) ||
            $inventory->office_name !== ($data['office_name'] ?? null);

        $inventory->update($data);

        $itemType = ItemType::find($inventory->item_type_id);

        if (
            $officeChanged &&
            $itemType?->is_main_inventory
        ) {
            Inventory::where('parent_component_id', $inventory->id)
                ->update([
                    'office_id' => $inventory->office_id,
                    'office_code' => $inventory->office_code,
                    'office_name' => $inventory->office_name,
                    'division_id' => $inventory->division_id,
                    'division_name' => $inventory->division_name,
                ]);
        }

        if ($itemType?->is_main_inventory) {
            $newComponents = $data['internal_components'] ?? [];

            $existingIds = $inventory->internal_components()->pluck('id')->toArray();

            $incomingIds = collect($newComponents)
                ->pluck('id')
                ->filter()
                ->toArray();

            $toDelete = array_diff($existingIds, $incomingIds);
            InventoryInternalComponent::whereIn('id', $toDelete)->delete();

            foreach ($newComponents as $component) {
                if (isset($component['id']) && in_array($component['id'], $existingIds, true)) {
                    $comp = InventoryInternalComponent::find($component['id']);
                    $comp?->update([
                        'brand_model_id' => $component['brand_model']['id'],
                        'quantity' => $component['quantity'],
                    ]);
                } else {
                    InventoryInternalComponent::create([
                        'inventory_id' => $inventory->id,
                        'brand_model_id' => $component['brand_model']['id'],
                        'quantity' => $component['quantity'],
                    ]);
                }
            }
        } else {
            $inventory->internal_components()->delete();
        }

        $this->injectEmployeeMap($request, $inventory->employee_id, app(HrisClientService::class));

        return new InventoryResource($inventory);
    }

    public function destroy(Inventory $inventory) {
      Gate::authorize('inventories.delete');

      $inventory->delete();
      
      return new InventoryResource($inventory);
    }

    public function search(Request $request, HrisClientService $hris) {
        Gate::authorize('inventories.view');
        $query  = trim((string) $request->input('q', ''));
        $limit  = (int) $request->input('limit', 20);
        $page   = (int) $request->input('page', 1);
        $offset = ($page - 1) * $limit;

        $inventories = Inventory::query()
            ->when($query, function ($qBuilder) use ($query, $hris) {
                $needle = mb_strtolower($query);

                // Targeted name search — no full list fetch
                $employeeIds = collect($hris->searchEmployees($query))
                    ->filter(fn ($e) => isset($e['id']))
                    ->pluck('id')
                    ->map(fn ($v) => (int) $v)
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

        $employeeMap = collect($hris->getEmployeesCached(10))
            ->filter(fn ($e) => isset($e['id']))
            ->keyBy(fn ($e) => (int) $e['id']);

        $request->attributes->set('employeeMap', $employeeMap);

        return response()->json([
            'data' => InventoryResource::collection($inventories),
        ]);
    }

    public function searchMainAsset(Request $request) {
        Gate::authorize('inventories.view');
        $query = $request->input('q');
        $limit = (int) $request->input('limit', 20);
        $page = (int) $request->input('page', 1);
        $offset = ($page - 1) * $limit;

        $exclude_id = $request->input('exclude_id');

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

    private function injectEmployeeMap(Request $request, $employeeId, HrisClientService $hris): void {
        $employeeMap = collect();

        if ($employeeId) {
            $found = $hris->getEmployeesWithParams(['employee_id' => (string) $employeeId], 30);
            foreach ($found as $e) {
                if (isset($e['id'])) {
                    $employeeMap->put((int) $e['id'], $e);
                }
            }
        }

        $request->attributes->set('employeeMap', $employeeMap);
    }
}
