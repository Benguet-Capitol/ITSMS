<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketComplexityLevelRequest;
use App\Http\Requests\UpdateTicketComplexityLevelRequest;
use App\Http\Resources\TicketComplexityLevelResource;
use App\Models\TicketComplexityLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TicketComplexityLevelController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('ticket_complexity_levels.view');

        $query = TicketComplexityLevel::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('label', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        $sortable = ['id', 'label', 'min_minutes', 'max_minutes', 'sort_order', 'created_at', 'updated_at'];

        if ($request->filled('sort') && in_array($request->sort, $sortable, true)) {
            $order = $request->input('order') === 'desc' ? 'desc' : 'asc';
            $query->orderBy($request->sort, $order);
        } else {
            $query->orderBy('sort_order');
        }

        $items = $query->paginate($request->input('per_page', 5))->appends($request->query());

        return response()->json([
            'data' => TicketComplexityLevelResource::collection($items),
            'meta' => [
                'total' => $items->total(),
                'per_page' => $items->perPage(),
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
            ],
        ]);
    }

    public function store(StoreTicketComplexityLevelRequest $request)
    {
        Gate::authorize('ticket_complexity_levels.create');

        $data = $request->validated();

        $complexityLevel = TicketComplexityLevel::create($data);

        return new TicketComplexityLevelResource($complexityLevel);
    }

    public function update(UpdateTicketComplexityLevelRequest $request, TicketComplexityLevel $ticketComplexityLevel)
    {
        Gate::authorize('ticket_complexity_levels.update');

        $data = $request->validated();

        $ticketComplexityLevel->update($data);

        return new TicketComplexityLevelResource($ticketComplexityLevel);
    }

    public function destroy(TicketComplexityLevel $ticketComplexityLevel)
    {
        Gate::authorize('ticket_complexity_levels.delete');

        $ticketsCount = $ticketComplexityLevel->tickets()->count();

        if ($ticketsCount > 0) {
            abort(422, "Cannot delete \"{$ticketComplexityLevel->label}\": {$ticketsCount} ticket(s) are still linked to it. Reassign or delete those first.");
        }

        $ticketComplexityLevel->delete();

        return new TicketComplexityLevelResource($ticketComplexityLevel);
    }

    public function select()
    {
        Gate::authorize('ticket_complexity_levels.select');

        $complexityLevels = TicketComplexityLevel::orderBy('sort_order')->get();

        return response()->json([
            'data' => TicketComplexityLevelResource::collection($complexityLevels),
        ]);
    }
}
