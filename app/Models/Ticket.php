<?php

namespace App\Models;

use App\Enums\ServiceMethod;
use App\Enums\TicketStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Ticket extends Model
{
    use HasFactory;

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
        'reopened_at',
        'released_at',
        'released_by',
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
        'reopened_at' => 'datetime',
    ];

    public static function generateTicketNumber(): string
    {
        $today = Carbon::now()->format('Ymd');
        $count = self::whereDate('created_at', Carbon::today())->count();
        $serial = str_pad($count + 1, 4, '0', STR_PAD_LEFT);

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

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
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
