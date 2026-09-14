<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSolutionRequest;
use App\Http\Requests\UpdateSolutionRequest;
use App\Http\Resources\SolutionResource;
use App\Models\Solution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SolutionController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('solutions.view');

        $query = Solution::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%")
                    ->orWhere('error_code', 'LIKE', "%{$search}%");
            });
        }

        // Sorting (default to ID) -- whitelisted against real, sortable
        // columns so an arbitrary `sort` query param can't be used to probe
        // schema/column names or order by something not meant to be exposed.
        $sortable = ['id', 'title', 'error_code', 'created_at', 'updated_at'];

        if ($request->filled('sort') && in_array($request->sort, $sortable, true)) {
            $order = $request->input('order') === 'desc' ? 'desc' : 'asc';
            $query->orderBy($request->sort, $order);
        }

        // Paginate with customizable per-page count
        $solutions = $query->paginate($request->input('per_page', 5))->appends($request->query());

        return response()->json([
            'data' => SolutionResource::collection($solutions),
            'meta' => [
                'total' => $solutions->total(),
                'per_page' => $solutions->perPage(),
                'current_page' => $solutions->currentPage(),
                'last_page' => $solutions->lastPage(),
            ],
        ]);
    }

    public function store(StoreSolutionRequest $request)
    {
        Gate::authorize('solutions.create');

        $data = $request->validated();

        $solution = Solution::create($data);

        return new SolutionResource($solution);
    }

    public function update(UpdateSolutionRequest $request, Solution $solution)
    {
        Gate::authorize('solutions.update');

        $data = $request->validated();

        $solution->update($data);

        return new SolutionResource($solution);
    }

    public function destroy(Solution $solution)
    {
        Gate::authorize('solutions.delete');

        $solution->delete();

        return new SolutionResource($solution);
    }

    public function select(Request $request)
    {
        Gate::authorize('solutions.select');

        $solutions = Solution::query()
            ->when(
                $request->filled('search'),
                function ($query) use ($request) {
                    $search = $request->string('search');

                    $query->where(function ($query) use ($search) {
                        $query
                            ->where('title', 'like', "%{$search}%")
                            ->orWhere(
                                'description',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'error_code',
                                'like',
                                "%{$search}%"
                            );
                    });
                }
            )
            ->latest()
            ->limit($request->integer('limit', 50))
            ->get();

        return response()->json([
            'data' => SolutionResource::collection($solutions),
        ]);
    }
}
