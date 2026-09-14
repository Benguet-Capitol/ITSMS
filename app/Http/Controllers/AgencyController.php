<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAgencyRequest;
use App\Http\Requests\UpdateAgencyRequest;
use App\Http\Resources\AgencyResource;
use App\Models\Agency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AgencyController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('agencies.view');

        $query = Agency::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%");
            });
        }

        // Sorting (default to ID) -- whitelisted against real, sortable
        // columns so an arbitrary `sort` query param can't be used to probe
        // schema/column names or order by something not meant to be exposed.
        $sortable = ['id', 'name', 'abbreviation', 'created_at', 'updated_at'];

        if ($request->filled('sort') && in_array($request->sort, $sortable, true)) {
            $order = $request->input('order') === 'desc' ? 'desc' : 'asc';
            $query->orderBy($request->sort, $order);
        }

        // Paginate with customizable per-page count
        $agencies = $query->paginate($request->input('per_page', 5))->appends($request->query());

        return response()->json([
            'data' => AgencyResource::collection($agencies),
            'meta' => [
                'total' => $agencies->total(),
                'per_page' => $agencies->perPage(),
                'current_page' => $agencies->currentPage(),
                'last_page' => $agencies->lastPage(),
            ],
        ]);
    }

    public function store(StoreAgencyRequest $request)
    {
        Gate::authorize('agencies.create');

        $data = $request->validated();

        $agency = Agency::create($data);

        return new AgencyResource($agency);
    }

    public function update(UpdateAgencyRequest $request, Agency $agency)
    {
        Gate::authorize('agencies.update');

        $data = $request->validated();

        $agency->update($data);

        return new AgencyResource($agency);
    }

    // profile_agency cascades on delete (it's a pivot table, not a real
    // record), but tickets.agency_id is nullOnDelete -- surfacing both
    // counts before confirming, same pattern as ItemType/BrandModel/Role.
    public function usage(Agency $agency)
    {
        Gate::authorize('agencies.delete');

        return response()->json([
            'assigned_profiles_count' => $agency->assigned_profiles()->count(),
            'tickets_count' => $agency->tickets()->count(),
        ]);
    }

    public function destroy(Agency $agency)
    {
        Gate::authorize('agencies.delete');

        $agency->delete();

        return new AgencyResource($agency);
    }

    public function select()
    {
        Gate::authorize('agencies.select');

        $agencies = Agency::query()->orderBy('abbreviation')->get();

        return response()->json([
            'data' => AgencyResource::collection($agencies),
        ]);
    }

    public function search(Request $request)
    {
        Gate::authorize('agencies.search');

        $query = trim(
            $request->string('q')->toString()
        );

        $limit = min(
            max($request->integer('limit', 20), 1),
            100
        );

        $page = max(
            $request->integer('page', 1),
            1
        );

        $agencies = Agency::query()
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($builder) use ($query) {
                    $builder
                        ->where('name', 'like', "%{$query}%")
                        ->orWhere(
                            'abbreviation',
                            'like',
                            "%{$query}%"
                        );
                });
            })
            ->orderBy('abbreviation')
            ->forPage($page, $limit)
            ->get();

        return response()->json([
            'data' => AgencyResource::collection($agencies),
        ]);
    }
}
