<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use Illuminate\Http\Request;
use App\Http\Resources\AgencyResource;
use App\Http\Requests\StoreAgencyRequest;
use App\Http\Requests\UpdateAgencyRequest;
use Illuminate\Support\Facades\Gate;

class AgencyController extends Controller
{
    public function index(Request $request) {
      Gate::authorize('agencies.view');

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
      Gate::authorize('agencies.create');
      
      $data = $request->validated();

      $agency = Agency::create($data);

      return new AgencyResource($agency);
    }

    public function update(UpdateAgencyRequest $request, Agency $agency) {
      Gate::authorize('agencies.update');

      $data = $request->validated();

      $agency->update($data);

      return new AgencyResource($agency);
    }

    public function destroy(Agency $agency) {
      Gate::authorize('agencies.delete');

      $agency->delete();
      
      return new AgencyResource($agency);
    }

    public function select() {
        Gate::authorize('agencies.select');

        $agencies = Agency::query()->orderBy('abbreviation')->get();

        return response()->json([
            'data' => AgencyResource::collection($agencies),
        ]);
    }

    public function search(Request $request) {
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
