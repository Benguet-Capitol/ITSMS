<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreItServiceRequest;
use App\Http\Requests\UpdateItServiceRequest;
use App\Http\Resources\ItServiceResource;
use App\Models\ItService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ItServiceController extends Controller
{
    public function index(Request $request) {
      Gate::authorize('it_services.view');

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
      Gate::authorize('it_services.create');
      
      $data = $request->validated();

      $itService = ItService::create($data);

      return new ItServiceResource($itService);
    }

    public function update(UpdateItServiceRequest $request, ItService $itService) {
      Gate::authorize('it_services.update');

      $data = $request->validated();

      $itService->update($data);

      return new ItServiceResource($itService);
    }

    public function destroy(ItService $itService) {
      Gate::authorize('it_services.delete');

      $itService->delete();
      
      return new ItServiceResource($itService);
    }

    public function select() {
      Gate::authorize('it_services.view');
      
      $itServices = ItService::all();

      return response()->json([
        'data' => ItServiceResource::collection($itServices)
      ]);
    }
}
