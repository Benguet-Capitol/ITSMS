<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\Profile;
use App\Enums\TicketStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\TicketResource;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Requests\ResolveTicketRequest;
use App\Http\Requests\SetTicketReleaseDateRequest;
use App\Http\Requests\SetTicketServiceMethodRequest;
use App\Services\ProfileEngagementService;
use Illuminate\Support\Facades\Log;

class TicketController extends Controller
{
    public function index(Request $request) {
      // Gate::authorize('it_service_index');

      $profileId = Auth::user()->profile->id;
      $baseQuery = Ticket::query();

      if ($request->filled('search')) {
          $search = $request->search;
          $baseQuery->where(function ($q) use ($search) {
              $q->where('concern', 'LIKE', "%{$search}%")
                ->orWhere('ticket_number', 'LIKE', "%{$search}%");
          });
      }

      $query = (clone $baseQuery)
          ->with(['personnel'])
          ->withCount([
              'personnel as accepted_by_me' => fn($q) => $q->where('profile_id', $profileId),
              'personnel as personnel_count',
          ]);

      if ($request->filled('tab')) {
          switch ($request->tab) {
              case 'accepted_by_me':
                  $query->whereHas('personnel', fn($q) => $q->where('profile_id', $profileId));
                  break;

              case 'accepted_by_others':
                  $query->whereHas('personnel', fn($q) => $q->where('profile_id', '!=', $profileId));
                  break;

              case 'open':
                  $query->whereIn('request_status', [TicketStatus::Open, TicketStatus::Reopened]);
                  break;

              case 'closed':
                  $query->whereIn('query_status', [TicketStatus::Resolved, TicketStatus::Cancelled]);
                  break;

          }
      }

      // Query_status filter from dropdown (optional)
      if ($request->filled('query_status')) {
          $query->where('query_status', $request->query_status);
      }

      // Sorting
      if ($request->filled('sort')) {
          $order = $request->input('order', 'asc');
          $query->orderBy($request->sort, $order);
      } else {
          $query->latest();
      }

      $perPage = $request->input('per_page', 10);
      $currentPage = $request->input('page', 1);
      $tickets = $query->paginate($perPage, ['*'], 'page', $currentPage)->appends($request->query());

      $counts = [
          'all' => (clone $baseQuery)->count(),
          'open' => (clone $baseQuery)->whereIn('request_status', [TicketStatus::Open, TicketStatus::Reopened])->count(),
          'accepted_by_me' => (clone $baseQuery)->whereHas('personnel', fn($q) => $q->where('profile_id', $profileId))->count(),
          'accepted_by_others' => (clone $baseQuery)->whereHas('personnel', fn($q) => $q->where('profile_id', '!=', $profileId))->count(),
          'closed' => (clone $baseQuery)->whereIn('query_status', [TicketStatus::Resolved, TicketStatus::Cancelled])->count(),
      ];

      return response()->json([
          'data' => TicketResource::collection($tickets),
          'meta' => [
              'total' => $tickets->total(),
              'per_page' => $tickets->perPage(),
              'current_page' => $tickets->currentPage(),
              'last_page' => $tickets->lastPage(),
              'counts' => $counts,
          ],
      ]);
    }

    public function store(StoreTicketRequest $request) {
      // Gate::authorize('it_service_store');
      
      $data = $request->validated();

      $data['ticket_number'] = Ticket::generateTicketNumber();

      $ticket = Ticket::create($data);

      return new TicketResource($ticket);
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket) {
      // Gate::authorize('it_service_update');

      $data = $request->validated();

      $ticket->update($data);

      return new TicketResource($ticket);
    }

    public function destroy(Ticket $ticket) {
      // Gate::authorize('it_service_destroy');

      $ticket->delete();
      
      return new TicketResource($ticket);
    }

    public function accept(Request $request, Ticket $ticket) {
      $profile = Auth::user()->profile;

        if (!$profile) {
            return response()->json(['error' => 'Profile not found.'], 404);
        }

        $alreadyAccepted = $ticket->personnel()->where('profile_id', $profile->id)->exists();

        if (!$alreadyAccepted) {
            $ticket->personnel()->attach($profile->id);

            if ($ticket->personnel()->count() === 1) {
                $ticket->update([
                    'query_status' => TicketStatus::InProgress,
                    'request_status' => TicketStatus::Accepted,
                ]);
            }
        }

        ProfileEngagementService::syncTicket($ticket);

        return new TicketResource($ticket);
    }

    public function checkStock(Request $request, Ticket $ticket) {
      $ticket->update([
          'query_status' => TicketStatus::CheckingStock,
      ]);

      // ?? Consider this action if while personnel is checking stock should be able to accept other tickets

      return new TicketResource($ticket);
    }

    public function awaitPart(Request $request, Ticket $ticket) {
      $ticket->update([
          'query_status' => TicketStatus::AwaitingPart,
      ]);

      ProfileEngagementService::syncTicket($ticket);

      return new TicketResource($ticket);      
    }

    public function resolve(ResolveTicketRequest $request, Ticket $ticket) {
      $data = $request->validated();

      $data['query_status'] = TicketStatus::Resolved;
      $data['request_status'] = TicketStatus::Closed;

      $ticket->update($data);

      ProfileEngagementService::syncTicket($ticket);

      return new TicketResource($ticket);
    }

    public function cancel(Request $request, Ticket $ticket) {
      $ticket->update([
          'query_status' => TicketStatus::Cancelled,
          'request_status' => TicketStatus::Closed,
      ]);

      ProfileEngagementService::syncTicket($ticket);

      return new TicketResource($ticket);
    }

    public function reopen(Request $request, Ticket $ticket) {
      $ticket->update([
            'query_status' => TicketStatus::InProgress,
            'request_status' => TicketStatus::Reopened,
        ]);

        ProfileEngagementService::syncTicket($ticket);

        return new TicketResource($ticket);
    }

    public function setServiceMethod(SetTicketServiceMethodRequest $request, Ticket $ticket) {
      $data = $request->validated();

      $ticket->update($data);

      return new TicketResource($ticket);
    }

    public function setReleaseDate(SetTicketReleaseDateRequest $request, Ticket $ticket) {
      $data = $request->validated();

      $ticket->update($data);

      return new TicketResource($ticket);
    }
}
