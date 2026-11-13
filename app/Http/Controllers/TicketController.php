<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Http\Requests\AssessTicketRequest;
use App\Http\Requests\CancelTicketRequest;
use App\Http\Requests\ResolveTicketRequest;
use App\Http\Requests\SetTicketReleaseDateRequest;
use App\Http\Requests\SetTicketServiceMethodRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Resources\ProfileResource;
use App\Http\Resources\TicketResource;
use App\Models\Profile;
use App\Models\Ticket;
use App\Models\TicketAssessment;
use App\Notifications\TicketAssignedNotification;
use App\Notifications\TicketCompletedNotification;
use App\Notifications\TicketPersonnelJoinedNotification;
use App\Services\ActivityLogger;
use App\Services\HrisClientService;
use App\Services\PdfImageService;
use App\Services\ProfileEngagementService;
use App\Services\TicketDashboardService;
use App\Services\WorkReminders;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

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
            'relatedTicket',
            'recurrences',
            'resolutions.solution',
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

                    // Deleted tickets, for restoring; only to those who may.
                case 'deleted':
                    Gate::authorize('tickets.restore');
                    $query->onlyTrashed();
                    break;
            }
        }

        if ($request->filled('query_status')) {
            if ($request->query_status === TicketStatus::Reopened->value) {
                $query->where('request_status', TicketStatus::Reopened);
            } else {
                $query->where('query_status', $request->query_status);
            }
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
            'deleted' => Gate::allows('tickets.restore') ? (clone $baseQuery)->onlyTrashed()->count() : 0,
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

    public function search(Request $request)
    {
        Gate::authorize('tickets.search');

        $query = trim($request->string('q')->toString());

        $limit = min(max($request->integer('limit', 20), 1), 100);

        $tickets = Ticket::query()
            ->without(['profile', 'employee', 'inventory.parent_component', 'itService', 'personnel', 'item_type', 'solution', 'agency', 'complexityLevel'])
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($builder) use ($query) {
                    $builder
                        ->where('ticket_number', 'like', "%{$query}%")
                        ->orWhere('concern', 'like', "%{$query}%");
                });
            })
            ->when($request->filled('exclude'), function ($builder) use ($request) {
                $builder->whereKeyNot($request->input('exclude'));
            })
            ->latest()
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $tickets->map(fn (Ticket $ticket) => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'concern' => $ticket->concern,
                'query_status' => $ticket->query_status,
                'request_status' => $ticket->request_status,
                'resolved_at' => $ticket->resolved_at,
            ]),
        ]);
    }

    /**
     * One ticket, for the ticket view page: everything the list row has
     * (employee names from HRIS included) plus its history.
     */
    public function show(Request $request, Ticket $ticket, HrisClientService $hris)
    {
        Gate::authorize('tickets.view');

        if ($ticket->trashed()) {
            Gate::authorize('tickets.restore');
        }

        // Same HRIS enrichment as index(); the ticket still loads without it.
        $hrisUnavailable = false;

        try {
            $employeeMap = collect($hris->getEmployeesCached(10))
                ->filter(fn ($e) => isset($e['id']))
                ->keyBy(fn ($e) => (int) $e['id']);
        } catch (\Throwable $e) {
            $hrisUnavailable = true;
            $employeeMap = collect();
        }

        $request->attributes->set('employeeMap', $employeeMap);

        $profileId = Auth::user()->profile->id;

        $ticket = Ticket::withTrashed()
            ->with([
                'profile',
                'inventory.parent_component',
                'itService',
                'personnel',
                'item_type',
                'solution',
                'agency',
                'assessment',
                'resolutions.solution',
                'relatedTicket',
                'recurrences',
            ])
            ->withCount([
                'personnel as personnel_count',

                'personnel as accepted_by_me' => fn ($query) => $query->where('profile_id', $profileId),
            ])
            ->findOrFail($ticket->id);

        // Merged in rather than ->additional(), which would wrap the ticket
        // in "data" and change the shape existing callers read.
        return response()->json([
            ...TicketResource::make($ticket)->resolve($request),
            'meta' => ['hris_unavailable' => $hrisUnavailable],
        ]);
    }

    /**
     * Removes a duplicate ticket. Only one nobody has accepted or been
     * assigned to yet (Ticket::isDeletable()) -- anything worked on keeps
     * its history. The ticket's notifications ("new ticket") go with it.
     */
    public function destroy(Ticket $ticket)
    {
        Gate::authorize('tickets.delete');

        DB::transaction(function () use ($ticket) {
            // Locked so someone accepting it at the same moment can't slip
            // in between the check and the delete.
            $locked = Ticket::query()->setEagerLoads([])->lockForUpdate()->findOrFail($ticket->id);

            if (! $locked->isDeletable()) {
                abort(422, "Ticket {$locked->ticket_number} can't be deleted: someone has already accepted or worked on it. Only a ticket nobody has accepted yet can be deleted.");
            }

            DatabaseNotification::query()->where('data->ticket_id', $locked->id)->delete();

            $locked->delete();
        });

        return response()->json([
            'message' => "Ticket {$ticket->ticket_number} deleted.",
        ]);
    }

    public function accept(Request $request, Ticket $ticket)
    {
        Gate::authorize('tickets.update');
        $profile = Auth::user()->profile;

        if (! $profile) {
            return response()->json(['error' => 'Profile not found.'], 404);
        }

        $this->ensureWorkflowAllows($ticket, 'accept');

        $ticket->personnel()->attach($profile->id);

        if ($ticket->personnel()->count() === 1) {
            $ticket->update([
                'query_status' => TicketStatus::InProgress,
                'request_status' => TicketStatus::Accepted,
                'accepted_at' => now(),
            ]);
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

        $this->ensureWorkflowAllows($ticket, 'unaccept');

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

    /**
     * An admin puts one or more technicians on the ticket. The first
     * person on a queued ticket starts it, as accepting does. Each new
     * technician is notified, except an admin assigning themselves.
     */
    public function assign(Request $request, Ticket $ticket)
    {
        Gate::authorize('tickets.assign');
        $this->ensureWorkflowAllows($ticket, 'assign');

        $admin = Auth::user()->profile;
        abort_unless($admin, 404, 'Profile not found.');

        $data = $request->validate([
            'profile_ids' => ['required', 'array', 'min:1'],
            'profile_ids.*' => [
                'integer',
                'distinct',
                Rule::in($this->assignableProfiles()->pluck('id')->all()),
            ],
        ], [
            'profile_ids.required' => 'Select at least one person to assign.',
            'profile_ids.*.in' => 'Only IT personnel who can accept tickets can be assigned.',
        ]);

        $newIds = array_values(array_diff(
            $data['profile_ids'],
            $ticket->personnel()->pluck('profiles.id')->all(),
        ));

        if (! $newIds) {
            abort(422, 'Everyone selected is already on this ticket.');
        }

        DB::transaction(function () use ($ticket, $admin, $newIds) {
            $ticket->personnel()->attach(
                array_fill_keys($newIds, ['assigned_by_profile_id' => $admin->id]),
            );

            $this->markAccepted($ticket);
        });

        $this->notifyAssigned($ticket, $admin, $newIds);

        return new TicketResource($ticket->refresh());
    }

    /**
     * Moves the ticket from one technician to another (e.g. the first is
     * out of the office). The ticket's status and timing don't change;
     * the new technician is notified.
     */
    public function transfer(Request $request, Ticket $ticket)
    {
        Gate::authorize('tickets.assign');
        $this->ensureWorkflowAllows($ticket, 'transfer');

        $admin = Auth::user()->profile;
        abort_unless($admin, 404, 'Profile not found.');

        $onTicket = $ticket->personnel()->pluck('profiles.id')->all();

        $data = $request->validate([
            'from_profile_id' => ['required', 'integer', Rule::in($onTicket)],
            'to_profile_id' => [
                'required',
                'integer',
                'different:from_profile_id',
                Rule::notIn($onTicket),
                Rule::in($this->assignableProfiles()->pluck('id')->all()),
            ],
        ], [
            'from_profile_id.required' => 'Select who to transfer the ticket from.',
            'from_profile_id.in' => 'That person is not on this ticket.',
            'to_profile_id.required' => 'Select who to transfer the ticket to.',
            'to_profile_id.different' => 'Pick a different person to transfer to.',
            'to_profile_id.not_in' => 'That person is already on this ticket.',
            'to_profile_id.in' => 'Only IT personnel who can accept tickets can be assigned.',
        ]);

        $from = Profile::findOrFail($data['from_profile_id']);

        DB::transaction(function () use ($ticket, $admin, $data) {
            $ticket->personnel()->detach($data['from_profile_id']);
            $ticket->personnel()->attach($data['to_profile_id'], ['assigned_by_profile_id' => $admin->id]);
        });

        ProfileEngagementService::sync($from);

        app(ActivityLogger::class)->addContext($ticket, [
            'transferred_from' => $from->display_name,
            'transferred_to' => Profile::whereKey($data['to_profile_id'])->value('display_name'),
        ]);

        $this->notifyAssigned($ticket, $admin, [$data['to_profile_id']], $from);

        return new TicketResource($ticket->refresh());
    }

    /** IT personnel an admin can assign: everyone whose roles grant tickets.accept. */
    public function personnelOptions()
    {
        Gate::authorize('tickets.assign');

        return ProfileResource::collection(
            $this->assignableProfiles()->orderBy('display_name')->get(),
        );
    }

    /** Brings back a deleted ticket, with its number, history and links unchanged. */
    public function restore(Ticket $ticket)
    {
        Gate::authorize('tickets.restore');

        if (! $ticket->trashed()) {
            abort(422, "Ticket {$ticket->ticket_number} isn't deleted.");
        }

        // Same rule as filing and reopening: one open ticket per item and service type.
        if ($ticket->inventory_id && $ticket->it_service_id && ! $ticket->isFinished()
            && Ticket::hasUnfinishedFor($ticket->inventory_id, $ticket->it_service_id, $ticket->id)) {
            abort(422, "Ticket {$ticket->ticket_number} can't be restored: this inventory item already has an open ticket for this service type.");
        }

        $ticket->restore();

        return new TicketResource($ticket->refresh());
    }

    public function checkStock(Request $request, Ticket $ticket)
    {
        Gate::authorize('tickets.update');
        $this->ensureWorkflowAllows($ticket, 'check_stock');

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
        $this->ensureWorkflowAllows($ticket, 'await_part');

        $ticket->update([
            'query_status' => TicketStatus::AwaitingPart,
        ]);

        ProfileEngagementService::syncTicket($ticket);

        return new TicketResource($ticket);
    }

    public function resolve(ResolveTicketRequest $request, Ticket $ticket)
    {
        Gate::authorize('tickets.update');
        $this->ensureWorkflowAllows($ticket, 'resolve');

        $data = $request->validated();

        $resolvedAt = now();
        $data['query_status'] = TicketStatus::Resolved;
        $data['request_status'] = TicketStatus::Closed;
        $data['resolved_at'] = $resolvedAt;

        $ticket->update($data);
        $user = Auth::user();
        $ticket->resolutions()->create([
            'solution_id' => $data['solution_id'],
            'service_method' => $data['service_method'] ?? null,
            'resolved_by' => $user->profile?->formatted_name ?? $user->name,
            'resolved_at' => $resolvedAt,
        ]);

        ProfileEngagementService::syncTicket($ticket);

        $this->notifyFiler($ticket);

        return new TicketResource($ticket);
    }

    /**
     * Record the client's satisfaction rating (1-5) and when it was given,
     * the same rating the request types take on completion. The rater is
     * the ticket's client, whose name the frontend sends as shown on the
     * ticket (it comes from HRIS, which the API doesn't keep locally).
     * Rating again replaces the earlier one.
     */
    public function rate(Request $request, Ticket $ticket)
    {
        Gate::authorize('tickets.resolve');
        $this->ensureWorkflowAllows($ticket, 'rate');

        $data = $request->validate([
            'feedback_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'feedback_name' => ['nullable', 'string', 'max:255'],
            'feedback_date' => ['required', 'date'],
        ], [
            'feedback_rating.required' => "Enter the client's rating.",
            'feedback_date.required' => 'Enter the date of the rating.',
        ]);

        $ticket->forceFill([
            'feedback_rating' => $data['feedback_rating'],
            'feedback_name' => filled($data['feedback_name'] ?? null)
                ? trim($data['feedback_name'])
                : null,
            'feedback_date' => $data['feedback_date'],
        ])->save();

        return new TicketResource($ticket);
    }

    public function cancel(CancelTicketRequest $request, Ticket $ticket)
    {
        Gate::authorize('tickets.update');
        $this->ensureWorkflowAllows($ticket, 'cancel');

        $ticket->update([
            'query_status' => TicketStatus::Cancelled,
            'request_status' => TicketStatus::Closed,
            'cancellation_reason' => $request->validated('reason'),
        ]);

        ProfileEngagementService::syncTicket($ticket);

        return new TicketResource($ticket);
    }

    public function reopen(Request $request, Ticket $ticket)
    {
        Gate::authorize('tickets.update');
        $this->ensureWorkflowAllows($ticket, 'reopen');

        // A newer ticket may have been filed for the same item while this
        // one was closed; reopening would leave two working the same issue.
        if ($ticket->inventory_id && $ticket->it_service_id
            && Ticket::hasUnfinishedFor($ticket->inventory_id, $ticket->it_service_id, $ticket->id)) {
            abort(422, "Ticket {$ticket->ticket_number} can't be reopened: this inventory item already has an open ticket for this service type. Close that one first.");
        }

        $ticket->assessment()->delete();

        // A ticket cancelled before anyone accepted it has nobody to work
        // it; it goes back to the queue instead of "in progress".
        $ticket->update([
            'query_status' => $ticket->personnel()->exists()
                ? TicketStatus::InProgress
                : TicketStatus::Queued,
            'request_status' => TicketStatus::Reopened,
            'resolved_at' => null,
            'assessed_at' => null,
            'reopened_at' => now(),
            'cancellation_reason' => null,
        ]);

        ProfileEngagementService::syncTicket($ticket);

        return new TicketResource($ticket);
    }

    public function assess(AssessTicketRequest $request, Ticket $ticket)
    {
        Gate::authorize('tickets.update');
        $this->ensureWorkflowAllows($ticket, 'assess');

        $data = $request->validated();

        $user = Auth::user();
        $user_profile_designation = $user?->profile?->designation ?? '';
        $assessedBy = $user->profile?->formatted_name ?? $user->name;

        $payload = [
            ...$data,
            'assessed_by' => $assessedBy,
            'assessed_by_position' => $user_profile_designation,
        ];

        // Editing an assessment of an already assessed ticket isn't news.
        $wasAssessed = $ticket->query_status === TicketStatus::Assessed;

        $existingAssessment = $ticket->assessment()->withTrashed()->first();

        if ($existingAssessment) {
            if ($existingAssessment->trashed()) {
                $existingAssessment->restore();
            }

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
            'assessed_at' => now(),
        ]);

        ProfileEngagementService::syncTicket($ticket);

        if (! $wasAssessed) {
            $this->notifyFiler($ticket);
        }

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
    /**
     * 422s when the ticket's status (or who is on it) doesn't allow the
     * action right now -- see Ticket::workflowAllows().
     */
    /**
     * Tells whoever filed the ticket that it's done, so they can let the
     * client know (clients have no account or email here). Not when they
     * closed it themselves.
     */
    private function notifyFiler(Ticket $ticket): void
    {
        $completedBy = Auth::user()->profile;
        $filer = $ticket->profile_id ? Profile::with('user')->find($ticket->profile_id) : null;

        if (! $completedBy || ! $filer?->user || $filer->id === $completedBy->id) {
            return;
        }

        $filer->user->notify(new TicketCompletedNotification($ticket, $completedBy));
    }

    private function assignableProfiles()
    {
        return Profile::query()->whereHas(
            'user.roles.permissions',
            fn ($q) => $q->where('title', 'tickets.accept'),
        );
    }

    /** The first person on a queued ticket starts it -- what accept() does. */
    private function markAccepted(Ticket $ticket): void
    {
        if ($ticket->query_status === TicketStatus::Queued) {
            $ticket->update([
                'query_status' => TicketStatus::InProgress,
                'request_status' => TicketStatus::Accepted,
                'accepted_at' => now(),
            ]);
        }
    }

    /** @param  list<int>  $profileIds */
    private function notifyAssigned(Ticket $ticket, Profile $admin, array $profileIds, ?Profile $transferredFrom = null): void
    {
        foreach (Profile::with('user')->whereIn('id', $profileIds)->get() as $assignee) {
            ProfileEngagementService::sync($assignee);

            if ($assignee->id !== $admin->id) {
                $assignee->user?->notify(new TicketAssignedNotification($ticket, $admin, $transferredFrom));
            }
        }
    }

    private function ensureWorkflowAllows(Ticket $ticket, string $action): void
    {
        $user = Auth::user();
        $isAdmin = $user->roles()->where('title', 'Admin')->exists();

        if ($ticket->workflowAllows($action, $user->profile, $isAdmin)) {
            return;
        }

        $status = str_replace('_', ' ', $ticket->query_status->value);
        $isMine = $user->profile
            && $ticket->personnel()->where('profile_id', $user->profile->id)->exists();
        // Name the real reason: not being on the ticket, or its status.
        $workReason = $isMine
            ? "This can't be done while the ticket is {$status}."
            : 'Only personnel on this ticket can do that. Accept it first.';

        abort(422, match ($action) {
            'accept' => $ticket->isFinished()
                ? "A ticket that is {$status} can't be accepted. Reopen it first."
                : 'You have already accepted this ticket.',
            'unaccept' => $ticket->isFinished()
                ? "You can't leave a ticket that is {$status}."
                : 'You have not accepted this ticket.',
            'await_part' => $isMine && $ticket->query_status === TicketStatus::AwaitingPart
                ? 'This ticket is already awaiting parts.'
                : $workReason,
            'cancel' => $ticket->isFinished()
                ? "A ticket that is {$status} can't be cancelled."
                : 'Only personnel on this ticket or an Admin can cancel it.',
            'reopen' => 'Only resolved, assessed or cancelled tickets can be reopened.',
            'assign', 'transfer' => "A ticket that is {$status} can't be assigned or transferred. Reopen it first.",
            'rate' => 'Only resolved or assessed tickets can be rated.',
            default => $workReason,
        });
    }

    /**
     * Dashboard KPIs, charts and monitoring lists (Sprint 7 -- see
     * TicketDashboardService). `trend` picks the created-vs-resolved chart's
     * buckets: daily (last 14 days, the default), monthly (12 months) or
     * yearly (5 years).
     *
     * The four top-level counts below predate Sprint 7's `kpis` and are kept
     * as they were, so nothing reading them breaks.
     */
    public function dashboardSummary(Request $request, TicketDashboardService $dashboard)
    {
        Gate::authorize('tickets.view');

        $validated = $request->validate([
            'trend' => ['nullable', Rule::in(TicketDashboardService::TREND_PERIODS)],
        ]);

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

            'breakdown' => Ticket::query()
                ->selectRaw('query_status, COUNT(*) as count')
                ->groupBy('query_status')
                ->pluck('count', 'query_status'),

            ...$dashboard->summary($validated['trend'] ?? 'daily'),

            // The signed-in person's own accepted, unfinished tickets and
            // requests, the ones needing attention first.
            'my_work' => $this->myWork($request->user()?->profile),
        ]);
    }

    private function myWork(?Profile $profile): array
    {
        $items = $profile ? (new WorkReminders)->openFor($profile) : collect();

        return [
            'open' => $items->count(),
            'overdue' => $items->where('reason', 'overdue')->count(),
            'no_update' => $items->where('reason', 'no_update')->count(),
            'items' => $items->take(8)->values()->all(),
        ];
    }
}
