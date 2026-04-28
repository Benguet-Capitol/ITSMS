<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    protected $fillable = [
        'user_id',
        'display_name',
        'name',
        'designation',
        'status',
        'status_text',
        'engagement',
        'gender',
        'img_path',
        'last_seen_at',
    ];

    const STATUS_ONLINE = 'online';
    const STATUS_OFFLINE = 'offline';

    const ENGAGEMENT_READY = 'ready';
    const ENGAGEMENT_BUSY = 'busy';

    public function hasActiveTickets() {
      return $this->ticketPersonnel()
          ->wherePivotNotIn('status', ['resolved', 'cancelled']) // adjust if you have ticket status on pivot
          ->whereIn('tickets.request_status', [
              TicketStatus::Accepted,
              TicketStatus::InProgress,
          ])
          ->exists();
    }

     public function user() {
        return $this->belongsTo(User::class);
    }

    public function ticketPersonnel() {
        return $this->belongsToMany(Ticket::class, 'ticket_personnel', 'profile_id', 'ticket_id');
    }

    public function solutions() {
        return $this->hasMany(Solution::class, 'author_id');
    }

    public function departments() {
        return $this->belongsToMany(Department::class, 'profile_department');
    }

    public function agencies() {
        return $this->belongsToMany(Agency::class, 'profile_agency');
    }

    public function profileOffices() {
        return $this->hasMany(ProfileOffice::class);
    }

    public function getOfficeIdsAttribute(): array {
        return $this->profileOffices->pluck('office_id')->toArray();
    }

    public function getOfficesAssignedAttribute(): array {
        return $this->profileOffices->map(function ($office) {
            return [
                'id' => $office->office_id,
                'office_code' => $office->office_code,
                'office_desc' => $office->office_desc,
                'abbreviation' => $office->office_code,
            ];
        })->toArray();
    }

}
