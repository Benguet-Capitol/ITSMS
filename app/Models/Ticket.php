<?php

namespace App\Models;

use App\Enums\ServiceMethod;
use App\Enums\TicketStatus;
use App\Models\Concerns\LogsActivity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class Ticket extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected static function booted(): void
    {
        // public_id is the safe, non-guessable identifier external
        // surfaces reference instead of the raw sequential id -- generated
        // once here rather than mass-assignable, so it can never be
        // set/overridden via request input.
        static::creating(function (Ticket $ticket) {
            $ticket->public_id ??= (string) Str::ulid();
        });
    }

    protected $with = ['profile', 'employee', 'inventory.parent_component', 'itService', 'personnel', 'item_type', 'solution', 'agency', 'complexityLevel'];

    protected $fillable = [
        'profile_id',
        'inventory_id',
        'item_type_id',
        'it_service_id',
        'agency_id',
        'office_id',
        'office_code',
        'office_desc',
        'solution_id',
        'related_ticket_id',
        'ticket_number',
        'full_name',
        'client_name',
        'concern',
        'query_status',
        'request_status',
        'complexity_level_id',
        'service_method',
        'date',
        'accepted_at',
        'resolved_at',
        'assessed_at',
        'reopened_at',
        'released_at',
        'released_by',
        'cancellation_reason',
        'contact_number',
        'is_other_agency',
        'quality',
        'efficiency',
        'timeliness',
    ];

    protected $casts = [
        'query_status' => TicketStatus::class,
        'request_status' => TicketStatus::class,
        'service_method' => ServiceMethod::class,
        'accepted_at' => 'datetime',
        'resolved_at' => 'datetime',
        'assessed_at' => 'datetime',
        'reopened_at' => 'datetime',
        'feedback_date' => 'date',
    ];

    // query_status values that mean nobody is working the ticket anymore.
    public const FINISHED_QUERY_STATUSES = [
        TicketStatus::Resolved,
        TicketStatus::Assessed,
        TicketStatus::Cancelled,
    ];

    /**
     * One unfinished ticket per inventory item and service type: an item
     * can have a repair ticket and a software-install ticket open at once,
     * but not two repair tickets. $exceptId skips the ticket being edited
     * or reopened.
     */
    public static function hasUnfinishedFor(int|string $inventoryId, int|string $itServiceId, int|string|null $exceptId = null): bool
    {
        return self::query()
            ->setEagerLoads([])
            ->where('inventory_id', $inventoryId)
            ->where('it_service_id', $itServiceId)
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->whereNotIn('query_status', self::FINISHED_QUERY_STATUSES)
            ->exists();
    }

    public function isFinished(): bool
    {
        return in_array($this->query_status, self::FINISHED_QUERY_STATUSES, true);
    }

    /**
     * Whether a lifecycle action may run on this ticket right now -- the
     * same rules the ticket row menu uses to show its buttons, enforced
     * here so a stale page or a direct API call can't skip them.
     */
    public function workflowAllows(string $action, ?Profile $profile, bool $isAdmin = false): bool
    {
        $isMine = $profile !== null
            && $this->personnel()->where('profile_id', $profile->id)->exists();
        $isActive = ! $this->isFinished();

        return match ($action) {
            'accept' => $isActive && ! $isMine,
            'unaccept', 'check_stock', 'resolve' => $isActive && $isMine,
            'await_part' => $isActive && $isMine && $this->query_status !== TicketStatus::AwaitingPart,
            // Assessed tickets stay assessable so the assessment can be edited.
            'assess' => $isMine && ($isActive || $this->query_status === TicketStatus::Assessed),
            'cancel' => $isActive && ($isMine || $isAdmin),
            'reopen' => ! $isActive,
            // An admin puts someone on, or moves it from one person to
            // another, while it's still being worked.
            'assign', 'transfer' => $isActive,
            // The client rates finished work, not a cancelled ticket. Anyone
            // allowed to resolve may record it, on the ticket or not.
            'rate' => in_array($this->query_status, [TicketStatus::Resolved, TicketStatus::Assessed], true),
            default => false,
        };
    }

    public static function generateTicketNumber(): string
    {
        $today = Carbon::now()->format('Ymd');

        // Next after the highest serial already used today, not today's
        // ticket count: deleting a ticket lowers the count and would hand
        // out a number that still exists. Compared as integers so 10000
        // still sorts after 9999. Deleted tickets keep their numbers.
        $lastSerial = self::withTrashed()
            ->setEagerLoads([])
            ->where('ticket_number', 'like', "{$today}-%")
            ->pluck('ticket_number')
            ->map(fn (string $number) => (int) substr($number, strlen($today) + 1))
            ->max() ?? 0;

        $serial = str_pad($lastSerial + 1, 4, '0', STR_PAD_LEFT);

        return "{$today}-{$serial}"; // 2025-0616-0001 / 20250616-0001
    }

    // Accessor: dynamically compute average rating
    public function getComputedRatingAttribute(): ?int
    {
        $scores = [
            $this->quality,
            $this->efficiency,
            $this->timeliness,
        ];

        // Filter out nulls in case some scores aren’t filled
        $validScores = array_filter($scores, fn ($val) => ! is_null($val));

        if (count($validScores) === 3) {
            return (int) round(array_sum($validScores) / 3);
        }

        return null; // no complete rating yet

        /*
        Usage
        $ticket = Ticket::find(1);
        $ticket->computed_rating;
        */
    }

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    // A deleted item still shows on the tickets filed for it.
    public function inventory()
    {
        return $this->belongsTo(Inventory::class)->withTrashed();
    }

    public function activityLabel(): string
    {
        return (string) $this->ticket_number;
    }

    /**
     * The ticket with names instead of ids, who is on it, and its
     * assessment's key fields (LogsActivity).
     */
    public function activitySnapshot(): array
    {
        $this->loadMissing(['profile', 'inventory', 'item_type', 'itService', 'solution', 'agency', 'complexityLevel']);
        $assessment = $this->assessment()->first();

        return [
            ...self::normalizeActivityValues(Arr::only($this->getAttributes(), [
                'ticket_number', 'client_name', 'full_name', 'contact_number', 'concern',
                'office_code', 'office_desc', 'is_other_agency', 'query_status', 'request_status',
                'service_method', 'date', 'accepted_at', 'resolved_at', 'assessed_at', 'reopened_at',
                'released_at', 'released_by', 'cancellation_reason',
                'feedback_rating', 'feedback_name', 'feedback_date', 'deleted_at',
            ]), $this),
            'filed_by' => $this->profile?->display_name,
            'inventory' => $this->inventory?->property_number,
            'item_type' => $this->item_type?->type,
            'service_type' => $this->itService?->name,
            'solution' => $this->solution?->title,
            'agency' => $this->agency?->name,
            'complexity' => $this->complexityLevel?->label,
            'related_ticket' => $this->related_ticket_id
                ? self::withTrashed()->setEagerLoads([])->whereKey($this->related_ticket_id)->value('ticket_number')
                : null,
            'personnel' => $this->personnel()->pluck('display_name')->sort()->values()->all(),
            'assessment_control_number' => $assessment?->control_number,
            'assessment_recommendations' => $assessment?->recommendations,
            'assessment_replacement_available' => $assessment?->replacement_available === null ? null : (int) $assessment->replacement_available,
            'assessment_findings' => $assessment?->component_findings,
        ];
    }

    public function itService()
    {
        return $this->belongsTo(ItService::class);
    }

    public function personnel()
    {
        return $this->belongsToMany(Profile::class, 'ticket_personnel')
            ->using(TicketPersonnel::class)
            ->withTimestamps();
    }

    public function item_type()
    {
        return $this->belongsTo(ItemType::class);
    }

    public function solution()
    {
        return $this->belongsTo(Solution::class);
    }

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }

    public function assessment()
    {
        return $this->hasOne(TicketAssessment::class);
    }

    public function resolutions()
    {
        return $this->hasMany(TicketResolution::class)
            ->orderByDesc('resolved_at')
            ->orderByDesc('id');
    }

    /**
     * Deleting is only for removing a duplicate before anyone works on it:
     * still Open/Queued with nobody on it. Un-accepting puts a ticket back
     * in that state, so it also must never have been resolved or assessed.
     * Uses the loaded personnel when there (the list), else queries.
     */
    public function isDeletable(bool $checkHistory = true): bool
    {
        if ($this->request_status !== TicketStatus::Open || $this->query_status !== TicketStatus::Queued) {
            return false;
        }

        $hasPersonnel = $this->relationLoaded('personnel')
            ? $this->personnel->isNotEmpty()
            : $this->personnel()->exists();

        if ($hasPersonnel) {
            return false;
        }

        return ! $checkHistory
            || (! $this->resolutions()->exists() && ! $this->assessment()->exists());
    }

    public function relatedTicket()
    {
        return $this->belongsTo(Ticket::class, 'related_ticket_id');
    }

    // The reverse side: other tickets that were filed as a recurrence of
    // this one.
    public function recurrences()
    {
        return $this->hasMany(Ticket::class, 'related_ticket_id');
    }

    public function complexityLevel()
    {
        return $this->belongsTo(TicketComplexityLevel::class);
    }
}
