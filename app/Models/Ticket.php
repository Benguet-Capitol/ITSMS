<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Enums\TicketStatus;
use App\Enums\ServiceMethod;
use App\Models\Solution;

class Ticket extends Model
{
    protected $with = ['profile', 'employee', 'inventory', 'itService', 'personnel', 'item_type', 'solution', 'agency'];

    protected $fillable = [
        'profile_id',
        'inventory_id',
        'item_type_id',
        'it_service_id',
        'agency_id',
        'solution_id',
        'ticket_number',
        'concern',
        'query_status',
        'request_status',
        'priority',
        'service_method',
        'date',
        'released_at',
        'contact_number',
        'full_name',
        'is_other_agency',
        'quality',
        'efficiency',
        'timeliness',
    ];

    protected $casts = [
    'query_status' => TicketStatus::class,
    'request_status' => TicketStatus::class,
    'service_method' => ServiceMethod::class,
    ];

    public static function generateTicketNumber(): string {
      $today = Carbon::now()->format('Ymd');
      $count = self::whereDate('created_at', Carbon::today())->count();
      $serial = str_pad($count + 1, 4, '0', STR_PAD_LEFT);
      return "{$today}-{$serial}"; // 2025-0616-0001 / 20250616-0001
    }

    // Accessor: dynamically compute average rating
    public function getComputedRatingAttribute(): ?int {
        $scores = [
            $this->quality,
            $this->efficiency,
            $this->timeliness,
        ];

        // Filter out nulls in case some scores aren’t filled
        $validScores = array_filter($scores, fn($val) => !is_null($val));

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

    public function profile() {
      return $this->belongsTo(Profile::class);
    }

    public function employee() {
      return $this->belongsTo(Employee::class);
    }

    public function inventory() {
      return $this->belongsTo(Inventory::class);
    }
    
    public function itService() {
      return $this->belongsTo(ItService::class);
    }

    public function personnel() {
      return $this->belongsToMany(Profile::class, 'ticket_personnel')
        ->using(TicketPersonnel::class)
        ->withTimestamps();
    }

    public function item_type() {
      return $this->belongsTo(ItemType::class);
    }

    public function solution() {
        return $this->belongsTo(Solution::class);
    }

    public function agency() {
      return $this->belongsTo(Agency::class);
    }
}
