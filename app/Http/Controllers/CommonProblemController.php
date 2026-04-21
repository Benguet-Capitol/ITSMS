<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CommonProblem;
use App\Http\Resources\CommonProblemResource;
use App\Http\Requests\StoreCommonProblemRequest;
use App\Http\Requests\UpdateCommonProblemRequest;

class CommonProblemController extends Controller
{
    public function index(Request $request) {
      // Gate::authorize('item_type_index');

      $query = CommonProblem::query();

      if($request->has('search')) {
        $search = $request->search;
        $query->where(function ($q) use($search) {
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
          ]
      ]);
    }

    public function store(StoreCommonProblemRequest $request) {
      // Gate::authorize('item_type_store');
      
      $data = $request->validated();

      $common_problem = CommonProblem::create($data);

      return new CommonProblemResource($common_problem);
    }

    public function update(UpdateCommonProblemRequest $request, CommonProblem $common_problem) {
      // Gate::authorize('item_type_update');

      $data = $request->validated();

      $common_problem->update($data);

      return new CommonProblemResource($common_problem);
    }

    public function destroy(CommonProblem $common_problem) {
      // Gate::authorize('item_type_destroy');

      $common_problem->delete();
      
      return new CommonProblemResource($common_problem);
    }
}
