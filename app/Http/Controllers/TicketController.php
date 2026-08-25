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
use App\Services\PdfImageService;
use App\Notifications\TicketPersonnelJoinedNotification;

class TicketController extends Controller
{
    public function index(Request $request, HrisClientService $hris) {
      Gate::authorize('tickets.view');

      $profileId = Auth::user()->profile->id;
      $baseQuery = Ticket::query()->with([
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
          'assessment',
      ]);

      if ($request->filled('search')) {
          $search = trim((string) $request->input('search', ''));

          $baseQuery->where(function ($q) use ($search, $hris) {
              $like = "%{$search}%";

              // Ticket-level fields
              $q->where('concern', 'LIKE', $like)
                ->orWhere('ticket_number', 'LIKE', $like)
                ->orWhere('full_name', 'LIKE', $like)
                ->orWhere('client_name', 'LIKE', $like);

              // If search has letters, also match HRIS employees
              if (preg_match('/[a-zA-Z]/', $search)) {
                  $employeeIds = collect($hris->searchEmployees($search))
                      ->filter(fn ($e) => isset($e['id']))
                      ->pluck('id')
                      ->map(fn ($v) => (int) $v)
                      ->values()
                      ->all();

                  if (!empty($employeeIds)) {
                      $q->orWhereHas('inventory', function ($inv) use ($employeeIds) {
                          $inv->whereIn('employee_id', $employeeIds)
                              ->orWhereHas('parent_component', function ($pc) use ($employeeIds) {
                                  $pc->whereIn('employee_id', $employeeIds);
                              });
                      });
                  }
              }

              // Property number/serial/IP on inventory or parent component
              $q->orWhereHas('inventory', function ($inv) use ($like) {
                  $inv->where('property_number', 'like', $like)
                      ->orWhere('serial_number', 'like', $like)
                      ->orWhere('ip_address', 'like', $like)
                      ->orWhereHas('parent_component', function ($pc) use ($like) {
                          $pc->where('property_number', 'like', $like)
                            ->orWhere('serial_number', 'like', $like)
                            ->orWhere('ip_address', 'like', $like);
                      });
              });
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

      $sortable = [
          'ticket_number' => 'ticket_number',
          'property_number' => 'property_number',   // special handling below
          'full_name' => 'full_name',
          'client' => 'client_name',
          'query_status' => 'query_status',
          'request_status' => 'request_status',
          'priority' => 'priority',
          'service_method' => 'service_method',
          'date' => 'date',
          'created_at' => 'created_at',
      ];

      if ($request->filled('sort')) {
          $sortKey = $request->input('sort');
          $order = $request->input('order', 'asc') === 'desc' ? 'desc' : 'asc';

          if (isset($sortable[$sortKey])) {
              if ($sortKey === 'property_number') {
                  // sort via inventories.property_number
                  $query->join('inventories', 'tickets.inventory_id', '=', 'inventories.id')
                        ->orderBy('inventories.property_number', $order)
                        ->select('tickets.*');
              } else {
                  $query->orderBy($sortable[$sortKey], $order);
              }
          } else {
              $query->latest();
          }
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

    public function show(Ticket $ticket) {
        Gate::authorize('tickets.view');

        $profileId = Auth::user()->profile->id;

        $ticket = Ticket::query()
            ->with([
                'profile',
                'inventory.parent_component',
                'itService',
                'personnel',
                'item_type',
                'solution',
                'agency',
                'assessment',
            ])
            ->withCount([
                'personnel as personnel_count',

                'personnel as accepted_by_me' => fn ($query) => $query->where('profile_id', $profileId),
            ])
            ->findOrFail($ticket->id);

        return TicketResource::make($ticket);
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

        $existingPersonnel = $ticket->personnel()
          ->where('profile_id', '!=', $request->user()->profile->id)
          ->get();

        if ($existingPersonnel->isNotEmpty()) {
            $joinedProfile = $request->user()->profile;
            foreach ($existingPersonnel as $profile) {
                $profile->user->notify(new TicketPersonnelJoinedNotification($ticket, $joinedProfile));
            }
        }

        return new TicketResource($ticket);
    }

    public function unaccept(Request $request, Ticket $ticket) {
        Gate::authorize('tickets.update');

        $profile = Auth::user()->profile;

        if (!$profile) {
            return response()->json(['error' => 'Profile not found.'], 404);
        }

        $isAccepted = $ticket->personnel()->where('profile_id', $profile->id)->exists();

        if (!$isAccepted) {
            return response()->json([
                'error' => 'You have not accepted this ticket.',
            ], 422);
        }

        $ticket->personnel()->detach($profile->id);

        if ($ticket->personnel()->count() === 0 && $ticket->request_status === TicketStatus::Accepted) {
            $ticket->update([
                'query_status' => TicketStatus::Queued,
                'request_status' => TicketStatus::Open,
            ]);
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

    public function assessmentReport(Ticket $ticket, HrisClientService $hris, PdfImageService $pdfImages) {
        Gate::authorize('tickets.view');

        $ticket->load([
            'assessment',

            'inventory',
            'inventory.item_type',
            'inventory.brand_model',
            'inventory.brand_model.item_type',
            'inventory.brand_model.brand',

            'inventory.internal_components',
            'inventory.internal_components.brand_model',
            'inventory.internal_components.brand_model.brand',

            'inventory.parent_component',
            'inventory.parent_component.item_type',
            'inventory.parent_component.brand_model',
            'inventory.parent_component.brand_model.item_type',
            'inventory.parent_component.brand_model.brand',

            'inventory.parent_component.internal_components',
            'inventory.parent_component.internal_components.brand_model',
            'inventory.parent_component.internal_components.brand_model.brand',

            'item_type',
            'profile',
            'agency',
        ]);

        if (!$ticket->assessment) {
            return response()->json([
                'message' => 'No assessment found for this ticket.',
            ], 404);
        }

        // Resolve employee from HRIS.
        $employeeMap = collect($hris->getEmployeesCached())
            ->filter(fn ($employee) => isset($employee['id']))
            ->keyBy(fn ($employee) => (int) $employee['id']);

        $inventory = $ticket->inventory;
        $parentInventory = $inventory?->parent_component;

        // When the ticket inventory is a child component, use its parent
        // as the primary source for inventory-level details.
        $resolvedInventory = $parentInventory ?? $inventory;

        $employeeId = $inventory?->employee_id
            ?? $parentInventory?->employee_id
            ?? $ticket->employee_id
            ?? null;

        $employee = $employeeMap->get((int) $employeeId);

        $office = $ticket->is_other_agency
            ? ($ticket->agency?->name ?? $ticket->agency?->abbreviation ?? '—')
            : (
                $ticket->office_desc
                    ? $ticket->office_desc
                        . ($ticket->office_code ? " ({$ticket->office_code})" : '')
                    : (
                        $resolvedInventory?->office_name
                            ? $resolvedInventory->office_name
                                . ($resolvedInventory->office_code
                                    ? " ({$resolvedInventory->office_code})"
                                    : '')
                            : (data_get($employee, 'office_desc') ?? '—')
                    )
            );

        $issuedTo = data_get($employee, 'fullname')
            ?? data_get($employee, 'full_name')
            ?? $ticket->client_name
            ?? $ticket->full_name
            ?? '—';

        /*
        |--------------------------------------------------------------------------
        | Model / Description
        |--------------------------------------------------------------------------
        |
        | Only when the TICKET'S OWN inventory item's item_type has
        | supports_internal_components = true do we build the description
        | from its internal components. Everything else (UPS, Monitor,
        | Printer, etc.) uses its own brand_model directly, even if it
        | happens to be a child/parent component.
        |
        */

        $itemType = $inventory?->item_type ?? $ticket->item_type;

        $usesInternalComponents = (bool) ($itemType?->supports_internal_components ?? false);

        $brandModel = null;

        if ($usesInternalComponents && $inventory) {
            $componentDescriptions = $inventory->internal_components
                ->map(function ($component) {
                    $componentBrandModel = $component->brand_model;

                    if (!$componentBrandModel) {
                        return null;
                    }

                    $parts = array_filter([
                        $componentBrandModel->brand?->name,
                        $componentBrandModel->name,
                        $componentBrandModel->specification,
                    ], fn ($value) => filled($value));

                    return !empty($parts)
                        ? implode(' ', $parts)
                        : null;
                })
                ->filter()
                ->unique()
                ->values();

            if ($componentDescriptions->isNotEmpty()) {
                $brandModel = $componentDescriptions->implode(', ');
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Standard inventory brand-model (covers UPS and everything else)
        |--------------------------------------------------------------------------
        */

        if (!$brandModel) {
            $brandModelSource = $inventory?->brand_model
                ?? $parentInventory?->brand_model;

            if ($brandModelSource) {
                $parts = array_filter([
                    $brandModelSource->brand?->name,
                    $brandModelSource->name,
                    $brandModelSource->specification,
                ], fn ($value) => filled($value));

                $brandModel = !empty($parts)
                    ? implode(' ', $parts)
                    : null;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Legacy/direct-field fallback
        |--------------------------------------------------------------------------
        */

        if (!$brandModel) {
            $fallbackSource = $inventory ?? $parentInventory;

            $parts = array_filter([
                $fallbackSource?->brand?->name
                    ?? $fallbackSource?->brand_name,
                $fallbackSource?->model,
                $fallbackSource?->specification
                    ?? $fallbackSource?->description,
            ], fn ($value) => filled($value));

            $brandModel = !empty($parts)
                ? implode(' ', $parts)
                : null;
        }

        /*
        |--------------------------------------------------------------------------
        | Inline acquisition flag
        |--------------------------------------------------------------------------
        */

        if ($ticket->assessment->is_set) {
            $brandModel = ($brandModel ?: '—') . ' (Set)';
        }

        /*
        |--------------------------------------------------------------------------
        | Inline acquisition flag
        |--------------------------------------------------------------------------
        */

        if ($ticket->assessment->is_set) {
            $brandModel = ($brandModel ?: '—') . ' (Set)';
        }

        // Item type — inventory first, then parent, then ticket.
        $itemType = $inventory?->item_type?->type
            ?? $parentInventory?->item_type?->type
            ?? $ticket->item_type?->type
            ?? '—';

        $dateAcquired = $resolvedInventory?->date_acquired
            ? \Carbon\Carbon::parse($resolvedInventory->date_acquired)
                ->format('F d, Y')
            : '—';

        $data = [
            'ticket'        => $ticket,
            'assessment'    => $ticket->assessment,
            'date' => $ticket->assessment->created_at?->format('F d, Y') ?? '—',
            'control_no'    => $ticket->ticket_number,
            'office'        => $office,
            'item_name'     => $itemType,
            'property_no'   => $inventory?->property_number
                ?? $parentInventory?->property_number
                ?? '—',
            'date_acquired' => $dateAcquired,
            'issued_to'     => $issuedTo,
            'brand_model'   => $brandModel ?? '—',
            'serial_number' => $inventory?->serial_number
                ?? $parentInventory?->serial_number
                ?? '—',
            'concern'       => $ticket->concern,
            'components'    => $ticket->assessment->components ?? [],
            'system_unit_parts' => [
                'PROCESSOR',
                'RAM/ Memory Module',
                'SOLID STATE DRIVE',
                'HARD DISK',
                'VIDEO CARD',
                'POWER SUPPLY',
                'MOTHERBOARD',
                'OPTICAL DRIVE',
                'MONITOR',
                'OTHERS',
            ],
            'peripherals' => [
                'KEYBOARD',
                'MOUSE',
                'SPEAKER',
                'USB/FLASHDRIVE',
                'AVR',
                'UPS',
                'PRINTER',
                'SCANNER',
                'Router / Switch',
                'OTHERS',
            ],
            ...$pdfImages->agencyLogos(),
        ];

        $pdf = Pdf::loadView('reports.ticket-assessment', $data)
            ->setPaper('a4', 'portrait');

        $filename = 'Assessment-'
            . $ticket->ticket_number
            . '-'
            . now()->format('Y-m-d_Hi')
            . '.pdf';

        return $pdf->download($filename, [
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
