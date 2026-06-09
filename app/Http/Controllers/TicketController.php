<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Http\Requests\AssessTicketRequest;
use App\Http\Requests\ResolveTicketRequest;
use App\Http\Requests\SetTicketReleaseDateRequest;
use App\Http\Requests\SetTicketServiceMethodRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\HrisClientService;
use App\Services\ProfileEngagementService;
use Barryvdh\DomPDF\Facade\Pdf; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class TicketController extends Controller
{
    public function index(Request $request, HrisClientService $hris) {
      Gate::authorize('tickets.view');

      $profileId = Auth::user()->profile->id;
      $baseQuery = Ticket::query();

      if ($request->filled('search')) {
          $search = $request->search;
          $baseQuery->where(function ($q) use ($search) {
              $q->where('concern', 'LIKE', "%{$search}%")
                ->orWhere('ticket_number', 'LIKE', "%{$search}%")
                ->orWhere('full_name', 'LIKE', "%{$search}%");
          });
      }

      $query = (clone $baseQuery)
          ->with([
              'profile',
              'inventory',
              'inventory.item_type',
              'inventory.brand_model',
              'inventory.parent_component',
              'inventory.parent_component.item_type',
              'inventory.parent_component.brand_model',
              'agency',
              'itService',
              'solution',
              'solution.author',
              'personnel',
          ])
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

      if ($request->filled('query_status')) {
          $query->where('query_status', $request->query_status);
      }

      if ($request->filled('sort')) {
          $order = $request->input('order', 'asc');
          $query->orderBy($request->sort, $order);
      } else {
          $query->latest();
      }

      $perPage = $request->input('per_page', 10);
      $currentPage = $request->input('page', 1);
      $tickets = $query
          ->paginate($perPage, ['*'], 'page', $currentPage)
          ->appends($request->query());

      $employeeMap = collect($hris->getEmployeesCached(10))
        ->filter(fn ($e) => isset($e['id']))
        ->keyBy(fn ($e) => (int) $e['id']);

      $request->attributes->set('employeeMap', $employeeMap);

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
      Gate::authorize('tickets.create');
      
      $data = $request->validated();

      $data['ticket_number'] = Ticket::generateTicketNumber();

      $ticket = Ticket::create($data);

      return new TicketResource($ticket);
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket) {
      Gate::authorize('tickets.update');

      $data = $request->validated();

      $ticket->update($data);

      return new TicketResource($ticket);
    }

    public function destroy(Ticket $ticket) {
      Gate::authorize('tickets.delete');

      $ticket->delete();
      
      return new TicketResource($ticket);
    }

    public function accept(Request $request, Ticket $ticket) {
      Gate::authorize('tickets.update');
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
      Gate::authorize('tickets.update');
      $ticket->update([
          'query_status' => TicketStatus::CheckingStock,
      ]);

      // ?? Consider this action if while personnel is checking stock should be able to accept other tickets

      return new TicketResource($ticket);
    }

    public function awaitPart(Request $request, Ticket $ticket) {
      Gate::authorize('tickets.update');
      $ticket->update([
          'query_status' => TicketStatus::AwaitingPart,
      ]);

      ProfileEngagementService::syncTicket($ticket);

      return new TicketResource($ticket);      
    }

    public function resolve(ResolveTicketRequest $request, Ticket $ticket) {
      Gate::authorize('tickets.update');
      $data = $request->validated();

      $data['query_status'] = TicketStatus::Resolved;
      $data['request_status'] = TicketStatus::Closed;

      $ticket->update($data);

      ProfileEngagementService::syncTicket($ticket);

      return new TicketResource($ticket);
    }

    public function cancel(Request $request, Ticket $ticket) {
      Gate::authorize('tickets.update');
      $ticket->update([
          'query_status' => TicketStatus::Cancelled,
          'request_status' => TicketStatus::Closed,
      ]);

      ProfileEngagementService::syncTicket($ticket);

      return new TicketResource($ticket);
    }

    public function reopen(Request $request, Ticket $ticket) {
      Gate::authorize('tickets.update');
      $ticket->assessment()->delete();

      $ticket->update([
            'query_status' => TicketStatus::InProgress,
            'request_status' => TicketStatus::Reopened,
        ]);

        ProfileEngagementService::syncTicket($ticket);

        return new TicketResource($ticket);
    }

    public function assess(AssessTicketRequest $request, Ticket $ticket, HrisClientService $hris) {
        Gate::authorize('tickets.update');
        $data = $request->validated();

        // Resolve assessed_by — match by employee_id/employee_id_number, fallback to auth user name
        $user           = Auth::user();
        $user_profile_designation = $user?->profile?->designation ?? '';
        $authEmployeeId = (string) ($user?->profile?->employee_id ?? '');

        $authEmployee = collect($hris->getEmployees())
            ->first(function ($e) use ($authEmployeeId) {
                return (string) ($e['employee_id'] ?? $e['employee_id_number'] ?? $e['id'] ?? '') === $authEmployeeId;
            });

        $assessedBy = $user->profile?->formatted_name ?? $user->name;

        // Create or update assessment
        $ticket->assessment()->updateOrCreate(
            ['ticket_id' => $ticket->id],
            [
                ...$data,
                'assessed_by' => $assessedBy,
                'assessed_by_position' => $user_profile_designation,
            ]
        );

        // Update ticket status
        $ticket->update([
            'query_status'   => TicketStatus::Assessed,
            'request_status' => TicketStatus::Closed,
        ]);

        ProfileEngagementService::syncTicket($ticket);

        $ticket->load('assessment');

        return new TicketResource($ticket);
    }

    public function setServiceMethod(SetTicketServiceMethodRequest $request, Ticket $ticket) {
      Gate::authorize('tickets.update');
      $data = $request->validated();

      $ticket->update($data);

      return new TicketResource($ticket);
    }

    public function setReleaseDate(SetTicketReleaseDateRequest $request, Ticket $ticket) {
      Gate::authorize('tickets.update');
      $data = $request->validated();

      $ticket->update($data);

      return new TicketResource($ticket);
    }

    public function assessmentReport(Ticket $ticket, HrisClientService $hris) {
        Gate::authorize('tickets.view');
        $ticket->load([
            'assessment',
            'inventory',
            'inventory.item_type',
            'inventory.brand_model',
            'inventory.brand_model.item_type',
            'item_type',
            'profile',
        ]);

        if (!$ticket->assessment) {
            return response()->json(['message' => 'No assessment found for this ticket.'], 404);
        }

        // Resolve employee from HRIS
        $employeeMap = collect($hris->getEmployeesCached())
            ->filter(fn ($e) => isset($e['id']))
            ->keyBy(fn ($e) => (int) $e['id']);

        // Employee is on the inventory (not directly on ticket)
        $employeeId = $ticket->inventory?->employee_id ?? $ticket->employee_id ?? null;
        $employee   = $employeeMap->get((int) $employeeId);

        // Item type — prefer inventory, fall back to ticket
        $itemType = $ticket->inventory?->item_type?->type
            ?? $ticket->item_type?->type
            ?? '—';

        // Brand/model
        $brandModel = null;
        if ($ticket->inventory?->brand_model) {
            $bm = $ticket->inventory->brand_model;
            $brandModel = $bm->name
                ? "{$bm->item_type?->type} {$bm->specification}, {$bm->name}"
                : "{$bm->item_type?->type}, {$bm->specification}";
        }

        $data = [
            'ticket'       => $ticket,
            'assessment'   => $ticket->assessment,
            'date'         => now()->format('F d, Y'),
            'control_no'   => $ticket->ticket_number,
            'office'       => data_get($employee, 'office_desc') ?? '—',
            'item_name'    => $itemType,
            'property_no'  => $ticket->inventory?->property_number ?? '—',
            'date_acquired'=> $ticket->inventory?->date_acquired
                                ? \Carbon\Carbon::parse($ticket->inventory->date_acquired)->format('F d, Y')
                                : '—',
            'issued_to'    => data_get($employee, 'fullname')
                                ?? data_get($employee, 'full_name')
                                ?? $ticket->full_name
                                ?? '—',
            'brand_model'  => $brandModel ?? '—',
            'serial_number'=> $ticket->inventory?->serial_number ?? '—',
            'concern'      => $ticket->concern,
            'components'   => $ticket->assessment->components ?? [],
            'system_unit_parts' => [
                'PROCESSOR', 'RAM/ Memory Module', 'SOLID STATE DRIVE',
                'HARD DISK', 'VIDEO CARD', 'POWER SUPPLY',
                'MOTHERBOARD', 'OPTICAL DRIVE', 'MONITOR', 'OTHERS',
            ],
            'peripherals' => [
                'KEYBOARD', 'MOUSE', 'SPEAKER', 'USB/FLASHDRIVE',
                'AVR', 'UPS', 'PRINTER', 'SCANNER', 'Router / Switch', 'OTHERS',
            ],
        ];

        $pdf = Pdf::loadView('reports.ticket-assessment', $data)
            ->setPaper('a4', 'portrait');

        $filename = 'Assessment-' . $ticket->ticket_number . '-' . now()->format('Y-m-d_Hi') . '.pdf';

        return $pdf->download($filename, [
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
