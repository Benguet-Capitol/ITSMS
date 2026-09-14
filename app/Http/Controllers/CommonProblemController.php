<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommonProblemRequest;
use App\Http\Requests\UpdateCommonProblemRequest;
use App\Http\Resources\CommonProblemResource;
use App\Models\CommonProblem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CommonProblemController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('common_problems.view');

        $query = CommonProblem::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'LIKE', "%{$search}%")
                    ->orWhere('general_term', 'LIKE', "%{$search}%")
                    ->orWhere('information', 'LIKE', "%{$search}%");
            });
        }

        if ($request->has('classification')) {
            $query->where('classification', $request->classification);
        }

        // Sorting (default to ID)
        if ($request->has('sort')) {
            $order = $request->input('order', 'asc');
            $query->orderBy($request->sort, $order);
        }

        // Paginate with customizable per-page count
        $common_problems = $query->paginate($request->input('per_page', 5))->appends($request->query());

        return response()->json([
            'data' => CommonProblemResource::collection($common_problems),
            'meta' => [
                'total' => $common_problems->total(),
                'per_page' => $common_problems->perPage(),
                'current_page' => $common_problems->currentPage(),
                'last_page' => $common_problems->lastPage(),
            ],
        ]);
    }

    public function store(StoreCommonProblemRequest $request)
    {
        Gate::authorize('common_problems.create');

        $data = $request->validated();

        $common_problem = CommonProblem::create($data);

        return new CommonProblemResource($common_problem);
    }

    public function update(UpdateCommonProblemRequest $request, CommonProblem $common_problem)
    {
        Gate::authorize('common_problems.update');

        $data = $request->validated();

        $common_problem->update($data);

        return new CommonProblemResource($common_problem);
    }

    public function destroy(CommonProblem $common_problem)
    {
        Gate::authorize('common_problems.delete');

        $common_problem->delete();

        return new CommonProblemResource($common_problem);
    }

    // Scoped by item_type_id so Tickets' concern field can offer a
    // quick-select of problems relevant to whatever item type the ticket
    // is already being filed against, instead of the full unscoped list.
    public function select(Request $request)
    {
        Gate::authorize('common_problems.select');

        $itemTypeId = $request->input('item_type_id');

        $common_problems = CommonProblem::query()
            ->when($itemTypeId, fn ($query) => $query->where('item_type_id', $itemTypeId))
            ->orderBy('general_term')
            ->get();

        return response()->json([
            'data' => CommonProblemResource::collection($common_problems),
        ]);
    }
}
