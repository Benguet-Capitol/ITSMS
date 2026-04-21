<?php

namespace App\Http\Controllers;

use App\Models\ItService;
use App\Http\Resources\ItServiceResource;
use App\Http\Requests\StoreItServiceRequest;
use App\Http\Requests\UpdateItServiceRequest;
use Illuminate\Http\Request;

class ItServiceController extends Controller
{
    public function index(Request $request) {
      // Gate::authorize('it_service_index');

      $query = ItService::query();

      if($request->has('search')) {
        $search = $request->search;
        $query->where(function ($q) use($search) {
          $q->where('name', 'LIKE', "%{$search}%")
          ->orWhere('description', 'LIKE', "%{$search}%")
          ->orWhere('code', 'LIKE', "%{$search}%");
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
      $itServices = $query->paginate($request->input('per_page', 5))->appends($request->query());

      return response()->json([
          'data' => ItServiceResource::collection($itServices),
          'meta' => [
              'total' => $itServices->total(),
              'per_page' => $itServices->perPage(),
              'current_page' => $itServices->currentPage(),
              'last_page' => $itServices->lastPage(),
          ]
      ]);
    }

    public function store(StoreItServiceRequest $request) {
      // Gate::authorize('it_service_store');
      
      $data = $request->validated();

      $itService = ItService::create($data);

      return new ItServiceResource($itService);
    }

    public function update(UpdateItServiceRequest $request, ItService $itService) {
      // Gate::authorize('it_service_update');

      $data = $request->validated();

      $itService->update($data);

      return new ItServiceResource($itService);
    }

    public function destroy(ItService $itService) {
      // Gate::authorize('it_service_destroy');

      $itService->delete();
      
      return new ItServiceResource($itService);
    }

    public function select() {
        $itServices = ItService::all();

        return response()->json([
          'data' => ItServiceResource::collection($itServices)
        ]);
    }
}
