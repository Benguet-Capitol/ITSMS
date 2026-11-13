<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Enums\TicketStatus;
use App\Models\Profile;
use App\Models\Ticket;
use App\Models\User;
use App\Support\RequestTypes;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Everything the IT office's wall display (the TV page, /display) shows,
 * in one payload it polls every 30 seconds:
 *
 * - counts: waiting, in progress, received and resolved today
 * - queue: tickets and requests nobody has accepted, longest waiting first
 * - team: the technicians, their presence and what they're on
 * - recently_resolved: the latest finished work
 *
 * Tickets and requests are counted together throughout, unlike the
 * dashboard's ticket-only KPIs. See docs/wall-display.md.
 */
class WallDisplayService
{
    /** Queue rows sent; the page shows as many as fit and "+N more". */
    public const QUEUE_LIMIT = 20;

    public const RECENT_LIMIT = 12;

    /** The dashboard's "Technical Staff"; everyone else on the team board is non-technical. */
    public const TECHNICAL_ROLES = ['It Technical'];

    /** The display's own sign-in; never shown as staff. */
    public const DISPLAY_ROLE = 'Wall Display';

    private CarbonImmutable $now;

    private CarbonImmutable $today;

    private WorkReminders $reminders;

    public function __construct(?CarbonInterface $now = null)
    {
        $this->now = CarbonImmutable::instance($now ?? now());
        $this->today = $this->now->startOfDay();
        $this->reminders = new WorkReminders($this->now);
    }

    public function summary(): array
    {
        $waiting = $this->reminders->waiting();

        return [
            'generated_at' => $this->now->toIso8601String(),
            'counts' => [
                'waiting' => $waiting->count(),
                'waiting_late' => $waiting->where('reason', 'unaccepted')->count(),
                'in_progress' => $this->openTickets()->count() + $this->openRequestCount(),
                'overdue' => $this->overdueTicketCount(),
                'received_today' => $this->receivedToday(),
                'resolved_today' => $this->resolvedToday(),
            ],
            'response_minutes_today' => $this->responseMinutesToday(),
            'satisfaction' => $this->satisfaction(),
            'queue' => $waiting->take(self::QUEUE_LIMIT)->values()->all(),
            'team' => $this->team(),
            'recently_resolved' => $this->recentlyResolved(),
        ];
    }

    private function openTickets(): Builder
    {
        return Ticket::query()->setEagerLoads([])
            ->whereIn('query_status', WorkReminders::OPEN_TICKET_STATUSES);
    }

    private function openRequestCount(): int
    {
        return $this->requestModels()->sum(fn (string $model) => $model::query()
            ->whereIn('status', WorkReminders::OPEN_REQUEST_STATUSES)
            ->count());
    }

    /** Accepted tickets past their complexity level's maximum, the dashboard's rule. */
    private function overdueTicketCount(): int
    {
        return $this->openTickets()
            ->with('complexityLevel')
            ->whereNotNull('complexity_level_id')
            ->get(['id', 'complexity_level_id', 'accepted_at', 'reopened_at'])
            ->filter(fn (Ticket $ticket) => $this->reminders->isOverdue($ticket))
            ->count();
    }

    private function receivedToday(): int
    {
        return Ticket::query()->where('created_at', '>=', $this->today)->count()
            + $this->requestModels()->sum(fn (string $model) => $model::query()
                ->where('created_at', '>=', $this->today)
                ->count());
    }

    /**
     * Tickets resolved or assessed today (an assessment finishes a ticket
     * too), and requests whose work was finished today.
     */
    private function resolvedToday(): int
    {
        return $this->finishedTickets()
            ->whereRaw('COALESCE(resolved_at, assessed_at) >= ?', [$this->today->toDateTimeString()])
            ->count()
            + $this->requestModels()->sum(fn (string $model) => $model::query()
                ->where('finished_at', '>=', $this->today)
                ->count());
    }

    /** Average minutes from submission to acceptance, for tickets accepted today. */
    private function responseMinutesToday(): ?int
    {
        $average = Ticket::query()
            ->where('accepted_at', '>=', $this->today)
            ->whereColumn('accepted_at', '>=', 'created_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, accepted_at)) as minutes')
            ->value('minutes');

        return $average === null ? null : (int) round((float) $average);
    }

    /** All-time client rating of tickets, as on the dashboard. */
    private function satisfaction(): array
    {
        $rated = Ticket::query()->whereNotNull('feedback_rating');

        $average = (clone $rated)->avg('feedback_rating');

        return [
            'average' => $average === null ? null : round((float) $average, 1),
            'count' => $rated->count(),
        ];
    }

    /**
     * All staff, technical and not -- non-technical staff accept tickets
     * and requests too -- the same people as the dashboard's Online Users.
     * The ones with work first, then by presence and name, each with the
     * tickets and requests they're on.
     *
     * @return array<int, array<string, mixed>>
     */
    private function team(): array
    {
        $users = User::query()
            ->with(['profile', 'roles:id,title'])
            // Someone with no role can't accept work.
            ->whereHas('roles')
            ->whereDoesntHave('roles', fn ($q) => $q->where('title', self::DISPLAY_ROLE))
            ->get()
            ->filter(fn (User $user) => $user->profile !== null);

        $work = ProfileEngagementService::currentWork($users->pluck('profile.id'));
        $presenceOrder = [Profile::STATUS_ONLINE => 0, Profile::STATUS_IDLE => 1, Profile::STATUS_OFFLINE => 2];

        return $users
            ->map(function (User $user) use ($work) {
                $profile = $user->profile;

                return [
                    'id' => $user->id,
                    'name' => $profile->display_name,
                    'technical' => $user->roles->contains(fn ($role) => in_array($role->title, self::TECHNICAL_ROLES, true)),
                    'status' => $profile->status ?? Profile::STATUS_OFFLINE,
                    'last_seen_at' => $profile->last_seen_at
                        ? CarbonImmutable::parse($profile->last_seen_at)->toIso8601String()
                        : null,
                    'work' => collect($work[$profile->id] ?? [])
                        ->map(fn (array $item) => collect($item)->only(['type', 'label', 'reference', 'office', 'status'])->all())
                        ->values()
                        ->all(),
                ];
            })
            ->sortBy([
                fn ($a, $b) => empty($a['work']) <=> empty($b['work']),
                fn ($a, $b) => ($presenceOrder[$a['status']] ?? 3) <=> ($presenceOrder[$b['status']] ?? 3),
                fn ($a, $b) => strcasecmp((string) $a['name'], (string) $b['name']),
            ])
            ->values()
            ->all();
    }

    /**
     * The latest finished tickets and requests, newest first, with who
     * did them.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recentlyResolved(): array
    {
        $tickets = $this->finishedTickets()
            ->with(['personnel:id,display_name', 'agency', 'inventory.parent_component'])
            ->selectRaw('tickets.*, COALESCE(resolved_at, assessed_at) as finished_on')
            ->orderByDesc('finished_on')
            ->limit(self::RECENT_LIMIT)
            ->get()
            ->map(fn (Ticket $ticket) => [
                'kind' => 'ticket',
                'type_label' => 'Ticket',
                'reference' => (string) $ticket->ticket_number,
                'office' => WorkReminders::ticketOffice($ticket),
                'by' => $ticket->personnel->pluck('display_name')->filter()->values()->all(),
                'finished_at' => CarbonImmutable::parse($ticket->finished_on)->toIso8601String(),
            ]);

        $requests = collect(RequestTypes::TYPES)->flatMap(fn (array $type) => $type['model']::query()
            ->with('personnel:id,display_name')
            ->whereNotNull('finished_at')
            ->whereNotIn('status', [RequestStatus::Cancelled])
            ->orderByDesc('finished_at')
            ->limit(self::RECENT_LIMIT)
            ->get()
            ->map(fn (Model $request) => [
                'kind' => 'request',
                'type_label' => $type['label'],
                'reference' => $request->workflowReference(),
                'office' => defined($request::class.'::OFFICE_FIELD')
                    ? ($request->office_code ?: $request->{$request::OFFICE_FIELD})
                    : null,
                'by' => $request->personnel->pluck('display_name')->filter()->values()->all(),
                'finished_at' => $request->finished_at->toIso8601String(),
            ]));

        return $tickets->concat($requests)
            ->sortByDesc('finished_at')
            ->take(self::RECENT_LIMIT)
            ->values()
            ->all();
    }

    private function finishedTickets(): Builder
    {
        return Ticket::query()->setEagerLoads([])
            ->whereIn('query_status', [TicketStatus::Resolved, TicketStatus::Assessed])
            ->whereRaw('COALESCE(resolved_at, assessed_at) IS NOT NULL');
    }

    /** @return Collection<int, class-string<Model>> */
    private function requestModels(): Collection
    {
        return collect(RequestTypes::TYPES)->pluck('model')->values();
    }
}
