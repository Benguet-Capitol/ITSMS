<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use Illuminate\Http\Request;
use App\Http\Resources\AgencyResource;
use App\Http\Requests\StoreAgencyRequest;
use App\Http\Requests\UpdateAgencyRequest;

class AgencyController extends Controller
{
    public function index(Request $request) {
      // Gate::authorize('agency_index');

      $query = Agency::query();

      if($request->has('search')) {
        $search = $request->search;
        $query->where(function ($q) use($search) {
          $q->where('name', 'LIKE', "%{$search}%");
        });
      }

      // Sorting (default to ID)
      if ($request->has('sort')) {
        $order = $request->input('order', 'asc');
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
          ]
      ]);
    }

    public function store(StoreAgencyRequest $request) {
      // Gate::authorize('agency_store');
      
      $data = $request->validated();

      $agency = Agency::create($data);

      return new AgencyResource($agency);
    }

    public function update(UpdateAgencyRequest $request, Agency $agency) {
      // Gate::authorize('agency_update');

      $data = $request->validated();

      $agency->update($data);

      return new AgencyResource($agency);
    }

    public function destroy(Agency $agency) {
      // Gate::authorize('agency_destroy');

      $agency->delete();
      
      return new AgencyResource($agency);
    }

    public function select() {
        $agencies = Agency::all();

        return response()->json([
          'data' => AgencyResource::collection($agencies)
        ]);
    }

    public function search(Request $request) {
      $query = $request->get('q');
      $limit = (int) $request->get('limit', 20);
      $page = (int) $request->get('page', 1);
      $offset = ($page - 1) * $limit;

      $agencies = Agency::query()
          ->when($query, fn($qBuilder) =>
              $qBuilder->where('name', 'like', "%$query%")->orWhere('abbreviation', 'like', "%$query%")
          )
          ->offset($offset)
          ->limit($limit)
          ->get();

      return response()->json([
          'data' => AgencyResource::collection($agencies),
      ]);
    }
}
