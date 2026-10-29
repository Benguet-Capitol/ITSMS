<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Enums\TicketStatus;
use App\Models\Profile;
use App\Models\Ticket;
use App\Support\RequestTypes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProfileEngagementService
{
    /**
     * Sync a profile's engagement (READY/BUSY) based on their active
     * tickets and active requests (in progress or on hold).
     */
    public static function sync(Profile $profile): void
    {
        $isBusy = $profile->ticketPersonnel()
            ->whereIn('request_status', [TicketStatus::Accepted, TicketStatus::Reopened])
            ->whereNotIn('query_status', [TicketStatus::Resolved, TicketStatus::Cancelled])
            ->exists()
            || self::hasActiveRequests($profile);

        $profile->update([
            'engagement' => $isBusy
                ? Profile::ENGAGEMENT_BUSY
                : Profile::ENGAGEMENT_READY,
        ]);
    }

    /**
     * What each profile is working on: the same active tickets and
     * requests that make them BUSY in sync(), for the dashboard. One query
     * for tickets plus one per request type, for all profiles at once.
     *
     * @param  Collection<int, int>|array<int, int>  $profileIds
     * @return array<int, list<array{type: string, label: string, reference: string, office: ?string, status: string, summary: ?string, path: string}>>
     */
    public static function currentWork(Collection|array $profileIds): array
    {
        $profileIds = collect($profileIds)->filter()->unique()->values();
        $work = [];

        if ($profileIds->isEmpty()) {
            return $work;
        }

        // Ticket's $with eager-loads nine relations nobody needs here.
        $tickets = Ticket::query()
            ->setEagerLoads([])
            ->join('ticket_personnel', 'ticket_personnel.ticket_id', '=', 'tickets.id')
            ->whereIn('ticket_personnel.profile_id', $profileIds)
            ->whereIn('tickets.request_status', [TicketStatus::Accepted, TicketStatus::Reopened])
            ->whereNotIn('tickets.query_status', [TicketStatus::Resolved, TicketStatus::Cancelled])
            ->orderBy('tickets.accepted_at')
            ->get([
                'tickets.id', 'tickets.ticket_number', 'tickets.office_code', 'tickets.office_desc',
                'tickets.concern', 'tickets.query_status', 'ticket_personnel.profile_id',
            ]);

        foreach ($tickets as $ticket) {
            $work[$ticket->profile_id][] = [
                'type' => 'ticket',
                'label' => 'Ticket',
                'reference' => $ticket->ticket_number,
                'office' => $ticket->office_code ?: $ticket->office_desc,
                'status' => self::ticketStatusLabel($ticket->query_status),
                'summary' => $ticket->concern ? Str::limit(trim($ticket->concern), 80) : null,
                'path' => "/tickets/{$ticket->id}",
            ];
        }

        foreach (RequestTypes::TYPES as $type => $config) {
            $requests = $config['model']::query()
                ->whereIn('status', RequestStatus::active())
                ->whereHas('personnel', fn ($q) => $q->whereIn('profiles.id', $profileIds))
                ->with(['personnel' => fn ($q) => $q->whereIn('profiles.id', $profileIds)])
                ->orderBy('accepted_at')
                ->get();

            foreach ($requests as $request) {
                // CCTV Footage requests have no office.
                $office = defined($request::class.'::OFFICE_FIELD')
                    ? ($request->office_code ?: $request->{$request::OFFICE_FIELD})
                    : null;

                foreach ($request->personnel as $profile) {
                    $work[$profile->id][] = [
                        'type' => $type,
                        'label' => $config['label'],
                        'reference' => $request->control_number,
                        'office' => $office,
                        'status' => $request->status->label(),
                        'summary' => null,
                        'path' => "{$config['path']}/{$request->id}",
                    ];
                }
            }
        }

        return $work;
    }

    /** Ticket progress as the ticket list shows it. */
    private static function ticketStatusLabel(TicketStatus $status): string
    {
        return match ($status) {
            TicketStatus::AwaitingPart => 'Waiting for Parts',
            default => Str::of($status->value)->replace('_', ' ')->title()->toString(),
        };
    }

    /** Sync everyone on a request (any HasRequestWorkflow model). */
    public static function syncRequest(Model $request): void
    {
        foreach ($request->personnel()->get() as $profile) {
            self::sync($profile);
        }
    }

    private static function hasActiveRequests(Profile $profile): bool
    {
        foreach (RequestTypes::morphMap() as $model) {
            $active = $model::query()
                ->assignedTo($profile->id)
                ->whereIn('status', RequestStatus::active())
                ->exists();

            if ($active) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sync all profiles attached to a ticket.
     *
     * Queries personnel fresh rather than using $ticket->personnel: that
     * relation is in Ticket's $with, so it's eager-loaded (and cached) the
     * moment the ticket is route-model-bound -- before the same request's
     * attach()/detach() call runs. Reading the cached property here would
     * silently skip whichever profile was just attached (their engagement
     * would never flip to busy) or include one that still needs a stale
     * re-check after being detached.
     */
    public static function syncTicket($ticket): void
    {
        foreach ($ticket->personnel()->get() as $profile) {
            self::sync($profile);
        }
    }
}
