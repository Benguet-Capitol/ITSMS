<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Solution;
use App\Http\Resources\SolutionResource;
use App\Http\Requests\StoreSolutionRequest;
use App\Http\Requests\UpdateSolutionRequest;

class SolutionController extends Controller
{
    public function index(Request $request) {
      // Gate::authorize('it_service_index');

      $query = Solution::query();

      if($request->has('search')) {
        $search = $request->search;
        $query->where(function ($q) use($search) {
          $q->where('title', 'LIKE', "%{$search}%")
          ->orWhere('description', 'LIKE', "%{$search}%")
          ->orWhere('error_code', 'LIKE', "%{$search}%");
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
      $solutions = $query->paginate($request->input('per_page', 5))->appends($request->query());

      return response()->json([
          'data' => SolutionResource::collection($solutions),
          'meta' => [
              'total' => $solutions->total(),
              'per_page' => $solutions->perPage(),
              'current_page' => $solutions->currentPage(),
              'last_page' => $solutions->lastPage(),
          ]
      ]);
    }

    public function store(StoreSolutionRequest $request) {
      $data = $request->validated();

      $solution = Solution::create($data);

      return new SolutionResource($solution); 
    }

    public function update(UpdateSolutionRequest $request, Solution $solution) {
      // Gate::authorize('it_service_update');

      $data = $request->validated();

      $solution->update($data);

      return new SolutionResource($solution);
    }

    public function destroy(Solution $solution) {
      // Gate::authorize('item_destroy');

      $solution->delete();
      
      return new SolutionResource($solution);
    }

    public function select() {
        $solutions = Solution::latest()->get();

        return response()->json([
          'data' => SolutionResource::collection($solutions)
        ]);
    }
}
