<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInspectorRequest;
use App\Http\Resources\InspectorResource;
use App\Models\Inspector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class InspectorController extends Controller
{
    /** Columns the list may be sorted by -- never pass the raw param to orderBy. */
    private const SORTABLE = ['name', 'position', 'office', 'is_active', 'created_at'];

    public function index(Request $request)
    {
        Gate::authorize('inspectors.view');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'sort' => ['nullable', 'string', Rule::in(self::SORTABLE)],
            'order' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Inspector::query()
            ->withCount('inspectionRequests')
            ->when($filters['search'] ?? null, function ($q, string $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('position', 'like', "%{$search}%")
                        ->orWhere('office', 'like', "%{$search}%")
                        ->orWhere('office_code', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('is_active', $status === 'active'));

        if (! empty($filters['sort'])) {
            $query->orderBy($filters['sort'], $filters['order'] ?? 'asc');
        } else {
            $query->orderBy('name');
        }

        $inspectors = $query->paginate($filters['per_page'] ?? 5)->appends($request->query());

        return response()->json([
            'data' => InspectorResource::collection($inspectors),
            'meta' => [
                'total' => $inspectors->total(),
                'per_page' => $inspectors->perPage(),
                'current_page' => $inspectors->currentPage(),
                'last_page' => $inspectors->lastPage(),
            ],
        ]);
    }

    public function store(StoreInspectorRequest $request)
    {
        Gate::authorize('inspectors.create');

        return new InspectorResource(Inspector::create($request->validated())->refresh());
    }

    public function show(Inspector $inspector)
    {
        Gate::authorize('inspectors.view');

        return new InspectorResource($inspector->loadCount('inspectionRequests'));
    }

    public function update(StoreInspectorRequest $request, Inspector $inspector)
    {
        Gate::authorize('inspectors.update');

        $inspector->update($request->validated());

        return new InspectorResource($inspector->loadCount('inspectionRequests'));
    }

    public function destroy(Inspector $inspector)
    {
        Gate::authorize('inspectors.delete');

        // Past requests still need their inspectors (the report prints
        // them), so someone who has inspected is deactivated, not deleted.
        $count = $inspector->inspectionRequests()->count();

        if ($count > 0) {
            abort(422, "Cannot delete \"{$inspector->name}\": they're on {$count} inspection request(s). Set them as inactive instead.");
        }

        $inspector->delete();

        return new InspectorResource($inspector);
    }

    /** Active inspectors, for the Inspection Request form's dropdown. */
    public function select()
    {
        Gate::authorize('inspectors.select');

        return response()->json([
            'data' => InspectorResource::collection(
                Inspector::query()->where('is_active', true)->orderBy('name')->get()
            ),
        ]);
    }
}
