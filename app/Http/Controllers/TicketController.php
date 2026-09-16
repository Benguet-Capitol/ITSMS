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
use App\Models\TicketAssessment;
use App\Notifications\TicketPersonnelJoinedNotification;
use App\Services\HrisClientService;
use App\Services\PdfImageService;
use App\Services\ProfileEngagementService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class TicketController extends Controller
{
    public function index(Request $request, HrisClientService $hris)
    {
        Gate::authorize('tickets.view');

        // HRIS is a real external system this app has no control over --
        // when it's unreachable/erroring, the ticket list itself (which
        // has nothing to do with HRIS) shouldn't 500 for every user.
        // Degrade gracefully (no employee-name enrichment) and tell the
        // frontend via meta so it can show a "this isn't an ITSMS problem"
        // notice instead of a blank/broken table.
        $hrisUnavailable = false;

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

            $baseQuery->where(function ($q) use ($search, $hris, &$hrisUnavailable) {
                $like = "%{$search}%";

                // Ticket-level fields
                $q->where('concern', 'LIKE', $like)
                    ->orWhere('ticket_number', 'LIKE', $like)
                    ->orWhere('full_name', 'LIKE', $like)
                    ->orWhere('client_name', 'LIKE', $like);

                // If search has letters, also match HRIS employees
                if (preg_match('/[a-zA-Z]/', $search)) {
                    try {
                        $employeeIds = collect($hris->searchEmployees($search))
                            ->filter(fn ($e) => isset($e['id']))
                            ->pluck('id')
                            ->map(fn ($v) => (int) $v)
                            ->values()
                            ->all();

                        if (! empty($employeeIds)) {
                            $q->orWhereHas('inventory', function ($inv) use ($employeeIds) {
                                $inv->whereIn('employee_id', $employeeIds)
                                    ->orWhereHas('parent_component', function ($pc) use ($employeeIds) {
                                        $pc->whereIn('employee_id', $employeeIds);
                                    });
                            });
                        }
                    } catch (\Throwable $e) {
                        $hrisUnavailable = true;
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
            ->select('tickets.*')
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
                'personnel as accepted_by_me' => fn ($q) => $q->where('profile_id', $profileId),
                'personnel as personnel_count',
            ]);

        if ($request->filled('tab')) {
            switch ($request->tab) {
                case 'accepted_by_me':
                    $query->whereHas('personnel', fn ($q) => $q->where('profile_id', $profileId));
                    break;

                case 'accepted_by_others':
                    $query->whereHas('personnel', fn ($q) => $q->where('profile_id', '!=', $profileId));
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
            'complexity_level_id' => 'complexity_level_id',
            'service_method' => 'service_method',
            'date' => 'date',
            'accepted_at' => 'accepted_at',
            'resolved_at' => 'resolved_at',
            'created_at' => 'created_at',
        ];

        if ($request->filled('sort')) {
            $sortKey = $request->input('sort');
            $order = $request->input('order', 'asc') === 'desc' ? 'desc' : 'asc';

            if (isset($sortable[$sortKey])) {
                if ($sortKey === 'property_number') {
                    // leftJoin, not join: an inner join here silently
                    // dropped every ticket with no inventory_id (other-
                    // agency tickets, or tickets whose inventory was since
                    // deleted) from the entire list whenever this column
                    // was sorted, not just reordered them.
                    $query->leftJoin('inventories', 'tickets.inventory_id', '=', 'inventories.id')
                        ->orderBy('inventories.property_number', $order);
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

        try {
            $employeeMap = collect($hris->getEmployeesCached(10))
                ->filter(fn ($e) => isset($e['id']))
                ->keyBy(fn ($e) => (int) $e['id']);
        } catch (\Throwable $e) {
            $hrisUnavailable = true;
            $employeeMap = collect();
        }

        $request->attributes->set('employeeMap', $employeeMap);

        $counts = [
            'all' => (clone $baseQuery)->count(),
            'open' => (clone $baseQuery)->whereIn('request_status', [TicketStatus::Open, TicketStatus::Reopened])->count(),
            'accepted_by_me' => (clone $baseQuery)->whereHas('personnel', fn ($q) => $q->where('profile_id', $profileId))->count(),
            'accepted_by_others' => (clone $baseQuery)->whereHas('personnel', fn ($q) => $q->where('profile_id', '!=', $profileId))->count(),
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
                'hris_unavailable' => $hrisUnavailable,
            ],
        ]);
    }

    public function store(StoreTicketRequest $request)
    {
        Gate::authorize('tickets.create');

        $data = $request->validated();

        $attempts = 0;

        while (true) {
            $data['ticket_number'] = Ticket::generateTicketNumber();

            try {
                $ticket = Ticket::create($data);
                break;
            } catch (UniqueConstraintViolationException $e) {
                if (++$attempts >= 5) {
                    throw $e;
                }
            }
        }

        return new TicketResource($ticket);
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket)
    {
        Gate::authorize('tickets.update');

        $data = $request->validated();

        $ticket->update($data);

        return new TicketResource($ticket);
    }

    public function show(Ticket $ticket)
    {
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

    public function destroy(Ticket $ticket)
    {
        Gate::authorize('tickets.delete');

        $ticket->delete();

        return new TicketResource($ticket);
    }

    public function accept(Request $request, Ticket $ticket)
    {
        Gate::authorize('tickets.update');
        $profile = Auth::user()->profile;

        if (! $profile) {
            return response()->json(['error' => 'Profile not found.'], 404);
        }

        $alreadyAccepted = $ticket->personnel()->where('profile_id', $profile->id)->exists();

        if (! $alreadyAccepted) {
            $ticket->personnel()->attach($profile->id);

            if ($ticket->personnel()->count() === 1) {
                $ticket->update([
                    'query_status' => TicketStatus::InProgress,
                    'request_status' => TicketStatus::Accepted,
                    'accepted_at' => now(),
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

    public function unaccept(Request $request, Ticket $ticket)
    {
        Gate::authorize('tickets.update');

        $profile = Auth::user()->profile;

        if (! $profile) {
            return response()->json(['error' => 'Profile not found.'], 404);
        }

        $isAccepted = $ticket->personnel()->where('profile_id', $profile->id)->exists();

        if (! $isAccepted) {
            return response()->json([
                'error' => 'You have not accepted this ticket.',
            ], 422);
        }

        $ticket->personnel()->detach($profile->id);

        if ($ticket->personnel()->count() === 0 && $ticket->request_status === TicketStatus::Accepted) {
            $ticket->update([
                'query_status' => TicketStatus::Queued,
                'request_status' => TicketStatus::Open,
                'accepted_at' => null,
            ]);
        }

        ProfileEngagementService::syncTicket($ticket);

        return new TicketResource($ticket);
    }

    public function checkStock(Request $request, Ticket $ticket)
    {
        Gate::authorize('tickets.update');
        $ticket->update([
            'query_status' => TicketStatus::CheckingStock,
        ]);

        ProfileEngagementService::syncTicket($ticket);

        // ?? Consider this action if while personnel is checking stock should be able to accept other tickets

        return new TicketResource($ticket);
    }

    public function awaitPart(Request $request, Ticket $ticket)
    {
        Gate::authorize('tickets.update');
        $ticket->update([
            'query_status' => TicketStatus::AwaitingPart,
        ]);

        ProfileEngagementService::syncTicket($ticket);

        return new TicketResource($ticket);
    }

    public function resolve(ResolveTicketRequest $request, Ticket $ticket)
    {
        Gate::authorize('tickets.update');
        $data = $request->validated();

        $data['query_status'] = TicketStatus::Resolved;
        $data['request_status'] = TicketStatus::Closed;
        $data['resolved_at'] = now();

        $ticket->update($data);

        ProfileEngagementService::syncTicket($ticket);

        return new TicketResource($ticket);
    }

    public function cancel(Request $request, Ticket $ticket)
    {
        Gate::authorize('tickets.update');
        $ticket->update([
            'query_status' => TicketStatus::Cancelled,
            'request_status' => TicketStatus::Closed,
        ]);

        ProfileEngagementService::syncTicket($ticket);

        return new TicketResource($ticket);
    }

    public function reopen(Request $request, Ticket $ticket)
    {
        Gate::authorize('tickets.update');
        $ticket->assessment()->delete();

        $ticket->update([
            'query_status' => TicketStatus::InProgress,
            'request_status' => TicketStatus::Reopened,
            // accepted_at is left untouched -- it records when the ticket
            // was originally accepted, which reopening doesn't change.
            // Only resolved_at is cleared, since the ticket is no longer
            // resolved.
            'resolved_at' => null,
        ]);

        ProfileEngagementService::syncTicket($ticket);

        return new TicketResource($ticket);
    }

    public function assess(AssessTicketRequest $request, Ticket $ticket)
    {
        Gate::authorize('tickets.update');
        $data = $request->validated();

        $user = Auth::user();
        $user_profile_designation = $user?->profile?->designation ?? '';
        $assessedBy = $user->profile?->formatted_name ?? $user->name;

        $payload = [
            ...$data,
            'assessed_by' => $assessedBy,
            'assessed_by_position' => $user_profile_designation,
        ];

        // control_number is generated once, on first creation -- editing an
        // existing assessment must never overwrite it.
        $existingAssessment = $ticket->assessment;

        if ($existingAssessment) {
            $existingAssessment->update($payload);
        } else {
            $ticket->assessment()->create([
                ...$payload,
                'control_number' => TicketAssessment::generateControlNumber(),
            ]);
        }

        // Update ticket status
        $ticket->update([
            'query_status' => TicketStatus::Assessed,
            'request_status' => TicketStatus::Closed,
        ]);

        ProfileEngagementService::syncTicket($ticket);

        $ticket->load('assessment');

        return new TicketResource($ticket);
    }

    public function setServiceMethod(SetTicketServiceMethodRequest $request, Ticket $ticket)
    {
        Gate::authorize('tickets.update');
        $data = $request->validated();

        $ticket->update($data);

        return new TicketResource($ticket);
    }

    public function setReleaseDate(SetTicketReleaseDateRequest $request, Ticket $ticket)
    {
        Gate::authorize('tickets.update');
        $data = $request->validated();

        $user = Auth::user();
        $data['released_by'] = $user->profile?->formatted_name ?? $user->name;

        $ticket->update($data);

        return new TicketResource($ticket);
    }

    public function assessmentReport(Ticket $ticket, HrisClientService $hris, PdfImageService $pdfImages)
    {
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

        if (! $ticket->assessment) {
            return response()->json([
                'message' => 'No assessment found for this ticket.',
            ], 404);
        }

        // Resolve employee from HRIS -- if it's unreachable, the report
        // should still generate (with employee/office details falling
        // back to "—" below) rather than fail the whole download.
        try {
            $employeeMap = collect($hris->getEmployeesCached())
                ->filter(fn ($employee) => isset($employee['id']))
                ->keyBy(fn ($employee) => (int) $employee['id']);
        } catch (\Throwable $e) {
            $employeeMap = collect();
        }

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
                        .($ticket->office_code ? " ({$ticket->office_code})" : '')
                    : (
                        $resolvedInventory?->office_name
                            ? $resolvedInventory->office_name
                                .($resolvedInventory->office_code
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

                    if (! $componentBrandModel) {
                        return null;
                    }

                    $parts = array_filter([
                        $componentBrandModel->brand?->name,
                        $componentBrandModel->name,
                        $componentBrandModel->specification,
                    ], fn ($value) => filled($value));

                    return ! empty($parts)
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

        if (! $brandModel) {
            $brandModelSource = $inventory?->brand_model
                ?? $parentInventory?->brand_model;

            if ($brandModelSource) {
                $parts = array_filter([
                    $brandModelSource->brand?->name,
                    $brandModelSource->name,
                    $brandModelSource->specification,
                ], fn ($value) => filled($value));

                $brandModel = ! empty($parts)
                    ? implode(' ', $parts)
                    : null;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Legacy/direct-field fallback
        |--------------------------------------------------------------------------
        */

        if (! $brandModel) {
            $fallbackSource = $inventory ?? $parentInventory;

            $parts = array_filter([
                $fallbackSource?->brand?->name
                    ?? $fallbackSource?->brand_name,
                $fallbackSource?->model,
                $fallbackSource?->specification
                    ?? $fallbackSource?->description,
            ], fn ($value) => filled($value));

            $brandModel = ! empty($parts)
                ? implode(' ', $parts)
                : null;
        }

        /*
        |--------------------------------------------------------------------------
        | Inline acquisition flag
        |--------------------------------------------------------------------------
        */

        if ($ticket->assessment->is_set) {
            $brandModel = ($brandModel ?: '—').' (Set)';
        }

        /*
        |--------------------------------------------------------------------------
        | Inline acquisition flag
        |--------------------------------------------------------------------------
        */

        if ($ticket->assessment->is_set) {
            $brandModel = ($brandModel ?: '—').' (Set)';
        }

        // Item type — inventory first, then parent, then ticket.
        $itemType = $inventory?->item_type?->type
            ?? $parentInventory?->item_type?->type
            ?? $ticket->item_type?->type
            ?? '—';

        $dateAcquired = $resolvedInventory?->date_acquired
            ? Carbon::parse($resolvedInventory->date_acquired)
                ->format('F d, Y')
            : '—';

        $data = [
            'ticket' => $ticket,
            'assessment' => $ticket->assessment,
            'date' => $ticket->assessment->created_at?->format('F d, Y') ?? '—',
            'control_no' => $ticket->assessment->control_number ?? $ticket->ticket_number,
            'office' => $office,
            'item_name' => $itemType,
            'property_no' => $inventory?->property_number
                ?? $parentInventory?->property_number
                ?? '—',
            'date_acquired' => $dateAcquired,
            'issued_to' => $issuedTo,
            'brand_model' => $brandModel ?? '—',
            'serial_number' => $inventory?->serial_number
                ?? $parentInventory?->serial_number
                ?? '—',
            'concern' => $ticket->concern,
            'components' => $ticket->assessment->components ?? [],
            'component_findings' => $ticket->assessment->component_findings ?? [],
            ...self::assessmentComponentCategories(),
            ...$pdfImages->agencyLogos(),
        ];

        // "Short" bond paper or Letter(8.5x11in),
        $pdf = Pdf::loadView('reports.ticket-assessment', $data)
            ->setPaper('letter', 'portrait');

        $filename = 'Assessment-'
            .$ticket->ticket_number
            .'-'
            .now()->format('Y-m-d_Hi')
            .'.pdf';

        return $pdf->download($filename, [
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * The fixed component checklist shown on the assessment report and in
     * the assess modal. This is the backend's single copy -- the frontend
     * (components/tickets/AssessModal.vue) maintains its own SYSTEM_UNIT_PARTS/
     * PERIPHERALS/LAPTOP_PARTS/MOBILE_PARTS constants that must match these
     * exactly, since there's no shared runtime between the two apps.
     * tests/Feature/Ticket/AssessmentComponentLabelsMatchFrontendTest.php
     * pins the frontend's current values and fails if this array drifts
     * from them.
     *
     * @return array<string, array<int, string>>
     */
    public static function assessmentComponentCategories(): array
    {
        return [
            'system_unit_parts' => [
                'PROCESSOR',
                'RAM/ Memory Module',
                'SOLID STATE DRIVE',
                'HARD DISK',
                'VIDEO CARD',
                'POWER SUPPLY',
                'MOTHERBOARD',
                'OPTICAL DRIVE',
                'OTHERS (System Unit)',
            ],
            'peripherals' => [
                'MONITOR',
                'KEYBOARD',
                'MOUSE',
                'SPEAKER',
                'USB/FLASHDRIVE',
                'AVR',
                'UPS',
                'PRINTER',
                'SCANNER',
                'Router / Switch',
                'OTHERS (Peripherals)',
            ],
            'laptop_parts' => [
                'BATTERY (Laptop)',
                'KEYBOARD (Laptop)',
                'TOUCHPAD',
                'LCD/SCREEN',
                'HINGE',
                'RAM/ Memory Module (Laptop)',
                'SOLID STATE DRIVE (Laptop)',
                'HARD DISK (Laptop)',
                'CHARGER/ADAPTER (Laptop)',
                'WEBCAM',
                'COOLING FAN',
                'MOTHERBOARD (Laptop)',
                'OTHERS (Laptop)',
            ],
            'mobile_parts' => [
                'BATTERY (Mobile)',
                'SCREEN/DIGITIZER',
                'CHARGING PORT',
                'CHARGER/ADAPTER (Mobile)',
                'SIM TRAY',
                'CAMERA',
                'SPEAKER (Mobile)',
                'MICROPHONE',
                'BUTTONS (Power/Volume)',
                'OTHERS (Mobile)',
            ],
        ];
    }

    /**
     * Return high-level ticket metrics for the dashboard.
     */
    public function dashboardSummary()
    {
        Gate::authorize('tickets.view');

        $openStatuses = [
            TicketStatus::Open->value,
            TicketStatus::Reopened->value,
        ];

        return response()->json([
            'open_tickets' => Ticket::query()
                ->whereIn('request_status', $openStatuses)
                ->count(),

            'unassigned_tickets' => Ticket::query()
                ->whereIn('request_status', $openStatuses)
                ->doesntHave('personnel')
                ->count(),

            'awaiting_parts' => Ticket::query()
                ->where('query_status', TicketStatus::AwaitingPart->value)
                ->count(),

            'resolved_today' => Ticket::query()
                ->where('query_status', TicketStatus::Resolved->value)
                ->whereDate('updated_at', today())
                ->count(),

            'trend' => $this->dashboardTrend(),

            'breakdown' => Ticket::query()
                ->selectRaw('query_status, COUNT(*) as count')
                ->groupBy('query_status')
                ->pluck('count', 'query_status'),
        ]);
    }

    /**
     * Daily created-vs-resolved counts for the last 14 days (inclusive of
     * today), for the dashboard trend chart. `resolved_at` (not
     * `updated_at`) is used for the resolved series -- it's a dedicated
     * timestamp set only by TicketController::resolve() and cleared on
     * reopen, so it won't be skewed by unrelated field edits touching
     * `updated_at` the way the existing "Resolved Today" KPI card is.
     * MySQL's grouped query silently omits days with zero rows, so the
     * gaps are filled with 0 in PHP after the fact.
     */
    private function dashboardTrend(): array
    {
        $since = Carbon::today()->subDays(13);

        $createdByDay = Ticket::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as count')
            ->groupBy('day')
            ->pluck('count', 'day');

        $resolvedByDay = Ticket::query()
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', $since)
            ->selectRaw('DATE(resolved_at) as day, COUNT(*) as count')
            ->groupBy('day')
            ->pluck('count', 'day');

        $trend = [];

        for ($i = 13; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i)->toDateString();

            $trend[] = [
                'date' => $day,
                'created' => (int) ($createdByDay[$day] ?? 0),
                'resolved' => (int) ($resolvedByDay[$day] ?? 0),
            ];
        }

        return $trend;
    }
}
