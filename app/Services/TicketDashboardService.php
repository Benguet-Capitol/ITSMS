<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketAssessment;
use App\Models\TicketComplexityLevel;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sprint 7 "Dashboard v2": the KPI set, charts and monitoring lists the
 * client's Scope of Work asks for (Total / Pending / In Progress /
 * Resolved / Complex / Overdue cards; requests by complexity, service
 * type, office and technician; average response and resolution times;
 * assessment reports; recent and overdue requests).
 *
 * Every figure is a plain aggregate query over `tickets`, so the
 * dashboard costs a fixed handful of queries however many tickets exist.
 *
 * Client satisfaction comes from the ratings recorded with each ticket's
 * Rate action (TicketController::rate()).
 */
class TicketDashboardService
{
    // "Pending" is the client's name for Queued -- accepted by nobody yet.
    private const PENDING_STATUSES = [
        TicketStatus::Queued,
    ];

    // Accepted and being worked: everything that isn't waiting for a
    // technician and isn't finished.
    private const ACTIVE_STATUSES = [
        TicketStatus::InProgress,
        TicketStatus::CheckingStock,
        TicketStatus::AwaitingPart,
        TicketStatus::AwaitingUser,
        TicketStatus::AwaitingVendor,
    ];

    public const TREND_PERIODS = ['daily', 'monthly', 'yearly'];

    private const LIST_LIMIT = 8;

    private CarbonInterface $now;

    public function __construct()
    {
        $this->now = Carbon::now();
    }

    public function summary(string $trendPeriod = 'daily'): array
    {
        $levels = TicketComplexityLevel::query()->orderBy('sort_order')->get();
        $complexLevel = $levels->last();

        return [
            'kpis' => [
                'total' => Ticket::query()->count(),
                'pending' => $this->ticketsIn(self::PENDING_STATUSES)->count(),
                'in_progress' => $this->ticketsIn(self::ACTIVE_STATUSES)->count(),
                'resolved' => Ticket::query()
                    ->where('query_status', TicketStatus::Resolved->value)
                    ->count(),
                'complex' => $complexLevel
                    ? Ticket::query()->where('complexity_level_id', $complexLevel->id)->count()
                    : 0,
                'complex_label' => $complexLevel?->label,
                'overdue' => $this->overdueQuery()->count(),
                'closed' => Ticket::query()
                    ->where('request_status', TicketStatus::Closed->value)
                    ->count(),
                // The client's "Closed Requests: completed requests with
                // feedback" -- closed tickets the client has rated.
                'closed_with_feedback' => Ticket::query()
                    ->where('request_status', TicketStatus::Closed->value)
                    ->whereNotNull('feedback_rating')
                    ->count(),
                'assessment_reports' => TicketAssessment::query()->count(),
            ],
            'satisfaction' => $this->satisfaction(),
            'averages' => $this->averages($levels),
            'by_complexity' => $this->byComplexity($levels),
            'by_service_type' => $this->byServiceType(),
            'by_office' => $this->byOffice(),
            'by_personnel' => $this->byPersonnel(),
            'recent' => $this->recent(),
            'attention' => $this->attention(),
            'latest_assessments' => $this->latestAssessments(),
            'trend_period' => $trendPeriod,
            'trend' => $this->trend($trendPeriod),
            'overdue_rule' => 'An accepted, unfinished ticket whose current work session '
                .'(since acceptance, or since its last reopen) has run longer than its '
                ."complexity level's maximum minutes. Levels without a maximum, and tickets "
                .'without a complexity level, are never overdue.',
            'pending_decisions' => $this->pendingDecisions(),
        ];
    }

    /**
     * @param  array<int, TicketStatus>  $statuses
     */
    private function ticketsIn(array $statuses)
    {
        return Ticket::query()->whereIn(
            'query_status',
            array_map(fn (TicketStatus $status) => $status->value, $statuses),
        );
    }

    /**
     * The target time is the complexity level's max_minutes (the client's
     * choice) -- the same ceiling the ticket list's Complexity Check uses,
     * measured from the current work session's start (reopened_at ??
     * accepted_at).
     */
    private function overdueQuery(): Builder
    {
        return DB::table('tickets')
            ->join('ticket_complexity_levels as levels', 'levels.id', '=', 'tickets.complexity_level_id')
            ->whereIn('tickets.query_status', array_map(fn ($status) => $status->value, self::ACTIVE_STATUSES))
            ->whereNotNull('levels.max_minutes')
            ->whereRaw('COALESCE(tickets.reopened_at, tickets.accepted_at) IS NOT NULL')
            ->whereRaw(
                'TIMESTAMPDIFF(MINUTE, COALESCE(tickets.reopened_at, tickets.accepted_at), ?) > levels.max_minutes',
                [$this->now->toDateTimeString()],
            );
    }

    /**
     * Response = submission (created_at) to acceptance. Resolution = the
     * work session's start to its finish, the same span the ticket list's
     * Complexity Check measures: reopened_at ?? accepted_at, to
     * resolved_at ?? assessed_at (an assessment finishes a ticket too).
     */
    private function averages(Collection $levels): array
    {
        $response = DB::table('tickets')
            ->whereNotNull('accepted_at')
            ->whereColumn('accepted_at', '>=', 'created_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, accepted_at)) as minutes, COUNT(*) as count')
            ->first();

        $resolutionByLevel = $this->resolutionSpans()
            ->selectRaw('complexity_level_id, AVG(TIMESTAMPDIFF(MINUTE, COALESCE(reopened_at, accepted_at), COALESCE(resolved_at, assessed_at))) as minutes, COUNT(*) as count')
            ->groupBy('complexity_level_id')
            ->get()
            ->keyBy(fn ($row) => $row->complexity_level_id ?? 'none');

        $resolution = $this->resolutionSpans()
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, COALESCE(reopened_at, accepted_at), COALESCE(resolved_at, assessed_at))) as minutes, COUNT(*) as count')
            ->first();

        return [
            'response_minutes' => $this->roundOrNull($response->minutes),
            'response_count' => (int) $response->count,
            'resolution_minutes' => $this->roundOrNull($resolution->minutes),
            'resolution_count' => (int) $resolution->count,
            'resolution_by_complexity' => $levels
                ->map(fn ($level) => [
                    'id' => $level->id,
                    'label' => $level->label,
                    'color' => $level->color,
                    'max_minutes' => $level->max_minutes,
                    'minutes' => $this->roundOrNull($resolutionByLevel->get($level->id)?->minutes),
                    'count' => (int) ($resolutionByLevel->get($level->id)?->count ?? 0),
                ])
                ->values()
                ->all(),
        ];
    }

    private function resolutionSpans(): Builder
    {
        return DB::table('tickets')
            ->whereRaw('COALESCE(reopened_at, accepted_at) IS NOT NULL')
            ->whereRaw('COALESCE(resolved_at, assessed_at) IS NOT NULL')
            ->whereRaw('COALESCE(resolved_at, assessed_at) >= COALESCE(reopened_at, accepted_at)');
    }

    private function byComplexity(Collection $levels): array
    {
        $counts = DB::table('tickets')
            ->selectRaw('complexity_level_id, COUNT(*) as count')
            ->groupBy('complexity_level_id')
            ->pluck('count', 'complexity_level_id');

        $rows = $levels->map(fn ($level) => [
            'id' => $level->id,
            'label' => $level->label,
            'color' => $level->color,
            'count' => (int) ($counts[$level->id] ?? 0),
        ])->values()->all();

        // pluck() keys a NULL group by an empty string.
        $unset = (int) ($counts[''] ?? 0);

        if ($unset > 0) {
            $rows[] = ['id' => null, 'label' => 'Not set', 'color' => 'neutral', 'count' => $unset];
        }

        return $rows;
    }

    private function byServiceType(): array
    {
        return DB::table('tickets')
            ->leftJoin('it_services', 'it_services.id', '=', 'tickets.it_service_id')
            ->selectRaw("COALESCE(it_services.name, 'Not set') as label, COUNT(*) as count")
            ->groupBy('label')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'count' => (int) $row->count])
            ->all();
    }

    /**
     * Other-agency tickets count under their agency. Provincial tickets
     * rarely carry their own office (most are filed against an inventory
     * item), so the office falls back to the item's location, then to its
     * parent asset's -- the same item-location rule the tickets list uses.
     */
    private function byOffice(): array
    {
        return DB::table('tickets')
            ->leftJoin('agencies', 'agencies.id', '=', 'tickets.agency_id')
            ->leftJoin('inventories as items', 'items.id', '=', 'tickets.inventory_id')
            ->leftJoin('inventories as parents', 'parents.id', '=', 'items.parent_component_id')
            ->selectRaw("
                CASE
                    WHEN tickets.is_other_agency = 1 THEN COALESCE(agencies.name, 'Other agency')
                    ELSE COALESCE(tickets.office_code, items.office_code, parents.office_code, 'No office recorded')
                END as code,
                MAX(CASE
                    WHEN tickets.is_other_agency = 1 THEN COALESCE(agencies.name, 'Other agency')
                    ELSE COALESCE(tickets.office_desc, items.office_name, parents.office_name, 'No office recorded')
                END) as name,
                MAX(tickets.is_other_agency) as is_other_agency,
                COUNT(*) as count
            ")
            ->groupBy('code')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'code' => $row->code,
                'name' => $row->name,
                'is_other_agency' => (bool) $row->is_other_agency,
                'count' => (int) $row->count,
            ])
            ->all();
    }

    /**
     * Every technician who has been on a ticket: all-time count, how many
     * of those are still being worked, and how many they finished.
     */
    private function byPersonnel(): array
    {
        $active = array_map(fn ($status) => $status->value, self::ACTIVE_STATUSES);
        $finished = array_map(fn ($status) => $status->value, Ticket::FINISHED_QUERY_STATUSES);

        return DB::table('ticket_personnel')
            ->join('profiles', 'profiles.id', '=', 'ticket_personnel.profile_id')
            ->join('tickets', 'tickets.id', '=', 'ticket_personnel.ticket_id')
            ->selectRaw(
                'profiles.id as profile_id, MAX(profiles.display_name) as name, MAX(profiles.engagement) as engagement, COUNT(*) as total, '
                .'SUM(CASE WHEN tickets.query_status IN ('.$this->placeholders($active).') THEN 1 ELSE 0 END) as active, '
                .'SUM(CASE WHEN tickets.query_status IN ('.$this->placeholders($finished).') THEN 1 ELSE 0 END) as finished',
                [...$active, ...$finished],
            )
            ->groupBy('profiles.id')
            ->orderByDesc('active')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'profile_id' => (int) $row->profile_id,
                'name' => $row->name,
                'engagement' => $row->engagement,
                'total' => (int) $row->total,
                'active' => (int) $row->active,
                'finished' => (int) $row->finished,
            ])
            ->all();
    }

    private function recent(): array
    {
        return $this->ticketListQuery()
            ->orderByDesc('tickets.updated_at')
            ->orderByDesc('tickets.id')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(fn ($row) => $this->ticketListRow($row))
            ->all();
    }

    /**
     * Pending/Overdue monitoring list: overdue tickets first (longest over
     * their limit first), then pending ones (longest waiting first).
     */
    private function attention(): array
    {
        $overdue = $this->overdueQuery()
            ->select('tickets.id')
            ->selectRaw(
                'TIMESTAMPDIFF(MINUTE, COALESCE(tickets.reopened_at, tickets.accepted_at), ?) as elapsed, levels.max_minutes as limit_minutes',
                [$this->now->toDateTimeString()],
            )
            ->orderByRaw('elapsed - levels.max_minutes DESC')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->keyBy('id');

        $rows = $overdue->isEmpty()
            ? collect()
            : $this->ticketListQuery()
                ->whereIn('tickets.id', $overdue->keys())
                ->get()
                ->map(fn ($row) => [
                    ...$this->ticketListRow($row),
                    'reason' => 'overdue',
                    'elapsed_minutes' => (int) $overdue[$row->id]->elapsed,
                    'limit_minutes' => (int) $overdue[$row->id]->limit_minutes,
                ])
                ->sortByDesc(fn ($row) => $row['elapsed_minutes'] - $row['limit_minutes'])
                ->values();

        $remaining = self::LIST_LIMIT - $rows->count();

        if ($remaining > 0) {
            $pending = $this->ticketListQuery()
                ->whereIn('tickets.query_status', array_map(fn ($status) => $status->value, self::PENDING_STATUSES))
                ->orderBy('tickets.created_at')
                ->limit($remaining)
                ->get()
                ->map(fn ($row) => [
                    ...$this->ticketListRow($row),
                    'reason' => 'pending',
                    'elapsed_minutes' => (int) Carbon::parse($row->created_at)->diffInMinutes($this->now),
                    'limit_minutes' => null,
                ]);

            $rows = $rows->concat($pending);
        }

        return $rows->values()->all();
    }

    private function latestAssessments(): array
    {
        return DB::table('ticket_assessments')
            ->join('tickets', 'tickets.id', '=', 'ticket_assessments.ticket_id')
            ->leftJoin('inventories as items', 'items.id', '=', 'tickets.inventory_id')
            ->whereNull('ticket_assessments.deleted_at')
            ->select([
                'ticket_assessments.id',
                'ticket_assessments.control_number',
                'ticket_assessments.assessed_by',
                'ticket_assessments.created_at',
                'tickets.id as ticket_id',
                'tickets.ticket_number',
                'items.property_number',
            ])
            ->orderByDesc('ticket_assessments.created_at')
            ->orderByDesc('ticket_assessments.id')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'control_number' => $row->control_number,
                'assessed_by' => $row->assessed_by,
                'created_at' => $this->isoOrNull($row->created_at),
                'ticket_id' => (int) $row->ticket_id,
                'ticket_number' => $row->ticket_number,
                'property_number' => $row->property_number,
            ])
            ->all();
    }

    private function ticketListQuery(): Builder
    {
        return DB::table('tickets')
            ->leftJoin('it_services', 'it_services.id', '=', 'tickets.it_service_id')
            ->leftJoin('ticket_complexity_levels as levels', 'levels.id', '=', 'tickets.complexity_level_id')
            ->select([
                'tickets.id',
                'tickets.ticket_number',
                'tickets.client_name',
                'tickets.full_name',
                'tickets.concern',
                'tickets.query_status',
                'tickets.request_status',
                'tickets.created_at',
                'tickets.updated_at',
                'it_services.name as service',
                'levels.label as complexity',
                'levels.color as complexity_color',
            ]);
    }

    private function ticketListRow(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'ticket_number' => $row->ticket_number,
            'client' => $row->client_name ?: $row->full_name,
            'concern' => $row->concern,
            'service' => $row->service,
            'complexity' => $row->complexity,
            'complexity_color' => $row->complexity_color,
            'query_status' => $row->query_status,
            'request_status' => $row->request_status,
            'created_at' => $this->isoOrNull($row->created_at),
            'updated_at' => $this->isoOrNull($row->updated_at),
        ];
    }

    /**
     * Created vs. resolved counts per bucket, oldest first and zero-filled
     * (a grouped query omits empty buckets). daily = last 14 days, monthly
     * = last 12 months, yearly = last 5 years. `resolved_at` rather than
     * `updated_at` keeps unrelated edits from shifting a resolution, and
     * reopen() clears it so a reopened ticket stops counting.
     */
    private function trend(string $period): array
    {
        [$count, $start, $format, $step] = match ($period) {
            'monthly' => [12, $this->now->copy()->startOfMonth()->subMonths(11), '%Y-%m-01', 'addMonth'],
            'yearly' => [5, $this->now->copy()->startOfYear()->subYears(4), '%Y-01-01', 'addYear'],
            default => [14, $this->now->copy()->startOfDay()->subDays(13), '%Y-%m-%d', 'addDay'],
        };

        $createdByBucket = DB::table('tickets')
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE_FORMAT(created_at, ?) as bucket, COUNT(*) as count', [$format])
            ->groupBy('bucket')
            ->pluck('count', 'bucket');

        $resolvedByBucket = DB::table('tickets')
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', $start)
            ->selectRaw('DATE_FORMAT(resolved_at, ?) as bucket, COUNT(*) as count', [$format])
            ->groupBy('bucket')
            ->pluck('count', 'bucket');

        $trend = [];
        $cursor = $start->copy();

        for ($i = 0; $i < $count; $i++) {
            $bucket = $cursor->toDateString();

            $trend[] = [
                'date' => $bucket,
                'created' => (int) ($createdByBucket[$bucket] ?? 0),
                'resolved' => (int) ($resolvedByBucket[$bucket] ?? 0),
            ];

            $cursor->{$step}();
        }

        return $trend;
    }

    /**
     * Client feedback/satisfaction: the 1-5 ratings recorded with each
     * ticket's Rate action -- the average, how many, and how many of each.
     */
    private function satisfaction(): array
    {
        $counts = DB::table('tickets')
            ->whereNotNull('feedback_rating')
            ->selectRaw('feedback_rating, COUNT(*) as count')
            ->groupBy('feedback_rating')
            ->pluck('count', 'feedback_rating');

        $total = (int) $counts->sum();
        $average = $total > 0
            ? round($counts->map(fn ($count, $rating) => $rating * $count)->sum() / $total, 1)
            : null;

        return [
            'average' => $average,
            'count' => $total,
            'distribution' => collect([5, 4, 3, 2, 1])
                ->map(fn ($rating) => ['rating' => $rating, 'count' => (int) ($counts[$rating] ?? 0)])
                ->all(),
        ];
    }

    /**
     * Client metrics waiting on a product decision, shown on the dashboard
     * rather than silently left out. None open: the client chose the
     * complexity maximums as the overdue target and a Rate action for
     * satisfaction (2026-11-06). Kept so a future open question has a
     * place to surface.
     */
    private function pendingDecisions(): array
    {
        return [];
    }

    private function placeholders(array $values): string
    {
        return implode(', ', array_fill(0, count($values), '?'));
    }

    private function roundOrNull($minutes): ?int
    {
        return $minutes === null ? null : (int) round((float) $minutes);
    }

    private function isoOrNull(?string $timestamp): ?string
    {
        return $timestamp === null ? null : Carbon::parse($timestamp)->toIso8601String();
    }
}
