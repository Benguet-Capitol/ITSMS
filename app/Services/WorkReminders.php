<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Enums\TicketStatus;
use App\Models\Profile;
use App\Models\Ticket;
use App\Support\RequestTypes;
use App\Support\WorkingDays;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Finds the work people have forgotten, for the 8:00 AM reminders
 * (reminders:send) and the dashboard's "My open work" card:
 *
 * - Unaccepted: tickets still Queued and requests still Pending after
 *   ACCEPT_WITHIN_WORKING_DAYS working days. Admins are told.
 * - A person's open work: accepted tickets and requests they are on. One
 *   needs attention when it is overdue (a ticket past its complexity
 *   level's maximum, the dashboard's rule) or hasn't changed for
 *   STALE_AFTER_WORKING_DAYS working days. They are told.
 *
 * Every item comes back in the same shape, so the notification, its
 * email and the dashboard can list tickets and requests together.
 */
class WorkReminders
{
    public const ACCEPT_WITHIN_WORKING_DAYS = 1;

    public const STALE_AFTER_WORKING_DAYS = 1;

    /** Roles told about unaccepted work. */
    public const ADMIN_ROLES = ['Admin', 'It Admin Staff', 'It Admin Staff (No Delete)'];

    /** Accepted and unfinished, the dashboard's "in progress". */
    public const OPEN_TICKET_STATUSES = [
        TicketStatus::InProgress,
        TicketStatus::CheckingStock,
        TicketStatus::AwaitingPart,
        TicketStatus::AwaitingUser,
        TicketStatus::AwaitingVendor,
    ];

    /** Accepted and unfinished requests. For Completion waits on the client, not on staff. */
    public const OPEN_REQUEST_STATUSES = [
        RequestStatus::InProgress,
        RequestStatus::OnHold,
    ];

    private CarbonImmutable $now;

    public function __construct(?CarbonInterface $now = null)
    {
        $this->now = CarbonImmutable::instance($now ?? now());
    }

    /** Whatever was filed before this has waited too long to be accepted. */
    public function acceptCutoff(): CarbonImmutable
    {
        return WorkingDays::cutoff($this->now, self::ACCEPT_WITHIN_WORKING_DAYS);
    }

    /** Open work last changed before this has stalled. */
    public function staleCutoff(): CarbonImmutable
    {
        return WorkingDays::cutoff($this->now, self::STALE_AFTER_WORKING_DAYS);
    }

    /**
     * Queued tickets and Pending requests past the time to accept them,
     * longest waiting first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function unaccepted(): Collection
    {
        return $this->waiting()->where('reason', 'unaccepted')->values();
    }

    /**
     * Every Queued ticket and Pending request, longest waiting first, for
     * the wall display's queue. `reason` is unaccepted once it is past the
     * time to accept it, pending before that.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function waiting(): Collection
    {
        $cutoff = $this->acceptCutoff();
        $reason = fn (Model $model) => $model->created_at < $cutoff ? 'unaccepted' : 'pending';

        $tickets = $this->ticketQuery()
            ->where('query_status', TicketStatus::Queued)
            ->get()
            ->map(fn (Ticket $ticket) => $this->ticketItem($ticket, $reason($ticket), $ticket->created_at));

        $requests = $this->requestQueries()->flatMap(fn (Builder $query) => $query
            ->where('status', RequestStatus::Pending)
            ->get()
            ->map(fn (Model $request) => $this->requestItem($request, $reason($request), $request->created_at)));

        return $tickets->concat($requests)->sortBy('since')->values();
    }

    /**
     * Every accepted, unfinished ticket and request the person is on.
     * `reason` is overdue, no_update or null (fine for now); the ones
     * needing attention come first, longest waiting first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function openFor(Profile $profile): Collection
    {
        $staleCutoff = $this->staleCutoff();

        $tickets = $this->ticketQuery()
            ->with('complexityLevel')
            ->whereIn('query_status', self::OPEN_TICKET_STATUSES)
            ->whereHas('personnel', fn ($q) => $q->where('profiles.id', $profile->id))
            ->get()
            ->map(function (Ticket $ticket) use ($staleCutoff) {
                $reason = match (true) {
                    $this->isOverdue($ticket) => 'overdue',
                    $ticket->updated_at < $staleCutoff => 'no_update',
                    default => null,
                };

                return $this->ticketItem($ticket, $reason, $ticket->updated_at);
            });

        $requests = $this->requestQueries()->flatMap(fn (Builder $query) => $query
            ->whereIn('status', self::OPEN_REQUEST_STATUSES)
            ->whereHas('personnel', fn ($q) => $q->where('profiles.id', $profile->id))
            ->get()
            ->map(fn (Model $request) => $this->requestItem(
                $request,
                $request->updated_at < $staleCutoff ? 'no_update' : null,
                $request->updated_at,
            )));

        return $tickets->concat($requests)
            ->sortBy([
                fn ($a, $b) => ($a['reason'] === null) <=> ($b['reason'] === null),
                fn ($a, $b) => $a['since'] <=> $b['since'],
            ])
            ->values();
    }

    /**
     * The open work of everyone who has some needing attention, keyed by
     * profile id, for the personal reminders.
     *
     * @return Collection<int, array{profile: Profile, items: Collection<int, array<string, mixed>>}>
     */
    public function needingAttentionByProfile(): Collection
    {
        $ticketPeople = Profile::query()
            ->whereHas('ticketPersonnel', fn ($q) => $q
                ->whereIn('query_status', self::OPEN_TICKET_STATUSES)
                ->whereNull('tickets.deleted_at'))
            ->pluck('id');

        $requestPeople = collect(RequestTypes::TYPES)->flatMap(fn (array $type) => $type['model']::query()
            ->whereIn('status', self::OPEN_REQUEST_STATUSES)
            ->with('personnel:id')
            ->get()
            ->flatMap(fn (Model $request) => $request->personnel->pluck('id')));

        return Profile::query()
            ->with('user')
            ->whereIn('id', $ticketPeople->concat($requestPeople)->unique()->values())
            ->get()
            ->mapWithKeys(fn (Profile $profile) => [$profile->id => [
                'profile' => $profile,
                'items' => $this->openFor($profile)->filter(fn ($item) => $item['reason'] !== null)->values(),
            ]])
            ->filter(fn ($entry) => $entry['items']->isNotEmpty());
    }

    /** The dashboard's rule: the work session has run past the level's maximum. */
    public function isOverdue(Ticket $ticket): bool
    {
        $maxMinutes = $ticket->complexityLevel?->max_minutes;
        $sessionStart = $ticket->reopened_at ?? $ticket->accepted_at;

        return $maxMinutes !== null
            && $sessionStart !== null
            && (int) floor($sessionStart->diffInMinutes($this->now, true)) > $maxMinutes;
    }

    private function ticketQuery(): Builder
    {
        // Ticket's own $with loads nine relations; only the office's are needed.
        return Ticket::query()->setEagerLoads([])->with(['agency', 'inventory.parent_component']);
    }

    /**
     * The dashboard's office rule: the agency for other agencies, else the
     * ticket's office, then its item's, then the item's parent's.
     */
    public static function ticketOffice(Ticket $ticket): ?string
    {
        if ($ticket->is_other_agency) {
            return $ticket->agency?->name ?? 'Other agency';
        }

        return $ticket->office_code
            ?: $ticket->inventory?->office_code
            ?: $ticket->inventory?->parent_component?->office_code
            ?: null;
    }

    /** @return Collection<string, Builder> one query per request type */
    private function requestQueries(): Collection
    {
        return collect(RequestTypes::TYPES)->map(fn (array $type) => $type['model']::query());
    }

    private function ticketItem(Ticket $ticket, ?string $reason, ?CarbonInterface $since): array
    {
        return [
            'kind' => 'ticket',
            'id' => $ticket->id,
            'type_label' => 'Ticket',
            'reference' => (string) $ticket->ticket_number,
            'summary' => collect([$ticket->client_name ?: $ticket->full_name, $ticket->concern])
                ->filter()
                ->implode(' — '),
            'status' => self::ticketStatusLabel($ticket->query_status),
            'office' => self::ticketOffice($ticket),
            'path' => "/tickets/{$ticket->id}",
            'reason' => $reason,
            'since' => $since?->toIso8601String(),
        ];
    }

    private function requestItem(Model $request, ?string $reason, ?CarbonInterface $since): array
    {
        $type = RequestTypes::forModel($request);

        return [
            'kind' => 'request',
            'id' => $request->getKey(),
            'type_label' => $type['label'],
            'reference' => $request->workflowReference(),
            'summary' => collect([
                $request->office_code ?? $request->department_office,
                $request->requesterName(),
            ])->filter()->implode(' — '),
            'status' => $request->status->label(),
            // CCTV Footage requests have no office.
            'office' => defined($request::class.'::OFFICE_FIELD')
                ? ($request->office_code ?: $request->{$request::OFFICE_FIELD})
                : null,
            'path' => "{$type['path']}/{$request->getKey()}",
            'reason' => $reason,
            'since' => $since?->toIso8601String(),
        ];
    }

    private static function ticketStatusLabel(?TicketStatus $status): string
    {
        return match ($status) {
            TicketStatus::AwaitingPart => 'Waiting for Parts',
            null => '',
            default => ucwords(str_replace('_', ' ', $status->value)),
        };
    }
}
